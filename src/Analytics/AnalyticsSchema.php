<?php
declare(strict_types=1);
/**
 * Analytics Schema
 * 
 * Creates database tables for analytics tracking:
 * - swc_analytics_events: Real-time event stream
 * - swc_usage_stats: Daily rollup for fast queries
 * 
 * @package Quarksol\SmartChatbot\Analytics
 */

namespace Quarksol\SmartChatbot\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics Schema Manager
 */
class AnalyticsSchema {
    
    /** Schema version */
    const VERSION = '1.2.0';
    
    /** Option key for version tracking */
    const VERSION_OPTION = 'swc_analytics_db_version';
    
    /**
     * Get analytics table names
     */
    public static function getTableNames(): array {
        global $wpdb;
        
        return [
            'events' => $wpdb->prefix . 'swc_analytics_events',
            'stats'  => $wpdb->prefix . 'swc_usage_stats',
            'pricing' => $wpdb->prefix . 'swc_api_pricing',
        ];
    }
    
    /**
     * Create analytics tables
     */
    public static function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::getTableNames();
        
        require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Table 1: Analytics Events (real-time stream)
        $sql_events = "CREATE TABLE {$tables['events']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            session_id varchar(64) DEFAULT NULL,
            agent_db_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            visitor_id varchar(64) DEFAULT NULL,
            provider varchar(50) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            input_tokens int(11) DEFAULT 0,
            output_tokens int(11) DEFAULT 0,
            cache_read_tokens int(11) DEFAULT 0,
            cache_write_tokens int(11) DEFAULT 0,
            cost_usd decimal(12,8) DEFAULT 0.00000000,
            duration_ms int(11) DEFAULT NULL,
            tool_name varchar(100) DEFAULT NULL,
            skill_slug varchar(100) DEFAULT NULL,
            success tinyint(1) DEFAULT 1,
            error_message text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            source varchar(50) DEFAULT NULL,
            query_length int(11) DEFAULT NULL,
            response_length int(11) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_session (session_id),
            KEY idx_agent (agent_db_id),
            KEY idx_created (created_at),
            KEY idx_provider_model (provider, model),
            KEY idx_tool (tool_name),
            KEY idx_skill (skill_slug),
            KEY idx_source (source)
        ) $charset_collate;";
        
        dbDelta($sql_events);
        
        // Table 2: Daily Usage Stats (rollup for fast queries)
        $sql_stats = "CREATE TABLE {$tables['stats']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_date date NOT NULL,
            agent_db_id bigint(20) UNSIGNED DEFAULT NULL,
            provider varchar(50) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            total_sessions int(11) DEFAULT 0,
            total_messages int(11) DEFAULT 0,
            total_tool_calls int(11) DEFAULT 0,
            total_skill_activations int(11) DEFAULT 0,
            total_rag_queries int(11) DEFAULT 0,
            total_input_tokens bigint(20) DEFAULT 0,
            total_output_tokens bigint(20) DEFAULT 0,
            total_cache_read_tokens bigint(20) DEFAULT 0,
            total_cache_write_tokens bigint(20) DEFAULT 0,
            total_cost_usd decimal(12,4) DEFAULT 0.0000,
            avg_response_time_ms int(11) DEFAULT NULL,
            p95_response_time_ms int(11) DEFAULT NULL,
            error_count int(11) DEFAULT 0,
            thumbs_up int(11) DEFAULT 0,
            thumbs_down int(11) DEFAULT 0,
            avg_star_rating decimal(3,2) DEFAULT NULL,
            unique_users int(11) DEFAULT 0,
            unique_visitors int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_day_agent_provider (stat_date, agent_db_id, provider, model),
            KEY idx_stat_date (stat_date),
            KEY idx_agent (agent_db_id),
            KEY idx_provider (provider)
        ) $charset_collate;";
        
        dbDelta($sql_stats);
        
        // Table 3: API Pricing Overrides (custom pricing configuration)
        $sql_pricing = "CREATE TABLE {$tables['pricing']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider varchar(50) NOT NULL,
            model varchar(100) NOT NULL,
            input_price_per_million decimal(12,6) DEFAULT NULL,
            output_price_per_million decimal(12,6) DEFAULT NULL,
            cache_write_price_per_million decimal(12,6) DEFAULT NULL,
            cache_read_price_per_million decimal(12,6) DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY provider_model (provider, model),
            KEY idx_provider (provider)
        ) $charset_collate;";
        
        dbDelta($sql_pricing);
        
        // Update version
        update_option(self::VERSION_OPTION, self::VERSION);
    }
    
    /**
     * Check if tables need upgrade
     */
    public static function needsUpgrade(): bool {
        $current = get_option(self::VERSION_OPTION, '0.0.0');
        return version_compare($current, self::VERSION, '<');
    }
    
    /**
     * Check if analytics tables exist
     */
    public static function tablesExist(): bool {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Drop analytics tables (for uninstall)
     */
    public static function dropTables(): void {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        $wpdb->query("DROP TABLE IF EXISTS {$tables['pricing']}");
        $wpdb->query("DROP TABLE IF EXISTS {$tables['stats']}");
        $wpdb->query("DROP TABLE IF EXISTS {$tables['events']}");
        
        delete_option(self::VERSION_OPTION);
    }
}
