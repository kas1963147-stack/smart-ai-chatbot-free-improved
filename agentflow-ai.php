<?php
/**
 * Plugin Name: AgentFlow AI – Multi-Agent Chatbot & Automated Workflows
 * Description: AI-powered chatbot, autonomous agents, RAG knowledge search, and automated support workflows for WordPress.
 * Version: 1.1.1
 * Author: Quarksol
 * Text Domain: agentflow-ai
 * Requires at least: 5.8
 * Requires PHP: 8.1
 * Tested up to: 6.9
 */

if (!defined('ABSPATH')) {
    exit;
}

// -------------------------------------------------------------
// CONFLICT CHECK: Prevent Fatal Error if another version is active
// -------------------------------------------------------------
if (defined('SWC_CHATBOT_VERSION')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>AgentFlow AI:</strong> Another version of the AgentFlow AI plugin is already active. Please deactivate the active version first to prevent conflicts.</p></div>';
    });
    return; // Halt further execution of this file
}

// Plugin constants
define('SWC_CHATBOT_VERSION', '1.1.1');
define('SWC_CHATBOT_PATH', plugin_dir_path(__FILE__));
define('SWC_CHATBOT_URL', plugin_dir_url(__FILE__));

// PSR-4 Autoloading
if (file_exists(SWC_CHATBOT_PATH . 'vendor/autoload.php')) {
    if (file_exists(SWC_CHATBOT_PATH . 'vendor/autoload.php')) { require_once SWC_CHATBOT_PATH . 'vendor/autoload.php'; }
}

// Load bootstrap.php (Analytics API, Provider Bridge, Module System)
if (file_exists(SWC_CHATBOT_PATH . 'src/bootstrap.php')) {
    if (file_exists(SWC_CHATBOT_PATH . 'src/bootstrap.php')) { require_once SWC_CHATBOT_PATH . 'src/bootstrap.php'; }
}

// WooCommerce check - plugin works without WC but enables WC features if available
function swc_chatbot_check_woocommerce() {
    return true; // Plugin works with or without WooCommerce
}

// Initialize plugin
function swc_chatbot_init() {
    if (!swc_chatbot_check_woocommerce()) {
        return;
    }
    
    // Tables are now managed by \SWC\Database\Schema via swc_chatbot_load_chat_system()
    // Validation happens only on version change or activation
    
    // Load classes
    if (file_exists(SWC_CHATBOT_PATH . 'includes/class-admin.php')) { require_once SWC_CHATBOT_PATH . 'includes/class-admin.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/class-woocommerce.php')) { require_once SWC_CHATBOT_PATH . 'includes/class-woocommerce.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/class-orders.php')) { require_once SWC_CHATBOT_PATH . 'includes/class-orders.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/class-faq.php')) { require_once SWC_CHATBOT_PATH . 'includes/class-faq.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/class-chatbot.php')) { require_once SWC_CHATBOT_PATH . 'includes/class-chatbot.php'; }
    
    // Load base AI provider and factory (individual providers lazy-loaded on demand)
    if (file_exists(SWC_CHATBOT_PATH . 'includes/api-providers/class-ai-provider.php')) { require_once SWC_CHATBOT_PATH . 'includes/api-providers/class-ai-provider.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/api-providers/class-provider-factory.php')) { require_once SWC_CHATBOT_PATH . 'includes/api-providers/class-provider-factory.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/class-context-retriever.php')) { require_once SWC_CHATBOT_PATH . 'includes/class-context-retriever.php'; }
    
    // Register autoloader for legacy AI providers to lazy-load them on demand
    spl_autoload_register(function ($class) {
        if (strpos($class, 'SWC_Chatbot_') === 0 && strpos($class, '_Provider') !== false) {
            $provider_slug = strtolower(str_replace(['SWC_Chatbot_', '_Provider'], '', $class));
            $file = SWC_CHATBOT_PATH . 'includes/api-providers/class-' . $provider_slug . '-provider.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });
    
    // Load Agent System classes
    if (file_exists(SWC_CHATBOT_PATH . 'includes/agent/tools/class-tool-base.php')) { require_once SWC_CHATBOT_PATH . 'includes/agent/tools/class-tool-base.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/agent/tools/class-tool-sql.php')) { require_once SWC_CHATBOT_PATH . 'includes/agent/tools/class-tool-sql.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/agent/tools/class-tool-email.php')) { require_once SWC_CHATBOT_PATH . 'includes/agent/tools/class-tool-email.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/agent/class-tool-registry.php')) { require_once SWC_CHATBOT_PATH . 'includes/agent/class-tool-registry.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/agent/class-skill-loader.php')) { require_once SWC_CHATBOT_PATH . 'includes/agent/class-skill-loader.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/agent/class-prompt-composer.php')) { require_once SWC_CHATBOT_PATH . 'includes/agent/class-prompt-composer.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'includes/agent/class-google-calendar.php')) { require_once SWC_CHATBOT_PATH . 'includes/agent/class-google-calendar.php'; }
    
    // Initialize classes
    if (is_admin()) {
        new SWC_Chatbot_Admin();
    }
    new SWC_Chatbot_Main();
    
    // Register new Chat Controller (replaces legacy handlers)
    if (class_exists('Quarksol\SmartChatbot\Api\Controllers\ChatController')) {
        Quarksol\SmartChatbot\Api\Controllers\ChatController::register();
    }
    
    // Load Multi-Agent Chat System (Phase 2)
    swc_chatbot_load_chat_system();
    
    // Show activation error notice if any
    if ($activation_error = get_option('swc_activation_error')) {
        add_action('admin_notices', function() use ($activation_error) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Smart AI Chatbot:</strong> Activation completed with warnings: ' . esc_html($activation_error) . '</p></div>';
        });
        delete_option('swc_activation_error');
    }
}
add_action('plugins_loaded', 'swc_chatbot_init');

/**
 * Load Multi-Agent Chat System classes
 */
function swc_chatbot_load_chat_system() {
    // Database Schema
    if (file_exists(SWC_CHATBOT_PATH . 'includes/Database/Schema.php')) { require_once SWC_CHATBOT_PATH . 'includes/Database/Schema.php'; }
    
    // Analytics Schema Check
    if (file_exists(SWC_CHATBOT_PATH . 'src/Analytics/AnalyticsSchema.php')) {
        if (file_exists(SWC_CHATBOT_PATH . 'src/Analytics/AnalyticsSchema.php')) { require_once SWC_CHATBOT_PATH . 'src/Analytics/AnalyticsSchema.php'; }
    }
    
    // History Schema Check
    if (file_exists(SWC_CHATBOT_PATH . 'src/History/HistorySchema.php')) {
        if (file_exists(SWC_CHATBOT_PATH . 'src/History/HistorySchema.php')) { require_once SWC_CHATBOT_PATH . 'src/History/HistorySchema.php'; }
    }
    
    // Models
    if (file_exists(SWC_CHATBOT_PATH . 'src/Models/ChatAgent.php')) { require_once SWC_CHATBOT_PATH . 'src/Models/ChatAgent.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Models/ChatAssignment.php')) { require_once SWC_CHATBOT_PATH . 'src/Models/ChatAssignment.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Models/ChatSession.php')) { require_once SWC_CHATBOT_PATH . 'src/Models/ChatSession.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Models/ChatRating.php')) { require_once SWC_CHATBOT_PATH . 'src/Models/ChatRating.php'; }
    
    // Services
    if (file_exists(SWC_CHATBOT_PATH . 'src/Services/AgentResolver.php')) { require_once SWC_CHATBOT_PATH . 'src/Services/AgentResolver.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Services/HandoffService.php')) { require_once SWC_CHATBOT_PATH . 'src/Services/HandoffService.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Services/Logger.php')) { require_once SWC_CHATBOT_PATH . 'src/Services/Logger.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Services/ErrorHandler.php')) { require_once SWC_CHATBOT_PATH . 'src/Services/ErrorHandler.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Services/RateLimiter.php')) { require_once SWC_CHATBOT_PATH . 'src/Services/RateLimiter.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Services/SearchEnhancer.php')) { require_once SWC_CHATBOT_PATH . 'src/Services/SearchEnhancer.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Services/PageContextResolver.php')) { require_once SWC_CHATBOT_PATH . 'src/Services/PageContextResolver.php'; }
    
    // API Controllers
    if (file_exists(SWC_CHATBOT_PATH . 'src/Api/Controllers/AgentController.php')) { require_once SWC_CHATBOT_PATH . 'src/Api/Controllers/AgentController.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Api/Controllers/StreamController.php')) { require_once SWC_CHATBOT_PATH . 'src/Api/Controllers/StreamController.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Api/Controllers/SettingsController.php')) { require_once SWC_CHATBOT_PATH . 'src/Api/Controllers/SettingsController.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Api/Controllers/SessionController.php')) { require_once SWC_CHATBOT_PATH . 'src/Api/Controllers/SessionController.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Api/Controllers/ChatWidgetController.php')) { require_once SWC_CHATBOT_PATH . 'src/Api/Controllers/ChatWidgetController.php'; }
    
    // Admin Agent Manager (React UI - single unified admin interface)
    if (file_exists(SWC_CHATBOT_PATH . 'includes/admin/class-agent-manager-admin.php')) { require_once SWC_CHATBOT_PATH . 'includes/admin/class-agent-manager-admin.php'; }
    
    // Agent Templates
    if (file_exists(SWC_CHATBOT_PATH . 'src/Templates/TemplateRegistry.php')) { require_once SWC_CHATBOT_PATH . 'src/Templates/TemplateRegistry.php'; }
    
    // Knowledge Base System
    if (file_exists(SWC_CHATBOT_PATH . 'src/Knowledge/KnowledgeDocument.php')) { require_once SWC_CHATBOT_PATH . 'src/Knowledge/KnowledgeDocument.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Knowledge/ReadKnowledgeDocumentTool.php')) { require_once SWC_CHATBOT_PATH . 'src/Knowledge/ReadKnowledgeDocumentTool.php'; }
    if (file_exists(SWC_CHATBOT_PATH . 'src/Knowledge/KnowledgeController.php')) { require_once SWC_CHATBOT_PATH . 'src/Knowledge/KnowledgeController.php'; }
    
    // Register error handler
    \Quarksol\SmartChatbot\Services\ErrorHandler::register();
    
    
    
    // Ensure tables and seed content only in admin to prevent overhead
    if (is_admin()) {
        add_action('admin_init', function() {
            // Ensure chat tables exist
            if (\SWC\Database\Schema::needsUpgrade() || !\SWC\Database\Schema::tablesExist()) {
                \SWC\Database\Schema::createTables();
            }
            
            // Seed default content if not already done for this version
            if (!get_option('swc_rebranded_seed')) {
                try {
                    \SWC\Database\Schema::seedDefaultAgent();
                    \SWC\Database\Schema::seedDefaultWidgets();
                    \SWC\Database\Schema::seedDefaultKnowledge();
                    update_option('swc_rebranded_seed', true);
                } catch (\Exception $e) {
                    error_log('SWC Seeding Error: ' . $e->getMessage());
                }
            }
            
            // Ensure analytics tables exist (admin only, not on frontend)
            if (class_exists('\\Quarksol\\SmartChatbot\\Analytics\\AnalyticsSchema') && (!\Quarksol\SmartChatbot\Analytics\AnalyticsSchema::tablesExist() || \Quarksol\SmartChatbot\Analytics\AnalyticsSchema::needsUpgrade())) {
                \Quarksol\SmartChatbot\Analytics\AnalyticsSchema::createTables();
            }
            
            // Ensure history tables exist (admin only)
            if (class_exists('\\Quarksol\\SmartChatbot\\History\\HistorySchema') && (!\Quarksol\SmartChatbot\History\HistorySchema::tablesExist() || \Quarksol\SmartChatbot\History\HistorySchema::needsUpgrade())) {
                \Quarksol\SmartChatbot\History\HistorySchema::createTables();
            }
        });
    }
    
    // Register REST API routes — each wrapped in try/catch so one failure doesn't kill all routes
    add_action('rest_api_init', function() {
        $controllers = [
            ['Quarksol\SmartChatbot\Api\\Controllers\\AgentController', 'register'],
            ['Quarksol\SmartChatbot\Api\\Controllers\\StreamController', 'register'],
            ['Quarksol\SmartChatbot\Knowledge\\KnowledgeController', 'register'],
            ['Quarksol\SmartChatbot\Api\\Controllers\\ChatWidgetController', 'register'],
            ['Quarksol\SmartChatbot\Api\\KnowledgeController', 'register'],
            ['Quarksol\SmartChatbot\Api\\Controllers\\SessionController', 'register'],
            ['Quarksol\SmartChatbot\Api\\Controllers\\WorkspaceController', 'registerRoutes'],
            ['Quarksol\SmartChatbot\Api\\DocumentsController', 'register'],
            ['Quarksol\SmartChatbot\Api\\AgentDocumentsController', 'register'],
            ['Quarksol\SmartChatbot\Api\\Controllers\\HistoryController', 'register'],
        ];
        
        foreach ($controllers as $callback) {
            try {
                if (class_exists($callback[0])) {
                    call_user_func($callback);
                } else {
                    error_log('[SWC REST] Class not found, skipping: ' . $callback[0]);
                }
            } catch (\Throwable $e) {
                error_log('[SWC REST] Failed to register ' . $callback[0] . ': ' . $e->getMessage());
            }
        }
        
        // SettingsController (uses instance method)
        try {
            if (class_exists('Quarksol\SmartChatbot\Api\\Controllers\\SettingsController')) {
                $settings = new \Quarksol\SmartChatbot\Api\Controllers\SettingsController();
                $settings->register();
            }
        } catch (\Throwable $e) {
            error_log('[SWC REST] SettingsController failed: ' . $e->getMessage());
        }
        
        // API Pricing Controller
        try {
            $pricingFile = SWC_CHATBOT_PATH . 'src/Api/ApiPricingController.php';
            if (file_exists($pricingFile)) {
                require_once $pricingFile;
                if (class_exists('Quarksol\SmartChatbot\Api\\ApiPricingController')) {
                    $pricingController = new \Quarksol\SmartChatbot\Api\ApiPricingController();
                    $pricingController->registerRoutes();
                }
            }
        } catch (\Throwable $e) {
            error_log('[SWC REST] ApiPricingController failed: ' . $e->getMessage());
        }
        
    });
    
    // Register Toolkits (150+ agentic tools)
    add_action('init', function() {
        if (!class_exists('SWC_Chatbot_Tool_Registry')) {
            return;
        }
        
        $toolkitPath = SWC_CHATBOT_PATH . 'toolkits/';
        $toolkits = [
            'WooCommerce' => 'WooCommerceToolkit',
            'WordPress' => 'WordPressToolkit',
            'WebResearch' => 'WebResearchToolkit',
            'SEO' => 'SEOToolkit',
        ];
        
        foreach ($toolkits as $folder => $class) {
            $file = $toolkitPath . $folder . '/' . $class . '.php';
            if (file_exists($file)) {
                require_once $file;
                $fullClass = "Toolkits\\{$folder}\\{$class}";
                if (class_exists($fullClass)) {
                    \SWC_Chatbot_Tool_Registry::register_toolkit($folder, new $fullClass());
                }
            }
        }
    }, 20);
}

// Deprecated: Tables are now managed by \SWC\Database\Schema
function swc_chatbot_ensure_tables() {
    // No-op for performance. 
    // If you need to force table creation, use \SWC\Database\Schema::createTables()
    // or trigger a plugin update/reactivation.
}

// Activation hook
function swc_chatbot_activate() {
    try {
        // Set default options
        $defaults = array(
            'enabled' => true,
            'welcome_message' => 'Hi! How can I help you today?',
            'bot_name' => 'Shopping Assistant',
            'primary_color' => '#6366f1',
            'position' => 'right',
            'guest_order_lookup' => true
        );
        
        if (!get_option('swc_chatbot_settings')) {
            add_option('swc_chatbot_settings', $defaults);
        }
        
        // Create Multi-Agent Chat System tables
        // Important: Load the system classes first because activation happens before plugins_loaded
        swc_chatbot_load_chat_system();
        
        // Reset seeding flag so agents/widgets/knowledge are re-seeded on activation
        delete_option('swc_rebranded_seed');
        
        // Create tables and seed default content
        if (class_exists('SWC\\Database\\Schema')) {
            \SWC\Database\Schema::createTables();
            \SWC\Database\Schema::seedDefaultAgent();
            \SWC\Database\Schema::seedDefaultWidgets();
            \SWC\Database\Schema::seedDefaultKnowledge();
            update_option('swc_rebranded_seed', true);
            error_log('SWC Activation: Tables created and defaults seeded successfully');
        } else {
            error_log('SWC Activation ERROR: SWC\\Database\\Schema class not found after loading chat system');
        }
        
        // Create Analytics tables (non-critical)
        if (file_exists(SWC_CHATBOT_PATH . 'src/Analytics/AnalyticsSchema.php')) {
            require_once SWC_CHATBOT_PATH . 'src/Analytics/AnalyticsSchema.php';
            if (class_exists('\\Quarksol\\SmartChatbot\\Analytics\\AnalyticsSchema')) {
                \Quarksol\SmartChatbot\Analytics\AnalyticsSchema::createTables();
            }
        }
        
        // Show introduction popup on first activation only
        if (!get_option('swc_intro_shown')) {
            update_option('swc_show_intro', true);
            update_option('swc_intro_shown', true);
        }
        
    } catch (\Throwable $e) {
        error_log('Smart AI Chatbot activation error: ' . $e->getMessage());
        update_option('swc_activation_error', $e->getMessage());
    }
}
register_activation_hook(__FILE__, 'swc_chatbot_activate');

// Deactivation hook
function swc_chatbot_deactivate() {
    wp_clear_scheduled_hook('swc_analytics_aggregate');
}
register_deactivation_hook(__FILE__, 'swc_chatbot_deactivate');
