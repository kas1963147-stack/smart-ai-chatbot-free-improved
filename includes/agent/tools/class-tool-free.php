<?php
/**
 * Free Tier Built-in Hidden Tool
 * 
 * Provides basic functionality like order tracking and generic
 * product search to free-tier AI agents without requiring UI setup.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SWC_CHATBOT_PATH . 'includes/agent/tools/class-tool-base.php';
require_once SWC_CHATBOT_PATH . 'includes/class-woocommerce.php';
require_once SWC_CHATBOT_PATH . 'includes/class-orders.php';

class SWC_Tool_Free_Assistant extends SWC_Tool_Base {
    
    /**
     * Get available actions
     */
    public function get_actions() {
        return ['track_order', 'search_products', 'search_posts', 'read_post'];
    }
    
    /**
     * Get tool definition for AI
     */
    public function get_ai_definition() {
        return [
            'description' => 'Built-in store assistant for tracking orders, finding products, and reading blog articles.',
            'actions' => [
                'track_order' => 'Check the status of an order using an Order ID and Email.',
                'search_products' => 'Search for products available in the store using keywords.',
                'search_posts' => 'Search blog posts and articles by keyword. Returns titles, excerpts, and links.',
                'read_post' => 'Read the full content of a specific blog post or article by its ID.'
            ]
        ];
    }
    
    /**
     * Track Order
     */
    public function track_order($params) {
        error_log('[SWC Free Tool] track_order called with params: ' . wp_json_encode($params));
        $this->log('track_order', $params);
        
        try {
            $this->validate_params($params, ['order_id', 'email']);
            
            // Check if Guest Order Lookup is enabled in settings
            $settings = get_option('swc_chatbot_settings', []);
            if (empty($settings['allow_guest_tracking']) && empty($settings['enabled'])) {
                // If the user hasn't enabled guest tracking, return a generic denial
                return [
                    'success' => false,
                    'message' => 'Order tracking via email and order ID is currently disabled by store administrator.'
                ];
            }
            
            $result = SWC_Chatbot_Orders::get_order_by_email($params['order_id'], $params['email']);
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search Products
     */
    public function search_products($params) {
        error_log('[SWC Free Tool] search_products called with params: ' . wp_json_encode($params));
        $this->log('search_products', $params);
        
        try {
            $this->validate_params($params, ['keyword']);
            
            $limit = isset($params['limit']) ? intval($params['limit']) : 5;
            $products = SWC_Chatbot_WooCommerce::smart_search($params['keyword'], $limit);
            
            if (empty($products)) {
                return [
                    'success' => true,
                    'message' => 'No products found matching that keyword.'
                ];
            }
            
            return [
                'success' => true,
                'products' => $products
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search Posts / Articles / Blogs
     */
    public function search_posts($params) {
        error_log('[SWC Free Tool] search_posts called with params: ' . wp_json_encode($params));
        $this->log('search_posts', $params);
        
        try {
            $this->validate_params($params, ['keyword']);
            
            $limit = isset($params['limit']) ? intval($params['limit']) : 5;
            
            $query_args = [
                's'              => sanitize_text_field($params['keyword']),
                'post_type'      => ['post', 'page'],
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => 'relevance',
            ];
            
            $query = new \WP_Query($query_args);
            
            if (!$query->have_posts()) {
                return [
                    'success' => true,
                    'message' => 'No articles or blog posts found matching that keyword.'
                ];
            }
            
            $results = [];
            while ($query->have_posts()) {
                $query->the_post();
                $post = get_post();
                $results[] = [
                    'id'      => $post->ID,
                    'title'   => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 30, '...'),
                    'url'     => get_permalink(),
                    'date'    => get_the_date('M j, Y'),
                    'type'    => $post->post_type === 'page' ? 'Page' : 'Blog Post',
                ];
            }
            wp_reset_postdata();
            
            return [
                'success' => true,
                'posts'   => $results,
                'total'   => $query->found_posts
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Read a specific Post / Article / Blog
     */
    public function read_post($params) {
        error_log('[SWC Free Tool] read_post called with params: ' . wp_json_encode($params));
        $this->log('read_post', $params);
        
        try {
            $this->validate_params($params, ['post_id']);
            
            $post = get_post(intval($params['post_id']));
            
            if (!$post || $post->post_status !== 'publish') {
                return [
                    'success' => false,
                    'message' => 'Post not found or not published.'
                ];
            }
            
            // Strip HTML and convert to clean text
            $content = wp_strip_all_tags($post->post_content);
            $content = wp_trim_words($content, 500, '...');
            
            return [
                'success' => true,
                'post'    => [
                    'id'       => $post->ID,
                    'title'    => $post->post_title,
                    'content'  => $content,
                    'url'      => get_permalink($post->ID),
                    'date'     => get_the_date('M j, Y', $post),
                    'author'   => get_the_author_meta('display_name', $post->post_author),
                    'type'     => $post->post_type === 'page' ? 'Page' : 'Blog Post',
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
