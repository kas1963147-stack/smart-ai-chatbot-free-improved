<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Toolkit Loader - Free Version
 * 
 * Loads essential custom toolkits from the toolkits/ folder.
 * 
 * @package Quarksol\AgentFlowAI\Toolkits
 */

// Toolkit base path
define('QAFAI_TOOLKITS_PATH', dirname(__FILE__) . '/');

/**
 * Helper to inject toolkit ID into tool objects
 */
function qafai_inject_toolkit_id(array $tools, string $toolkitId): array
{
    foreach ($tools as $tool) {
        $tool->_toolkit_id = $toolkitId;
    }
    return $tools;
}

/**
 * Get toolkit ID from a tool object
 */
function qafai_get_toolkit_id(object $tool): ?string
{
    if (isset($tool->_toolkit_id)) {
        return $tool->_toolkit_id;
    }
    return null;
}

/**
 * Get all available toolkit tools
 */
function qafai_get_all_toolkit_tools(): array
{
    $tools = [];

    // WordPress Core tools
    if (class_exists('Quarksol\AgentFlowAI\Toolkits\WordPress\WordPressToolkit')) {
        $tools = array_merge($tools, qafai_inject_toolkit_id(
            (new \Quarksol\AgentFlowAI\Toolkits\WordPress\WordPressToolkit())->tools(),
            'wordpress_core'
        ));
    }

    // WordPress Content tools
    if (class_exists('Quarksol\AgentFlowAI\Toolkits\WordPressContent\WordPressContentToolkit')) {
        $tools = array_merge($tools, qafai_inject_toolkit_id(
            (new \Quarksol\AgentFlowAI\Toolkits\WordPressContent\WordPressContentToolkit())->tools(),
            'wordpress_content'
        ));
    }

    // WooCommerce tools
    if (class_exists('Quarksol\AgentFlowAI\Toolkits\WooCommerce\WooCommerceToolkit')) {
        $tools = array_merge($tools, qafai_inject_toolkit_id(
            (new \Quarksol\AgentFlowAI\Toolkits\WooCommerce\WooCommerceToolkit())->tools(),
            'woocommerce'
        ));
    }

    return $tools;
}

/**
 * Get available toolkits status for admin display
 */
function qafai_get_toolkits_status(): array
{
    $status = [
        'wordpress_core' => [
            'name'      => 'WordPress Core Essential',
            'icon'      => '🔧',
            'available' => true,
            'tools'     => ['wp_search'],
            'count'     => 1,
        ],
        'wordpress_content' => [
            'name'      => 'WordPress Content',
            'icon'      => '📝',
            'available' => true,
            'tools'     => ['wp_read_posts'],
            'count'     => 1,
        ],
    ];

    if (class_exists('WooCommerce')) {
        $status['woocommerce'] = [
            'name'      => 'WooCommerce Basic',
            'icon'      => '🛒',
            'available' => true,
            'tools'     => ['woo_search_products', 'woo_order_track'],
            'count'     => 2,
        ];
    } else {
        $status['woocommerce'] = [
            'name'      => 'WooCommerce Basic',
            'icon'      => '🛒',
            'available' => false,
            'tools'     => [],
            'count'     => 0,
            'message'   => 'Install WooCommerce to enable e-commerce tools',
        ];
    }

    return $status;
}

/**
 * Get total tool count
 */
function qafai_get_total_tools_count(): int
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
function qafai_get_toolkit_summary(): array
{
    $woo = class_exists('WooCommerce');

    return [
        'toolkits' => [
            'WordPress Core'    => 1,
            'WordPress Content' => 1,
            'WooCommerce'       => $woo ? 2 : 0,
        ],
        'total'                => $woo ? 4 : 2,
        'woocommerce_available' => $woo,
        'acf_available'        => false,
        'coverage'             => [
            'admin'         => 'Basic',
            'content'       => 'Posts',
            'ecommerce'     => $woo ? 'Basic' : 'N/A',
            'files'         => 'N/A',
            'integrations'  => 'N/A',
            'custom_fields' => 'N/A',
        ],
    ];
}

// Backward-compatibility aliases (deprecated — use the prefixed versions above)
function inject_toolkit_id(array $tools, string $toolkitId): array {
    return qafai_inject_toolkit_id($tools, $toolkitId);
}
function get_toolkit_id(object $tool): ?string {
    return qafai_get_toolkit_id($tool);
}
function get_all_toolkit_tools(): array {
    return qafai_get_all_toolkit_tools();
}
function get_toolkits_status(): array {
    return qafai_get_toolkits_status();
}
function get_total_tools_count(): int {
    return qafai_get_total_tools_count();
}
function get_toolkit_summary(): array {
    return qafai_get_toolkit_summary();
}
