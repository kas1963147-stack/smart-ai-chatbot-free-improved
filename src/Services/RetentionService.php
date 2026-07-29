<?php
declare(strict_types=1);
/**
 * Retention Service
 *
 * Handles cleanup of old logs, sessions, and history data.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\History\ActionLog;
use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class RetentionService
{
    private const DEFAULT_RETENTION_DAYS = 90;
    private const DEFAULT_CUSTOM_DATA_DAYS = 180;
    private const DEFAULT_ERROR_LOG_DAYS = 30;

    /**
     * Run all retention cleanups.
     *
     * @return array Summary of deletions
     */
    public static function cleanup(): array
    {
        $baseDays = (int) apply_filters('swc_retention_days', self::DEFAULT_RETENTION_DAYS);

        $actionLogDays = (int) apply_filters('swc_retention_days_action_log', $baseDays);
        $sessionDays = (int) apply_filters('swc_retention_days_sessions', $baseDays);
        $workspaceDays = (int) apply_filters('swc_retention_days_workspace', $baseDays);
        $customDataDays = (int) apply_filters('swc_retention_days_custom_data', self::DEFAULT_CUSTOM_DATA_DAYS);
        $errorLogDays = (int) apply_filters('swc_retention_days_error_logs', self::DEFAULT_ERROR_LOG_DAYS);

        $summary = [
            'action_log' => class_exists('\Quarksol\SmartChatbot\History\\ActionLog') ? ActionLog::cleanup($actionLogDays) : 0,
            'sessions' => self::cleanupSessions($sessionDays),
            'workspace_conversations' => self::cleanupWorkspaceConversations($workspaceDays),
            'custom_data' => self::cleanupCustomData($customDataDays),
            'error_logs' => class_exists('\Quarksol\SmartChatbot\Services\Logger') ? Logger::cleanupOldLogs($errorLogDays) : 0,
        ];

        return $summary;
    }

    private static function cleanupSessions(int $daysToKeep): int
    {
        if ($daysToKeep <= 0 || !class_exists('SWC\Database\Schema')) {
            return 0;
        }

        global $wpdb;
        $tables = Schema::getTableNames();
        $table = $tables['sessions'] ?? null;
        if (!$table) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE COALESCE(last_message_at, started_at) < %s",
                $cutoff
            )
        );
    }

    private static function cleanupCustomData(int $daysToKeep): int
    {
        if ($daysToKeep <= 0) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'swc_custom_data';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        return (int) $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $cutoff)
        );
    }

    private static function cleanupWorkspaceConversations(int $daysToKeep): int
    {
        if ($daysToKeep <= 0) {
            return 0;
        }

        $conversations = get_option('swc_workspace_conversations', []);
        if (empty($conversations) || !is_array($conversations)) {
            return 0;
        }

        $cutoff = strtotime("-{$daysToKeep} days");
        $deleted = 0;

        foreach ($conversations as $id => $conversation) {
            $updated = $conversation['updated_at'] ?? $conversation['created_at'] ?? null;
            if (!$updated) {
                continue;
            }

            if (strtotime($updated) < $cutoff) {
                unset($conversations[$id]);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            update_option('swc_workspace_conversations', $conversations);
        }

        return $deleted;
    }
}
