<?php
declare(strict_types=1);
/**
 * Tool Access Policy
 *
 * Central policy for enabling/disabling high-risk toolkits in public contexts.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ToolAccessPolicy
{
    /**
     * Default restricted toolkits for non-admin contexts.
     *
     * @var string[]
     */
    private const DEFAULT_RESTRICTED_TOOLKITS = [
        'cli',
        'file_access',
        'security',
        'integrations',
        'expressions',
        'performance',
        'web_research',
    ];

    /**
     * Determine if the current context can access restricted toolkits.
     */
    public static function isPrivilegedContext(): bool
    {
        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        return current_user_can('manage_options');
    }

    /**
     * Get restricted toolkit IDs.
     *
     * @return string[]
     */
    public static function getRestrictedToolkits(): array
    {
        $restricted = apply_filters('swc_restricted_toolkits', self::DEFAULT_RESTRICTED_TOOLKITS);
        $restricted = is_array($restricted) ? $restricted : self::DEFAULT_RESTRICTED_TOOLKITS;
        $restricted = array_map('strtolower', $restricted);

        return array_values(array_unique($restricted));
    }

    /**
     * Filter tools based on access policy.
     *
     * @param array $tools Tool instances
     * @return array Filtered tool list
     */
    public static function filterTools(array $tools): array
    {
        if (self::isPrivilegedContext()) {
            return $tools;
        }

        $restricted = self::getRestrictedToolkits();

        return array_values(array_filter($tools, function ($tool) use ($restricted): bool {
            $toolkitId = null;
            if (method_exists($tool, 'getToolkitId')) {
                $toolkitId = $tool->getToolkitId();
            }
            if (!$toolkitId) {
                $toolkitId = self::inferToolkitFromToolName($tool->getName());
            }

            $decision = apply_filters('swc_tool_access_allowed', null, $tool, $toolkitId, false);
            if ($decision !== null) {
                return (bool) $decision;
            }

            if (!$toolkitId) {
                return true;
            }

            return !in_array(strtolower($toolkitId), $restricted, true);
        }));
    }

    /**
     * Check if MCP tools are allowed in the current context.
     *
     * @deprecated Use McpSecurityPolicy::canUseExternalMcp() for per-agent context-aware checks.
     *             This method remains for backward compatibility and non-agent-specific checks.
     */
    public static function canUseMcpTools(): bool
    {
        $allowed = self::isPrivilegedContext();
        return (bool) apply_filters('swc_allow_mcp_tools', $allowed);
    }

    /**
     * Infer toolkit ID from a tool name prefix.
     */
    private static function inferToolkitFromToolName(string $toolId): ?string
    {
        $prefixMap = [
            'woo_' => 'woocommerce',
            'wp_' => 'wordpress_core',
            'cli_' => 'cli',
            'seo_' => 'seo',
            'security_' => 'security',
            'perf_' => 'performance',
            'file_' => 'file_access',
            'forms_' => 'forms',
            'expr_' => 'expressions',
            'research_' => 'web_research',
            'acf_' => 'custom_fields',
            'integration_' => 'integrations',
            'availability_checker' => 'appointment_booking',
            'appointment_booker' => 'appointment_booking',
            'lead_collector' => 'LeadCollection',
        ];

        foreach ($prefixMap as $prefix => $toolkit) {
            if (str_starts_with($toolId, $prefix)) {
                return $toolkit;
            }
        }

        return null;
    }
}
