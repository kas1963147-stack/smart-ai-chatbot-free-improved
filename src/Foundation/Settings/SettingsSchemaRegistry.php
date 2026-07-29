<?php
declare(strict_types=1);
/**
 * Settings Schema Registry
 *
 * Centralized schema for settings defaults, sanitization, and metadata.
 *
 * @package Quarksol\SmartChatbot\Foundation\Settings
 */

namespace Quarksol\SmartChatbot\Foundation\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsSchemaRegistry
{
    /**
     * @var array<string, array>
     */
    private static array $schema = [];

    public static function register(string $key, array $definition): void
    {
        self::$schema[$key] = $definition;
    }

    public static function getSchema(): array
    {
        return self::$schema;
    }

    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::$schema as $key => $definition) {
            if (array_key_exists('default', $definition)) {
                $defaults[$key] = $definition['default'];
            }
        }
        return $defaults;
    }

    /**
     * Sanitize settings based on registered schema.
     */
    public static function sanitize(array $incoming, array $existing = []): array
    {
        $sanitized = [];
        foreach (self::$schema as $key => $definition) {
            $value = self::resolveValue($key, $definition, $incoming, $existing);
            $sanitized[$key] = self::sanitizeValue($value, $definition);
        }
        return $sanitized;
    }

    private static function resolveValue(string $key, array $definition, array $incoming, array $existing): mixed
    {
        if (array_key_exists($key, $incoming)) {
            return $incoming[$key];
        }

        foreach ((array) ($definition['aliases'] ?? []) as $alias) {
            if (array_key_exists($alias, $incoming)) {
                return $incoming[$alias];
            }
        }

        if (array_key_exists($key, $existing)) {
            return $existing[$key];
        }

        foreach ((array) ($definition['aliases'] ?? []) as $alias) {
            if (array_key_exists($alias, $existing)) {
                return $existing[$alias];
            }
        }

        return $definition['default'] ?? null;
    }

    private static function sanitizeValue(mixed $value, array $definition): mixed
    {
        if (isset($definition['sanitize']) && is_callable($definition['sanitize'])) {
            return call_user_func($definition['sanitize'], $value);
        }

        $type = $definition['type'] ?? 'string';
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'int' => (int) $value,
            'float' => (float) $value,
            'text' => sanitize_textarea_field((string) $value),
            'color' => sanitize_hex_color((string) $value) ?: ($definition['default'] ?? ''),
            'url' => esc_url_raw((string) $value),
            'array' => is_array($value) ? $value : (array) ($definition['default'] ?? []),
            default => sanitize_text_field((string) $value),
        };
    }
}
