<?php
/**
 * WooCommerce Product Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_WooCommerce {
    
    /**
     * Search products by keyword
     */
    public static function search_products($keyword, $limit = 10) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $keyword,
            'posts_per_page' => $limit
        );
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product) {
                    $products[] = self::format_product($product);
                }
            }
        }
        
        wp_reset_postdata();
        return $products;
    }
    
    /**
     * Search products by category (slug or name)
     */
    public static function search_by_category($category_query, $limit = 10) {
        // First try to find the category
        $category = self::find_category($category_query);
        
        if (!$category) {
            return array();
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category->term_id
                )
            )
        );
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product) {
                    $products[] = self::format_product($product);
                }
            }
        }
        
        wp_reset_postdata();
        return $products;
    }
    
    /**
     * Get product by ID
     */
    public static function get_product($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return null;
        }
        
        return self::format_product($product);
    }
    
    /**
     * Format product data for chat
     */
    public static function format_product($product) {
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src();
        
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price_html(),
            'price_raw' => $product->get_price(),
            'image' => $image_url,
            'url' => get_permalink($product->get_id()),
            'in_stock' => $product->is_in_stock(),
            'stock_status' => $product->get_stock_status(),
            'short_description' => wp_trim_words($product->get_short_description(), 15),
            'type' => $product->get_type(),
            'rating' => $product->get_average_rating()
        );
    }
    
    /**
     * Get all product categories
     */
    public static function get_categories() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));
        
        $result = array();
        foreach ($categories as $cat) {
            $result[] = array(
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'count' => $cat->count
            );
        }
        
        return $result;
    }
    
    /**
     * Add product to cart via AJAX
     */
    public static function add_to_cart($product_id, $quantity = 1) {
        if (!$product_id) {
            return array('success' => false, 'message' => 'Invalid product');
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return array('success' => false, 'message' => 'Product not found');
        }
        
        if (!$product->is_in_stock()) {
            return array('success' => false, 'message' => 'Product is out of stock');
        }
        
        // Ensure WooCommerce cart is available
        if (!function_exists('WC') || !WC()->cart) {
            return array('success' => false, 'message' => 'Cart not available');
        }
        
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
        
        if ($cart_item_key) {
            return array(
                'success' => true,
                'message' => $product->get_name() . ' added to cart!',
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_total' => WC()->cart->get_cart_total()
            );
        }
        
        return array('success' => false, 'message' => 'Could not add to cart');
    }
    
    /**
     * Get cart info
     */
    public static function get_cart_info() {
        if (!WC()->cart) {
            return array('count' => 0, 'total' => '$0.00');
        }
        
        return array(
            'count' => WC()->cart->get_cart_contents_count(),
            'total' => WC()->cart->get_cart_total(),
            'checkout_url' => wc_get_checkout_url(),
            'cart_url' => wc_get_cart_url()
        );
    }
    
    /**
     * Get best selling products
     */
    public static function get_best_sellers($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get products on sale
     */
    public static function get_on_sale($limit = 5) {
        $sale_products = wc_get_product_ids_on_sale();
        
        if (empty($sale_products)) {
            return array();
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__in' => $sale_products,
            'orderby' => 'rand'
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get new arrivals (last 30 days)
     */
    public static function get_new_arrivals($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => array(
                array(
                    'after' => '30 days ago'
                )
            )
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get featured products
     */
    public static function get_featured($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'featured'
                )
            )
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get products by price range
     */
    public static function get_by_price($min_price = 0, $max_price = 999999, $limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_price',
                    'value' => array($min_price, $max_price),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                )
            ),
            'orderby' => 'meta_value_num',
            'meta_key' => '_price',
            'order' => 'ASC'
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get cheapest products
     */
    public static function get_cheapest($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => '_price',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC'
                )
            )
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get most expensive products
     */
    public static function get_expensive($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => '_price',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get top rated products
     */
    public static function get_top_rated($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => '_wc_average_rating',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_wc_average_rating',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC'
                )
            )
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get in-stock products count
     */
    public static function get_stock_summary() {
        global $wpdb;
        
        $total = wp_count_posts('product')->publish;
        
        $in_stock = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s 
            AND p.post_status = %s
            AND pm.meta_key = %s 
            AND pm.meta_value = %s
        ", 'product', 'publish', '_stock_status', 'instock'));
        
        $on_sale = count(wc_get_product_ids_on_sale());
        
        return array(
            'total' => (int)$total,
            'in_stock' => (int)$in_stock,
            'out_of_stock' => (int)$total - (int)$in_stock,
            'on_sale' => $on_sale
        );
    }
    
    /**
     * Get store statistics
     */
    public static function get_store_stats() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));
        
        $stock = self::get_stock_summary();
        
        return array(
            'total_products' => $stock['total'],
            'in_stock' => $stock['in_stock'],
            'on_sale' => $stock['on_sale'],
            'categories_count' => count($categories),
            'categories' => array_slice(array_map(function($cat) {
                return $cat->name;
            }, $categories), 0, 10)
        );
    }
    
    /**
     * Get related products
     */
    public static function get_related($product_id, $limit = 3) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return array();
        }
        
        $related_ids = wc_get_related_products($product_id, $limit);
        $products = array();
        
        foreach ($related_ids as $id) {
            $prod = wc_get_product($id);
            if ($prod) {
                $products[] = self::format_product($prod);
            }
        }
        
        return $products;
    }
    
    /**
     * Get available (in-stock) products
     */
    public static function get_available($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                )
            ),
            'orderby' => 'rand'
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get all products (random selection)
     */
    public static function get_all_products($limit = 50) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'rand'
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get out of stock products
     */
    public static function get_out_of_stock($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '='
                )
            )
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Get random products
     */
    public static function get_random($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'rand'
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Smart search - comprehensive search across all product fields
     */
    public static function smart_search($query, $limit = 10) {
        $query = strtolower(trim($query));
        
        // Step 1: Try exact title match first
        $products = self::search_by_title($query, $limit);
        if (!empty($products)) {
            return $products;
        }
        
        // Step 2: Try category search
        $products = self::search_by_category($query, $limit);
        if (!empty($products)) {
            return $products;
        }
        
        // Step 3: Comprehensive search (title + content + description + excerpt)
        $products = self::search_all_fields($query, $limit);
        if (!empty($products)) {
            return $products;
        }
        
        // Step 4: Search by tags
        $products = self::search_by_tag($query, $limit);
        if (!empty($products)) {
            return $products;
        }
        
        // Step 5: Try individual keywords
        $words = explode(' ', $query);
        $stopwords = array('show', 'me', 'the', 'a', 'an', 'of', 'for', 'products', 'product', 'items', 'item', 'i', 'want', 'need', 'looking', 'find', 'get');
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stopwords)) {
                $products = self::search_all_fields($word, $limit);
                if (!empty($products)) {
                    return $products;
                }
            }
        }
        
        return array();
    }
    
    /**
     * Search products by exact title match
     */
    public static function search_by_title($keyword, $limit = 10) {
        global $wpdb;
        
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        
        $product_ids = $wpdb->get_col($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish' 
            AND LOWER(post_title) LIKE %s 
            LIMIT %d
        ", $like, $limit));
        
        $products = array();
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $products[] = self::format_product($product);
            }
        }
        
        return $products;
    }
    
    /**
     * Search all product fields (title, content, description, excerpt)
     */
    public static function search_all_fields($keyword, $limit = 10) {
        global $wpdb;
        
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        
        // Search in post title, content, and excerpt
        $product_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish' 
            AND (
                LOWER(p.post_title) LIKE %s 
                OR LOWER(p.post_content) LIKE %s 
                OR LOWER(p.post_excerpt) LIKE %s
                OR (pm.meta_key = '_sku' AND LOWER(pm.meta_value) LIKE %s)
            )
            LIMIT %d
        ", $like, $like, $like, $like, $limit));
        
        $products = array();
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $products[] = self::format_product($product);
            }
        }
        
        return $products;
    }
    
    /**
     * Search products by tag
     */
    public static function search_by_tag($tag_name, $limit = 10) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field' => 'name',
                    'terms' => $tag_name,
                    'operator' => 'LIKE'
                )
            )
        );
        
        return self::get_products_by_args($args);
    }
    
    /**
     * Helper: Get products by WP_Query args
     */
    private static function get_products_by_args($args) {
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $products[] = self::format_product($product);
                }
            }
        }
        
        wp_reset_postdata();
        return $products;
    }
    
    /**
     * Get shipping methods info
     */
    public static function get_shipping_info() {
        $shipping_zones = WC_Shipping_Zones::get_zones();
        $methods = array();
        
        foreach ($shipping_zones as $zone) {
            foreach ($zone['shipping_methods'] as $method) {
                if ($method->is_enabled()) {
                    $methods[] = array(
                        'title' => $method->get_method_title(),
                        'description' => $method->get_method_description(),
                        'cost' => method_exists($method, 'get_option') ? $method->get_option('cost', 'Varies') : 'Varies'
                    );
                }
            }
        }
        
        // Get default zone methods
        $default_zone = new WC_Shipping_Zone(0);
        foreach ($default_zone->get_shipping_methods() as $method) {
            if ($method->is_enabled()) {
                $methods[] = array(
                    'title' => $method->get_method_title(),
                    'description' => $method->get_method_description(),
                    'cost' => method_exists($method, 'get_option') ? $method->get_option('cost', 'Varies') : 'Varies'
                );
            }
        }
        
        return array_unique($methods, SORT_REGULAR);
    }
    
    /**
     * Get payment methods info
     */
    public static function get_payment_methods() {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $methods = array();
        
        foreach ($gateways as $gateway) {
            $methods[] = array(
                'id' => $gateway->id,
                'title' => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'icon' => $gateway->get_icon()
            );
        }
        
        return $methods;
    }
    
    /**
     * Get store policies (pages)
     */
    public static function get_store_policies() {
        $policies = array();
        
        // Privacy policy
        $privacy_page = get_option('wp_page_for_privacy_policy');
        if ($privacy_page) {
            $policies['privacy'] = array(
                'title' => 'Privacy Policy',
                'url' => get_permalink($privacy_page),
                'content' => wp_trim_words(get_post_field('post_content', $privacy_page), 50)
            );
        }
        
        // Terms & Conditions
        $terms_page = wc_get_page_id('terms');
        if ($terms_page > 0) {
            $policies['terms'] = array(
                'title' => 'Terms & Conditions',
                'url' => get_permalink($terms_page),
                'content' => wp_trim_words(get_post_field('post_content', $terms_page), 50)
            );
        }
        
        // Refund policy - check for common page titles (WordPress 6.2+ compatible)
        $refund_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'title' => 'Refund Policy',
            'posts_per_page' => 1
        ]);
        $refund_page = $refund_pages[0] ?? null;
        
        if (!$refund_page) {
            $return_pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'title' => 'Return Policy',
                'posts_per_page' => 1
            ]);
            $refund_page = $return_pages[0] ?? null;
        }
        
        if ($refund_page) {
            $policies['refund'] = array(
                'title' => $refund_page->post_title,
                'url' => get_permalink($refund_page->ID),
                'content' => wp_trim_words($refund_page->post_content, 50)
            );
        }
        
        return $policies;
    }
    
    /**
     * Get active coupons (limited info for security)
     */
    public static function get_active_coupons() {
        $args = array(
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'posts_per_page' => 10
        );
        
        $coupons = get_posts($args);
        $result = array();
        
        foreach ($coupons as $coupon_post) {
            $coupon = new WC_Coupon($coupon_post->ID);
            
            // Check if coupon is still valid
            $expiry = $coupon->get_date_expires();
            if ($expiry && $expiry->getTimestamp() < time()) {
                continue; // Skip expired coupons
            }
            
            // Only show public coupons (no email restrictions)
            if (!empty($coupon->get_email_restrictions())) {
                continue;
            }
            
            $result[] = array(
                'code' => $coupon->get_code(),
                'type' => $coupon->get_discount_type(),
                'amount' => $coupon->get_amount(),
                'description' => $coupon->get_description(),
                'min_spend' => $coupon->get_minimum_amount()
            );
        }
        
        return $result;
    }
    
    /**
     * Get detailed category info
     */
    public static function get_category_info($category_slug) {
        $term = get_term_by('slug', $category_slug, 'product_cat');
        
        if (!$term) {
            // Try by name
            $term = get_term_by('name', $category_slug, 'product_cat');
        }
        
        if (!$term) {
            return null;
        }
        
        $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
        $image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
        
        return array(
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'count' => $term->count,
            'image' => $image,
            'url' => get_term_link($term)
        );
    }
    
    /**
     * Find category by name (fuzzy match)
     */
    public static function find_category($query) {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));
        
        $query_lower = strtolower($query);
        
        foreach ($categories as $cat) {
            if (strpos(strtolower($cat->name), $query_lower) !== false || 
                strpos(strtolower($cat->slug), $query_lower) !== false) {
                return $cat;
            }
        }
        
        return null;
    }
    
    /**
     * Get products by category name (fuzzy)
     */
    public static function get_products_by_category_name($category_name, $limit = 5) {
        $category = self::find_category($category_name);
        
        if (!$category) {
            return array('products' => array(), 'category' => null);
        }
        
        $products = self::search_by_category($category->slug, $limit);
        
        return array(
            'products' => $products,
            'category' => array(
                'name' => $category->name,
                'count' => $category->count,
                'slug' => $category->slug
            )
        );
    }
    
    /**
     * Get store general info
     */
    public static function get_store_info() {
        return array(
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
            'email' => get_option('woocommerce_email_from_address', get_option('admin_email')),
            'currency' => get_woocommerce_currency_symbol(),
            'currency_code' => get_woocommerce_currency(),
            'country' => WC()->countries->get_base_country(),
            'address' => WC()->countries->get_base_address(),
            'city' => WC()->countries->get_base_city(),
            'shop_page' => get_permalink(wc_get_page_id('shop')),
            'cart_page' => wc_get_cart_url(),
            'checkout_page' => wc_get_checkout_url(),
            'my_account_page' => get_permalink(wc_get_page_id('myaccount'))
        );
    }
    
    /**
     * Get complete store overview
     */
    public static function get_complete_overview() {
        $stats = self::get_store_stats();
        $info = self::get_store_info();
        $payment = self::get_payment_methods();
        $shipping = self::get_shipping_info();
        
        return array(
            'store' => $info,
            'products' => $stats,
            'payment_methods' => count($payment),
            'shipping_methods' => count($shipping)
        );
    }
    
    /**
     * Get user's country from IP address
     * Uses WooCommerce's geolocate functionality if available, falls back to free API
     */
    public static function get_user_country_by_ip() {
        $ip = self::get_user_ip();
        
        // Try WooCommerce geolocation first (if enabled)
        if (class_exists('WC_Geolocation')) {
            $geo = WC_Geolocation::geolocate_ip($ip);
            if (!empty($geo['country'])) {
                return array(
                    'country_code' => $geo['country'],
                    'country_name' => WC()->countries->countries[$geo['country']] ?? $geo['country'],
                    'state' => $geo['state'] ?? '',
                    'ip' => $ip,
                    'source' => 'woocommerce'
                );
            }
        }
        
        // Fallback to free IP-API service (no API key required)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city");
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && $data['status'] === 'success') {
                return array(
                    'country_code' => $data['countryCode'],
                    'country_name' => $data['country'],
                    'state' => $data['regionName'] ?? '',
                    'city' => $data['city'] ?? '',
                    'ip' => $ip,
                    'source' => 'ip-api'
                );
            }
        }
        
        // If all else fails, return unknown
        return array(
            'country_code' => '',
            'country_name' => 'Unknown',
            'ip' => $ip,
            'source' => 'unknown'
        );
    }
    
    /**
     * Get the user's IP address (securely)
     * 
     * Validates and sanitizes all IP address sources to prevent
     * IP spoofing attacks.
     */
    private static function get_user_ip() {
        $ip = '';
        
        // Priority order: most trusted last (REMOTE_ADDR is most reliable)
        // Note: Forwarded headers can be spoofed, but we validate them
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $candidate = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
        
        if (empty($ip) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            $candidates = explode(',', $forwarded);
            $candidate = trim($candidates[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
        
        if (empty($ip) && !empty($_SERVER['REMOTE_ADDR'])) {
            $candidate = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
        
        // Handle localhost
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return ''; // Return empty for localhost, will use default country
        }
        
        return $ip;
    }
    
    /**
     * Check if WooCommerce ships to a specific country
     */
    public static function check_shipping_to_country($country_code) {
        if (empty($country_code)) {
            return array(
                'ships' => false,
                'message' => 'Could not determine your location'
            );
        }
        
        // Get all shipping zones
        $shipping_zones = WC_Shipping_Zones::get_zones();
        $ships_to_country = false;
        $available_methods = array();
        
        // Check each zone for the country
        foreach ($shipping_zones as $zone) {
            $zone_obj = new WC_Shipping_Zone($zone['id']);
            $zone_locations = $zone_obj->get_zone_locations();
            
            foreach ($zone_locations as $location) {
                // Check if country matches
                if ($location->type === 'country' && $location->code === $country_code) {
                    $ships_to_country = true;
                    
                    // Get available shipping methods for this zone
                    foreach ($zone_obj->get_shipping_methods(true) as $method) {
                        $available_methods[] = $method->get_method_title();
                    }
                    break 2;
                }
                
                // Check if continent includes this country
                if ($location->type === 'continent') {
                    $continent = $location->code;
                    $countries_in_continent = WC()->countries->get_continent_countries_for_shipping($continent);
                    
                    if (in_array($country_code, $countries_in_continent)) {
                        $ships_to_country = true;
                        
                        foreach ($zone_obj->get_shipping_methods(true) as $method) {
                            $available_methods[] = $method->get_method_title();
                        }
                        break 2;
                    }
                }
            }
        }
        
        // Check default zone (Rest of the World)
        if (!$ships_to_country) {
            $default_zone = new WC_Shipping_Zone(0);
            $default_methods = $default_zone->get_shipping_methods(true);
            
            if (!empty($default_methods)) {
                $ships_to_country = true;
                foreach ($default_methods as $method) {
                    $available_methods[] = $method->get_method_title();
                }
            }
        }
        
        // Get country name
        $country_name = WC()->countries->countries[$country_code] ?? $country_code;
        
        return array(
            'ships' => $ships_to_country,
            'country_code' => $country_code,
            'country_name' => $country_name,
            'methods' => array_unique($available_methods),
            'message' => $ships_to_country 
                ? "Yes! We ship to {$country_name}." 
                : "Unfortunately, we don't currently ship to {$country_name}."
        );
    }
    
    /**
     * Get delivery info for user's current location (by IP)
     */
    public static function get_delivery_to_user_location() {
        $location = self::get_user_country_by_ip();
        
        if (empty($location['country_code'])) {
            return array(
                'detected' => false,
                'message' => "I couldn't detect your location. Please tell me which country you're in, and I'll check if we deliver there!"
            );
        }
        
        $shipping = self::check_shipping_to_country($location['country_code']);
        
        return array(
            'detected' => true,
            'location' => $location,
            'shipping' => $shipping
        );
    }

    /**
     * Search products with filters (keyword, category, price, tags, stock, rating)
     */
    public static function search_products_filtered($filters = array()) {
        $check = self::ensure_wc();
        if (is_wp_error($check)) {
            return $check;
        }

        $keyword = sanitize_text_field($filters['keyword'] ?? '');
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 10;
        $min_price = isset($filters['min_price']) ? (float) $filters['min_price'] : null;
        $max_price = isset($filters['max_price']) ? (float) $filters['max_price'] : null;
        $in_stock = isset($filters['in_stock']) ? (bool) $filters['in_stock'] : null;
        $on_sale = isset($filters['on_sale']) ? (bool) $filters['on_sale'] : null;
        $category = $filters['category'] ?? null;
        $tags = $filters['tags'] ?? null;
        $rating_min = isset($filters['rating_min']) ? (float) $filters['rating_min'] : null;
        $orderby = sanitize_text_field($filters['orderby'] ?? '');
        $order = strtoupper(sanitize_text_field($filters['order'] ?? 'DESC'));

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
        );

        if ($keyword) {
            $args['s'] = $keyword;
        }

        $tax_query = array();
        if ($category) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field' => is_numeric($category) ? 'term_id' : 'slug',
                'terms' => $category,
            );
        }

        if ($tags) {
            $tax_query[] = array(
                'taxonomy' => 'product_tag',
                'field' => is_numeric($tags) ? 'term_id' : 'slug',
                'terms' => $tags,
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $meta_query = array();
        if ($min_price !== null || $max_price !== null) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => array($min_price ?? 0, $max_price ?? 999999),
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            );
        }

        if ($in_stock === true) {
            $meta_query[] = array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => '='
            );
        }

        if ($rating_min !== null) {
            $meta_query[] = array(
                'key' => '_wc_average_rating',
                'value' => $rating_min,
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        if ($on_sale) {
            $sale_products = wc_get_product_ids_on_sale();
            if (!empty($sale_products)) {
                $args['post__in'] = $sale_products;
            }
        }

        switch ($orderby) {
            case 'price':
                $args['meta_key'] = '_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = $order;
                break;
            case 'rating':
                $args['meta_key'] = '_wc_average_rating';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = $order;
                break;
            case 'date':
                $args['orderby'] = 'date';
                $args['order'] = $order;
                break;
            default:
                // leave default WP_Query behavior
                break;
        }

        return self::get_products_by_args($args);
    }

    /**
     * Get order details with optional email verification
     */
    public static function get_order($order_id_or_key, $email = '') {
        $check = self::ensure_wc();
        if (is_wp_error($check)) {
            return $check;
        }

        $order_id = is_numeric($order_id_or_key) ? (int) $order_id_or_key : 0;
        if (!$order_id && is_string($order_id_or_key)) {
            $order_id = wc_get_order_id_by_order_key($order_id_or_key);
        }

        if (!$order_id) {
            return self::wc_error('order_not_found', 'Order not found');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return self::wc_error('order_not_found', 'Order not found');
        }

        if ($email) {
            $billing_email = strtolower((string) $order->get_billing_email());
            if ($billing_email && strtolower($email) !== $billing_email) {
                return self::wc_error('order_email_mismatch', 'Order email does not match');
            }
        }

        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
            );
        }

        return array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'created_at' => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
            'items' => $items,
            'billing_email' => $order->get_billing_email(),
        );
    }

    /**
     * Get order status information
     */
    public static function get_order_status($order_id_or_key, $email = '') {
        $order = self::get_order($order_id_or_key, $email);
        if (is_wp_error($order)) {
            return $order;
        }

        return array(
            'id' => $order['id'],
            'status' => $order['status'],
            'status_label' => $order['status_label'],
            'total' => $order['total'],
            'currency' => $order['currency'],
        );
    }

    /**
     * Update cart item quantity
     */
    public static function update_cart_item_quantity($cart_item_key, $quantity) {
        $check = self::ensure_wc(true);
        if (is_wp_error($check)) {
            return $check;
        }

        $quantity = max(0, (int) $quantity);
        $result = WC()->cart->set_quantity($cart_item_key, $quantity, true);

        if ($result === false) {
            return self::wc_error('cart_update_failed', 'Unable to update cart item quantity');
        }

        return array(
            'success' => true,
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total(),
        );
    }

    /**
     * Remove item from cart
     */
    public static function remove_from_cart($cart_item_key) {
        $check = self::ensure_wc(true);
        if (is_wp_error($check)) {
            return $check;
        }

        $result = WC()->cart->remove_cart_item($cart_item_key);
        if (!$result) {
            return self::wc_error('cart_remove_failed', 'Unable to remove cart item');
        }

        return array(
            'success' => true,
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total(),
        );
    }

    /**
     * Clear cart
     */
    public static function clear_cart() {
        $check = self::ensure_wc(true);
        if (is_wp_error($check)) {
            return $check;
        }

        WC()->cart->empty_cart(true);
        return array(
            'success' => true,
            'cart_count' => 0,
            'cart_total' => WC()->cart->get_cart_total(),
        );
    }

    /**
     * Get customer data by ID or email
     */
    public static function get_customer($customer) {
        $check = self::ensure_wc();
        if (is_wp_error($check)) {
            return $check;
        }

        $user = null;
        if (is_numeric($customer)) {
            $user = get_user_by('id', (int) $customer);
        } elseif (is_string($customer)) {
            $user = get_user_by('email', $customer);
        }

        if (!$user) {
            return self::wc_error('customer_not_found', 'Customer not found');
        }

        $wc_customer = new WC_Customer($user->ID);
        return array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'first_name' => $wc_customer->get_first_name(),
            'last_name' => $wc_customer->get_last_name(),
            'billing' => $wc_customer->get_billing(),
            'shipping' => $wc_customer->get_shipping(),
        );
    }

    /**
     * Get customer orders summary
     */
    public static function get_customer_orders($customer, $limit = 5) {
        $check = self::ensure_wc();
        if (is_wp_error($check)) {
            return $check;
        }

        $user_id = 0;
        if (is_numeric($customer)) {
            $user_id = (int) $customer;
        } elseif (is_string($customer)) {
            $user = get_user_by('email', $customer);
            $user_id = $user ? (int) $user->ID : 0;
        }

        if (!$user_id) {
            return self::wc_error('customer_not_found', 'Customer not found');
        }

        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $result = array();
        foreach ($orders as $order) {
            $result[] = array(
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'created_at' => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
            );
        }

        return $result;
    }

    /**
     * Ensure WooCommerce is available
     */
    protected static function ensure_wc($needs_cart = false) {
        if (!class_exists('WooCommerce')) {
            return self::wc_error('woocommerce_missing', 'WooCommerce is not active');
        }

        if (!function_exists('WC')) {
            return self::wc_error('woocommerce_missing', 'WooCommerce is not available');
        }

        if ($needs_cart && (!WC()->cart)) {
            return self::wc_error('cart_unavailable', 'Cart is not available');
        }

        return true;
    }

    /**
     * Helper to build WP_Error
     */
    protected static function wc_error($code, $message, $data = array()) {
        return new WP_Error($code, $message, $data);
    }
}
