<?php
namespace Quarksol\SmartChatbot\Http;

defined('ABSPATH') || exit;

use SWC_Chatbot_WooCommerce;
use SWC_Chatbot_Orders;

/**
 * Class AjaxHandlers
 * 
 * Handles all AJAX requests for the chatbot backend.
 * Replaces the monolithic handling in class-chatbot.php.
 * 
 * @package Quarksol\SmartChatbot\Http
 */
class AjaxHandlers {

    /**
     * Register AJAX hooks
     */
    public function registerHooks() {
        // Chatbot AJAX actions
        add_action('wp_ajax_swc_add_to_cart', [$this, 'addToCart']);
        add_action('wp_ajax_nopriv_swc_add_to_cart', [$this, 'addToCart']);
        
        add_action('wp_ajax_swc_add_to_wishlist', [$this, 'addToWishlist']);
        add_action('wp_ajax_nopriv_swc_add_to_wishlist', [$this, 'addToWishlist']);
        
        add_action('wp_ajax_swc_stock_alert', [$this, 'subscribeStockAlert']);
        add_action('wp_ajax_nopriv_swc_stock_alert', [$this, 'subscribeStockAlert']);
        
        add_action('wp_ajax_swc_get_product_url', [$this, 'getProductUrl']);
        add_action('wp_ajax_nopriv_swc_get_product_url', [$this, 'getProductUrl']);
        
        add_action('wp_ajax_swc_message_feedback', [$this, 'handleFeedback']);
        add_action('wp_ajax_nopriv_swc_message_feedback', [$this, 'handleFeedback']);
    }

    /**
     * AJAX: Add to Cart
     */
    public function addToCart() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        $result = SWC_Chatbot_WooCommerce::add_to_cart($product_id, $quantity);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Add to Wishlist
     */
    public function addToWishlist() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (!$product_id) {
            wp_send_json_error('Invalid product');
        }
        
        // Validate product exists
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            // Use SessionValidator for secure guest wishlist (transients, not PHP sessions)
            if (class_exists('\Quarksol\SmartChatbot\Services\SessionValidator')) {
                $visitorId = \Quarksol\SmartChatbot\Services\SessionValidator::getOrCreateVisitorId();
                $wishlist = \Quarksol\SmartChatbot\Services\SessionValidator::getVisitorData($visitorId, 'wishlist', []);
                
                if (!is_array($wishlist)) {
                    $wishlist = [];
                }
                
                if (!in_array($product_id, $wishlist)) {
                    $wishlist[] = $product_id;
                    \Quarksol\SmartChatbot\Services\SessionValidator::setVisitorData($visitorId, 'wishlist', $wishlist);
                }
                
                wp_send_json_success(['message' => '️ Added to your wishlist! Sign in to save it permanently.']);
            } else {
                // Fallback: Just acknowledge without persistent storage
                wp_send_json_success(['message' => '️ Added to your wishlist! Sign in to save it permanently.']);
            }
            return;
        }
        
        // For logged in users, use user meta
        $user_id = get_current_user_id();
        $wishlist = get_user_meta($user_id, 'swc_wishlist', true);
        if (!is_array($wishlist)) {
            $wishlist = [];
        }
        
        if (!in_array($product_id, $wishlist)) {
            $wishlist[] = $product_id;
            update_user_meta($user_id, 'swc_wishlist', $wishlist);
        }
        
        wp_send_json_success(['message' => '️ Added to your wishlist!']);
    }

    /**
     * AJAX: Stock Alert Subscription
     */
    public function subscribeStockAlert() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!$product_id || !is_email($email)) {
            wp_send_json_error('Please provide a valid email address.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_stock_alerts';
        
        // Check if table exists, if not create it
        $this->maybeCreateStockAlertsTable();
        
        // Check if already subscribed
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE product_id = %d AND email = %s",
            $product_id, $email
        ));
        
        if ($exists) {
            wp_send_json_success(array('message' => "You're already on the notification list for this product!"));
        }
        
        // Insert new subscription
        $wpdb->insert($table, array(
            'product_id' => $product_id,
            'email' => $email,
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array('message' => "We'll notify you at {$email} when this product is back in stock!"));
    }

    /**
     * AJAX handler to get product URL
     */
    public function getProductUrl() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }
        
        $url = get_permalink($product_id);
        wp_send_json_success(array('url' => $url));
    }

    /**
     * AJAX: Handle message feedback (thumbs up/down)
     */
    public function handleFeedback() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $message_id = sanitize_text_field($_POST['message_id'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $feedback_type = sanitize_text_field($_POST['feedback_type'] ?? '');
        
        if (empty($message_id) || empty($feedback_type)) {
            wp_send_json_error('Missing required fields');
        }
        
        if (!in_array($feedback_type, ['up', 'down'], true)) {
            wp_send_json_error('Invalid feedback type');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_message_feedback';
        
        // Create table if not exists
        $this->maybeCreateFeedbackTable();
        
        // Insert or update feedback
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE message_id = %s AND session_id = %s",
            $message_id,
            $session_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $table,
                ['feedback' => $feedback_type, 'updated_at' => current_time('mysql')],
                ['id' => $existing],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert($table, [
                'message_id' => $message_id,
                'session_id' => $session_id,
                'feedback' => $feedback_type,
                'created_at' => current_time('mysql')
            ], ['%s', '%s', '%s', '%s']);
        }
        
        wp_send_json_success(['message' => 'Feedback recorded']);
    }

    /**
     * Create stock alerts table if not exists
     */
    private function maybeCreateStockAlertsTable() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_stock_alerts';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                email varchar(255) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                notified tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY product_id (product_id),
                KEY email (email)
            ) $charset;";
            
            require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Create message feedback table if not exists
     */
    private function maybeCreateFeedbackTable() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        global $wpdb;
        $table = $wpdb->prefix . 'swc_message_feedback';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                message_id varchar(100) NOT NULL,
                session_id varchar(100) NOT NULL,
                feedback varchar(10) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY message_id (message_id),
                KEY session_id (session_id)
            ) $charset;";
            
            require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}
