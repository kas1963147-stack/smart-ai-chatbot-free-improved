<?php
declare(strict_types=1);
/**
 * Product Service
 * 
 * Handles WooCommerce product operations, search, and data formatting.
 * Replaces the legacy SWC_Chatbot_WooCommerce static class.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use WP_Query;
use WC_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Service
 */
class ProductService {
    
    /**
     * Search products by keyword
     * 
     * Simple search — the AI decides what keywords to send.
     * 
     * @param string $query Search keyword
     * @param int $limit Max results
     * @param array $priceFilter Optional price filter ['min_price' => float, 'max_price' => float]
     */
    public function search(string $query, int $limit = 10, array $priceFilter = []): array {
        $query = strtolower(trim($query));
        
        if (empty($query)) {
            return [];
        }
        
        // Search title first (strongest match)
        $products = $this->searchByTitle($query, $limit);
        if (!empty($products)) return $products;
        
        // Then category
        $products = $this->searchByCategory($query, $limit);
        if (!empty($products)) return $products;
        
        // Then all fields (content, description, SKU)
        $products = $this->searchAllFields($query, $limit);
        if (!empty($products)) return $products;
        
        // Then tags
        $products = $this->searchByTag($query, $limit);
        if (!empty($products)) return $products;
        
        // Nothing found — return empty. Let the AI decide what to do next.
        return [];
    }
    
    /**
     * Search products by price range only
     */
    public function searchByPrice(?float $minPrice, ?float $maxPrice, int $limit = 10): array {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_key' => '_price',
            'meta_query' => [],
        ];
        
        if ($minPrice !== null) {
            $args['meta_query'][] = [
                'key' => '_price',
                'value' => $minPrice,
                'compare' => '>=',
                'type' => 'NUMERIC',
            ];
        }
        
        if ($maxPrice !== null) {
            $args['meta_query'][] = [
                'key' => '_price',
                'value' => $maxPrice,
                'compare' => '<=',
                'type' => 'NUMERIC',
            ];
        }
        
        if (count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }
        
        return $this->queryProducts($args);
    }
    
    /**
     * Search by title
     */
    public function searchByTitle(string $keyword, int $limit = 10): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        
        $productIds = $wpdb->get_col($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish' 
            AND LOWER(post_title) LIKE %s 
            LIMIT %d
        ", $like, $limit));
        
        return $this->formatProducts($productIds);
    }
    
    /**
     * Search by category
     */
    public function searchByCategory(string $categoryQuery, int $limit = 10): array {
        // Find category term
        $term = get_term_by('slug', $categoryQuery, 'product_cat');
        if (!$term) $term = get_term_by('name', $categoryQuery, 'product_cat');
        
        if (!$term) return [];
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term->term_id
            ]]
        ];
        
        return $this->queryProducts($args);
    }
    
    /**
     * Search all fields
     */
    public function searchAllFields(string $keyword, int $limit = 10): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        
        $productIds = $wpdb->get_col($wpdb->prepare("
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
        
        return $this->formatProducts($productIds);
    }

    /**
     * Search by tag
     */
    public function searchByTag(string $tagName, int $limit = 10): array {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'tax_query' => [[
                'taxonomy' => 'product_tag',
                'field' => 'name',
                'terms' => $tagName,
                'operator' => 'LIKE'
            ]]
        ];
        return $this->queryProducts($args);
    }

    /**
     * Get Best Sellers
     */
    public function getBestSellers(int $limit = 5): array {
        return $this->queryProducts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ]);
    }

    /**
     * Get On Sale
     */
    public function getOnSale(int $limit = 5): array {
        $saleIds = wc_get_product_ids_on_sale();
        if (empty($saleIds)) return [];
        
        return $this->queryProducts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__in' => $saleIds,
            'orderby' => 'rand'
        ]);
    }

    /**
     * Helper: Run WP_Query and format results
     */
    private function queryProducts(array $args): array {
        $query = new WP_Query($args);
        $products = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $products[] = $this->format($product);
                }
            }
        }
        wp_reset_postdata();
        return $products;
    }
    
    /**
     * Get Cart Info
     */
    public function getCartInfo(): array {
        if (!function_exists('WC') || !WC()->cart) {
            return ['count' => 0, 'total' => '$0.00'];
        }
        
        return [
            'count' => WC()->cart->get_cart_contents_count(),
            'total' => WC()->cart->get_cart_total(),
            'checkout_url' => wc_get_checkout_url(),
            'cart_url' => wc_get_cart_url()
        ];
    }

    /**
     * Add to cart
     */
    public function addToCart(int $productId, int $quantity = 1): array {
        if (!function_exists('WC') || !WC()->cart) {
            return ['success' => false, 'message' => 'Cart not available'];
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        if (!$product->is_in_stock()) {
            return ['success' => false, 'message' => 'Product out of stock'];
        }

        try {
            $cartItemKey = WC()->cart->add_to_cart($productId, $quantity);
            if ($cartItemKey) {
                return [
                    'success' => true,
                    'message' => $product->get_name() . ' added to cart!',
                    'cart_count' => WC()->cart->get_cart_contents_count(),
                    'cart_total' => WC()->cart->get_cart_total()
                ];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Could not add to cart'];
    }

    /**
     * Get Order Status
     */
    public function getOrderStatus(string $orderId, ?string $email = null): array {
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return ['error' => 'Order not found'];
        }

        // Email verification if provided
        if ($email && strtolower($order->get_billing_email()) !== strtolower($email)) {
            return ['error' => 'Email does not match order records'];
        }

        return [
            'order_id' => $order->get_id(),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_formatted_order_total(),
            'date' => $order->get_date_created()->format('Y-m-d H:i'),
            'items_count' => $order->get_item_count(),
            'tracking_url' => '#' // Placeholder
        ];
    }

    /**
     * Get Coupon Info
     */
    public function getCoupon(?string $code = null, ?int $id = null): array {
        $coupon = null;
        if ($code) {
            $coupon = new \WC_Coupon($code);
        } elseif ($id) {
            $coupon = new \WC_Coupon($id);
        }
        
        if (!$coupon || !$coupon->get_id()) {
            return ['error' => 'Coupon not found'];
        }
        
        return [
            'id' => $coupon->get_id(),
            'code' => $coupon->get_code(),
            'discount_type' => $coupon->get_discount_type(),
            'amount' => $coupon->get_amount(),
            'description' => $coupon->get_description(),
            'usage_limit' => $coupon->get_usage_limit(),
            'usage_count' => $coupon->get_usage_count(),
            'expiry_date' => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('Y-m-d') : null,
            'minimum_amount' => $coupon->get_minimum_amount(),
            'maximum_amount' => $coupon->get_maximum_amount(),
            'free_shipping' => $coupon->get_free_shipping(),
            'individual_use' => $coupon->get_individual_use(),
            'product_ids' => $coupon->get_product_ids(),
            'excluded_product_ids' => $coupon->get_excluded_product_ids(),
            'product_categories' => $coupon->get_product_categories(),
            'exclude_product_categories' => $coupon->get_excluded_product_categories()
        ];
    }

    /**
     * Helper: Format list of IDs
     */
    private function formatProducts(array $ids): array {
        $products = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $products[] = $this->format($product);
            }
        }
        return $products;
    }

    /**
     * Format single product
     */
    public function format(\WC_Product $product): array {
        $imageId = $product->get_image_id();
        $imageUrl = $imageId ? wp_get_attachment_image_url($imageId, 'woocommerce_thumbnail') : wc_placeholder_img_src();
        
        // Build a clean text price for AI output (strip HTML and decode entities)
        $priceHtml = $product->get_price_html();
        $priceDisplay = html_entity_decode(wp_strip_all_tags($priceHtml), ENT_QUOTES, 'UTF-8');
        
        // Also build a simple formatted price with currency symbol
        $rawPrice = $product->get_price();
        $currencySymbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
        $formattedPrice = $currencySymbol . number_format(floatval($rawPrice), 2);
        
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $priceHtml,
            'price_display' => $priceDisplay,
            'price_formatted' => $formattedPrice,
            'price_raw' => $rawPrice,
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'image' => $imageUrl,
            'url' => $product->get_permalink(),
            'in_stock' => $product->is_in_stock(),
            'stock_status' => $product->get_stock_status(),
            'short_description' => wp_trim_words($product->get_short_description(), 15),
            'type' => $product->get_type(),
            'rating' => $product->get_average_rating()
        ];
    }
    /**
     * Get counts of products by status
     */
    public function getProductStatusCounts(): array {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT post_status, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            GROUP BY post_status
        ");
        
        $counts = [
            'publish' => 0,
            'draft' => 0,
            'pending' => 0,
            'private' => 0,
            'trash' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $status = $row->post_status;
            if (isset($counts[$status])) {
                $counts[$status] = (int)$row->count;
            }
            $counts['total'] += (int)$row->count;
        }
        
        return $counts;
    }
}
