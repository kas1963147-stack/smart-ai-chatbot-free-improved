<?php
declare(strict_types=1);
/**
 * Admin Settings Class
 * 
 * Manages the plugin settings via REST API.
 * Note: The main admin page is now rendered by the React app.
 * Menu registration is handled by class-agent-manager-admin.php
 * 
 * @package Quarksol\SmartChatbot\Admin
 */

namespace Quarksol\SmartChatbot\Admin;

use Quarksol\SmartChatbot\Api\Providers\ProviderFactory;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {
    
    public function __construct() {
        // NOTE: Menu registration is now in includes/admin/class-agent-manager-admin.php
        // This class provides settings management via API for the React app
        
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX Actions (for legacy and non-React functionality)
        add_action('wp_ajax_swc_save_faq', array($this, 'save_faq'));
        add_action('wp_ajax_swc_delete_faq', array($this, 'delete_faq'));
        add_action('wp_ajax_swc_get_faqs', array($this, 'get_faqs'));
        add_action('wp_ajax_swc_test_ai_connection', array($this, 'test_ai_connection'));
        add_action('wp_ajax_swc_get_provider_models', array($this, 'get_provider_models'));
        
        // Agent admin AJAX handlers (legacy agent-admin.js support)
        add_action('wp_ajax_swc_save_tool_settings', array($this, 'save_tool_settings'));
        add_action('wp_ajax_swc_save_prompts', array($this, 'save_prompts'));
        add_action('wp_ajax_swc_save_skills', array($this, 'save_skills'));
    }
    
    public function register_settings() {
        register_setting('swc_chatbot_settings_group', 'swc_chatbot_settings', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
    }
    
    public function render_settings_page() {
        $settings = get_option('swc_chatbot_settings', array());
        echo '<div class="wrap"><h1>Agent Settings</h1><p>Settings are now managed via the React admin panel.</p></div>';
    }
    
    /**
     * AJAX: Save tool settings (legacy agent-admin.js)
     */
    public function save_tool_settings() {
        check_ajax_referer('swc_agent_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $tools = isset($_POST['tools']) ? array_map('sanitize_text_field', $_POST['tools']) : [];
        $config = isset($_POST['config']) ? $this->sanitize_tool_config($_POST['config']) : [];
        
        update_option('swc_enabled_tools', $tools);
        update_option('swc_tool_config', $config);
        
        wp_send_json_success(['message' => 'Tool settings saved']);
    }
    
    /**
     * AJAX: Save prompt templates (legacy agent-admin.js)
     */
    public function save_prompts() {
        check_ajax_referer('swc_agent_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $prompts = isset($_POST['prompts']) ? $this->sanitize_prompts($_POST['prompts']) : [];
        update_option('swc_custom_prompts', $prompts);
        
        wp_send_json_success(['message' => 'Prompts saved']);
    }
    
    /**
     * AJAX: Save enabled skills (legacy agent-admin.js)
     */
    public function save_skills() {
        check_ajax_referer('swc_agent_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $skills = isset($_POST['skills']) ? array_map('sanitize_text_field', $_POST['skills']) : [];
        update_option('swc_enabled_skills', $skills);
        
        wp_send_json_success(['message' => 'Skills saved']);
    }
    
    /**
     * Sanitize tool configuration array
     */
    private function sanitize_tool_config($config) {
        if (!is_array($config)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($config as $tool_slug => $tool_config) {
            $tool_slug = sanitize_key($tool_slug);
            if (!is_array($tool_config)) {
                continue;
            }
            
            $sanitized[$tool_slug] = [];
            foreach ($tool_config as $key => $value) {
                $key = sanitize_key($key);
                if (is_array($value)) {
                    $sanitized[$tool_slug][$key] = array_map('sanitize_text_field', $value);
                } else {
                    $sanitized[$tool_slug][$key] = sanitize_text_field($value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize prompts array
     */
    private function sanitize_prompts($prompts) {
        if (!is_array($prompts)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($prompts as $key => $prompt) {
            $key = sanitize_key($key);
            if (!is_array($prompt)) {
                continue;
            }
            
            $sanitized[$key] = [
                'name' => sanitize_text_field($prompt['name'] ?? ''),
                'content' => wp_kses_post($prompt['content'] ?? ''),
                'priority' => absint($prompt['priority'] ?? 50)
            ];
        }
        
        return $sanitized;
    }
}
