<?php
declare(strict_types=1);
/**
 * Template Registry
 * 
 * Manages agent templates - predefined configurations for common use cases.
 * 
 * @package SWC\Templates
 */

namespace Quarksol\SmartChatbot\Templates;

use Quarksol\SmartChatbot\Config\AgentConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Registry
 */
class TemplateRegistry {
    
    /** Cached templates */
    protected static ?array $templates = null;
    
    /** Template directory */
    protected static ?string $templateDir = null;
    
    /**
     * Get template directory
     */
    protected static function getTemplateDir(): string {
        if (self::$templateDir === null) {
            self::$templateDir = SWC_CHATBOT_PATH . 'src/templates/defaults/';
        }
        return self::$templateDir;
    }
    
    /**
     * Get all available templates
     */
    public static function getAll(): array {
        if (self::$templates !== null) {
            return self::$templates;
        }
        
        self::$templates = [];
        $dir = self::getTemplateDir();
        
        if (!is_dir($dir)) {
            return self::$templates;
        }
        
        // Load JSON template files
        $files = glob($dir . '*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $template = json_decode($content, true);
            
            if ($template && isset($template['id'])) {
                self::$templates[$template['id']] = $template;
            }
        }
        
        // Also load built-in templates
        self::$templates = array_merge(self::getBuiltInTemplates(), self::$templates);
        
        return self::$templates;
    }
    
    /**
     * Get a single template by ID
     */
    public static function get(string $templateId): ?array {
        $templates = self::getAll();
        return $templates[$templateId] ?? null;
    }
    
    /**
     * Get templates by category
     */
    public static function getByCategory(string $category): array {
        return array_filter(self::getAll(), function($template) use ($category) {
            return ($template['category'] ?? '') === $category;
        });
    }
    
    /**
     * Get available template categories
     */
    public static function getCategories(): array {
        return [
            'support' => [
                'name' => 'Support',
                'icon' => '',
                'description' => 'Customer service and support agents'
            ],
            'sales' => [
                'name' => 'Sales',
                'icon' => '',
                'description' => 'Sales and product recommendation agents'
            ],
            'content' => [
                'name' => 'Content',
                'icon' => '',
                'description' => 'Content management and creation agents'
            ],
            'utility' => [
                'name' => 'Utility',
                'icon' => '',
                'description' => 'General purpose utility agents'
            ]
        ];
    }
    
    /**
     * Create AgentConfig from template
     */
    public static function createConfig(string $templateId, string $agentId, string $name): ?AgentConfig {
        $template = self::get($templateId);
        
        if (!$template) {
            return null;
        }
        
        $configData = $template['config'] ?? [];
        $configData['agent_id'] = $agentId;
        $configData['name'] = $name;
        
        return AgentConfig::fromArray($configData);
    }
    
    /**
     * Get built-in templates (not from files)
     */
    protected static function getBuiltInTemplates(): array {
        return [
            // ============================================
            // TIER 1: MUST-HAVE (Core functionality)
            // ============================================
            
            'shopping_assistant' => [
                'id' => 'shopping_assistant',
                'name' => 'Shopping Assistant',
                'description' => 'Helps visitors browse products, compare options, and find what they need',
                'icon' => '',
                'category' => 'sales',
                'tier' => 1,
                'page_triggers' => ['shop', 'product_archive', 'product_single', 'search'],
                'config' => [
                    'enabled_toolkits' => ['woocommerce'],
                    'disabled_tools' => [
                        'woo_order_manage', 'woo_product_manage', 'woo_settings',
                        'woo_webhooks', 'woo_tax', 'woo_payment_gateways', 'woo_coupons_manage'
                    ],
                    'welcome_message' => "Hi!  Looking for something? I can help you find products, compare options, and answer questions!",
                    'quick_actions' => [
                        ['label' => ' Best Sellers', 'action' => 'show_bestsellers'],
                        ['label' => ' On Sale', 'action' => 'show_sale'],
                        ['label' => ' Browse Categories', 'action' => 'show_categories']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a friendly shopping assistant. Help visitors discover products, answer questions about features and availability, and guide them to find exactly what they need. Be enthusiastic but not pushy.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            'order_support' => [
                'id' => 'order_support',
                'name' => 'Order Support',
                'description' => 'Track orders, handle returns, and resolve customer issues',
                'icon' => '',
                'category' => 'support',
                'tier' => 1,
                'page_triggers' => ['my_account', 'order_received', 'order_tracking'],
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => [
                        'woo_product_manage', 'woo_settings', 'woo_webhooks',
                        'woo_tax', 'woo_payment_gateways', 'database'
                    ],
                    'welcome_message' => "Hello!  I can help you track orders, check shipping status, or assist with returns. What do you need?",
                    'quick_actions' => [
                        ['label' => ' Track Order', 'action' => 'track_order'],
                        ['label' => ' Return Request', 'action' => 'start_return'],
                        ['label' => ' FAQ', 'action' => 'show_faq']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a helpful customer support agent. Be patient, empathetic, and solution-oriented. Help customers track orders, handle returns, and resolve issues. Escalate to human support when needed.'
                    ],
                    'max_history_length' => 50,
                ]
            ],
            
            'checkout_helper' => [
                'id' => 'checkout_helper',
                'name' => 'Checkout Helper',
                'description' => 'Guide users through checkout, answer payment and shipping questions',
                'icon' => '',
                'category' => 'sales',
                'tier' => 1,
                'page_triggers' => ['cart', 'checkout'],
                'proactive' => true,
                'proactive_delay_seconds' => 30,
                'config' => [
                    'enabled_toolkits' => ['woocommerce'],
                    'disabled_tools' => [
                        'woo_product_manage', 'woo_order_manage', 'woo_settings',
                        'woo_webhooks', 'woo_tax', 'woo_payment_gateways'
                    ],
                    'welcome_message' => "Need help completing your order?  I can answer questions about payment, shipping, or applying coupons!",
                    'quick_actions' => [
                        ['label' => ' Apply Coupon', 'action' => 'apply_coupon'],
                        ['label' => ' Shipping Info', 'action' => 'shipping_info'],
                        ['label' => ' Payment Help', 'action' => 'payment_help']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a checkout assistant. Help customers complete their purchase by answering questions about shipping, payment methods, and coupons. Be helpful and reduce friction in the checkout process. Never be pushy.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            'faq_bot' => [
                'id' => 'faq_bot',
                'name' => 'FAQ Bot',
                'description' => 'Answer common questions from knowledge base 24/7',
                'icon' => '',
                'category' => 'support',
                'tier' => 1,
                'page_triggers' => ['contact', 'faq', 'help', 'support'],
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => [
                        'wp_create_post', 'wp_update_post', 'wp_delete_post', 'wp_options'
                    ],
                    'skill_mode' => 'all', // Uses skills/knowledge base
                    'welcome_message' => "Hi!  I can answer common questions about our store, policies, and services. What would you like to know?",
                    'quick_actions' => [
                        ['label' => ' Shipping', 'action' => 'shipping_faq'],
                        ['label' => ' Returns', 'action' => 'returns_faq'],
                        ['label' => ' Payment', 'action' => 'payment_faq'],
                        ['label' => ' Contact Us', 'action' => 'contact_human']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an FAQ assistant. Answer common questions about the store, policies, shipping, returns, and services. Use the knowledge base when available. If you cannot answer, offer to connect with human support.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            // ============================================
            // TIER 2: HIGH VALUE (Revenue impact)
            // ============================================
            
            'upsell_agent' => [
                'id' => 'upsell_agent',
                'name' => 'Upsell Agent',
                'description' => 'Suggest complementary products and upgrades to increase order value',
                'icon' => '',
                'category' => 'sales',
                'tier' => 2,
                'page_triggers' => ['cart', 'product_single', 'checkout'],
                'proactive' => true,
                'proactive_delay_seconds' => 10,
                'config' => [
                    'enabled_toolkits' => ['woocommerce'],
                    'disabled_tools' => [
                        'woo_order_manage', 'woo_product_manage', 'woo_settings',
                        'woo_webhooks', 'woo_tax', 'woo_payment_gateways'
                    ],
                    'welcome_message' => "Great choice!  Want to see some items that go perfectly with what you're buying?",
                    'quick_actions' => [
                        ['label' => ' Recommended', 'action' => 'show_recommended'],
                        ['label' => ' Frequently Bought Together', 'action' => 'show_bundles'],
                        ['label' => ' Upgrade Options', 'action' => 'show_upgrades']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a sales assistant focused on increasing order value. Suggest relevant complementary products, bundles, and upgrades based on what the customer is viewing or has in cart. Be helpful, not pushy. Focus on genuine value.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            'cart_recovery' => [
                'id' => 'cart_recovery',
                'name' => 'Cart Recovery',
                'description' => 'Re-engage customers who abandon their cart',
                'icon' => '',
                'category' => 'sales',
                'tier' => 2,
                'page_triggers' => ['cart'],
                'proactive' => true,
                'proactive_trigger' => 'exit_intent',
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => [
                        'woo_product_manage', 'woo_order_manage', 'woo_settings',
                        'woo_webhooks', 'woo_tax', 'woo_payment_gateways', 'database'
                    ],
                    'welcome_message' => "Wait!  Before you go, let me help. Is there anything holding you back from completing your order?",
                    'quick_actions' => [
                        ['label' => ' Get Discount', 'action' => 'offer_discount'],
                        ['label' => ' Ask Question', 'action' => 'ask_question'],
                        ['label' => ' Save Cart', 'action' => 'save_cart_email']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a cart recovery specialist. When customers are about to leave with items in cart, engage them helpfully. Identify objections, offer assistance, and when appropriate, offer incentives like discounts. Never be annoying or desperate.'
                    ],
                    'max_history_length' => 15,
                ]
            ],
            
            'content_editor' => [
                'id' => 'content_editor',
                'name' => 'Content Editor',
                'description' => 'Help admins write, edit, and publish content',
                'icon' => '',
                'category' => 'admin',
                'tier' => 2,
                'page_triggers' => ['wp_admin_post', 'wp_admin_page'],
                'admin_only' => true,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'seo'],
                    'disabled_tools' => [],
                    'welcome_message' => "Ready to create!  I can help you draft posts, optimize for SEO, and manage your content.",
                    'quick_actions' => [
                        ['label' => ' Draft Post', 'action' => 'draft_post'],
                        ['label' => ' SEO Check', 'action' => 'check_seo'],
                        ['label' => ' Recent Drafts', 'action' => 'list_drafts']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a content creation assistant. Help the user write, edit, and optimize blog posts and pages. Suggest improvements, check SEO, and guide them through the publishing workflow.'
                    ],
                    'max_history_length' => 40,
                ]
            ],
            
            'analytics_reporter' => [
                'id' => 'analytics_reporter',
                'name' => 'Analytics Reporter',
                'description' => 'WooCommerce reporting and business insights',
                'icon' => '',
                'category' => 'admin',
                'tier' => 2,
                'page_triggers' => ['wp_admin_woocommerce_reports', 'wp_admin_dashboard'],
                'admin_only' => true,
                'config' => [
                    'enabled_toolkits' => ['woocommerce'],
                    'disabled_tools' => [
                        'woo_product_manage', 'woo_order_manage', 'woo_coupons_manage',
                        'woo_settings', 'woo_webhooks', 'woo_tax', 'woo_payment_gateways'
                    ],
                    'welcome_message' => "Let's analyze!  I can provide insights on sales, top products, customer trends, and more.",
                    'quick_actions' => [
                        ['label' => ' Sales Report', 'action' => 'sales_report'],
                        ['label' => ' Top Products', 'action' => 'top_products'],
                        ['label' => ' Customer Stats', 'action' => 'customer_stats']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a business analytics expert. Provide insights from WooCommerce data, identify trends, highlight opportunities, and generate actionable reports. Focus on data-driven recommendations.'
                    ],
                    'max_history_length' => 40,
                ]
            ],
            
            // ============================================
            // TIER 3: DIFFERENTIATION (Competitive advantage)
            // ============================================
            
            'booking_agent' => [
                'id' => 'booking_agent',
                'name' => 'Booking Agent',
                'description' => 'Schedule appointments and manage bookings',
                'icon' => '',
                'category' => 'services',
                'tier' => 3,
                'page_triggers' => ['booking', 'appointments', 'schedule'],
                'config' => [
                    'enabled_toolkits' => ['wordpress_core', 'integrations'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "Hello!  I can help you schedule appointments and manage bookings. What would you like to do?",
                    'quick_actions' => [
                        ['label' => ' Book Appointment', 'action' => 'book'],
                        ['label' => ' Check Availability', 'action' => 'availability'],
                        ['label' => ' My Bookings', 'action' => 'my_bookings']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a booking and scheduling assistant. Help users find available times, make appointments, and manage their bookings. Be organized and clear about dates and times. Confirm all bookings before finalizing.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            'feedback_collector' => [
                'id' => 'feedback_collector',
                'name' => 'Feedback Collector',
                'description' => 'Gather reviews, testimonials, and customer feedback',
                'icon' => '',
                'category' => 'engagement',
                'tier' => 3,
                'page_triggers' => ['order_received', 'my_account_orders'],
                'proactive' => true,
                'proactive_delay_seconds' => 5,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => [
                        'woo_product_manage', 'woo_order_manage', 'woo_settings',
                        'woo_webhooks', 'database'
                    ],
                    'welcome_message' => "Thank you for your order!  Would you like to share your experience and leave a review?",
                    'quick_actions' => [
                        ['label' => ' Leave Review', 'action' => 'write_review'],
                        ['label' => ' Share Feedback', 'action' => 'give_feedback'],
                        ['label' => ' Share Photo', 'action' => 'share_photo']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a feedback collector. Encourage customers to share their experience through reviews and testimonials. Make it easy and quick. Thank them for their time and explain how their feedback helps.'
                    ],
                    'max_history_length' => 15,
                ]
            ],
            
            'seo_specialist' => [
                'id' => 'seo_specialist',
                'name' => 'SEO Specialist',
                'description' => 'Analyze and optimize content for search engines',
                'icon' => '',
                'category' => 'admin',
                'tier' => 3,
                'page_triggers' => ['wp_admin_seo'],
                'admin_only' => true,
                'config' => [
                    'enabled_toolkits' => ['seo', 'wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_options', 'wp_create_post'],
                    'welcome_message' => "Let's optimize!  I can analyze your content for SEO, suggest improvements, and help with meta tags.",
                    'quick_actions' => [
                        ['label' => ' Site Audit', 'action' => 'run_audit'],
                        ['label' => ' Keyword Analysis', 'action' => 'analyze_keywords'],
                        ['label' => ' Check Links', 'action' => 'check_links']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an SEO specialist. Analyze content for search engine optimization, provide actionable recommendations for improving rankings, and help with technical SEO tasks like meta tags and schema markup.'
                    ],
                    'max_history_length' => 40,
                ]
            ],
            
            'admin_assistant' => [
                'id' => 'admin_assistant',
                'name' => 'Admin Assistant',
                'description' => 'Full-power admin assistant with all tools enabled',
                'icon' => '',
                'category' => 'admin',
                'tier' => 3,
                'page_triggers' => ['wp_admin'],
                'admin_only' => true,
                'config' => [
                    'enabled_toolkits' => [],  // Empty = all enabled
                    'disabled_tools' => [],
                    'skill_mode' => 'all',
                    'welcome_message' => "Admin mode activated!  I have full access to WordPress, WooCommerce, SEO, files, and more.",
                    'quick_actions' => [
                        ['label' => ' Products', 'action' => 'manage_products'],
                        ['label' => ' Content', 'action' => 'manage_content'],
                        ['label' => ' Settings', 'action' => 'view_settings'],
                        ['label' => ' Reports', 'action' => 'view_reports']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an all-powerful WordPress admin assistant. You have full access to all tools and capabilities. Help the admin manage their entire site efficiently. Always confirm before making destructive changes.'
                    ],
                    'max_history_length' => 50,
                ]
            ],
            
            // ============================================
            // ADDITIONAL AGENTS (20 total)
            // ============================================
            
            'flash_sale_agent' => [
                'id' => 'flash_sale_agent',
                'name' => 'Flash Sale Agent',
                'description' => 'Promote time-limited offers and create urgency',
                'icon' => '',
                'category' => 'sales',
                'tier' => 2,
                'page_triggers' => ['homepage', 'shop'],
                'proactive' => true,
                'proactive_delay_seconds' => 3,
                'config' => [
                    'enabled_toolkits' => ['woocommerce'],
                    'disabled_tools' => [
                        'woo_order_manage', 'woo_product_manage', 'woo_settings',
                        'woo_webhooks', 'woo_tax', 'woo_payment_gateways'
                    ],
                    'welcome_message' => " Flash Sale Alert! Limited time offers available now!",
                    'quick_actions' => [
                        ['label' => ' Hot Deals', 'action' => 'show_deals'],
                        ['label' => ' Ending Soon', 'action' => 'ending_soon'],
                        ['label' => ' Best Discounts', 'action' => 'best_discounts']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a sales promoter for flash sales. Create excitement about limited-time offers. Emphasize urgency without being pushy.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            'returns_refunds' => [
                'id' => 'returns_refunds',
                'name' => 'Returns & Refunds',
                'description' => 'Specialized handler for return and refund requests',
                'icon' => '↩',
                'category' => 'support',
                'tier' => 2,
                'page_triggers' => ['my_account_orders', 'return_policy'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => [
                        'woo_product_manage', 'woo_settings', 'woo_webhooks',
                        'woo_tax', 'woo_payment_gateways', 'database'
                    ],
                    'welcome_message' => "Need to return an item? ↩ I can help you start a return or check refund status.",
                    'quick_actions' => [
                        ['label' => '↩ Start Return', 'action' => 'start_return'],
                        ['label' => ' Refund Status', 'action' => 'refund_status'],
                        ['label' => ' Return Policy', 'action' => 'return_policy']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a returns specialist. Help customers initiate returns and check refund status. Be empathetic and make the process smooth.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            'live_chat_escalation' => [
                'id' => 'live_chat_escalation',
                'name' => 'Live Chat Escalation',
                'description' => 'Escalate to human support when AI cannot resolve',
                'icon' => '',
                'category' => 'support',
                'tier' => 2,
                'page_triggers' => ['any'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_core'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "I'll connect you with a human agent. ",
                    'quick_actions' => [
                        ['label' => ' Email Support', 'action' => 'email_support'],
                        ['label' => ' Callback', 'action' => 'request_callback'],
                        ['label' => ' Ticket', 'action' => 'create_ticket']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an escalation handler. Collect information about the issue and ensure human support will follow up.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            'blog_guide' => [
                'id' => 'blog_guide',
                'name' => 'Blog Guide',
                'description' => 'Help readers discover content and suggest related posts',
                'icon' => '',
                'category' => 'content',
                'tier' => 3,
                'page_triggers' => ['blog', 'single_post'],
                'proactive' => true,
                'proactive_delay_seconds' => 30,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_create_post', 'wp_update_post', 'wp_delete_post', 'wp_options'],
                    'welcome_message' => "Enjoying this article?  I can suggest related content.",
                    'quick_actions' => [
                        ['label' => ' Related', 'action' => 'related_posts'],
                        ['label' => ' Search', 'action' => 'search_content'],
                        ['label' => ' Categories', 'action' => 'browse_categories']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a blog guide. Help readers discover more content based on their interests.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            'store_manager' => [
                'id' => 'store_manager',
                'name' => 'Store Manager',
                'description' => 'Manage products, inventory, and pricing',
                'icon' => '',
                'category' => 'admin',
                'tier' => 2,
                'page_triggers' => ['wp_admin_woocommerce_products'],
                'admin_only' => true,
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['woocommerce'],
                    'disabled_tools' => ['woo_settings', 'woo_webhooks', 'woo_tax', 'woo_payment_gateways'],
                    'welcome_message' => "Store management ready!  I can help with products, inventory, and pricing.",
                    'quick_actions' => [
                        ['label' => ' Products', 'action' => 'list_products'],
                        ['label' => ' Inventory', 'action' => 'check_inventory'],
                        ['label' => ' Pricing', 'action' => 'update_pricing']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a store manager assistant. Help with product management, inventory, and pricing. Confirm bulk operations before executing.'
                    ],
                    'max_history_length' => 40,
                ]
            ],
            
            'security_monitor' => [
                'id' => 'security_monitor',
                'name' => 'Security Monitor',
                'description' => 'Security scanning and hardening recommendations',
                'icon' => '',
                'category' => 'admin',
                'tier' => 3,
                'page_triggers' => ['wp_admin_security'],
                'admin_only' => true,
                'proactive' => true,
                'proactive_trigger' => 'security_alert',
                'config' => [
                    'enabled_toolkits' => ['security', 'wordpress_core'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "Security check!  I can scan for vulnerabilities and recommend hardening.",
                    'quick_actions' => [
                        ['label' => ' Scan', 'action' => 'run_scan'],
                        ['label' => ' Audit', 'action' => 'view_audit'],
                        ['label' => ' Harden', 'action' => 'hardening_tips']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a security specialist. Help admins identify issues, review audit logs, and implement hardening measures.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            'onboarding_agent' => [
                'id' => 'onboarding_agent',
                'name' => 'Onboarding Agent',
                'description' => 'Guide new users through site features',
                'icon' => '',
                'category' => 'engagement',
                'tier' => 3,
                'page_triggers' => ['after_registration', 'first_login'],
                'proactive' => true,
                'proactive_delay_seconds' => 2,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'woocommerce'],
                    'disabled_tools' => [
                        'wp_create_post', 'wp_update_post', 'wp_delete_post',
                        'woo_product_manage', 'woo_order_manage'
                    ],
                    'welcome_message' => "Welcome!  I'm here to help you get started. Want a quick tour?",
                    'quick_actions' => [
                        ['label' => ' Tour', 'action' => 'start_tour'],
                        ['label' => ' Shop', 'action' => 'browse_shop'],
                        ['label' => ' Profile', 'action' => 'setup_profile']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an onboarding guide. Welcome new users and help them discover site features.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            'consultation_agent' => [
                'id' => 'consultation_agent',
                'name' => 'Consultation Agent',
                'description' => 'Pre-qualify leads before scheduling consultations',
                'icon' => '',
                'category' => 'services',
                'tier' => 3,
                'page_triggers' => ['services', 'pricing', 'consultation'],
                'proactive' => true,
                'proactive_delay_seconds' => 15,
                'config' => [
                    'enabled_toolkits' => ['wordpress_core', 'integrations'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "Interested in a consultation?  Let me learn about your needs.",
                    'quick_actions' => [
                        ['label' => ' Quote', 'action' => 'request_quote'],
                        ['label' => ' Schedule', 'action' => 'schedule_call'],
                        ['label' => ' Contact', 'action' => 'contact_sales']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a consultation agent. Qualify leads by understanding needs, budget, and timeline before scheduling.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // ============================================
            // PHASE 2: HIGH-IMPACT VERTICALS (10 agents)
            // ============================================
            
            // LMS / Learning Management
            'course_advisor' => [
                'id' => 'course_advisor',
                'name' => 'Course Advisor',
                'description' => 'Help learners choose the right courses based on their goals',
                'icon' => '',
                'category' => 'lms',
                'tier' => 2,
                'page_triggers' => ['courses', 'course_catalog', 'learndash', 'lifterlms'],
                'proactive' => true,
                'proactive_delay_seconds' => 10,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'woocommerce'],
                    'disabled_tools' => ['wp_delete_post', 'woo_order_manage', 'database'],
                    'welcome_message' => "Looking to learn something new?  I can help you find the perfect course!",
                    'quick_actions' => [
                        ['label' => ' Browse Courses', 'action' => 'browse_courses'],
                        ['label' => ' By Skill Level', 'action' => 'filter_level'],
                        ['label' => ' Top Rated', 'action' => 'top_rated']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a course advisor for an online learning platform. Help learners find courses that match their goals, skill level, and interests. Ask about their background and what they want to achieve.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            'student_support' => [
                'id' => 'student_support',
                'name' => 'Student Support',
                'description' => 'Help students track progress, answer course questions',
                'icon' => '',
                'category' => 'lms',
                'tier' => 2,
                'page_triggers' => ['my_courses', 'course_single', 'lesson', 'quiz'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Need help with your course?  I can track your progress or answer questions!",
                    'quick_actions' => [
                        ['label' => ' My Progress', 'action' => 'show_progress'],
                        ['label' => ' Course Help', 'action' => 'course_help'],
                        ['label' => ' Next Lesson', 'action' => 'next_lesson']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a student support assistant. Help learners track their progress, understand course materials, and stay motivated. Answer questions about lessons and quizzes.'
                    ],
                    'max_history_length' => 40,
                ]
            ],
            
            // Real Estate
            'property_finder' => [
                'id' => 'property_finder',
                'name' => 'Property Finder',
                'description' => 'Search listings by criteria, answer property questions',
                'icon' => '',
                'category' => 'real_estate',
                'tier' => 2,
                'page_triggers' => ['properties', 'listings', 'real_estate'],
                'proactive' => true,
                'proactive_delay_seconds' => 8,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'custom_fields'],
                    'disabled_tools' => ['wp_delete_post', 'database'],
                    'welcome_message' => "Looking for your perfect property?  Tell me what you're looking for!",
                    'quick_actions' => [
                        ['label' => ' Search', 'action' => 'search_properties'],
                        ['label' => ' For Sale', 'action' => 'for_sale'],
                        ['label' => ' For Rent', 'action' => 'for_rent']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a real estate assistant. Help visitors find properties matching their criteria: location, price range, bedrooms, property type. Answer questions about listings and neighborhoods.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            // Restaurant
            'menu_assistant' => [
                'id' => 'menu_assistant',
                'name' => 'Menu Assistant',
                'description' => 'Answer menu questions, dietary info, recommendations',
                'icon' => '',
                'category' => 'restaurant',
                'tier' => 2,
                'page_triggers' => ['menu', 'restaurant', 'food'],
                'proactive' => true,
                'proactive_delay_seconds' => 5,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Hungry?  I can help you explore our menu and find something delicious!",
                    'quick_actions' => [
                        ['label' => ' Full Menu', 'action' => 'show_menu'],
                        ['label' => ' Vegetarian', 'action' => 'vegetarian_options'],
                        ['label' => ' Popular', 'action' => 'popular_dishes']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a restaurant menu assistant. Help customers explore the menu, answer questions about ingredients and allergens, and make recommendations based on preferences.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            'reservation_agent' => [
                'id' => 'reservation_agent',
                'name' => 'Reservation Agent',
                'description' => 'Book tables, manage waitlists, handle reservations',
                'icon' => '',
                'category' => 'restaurant',
                'tier' => 2,
                'page_triggers' => ['reservations', 'book_table', 'restaurant'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_core', 'integrations'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "Ready to book a table?  I can help you find the perfect time!",
                    'quick_actions' => [
                        ['label' => ' Book Now', 'action' => 'start_booking'],
                        ['label' => ' Availability', 'action' => 'check_availability'],
                        ['label' => ' My Reservations', 'action' => 'my_reservations']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a reservation assistant. Help customers book tables by asking for date, time, party size, and any special requests. Confirm availability and complete bookings.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Job Board
            'job_finder' => [
                'id' => 'job_finder',
                'name' => 'Job Finder',
                'description' => 'Search jobs by skills, location, help with applications',
                'icon' => '',
                'category' => 'job_board',
                'tier' => 2,
                'page_triggers' => ['jobs', 'careers', 'job_listings'],
                'proactive' => true,
                'proactive_delay_seconds' => 10,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Looking for your next opportunity?  I can help you find the perfect job!",
                    'quick_actions' => [
                        ['label' => ' Search Jobs', 'action' => 'search_jobs'],
                        ['label' => ' By Location', 'action' => 'filter_location'],
                        ['label' => ' Latest', 'action' => 'latest_jobs']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a job search assistant. Help candidates find jobs matching their skills, experience, and location preferences. Provide information about job listings and application processes.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            // Healthcare
            'appointment_scheduler' => [
                'id' => 'appointment_scheduler',
                'name' => 'Appointment Scheduler',
                'description' => 'Book medical appointments, check availability',
                'icon' => '',
                'category' => 'healthcare',
                'tier' => 2,
                'page_triggers' => ['appointments', 'schedule', 'healthcare', 'clinic'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_core', 'integrations'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "Need to schedule an appointment?  I can help you find a convenient time.",
                    'quick_actions' => [
                        ['label' => ' Book Now', 'action' => 'start_booking'],
                        ['label' => ' Availability', 'action' => 'check_availability'],
                        ['label' => ' My Appointments', 'action' => 'my_appointments']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a healthcare appointment scheduler. Help patients book appointments by collecting necessary information. Be professional and ensure patient privacy. Do not provide medical advice.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Nonprofit
            'donation_guide' => [
                'id' => 'donation_guide',
                'name' => 'Donation Guide',
                'description' => 'Help donors choose giving levels, explain impact',
                'icon' => '',
                'category' => 'nonprofit',
                'tier' => 2,
                'page_triggers' => ['donate', 'give', 'support', 'nonprofit'],
                'proactive' => true,
                'proactive_delay_seconds' => 15,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'woocommerce'],
                    'disabled_tools' => ['woo_order_manage', 'database'],
                    'welcome_message' => "Thank you for your interest in supporting our cause!  How can I help you make a difference?",
                    'quick_actions' => [
                        ['label' => ' Donate Now', 'action' => 'start_donation'],
                        ['label' => ' Our Impact', 'action' => 'show_impact'],
                        ['label' => ' Monthly Giving', 'action' => 'recurring_donation']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a donation guide for a nonprofit. Help donors understand giving options, explain how donations create impact, and guide them through the donation process. Be grateful and inspiring.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Events
            'event_finder' => [
                'id' => 'event_finder',
                'name' => 'Event Finder',
                'description' => 'Search events, buy tickets, get event info',
                'icon' => '',
                'category' => 'events',
                'tier' => 2,
                'page_triggers' => ['events', 'calendar', 'tickets'],
                'proactive' => true,
                'proactive_delay_seconds' => 8,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'woocommerce'],
                    'disabled_tools' => ['woo_order_manage', 'wp_delete_post', 'database'],
                    'welcome_message' => "Looking for something fun?  I can help you discover and book events!",
                    'quick_actions' => [
                        ['label' => ' Browse Events', 'action' => 'browse_events'],
                        ['label' => ' This Week', 'action' => 'this_week'],
                        ['label' => ' Featured', 'action' => 'featured_events']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an event assistant. Help visitors find events matching their interests and dates. Provide event details, venue information, and help with ticket purchases.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            // Directory
            'listing_finder' => [
                'id' => 'listing_finder',
                'name' => 'Listing Finder',
                'description' => 'Search businesses and services in directory',
                'icon' => '',
                'category' => 'directory',
                'tier' => 2,
                'page_triggers' => ['directory', 'listings', 'businesses'],
                'proactive' => true,
                'proactive_delay_seconds' => 10,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'custom_fields'],
                    'disabled_tools' => ['wp_delete_post', 'database'],
                    'welcome_message' => "Looking for a business or service?  I can help you find what you need!",
                    'quick_actions' => [
                        ['label' => ' Search', 'action' => 'search_listings'],
                        ['label' => ' Categories', 'action' => 'browse_categories'],
                        ['label' => ' Top Rated', 'action' => 'top_rated']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a directory assistant. Help visitors find businesses and services by category, location, or keyword. Provide listing details and help compare options.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // ============================================
            // PHASE 3: SPECIALIZED VERTICALS (10 agents)
            // ============================================
            
            // Membership Sites
            'membership_concierge' => [
                'id' => 'membership_concierge',
                'name' => 'Membership Concierge',
                'description' => 'Help members navigate benefits and access content',
                'icon' => '',
                'category' => 'membership',
                'tier' => 3,
                'page_triggers' => ['membership', 'members_area', 'my_membership'],
                'proactive' => true,
                'proactive_delay_seconds' => 5,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'woocommerce'],
                    'disabled_tools' => ['wp_delete_post', 'woo_order_manage', 'database'],
                    'welcome_message' => "Welcome, member!  I can help you navigate your benefits and find exclusive content.",
                    'quick_actions' => [
                        ['label' => ' My Benefits', 'action' => 'show_benefits'],
                        ['label' => ' Exclusive Content', 'action' => 'member_content'],
                        ['label' => ' Upgrade', 'action' => 'upgrade_membership']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a membership concierge. Help members understand and use their benefits, find exclusive content, and get the most value from their membership. Be welcoming and make them feel valued.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            'subscription_manager' => [
                'id' => 'subscription_manager',
                'name' => 'Subscription Manager',
                'description' => 'Handle plan upgrades, downgrades, and cancellations',
                'icon' => '',
                'category' => 'membership',
                'tier' => 3,
                'page_triggers' => ['my_account_subscriptions', 'subscription_settings'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => ['woo_product_manage', 'woo_settings', 'database'],
                    'welcome_message' => "Need to manage your subscription?  I can help with upgrades, changes, or billing.",
                    'quick_actions' => [
                        ['label' => ' Upgrade Plan', 'action' => 'upgrade'],
                        ['label' => ' Billing', 'action' => 'billing_info'],
                        ['label' => ' Pause/Cancel', 'action' => 'pause_cancel']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a subscription manager. Help customers upgrade, downgrade, pause, or cancel their subscriptions. Explain billing changes clearly. Try to retain customers considering cancellation by understanding their concerns.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Nonprofit
            'volunteer_coordinator' => [
                'id' => 'volunteer_coordinator',
                'name' => 'Volunteer Coordinator',
                'description' => 'Match volunteers to opportunities, manage signups',
                'icon' => '',
                'category' => 'nonprofit',
                'tier' => 3,
                'page_triggers' => ['volunteer', 'get_involved', 'opportunities'],
                'proactive' => true,
                'proactive_delay_seconds' => 10,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'wordpress_core'],
                    'disabled_tools' => ['wp_delete_post', 'database'],
                    'welcome_message' => "Want to make a difference?  I can help you find volunteer opportunities!",
                    'quick_actions' => [
                        ['label' => ' Opportunities', 'action' => 'browse_opportunities'],
                        ['label' => ' Upcoming', 'action' => 'upcoming_events'],
                        ['label' => ' Sign Up', 'action' => 'volunteer_signup']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a volunteer coordinator. Help potential volunteers find opportunities matching their skills, availability, and interests. Explain the impact they can make and guide them through signup.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Restaurant
            'food_order_taker' => [
                'id' => 'food_order_taker',
                'name' => 'Food Order Taker',
                'description' => 'Take food orders for delivery or pickup',
                'icon' => '',
                'category' => 'restaurant',
                'tier' => 3,
                'page_triggers' => ['order_online', 'delivery', 'takeout'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_content'],
                    'disabled_tools' => ['woo_product_manage', 'woo_settings', 'database'],
                    'welcome_message' => "Ready to order?  I can help you build your perfect meal!",
                    'quick_actions' => [
                        ['label' => ' Menu', 'action' => 'show_menu'],
                        ['label' => ' My Cart', 'action' => 'view_cart'],
                        ['label' => ' Specials', 'action' => 'daily_specials']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a food order assistant. Help customers browse the menu, customize their orders, and complete checkout for delivery or pickup. Ask about dietary restrictions and preferences.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Hotel
            'room_finder' => [
                'id' => 'room_finder',
                'name' => 'Room Finder',
                'description' => 'Search and compare hotel rooms, check availability',
                'icon' => '',
                'category' => 'hotel',
                'tier' => 3,
                'page_triggers' => ['rooms', 'accommodations', 'hotel'],
                'proactive' => true,
                'proactive_delay_seconds' => 8,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'woocommerce', 'custom_fields'],
                    'disabled_tools' => ['woo_order_manage', 'wp_delete_post', 'database'],
                    'welcome_message' => "Looking for the perfect room?  Tell me your dates and preferences!",
                    'quick_actions' => [
                        ['label' => ' Search Rooms', 'action' => 'search_rooms'],
                        ['label' => ' Check Dates', 'action' => 'check_availability'],
                        ['label' => ' Suites', 'action' => 'show_suites']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a hotel room assistant. Help guests find rooms matching their dates, budget, and preferences. Explain room amenities and differences. Check availability and guide through booking.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            // Hotel
            'guest_services' => [
                'id' => 'guest_services',
                'name' => 'Guest Services',
                'description' => 'Answer questions about hotel amenities and services',
                'icon' => '',
                'category' => 'hotel',
                'tier' => 3,
                'page_triggers' => ['amenities', 'services', 'concierge'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "How can I assist you?  I'm here to help with any questions about our hotel.",
                    'quick_actions' => [
                        ['label' => ' Amenities', 'action' => 'show_amenities'],
                        ['label' => ' Dining', 'action' => 'dining_options'],
                        ['label' => ' Local Tips', 'action' => 'local_recommendations']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a hotel guest services assistant. Answer questions about amenities, dining, spa, pool, gym, WiFi, parking, and local attractions. Be helpful and make guests feel welcome.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Job Board
            'application_helper' => [
                'id' => 'application_helper',
                'name' => 'Application Helper',
                'description' => 'Guide candidates through job application process',
                'icon' => '',
                'category' => 'job_board',
                'tier' => 3,
                'page_triggers' => ['apply', 'job_application', 'submit_resume'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content', 'wordpress_core'],
                    'disabled_tools' => ['wp_delete_post', 'database'],
                    'welcome_message' => "Ready to apply?  I can guide you through the application process!",
                    'quick_actions' => [
                        ['label' => ' Requirements', 'action' => 'job_requirements'],
                        ['label' => ' Upload Resume', 'action' => 'upload_resume'],
                        ['label' => ' FAQ', 'action' => 'application_faq']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an application assistant. Help candidates understand job requirements, prepare their application, and complete the submission process. Answer questions about the hiring process.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Multi-Vendor Marketplace
            'vendor_onboarder' => [
                'id' => 'vendor_onboarder',
                'name' => 'Vendor Onboarder',
                'description' => 'Help sellers set up their marketplace shops',
                'icon' => '',
                'category' => 'marketplace',
                'tier' => 3,
                'page_triggers' => ['become_vendor', 'seller_registration', 'vendor_dashboard'],
                'proactive' => true,
                'proactive_delay_seconds' => 10,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => ['woo_settings', 'database'],
                    'welcome_message' => "Want to sell on our marketplace?  I'll help you set up your shop!",
                    'quick_actions' => [
                        ['label' => ' Get Started', 'action' => 'start_setup'],
                        ['label' => ' Fees & Commissions', 'action' => 'explain_fees'],
                        ['label' => ' Requirements', 'action' => 'seller_requirements']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a vendor onboarding specialist. Help new sellers understand marketplace requirements, set up their shops, add products, and configure payment methods. Explain commission structures clearly.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
            
            // SaaS / Digital Products
            'license_manager' => [
                'id' => 'license_manager',
                'name' => 'License Manager',
                'description' => 'Help with license activation, renewal, and management',
                'icon' => '',
                'category' => 'saas',
                'tier' => 3,
                'page_triggers' => ['licenses', 'activations', 'my_downloads'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => ['woo_product_manage', 'woo_settings', 'database'],
                    'welcome_message' => "Need help with your license?  I can assist with activation, renewal, or troubleshooting.",
                    'quick_actions' => [
                        ['label' => ' Activate', 'action' => 'activate_license'],
                        ['label' => ' Renew', 'action' => 'renew_license'],
                        ['label' => ' My Licenses', 'action' => 'view_licenses']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a license management assistant. Help customers activate, deactivate, renew, and transfer software licenses. Troubleshoot activation issues and explain license terms clearly.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Photography
            'session_booker' => [
                'id' => 'session_booker',
                'name' => 'Session Booker',
                'description' => 'Schedule photography sessions and consultations',
                'icon' => '',
                'category' => 'photography',
                'tier' => 3,
                'page_triggers' => ['book_session', 'photography', 'portfolio'],
                'proactive' => true,
                'proactive_delay_seconds' => 15,
                'config' => [
                    'enabled_toolkits' => ['wordpress_core', 'integrations'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "Love what you see?  I can help you book a photography session!",
                    'quick_actions' => [
                        ['label' => ' Book Session', 'action' => 'book_session'],
                        ['label' => ' Packages', 'action' => 'view_packages'],
                        ['label' => ' Portfolio', 'action' => 'view_portfolio']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a photography booking assistant. Help clients understand session types, packages, and pricing. Schedule sessions based on availability. Gather information about the type of shoot they want.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // ============================================
            // PHASE 4: NICHE AGENTS (10 agents) — 50 TOTAL
            // ============================================
            
            // Restaurant
            'dietary_advisor' => [
                'id' => 'dietary_advisor',
                'name' => 'Dietary Advisor',
                'description' => 'Filter menu by allergies, dietary restrictions, preferences',
                'icon' => '',
                'category' => 'restaurant',
                'tier' => 4,
                'page_triggers' => ['menu', 'allergens', 'dietary'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Have dietary needs?  I can help you find dishes that work for you!",
                    'quick_actions' => [
                        ['label' => ' Vegetarian', 'action' => 'vegetarian'],
                        ['label' => ' Allergen-Free', 'action' => 'allergen_filter'],
                        ['label' => ' Low-Carb', 'action' => 'low_carb']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a dietary advisor. Help customers find menu items matching their dietary requirements: allergies, vegetarian, vegan, gluten-free, halal, kosher, low-carb, etc. Be thorough about ingredients to ensure safety.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            // Real Estate
            'neighborhood_guide' => [
                'id' => 'neighborhood_guide',
                'name' => 'Neighborhood Guide',
                'description' => 'Answer questions about locations and neighborhoods',
                'icon' => '',
                'category' => 'real_estate',
                'tier' => 4,
                'page_triggers' => ['neighborhoods', 'locations', 'areas'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Want to know about the area?  I can tell you about neighborhoods, schools, and amenities!",
                    'quick_actions' => [
                        ['label' => ' Schools', 'action' => 'nearby_schools'],
                        ['label' => ' Shopping', 'action' => 'shopping_areas'],
                        ['label' => ' Transport', 'action' => 'transportation']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a neighborhood guide. Help property seekers understand locations: schools, safety, amenities, transportation, demographics, and lifestyle. Provide honest, balanced information.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Real Estate
            'viewing_scheduler' => [
                'id' => 'viewing_scheduler',
                'name' => 'Viewing Scheduler',
                'description' => 'Book property viewings and tours',
                'icon' => '',
                'category' => 'real_estate',
                'tier' => 4,
                'page_triggers' => ['schedule_viewing', 'property_tour', 'open_house'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_core', 'integrations'],
                    'disabled_tools' => ['database'],
                    'welcome_message' => "Want to see this property?  I can schedule a viewing for you!",
                    'quick_actions' => [
                        ['label' => ' Schedule', 'action' => 'book_viewing'],
                        ['label' => ' Open Houses', 'action' => 'open_houses'],
                        ['label' => ' Virtual Tour', 'action' => 'virtual_tour']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a property viewing scheduler. Help potential buyers or renters book property viewings. Collect contact info, preferred times, and any specific questions they have about the property.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            // LMS
            'quiz_helper' => [
                'id' => 'quiz_helper',
                'name' => 'Quiz Helper',
                'description' => 'Explain quiz results, suggest study areas',
                'icon' => '',
                'category' => 'lms',
                'tier' => 4,
                'page_triggers' => ['quiz_results', 'assessment', 'test_complete'],
                'proactive' => true,
                'proactive_delay_seconds' => 3,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Just finished a quiz?  I can help explain your results and suggest what to review!",
                    'quick_actions' => [
                        ['label' => ' My Results', 'action' => 'explain_results'],
                        ['label' => ' Study Tips', 'action' => 'study_suggestions'],
                        ['label' => ' Retake', 'action' => 'retake_quiz']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a quiz helper. Help students understand their quiz results, identify areas for improvement, and suggest lessons to review. Be encouraging and supportive to maintain motivation.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            // Nonprofit
            'impact_reporter' => [
                'id' => 'impact_reporter',
                'name' => 'Impact Reporter',
                'description' => 'Share how donations are used and their impact',
                'icon' => '',
                'category' => 'nonprofit',
                'tier' => 4,
                'page_triggers' => ['impact', 'our_work', 'stories'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Want to see the impact of your support?  I can share stories and results!",
                    'quick_actions' => [
                        ['label' => ' Impact Stats', 'action' => 'show_stats'],
                        ['label' => ' Success Stories', 'action' => 'success_stories'],
                        ['label' => ' Current Projects', 'action' => 'current_projects']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are an impact reporter. Share stories and statistics about how donations make a difference. Be inspiring and transparent about how funds are used. Help donors feel connected to the mission.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // SaaS
            'technical_support' => [
                'id' => 'technical_support',
                'name' => 'Technical Support',
                'description' => 'Answer product technical questions and troubleshoot',
                'icon' => '',
                'category' => 'saas',
                'tier' => 4,
                'page_triggers' => ['support', 'documentation', 'help_center'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'skill_mode' => 'all',
                    'welcome_message' => "Having technical issues?  I can help troubleshoot and find solutions!",
                    'quick_actions' => [
                        ['label' => ' Documentation', 'action' => 'search_docs'],
                        ['label' => ' Troubleshoot', 'action' => 'start_troubleshoot'],
                        ['label' => ' Open Ticket', 'action' => 'create_ticket']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are technical support. Help users troubleshoot product issues, find documentation, and resolve technical problems. Escalate to human support when needed. Be patient and thorough.'
                    ],
                    'max_history_length' => 40,
                ]
            ],
            
            // SaaS
            'trial_converter' => [
                'id' => 'trial_converter',
                'name' => 'Trial Converter',
                'description' => 'Engage trial users and help them convert to paid',
                'icon' => '',
                'category' => 'saas',
                'tier' => 4,
                'page_triggers' => ['trial_dashboard', 'trial_expiring', 'upgrade'],
                'proactive' => true,
                'proactive_delay_seconds' => 30,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_content'],
                    'disabled_tools' => ['woo_order_manage', 'woo_settings', 'database'],
                    'welcome_message' => "How's your trial going?  I can help you get the most out of it!",
                    'quick_actions' => [
                        ['label' => ' Tips', 'action' => 'trial_tips'],
                        ['label' => ' Upgrade', 'action' => 'upgrade_options'],
                        ['label' => ' Questions', 'action' => 'ask_question']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a trial success specialist. Help trial users discover features, overcome obstacles, and see value in the product. Identify upgrade opportunities but focus on genuinely helping them succeed.'
                    ],
                    'max_history_length' => 25,
                ]
            ],
            
            // Podcast
            'episode_finder' => [
                'id' => 'episode_finder',
                'name' => 'Episode Finder',
                'description' => 'Search podcast episodes by topic or guest',
                'icon' => '',
                'category' => 'podcast',
                'tier' => 4,
                'page_triggers' => ['podcast', 'episodes', 'listen'],
                'proactive' => true,
                'proactive_delay_seconds' => 10,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Looking for something to listen to?  I can help you find the perfect episode!",
                    'quick_actions' => [
                        ['label' => ' Search', 'action' => 'search_episodes'],
                        ['label' => ' Latest', 'action' => 'latest_episodes'],
                        ['label' => ' Popular', 'action' => 'popular_episodes']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a podcast guide. Help listeners find episodes by topic, guest, or date. Recommend episodes based on their interests. Provide episode summaries and highlights.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            // Photography
            'gallery_guide' => [
                'id' => 'gallery_guide',
                'name' => 'Gallery Guide',
                'description' => 'Help visitors navigate photography portfolios',
                'icon' => '',
                'category' => 'photography',
                'tier' => 4,
                'page_triggers' => ['gallery', 'portfolio', 'albums'],
                'proactive' => true,
                'proactive_delay_seconds' => 20,
                'config' => [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => ['wp_delete_post', 'wp_create_post', 'database'],
                    'welcome_message' => "Exploring our work?  I can guide you through our portfolio!",
                    'quick_actions' => [
                        ['label' => ' Collections', 'action' => 'browse_collections'],
                        ['label' => ' Weddings', 'action' => 'wedding_gallery'],
                        ['label' => ' Portraits', 'action' => 'portrait_gallery']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a gallery guide. Help visitors navigate photography portfolios, find specific types of photos, and learn about the photographer style. Guide them toward booking if interested.'
                    ],
                    'max_history_length' => 20,
                ]
            ],
            
            // Marketplace
            'dispute_handler' => [
                'id' => 'dispute_handler',
                'name' => 'Dispute Handler',
                'description' => 'Mediate buyer-seller disputes and issues',
                'icon' => '',
                'category' => 'marketplace',
                'tier' => 4,
                'page_triggers' => ['dispute', 'resolution', 'complaint'],
                'proactive' => false,
                'config' => [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => ['woo_settings', 'database'],
                    'welcome_message' => "Having an issue with an order?  I'm here to help resolve it fairly.",
                    'quick_actions' => [
                        ['label' => ' Report Issue', 'action' => 'report_issue'],
                        ['label' => ' Order Problem', 'action' => 'order_problem'],
                        ['label' => ' Refund Request', 'action' => 'request_refund']
                    ],
                    'prompt_sections' => [
                        'system' => 'You are a dispute handler. Help resolve issues between buyers and sellers fairly. Gather information from both sides, propose solutions, and escalate to administrators when needed. Be neutral and professional.'
                    ],
                    'max_history_length' => 30,
                ]
            ],
        ];
    }
    
    /**
     * Get template summary for API response
     */
    public static function getSummary(): array {
        return array_map(function($template) {
            return [
                'id' => $template['id'],
                'name' => $template['name'],
                'description' => $template['description'],
                'icon' => $template['icon'],
                'category' => $template['category'] ?? 'utility',
            ];
        }, self::getAll());
    }
}
