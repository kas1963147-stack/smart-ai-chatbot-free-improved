<?php
declare(strict_types=1);
/**
 * Session Token Service
 * 
 * Provides secure session tokens for public API authentication.
 * Uses HMAC signatures with IP binding and expiration.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session Token Service
 * 
 * Generates and validates secure session tokens for anonymous users.
 */
class SessionToken
{
    /**
     * Token expiration in seconds (24 hours)
     */
    private const EXPIRATION = 86400;
    
    /**
     * Token prefix for identification
     */
    private const PREFIX = 'swc_';
    
    /**
     * Generate a new session token
     * 
     * @param string|null $sessionId Optional session ID to bind to
     * @param bool $bindIp Whether to bind token to IP address
     * @return string The generated token
     */
    public static function generate(?string $sessionId = null, bool $bindIp = true): string
    {
        $data = [
            'sid' => $sessionId ?: wp_generate_uuid4(),
            'exp' => time() + self::EXPIRATION,
            'nonce' => bin2hex(random_bytes(8)),
        ];
        
        if ($bindIp) {
            $data['ip'] = self::getClientIP();
        }
        
        $payload = base64_encode(wp_json_encode($data));
        $signature = self::sign($payload);
        
        return self::PREFIX . $payload . '.' . $signature;
    }
    
    /**
     * Validate a session token
     * 
     * @param string $token The token to validate
     * @param bool $checkIp Whether to verify IP binding
     * @return array|false Token data if valid, false otherwise
     */
    public static function validate(string $token, bool $checkIp = true): array|false
    {
        $rawToken = $token;
        // Check prefix
        if (!str_starts_with($token, self::PREFIX)) {
            return false;
        }

        if (self::isBlacklisted($rawToken)) {
            return false;
        }
        
        $token = substr($token, strlen(self::PREFIX));
        
        // Split payload and signature
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        [$payload, $signature] = $parts;
        
        // Verify signature
        if (!hash_equals(self::sign($payload), $signature)) {
            return false;
        }
        
        // Decode payload
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return false;
        }
        
        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return false;
        }
        
        // Check expiration
        if (!isset($data['exp']) || $data['exp'] < time()) {
            return false;
        }
        
        // Check IP binding
        if ($checkIp && isset($data['ip'])) {
            if ($data['ip'] !== self::getClientIP()) {
                return false;
            }
        }
        
        return $data;
    }
    
    /**
     * Refresh a session token (extend expiration)
     * 
     * @param string $token The token to refresh
     * @return string|false New token if valid, false otherwise
     */
    public static function refresh(string $token): string|false
    {
        $data = self::validate($token);
        if ($data === false) {
            return false;
        }
        
        return self::generate($data['sid'] ?? null, isset($data['ip']));
    }
    
    /**
     * Extract session ID from token without full validation
     * 
     * @param string $token The token
     * @return string|null The session ID or null
     */
    public static function extractSessionId(string $token): ?string
    {
        if (!str_starts_with($token, self::PREFIX)) {
            return null;
        }
        
        $token = substr($token, strlen(self::PREFIX));
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        
        $decoded = base64_decode($parts[0], true);
        if ($decoded === false) {
            return null;
        }
        
        $data = json_decode($decoded, true);
        return $data['sid'] ?? null;
    }
    
    /**
     * Create HMAC signature for payload
     */
    private static function sign(string $payload): string
    {
        $key = self::getSigningKey();
        return hash_hmac('sha256', $payload, $key);
    }
    
    /**
     * Get the signing key
     * Uses WordPress AUTH_KEY with plugin-specific salt
     */
    private static function getSigningKey(): string
    {
        return hash('sha256', wp_salt('auth') . 'swc_session_token_v1');
    }
    
    /**
     * Get client IP address safely
     * Only trusts REMOTE_ADDR to prevent spoofing
     */
    private static function getClientIP(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '127.0.0.1';
    }
    
    /**
     * Get remaining time until token expires
     * 
     * @param string $token The token
     * @return int Seconds until expiration, 0 if expired/invalid
     */
    public static function getTimeRemaining(string $token): int
    {
        $data = self::validate($token, false);
        if ($data === false) {
            return 0;
        }
        
        return max(0, ($data['exp'] ?? 0) - time());
    }
    
    /**
     * Invalidate a token (mark as used for one-time tokens)
     * 
     * @param string $token The token to invalidate
     * @return bool Success
     */
    public static function invalidate(string $token): bool
    {
        $data = self::validate($token, false);
        if ($data === false) {
            return false;
        }
        
        // Store in transient to blacklist
        $key = 'swc_token_blacklist_' . substr(hash('sha256', $token), 0, 12);
        set_transient($key, true, self::EXPIRATION);
        
        return true;
    }
    
    /**
     * Check if token is blacklisted
     */
    public static function isBlacklisted(string $token): bool
    {
        $key = 'swc_token_blacklist_' . substr(hash('sha256', $token), 0, 12);
        return (bool) get_transient($key);
    }
}
