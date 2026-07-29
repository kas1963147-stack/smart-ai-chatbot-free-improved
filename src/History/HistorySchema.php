<?php
declare(strict_types=1);
/**
 * History Schema
 * 
 * Creates database table for action history tracking:
 * - swc_action_log: Detailed tool execution history per message
 * 
 * @package Quarksol\SmartChatbot\History
 */

namespace Quarksol\SmartChatbot\History;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * History Schema Manager
 */
class HistorySchema {
    
    /** Schema version */
    const VERSION = '1.1.0';
    
    /** Option key for version tracking */
    const VERSION_OPTION = 'swc_history_db_version';
    
    /**
     * Get history table names
     */
    public static function getTableNames(): array {
        global $wpdb;
        
        return [
            'action_log' => $wpdb->prefix . 'swc_action_log',
        ];
    }
    
    /**
     * Create history tables
     */
    public static function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::getTableNames();
        
        require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Table: Action Log - Detailed tool execution history
        $sql_action_log = "CREATE TABLE {$tables['action_log']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            message_index int(11) DEFAULT NULL,
            message_id varchar(64) DEFAULT NULL,
            action_id varchar(64) NOT NULL,
            action_type varchar(50) NOT NULL DEFAULT 'tool_call',
            tool_name varchar(100) NOT NULL,
            tool_description text DEFAULT NULL,
            inputs longtext DEFAULT NULL,
            outputs longtext DEFAULT NULL,
            duration_ms int(11) DEFAULT NULL,
            success tinyint(1) DEFAULT 1,
            error_message text DEFAULT NULL,
            agent_db_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_action (action_id),
            KEY idx_session (session_id),
            KEY idx_session_message (session_id, message_index),
            KEY idx_message (message_id),
            KEY idx_agent (agent_db_id),
            KEY idx_tool (tool_name),
            KEY idx_created (created_at),
            KEY idx_success (success)
        ) $charset_collate;";
        
        dbDelta($sql_action_log);
        
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
     * Check if history tables exist
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
     * Drop history tables (for uninstall)
     */
    public static function dropTables(): void {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        $wpdb->query("DROP TABLE IF EXISTS {$tables['action_log']}");
        
        delete_option(self::VERSION_OPTION);
    }
}
