<?php
declare(strict_types=1);
/**
 * Encrypted Settings Service
 * 
 * Provides secure storage for sensitive settings like API keys.
 * Uses AES-256-GCM encryption with WordPress AUTH_KEY.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encrypted Settings Service
 * 
 * Encrypts sensitive data before storing in WordPress options.
 */
class EncryptedSettings
{
    /**
     * Encryption cipher
     */
    private const CIPHER = 'aes-256-gcm';
    
    /**
     * Option name for encrypted settings
     */
    private const OPTION_NAME = 'swc_encrypted_settings';
    
    /**
     * Tag length for GCM mode
     */
    private const TAG_LENGTH = 16;
    
    /**
     * Encrypt a value
     * 
     * @param string $value The value to encrypt
     * @return string Base64-encoded encrypted value with IV and tag
     */
    public static function encrypt(string $value): string
    {
        $key = self::getKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $value,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );
        
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }
        
        // Combine IV + Tag + Encrypted data
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt a value
     * 
     * @param string $encrypted Base64-encoded encrypted value
     * @return string The decrypted value
     */
    public static function decrypt(string $encrypted): string
    {
        $key = self::getKey();
        $data = base64_decode($encrypted, true);
        
        if ($data === false) {
            throw new \InvalidArgumentException('Invalid encrypted data');
        }
        
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        
        if (strlen($data) < $ivLength + self::TAG_LENGTH) {
            throw new \InvalidArgumentException('Encrypted data too short');
        }
        
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($data, $ivLength + self::TAG_LENGTH);
        
        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed - data may be corrupted');
        }
        
        return $decrypted;
    }
    
    /**
     * Get the encryption key
     * Derives a 256-bit key from WordPress AUTH_KEY
     */
    private static function getKey(): string
    {
        return hash('sha256', wp_salt('auth') . 'swc_encrypted_settings_v1', true);
    }
    
    /**
     * Save an encrypted API key
     * 
     * @param string $provider Provider identifier (openai, anthropic, etc.)
     * @param string $key The API key to store
     */
    public static function saveApiKey(string $provider, string $key): void
    {
        if (empty($key)) {
            self::deleteApiKey($provider);
            return;
        }
        
        $settings = get_option(self::OPTION_NAME, []);
        $settings['api_keys'][$provider] = self::encrypt($key);
        $settings['updated_at'] = time();
        
        update_option(self::OPTION_NAME, $settings);
    }
    
    /**
     * Get a decrypted API key
     * 
     * @param string $provider Provider identifier
     * @return string|null The API key or null if not found
     */
    public static function getApiKey(string $provider): ?string
    {
        $settings = get_option(self::OPTION_NAME, []);
        
        if (!isset($settings['api_keys'][$provider])) {
            return null;
        }
        
        try {
            return self::decrypt($settings['api_keys'][$provider]);
        } catch (\Throwable $e) {
            // Log error but don't expose details
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                Logger::error('API key decryption failed', [
                    'provider' => $provider,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Delete an API key
     * 
     * @param string $provider Provider identifier
     */
    public static function deleteApiKey(string $provider): void
    {
        $settings = get_option(self::OPTION_NAME, []);
        
        if (isset($settings['api_keys'][$provider])) {
            unset($settings['api_keys'][$provider]);
            $settings['updated_at'] = time();
            update_option(self::OPTION_NAME, $settings);
        }
    }
    
    /**
     * Check if an API key exists for a provider
     * 
     * @param string $provider Provider identifier
     * @return bool Whether a key exists
     */
    public static function hasApiKey(string $provider): bool
    {
        $settings = get_option(self::OPTION_NAME, []);
        return isset($settings['api_keys'][$provider]);
    }
    
    /**
     * Get masked version of API key (for display)
     * 
     * @param string $provider Provider identifier
     * @return string|null Masked key like "sk-12...AB" or null
     */
    public static function getMaskedApiKey(string $provider): ?string
    {
        $key = self::getApiKey($provider);
        
        if (!$key || strlen($key) < 10) {
            return null;
        }
        
        // Show first 4 and last 2 characters
        return substr($key, 0, 4) . '...' . substr($key, -2);
    }
    
    /**
     * Migrate plaintext API keys to encrypted storage
     * 
     * Call this during plugin upgrade to migrate existing keys.
     */
    public static function migrateFromPlaintext(): int
    {
        $migrated = 0;
        $settings = get_option('swc_chatbot_settings', []);
        
        $keyMappings = [
            'ai_api_key' => 'default',
            'openai_api_key' => 'openai',
            'anthropic_api_key' => 'anthropic',
            'openrouter_api_key' => 'openrouter',
            'gemini_api_key' => 'gemini',
            'groq_api_key' => 'groq',
            'deepseek_api_key' => 'deepseek',
        ];
        
        foreach ($keyMappings as $oldKey => $provider) {
            if (!empty($settings[$oldKey]) && !self::hasApiKey($provider)) {
                self::saveApiKey($provider, $settings[$oldKey]);
                
                // Remove plaintext key from settings
                $settings[$oldKey] = '';
                $migrated++;
            }
        }
        
        if ($migrated > 0) {
            update_option('swc_chatbot_settings', $settings);
        }
        
        return $migrated;
    }
    
    /**
     * Rotate encryption key
     * 
     * Re-encrypts all data with a new key. Call after changing AUTH_KEY.
     * 
     * @param string $oldKey The previous AUTH_KEY
     */
    public static function rotateKey(string $oldKey): void
    {
        $settings = get_option(self::OPTION_NAME, []);
        
        if (empty($settings['api_keys'])) {
            return;
        }
        
        // Temporarily use old key to decrypt
        $decryptedKeys = [];
        foreach ($settings['api_keys'] as $provider => $encrypted) {
            try {
                $key = hash('sha256', $oldKey . 'swc_encrypted_settings_v1', true);
                
                $data = base64_decode($encrypted, true);
                $ivLength = openssl_cipher_iv_length(self::CIPHER);
                $iv = substr($data, 0, $ivLength);
                $tag = substr($data, $ivLength, self::TAG_LENGTH);
                $ciphertext = substr($data, $ivLength + self::TAG_LENGTH);
                
                $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
                
                if ($decrypted !== false) {
                    $decryptedKeys[$provider] = $decrypted;
                }
            } catch (\Throwable $e) {
                // Skip keys that can't be decrypted
            }
        }
        
        // Re-encrypt with new key
        foreach ($decryptedKeys as $provider => $key) {
            self::saveApiKey($provider, $key);
        }
    }
}
