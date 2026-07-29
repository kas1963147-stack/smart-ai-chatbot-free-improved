<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Default Chat Widget Configurations
 * 
 * 3 pre-built chat widgets for common use cases.
 * These are created on plugin activation if no widgets exist.
 * 
 * Each widget references a matching default agent via agent_id.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

return [
    [
        'name' => 'general_assistant_widget',
        'display_name' => 'General Assistant',
        'description' => 'Versatile AI assistant for general questions and site navigation.',
        'agent_id' => 'agent:customer_support',
        'is_active' => true,
        'appearance' => [
            'template' => 'modern-minimal',
            'position' => 'right',
            'color_primary' => '#6366f1',
            'color_primary_hover' => '#4f46e5',
            'color_bg_main' => '#ffffff',
            'color_bg_light' => '#f8fafc',
            'color_text_primary' => '#1e293b',
            'color_text_secondary' => '#64748b',
            'color_border' => '#e2e8f0',
            'font_family' => 'system',
            'font_size_base' => 14,
            'border_radius' => 16,
            'toggle_size' => 60,
            'toggle_shape' => 'circle',
            'toggle_icon' => 'chat',
            'entrance_animation' => 'slide-up',
            'greeting_bubble_enabled' => true,
            'greeting_bubble_text' => 'How can I help you?',
            'greeting_bubble_delay' => 5,
        ],
        'behavior' => [
            'greeting_message' => "Hello! I'm your General Assistant. Ask me anything and I'll do my best to help!",
            'placeholder_text' => 'Type your question here...',
        ],
        'triggers' => [
            'auto_open_enabled' => false,
            'exit_intent_enabled' => false,
            'time_on_page_enabled' => true,
            'time_on_page_seconds' => 30,
        ],
        'display' => [
            'include_urls' => [],
            'exclude_urls' => [],
            'devices' => ['desktop', 'tablet', 'mobile'],
        ],
        'engagement' => [
            'quick_replies_enabled' => true,
            'quick_replies' => [
                ['text' => 'What can you help with?', 'action' => 'message'],
                ['text' => 'Tell me about this site', 'action' => 'message'],
            ],
            'typing_indicator' => true,
            'session_persistence' => true,
        ],
    ],

    [
        'name' => 'support_bot_widget',
        'display_name' => 'Support Bot',
        'description' => 'Dedicated support assistant for answering FAQs and troubleshooting.',
        'agent_id' => 'agent:customer_support',
        'is_active' => false,
        'appearance' => [
            'template' => 'corporate-clean',
            'position' => 'right',
            'color_primary' => '#0f766e',
            'color_primary_hover' => '#0d9488',
            'color_bg_main' => '#ffffff',
            'color_bg_light' => '#f0fdfa',
            'color_text_primary' => '#134e4a',
            'color_text_secondary' => '#5eead4',
            'color_border' => '#99f6e4',
            'font_family' => 'system',
            'font_size_base' => 14,
            'border_radius' => 8,
            'toggle_size' => 56,
            'toggle_shape' => 'rounded_square',
            'toggle_icon' => 'support',
            'entrance_animation' => 'fade',
            'greeting_bubble_enabled' => true,
            'greeting_bubble_text' => 'Need support?',
            'greeting_bubble_delay' => 8,
        ],
        'behavior' => [
            'greeting_message' => "Hi there! I'm your Support Bot. How can I help you today?",
            'placeholder_text' => 'Describe your issue...',
        ],
        'triggers' => [
            'auto_open_enabled' => false,
            'exit_intent_enabled' => false,
        ],
        'display' => [
            'include_urls' => [],
            'exclude_urls' => ['/checkout/*'],
            'devices' => ['desktop', 'tablet', 'mobile'],
        ],
        'engagement' => [
            'quick_replies_enabled' => true,
            'quick_replies' => [
                ['text' => 'FAQ', 'action' => 'message'],
                ['text' => 'Technical Support', 'action' => 'message'],
                ['text' => 'Contact Us', 'action' => 'message'],
            ],
            'typing_indicator' => true,
            'session_persistence' => true,
        ],
    ],

    [
        'name' => 'shopping_assistant_widget',
        'display_name' => 'Shopping Assistant',
        'description' => 'Helps visitors find products, compare options, and complete purchases.',
        'agent_id' => 'agent:shopping_assistant',
        'is_active' => false,
        'appearance' => [
            'template' => 'modern-minimal',
            'position' => 'right',
            'color_primary' => '#7c3aed',
            'color_primary_hover' => '#6d28d9',
            'color_bg_main' => '#ffffff',
            'color_bg_light' => '#faf5ff',
            'color_text_primary' => '#1e293b',
            'color_text_secondary' => '#64748b',
            'color_border' => '#e9d5ff',
            'font_family' => 'system',
            'font_size_base' => 14,
            'border_radius' => 16,
            'toggle_size' => 60,
            'toggle_shape' => 'circle',
            'toggle_icon' => 'cart',
            'entrance_animation' => 'slide-up',
            'greeting_bubble_enabled' => true,
            'greeting_bubble_text' => 'Looking for something?',
            'greeting_bubble_delay' => 10,
        ],
        'behavior' => [
            'greeting_message' => "Hi! I'm your Shopping Assistant. I can help you find the perfect product!",
            'placeholder_text' => 'What are you looking for?',
        ],
        'triggers' => [
            'auto_open_enabled' => false,
            'exit_intent_enabled' => false,
            'time_on_page_enabled' => true,
            'time_on_page_seconds' => 45,
        ],
        'display' => [
            'include_urls' => [],
            'exclude_urls' => ['/checkout/*', '/cart/*'],
            'devices' => ['desktop', 'tablet', 'mobile'],
        ],
        'engagement' => [
            'quick_replies_enabled' => true,
            'quick_replies' => [
                ['text' => 'Browse Products', 'action' => 'message'],
                ['text' => 'Best Sellers', 'action' => 'message'],
                ['text' => 'Track My Order', 'action' => 'message'],
            ],
            'typing_indicator' => true,
            'session_persistence' => true,
        ],
    ],
];
