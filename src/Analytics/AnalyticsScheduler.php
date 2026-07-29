<?php
declare(strict_types=1);
/**
 * Analytics Scheduler
 * 
 * Manages cron jobs for analytics tasks like daily rollups and cleanup.
 * 
 * @package Quarksol\SmartChatbot\Analytics
 */

namespace Quarksol\SmartChatbot\Analytics;

use Quarksol\SmartChatbot\Services\Logger;
use Quarksol\SmartChatbot\Config\ChatbotConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics Scheduler
 */
class AnalyticsScheduler {
    
    /** Cron hook names */
    const HOOK_DAILY_ROLLUP = 'swc_analytics_daily_rollup';
    const HOOK_CLEANUP = 'swc_analytics_cleanup';
    
    /**
     * Initialize scheduler
     */
    public static function init(): void {
        // Register cron actions
        add_action(self::HOOK_DAILY_ROLLUP, [self::class, 'runDailyRollup']);
        add_action(self::HOOK_CLEANUP, [self::class, 'runCleanup']);
        
        // Schedule if not scheduled
        if (!wp_next_scheduled(self::HOOK_DAILY_ROLLUP)) {
            wp_schedule_event(strtotime('tomorrow 01:00:00'), 'daily', self::HOOK_DAILY_ROLLUP);
        }
        
        if (!wp_next_scheduled(self::HOOK_CLEANUP)) {
            wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', self::HOOK_CLEANUP);
        }
    }
    
    /**
     * Run daily rollup
     */
    public static function runDailyRollup(): void {
        if (!class_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService') || 
            !method_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService', 'rollupDailyStats')) {
            Logger::warning('AnalyticsService::rollupDailyStats not available, skipping daily rollup.');
            return;
        }
        
        // Roll up yesterday's data
        $date = date('Y-m-d', strtotime('-1 day'));
        $count = \Quarksol\SmartChatbot\Analytics\AnalyticsService::rollupDailyStats($date);
        
        Logger::info("Daily rollup completed for {$date}", ['records_created' => $count]);
    }
    
    /**
     * Run cleanup
     */
    public static function runCleanup(): void {
        if (!class_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService') ||
            !method_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService', 'cleanup')) {
            Logger::warning('AnalyticsService::cleanup not available, skipping cleanup.');
            return;
        }
        
        // Use configured retention days
        $retentionDays = ChatbotConfig::ANALYTICS_RETENTION_DAYS;
        $count = \Quarksol\SmartChatbot\Analytics\AnalyticsService::cleanup($retentionDays);
        
        Logger::info("Cleanup completed", ['events_deleted' => $count, 'retention_days' => $retentionDays]);
    }
    
    /**
     * Cancel all scheduled tasks (for deactivation)
     */
    public static function cancelAll(): void {
        wp_clear_scheduled_hook(self::HOOK_DAILY_ROLLUP);
        wp_clear_scheduled_hook(self::HOOK_CLEANUP);
    }
}
