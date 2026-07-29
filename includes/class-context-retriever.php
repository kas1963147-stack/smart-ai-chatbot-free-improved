<?php
declare(strict_types=1);
/**
 * Context Retriever Class for RAG Implementation
 * 
 * Retrieves relevant context from store data to augment AI responses.
 * This implements a simple but effective RAG (Retrieval Augmented Generation) approach.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Context_Retriever {
    
    /**
     * Build complete context for AI based on user message
     * 
     * @param string $message User's message
     * @param int|null $user_id Current user ID if logged in
     * @return string Formatted context string
     */
    public static function build_context($message, $user_id = null) {
        $context_parts = array();
        
        // 1. Store Information (safe - uses WordPress functions)
        try {
            $store_context = self::get_store_context();
            if (!empty($store_context)) {
                $context_parts[] = "=== STORE INFORMATION ===\n" . $store_context;
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('RAG get_store_context error', ['error' => $e->getMessage()]);
            }
        }
        
        // 2. Relevant Products (uses WooCommerce - may fail)
        try {
            $products = self::get_relevant_products($message, 5);
            if (!empty($products)) {
                $context_parts[] = "=== RELEVANT PRODUCTS ===\n" . $products;
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('RAG get_relevant_products error', ['error' => $e->getMessage()]);
            }
        }
        
        // 3. Matching FAQs (uses custom table - should be safe)
        try {
            $faqs = self::get_relevant_faqs($message, 3);
            if (!empty($faqs)) {
                $context_parts[] = "=== MATCHING FAQs ===\n" . $faqs;
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('RAG get_relevant_faqs error', ['error' => $e->getMessage()]);
            }
        }
        
        // 4. Current Promotions (uses WooCommerce heavily - SKIP FOR NOW TO PREVENT ERRORS)
        // This is the most likely to cause fatal errors due to WC_Shipping_Zones and WC_Coupon
        // Commenting out until we can make it more robust
        /*
        try {
            $promotions = self::get_active_promotions();
            if (!empty($promotions)) {
                $context_parts[] = "=== CURRENT PROMOTIONS ===\n" . $promotions;
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('RAG get_active_promotions error', ['error' => $e->getMessage()]);
            }
        }
        */
        
        // 5. User Context (if logged in)
        if ($user_id) {
            try {
                $user_context = self::get_user_context($user_id);
                if (!empty($user_context)) {
                    $context_parts[] = "=== USER CONTEXT ===\n" . $user_context;
                }
            } catch (Throwable $e) {
                if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                    \Quarksol\SmartChatbot\Services\Logger::debug('RAG get_user_context error', ['error' => $e->getMessage()]);
                }
            }
        }
        
        // 6. Product Categories
        try {
            $categories = self::get_product_categories();
            if (!empty($categories)) {
                $context_parts[] = "=== AVAILABLE CATEGORIES ===\n" . $categories;
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('RAG get_product_categories error', ['error' => $e->getMessage()]);
            }
        }
        
        return implode("\n\n", $context_parts);
    }
    
    /**
     * Get store context (name, description, policies)
     */
    public static function get_store_context() {
        $store_name = get_bloginfo('name');
        $store_description = get_bloginfo('description');
        $store_url = home_url();
        
        $context = "Store Name: {$store_name}\n";
        if (!empty($store_description)) {
            $context .= "Description: {$store_description}\n";
        }
        $context .= "Website: {$store_url}\n";
        
        // Try to get WooCommerce pages
        if (function_exists('wc_get_page_id')) {
            $shop_page = get_post(wc_get_page_id('shop'));
            if ($shop_page) {
                $context .= "Shop: " . get_permalink($shop_page) . "\n";
            }
        }
        
        // Get store settings if available
        $settings = get_option('swc_chatbot_settings', array());
        if (!empty($settings['store_email'])) {
            $context .= "Contact Email: {$settings['store_email']}\n";
        }
        if (!empty($settings['store_phone'])) {
            $context .= "Phone: {$settings['store_phone']}\n";
        }
        if (!empty($settings['shipping_info'])) {
            $context .= "Shipping: {$settings['shipping_info']}\n";
        }
        if (!empty($settings['return_policy'])) {
            $context .= "Returns: {$settings['return_policy']}\n";
        }
        
        return $context;
    }
    
    /**
     * Get relevant products based on query
     * 
     * @param string $query User's message
     * @param int $limit Maximum products to return
     * @return string Formatted product list
     */
    public static function get_relevant_products($query, $limit = 5) {
        if (!class_exists('SWC_Chatbot_WooCommerce')) {
            return '';
        }
        
        // Use existing smart search
        $products = SWC_Chatbot_WooCommerce::smart_search($query, $limit);
        
        if (empty($products)) {
            return '';
        }
        
        $output = "";
        foreach ($products as $index => $product) {
            $num = $index + 1;
            $name = $product['name'] ?? 'Unknown';
            $price = $product['price'] ?? 'N/A';
            $stock = isset($product['quantity']) && $product['quantity'] > 0 ? "In Stock ({$product['quantity']} available)" : "Out of Stock";
            
            $output .= "{$num}. {$name}\n";
            $output .= "   Price: {$price}\n";
            $output .= "   Status: {$stock}\n";
            
            if (!empty($product['description'])) {
                $desc = wp_trim_words(strip_tags($product['description']), 25);
                $output .= "   Description: {$desc}\n";
            }
            
            if (!empty($product['categories'])) {
                $output .= "   Categories: {$product['categories']}\n";
            }
            
            if (!empty($product['url'])) {
                $output .= "   URL: {$product['url']}\n";
            }
            
            $output .= "\n";
        }
        
        return trim($output);
    }
    
    /**
     * Get relevant FAQs based on query
     * 
     * @param string $query User's message
     * @param int $limit Maximum FAQs to return
     * @return string Formatted FAQ list
     */
    public static function get_relevant_faqs($query, $limit = 3) {
        if (!class_exists('SWC_Chatbot_FAQ')) {
            return '';
        }
        
        // Get all FAQs
        $all_faqs = SWC_Chatbot_FAQ::get_faqs();
        
        if (empty($all_faqs)) {
            return '';
        }
        
        // Extract keywords from query
        $keywords = self::extract_keywords($query);
        
        // Score FAQs by relevance
        $scored_faqs = array();
        foreach ($all_faqs as $faq) {
            $score = 0;
            $faq_text = strtolower($faq['question'] . ' ' . $faq['answer']);
            
            foreach ($keywords as $keyword) {
                if (stripos($faq_text, $keyword) !== false) {
                    $score += 1;
                    // Bonus for question match
                    if (stripos(strtolower($faq['question']), $keyword) !== false) {
                        $score += 2;
                    }
                }
            }
            
            if ($score > 0) {
                $faq['score'] = $score;
                $scored_faqs[] = $faq;
            }
        }
        
        // Sort by score descending
        usort($scored_faqs, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Take top results
        $scored_faqs = array_slice($scored_faqs, 0, $limit);
        
        if (empty($scored_faqs)) {
            return '';
        }
        
        $output = "";
        foreach ($scored_faqs as $faq) {
            $output .= "Q: {$faq['question']}\n";
            $output .= "A: {$faq['answer']}\n\n";
        }
        
        return trim($output);
    }
    
    /**
     * Get active promotions and sales
     */
    public static function get_active_promotions() {
        if (!function_exists('WC')) {
            return '';
        }
        
        $promotions = array();
        
        // Get products on sale
        $on_sale = wc_get_product_ids_on_sale();
        if (!empty($on_sale)) {
            $sale_count = count($on_sale);
            $promotions[] = "{$sale_count} products currently on sale";
        }
        
        // Get active coupons (be careful not to expose actual codes)
        $coupons = get_posts(array(
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'date_expires',
                    'value' => time(),
                    'compare' => '>',
                    'type' => 'NUMERIC'
                ),
                array(
                    'key' => 'date_expires',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        if (!empty($coupons)) {
            foreach ($coupons as $coupon_post) {
                $coupon = new WC_Coupon($coupon_post->ID);
                $type = $coupon->get_discount_type();
                $amount = $coupon->get_amount();
                
                if ($type === 'percent') {
                    $promotions[] = "{$amount}% discount available";
                } elseif ($type === 'fixed_cart' || $type === 'fixed_product') {
                    $promotions[] = wc_price($amount) . " discount available";
                }
            }
        }
        
        // Check for free shipping
        $free_shipping_zones = array();
        $shipping_zones = WC_Shipping_Zones::get_zones();
        foreach ($shipping_zones as $zone) {
            foreach ($zone['shipping_methods'] as $method) {
                if ($method->id === 'free_shipping' && $method->is_enabled()) {
                    $free_shipping_zones[] = $zone['zone_name'];
                }
            }
        }
        if (!empty($free_shipping_zones)) {
            $promotions[] = "Free shipping available for: " . implode(', ', $free_shipping_zones);
        }
        
        if (empty($promotions)) {
            return '';
        }
        
        return "- " . implode("\n- ", $promotions);
    }
    
    /**
     * Get user context for personalization
     */
    public static function get_user_context($user_id) {
        if (!$user_id || !function_exists('wc_get_orders')) {
            return '';
        }
        
        $context = "Customer is logged in\n";
        
        // Get user's recent orders
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => 3,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (!empty($orders)) {
            $context .= "Recent orders:\n";
            foreach ($orders as $order) {
                $order_id = $order->get_id();
                $order_date = $order->get_date_created()->date('M j, Y');
                $order_status = wc_get_order_status_name($order->get_status());
                $order_total = $order->get_formatted_order_total();
                
                $context .= "- Order #{$order_id} ({$order_date}): {$order_status} - {$order_total}\n";
            }
        }
        
        // Get wishlist if available
        $wishlist = get_user_meta($user_id, '_swc_wishlist', true);
        if (!empty($wishlist) && is_array($wishlist)) {
            $context .= "Wishlist items: " . count($wishlist) . "\n";
        }
        
        return $context;
    }
    
    /**
     * Get product categories
     */
    public static function get_product_categories() {
        if (!function_exists('get_terms')) {
            return '';
        }
        
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => 0, // Top-level only
            'number' => 10
        ));
        
        if (is_wp_error($categories) || empty($categories)) {
            return '';
        }
        
        $cat_names = array();
        foreach ($categories as $cat) {
            $count = $cat->count;
            $cat_names[] = "{$cat->name} ({$count} products)";
        }
        
        return implode(", ", $cat_names);
    }
    
    /**
     * Extract keywords from message for matching
     */
    public static function extract_keywords($message) {
        // Remove common words and punctuation
        $stopwords = array(
            'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'must', 'shall', 'can', 'need', 'dare',
            'ought', 'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by',
            'from', 'up', 'about', 'into', 'over', 'after', 'beneath', 'under',
            'above', 'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves',
            'you', 'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his',
            'himself', 'she', 'her', 'hers', 'herself', 'it', 'its', 'itself',
            'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which',
            'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'is', 'are',
            'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having',
            'do', 'does', 'did', 'doing', 'and', 'but', 'if', 'or', 'because',
            'as', 'until', 'while', 'of', 'at', 'by', 'for', 'with', 'about',
            'against', 'between', 'into', 'through', 'during', 'before', 'after',
            'above', 'below', 'to', 'from', 'up', 'down', 'in', 'out', 'on', 'off',
            'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there',
            'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most',
            'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same',
            'so', 'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don',
            'should', 'now', 'd', 'll', 'm', 'o', 're', 've', 'y', 'ain', 'aren',
            'couldn', 'didn', 'doesn', 'hadn', 'hasn', 'haven', 'isn', 'ma',
            'mightn', 'mustn', 'needn', 'shan', 'shouldn', 'wasn', 'weren', 'won',
            'wouldn', 'tell', 'show', 'find', 'get', 'want', 'looking', 'look',
            'please', 'thanks', 'thank', 'hi', 'hello', 'hey'
        );
        
        // Clean and tokenize
        $message = strtolower($message);
        $message = preg_replace('/[^\w\s]/', ' ', $message);
        $words = preg_split('/\s+/', $message, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter stopwords and short words
        $keywords = array();
        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
}
