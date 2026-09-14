<?php
/**
 * Admin Settings Class
 * 
 * Handles legacy AJAX handlers for backwards compatibility.
 * Note: The main admin page is now rendered by the React app.
 * Menu registration was moved to class-agent-manager-admin.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Admin {
    
    public function __construct() {
        // NOTE: Menu registration is now in class-agent-manager-admin.php
        // This class only handles AJAX callbacks for backwards compatibility
        
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'auto_seed_if_empty'));
        
        // AJAX Actions (still needed for legacy functionality)
        add_action('wp_ajax_swc_save_faq', array($this, 'save_faq'));
        add_action('wp_ajax_swc_delete_faq', array($this, 'delete_faq'));
        add_action('wp_ajax_swc_get_faqs', array($this, 'get_faqs'));
        add_action('wp_ajax_swc_test_ai_connection', array($this, 'test_ai_connection'));
        add_action('wp_ajax_swc_get_provider_models', array($this, 'get_provider_models'));
        // Reseed handled automatically via auto_seed_if_empty
        
        // Agent admin AJAX handlers
        add_action('wp_ajax_swc_save_tool_settings', array($this, 'save_tool_settings'));
        add_action('wp_ajax_swc_save_prompts', array($this, 'save_prompts'));
        add_action('wp_ajax_swc_save_skills', array($this, 'save_skills'));
    }
    
    /**
     * Automatically seed default agents if none exist.
     * Runs silently on admin_init — no buttons or banners needed.
     */
    public function auto_seed_if_empty() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chat_agents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            // Table doesn't exist yet — try to create it
            $this->ensure_and_seed();
            return;
        }
        
        // If agents table is empty, seed automatically
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count === 0) {
            $this->ensure_and_seed();
        }
    }
    
    /**
     * Create tables and seed all default content (agents, widgets, knowledge).
     * This is the bulletproof seeder — works even if Schema class isn't autoloaded.
     */
    private function ensure_and_seed() {
        // 1. Load Schema class directly
        $schema_file = SWC_CHATBOT_PATH . 'includes/Database/Schema.php';
        if (file_exists($schema_file)) {
            require_once $schema_file;
        }
        
        // 2. Try Schema class seeder
        if (class_exists('SWC\\Database\\Schema')) {
            \SWC\Database\Schema::createTables();
            delete_option('swc_rebranded_seed');
            \SWC\Database\Schema::seedDefaultAgent();
            \SWC\Database\Schema::seedDefaultWidgets();
            \SWC\Database\Schema::seedDefaultKnowledge();
            update_option('swc_rebranded_seed', true);
            error_log('SWC Auto-Seed: Default agents, widgets and knowledge seeded via Schema class.');
            return;
        }
        
        // 3. FALLBACK: Insert agents directly via raw SQL if Schema class fails
        error_log('SWC Auto-Seed: Schema class not available, using raw SQL fallback.');
        $this->raw_seed_agents();
    }
    
    /**
     * Raw SQL fallback seeder — inserts agents directly without Schema class.
     * This guarantees agents will exist no matter what.
     */
    private function raw_seed_agents() {
        global $wpdb;
        $agents_table = $wpdb->prefix . 'swc_chat_agents';
        $assignments_table = $wpdb->prefix . 'swc_chat_assignments';
        
        // Ensure agents table exists (minimal CREATE)
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$agents_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            agent_id varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            avatar varchar(500) DEFAULT '',
            is_active tinyint(1) DEFAULT 1,
            is_default tinyint(1) DEFAULT 0,
            config longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY agent_id (agent_id)
        ) {$charset}");
        
        // Insert Customer Support agent
        $wpdb->replace($agents_table, [
            'agent_id'    => 'customer_support',
            'name'        => 'Customer Support',
            'description' => 'Professional support agent for general inquiries.',
            'avatar'      => 'AI',
            'is_active'   => 1,
            'is_default'  => 1,
            'config'      => wp_json_encode([
                'enabled_toolkits' => ['WooCommerce', 'WordPress'],
                'welcome_message'  => 'Hi! How can I assist you today?',
                'prompt_sections'  => ['system' => 'You are a professional Customer Support Agent. Provide accurate and helpful support.'],
            ]),
        ]);
        $first_id = $wpdb->insert_id;
        
        // Insert Shopping Assistant agent
        $wpdb->replace($agents_table, [
            'agent_id'    => 'shopping_assistant',
            'name'        => 'Shopping Assistant',
            'description' => 'Helps users find products.',
            'avatar'      => 'SA',
            'is_active'   => 1,
            'is_default'  => 0,
            'config'      => wp_json_encode([
                'enabled_toolkits' => ['WooCommerce'],
                'welcome_message'  => 'Hi there! Looking for something specific?',
                'prompt_sections'  => ['system' => 'You are a friendly shopping assistant. Help users find products.'],
            ]),
        ]);
        
        // Create global assignment for first agent
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$assignments_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            agent_db_id bigint(20) unsigned NOT NULL,
            location_type varchar(50) DEFAULT 'global',
            location_value varchar(255) DEFAULT NULL,
            priority int DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id)
        ) {$charset}");
        
        if ($first_id) {
            $wpdb->replace($assignments_table, [
                'agent_db_id'    => $first_id,
                'location_type'  => 'global',
                'location_value' => null,
                'priority'       => 0,
                'is_active'      => 1,
            ]);
        }
        
        update_option('swc_rebranded_seed', true);
        error_log('SWC Auto-Seed: 2 agents inserted via raw SQL fallback.');
    }
    
    // Menu registration moved to class-agent-manager-admin.php
    
    public function register_settings() {
        register_setting('swc_chatbot_settings_group', 'swc_chatbot_settings', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
    }
    
    // admin_scripts is now handled by class-agent-manager-admin.php
    // render_settings_page is no longer needed - React app handles the UI
    
    /**
     * @deprecated 1.1.0 React Admin UI is now used.
     */
    public function render_settings_page() {
        _deprecated_function(__METHOD__, '1.1.0', 'React Admin UI');
        ?>
        <div class="wrap swc-admin-wrap">
            <h1> AI Agent</h1>
            <div class="notice notice-info">
                <p><?php esc_html_e('Please use the new Smart Chatbot menu to access settings.', 'agentflow-ai'); ?></p>
            </div>
        </div>
        <?php
    }
    
    public function save_faq() {
        _deprecated_function(__METHOD__, '1.1.0', 'React Admin via REST API');
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_faq';
        
        $result = $wpdb->insert($table, array(
            'question' => sanitize_text_field($_POST['question']),
            'answer' => sanitize_textarea_field($_POST['answer']),
            'keywords' => sanitize_text_field($_POST['keywords'])
        ));
        
        if ($result) {
            wp_send_json_success(array('id' => $wpdb->insert_id));
        } else {
            wp_send_json_error('Failed to save');
        }
    }
    
    public function delete_faq() {
        _deprecated_function(__METHOD__, '1.1.0', 'React Admin via REST API');
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_faq';
        
        $wpdb->delete($table, array('id' => intval($_POST['id'])));
        wp_send_json_success();
    }
    
    public function get_faqs() {
        _deprecated_function(__METHOD__, '1.1.0', 'React Admin via REST API');
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_faq';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is safely constructed from wpdb prefix
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is safely constructed from wpdb prefix
        $faqs = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id DESC");
        
        wp_send_json_success($faqs);
    }
    
    /**
     * Test AI connection with the provided settings
     */
    public function test_ai_connection() {
        _deprecated_function(__METHOD__, '1.1.0', 'REST API: /settings/test-connection');
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $provider_name = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');
        $base_url = sanitize_text_field($_POST['base_url'] ?? '');
        
        // Validate API key format
        $validation = SWC_Chatbot_Provider_Factory::validate_api_key($provider_name, $api_key);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }
        
        // Try to create provider and send test message
        $config = array(
            'provider' => $provider_name,
            'apiKey' => $api_key,
            'model' => $model,
            'baseUrl' => $base_url
        );
        
        $provider = SWC_Chatbot_Provider_Factory::create_provider($config);
        
        if (is_wp_error($provider)) {
            wp_send_json_error($provider->get_error_message());
        }
        
        // Send a simple test message
        $response = $provider->chat('Say "Hello! Connection successful." and nothing else.');
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => 'Connection successful!',
            'response' => $response
        ));
    }
    
    /**
     * Get models for a specific provider
     */
    public function get_provider_models() {
        _deprecated_function(__METHOD__, '1.1.0', 'REST API: /settings/providers');
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $models = SWC_Chatbot_Provider_Factory::get_provider_models($provider);
        
        wp_send_json_success($models);
    }
    
    /**
     * Save tool settings from agent admin
     */
    public function save_tool_settings() {
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below
        $tools = isset($_POST['tools']) ? array_map('sanitize_text_field', (array) $_POST['tools']) : [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in helper
        $config = isset($_POST['config']) ? $this->sanitize_tool_config((array) $_POST['config']) : [];
        
        $settings = get_option('swc_chatbot_settings', []);
        $settings['tools'] = $tools;
        $settings['tool_config'] = $config;
        update_option('swc_chatbot_settings', $settings);
        
        wp_send_json_success(['message' => 'Tool settings saved']);
    }
    
    /**
     * Save prompts from agent admin
     */
    public function save_prompts() {
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in helper
        $prompts = isset($_POST['prompts']) ? $this->sanitize_prompts((array) $_POST['prompts']) : [];
        
        $settings = get_option('swc_chatbot_settings', []);
        $settings['prompts'] = $prompts;
        update_option('swc_chatbot_settings', $settings);
        
        wp_send_json_success(['message' => 'Prompts saved']);
    }
    
    /**
     * Save skills from agent admin
     */
    public function save_skills() {
        check_ajax_referer('swc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below
        $skills = isset($_POST['skills']) ? array_map('sanitize_text_field', (array) $_POST['skills']) : [];
        
        $settings = get_option('swc_chatbot_settings', []);
        $settings['skills'] = $skills;
        update_option('swc_chatbot_settings', $settings);
        
        wp_send_json_success(['message' => 'Skills saved']);
    }
    
    /**
     * Sanitize tool configuration array
     *
     * @param array $config Raw config array
     * @return array Sanitized config
     */
    private function sanitize_tool_config(array $config): array {
        $sanitized = [];
        foreach ($config as $tool => $fields) {
            $tool = sanitize_key($tool);
            $sanitized[$tool] = [];
            foreach ((array) $fields as $key => $value) {
                $key = sanitize_key($key);
                if (is_array($value)) {
                    $sanitized[$tool][$key] = array_map('sanitize_text_field', $value);
                } else {
                    $sanitized[$tool][$key] = sanitize_text_field($value);
                }
            }
        }
        return $sanitized;
    }
    
    /**
     * Sanitize prompts array
     *
     * @param array $prompts Raw prompts array
     * @return array Sanitized prompts
     */
    private function sanitize_prompts(array $prompts): array {
        $sanitized = [];
        foreach ($prompts as $key => $prompt) {
            $key = sanitize_key($key);
            $sanitized[$key] = [
                'name' => sanitize_text_field($prompt['name'] ?? ''),
                'content' => sanitize_textarea_field($prompt['content'] ?? ''),
                'priority' => absint($prompt['priority'] ?? 50)
            ];
        }
        return $sanitized;
    }
}
