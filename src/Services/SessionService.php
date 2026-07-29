<?php
declare(strict_types=1);
/**
 * Session Service
 * 
 * Handles session management, wishlist operations, and visitor tracking.
 * Extracted from class-chatbot.php to reduce monolith complexity.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SessionService - Session and wishlist management
 */
class SessionService
{

    /** Session cookie name */
    const COOKIE_NAME = 'swc_session_id';

    /** Session expiry (30 days) */
    const SESSION_EXPIRY = 30 * \DAY_IN_SECONDS;

    /**
     * Get or create session ID
     */
    public static function getSessionId(): string
    {
        // Check for existing session cookie
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            return sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
        }

        // Check for logged-in user
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        // Generate new session for guest
        $sessionId = 'guest_' . wp_generate_uuid4();

        // ==========================================
        // Hook: swc/session/id (filter)
        // Modify the session ID before use
        // ==========================================
        $sessionId = \Quarksol\SmartChatbot\Hooks\Hooks::filter('swc/session/id', $sessionId, is_user_logged_in());

        // Set session cookie (only if headers not sent)
        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $sessionId, [
                'expires' => time() + self::SESSION_EXPIRY,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax', // CSRF protection
            ]);

            // ==========================================
            // Hook: swc/session/created (action)
            // Fired when a new session is created
            // ==========================================
            \Quarksol\SmartChatbot\Hooks\Hooks::action('swc/session/created', $sessionId, is_user_logged_in());
        }

        return $sessionId;
    }

    /**
     * Get visitor ID (shorter identifier for display)
     */
    public static function getVisitorId(): string
    {
        $sessionId = self::getSessionId();
        return substr(md5($sessionId), 0, 8);
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user ID
     */
    public static function getUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Get wishlist for current session
     * 
     * @return array Product IDs in wishlist
     */
    public static function getWishlist(): array
    {
        $sessionId = self::getSessionId();
        $wishlist = get_transient('swc_wishlist_' . $sessionId);
        return is_array($wishlist) ? $wishlist : [];
    }

    /**
     * Add product to wishlist
     * 
     * @param int $productId Product ID to add
     * @return bool Success
     */
    public static function addToWishlist(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $wishlist = self::getWishlist();

        if (!in_array($productId, $wishlist, true)) {
            $wishlist[] = $productId;
            $sessionId = self::getSessionId();
            set_transient('swc_wishlist_' . $sessionId, $wishlist, self::SESSION_EXPIRY);
        }

        return true;
    }

    /**
     * Remove product from wishlist
     * 
     * @param int $productId Product ID to remove
     * @return bool Success
     */
    public static function removeFromWishlist(int $productId): bool
    {
        $wishlist = self::getWishlist();
        $key = array_search($productId, $wishlist, true);

        if ($key !== false) {
            unset($wishlist[$key]);
            $wishlist = array_values($wishlist);
            $sessionId = self::getSessionId();
            set_transient('swc_wishlist_' . $sessionId, $wishlist, self::SESSION_EXPIRY);
            return true;
        }

        return false;
    }

    /**
     * Clear wishlist
     */
    public static function clearWishlist(): void
    {
        $sessionId = self::getSessionId();
        delete_transient('swc_wishlist_' . $sessionId);
    }

    /**
     * Get wishlist product details
     * 
     * @return array Array of product data
     */
    public static function getWishlistProducts(): array
    {
        $wishlist = self::getWishlist();
        $products = [];

        foreach ($wishlist as $productId) {
            $product = wc_get_product($productId);
            if ($product) {
                $products[] = [
                    'id' => $productId,
                    'name' => $product->get_name(),
                    'price' => wc_price($product->get_price()),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                    'url' => $product->get_permalink(),
                    'in_stock' => $product->is_in_stock(),
                ];
            }
        }

        return $products;
    }

    /**
     * Get stock alerts for current session
     * 
     * @return array Stock alert subscriptions
     */
    public static function getStockAlerts(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'swc_stock_alerts';
        $sessionId = self::getSessionId();

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %s AND status = 'active'",
                $sessionId
            ),
            ARRAY_A
        );
    }

    /**
     * Subscribe to stock alert
     * 
     * @param int $productId Product ID
     * @param string $email Email address
     * @return bool Success
     */
    public static function subscribeStockAlert(int $productId, string $email): bool
    {
        global $wpdb;

        if ($productId <= 0 || !is_email($email)) {
            return false;
        }

        $table = $wpdb->prefix . 'swc_stock_alerts';
        $sessionId = self::getSessionId();

        // Check for existing subscription
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE product_id = %d AND email = %s AND status = 'active'",
            $productId,
            $email
        ));

        if ($exists) {
            return true; // Already subscribed
        }

        return $wpdb->insert($table, [
            'product_id' => $productId,
            'email' => sanitize_email($email),
            'session_id' => $sessionId,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ]) !== false;
    }
}
