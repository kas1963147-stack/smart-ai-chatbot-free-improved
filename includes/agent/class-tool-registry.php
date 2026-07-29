<?php
/**
 * Agent Tools Registry
 * 
 * Manages available tools that the AI agent can use.
 * Tools are actions the AI can perform (SQL, Calendar, Email, etc.)
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Tool_Registry {
    
    private static $instance = null;
    private $tools = [];
    private $enabled_tools = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - register built-in tools
     */
    private function __construct() {
        $this->register_builtin_tools();
        $this->load_enabled_tools();
    }
    
    /**
     * Register all built-in tools
     */
    private function register_builtin_tools() {
        // WooCommerce Tool (Shopping)
        $this->register_tool([
            'slug' => 'woocommerce',
            'name' => 'WooCommerce',
            'description' => 'Search products, manage cart, track orders',
            'category' => 'ecommerce',
            'icon' => '',
            'class' => 'SWC_Tool_WooCommerce',
            'requires' => ['woocommerce'],
            'config_fields' => []
        ]);
        
        // SQL Tool (Database)
        $this->register_tool([
            'slug' => 'sql',
            'name' => 'Database',
            'description' => 'Save and query custom data in database',
            'category' => 'data',
            'icon' => '️',
            'class' => 'SWC_Tool_SQL',
            'requires' => [],
            'config_fields' => [
                'allowed_tables' => [
                    'type' => 'text',
                    'label' => 'Allowed Tables (comma-separated)',
                    'default' => 'swc_custom_data'
                ]
            ]
        ]);
        

        // Email Tool
        $this->register_tool([
            'slug' => 'email',
            'name' => 'Email',
            'description' => 'Send emails to admin or users',
            'category' => 'communication',
            'icon' => '',
            'class' => 'SWC_Tool_Email',
            'requires' => [],
            'config_fields' => [
                'admin_email' => [
                    'type' => 'email',
                    'label' => 'Admin Email',
                    'default' => get_option('admin_email')
                ],
                'email_from_name' => [
                    'type' => 'text',
                    'label' => 'From Name',
                    'default' => get_bloginfo('name')
                ]
            ]
        ]);
        
        // File Tool (Sandboxed)
        $this->register_tool([
            'slug' => 'files',
            'name' => 'Files',
            'description' => 'Read and write files (sandboxed)',
            'category' => 'system',
            'icon' => '',
            'class' => 'SWC_Tool_Files',
            'requires' => [],
            'config_fields' => [
                'sandbox_path' => [
                    'type' => 'text',
                    'label' => 'Sandbox Directory',
                    'default' => wp_upload_dir()['basedir'] . '/smart-ai-chatbot/files/'
                ],
                'allowed_extensions' => [
                    'type' => 'text',
                    'label' => 'Allowed Extensions',
                    'default' => 'txt,md,json,csv'
                ]
            ]
        ]);
        
        // FAQ Tool
        $this->register_tool([
            'slug' => 'faq',
            'name' => 'FAQ',
            'description' => 'Search and display FAQ answers',
            'category' => 'support',
            'icon' => '',
            'class' => 'SWC_Tool_FAQ',
            'requires' => [],
            'config_fields' => []
        ]);
    }
    
    /**
     * Register a tool
     */
    public function register_tool($tool) {
        $this->tools[$tool['slug']] = $tool;
    }
    
    /**
     * Load enabled tools from database
     */
    private function load_enabled_tools() {
        $settings = get_option('swc_chatbot_tools', []);
        $this->enabled_tools = $settings;
    }
    
    /**
     * Check if a tool is enabled
     */
    public function is_tool_enabled($slug) {
        return !empty($this->enabled_tools[$slug]['enabled']);
    }
    
    /**
     * Get all registered tools
     */
    public function get_all_tools() {
        return $this->tools;
    }
    
    /**
     * Get enabled tools only
     */
    public function get_enabled_tools() {
        $enabled = [];
        foreach ($this->tools as $slug => $tool) {
            if ($this->is_tool_enabled($slug)) {
                $enabled[$slug] = $tool;
            }
        }
        return $enabled;
    }
    
    /**
     * Get tool configuration
     */
    public function get_tool_config($slug) {
        return $this->enabled_tools[$slug]['config'] ?? [];
    }
    
    /**
     * Save tool settings
     */
    public function save_tool_settings($slug, $enabled, $config = []) {
        $settings = get_option('swc_chatbot_tools', []);
        $settings[$slug] = [
            'enabled' => (bool) $enabled,
            'config' => $config
        ];
        update_option('swc_chatbot_tools', $settings);
        $this->enabled_tools = $settings;
    }
    
    /**
     * Get tool instance
     */
    public function get_tool_instance($slug) {
        if (!isset($this->tools[$slug])) {
            return null;
        }
        
        $tool = $this->tools[$slug];
        $class = $tool['class'];
        
        if (class_exists($class)) {
            return new $class($this->get_tool_config($slug));
        }
        
        return null;
    }
    
    /**
     * Execute a tool action
     */
    public function execute_tool($slug, $action, $params = []) {
        // ===== SYSTEM TOOLS: Always enabled, use NeuronAI invocable pattern =====
        $system_tools = ['load_skill', 'load_skill_reference'];
        
        if (in_array($slug, $system_tools)) {
            return $this->execute_system_tool($slug, $action, $params);
        }
        
        // ===== FREE TIER BUILT-IN TOOLS =====
        if ($slug === 'free_assistant') {
            require_once __DIR__ . '/tools/class-tool-free.php';
            $instance = new SWC_Tool_Free_Assistant();
            
            if (!method_exists($instance, $action)) {
                return [
                    'success' => false,
                    'error' => "Action '{$action}' not available for tool '{$slug}'"
                ];
            }
            
            try {
                $result = $instance->$action($params);
                return [
                    'success' => true,
                    'data' => $result
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // ===== REGULAR TOOLS: Check if enabled =====
        if (!$this->is_tool_enabled($slug)) {
            // ===== FALLBACK TO NEW TOOLKITS =====
            // If the tool is not enabled in legacy settings, it might be a new Toolkit tool
            $toolkit_tool_instance = null;
            if (function_exists('get_all_toolkit_tools')) {
                $toolkit_tools = get_all_toolkit_tools();
                foreach ($toolkit_tools as $t) {
                    if ($t->getName() === $slug) {
                        $toolkit_tool_instance = $t;
                        break;
                    }
                }
            }
            
            if ($toolkit_tool_instance) {
                try {
                    // Modern toolkit tools accept 'action' as a property
                    if ($action && $action !== 'execute' && !isset($params['action'])) {
                        $params['action'] = $action;
                    }
                    
                    // PHP 8+ unpacking associative arrays works perfectly with named arguments to __invoke
                    $result = call_user_func_array([$toolkit_tool_instance, '__invoke'], $params);
                    
                    // Decode JSON result if the tool returns a JSON string
                    if (is_string($result) && (str_starts_with($result, '{') || str_starts_with($result, '['))) {
                        $decoded = json_decode($result, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $result = $decoded;
                        }
                    }
                    
                    return [
                        'success' => true,
                        'data' => $result
                    ];
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'error' => "Error executing toolkit tool '{$slug}': " . $e->getMessage()
                    ];
                }
            }
            
            return [
                'success' => false,
                'error' => "Tool '{$slug}' is not enabled"
            ];
        }
        
        $instance = $this->get_tool_instance($slug);
        if (!$instance) {
            return [
                'success' => false,
                'error' => "Tool '{$slug}' not found"
            ];
        }
        
        if (!method_exists($instance, $action)) {
            return [
                'success' => false,
                'error' => "Action '{$action}' not available for tool '{$slug}'"
            ];
        }
        
        try {
            $result = $instance->$action($params);
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute a system tool (always enabled, NeuronAI pattern)
     * 
     * System tools use __invoke() pattern from NeuronAI framework.
     * They cannot be disabled by admin configuration.
     */
    private function execute_system_tool($slug, $action, $params = []) {
        try {
            // Load system tool registry if available
            if (!class_exists('\Quarksol\SmartChatbot\Config\SystemToolRegistry')) {
                $bootstrap = defined('SWC_CHATBOT_PATH') 
                    ? SWC_CHATBOT_PATH . 'src/bootstrap.php' 
                    : '';
                if (file_exists($bootstrap)) {
                    require_once $bootstrap;
                }
            }
            
            // Map slug to system tool class
            $tool_class_map = [
                'load_skill' => '\Quarksol\SmartChatbot\Skills\LoadSkillTool',
                'load_skill_reference' => '\Quarksol\SmartChatbot\Skills\LoadSkillReferenceTool',
            ];
            
            if (!isset($tool_class_map[$slug])) {
                return [
                    'success' => false,
                    'error' => "Unknown system tool: {$slug}"
                ];
            }
            
            $class = $tool_class_map[$slug];
            
            if (!class_exists($class)) {
                return [
                    'success' => false,
                    'error' => "System tool class not found: {$class}"
                ];
            }
            
            // Instantiate and invoke the tool
            $tool = new $class();
            
            // Extract the primary parameter for invocation
            // For load_skill: skill_name
            // For load_skill_reference: skill_name + reference_name
            $skill_name = $params['skill_name'] ?? $action ?? '';
            
            if ($slug === 'load_skill') {
                $result = $tool($skill_name);
            } elseif ($slug === 'load_skill_reference') {
                $reference_name = $params['reference_name'] ?? '';
                $result = $tool($skill_name, $reference_name);
            } else {
                $result = $tool($skill_name);
            }
            
            return [
                'success' => true,
                'data' => json_decode($result, true) ?? $result
            ];
            
        } catch (Exception $e) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('System tool error', ['tool' => $slug, 'error' => $e->getMessage()]);
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get tool definitions for AI prompt
     */
    public function get_tool_definitions_for_ai() {
        $definitions = [];
        
        // Always include free built-in tools (hidden from UI)
        require_once __DIR__ . '/tools/class-tool-free.php';
        $free_tools = new SWC_Tool_Free_Assistant();
        $definitions['free_assistant'] = $free_tools->get_ai_definition();
        
        foreach ($this->get_enabled_tools() as $slug => $tool) {
            $instance = $this->get_tool_instance($slug);
            if ($instance && method_exists($instance, 'get_ai_definition')) {
                $definitions[$slug] = $instance->get_ai_definition();
            }
        }
        
        return $definitions;
    }
}
