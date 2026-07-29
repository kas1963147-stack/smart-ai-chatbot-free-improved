<?php
declare(strict_types=1);
/**
 * Extension API Contract
 *
 * Defines stable versioning for external modules/addons.
 *
 * @package Quarksol\SmartChatbot\Foundation
 */

namespace Quarksol\SmartChatbot\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionApi
{
    public const VERSION = '1.0.0';
    public const MIN_SUPPORTED = '1.0.0';
    public const REST_NAMESPACE = 'smart-ai-chatbot/v1';

    /**
     * Versions deprecated but still accepted.
     *
     * @var string[]
     */
    private const DEPRECATED = [];

    public static function getVersion(): string
    {
        return self::VERSION;
    }

    public static function getRestNamespace(): string
    {
        return self::REST_NAMESPACE;
    }

    public static function isCompatible(string $version): bool
    {
        return version_compare($version, self::MIN_SUPPORTED, '>=');
    }

    public static function isDeprecated(string $version): bool
    {
        return in_array($version, self::DEPRECATED, true);
    }

    public static function getCompatibilityReport(string $version): array
    {
        return [
            'version' => $version,
            'supported' => self::isCompatible($version),
            'deprecated' => self::isDeprecated($version),
            'min_supported' => self::MIN_SUPPORTED,
            'current' => self::VERSION,
        ];
    }
}
