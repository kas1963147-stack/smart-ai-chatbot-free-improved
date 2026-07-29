<?php
declare(strict_types=1);
/**
 * MCP Security Policy
 *
 * Controls which external MCP tools are allowed in different contexts
 * (admin panel vs. frontend widget). Implements a "deny by default,
 * allow selectively" approach for widget/public contexts.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Config\AgentConfig;

if (!defined('ABSPATH')) {
    exit;
}

class McpSecurityPolicy
{
    /**
     * Context constants
     */
    const CONTEXT_ADMIN = 'admin';
    const CONTEXT_AUTHENTICATED = 'authenticated';
    const CONTEXT_WIDGET = 'widget';

    /**
     * Cached context for current request
     */
    private static ?string $currentContext = null;

    /**
     * Whether context was manually overridden (e.g., for testing)
     */
    private static bool $contextOverridden = false;

    // =========================================================================
    // CONTEXT DETECTION
    // =========================================================================

    /**
     * Detect the current request context.
     *
     * Priority:
     *  1. Manual override (setContext)
     *  2. WP-CLI → admin
     *  3. Admin user with manage_options → admin
     *  4. Logged-in user → authenticated
     *  5. Everything else (AJAX nopriv, REST without auth) → widget
     */
    public static function detectContext(): string
    {
        if (self::$contextOverridden && self::$currentContext !== null) {
            return self::$currentContext;
        }

        // WP-CLI is always admin
        if (defined('WP_CLI') && WP_CLI) {
            return self::CONTEXT_ADMIN;
        }

        // Admin user
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return self::CONTEXT_ADMIN;
        }

        // Logged-in user (non-admin)
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return self::CONTEXT_AUTHENTICATED;
        }

        // Default: widget/public
        return self::CONTEXT_WIDGET;
    }

    /**
     * Get the current context (with caching per request).
     */
    public static function getContext(): string
    {
        if (self::$currentContext === null) {
            self::$currentContext = self::detectContext();

            // Log context detection details for debugging
            $userId = function_exists('get_current_user_id') ? get_current_user_id() : 0;
            $isLoggedIn = function_exists('is_user_logged_in') && is_user_logged_in();
            $canManage = function_exists('current_user_can') && current_user_can('manage_options');
            $isWpCli = defined('WP_CLI') && WP_CLI;
            $overridden = self::$contextOverridden ? 'YES' : 'no';

            error_log(sprintf(
                '[McpSecurity]  Context detected: "%s" | user_id=%d | logged_in=%s | is_admin=%s | wp_cli=%s | overridden=%s',
                self::$currentContext,
                $userId,
                $isLoggedIn ? 'YES' : 'no',
                $canManage ? 'YES' : 'no',
                $isWpCli ? 'YES' : 'no',
                $overridden
            ));
        }

        return self::$currentContext;
    }

    /**
     * Manually set the context (useful for testing or forced contexts).
     */
    public static function setContext(string $context): void
    {
        self::$currentContext = $context;
        self::$contextOverridden = true;
    }

    /**
     * Reset context (clear cache and override).
     */
    public static function resetContext(): void
    {
        self::$currentContext = null;
        self::$contextOverridden = false;
    }

    /**
     * Check if current context is a widget/public context.
     */
    public static function isWidgetContext(): bool
    {
        return self::getContext() === self::CONTEXT_WIDGET;
    }

    /**
     * Check if current context is admin.
     */
    public static function isAdminContext(): bool
    {
        return self::getContext() === self::CONTEXT_ADMIN;
    }

    // =========================================================================
    // MCP TOOL FILTERING
    // =========================================================================

    /**
     * Check if external MCP tools are allowed for the current context + agent config.
     *
     * In admin context → always allowed (full access).
     * In widget context → only if the agent has widget-allowed MCP tools configured.
     * In authenticated context → same as widget (conservative default).
     */
    public static function canUseExternalMcp(AgentConfig $config): bool
    {
        if (self::isAdminContext()) {
            return true;
        }

        // For non-admin contexts, check if agent has any widget-allowed MCP tools
        $widgetSecurity = $config->mcpWidgetSecurity;

        // If widget MCP is explicitly disabled
        if (isset($widgetSecurity['allow_external_mcp']) && !$widgetSecurity['allow_external_mcp']) {
            return false;
        }

        // If there are widget-allowed tools configured, external MCP is allowed (filtered)
        if (!empty($widgetSecurity['allowed_tools'])) {
            return true;
        }

        // Default: blocked for non-admin
        return false;
    }

    /**
     * Filter MCP tools for the current context.
     *
     * In admin context → return all tools unfiltered.
     * In widget/auth context → only return tools in the widget allowlist.
     *
     * @param array $tools Array of Tool objects (NeuronAI\Tools\Tool)
     * @param AgentConfig $config The agent's configuration
     * @return array Filtered tools
     */
    public static function filterMcpToolsForContext(array $tools, AgentConfig $config): array
    {
        // Admin gets everything
        if (self::isAdminContext()) {
            return $tools;
        }

        $widgetSecurity = $config->mcpWidgetSecurity;

        // If external MCP is explicitly disabled for widget
        if (isset($widgetSecurity['allow_external_mcp']) && !$widgetSecurity['allow_external_mcp']) {
            error_log('[McpSecurity] External MCP blocked for widget context (explicitly disabled)');
            return [];
        }

        // Get the whitelist of allowed tool names
        $allowedTools = $widgetSecurity['allowed_tools'] ?? [];

        // If no tools are whitelisted, block all
        if (empty($allowedTools)) {
            error_log('[McpSecurity] External MCP blocked for widget context (no whitelisted tools)');
            return [];
        }

        // Get the blocklist (always blocked regardless of whitelist)
        $blockedTools = $widgetSecurity['blocked_tools'] ?? [];

        // Filter: only keep tools that are in the allowlist AND not in the blocklist
        $filtered = array_filter($tools, function ($tool) use ($allowedTools, $blockedTools) {
            $toolName = method_exists($tool, 'getName') ? $tool->getName() : '';

            if (empty($toolName)) {
                return false;
            }

            // Blocked tools are always rejected
            if (in_array($toolName, $blockedTools, true)) {
                return false;
            }

            // Check against allowlist (supports exact match and wildcard prefix)
            foreach ($allowedTools as $allowed) {
                // Exact match
                if ($allowed === $toolName) {
                    return true;
                }

                // Wildcard prefix: "GOOGLECALENDAR_*" matches "GOOGLECALENDAR_CREATE_EVENT"
                if (str_ends_with($allowed, '*')) {
                    $prefix = substr($allowed, 0, -1);
                    if (str_starts_with($toolName, $prefix)) {
                        return true;
                    }
                }
            }

            return false;
        });

        $filteredCount = count($filtered);
        $totalCount = count($tools);
        error_log("[McpSecurity] Widget context: allowed {$filteredCount}/{$totalCount} external MCP tools");

        return array_values($filtered);
    }

    /**
     * Get the widget security configuration for an agent, with defaults.
     *
     * @param AgentConfig $config
     * @return array Normalized widget security config
     */
    public static function getWidgetSecurityConfig(AgentConfig $config): array
    {
        $defaults = [
            'allow_external_mcp' => false,
            'allowed_tools' => [],
            'blocked_tools' => [],
        ];

        $widgetSecurity = $config->mcpWidgetSecurity;

        if (empty($widgetSecurity) || !is_array($widgetSecurity)) {
            return $defaults;
        }

        return array_merge($defaults, $widgetSecurity);
    }

    /**
     * Check if a specific tool name is allowed in widget context.
     *
     * @param string $toolName The tool name to check
     * @param AgentConfig $config The agent's configuration
     * @return bool
     */
    public static function isToolAllowedInWidget(string $toolName, AgentConfig $config): bool
    {
        if (self::isAdminContext()) {
            return true;
        }

        $widgetSecurity = $config->mcpWidgetSecurity;

        if (isset($widgetSecurity['allow_external_mcp']) && !$widgetSecurity['allow_external_mcp']) {
            return false;
        }

        $allowedTools = $widgetSecurity['allowed_tools'] ?? [];
        $blockedTools = $widgetSecurity['blocked_tools'] ?? [];

        // Blocked always wins
        if (in_array($toolName, $blockedTools, true)) {
            return false;
        }

        // Check allowlist
        foreach ($allowedTools as $allowed) {
            if ($allowed === $toolName) {
                return true;
            }
            if (str_ends_with($allowed, '*')) {
                $prefix = substr($allowed, 0, -1);
                if (str_starts_with($toolName, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
