<?php
namespace Quarksol\SmartChatbot\Config;

defined('ABSPATH') || exit;

/**
 * Class SettingsManager
 * 
 * Centralized settings management for the chatbot.
 * 
 * @package Quarksol\SmartChatbot\Config
 */
class SettingsManager {

    private const OPTION_NAME = 'swc_chatbot_settings';

    /**
     * Get all settings with defaults applied
     * 
     * @return array
     */
    public static function getSettings() {
        $settings = get_option(self::OPTION_NAME, []);
        return self::applyDefaults($settings);
    }

    /**
     * Get a specific setting value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::getSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Apply default values to settings
     * 
     * @param array $settings
     * @return array
     */
    private static function applyDefaults($settings) {
        $defaults = [
            'enabled' => false,
            'bot_name' => 'Shopping Assistant',
            'welcome_message' => 'Hi! How can I help you today?',
            'color_primary' => '#6366f1', // Standardized key
            'primary_color' => '#6366f1', // Legacy alias
            'position' => 'right',
            'rate_limiting' => true,
            'enable_rate_limiting' => true,
            'enable_streaming' => true,
            'enable_proactive' => false,
            'proactive_delay' => 30,
            'enable_logging' => true,
        ];

        if (class_exists('\Quarksol\SmartChatbot\Foundation\Settings\\SettingsSchemaRegistry')) {
            $schemaDefaults = \Quarksol\SmartChatbot\Foundation\Settings\SettingsSchemaRegistry::defaults();
            if (!empty($schemaDefaults)) {
                $defaults = array_merge($schemaDefaults, $defaults);
            }
        }

        $settings = is_array($settings) ? $settings : [];

        // Alias mapping and normalization
        if (!array_key_exists('color_primary', $settings) && isset($settings['primary_color'])) {
            $settings['color_primary'] = $settings['primary_color'];
        }
        if (!array_key_exists('primary_color', $settings) && isset($settings['color_primary'])) {
            $settings['primary_color'] = $settings['color_primary'];
        }

        if (!array_key_exists('enable_rate_limiting', $settings) && isset($settings['rate_limiting'])) {
            $settings['enable_rate_limiting'] = (bool) $settings['rate_limiting'];
        }
        if (!array_key_exists('enable_streaming', $settings) && isset($settings['streaming_enabled'])) {
            $settings['enable_streaming'] = (bool) $settings['streaming_enabled'];
        }
        if (!array_key_exists('streaming_enabled', $settings) && isset($settings['enable_streaming'])) {
            $settings['streaming_enabled'] = (bool) $settings['enable_streaming'];
        }
        if (!array_key_exists('enable_proactive', $settings) && isset($settings['proactive_enabled'])) {
            $settings['enable_proactive'] = (bool) $settings['proactive_enabled'];
        }
        if (!array_key_exists('proactive_enabled', $settings) && isset($settings['enable_proactive'])) {
            $settings['proactive_enabled'] = (bool) $settings['enable_proactive'];
        }
        if (!array_key_exists('proactive_delay', $settings) && isset($settings['proactiveDelay'])) {
            $settings['proactive_delay'] = (int) $settings['proactiveDelay'];
        }

        return wp_parse_args($settings, $defaults);
    }
}
