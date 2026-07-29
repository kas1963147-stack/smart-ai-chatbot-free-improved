<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Toolkit Loader - Free Version
 * 
 * Loads essential custom toolkits from the toolkits/ folder.
 * 
 * @package Toolkits
 */

// Toolkit base path
define('TOOLKITS_PATH', dirname(__FILE__) . '/');

/**
 * Helper to inject toolkit ID into tool objects
 */
function inject_toolkit_id(array $tools, string $toolkitId): array
{
    foreach ($tools as $tool) {
        $tool->_toolkit_id = $toolkitId;
    }
    return $tools;
}

/**
 * Get toolkit ID from a tool object
 */
function get_toolkit_id(object $tool): ?string
{
    if (isset($tool->_toolkit_id)) {
        return $tool->_toolkit_id;
    }
    return null;
}

/**
 * Get all available toolkit tools
 */
function get_all_toolkit_tools(): array
{
    $tools = [];

    // WordPress Core tools
    if (class_exists('Toolkits\WordPress\WordPressToolkit')) {
        $tools = array_merge($tools, inject_toolkit_id(
            (new \Toolkits\WordPress\WordPressToolkit())->tools(),
            'wordpress_core'
        ));
    }

    // WordPress Content tools
    if (class_exists('Toolkits\WordPressContent\WordPressContentToolkit')) {
        $tools = array_merge($tools, inject_toolkit_id(
            (new \Toolkits\WordPressContent\WordPressContentToolkit())->tools(),
            'wordpress_content'
        ));
    }

    // WooCommerce tools
    if (class_exists('Toolkits\WooCommerce\WooCommerceToolkit')) {
        $tools = array_merge($tools, inject_toolkit_id(
            (new \Toolkits\WooCommerce\WooCommerceToolkit())->tools(),
            'woocommerce'
        ));
    }

    return $tools;
}

/**
 * Get available toolkits status for admin display
 */
function get_toolkits_status(): array
{
    $status = [
        'wordpress_core' => [
            'name' => 'WordPress Core Essential',
            'icon' => '🔧',
            'available' => true,
            'tools' => [
                'wp_search'
            ],
            'count' => 1
        ],
        'wordpress_content' => [
            'name' => 'WordPress Content',
            'icon' => '📝',
            'available' => true,
            'tools' => [
                'wp_read_posts'
            ],
            'count' => 1
        ]
    ];

    if (class_exists('WooCommerce')) {
        $status['woocommerce'] = [
            'name' => 'WooCommerce Basic',
            'icon' => '🛒',
            'available' => true,
            'tools' => [
                'woo_search_products',
                'woo_order_track'
            ],
            'count' => 2
        ];
    } else {
        $status['woocommerce'] = [
            'name' => 'WooCommerce Basic',
            'icon' => '🛒',
            'available' => false,
            'tools' => [],
            'count' => 0,
            'message' => 'Install WooCommerce to enable e-commerce tools'
        ];
    }

    return $status;
}

/**
 * Get total tool count
 */
function get_total_tools_count(): int
{
    $count = 2; // Core + Content
    if (class_exists('WooCommerce')) {
        $count += 2;
    }
    return $count;
}

/**
 * Get toolkit summary
 */
function get_toolkit_summary(): array
{
    $woo = class_exists('WooCommerce');

    return [
        'toolkits' => [
            'WordPress Core' => 1,
            'WordPress Content' => 1,
            'WooCommerce' => $woo ? 2 : 0
        ],
        'total' => $woo ? 4 : 2,
        'woocommerce_available' => $woo,
        'acf_available' => false,
        'coverage' => [
            'admin' => 'Basic',
            'content' => 'Posts',
            'ecommerce' => $woo ? 'Basic' : 'N/A',
            'files' => 'N/A',
            'integrations' => 'N/A',
            'custom_fields' => 'N/A'
        ]
    ];
}
