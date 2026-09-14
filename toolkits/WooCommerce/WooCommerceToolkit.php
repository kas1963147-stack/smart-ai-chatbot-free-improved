<?php

/**
 * WooCommerce Toolkit - Free Version
 * 
 * Essential WooCommerce e-commerce toolkit!
 * 
 * @package Toolkits\WooCommerce
 */

namespace Quarksol\AgentFlowAI\Toolkits\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceToolkit
{
    /**
     * Get all WooCommerce tools
     * 
     * @return array Array of Tool instances
     */
    public static function tools(): array
    {
        return [
            // === PRODUCT TOOLS ===
            new ProductSearchTool(),      // Search products
            
            // === ORDER TOOLS ===
            new OrderTrackTool(),         // Track orders
        ];
    }
    
    /**
     * Get tool categories
     */
    public static function categories(): array
    {
        return [
            'products' => [
                'name' => 'Products',
                'icon' => '📦',
                'tools' => ['woo_search_products']
            ],
            'orders' => [
                'name' => 'Orders',
                'icon' => '📋',
                'tools' => ['woo_order_track']
            ]
        ];
    }
    
    /**
     * Get toolkit info
     */
    public static function info(): array
    {
        return [
            'name' => 'WooCommerce Essential Toolkit',
            'version' => '2.0.0',
            'tool_count' => 2,
            'description' => 'Essential WooCommerce API tools for free AI assistants',
            'capabilities' => [
                'Product Management' => 'Search products',
                'Orders' => 'Track orders by ID'
            ]
        ];
    }
}
