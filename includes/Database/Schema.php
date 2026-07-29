<?php
/**
 * Database Schema for Multi-Agent Chat System
 * 
 * Creates and manages database tables for:
 * - Chat agents (agent definitions with configuration)
 * - Chat assignments (agent → location mapping)
 * - Chat sessions (conversation history)
 * - Scheduled tasks (background agent automation)
 * - Task executions (execution history/logs)
 * 
 * @package SWC\Database
 */

namespace SWC\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schema Manager
 * 
 * Handles table creation, upgrades, and version management.
 */
class Schema {
    
    /** Current schema version */
    const VERSION = '1.8.1';
    
    /** Option key for stored schema version */
    const VERSION_OPTION = 'swc_chatbot_db_version';
    
    /**
     * Get table names with WordPress prefix
     */
    public static function getTableNames(): array {
        global $wpdb;
        
        return [
            'agents'            => $wpdb->prefix . 'swc_chat_agents',
            'assignments'       => $wpdb->prefix . 'swc_chat_assignments',
            'sessions'          => $wpdb->prefix . 'swc_chat_sessions',
            'ratings'           => $wpdb->prefix . 'swc_chat_ratings',
            'error_logs'        => $wpdb->prefix . 'swc_error_logs',
            'chat_widgets'      => $wpdb->prefix . 'swc_chat_widgets',
            'knowledge_documents' => $wpdb->prefix . 'swc_knowledge_documents',
            // Task Scheduling Tables
            'scheduled_tasks'   => $wpdb->prefix . 'swc_scheduled_tasks',
            'task_executions'   => $wpdb->prefix . 'swc_task_executions',
            'task_templates'    => $wpdb->prefix . 'swc_task_templates',
            // Workflow Builder Tables
            'workflows'         => $wpdb->prefix . 'swc_workflows',
            'workflow_executions' => $wpdb->prefix . 'swc_workflow_executions',
            // Multi-Agent Orchestration Tables
            'agent_groups'      => $wpdb->prefix . 'swc_chat_agent_groups',
            'group_members'     => $wpdb->prefix . 'swc_chat_group_members',
            'faq'               => $wpdb->prefix . 'swc_chatbot_faq',
        ];
    }
    
    /**
     * Create all tables
     * 
     * Uses dbDelta for safe table creation/updates.
     * Should be called on plugin activation.
     */
    public static function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::getTableNames();
        
        require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Table 1: Chat Agents
        $sql_agents = "CREATE TABLE {$tables['agents']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            agent_id varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            avatar varchar(255) DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            is_hidden tinyint(1) NOT NULL DEFAULT 0,
            parent_agent_id bigint(20) UNSIGNED DEFAULT NULL,
            config longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY agent_id (agent_id),
            KEY is_active (is_active),
            KEY is_hidden (is_hidden),
            KEY parent_agent_id (parent_agent_id)
        ) $charset_collate;";
        
        dbDelta($sql_agents);
        
        // Table 2: Chat Assignments (supports both agent and group assignments)
        $sql_assignments = "CREATE TABLE {$tables['assignments']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            agent_db_id bigint(20) UNSIGNED DEFAULT NULL,
            group_db_id bigint(20) UNSIGNED DEFAULT NULL,
            widget_id bigint(20) UNSIGNED DEFAULT NULL,
            location_type varchar(32) NOT NULL,
            location_value varchar(255) DEFAULT NULL,
            assignment_type varchar(64) DEFAULT NULL,
            post_id bigint(20) UNSIGNED DEFAULT NULL,
            post_type varchar(32) DEFAULT NULL,
            priority int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY agent_db_id (agent_db_id),
            KEY group_db_id (group_db_id),
            KEY widget_id (widget_id),
            KEY assignment_type (assignment_type),
            KEY post_lookup (post_type, post_id),
            KEY location_lookup (location_type, location_value),
            UNIQUE KEY unique_assignment (agent_db_id, location_type, location_value)
        ) $charset_collate;";
        
        dbDelta($sql_assignments);
        
        // Table 3: Chat Sessions
        $sql_sessions = "CREATE TABLE {$tables['sessions']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            agent_db_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            visitor_id varchar(64) DEFAULT NULL,
            messages longtext NOT NULL,
            metadata longtext,
            status varchar(16) NOT NULL DEFAULT 'active',
            started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_message_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY agent_db_id (agent_db_id),
            KEY user_id (user_id),
            KEY visitor_id (visitor_id),
            KEY status (status),
            KEY status_date (status, last_message_at)
        ) $charset_collate;";
        
        dbDelta($sql_sessions);
        
        // Table 4: Chat Ratings
        $sql_ratings = "CREATE TABLE {$tables['ratings']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            agent_db_id bigint(20) UNSIGNED NOT NULL,
            message_index int(11) DEFAULT NULL,
            rating_type varchar(16) NOT NULL,
            rating_value int(11) DEFAULT NULL,
            comment text,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            visitor_id varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY agent_db_id (agent_db_id),
            KEY rating_type (rating_type)
        ) $charset_collate;";
        
        dbDelta($sql_ratings);
        
        // Table 5: Error Logs
        $sql_error_logs = "CREATE TABLE {$tables['error_logs']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level varchar(16) NOT NULL,
            message text NOT NULL,
            context longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql_error_logs);

        // Table 5b: Chat Widgets
        // Supports both individual agents and teams (agent groups)
        // agent_id format: 'agent:agent_id' or 'team:group_id'
        $sql_chat_widgets = "CREATE TABLE {$tables['chat_widgets']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            display_name varchar(255) NOT NULL,
            description text,
            appearance longtext,
            behavior longtext,
            triggers longtext,
            display longtext,
            engagement longtext,
            agent_id varchar(64) DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY is_active (is_active),
            KEY agent_id (agent_id)
        ) $charset_collate;";

        dbDelta($sql_chat_widgets);
        
        // Table 6: Knowledge Documents (Simplified markdown docs)
        $sql_knowledge_documents = "CREATE TABLE {$tables['knowledge_documents']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            content longtext,
            category varchar(50) DEFAULT 'general',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            type varchar(50) DEFAULT 'manual',
            source_url varchar(1000) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY title (title)
        ) $charset_collate;";
        
        dbDelta($sql_knowledge_documents);
        
        // Table 8: Scheduled Tasks
        $sql_scheduled_tasks = "CREATE TABLE {$tables['scheduled_tasks']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            agent_id varchar(64) NOT NULL,
            task_type varchar(50) NOT NULL DEFAULT 'custom',
            schedule_type varchar(30) NOT NULL DEFAULT 'once',
            schedule_config longtext,
            task_config longtext,
            status varchar(20) NOT NULL DEFAULT 'active',
            next_run_at datetime DEFAULT NULL,
            last_run_at datetime DEFAULT NULL,
            run_count int(11) NOT NULL DEFAULT 0,
            max_retries int(11) NOT NULL DEFAULT 3,
            retry_count int(11) NOT NULL DEFAULT 0,
            timeout_seconds int(11) NOT NULL DEFAULT 1800,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY task_id (task_id),
            KEY agent_id (agent_id),
            KEY status (status),
            KEY next_run_at (next_run_at),
            KEY task_type (task_type)
        ) $charset_collate;";
        
        dbDelta($sql_scheduled_tasks);
        
        // Table 9: Task Executions (history/logs)
        $sql_task_executions = "CREATE TABLE {$tables['task_executions']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_db_id bigint(20) UNSIGNED NOT NULL,
            execution_id varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            duration_ms int(11) DEFAULT NULL,
            result longtext,
            error_message text,
            tokens_used int(11) DEFAULT NULL,
            heartbeat_at datetime DEFAULT NULL,
            retry_of bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY execution_id (execution_id),
            KEY task_db_id (task_db_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        dbDelta($sql_task_executions);
        
        // Table 10: Task Templates (reusable configurations)
        $sql_task_templates = "CREATE TABLE {$tables['task_templates']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            category varchar(50) DEFAULT 'general',
            agent_type varchar(64) NOT NULL,
            default_config longtext,
            is_system tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_id (template_id),
            KEY category (category)
        ) $charset_collate;";
        
        dbDelta($sql_task_templates);

        // Table 10b: Workflows
        $sql_workflows = "CREATE TABLE {$tables['workflows']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            trigger_type varchar(32) NOT NULL DEFAULT 'manual',
            schedule_expression varchar(255) DEFAULT NULL,
            steps longtext,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY trigger_type (trigger_type),
            KEY is_active (is_active)
        ) $charset_collate;";

        dbDelta($sql_workflows);

        // Table 10c: Workflow Executions
        $sql_workflow_executions = "CREATE TABLE {$tables['workflow_executions']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) UNSIGNED NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            current_step int(11) NOT NULL DEFAULT 0,
            input longtext,
            output longtext,
            interrupted_data longtext,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_workflow_executions);
        
        // Table 11: Agent Groups (multi-agent orchestration)
        $sql_agent_groups = "CREATE TABLE {$tables['agent_groups']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            avatar varchar(255) DEFAULT '',
            orchestration_mode varchar(20) NOT NULL DEFAULT 'router',
            routing_config longtext,
            welcome_message text,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY group_id (group_id),
            KEY is_active (is_active),
            KEY orchestration_mode (orchestration_mode)
        ) $charset_collate;";
        
        dbDelta($sql_agent_groups);
        
        // Table 12: Agent Group Members (agent → group mapping)
        $sql_group_members = "CREATE TABLE {$tables['group_members']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_db_id bigint(20) UNSIGNED NOT NULL,
            agent_db_id bigint(20) UNSIGNED NOT NULL,
            role varchar(20) NOT NULL DEFAULT 'specialist',
            routing_keywords text,
            execution_order int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY group_db_id (group_db_id),
            KEY agent_db_id (agent_db_id),
            KEY role (role),
            UNIQUE KEY unique_membership (group_db_id, agent_db_id)
        ) $charset_collate;";
        
        dbDelta($sql_group_members);
        
        dbDelta($sql_group_members);
        
        // Table 13: FAQ (Legacy Chatbot) - Consolidated into Schema
        $sql_faq = "CREATE TABLE {$tables['faq']} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            question text NOT NULL,
            answer text NOT NULL,
            keywords text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        dbDelta($sql_faq);
        
        // Ensure is_hidden column exists (may be missing from older installations)
        $has_is_hidden = $wpdb->get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = '{$tables['agents']}' 
             AND COLUMN_NAME = 'is_hidden'"
        );
        
        if (!$has_is_hidden) {
            $wpdb->query(
                "ALTER TABLE {$tables['agents']} 
                 ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0 AFTER is_default,
                 ADD INDEX is_hidden (is_hidden)"
            );
        }
        
        // Store schema version
        update_option(self::VERSION_OPTION, self::VERSION);
        
        // Clear tables exist cache
        delete_transient('swc_tables_exist_check');
    }
    
    /**
     * Check if schema needs upgrade
     */
    public static function needsUpgrade(): bool {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        return version_compare($current_version, self::VERSION, '<');
    }
    
    /**
     * Check if all tables exist
     */
    public static function tablesExist(): bool {
        // Cache the check for performance (1 hour)
        $cached_check = get_transient('swc_tables_exist_check');
        if ($cached_check === 'yes' && !defined('WP_DEBUG')) {
            return true;
        }

        global $wpdb;
        
        $tables = self::getTableNames();
        
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
                return false;
            }
        }
        
        // Cache success result
        set_transient('swc_tables_exist_check', 'yes', HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function dropTables(): void {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        // Drop in reverse order to avoid FK issues
        $drop_order = [
            'workflow_executions',
            'workflows',
            'group_members',
            'agent_groups',
            'task_templates',
            'task_executions',
            'scheduled_tasks',
            'knowledge_documents',
            'chat_widgets',
            'error_logs',
            'ratings',
            'sessions',
            'assignments',
            'agents'
        ];

        foreach ($drop_order as $key) {
            if (isset($tables[$key])) {
                // Table name is sourced from trusted getTableNames() whitelist
                $wpdb->query("DROP TABLE IF EXISTS `{$tables[$key]}`");
            }
        }
        
        delete_option(self::VERSION_OPTION);
    }
    
    /**
     * Seed default agents
     * 
     * Creates all default agents from default-agents.php on first install.
     * Falls back to a single "General Assistant" if config file is missing.
     */
    public static function seedDefaultAgent(): void {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        // Check if any agents already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['agents']}");
        
        // Forced seeding for rebranding version
        $is_rebranded = get_option('swc_rebranded_seed');
        
        if ($count > 0 && $is_rebranded) {
            return;
        }
        
        // If not rebranded yet, clear old agents to ensure clean start
        if (!$is_rebranded) {
            $wpdb->query("DELETE FROM {$tables['agents']}");
            $wpdb->query("DELETE FROM {$tables['assignments']}");
        }
        
        // Load default agents from config file
        $agents = [];
        
        // Use plugin constant for reliable path resolution
        if (defined('SWC_CHATBOT_PATH')) {
            $configPath = SWC_CHATBOT_PATH . 'src/Config/default-agents.php';
        } else {
            // Fallback: Navigate from current file location
            $configPath = dirname(__DIR__, 2) . '/src/Config/default-agents.php';
        }
        
        // Try to load default agents with robust error handling
        if (file_exists($configPath)) {
            try {
                // Use output buffering to catch any stray output
                ob_start();
                $agents = include $configPath;
                ob_end_clean();
                
                if (!is_array($agents)) {
                    $agents = [];
                    error_log('SWC Seeder: default-agents.php did not return an array, returned: ' . gettype($agents));
                } else {
                    error_log('SWC Seeder: Loaded ' . count($agents) . ' agents from config file');
                }
            } catch (\Throwable $e) {
                error_log('SWC Seeder: Error loading default-agents.php: ' . $e->getMessage());
                $agents = [];
            }
        } else {
            error_log('SWC Seeder: default-agents.php not found at: ' . $configPath);
        }
        
        // Fallback to single agent if no config found
        if (empty($agents)) {
            $agents = [
                [
                    'agent_id' => 'general_assistant',
                    'name' => 'General Assistant',
                    'description' => 'Default AI assistant with full toolkit access',
                    'avatar' => '',
                    'is_active' => true,
                    'config' => [
                        'enabled_toolkits' => [],
                        'enabled_tools' => [],
                        'disabled_tools' => [],
                        'prompt_sections' => [
                            'greeting' => 'Hi! How can I help you today?',
                        ],
                        'welcome_message' => 'Hi! How can I help you today?',
                        'quick_actions' => [
                            ['label' => 'Products', 'action' => 'products'],
                            ['label' => 'Best Sellers', 'action' => 'bestsellers'],
                            ['label' => 'On Sale', 'action' => 'sale'],
                            ['label' => 'Track Order', 'action' => 'track'],
                        ],
                        'max_history_length' => 50,
                    ],
                ],
            ];
        }
        
        // Insert each agent
        $isFirst = true;
        foreach ($agents as $agentData) {
            $wpdb->insert(
                $tables['agents'],
                [
                    'agent_id' => $agentData['agent_id'],
                    'name' => $agentData['name'],
                    'description' => $agentData['description'] ?? '',
                    'avatar' => $agentData['avatar'] ?? '',
                    'is_active' => ($agentData['is_active'] ?? true) ? 1 : 0,
                    'is_default' => $isFirst ? 1 : 0,  // First agent is default
                    'config' => wp_json_encode($agentData['config'] ?? []),
                ],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%s']
            );
            
            // Assign first agent to global location
            if ($isFirst && $wpdb->insert_id) {
                $wpdb->insert(
                    $tables['assignments'],
                    [
                        'agent_db_id' => $wpdb->insert_id,
                        'location_type' => 'global',
                        'location_value' => null,
                        'priority' => 0,
                        'is_active' => 1,
                    ],
                    ['%d', '%s', '%s', '%d', '%d']
                );
            }
            $isFirst = false;
        }
    }

    /**
     * Seed default knowledge documents
     * 
     * Creates basic knowledge examples on first install so users can test the feature.
     */
    public static function seedDefaultKnowledge(): void {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        // Check if any knowledge documents already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['knowledge_documents']}");
        
        $is_rebranded = get_option('swc_rebranded_seed');
        
        if ($count > 0 && $is_rebranded) {
            return;
        }

        if (!$is_rebranded) {
            $wpdb->query("DELETE FROM {$tables['knowledge_documents']}");
        }
        
        $now = current_time('mysql');
        
        $documents = [
            [
                'title' => 'Company Return Policy',
                'description' => 'General return policy for standard and sale items.',
                'content' => "Our return policy allows items to be returned within 30 days of purchase. Items must be unused, in their original packaging, and accompanied by the receipt. Sale items are considered final sale and cannot be returned or exchanged. Refunds are processed to the original payment method within 5-7 business days after we receive the returned item.",
                'category' => 'Policies',
                'is_active' => 1,
                'type' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Shipping Information',
                'description' => 'Details about delivery times and shipping costs.',
                'content' => "We offer free standard shipping on all orders over $50. Standard shipping typically takes 3-5 business days. Expedited shipping is available for a flat rate of $15 and delivers within 1-2 business days. International shipping takes 7-14 business days depending on the destination. Tracking information is automatically provided via email once the order is shipped.",
                'category' => 'Shipping',
                'is_active' => 1,
                'type' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ];

        foreach ($documents as $doc) {
            $wpdb->insert(
                $tables['knowledge_documents'],
                $doc,
                ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
            );
        }
        
        error_log('SWC Seeder: Successfully inserted default knowledge documents');
    }

    /**
     * Seed default chat widgets
     * 
     * Creates all default widgets from default-widgets.php on first install.
     * All widgets are inactive by default — users activate what they need.
     */
    public static function seedDefaultWidgets(): void {
        global $wpdb;
        
        $tables = self::getTableNames();
        
        // Check if any widgets already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['chat_widgets']}");
        
        $is_rebranded = get_option('swc_rebranded_seed');
        
        if ($count > 0 && $is_rebranded) {
            return;
        }

        if (!$is_rebranded) {
            $wpdb->query("DELETE FROM {$tables['chat_widgets']}");
        }
        
        // Load default widgets from config file
        $widgets = [];
        
        if (defined('SWC_CHATBOT_PATH')) {
            $configPath = SWC_CHATBOT_PATH . 'src/Config/default-widgets.php';
        } else {
            $configPath = dirname(__DIR__, 2) . '/src/Config/default-widgets.php';
        }
        
        if (file_exists($configPath)) {
            try {
                ob_start();
                $widgets = include $configPath;
                ob_end_clean();
                
                if (!is_array($widgets)) {
                    $widgets = [];
                    error_log('SWC Seeder: default-widgets.php did not return an array, returned: ' . gettype($widgets));
                } else {
                    error_log('SWC Seeder: Loaded ' . count($widgets) . ' widgets from config file');
                }
            } catch (\Throwable $e) {
                error_log('SWC Seeder: Error loading default-widgets.php: ' . $e->getMessage());
                $widgets = [];
            }
        } else {
            error_log('SWC Seeder: default-widgets.php not found at: ' . $configPath);
        }
        
        if (empty($widgets)) {
            return;
        }
        
        // Insert each widget
        foreach ($widgets as $widgetData) {
            $wpdb->insert(
                $tables['chat_widgets'],
                [
                    'name'        => $widgetData['name'] ?? '',
                    'display_name'=> $widgetData['display_name'] ?? '',
                    'description' => $widgetData['description'] ?? '',
                    'appearance'  => wp_json_encode($widgetData['appearance'] ?? []),
                    'behavior'    => wp_json_encode($widgetData['behavior'] ?? []),
                    'triggers'    => wp_json_encode($widgetData['triggers'] ?? []),
                    'display'     => wp_json_encode($widgetData['display'] ?? []),
                    'engagement'  => wp_json_encode($widgetData['engagement'] ?? []),
                    'agent_id'    => $widgetData['agent_id'] ?? '',
                    'is_active'   => ($widgetData['is_active'] ?? false) ? 1 : 0,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
        }
        
        error_log('SWC Seeder: Successfully inserted ' . count($widgets) . ' default widgets');
    }
}
