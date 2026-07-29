<?php
declare(strict_types=1);
/**
 * Configuration Service
 * 
 * Centralizes access to plugin settings.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Types\ProviderSettings;

if (!defined('ABSPATH')) {
    exit;
}

class Config {
    
    private const OPTION_NAME = 'swc_chatbot_settings';
    
    /**
     * Get all settings as array
     */
    public static function getAll(): array {
        return get_option(self::OPTION_NAME, []);
    }
    
    /**
     * Get specific setting value
     */
    public static function get(string $key, $default = null) {
        $settings = self::getAll();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Get typed Provider Settings
     */
    public static function getProviderSettings(): ProviderSettings {
        $data = self::getAll();
        
        // Map legacy keys to ProviderSettings properties if needed
        // ProviderSettings::fromArray handles standard mapping
        
        // Legacy 'ai_provider' -> 'apiProvider'
        if (isset($data['ai_provider']) && !isset($data['apiProvider'])) {
            $data['apiProvider'] = $data['ai_provider'];
        }
        
        // Legacy 'ai_api_key' -> 'apiKey' (or provider specific)
        if (isset($data['ai_api_key'])) {
            $provider = $data['ai_provider'] ?? 'openai';
            if ($provider === 'openai') {
                $data['openAiApiKey'] = $data['ai_api_key'];
            } else {
                $data['apiKey'] = $data['ai_api_key'];
            }
        }
        
        return ProviderSettings::fromArray($data);
    }
    
    /**
     * Check if AI is enabled
     */
    public static function isAiEnabled(): bool {
        return (bool) self::get('ai_enabled', false);
    }
}
