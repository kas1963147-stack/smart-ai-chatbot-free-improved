<?php
declare(strict_types=1);
/**
 * Hooks Facade
 * 
 * Provides a clean API for applying filters and actions with
 * automatic tracing and documentation.
 * 
 * @package Quarksol\SmartChatbot\Hooks
 */

namespace Quarksol\SmartChatbot\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks Facade
 * 
 * Use this class instead of raw apply_filters/do_action for
 * automatic tracing and consistent behavior.
 */
class Hooks
{

    /**
     * Apply a filter with tracing
     * 
     * @param string $hook Hook name
     * @param mixed $value Value to filter
     * @param mixed ...$args Additional arguments
     * @return mixed Filtered value
     */
    public static function filter(string $hook, $value, ...$args)
    {
        $result = apply_filters($hook, $value, ...$args);

        if (HookRegistry::isTracingEnabled()) {
            HookRegistry::trace($hook, [$value, ...$args], $result);
        }

        return $result;
    }

    /**
     * Execute an action with tracing
     * 
     * @param string $hook Hook name
     * @param mixed ...$args Arguments
     */
    public static function action(string $hook, ...$args): void
    {
        do_action($hook, ...$args);

        if (HookRegistry::isTracingEnabled()) {
            HookRegistry::trace($hook, $args);
        }
    }

    /**
     * Apply a filter that returns a payload object
     * 
     * @param string $hook Hook name  
     * @param HookPayload $payload Payload to filter
     * @param mixed ...$args Additional arguments
     * @return HookPayload Filtered payload
     */
    public static function filterPayload(string $hook, HookPayload $payload, ...$args): HookPayload
    {
        $result = apply_filters($hook, $payload, ...$args);

        if (HookRegistry::isTracingEnabled()) {
            HookRegistry::trace($hook, [$payload, ...$args], $result);
        }

        // Ensure we always return a HookPayload
        if (!$result instanceof HookPayload) {
            return $payload;
        }

        return $result;
    }

    /**
     * Check if any callbacks are registered for a hook
     * 
     * @param string $hook Hook name
     * @return bool
     */
    public static function hasCallbacks(string $hook): bool
    {
        return has_filter($hook);
    }

    /**
     * Get the number of callbacks for a hook
     * 
     * @param string $hook Hook name
     * @return int
     */
    public static function countCallbacks(string $hook): int
    {
        global $wp_filter;

        if (!isset($wp_filter[$hook])) {
            return 0;
        }

        $count = 0;
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            $count += count($callbacks);
        }

        return $count;
    }
}
