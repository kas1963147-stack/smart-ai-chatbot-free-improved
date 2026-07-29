<?php
/**
 * Order Tracking Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Orders {
    
    /**
     * Get order by ID
     */
    public static function get_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return null;
        }
        
        return self::format_order($order);
    }
    
    /**
     * Get order by ID and email (for guest verification)
     */
    public static function get_order_by_email($order_id, $email) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array('success' => false, 'message' => 'Order not found');
        }
        
        $order_email = $order->get_billing_email();
        
        if (strtolower($order_email) !== strtolower($email)) {
            return array('success' => false, 'message' => 'Email does not match order');
        }
        
        return array('success' => true, 'order' => self::format_order($order));
    }
    
    /**
     * Get orders for logged in user
     */
    public static function get_user_orders($user_id, $limit = 5) {
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $result = array();
        foreach ($orders as $order) {
            $result[] = self::format_order($order);
        }
        
        return $result;
    }
    
    /**
     * Format order data for chat
     */
    private static function format_order($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $order->get_formatted_line_subtotal($item)
            );
        }
        
        $status_labels = array(
            'pending' => '⏳ Pending Payment',
            'processing' => ' Processing',
            'on-hold' => '⏸️ On Hold',
            'completed' => ' Completed',
            'cancelled' => ' Cancelled',
            'refunded' => '↩️ Refunded',
            'failed' => ' Failed'
        );
        
        $status = $order->get_status();
        
        return array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $status,
            'status_label' => $status_labels[$status] ?? ucfirst($status),
            'date' => $order->get_date_created()->date('M j, Y'),
            'total' => $order->get_formatted_order_total(),
            'items' => $items,
            'items_count' => count($items),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'tracking' => self::get_tracking_info($order)
        );
    }
    
    /**
     * Get tracking info (supports common tracking plugins)
     */
    private static function get_tracking_info($order) {
        // Check for WooCommerce Shipment Tracking
        $tracking_items = $order->get_meta('_wc_shipment_tracking_items');
        
        if (!empty($tracking_items) && is_array($tracking_items)) {
            $track = $tracking_items[0];
            return array(
                'carrier' => $track['tracking_provider'] ?? '',
                'number' => $track['tracking_number'] ?? '',
                'url' => $track['custom_tracking_link'] ?? ''
            );
        }
        
        // Check for simple tracking number meta
        $tracking_number = $order->get_meta('_tracking_number');
        if ($tracking_number) {
            return array(
                'carrier' => $order->get_meta('_tracking_provider') ?? 'Carrier',
                'number' => $tracking_number,
                'url' => ''
            );
        }
        
        return null;
    }
}
