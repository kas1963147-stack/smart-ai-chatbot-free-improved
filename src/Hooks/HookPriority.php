<?php
declare(strict_types=1);
/**
 * Hook Priority Constants
 * 
 * Defines standard priority levels for WordPress hooks to ensure
 * consistent ordering across the plugin.
 * 
 * @package Quarksol\SmartChatbot\Hooks
 */

namespace Quarksol\SmartChatbot\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook Priority Constants
 * 
 * Use these constants when adding filters/actions to ensure
 * consistent execution order.
 */
class HookPriority
{

    /**
     * FIRST - Security checks, validation, early exits
     * Use for: Permission checks, rate limiting, input validation
     */
    public const FIRST = 1;

    /**
     * EARLY - Core modifications before standard processing
     * Use for: Core plugin features, essential transformations
     */
    public const EARLY = 5;

    /**
     * DEFAULT - Standard priority for most hooks
     * Use for: Regular plugin integrations, typical modifications
     */
    public const DEFAULT = 10;

    /**
     * LATE - Modifications after standard processing
     * Use for: Final adjustments, formatting, cleanup
     */
    public const LATE = 15;

    /**
     * LAST - Final processing, logging, analytics
     * Use for: Logging, analytics, non-modifying observers
     */
    public const LAST = 20;

    /**
     * Get priority name for debugging
     * 
     * @param int $priority Priority value
     * @return string Priority name
     */
    public static function getName(int $priority): string
    {
        return match ($priority) {
            self::FIRST => 'FIRST',
            self::EARLY => 'EARLY',
            self::DEFAULT => 'DEFAULT',
            self::LATE => 'LATE',
            self::LAST => 'LAST',
            default => "CUSTOM($priority)",
        };
    }

    /**
     * Get all priority levels
     * 
     * @return array<string, int>
     */
    public static function all(): array
    {
        return [
            'FIRST' => self::FIRST,
            'EARLY' => self::EARLY,
            'DEFAULT' => self::DEFAULT ,
            'LATE' => self::LATE,
            'LAST' => self::LAST,
        ];
    }
}
