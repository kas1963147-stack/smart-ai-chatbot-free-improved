<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Default Agents Configuration (Free Version - Restricted)
 * 
 * Only 2 inbuilt agents as requested.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

return [
    // 1. Customer Support
    [
        'agent_id'    => 'customer_support',
        'name'        => 'Customer Support',
        'description' => 'Professional support agent for general inquiries.',
        'avatar'      => 'AI',
        'is_active'   => true,
        'is_default'  => true,
        'category'    => 'universal',
        'config' => [
            'enabled_toolkits' => ['WooCommerce', 'WordPress'],
            'welcome_message'  => "Hi! How can I assist you today?",
            'prompt_sections'  => [
                'system' => "You are a professional Customer Support Agent. Provide accurate and helpful support."
            ],
            'internal_mcp_config' => [
                'enabled' => true,
                'mode' => 'whitelist',
                'enabled_tools' => ['get_shop_info', 'search_products', 'get_product_info', 'get_order_status']
            ],
        ],
    ],

    // 2. Shopping Assistant
    [
        'agent_id'    => 'shopping_assistant',
        'name'        => 'Shopping Assistant',
        'description' => 'Helps users find products.',
        'avatar'      => 'SA',
        'is_active'   => true,
        'category'    => 'sales',
        'config' => [
            'enabled_toolkits' => ['WooCommerce'],
            'welcome_message'  => "Hi there! Looking for something specific?",
            'prompt_sections'  => [
                'system' => "You are a friendly shopping assistant. Help users find products."
            ],
            'internal_mcp_config' => [
                'enabled' => true,
                'mode' => 'whitelist',
                'enabled_tools' => ['get_shop_info', 'search_products', 'get_product_info', 'get_product_categories', 'search_posts']
            ],
        ],
    ],
];
