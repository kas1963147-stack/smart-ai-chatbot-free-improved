<?php

/**
 * MCP Tool Registry - Comprehensive WordPress/WooCommerce Tools
 * Core tools + auto-detected plugin extensions.
 */

namespace Quarksol\SmartChatbot\MCP;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\MCP\Extensions\McpExtensionRegistry;

class McpToolRegistry
{
    /**
     * Get all tools including active extensions.
     */
    public static function getTools(): array
    {
        return array_merge(
            self::getCoreTools(),
            self::getPostTools(),
            self::getPageTools(),
            self::getCommentTools(),
            self::getUserTools(),
            self::getMediaTools(),
            self::getTaxonomyTools(),
            self::getOptionTools(),
            self::getPluginTools(),
            self::getMenuTools(),
            self::getSystemTools(),
            self::getWooProductTools(),
            self::getWooOrderTools(),
            self::getWooCustomerTools(),
            self::getWooCouponTools(),
            self::getWooShippingTools(),
            self::getWooTaxTools(),
            self::getWooReportTools(),
            self::getWooSettingsTools(),
            self::getAppointmentTools(),
            self::getLeadTools(),
            self::getFormBuilderTools(),
            self::getExtensionTools()
        );
    }

    /**
     * Get tools from active plugin extensions.
     */
    public static function getExtensionTools(): array
    {
        if (!class_exists(McpExtensionRegistry::class)) {
            return [];
        }
        return McpExtensionRegistry::getActiveTools();
    }

    /**
     * Get categories including active extensions.
     */
    public static function getToolsByCategory(): array
    {
        $categories = [
            'core' => ['label' => 'Core', 'icon' => 'Zap', 'tools' => self::getCoreTools()],
            'posts' => ['label' => 'Posts', 'icon' => 'FileText', 'tools' => self::getPostTools()],
            'pages' => ['label' => 'Pages', 'icon' => 'File', 'tools' => self::getPageTools()],
            'comments' => ['label' => 'Comments', 'icon' => 'MessageSquare', 'tools' => self::getCommentTools()],
            'users' => ['label' => 'Users', 'icon' => 'Users', 'tools' => self::getUserTools()],
            'media' => ['label' => 'Media', 'icon' => 'Image', 'tools' => self::getMediaTools()],
            'taxonomies' => ['label' => 'Taxonomies', 'icon' => 'Tag', 'tools' => self::getTaxonomyTools()],
            'options' => ['label' => 'Options', 'icon' => 'Settings', 'tools' => self::getOptionTools()],
            'plugins' => ['label' => 'Plugins', 'icon' => 'Package', 'tools' => self::getPluginTools()],
            'menus' => ['label' => 'Menus', 'icon' => 'Menu', 'tools' => self::getMenuTools()],
            'system' => ['label' => 'System', 'icon' => 'Server', 'tools' => self::getSystemTools()],
            'wc_products' => ['label' => 'WC Products', 'icon' => 'ShoppingBag', 'tools' => self::getWooProductTools()],
            'wc_orders' => ['label' => 'WC Orders', 'icon' => 'ShoppingCart', 'tools' => self::getWooOrderTools()],
            'wc_customers' => ['label' => 'WC Customers', 'icon' => 'UserCheck', 'tools' => self::getWooCustomerTools()],
            'wc_coupons' => ['label' => 'WC Coupons', 'icon' => 'Percent', 'tools' => self::getWooCouponTools()],
            'wc_shipping' => ['label' => 'WC Shipping', 'icon' => 'Truck', 'tools' => self::getWooShippingTools()],
            'wc_tax' => ['label' => 'WC Tax', 'icon' => 'Receipt', 'tools' => self::getWooTaxTools()],
            'wc_reports' => ['label' => 'WC Reports', 'icon' => 'BarChart2', 'tools' => self::getWooReportTools()],
            'wc_settings' => ['label' => 'WC Settings', 'icon' => 'Sliders', 'tools' => self::getWooSettingsTools()],
            'appointments' => ['label' => 'Appointments', 'icon' => 'Calendar', 'tools' => self::getAppointmentTools()],
            'leads' => ['label' => 'Leads', 'icon' => 'Users', 'tools' => self::getLeadTools()],
            'forms' => ['label' => 'Form Builder', 'icon' => 'Clipboard', 'tools' => self::getFormBuilderTools()],
        ];

        // Merge extension categories (auto-detected plugins)
        if (class_exists(McpExtensionRegistry::class)) {
            $extensionCategories = McpExtensionRegistry::getActiveCategories();
            $categories = array_merge($categories, $extensionCategories);
        }

        return $categories;
    }

    public static function getDefaultEnabledTools(): array
    {
        return ['mcp_ping', 'wp_search_content', 'wp_get_site_info', 'request_human_support', 'wp_get_posts', 'wp_get_post', 'wp_get_post_meta', 'wp_get_pages', 'wp_get_comments', 'wp_get_users', 'wp_get_media', 'wp_get_terms', 'wp_get_taxonomies', 'wp_get_option', 'wp_list_plugins', 'wp_get_themes', 'wp_get_menus', 'wp_get_menu_items', 'wc_get_products', 'wc_get_product', 'wc_get_product_categories', 'wc_get_orders', 'wc_get_order', 'wc_get_customers', 'wc_get_coupons', 'wc_get_reviews', 'appointment_booker', 'availability_checker', 'lead_collector', 'form_filler'];
    }

    private static function getCoreTools(): array
    {
        return [
            self::tool('mcp_ping', 'Ping', 'Connectivity check - returns site name and time', [], true),
            self::tool('wp_search_content', 'Search Content', 'Search posts/pages by keyword', [
                'query' => ['type' => 'string', 'required' => true],
                'post_types' => ['type' => 'array', 'default' => ['post', 'page']],
                'limit' => ['type' => 'integer', 'default' => 20],
            ], true),
            self::tool('wp_get_site_info', 'Site Info', 'Get site name, URL, language, etc.', [], true),
            self::tool('request_human_support', 'Request Human Support', 'CRITICAL RULE: NEVER call this tool immediately. You MUST FIRST reply with a normal message asking the user: "Would you like to continue here on the website, or via WhatsApp?" and WAIT for their reply. ONLY call this tool AFTER the user replies to that specific question. If they chose Website, pass "web" as whatsapp_number. If they chose WhatsApp, ask for their number first, then pass it. DO NOT guess or assume.', [
                'whatsapp_number' => ['type' => 'string', 'required' => true],
                'user_explicit_reply' => ['type' => 'string', 'description' => 'You MUST pass the exact text the user replied with when you asked them which platform they prefer.', 'required' => true]
            ], true),
        ];
    }

    private static function getPostTools(): array
    {
        return [
            self::tool('wp_get_posts', 'Get Posts', 'List posts with filters', [
                'post_type' => ['type' => 'string', 'default' => 'post'],
                'post_status' => ['type' => 'string', 'default' => 'publish'],
                'limit' => ['type' => 'integer', 'default' => 10],
                'offset' => ['type' => 'integer', 'default' => 0],
                'search' => ['type' => 'string'],
            ], true),
            self::tool('wp_get_post', 'Get Post', 'Get single post by ID', ['ID' => ['type' => 'integer', 'required' => true]], true),
            self::tool('wp_get_post_meta', 'Get Post Meta', 'Get meta for a post', [
                'post_id' => ['type' => 'integer', 'required' => true],
                'key' => ['type' => 'string'],
            ], true),
        ];
    }

    private static function getPageTools(): array
    {
        return [
            self::tool('wp_get_pages', 'Get Pages', 'List pages', [
                'limit' => ['type' => 'integer', 'default' => 10],
                'search' => ['type' => 'string'],
            ], true),
            self::tool('wp_get_page', 'Get Page', 'Get single page', ['ID' => ['type' => 'integer', 'required' => true]], true),
        ];
    }

    private static function getCommentTools(): array
    {
        return [
            self::tool('wp_get_comments', 'Get Comments', 'List comments', [
                'post_id' => ['type' => 'integer'],
                'status' => ['type' => 'string', 'default' => 'approve'],
                'limit' => ['type' => 'integer', 'default' => 20],
            ], true),
            self::tool('wp_get_comment', 'Get Comment', 'Get single comment', ['comment_id' => ['type' => 'integer', 'required' => true]], true),
        ];
    }

    private static function getUserTools(): array
    {
        return [
            self::tool('wp_get_users', 'Get Users', 'List users', [
                'role' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 10],
                'search' => ['type' => 'string'],
            ], true),
            self::tool('wp_get_user', 'Get User', 'Get single user', ['user_id' => ['type' => 'integer', 'required' => true]], true),
            self::tool('wp_get_user_meta', 'Get User Meta', 'Get meta for user', [
                'user_id' => ['type' => 'integer', 'required' => true],
                'key' => ['type' => 'string'],
            ], true),
        ];
    }

    private static function getMediaTools(): array
    {
        return [
            self::tool('wp_get_media', 'Get Media', 'List media', [
                'mime_type' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 10],
            ], true),
            self::tool('wp_get_media_item', 'Get Media Item', 'Get single media', ['attachment_id' => ['type' => 'integer', 'required' => true]], true),
        ];
    }

    private static function getTaxonomyTools(): array
    {
        return [
            self::tool('wp_get_taxonomies', 'Get Taxonomies', 'List taxonomies', ['post_type' => ['type' => 'string']], true),
            self::tool('wp_get_terms', 'Get Terms', 'List terms', [
                'taxonomy' => ['type' => 'string', 'required' => true],
                'hide_empty' => ['type' => 'boolean', 'default' => false],
                'limit' => ['type' => 'integer', 'default' => 50],
            ], true),
            self::tool('wp_get_categories', 'Get Categories', 'List categories', ['limit' => ['type' => 'integer', 'default' => 50]], true),
            self::tool('wp_get_tags', 'Get Tags', 'List tags', ['limit' => ['type' => 'integer', 'default' => 50]], true),
        ];
    }

    private static function getOptionTools(): array
    {
        return [
            self::tool('wp_get_option', 'Get Option', 'Get WP option', ['key' => ['type' => 'string', 'required' => true]], true),
            self::tool('wp_get_settings', 'Get Settings', 'Get WordPress settings', [], true),
        ];
    }

    private static function getPluginTools(): array
    {
        return [
            self::tool('wp_list_plugins', 'List Plugins', 'List installed plugins', ['status' => ['type' => 'string']], true),
            self::tool('wp_get_themes', 'Get Themes', 'List themes', [], true),
        ];
    }

    private static function getMenuTools(): array
    {
        return [
            self::tool('wp_get_menus', 'Get Menus', 'List nav menus', [], true),
            self::tool('wp_get_menu_items', 'Get Menu Items', 'Get menu items', ['menu_id' => ['type' => 'integer', 'required' => true]], true),
        ];
    }

    private static function getSystemTools(): array
    {
        return [
            self::tool('wp_get_site_health', 'Site Health', 'Get site health info', [], true),
            self::tool('wp_get_transient', 'Get Transient', 'Get transient value', ['key' => ['type' => 'string', 'required' => true]], true),
            self::tool('wp_get_cron_events', 'Get Cron Events', 'List scheduled cron events', [], true),
        ];
    }

    private static function getWooProductTools(): array
    {
        return [
            self::tool('wc_get_products', 'Get Products', 'List products', [
                'status' => ['type' => 'string', 'default' => 'publish'],
                'limit' => ['type' => 'integer', 'default' => 10],
                'search' => ['type' => 'string'],
                'category' => ['type' => 'integer'],
            ], true),
            self::tool('wc_get_product', 'Get Product', 'Get single product', ['product_id' => ['type' => 'integer', 'required' => true]], true),
            self::tool('wc_get_product_categories', 'Get Categories', 'List product categories', [
                'hide_empty' => ['type' => 'boolean', 'default' => false],
            ], true),
            self::tool('wc_get_product_tags', 'Get Tags', 'List product tags', [], true),
            self::tool('wc_get_variations', 'Get Variations', 'Get product variations', ['product_id' => ['type' => 'integer', 'required' => true]], true),
            self::tool('wc_get_reviews', 'Get Reviews', 'List product reviews', [
                'product_id' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 10],
            ], true),
            self::tool('wc_get_low_stock', 'Get Low Stock', 'Get low stock products', ['threshold' => ['type' => 'integer', 'default' => 5]], true),
        ];
    }

    private static function getWooOrderTools(): array
    {
        return [
            self::tool('wc_get_orders', 'Get Orders', 'List orders', [
                'status' => ['type' => 'string'],
                'customer_id' => ['type' => 'integer'],
                'limit' => ['type' => 'integer', 'default' => 10],
            ], true),
            self::tool('wc_get_order', 'Get Order', 'Get single order', ['order_id' => ['type' => 'integer', 'required' => true]], true),
            self::tool('wc_get_order_notes', 'Get Order Notes', 'Get notes for order', ['order_id' => ['type' => 'integer', 'required' => true]], true),
            self::tool('wc_get_refunds', 'Get Refunds', 'List refunds', ['order_id' => ['type' => 'integer']], true),
        ];
    }

    private static function getWooCustomerTools(): array
    {
        return [
            self::tool('wc_get_customers', 'Get Customers', 'List customers', [
                'role' => ['type' => 'string', 'default' => 'all'],
                'limit' => ['type' => 'integer', 'default' => 10],
                'search' => ['type' => 'string'],
            ], true),
            self::tool('wc_get_customer', 'Get Customer', 'Get single customer', ['customer_id' => ['type' => 'integer', 'required' => true]], true),
        ];
    }

    private static function getWooCouponTools(): array
    {
        return [
            self::tool('wc_get_coupons', 'Get Coupons', 'List coupons', [
                'limit' => ['type' => 'integer', 'default' => 10],
                'search' => ['type' => 'string'],
            ], true),
            self::tool('wc_get_coupon', 'Get Coupon', 'Get single coupon', ['coupon_id' => ['type' => 'integer', 'required' => true]], true),
        ];
    }

    private static function getWooShippingTools(): array
    {
        return [
            self::tool('wc_get_shipping_zones', 'Get Zones', 'List shipping zones', [], true),
            self::tool('wc_get_shipping_methods', 'Get Methods', 'Get zone methods', ['zone_id' => ['type' => 'integer', 'required' => true]], true),
        ];
    }

    private static function getWooTaxTools(): array
    {
        return [
            self::tool('wc_get_tax_classes', 'Get Tax Classes', 'List tax classes', [], true),
            self::tool('wc_get_tax_rates', 'Get Tax Rates', 'List tax rates', ['class' => ['type' => 'string']], true),
        ];
    }

    private static function getWooReportTools(): array
    {
        return [
            self::tool('wc_get_sales_report', 'Sales Report', 'Get sales data', [
                'date_min' => ['type' => 'string'],
                'date_max' => ['type' => 'string'],
            ], true),
            self::tool('wc_get_top_sellers', 'Top Sellers', 'Get top selling products', [
                'limit' => ['type' => 'integer', 'default' => 10],
            ], true),
            self::tool('wc_get_orders_totals', 'Orders Totals', 'Get orders totals by status', [], true),
        ];
    }

    private static function getWooSettingsTools(): array
    {
        return [
            self::tool('wc_get_settings', 'Get Settings', 'Get WC settings group', ['group' => ['type' => 'string', 'default' => 'general']], true),
            self::tool('wc_get_payment_gateways', 'Get Gateways', 'List payment gateways', [], true),
            self::tool('wc_get_system_status', 'System Status', 'Get WC system status', [], true),
        ];
    }

    // =========================================================================
    // APPOINTMENTS & LEADS
    // =========================================================================

    private static function getAppointmentTools(): array
    {
        return [
            self::tool('appointment_booker', 'Appointment Booker', 'Book, cancel, reschedule, and retrieve appointments. Use this tool when users explicitly want to schedule a specific date/time for a service. CRITICAL: NEVER use this tool just to capture an email or if the user simply wants to "talk to someone" or "get a quote" without a specific time.', [
                'action' => ['type' => 'string', 'required' => true],
                'appointment_id' => ['type' => 'integer'],
                'customer_name' => ['type' => 'string'],
                'customer_email' => ['type' => 'string'],
                'customer_phone' => ['type' => 'string'],
                'appointment_type' => ['type' => 'string'],
                'date' => ['type' => 'string'],
                'time' => ['type' => 'string'],
                'duration_minutes' => ['type' => 'integer'],
                'notes' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'date_from' => ['type' => 'string'],
                'date_to' => ['type' => 'string'],
            ], false),
            self::tool('availability_checker', 'Availability Checker', 'Check appointment availability. Actions: check_slot, get_available_slots, get_appointment_types.', [
                'action' => ['type' => 'string', 'required' => true],
                'date' => ['type' => 'string'],
                'time' => ['type' => 'string'],
                'appointment_type' => ['type' => 'string'],
                'duration_minutes' => ['type' => 'integer'],
            ], true),
        ];
    }

    private static function getLeadTools(): array
    {
        return [
            self::tool('lead_collector', 'Lead Collector', 'Capture, list, or view leads and requests from customers. Use this to save user contact information and their requests so the admin can follow up later. CRITICAL: NEVER use this tool to book an appointment. NEVER use this tool if the user asks for Live Chat, Handoff, WhatsApp, or Escalate (use request_human_support instead!).', [
                'action' => ['type' => 'string', 'required' => true],
                'lead_id' => ['type' => 'integer'],
                'customer_name' => ['type' => 'string'],
                'customer_email' => ['type' => 'string'],
                'customer_phone' => ['type' => 'string'],
                'company' => ['type' => 'string'],
                'request_summary' => ['type' => 'string', 'required' => true],
                'lead_type' => ['type' => 'string'],
                'priority' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ], false),
        ];
    }

    private static function getFormBuilderTools(): array
    {
        return [
            self::tool('form_filler', 'Form Filler', 'Interact with admin-created forms to collect user data conversationally. Actions: get_form (get form fields), submit_field (save a single field answer), get_progress (check filled vs remaining), complete_submission (finalize the form).', [
                'action' => ['type' => 'string', 'required' => true],
                'form_id' => ['type' => 'integer'],
                'field_id' => ['type' => 'string'],
                'value' => ['type' => 'string'],
                'submission_id' => ['type' => 'integer'],
            ], false),
        ];
    }

    private static function tool(string $name, string $title, string $desc, array $params, bool $default): array
    {
        return [
            'name' => $name,
            'title' => $title,
            'description' => $desc,
            'inputSchema' => ['type' => 'object', 'properties' => $params, 'required' => array_keys(array_filter($params, fn($p) => $p['required'] ?? false))],
            'default' => $default,
            'annotations' => ['title' => $title, 'readOnlyHint' => $default, 'destructiveHint' => str_contains($name, 'delete'), 'idempotentHint' => str_contains($name, 'get') || str_contains($name, 'list')],
        ];
    }

    // =========================================================================
    // PROFILES CONSTANT
    // =========================================================================

    public const PROFILES = [
        'read_only' => [
            'name' => 'Read Only',
            'description' => 'Safe read-only operations',
            'icon' => '',
            'pattern' => 'get|list|search|count',
        ],
        'content_management' => [
            'name' => 'Content Management',
            'description' => 'Posts, pages, media, comments',
            'icon' => '',
            'categories' => ['posts', 'pages', 'comments', 'media', 'taxonomies'],
        ],
        'full_access' => [
            'name' => 'Full Access',
            'description' => 'All available tools',
            'icon' => '',
            'all' => true,
        ],
        'woocommerce' => [
            'name' => 'WooCommerce',
            'description' => 'Products, orders, customers',
            'icon' => '',
            'categories' => ['wc_products', 'wc_orders', 'wc_customers', 'wc_coupons', 'wc_shipping', 'wc_tax', 'wc_reports', 'wc_settings'],
        ],
        'none' => [
            'name' => 'None',
            'description' => 'Disable all tools',
            'icon' => '',
            'none' => true,
        ],
    ];

    private const OPTION_KEY = 'swc_mcp_tool_state';

    // =========================================================================
    // STATS AND STATE MANAGEMENT
    // =========================================================================

    public static function getStats(): array
    {
        $tools = self::getTools();
        $state = self::getEnabledState();
        $enabled = 0;
        $total = count($tools);

        foreach ($tools as $tool) {
            $name = $tool['name'];
            $isEnabled = $state[$name] ?? $tool['default'] ?? true;
            if ($isEnabled)
                $enabled++;
        }

        return [
            'total' => $total,
            'enabled' => $enabled,
            'disabled' => $total - $enabled,
        ];
    }

    public static function getEnabledState(): array
    {
        return get_option(self::OPTION_KEY, []);
    }

    public static function saveEnabledState(array $state): bool
    {
        return update_option(self::OPTION_KEY, $state);
    }

    public static function isToolEnabled(string $toolName): bool
    {
        $state = self::getEnabledState();
        if (isset($state[$toolName])) {
            return (bool) $state[$toolName];
        }
        // Check default
        foreach (self::getTools() as $tool) {
            if ($tool['name'] === $toolName) {
                return $tool['default'] ?? true;
            }
        }
        return true;
    }

    public static function getEnabledTools(): array
    {
        $enabled = [];
        foreach (self::getTools() as $tool) {
            if (self::isToolEnabled($tool['name'])) {
                $enabled[] = $tool;
            }
        }
        return $enabled;
    }

    public static function applyProfile(string $profileId): bool
    {
        $profile = self::PROFILES[$profileId] ?? null;
        if (!$profile)
            return false;

        $state = [];
        $tools = self::getTools();
        $categories = self::getToolsByCategory();

        if ($profile['none'] ?? false) {
            // Disable all
            foreach ($tools as $tool) {
                $state[$tool['name']] = false;
            }
        } elseif ($profile['all'] ?? false) {
            // Enable all
            foreach ($tools as $tool) {
                $state[$tool['name']] = true;
            }
        } elseif (!empty($profile['pattern'])) {
            // Pattern-based (read_only)
            $pattern = '/' . $profile['pattern'] . '/i';
            foreach ($tools as $tool) {
                $state[$tool['name']] = (bool) preg_match($pattern, $tool['name']);
            }
        } elseif (!empty($profile['categories'])) {
            // Category-based
            $allowedCategories = $profile['categories'];
            foreach ($categories as $catId => $cat) {
                $inProfile = in_array($catId, $allowedCategories);
                foreach ($cat['tools'] as $tool) {
                    $state[$tool['name']] = $inProfile;
                }
            }
        }

        return self::saveEnabledState($state);
    }
}
