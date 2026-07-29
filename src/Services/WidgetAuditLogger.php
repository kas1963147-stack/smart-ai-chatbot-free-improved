<?php
declare(strict_types=1);
/**
 * Widget Security Audit Logger
 *
 * Logs all tool call attempts from widget/public contexts for security
 * monitoring and incident response. Stores to a WordPress option-based
 * rolling log (last N entries) for easy admin review.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class WidgetAuditLogger
{
    /** WordPress option key for the rolling log */
    const OPTION_KEY = 'swc_widget_audit_log';

    /** Maximum entries to keep in the rolling log */
    const MAX_ENTRIES = 500;

    /** Log levels */
    const LEVEL_INFO     = 'info';
    const LEVEL_WARNING  = 'warning';
    const LEVEL_BLOCKED  = 'blocked';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Log a tool call attempt from widget context.
     *
     * @param string $toolName    Name of the tool called
     * @param string $agentId     Agent identifier
     * @param string $context     Security context (widget, authenticated, admin)
     * @param string $action      What happened: 'allowed', 'blocked', 'executed', 'error'
     * @param array  $metadata    Extra context (sanitized)
     */
    public static function logToolCall(
        string $toolName,
        string $agentId,
        string $context,
        string $action,
        array $metadata = []
    ): void {
        // Only log non-admin contexts (admin is always allowed, no need to audit)
        if ($context === McpSecurityPolicy::CONTEXT_ADMIN) {
            return;
        }

        $level = match ($action) {
            'allowed', 'executed' => self::LEVEL_INFO,
            'blocked' => self::LEVEL_BLOCKED,
            'error' => self::LEVEL_WARNING,
            default => self::LEVEL_INFO,
        };

        $entry = [
            'timestamp' => current_time('mysql'),
            'unix_time' => time(),
            'level'     => $level,
            'tool'      => $toolName,
            'agent_id'  => $agentId,
            'context'   => $context,
            'action'    => $action,
            'ip'        => self::getClientIp(),
            'user_id'   => is_user_logged_in() ? get_current_user_id() : 0,
            'metadata'  => self::sanitizeMetadata($metadata),
        ];

        // Write to rolling log
        self::appendToLog($entry);

        // Also write to PHP error log for real-time monitoring
        $logMessage = sprintf(
            '[WidgetAudit] [%s] %s | tool=%s | agent=%s | context=%s | ip=%s | user=%d',
            strtoupper($action),
            $level === self::LEVEL_BLOCKED ? '' : '',
            $toolName,
            $agentId,
            $context,
            $entry['ip'],
            $entry['user_id']
        );
        error_log($logMessage);

        // Fire WordPress action for custom integrations (email alerts, etc.)
        if (function_exists('do_action')) {
            do_action('swc/security/widget_tool_call', $entry);
        }
    }

    /**
     * Log when tool filtering occurs on an entire tool set.
     */
    public static function logToolFiltering(
        string $agentId,
        string $context,
        int $totalTools,
        int $allowedTools,
        string $filterType = 'internal'
    ): void {
        if ($context === McpSecurityPolicy::CONTEXT_ADMIN) {
            return;
        }

        $blockedCount = $totalTools - $allowedTools;
        if ($blockedCount <= 0) {
            return;
        }

        $entry = [
            'timestamp'     => current_time('mysql'),
            'unix_time'     => time(),
            'level'         => self::LEVEL_INFO,
            'tool'          => '__filter__',
            'agent_id'      => $agentId,
            'context'       => $context,
            'action'        => 'filtered',
            'ip'            => self::getClientIp(),
            'user_id'       => is_user_logged_in() ? get_current_user_id() : 0,
            'metadata'      => [
                'filter_type' => $filterType,
                'total' => $totalTools,
                'allowed' => $allowedTools,
                'blocked' => $blockedCount,
            ],
        ];

        self::appendToLog($entry);

        error_log(sprintf(
            '[WidgetAudit] [FILTER] %s tools: %d/%d allowed for agent=%s context=%s',
            $filterType,
            $allowedTools,
            $totalTools,
            $agentId,
            $context
        ));
    }

    /**
     * Get the audit log entries.
     *
     * @param int $limit Maximum entries to return (0 = all)
     * @param string|null $level Filter by level
     * @return array Log entries (newest first)
     */
    public static function getLog(int $limit = 50, ?string $level = null): array
    {
        $log = get_option(self::OPTION_KEY, []);

        if (!is_array($log)) {
            return [];
        }

        // Filter by level
        if ($level !== null) {
            $log = array_filter($log, fn($entry) => ($entry['level'] ?? '') === $level);
        }

        // Sort newest first
        usort($log, fn($a, $b) => ($b['unix_time'] ?? 0) <=> ($a['unix_time'] ?? 0));

        // Apply limit
        if ($limit > 0) {
            $log = array_slice($log, 0, $limit);
        }

        return $log;
    }

    /**
     * Get summary stats from the audit log.
     */
    public static function getStats(): array
    {
        $log = get_option(self::OPTION_KEY, []);

        if (!is_array($log) || empty($log)) {
            return [
                'total_entries' => 0,
                'blocked' => 0,
                'allowed' => 0,
                'unique_ips' => 0,
                'oldest_entry' => null,
                'newest_entry' => null,
            ];
        }

        $blocked = 0;
        $allowed = 0;
        $ips = [];

        foreach ($log as $entry) {
            if (($entry['action'] ?? '') === 'blocked') {
                $blocked++;
            } else {
                $allowed++;
            }
            if (!empty($entry['ip'])) {
                $ips[$entry['ip']] = true;
            }
        }

        usort($log, fn($a, $b) => ($a['unix_time'] ?? 0) <=> ($b['unix_time'] ?? 0));

        return [
            'total_entries' => count($log),
            'blocked' => $blocked,
            'allowed' => $allowed,
            'unique_ips' => count($ips),
            'oldest_entry' => $log[0]['timestamp'] ?? null,
            'newest_entry' => end($log)['timestamp'] ?? null,
        ];
    }

    /**
     * Clear the audit log.
     */
    public static function clearLog(): bool
    {
        return update_option(self::OPTION_KEY, []);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Append an entry to the rolling log.
     */
    private static function appendToLog(array $entry): void
    {
        $log = get_option(self::OPTION_KEY, []);

        if (!is_array($log)) {
            $log = [];
        }

        $log[] = $entry;

        // Trim to max size (remove oldest entries)
        if (count($log) > self::MAX_ENTRIES) {
            $log = array_slice($log, -self::MAX_ENTRIES);
        }

        update_option(self::OPTION_KEY, $log, false); // false = don't autoload
    }

    /**
     * Get the client IP address (anonymized for privacy).
     */
    private static function getClientIp(): string
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']))[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_REAL_IP']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        // Anonymize last octet for privacy (GDPR compliance)
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }

        return $ip ? substr($ip, 0, -4) . 'xxxx' : 'unknown';
    }

    /**
     * Sanitize metadata to prevent log injection.
     */
    private static function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $key = sanitize_key($key);

            if (is_string($value)) {
                $sanitized[$key] = substr(sanitize_text_field($value), 0, 200);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = array_map(function ($v) {
                    return is_string($v) ? substr(sanitize_text_field($v), 0, 200) : $v;
                }, $value);
            }
            // Skip other types (objects, resources, etc.)
        }

        return $sanitized;
    }
}
