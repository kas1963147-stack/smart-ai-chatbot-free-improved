<?php
declare(strict_types=1);
/**
 * Settings Validator Service
 * 
 * Validates and sanitizes plugin settings with defaults.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Validator
 */
class SettingsValidator {
    
    /**
     * Default settings values
     */
    public const DEFAULTS = [
        'enabled' => true,
        'ai_enabled' => false,
        'ai_provider' => 'openai',
        'ai_api_key' => '',
        'ai_model' => '',
        'ai_base_url' => '',
        'max_tokens' => 4096,
        'temperature' => 0.7,
        'welcome_message' => 'Hi! How can I help you today?',
        'position' => 'right',
        'primary_color' => '#4F46E5',
        'enable_streaming' => true,
        'enable_proactive' => false,
        'proactive_delay' => 30,
        'enable_history' => true,
        'max_history_messages' => 50,
        'enable_analytics' => true,
        'enable_rate_limiting' => true,
    ];
    
    /**
     * Validate and sanitize settings
     * 
     * @param array|mixed $settings Raw settings from database
     * @return array Validated settings with defaults
     */
    public static function validate($settings): array {
        if (!is_array($settings)) {
            return self::DEFAULTS;
        }
        
        return [
            'enabled' => (bool) ($settings['enabled'] ?? self::DEFAULTS['enabled']),
            'ai_enabled' => (bool) ($settings['ai_enabled'] ?? self::DEFAULTS['ai_enabled']),
            'ai_provider' => self::sanitizeProvider($settings['ai_provider'] ?? self::DEFAULTS['ai_provider']),
            'ai_api_key' => sanitize_text_field($settings['ai_api_key'] ?? self::DEFAULTS['ai_api_key']),
            'ai_model' => sanitize_text_field($settings['ai_model'] ?? self::DEFAULTS['ai_model']),
            'ai_base_url' => esc_url_raw($settings['ai_base_url'] ?? self::DEFAULTS['ai_base_url']),
            'max_tokens' => self::sanitizeInt($settings['max_tokens'] ?? self::DEFAULTS['max_tokens'], 100, 32000),
            'temperature' => self::sanitizeFloat($settings['temperature'] ?? self::DEFAULTS['temperature'], 0, 2),
            'welcome_message' => sanitize_textarea_field($settings['welcome_message'] ?? self::DEFAULTS['welcome_message']),
            'position' => in_array($settings['position'] ?? '', ['left', 'right']) ? $settings['position'] : self::DEFAULTS['position'],
            'primary_color' => sanitize_hex_color($settings['primary_color'] ?? self::DEFAULTS['primary_color']) ?: self::DEFAULTS['primary_color'],
            'enable_streaming' => (bool) ($settings['enable_streaming'] ?? self::DEFAULTS['enable_streaming']),
            'enable_proactive' => (bool) ($settings['enable_proactive'] ?? self::DEFAULTS['enable_proactive']),
            'proactive_delay' => self::sanitizeInt($settings['proactive_delay'] ?? self::DEFAULTS['proactive_delay'], 5, 300),
            'enable_history' => (bool) ($settings['enable_history'] ?? self::DEFAULTS['enable_history']),
            'max_history_messages' => self::sanitizeInt($settings['max_history_messages'] ?? self::DEFAULTS['max_history_messages'], 10, 200),
            'enable_analytics' => (bool) ($settings['enable_analytics'] ?? self::DEFAULTS['enable_analytics']),
            'enable_rate_limiting' => (bool) ($settings['enable_rate_limiting'] ?? self::DEFAULTS['enable_rate_limiting']),
        ];
    }
    
    /**
     * Get validated settings from database
     */
    public static function get(): array {
        $settings = get_option('swc_chatbot_settings', []);
        return self::validate($settings);
    }
    
    /**
     * Get single setting value
     */
    public static function getSetting(string $key, $default = null) {
        $settings = self::get();
        return $settings[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }
    
    /**
     * Sanitize provider name
     */
    private static function sanitizeProvider(string $provider): string {
        $validProviders = [
            'openai', 'anthropic', 'openrouter', 'gemini', 'groq',
            'deepseek', 'mistral', 'ollama', 'lmstudio', 'xai',
            'fireworks', 'cerebras', 'sambanova', 'azure', 'bedrock', 'vertex'
        ];
        
        $provider = sanitize_key($provider);
        return in_array($provider, $validProviders) ? $provider : self::DEFAULTS['ai_provider'];
    }
    
    /**
     * Sanitize integer within range
     */
    private static function sanitizeInt($value, int $min, int $max): int {
        $value = (int) $value;
        return max($min, min($max, $value));
    }
    
    /**
     * Sanitize float within range
     */
    private static function sanitizeFloat($value, float $min, float $max): float {
        $value = (float) $value;
        return max($min, min($max, $value));
    }
}
