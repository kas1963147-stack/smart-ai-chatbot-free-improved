<?php
declare(strict_types=1);
/**
 * Internal MCP Tool Classifier
 *
 * Classifies internal MCP tools (WordPress/WooCommerce) into security levels:
 *    SAFE     — Read-only, no sensitive data. Always allowed.
 *    GUARDED  — Limited writes that users may need (e.g., create comment). Allowed with checks.
 *    ADMIN    — Destructive/sensitive operations. Admin only.
 *
 * Used by McpSecurityPolicy to filter internal MCP tools in widget contexts.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class InternalToolClassifier
{
    const LEVEL_SAFE    = 'safe';     //  Read-only, public data
    const LEVEL_GUARDED = 'guarded';  //  Limited writes, needs context check
    const LEVEL_ADMIN   = 'admin';    //  Admin-only operations

    /**
     * Get the security level for a given internal MCP tool.
     *
     * @param string $toolName Tool name (e.g., 'wp_get_posts', 'wp_delete_post')
     * @return string One of LEVEL_SAFE, LEVEL_GUARDED, LEVEL_ADMIN
     */
    public static function classify(string $toolName): string
    {
        // Check exact matches first
        $classification = self::getClassificationMap();
        if (isset($classification[$toolName])) {
            return $classification[$toolName];
        }

        // Pattern-based classification for unknown tools
        return self::classifyByPattern($toolName);
    }

    /**
     * Filter an array of Tool objects based on context.
     *
     * @param array $tools Array of NeuronAI Tool objects
     * @param string $context One of McpSecurityPolicy::CONTEXT_*
     * @return array Filtered tools
     */
    public static function filterForContext(array $tools, string $context): array
    {
        // Admin gets everything
        if ($context === McpSecurityPolicy::CONTEXT_ADMIN) {
            return $tools;
        }

        $allowedLevels = [self::LEVEL_SAFE];

        // Authenticated users can also use guarded tools
        if ($context === McpSecurityPolicy::CONTEXT_AUTHENTICATED) {
            $allowedLevels[] = self::LEVEL_GUARDED;
        }

        $filtered = [];
        $blocked = [];

        foreach ($tools as $tool) {
            $toolName = method_exists($tool, 'getName') ? $tool->getName() : '';
            if (empty($toolName)) {
                continue;
            }

            $level = self::classify($toolName);

            if (in_array($level, $allowedLevels, true)) {
                $filtered[] = $tool;
            } else {
                $blocked[] = $toolName;
            }
        }

        if (!empty($blocked)) {
            $blockedCount = count($blocked);
            $totalCount = count($tools);
            $allowedCount = count($filtered);
            error_log("[InternalToolClassifier] Context '{$context}': allowed {$allowedCount}/{$totalCount} internal tools (blocked {$blockedCount} admin-only tools)");
        }

        return $filtered;
    }

    /**
     * Pattern-based classification for tools not explicitly listed.
     * Safe default: unknown tools are classified as ADMIN (deny by default).
     */
    private static function classifyByPattern(string $toolName): string
    {
        // Read operations are safe
        if (preg_match('/^(wp|wc)_(get|list|search|count|ping)/', $toolName)) {
            return self::LEVEL_SAFE;
        }

        // Delete operations are always admin
        if (str_contains($toolName, 'delete') || str_contains($toolName, 'trash')) {
            return self::LEVEL_ADMIN;
        }

        // Create/update operations default to admin
        if (str_contains($toolName, 'create') || str_contains($toolName, 'update')) {
            return self::LEVEL_ADMIN;
        }

        // Activate/deactivate operations are admin
        if (str_contains($toolName, 'activate') || str_contains($toolName, 'deactivate')) {
            return self::LEVEL_ADMIN;
        }

        // Settings operations are admin
        if (str_contains($toolName, 'setting') || str_contains($toolName, 'option')) {
            return self::LEVEL_ADMIN;
        }

        // Default: admin (deny by default for unknown)
        return self::LEVEL_ADMIN;
    }

    /**
     * Comprehensive classification map for all known internal MCP tools.
     *
     * @return array<string, string> Tool name => security level
     */
    private static function getClassificationMap(): array
    {
        return [
            // ============================================================
            // CORE —  All safe (read-only)
            // ============================================================
            'mcp_ping'              => self::LEVEL_SAFE,
            'wp_search_content'     => self::LEVEL_SAFE,
            'wp_get_site_info'      => self::LEVEL_SAFE,

            // ============================================================
            // POSTS — Reads safe, writes admin
            // ============================================================
            'wp_get_posts'          => self::LEVEL_SAFE,
            'wp_get_post'           => self::LEVEL_SAFE,
            'wp_get_post_meta'      => self::LEVEL_SAFE,
            'wp_get_revisions'      => self::LEVEL_SAFE,
            'wp_count_posts'        => self::LEVEL_SAFE,
            'wp_get_post_types'     => self::LEVEL_SAFE,
            'wp_create_post'        => self::LEVEL_ADMIN,
            'wp_update_post'        => self::LEVEL_ADMIN,
            'wp_delete_post'        => self::LEVEL_ADMIN,
            'wp_update_post_meta'   => self::LEVEL_ADMIN,
            'wp_delete_post_meta'   => self::LEVEL_ADMIN,
            'wp_restore_revision'   => self::LEVEL_ADMIN,

            // ============================================================
            // PAGES — Reads safe, writes admin
            // ============================================================
            'wp_get_pages'          => self::LEVEL_SAFE,
            'wp_create_page'        => self::LEVEL_ADMIN,
            'wp_update_page'        => self::LEVEL_ADMIN,
            'wp_delete_page'        => self::LEVEL_ADMIN,

            // ============================================================
            // COMMENTS — Read safe, create guarded, modify admin
            // ============================================================
            'wp_get_comments'       => self::LEVEL_SAFE,
            'wp_create_comment'     => self::LEVEL_GUARDED,  // Visitors may leave comments
            'wp_update_comment'     => self::LEVEL_ADMIN,
            'wp_delete_comment'     => self::LEVEL_ADMIN,

            // ============================================================
            // USERS — Most are admin-only (sensitive PII data)
            // ============================================================
            'wp_get_current_user'   => self::LEVEL_SAFE,  // Only returns own info
            'wp_get_users'          => self::LEVEL_ADMIN,  // Lists all users - sensitive
            'wp_get_user'           => self::LEVEL_ADMIN,  // Gets specific user - sensitive
            'wp_create_user_DISABLED'        => self::LEVEL_ADMIN,
            'wp_update_user'        => self::LEVEL_ADMIN,
            'wp_get_user_meta'      => self::LEVEL_ADMIN,
            'wp_update_user_meta'   => self::LEVEL_ADMIN,

            // ============================================================
            // MEDIA — Reads safe, writes admin
            // ============================================================
            'wp_get_media'          => self::LEVEL_SAFE,
            'wp_get_media_item'     => self::LEVEL_SAFE,
            'wp_upload_media'       => self::LEVEL_ADMIN,
            'wp_update_media'       => self::LEVEL_ADMIN,
            'wp_delete_media'       => self::LEVEL_ADMIN,
            'wp_set_featured_image' => self::LEVEL_ADMIN,

            // ============================================================
            // TAXONOMIES — Reads safe, writes admin
            // ============================================================
            'wp_get_taxonomies'     => self::LEVEL_SAFE,
            'wp_get_terms'          => self::LEVEL_SAFE,
            'wp_get_categories'     => self::LEVEL_SAFE,
            'wp_get_tags'           => self::LEVEL_SAFE,
            'wp_create_term'        => self::LEVEL_ADMIN,
            'wp_update_term'        => self::LEVEL_ADMIN,
            'wp_delete_term'        => self::LEVEL_ADMIN,
            'wp_set_post_terms'     => self::LEVEL_ADMIN,

            // ============================================================
            // OPTIONS/SETTINGS — All admin (sensitive site config)
            // ============================================================
            'wp_get_option'         => self::LEVEL_ADMIN,  // Can expose sensitive data
            'wp_update_option'      => self::LEVEL_ADMIN,
            'wp_delete_option'      => self::LEVEL_ADMIN,
            'wp_get_settings'       => self::LEVEL_ADMIN,
            'wp_update_settings'    => self::LEVEL_ADMIN,

            // ============================================================
            // PLUGINS/THEMES — All admin (system management)
            // ============================================================
            'wp_list_plugins'       => self::LEVEL_ADMIN,
            'wp_activate_plugin'    => self::LEVEL_ADMIN,
            'wp_deactivate_plugin'  => self::LEVEL_ADMIN,
            'wp_get_themes'         => self::LEVEL_ADMIN,

            // ============================================================
            // MENUS — Reads safe, writes admin
            // ============================================================
            'wp_get_menus'          => self::LEVEL_SAFE,
            'wp_get_menu_items'     => self::LEVEL_SAFE,
            'wp_create_menu'        => self::LEVEL_ADMIN,
            'wp_add_menu_item'      => self::LEVEL_ADMIN,

            // ============================================================
            // SYSTEM — All admin (infrastructure)
            // ============================================================
            'wp_get_site_health'    => self::LEVEL_ADMIN,
            'wp_flush_cache'        => self::LEVEL_ADMIN,
            'wp_get_transient'      => self::LEVEL_ADMIN,
            'wp_set_transient'      => self::LEVEL_ADMIN,
            'wp_delete_transient'   => self::LEVEL_ADMIN,
            'wp_get_cron_events'    => self::LEVEL_ADMIN,

            // ============================================================
            // WOOCOMMERCE PRODUCTS — Reads safe (public catalog), writes admin
            // ============================================================
            'wc_get_products'           => self::LEVEL_SAFE,  // Public catalog
            'wc_get_product'            => self::LEVEL_SAFE,  // Public product page
            'wc_get_product_categories' => self::LEVEL_SAFE,  // Public categories
            'wc_get_product_tags'       => self::LEVEL_SAFE,
            'wc_get_variations'         => self::LEVEL_SAFE,
            'wc_get_reviews'            => self::LEVEL_SAFE,  // Public reviews
            'wc_get_low_stock'          => self::LEVEL_ADMIN,  // Business intel
            'wc_create_product'         => self::LEVEL_ADMIN,
            'wc_update_product'         => self::LEVEL_ADMIN,
            'wc_delete_product'         => self::LEVEL_ADMIN,
            'wc_batch_update_products'  => self::LEVEL_ADMIN,
            'wc_create_product_category' => self::LEVEL_ADMIN,
            'wc_create_variation'       => self::LEVEL_ADMIN,
            'wc_update_stock'           => self::LEVEL_ADMIN,

            // ============================================================
            // WOOCOMMERCE ORDERS — All admin (financial/PII data)
            // ============================================================
            'wc_get_orders'         => self::LEVEL_ADMIN,  // Lists all orders
            'wc_get_order'          => self::LEVEL_ADMIN,  // Order details (PII)
            'wc_create_order'       => self::LEVEL_ADMIN,
            'wc_update_order'       => self::LEVEL_ADMIN,
            'wc_delete_order'       => self::LEVEL_ADMIN,
            'wc_get_order_notes'    => self::LEVEL_ADMIN,
            'wc_add_order_note'     => self::LEVEL_ADMIN,
            'wc_get_refunds'        => self::LEVEL_ADMIN,
            'wc_create_refund'      => self::LEVEL_ADMIN,

            // ============================================================
            // WOOCOMMERCE CUSTOMERS — All admin (PII data)
            // ============================================================
            'wc_get_customers'      => self::LEVEL_ADMIN,
            'wc_get_customer'       => self::LEVEL_ADMIN,
            'wc_create_customer'    => self::LEVEL_ADMIN,
            'wc_update_customer'    => self::LEVEL_ADMIN,

            // ============================================================
            // WOOCOMMERCE COUPONS — Read guarded, writes admin
            // ============================================================
            'wc_get_coupons'        => self::LEVEL_GUARDED,  // Visitors may check coupons
            'wc_get_coupon'         => self::LEVEL_GUARDED,
            'wc_create_coupon'      => self::LEVEL_ADMIN,
            'wc_update_coupon'      => self::LEVEL_ADMIN,
            'wc_delete_coupon'      => self::LEVEL_ADMIN,

            // ============================================================
            // WOOCOMMERCE SHIPPING/TAX/REPORTS/SETTINGS — All admin
            // ============================================================
            'wc_get_shipping_zones'     => self::LEVEL_ADMIN,
            'wc_get_shipping_methods'   => self::LEVEL_ADMIN,
            'wc_create_shipping_zone'   => self::LEVEL_ADMIN,
            'wc_update_shipping_zone'   => self::LEVEL_ADMIN,
            'wc_get_tax_classes'        => self::LEVEL_ADMIN,
            'wc_get_tax_rates'          => self::LEVEL_ADMIN,
            'wc_create_tax_rate'        => self::LEVEL_ADMIN,
            'wc_update_tax_rate'        => self::LEVEL_ADMIN,
            'wc_delete_tax_rate'        => self::LEVEL_ADMIN,
            'wc_get_sales_report'       => self::LEVEL_ADMIN,
            'wc_get_top_sellers'        => self::LEVEL_ADMIN,
            'wc_get_orders_totals'      => self::LEVEL_ADMIN,
            'wc_get_settings'           => self::LEVEL_ADMIN,
            'wc_update_setting'         => self::LEVEL_ADMIN,
            'wc_get_payment_gateways'   => self::LEVEL_ADMIN,
            'wc_update_payment_gateway' => self::LEVEL_ADMIN,
            'wc_get_system_status'      => self::LEVEL_ADMIN,
        ];
    }

    /**
     * Get all tools classified at a specific level.
     *
     * @param string $level One of LEVEL_SAFE, LEVEL_GUARDED, LEVEL_ADMIN
     * @return array Tool names at that level
     */
    public static function getToolsByLevel(string $level): array
    {
        return array_keys(
            array_filter(
                self::getClassificationMap(),
                fn($l) => $l === $level
            )
        );
    }

    /**
     * Get classification stats for debugging/display.
     */
    public static function getStats(): array
    {
        $map = self::getClassificationMap();
        $safe = count(array_filter($map, fn($l) => $l === self::LEVEL_SAFE));
        $guarded = count(array_filter($map, fn($l) => $l === self::LEVEL_GUARDED));
        $admin = count(array_filter($map, fn($l) => $l === self::LEVEL_ADMIN));

        return [
            'total' => count($map),
            'safe' => $safe,
            'guarded' => $guarded,
            'admin' => $admin,
        ];
    }
}
