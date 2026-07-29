<?php

/**
 * Order Track Tool
 * 
 * Track orders by ID or email.
 * 
 * @package Toolkits\WooCommerce
 */

namespace Toolkits\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class OrderTrackTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'woo_order_track',
            description: 'Track order status by order ID or customer email'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'order_id',
                type: PropertyType::STRING,
                description: 'Order ID/number to track',
                required: false
            ),
            new ToolProperty(
                name: 'email',
                type: PropertyType::STRING,
                description: 'Customer email to look up orders',
                required: false
            ),
        ];
    }
    
    public function __invoke(?string $order_id = null, ?string $email = null): string
    {
        if (!$order_id && !$email) {
            return \Quarksol\SmartChatbot\Types\ToolResponse::error('Provide order_id or email');
        }
        
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProductService')) {
             return \Quarksol\SmartChatbot\Types\ToolResponse::error('ProductService not available');
        }
        
        $service = new \Quarksol\SmartChatbot\Services\ProductService();
        
        if ($order_id) {
            $result = $service->getOrderStatus($order_id, $email);
            if (isset($result['error'])) {
                return \Quarksol\SmartChatbot\Types\ToolResponse::error($result['error']);
            }
            return \Quarksol\SmartChatbot\Types\ToolResponse::success(['order' => $result]);
        }
        
        return \Quarksol\SmartChatbot\Types\ToolResponse::error('Order search by email disabled for security. Please provide order ID.');
    }
}
