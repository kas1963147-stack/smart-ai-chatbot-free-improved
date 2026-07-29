<?php
declare(strict_types=1);
/**
 * Core Settings Schema
 *
 * Registers baseline settings with defaults and sanitizers.
 *
 * @package Quarksol\SmartChatbot\Foundation\Settings
 */

namespace Quarksol\SmartChatbot\Foundation\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class CoreSettingsSchema
{
    public static function register(): void
    {
        $positionSanitizer = static function ($value): string {
            $value = sanitize_text_field((string) $value);
            return in_array($value, ['left', 'right'], true) ? $value : 'right';
        };

        SettingsSchemaRegistry::register('enabled', [
            'type' => 'bool',
            'default' => false,
        ]);
        SettingsSchemaRegistry::register('ai_enabled', [
            'type' => 'bool',
            'default' => false,
        ]);
        SettingsSchemaRegistry::register('guest_order_lookup', [
            'type' => 'bool',
            'default' => false,
        ]);
        SettingsSchemaRegistry::register('enable_logging', [
            'type' => 'bool',
            'default' => false,
        ]);
        SettingsSchemaRegistry::register('enable_streaming', [
            'type' => 'bool',
            'default' => true,
            'aliases' => ['streaming_enabled'],
        ]);
        SettingsSchemaRegistry::register('enable_proactive', [
            'type' => 'bool',
            'default' => false,
            'aliases' => ['proactive_enabled'],
        ]);
        SettingsSchemaRegistry::register('enable_history', [
            'type' => 'bool',
            'default' => true,
        ]);
        SettingsSchemaRegistry::register('enable_analytics', [
            'type' => 'bool',
            'default' => true,
        ]);
        SettingsSchemaRegistry::register('enable_rate_limiting', [
            'type' => 'bool',
            'default' => true,
            'aliases' => ['rate_limiting'],
        ]);

        SettingsSchemaRegistry::register('bot_name', [
            'type' => 'string',
            'default' => 'Shopping Assistant',
        ]);
        SettingsSchemaRegistry::register('welcome_message', [
            'type' => 'text',
            'default' => '',
        ]);
        SettingsSchemaRegistry::register('ai_provider', [
            'type' => 'string',
            'default' => 'openai',
        ]);
        SettingsSchemaRegistry::register('ai_model', [
            'type' => 'string',
            'default' => '',
        ]);
        SettingsSchemaRegistry::register('ai_base_url', [
            'type' => 'url',
            'default' => '',
        ]);
        SettingsSchemaRegistry::register('ai_system_prompt', [
            'type' => 'text',
            'default' => '',
        ]);
        SettingsSchemaRegistry::register('primary_color', [
            'type' => 'color',
            'default' => '#6366f1',
            'aliases' => ['color_primary'],
        ]);
        SettingsSchemaRegistry::register('position', [
            'default' => 'right',
            'sanitize' => $positionSanitizer,
        ]);

        SettingsSchemaRegistry::register('ai_temperature', [
            'type' => 'float',
            'default' => 0.7,
            'aliases' => ['temperature'],
        ]);
        SettingsSchemaRegistry::register('max_tokens', [
            'type' => 'int',
            'default' => 1000,
        ]);
        SettingsSchemaRegistry::register('proactive_delay', [
            'type' => 'int',
            'default' => 30,
            'aliases' => ['proactiveDelay'],
        ]);
        SettingsSchemaRegistry::register('max_history_messages', [
            'type' => 'int',
            'default' => 50,
        ]);
        SettingsSchemaRegistry::register('dark_mode', [
            'type' => 'bool',
            'default' => false,
        ]);
        SettingsSchemaRegistry::register('onboarding_completed', [
            'type' => 'bool',
            'default' => false,
        ]);
        SettingsSchemaRegistry::register('intro_completed', [
            'type' => 'bool',
            'default' => false,
        ]);
    }
}
