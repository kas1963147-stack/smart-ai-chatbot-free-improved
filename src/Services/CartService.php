<?php
declare(strict_types=1);
/**
 * Cart Service
 * 
 * Handles shopping cart operations for the chatbot.
 * Extracted from class-chatbot.php to reduce monolith complexity.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CartService - WooCommerce cart operations
 */
class CartService {
    
    /**
     * Add product to cart
     * 
     * @param int $productId Product ID
     * @param int $quantity Quantity to add
     * @param array $variation Variation data for variable products
     * @return array Result with success status and message
     */
    public static function addToCart(int $productId, int $quantity = 1, array $variation = []): array {
        if ($productId <= 0) {
            return [
                'success' => false,
                'message' => __('Invalid product ID', 'smart-ai-chatbot'),
            ];
        }
        
        $product = wc_get_product($productId);
        
        if (!$product) {
            return [
                'success' => false,
                'message' => __('Product not found', 'smart-ai-chatbot'),
            ];
        }
        
        // Check stock
        if (!$product->is_in_stock()) {
            return [
                'success' => false,
                'message' => __('Sorry, this product is out of stock.', 'smart-ai-chatbot'),
            ];
        }
        
        // Handle variable products
        $variationId = 0;
        if ($product->is_type('variable')) {
            if (!empty($variation)) {
                $variationId = self::findVariation($productId, $variation);
                if (!$variationId) {
                    return [
                        'success' => false,
                        'message' => __('Please select product options.', 'smart-ai-chatbot'),
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => __('Please select product options.', 'smart-ai-chatbot'),
                ];
            }
        }
        
        try {
            // Ensure WooCommerce session is started
            if (!WC()->session->has_session()) {
                WC()->session->set_customer_session_cookie(true);
            }
            
            // Add to cart
            $cartItemKey = WC()->cart->add_to_cart(
                $productId,
                $quantity,
                $variationId,
                $variation
            );
            
            if ($cartItemKey) {
                return [
                    'success' => true,
                    'message' => sprintf(
                        __('%s has been added to your cart!', 'smart-ai-chatbot'),
                        $product->get_name()
                    ),
                    'cart_item_key' => $cartItemKey,
                    'cart_url' => wc_get_cart_url(),
                    'checkout_url' => wc_get_checkout_url(),
                    'cart_count' => WC()->cart->get_cart_contents_count(),
                    'cart_total' => WC()->cart->get_cart_total(),
                ];
            }
            
            return [
                'success' => false,
                'message' => __('Could not add item to cart.', 'smart-ai-chatbot'),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Find variation ID from attributes
     * 
     * @param int $productId Parent product ID
     * @param array $attributes Attribute values (e.g., ['pa_color' => 'red'])
     * @return int Variation ID or 0
     */
    public static function findVariation(int $productId, array $attributes): int {
        $product = wc_get_product($productId);
        
        if (!$product || !$product->is_type('variable')) {
            return 0;
        }
        
        $dataStore = \WC_Data_Store::load('product');
        return $dataStore->find_matching_product_variation($product, $attributes);
    }
    
    /**
     * Get cart contents
     * 
     * @return array Cart items with product details
     */
    public static function getCartContents(): array {
        if (!WC()->cart) {
            return [];
        }
        
        $items = [];
        
        foreach (WC()->cart->get_cart() as $cartItemKey => $cartItem) {
            $product = $cartItem['data'];
            
            $items[] = [
                'key' => $cartItemKey,
                'product_id' => $cartItem['product_id'],
                'variation_id' => $cartItem['variation_id'],
                'name' => $product->get_name(),
                'quantity' => $cartItem['quantity'],
                'price' => wc_price($product->get_price()),
                'subtotal' => wc_price($cartItem['line_subtotal']),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
            ];
        }
        
        return $items;
    }
    
    /**
     * Get cart summary
     * 
     * @return array Cart totals and counts
     */
    public static function getCartSummary(): array {
        if (!WC()->cart) {
            return [
                'count' => 0,
                'subtotal' => wc_price(0),
                'total' => wc_price(0),
                'cart_url' => wc_get_cart_url(),
                'checkout_url' => wc_get_checkout_url(),
            ];
        }
        
        return [
            'count' => WC()->cart->get_cart_contents_count(),
            'subtotal' => WC()->cart->get_cart_subtotal(),
            'total' => WC()->cart->get_cart_total(),
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
        ];
    }
    
    /**
     * Remove item from cart
     * 
     * @param string $cartItemKey Cart item key
     * @return bool Success
     */
    public static function removeFromCart(string $cartItemKey): bool {
        if (!WC()->cart) {
            return false;
        }
        
        return WC()->cart->remove_cart_item($cartItemKey);
    }
    
    /**
     * Update cart item quantity
     * 
     * @param string $cartItemKey Cart item key
     * @param int $quantity New quantity
     * @return bool Success
     */
    public static function updateQuantity(string $cartItemKey, int $quantity): bool {
        if (!WC()->cart) {
            return false;
        }
        
        return WC()->cart->set_quantity($cartItemKey, $quantity);
    }
    
    /**
     * Clear the entire cart
     */
    public static function clearCart(): void {
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
    }
    
    /**
     * Check if product is in cart
     * 
     * @param int $productId Product ID
     * @return bool|string Cart item key if found, false otherwise
     */
    public static function isInCart(int $productId) {
        if (!WC()->cart) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cartItemKey => $cartItem) {
            if ($cartItem['product_id'] === $productId) {
                return $cartItemKey;
            }
        }
        
        return false;
    }
}
