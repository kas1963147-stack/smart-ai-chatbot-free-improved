<?php
declare(strict_types=1);
/**
 * Security Headers Service
 * 
 * Adds security headers to responses to protect against common attacks.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Headers Service
 * 
 * Sends security-related HTTP headers.
 */
class SecurityHeaders
{
    /**
     * Send all security headers
     */
    public static function send(): void
    {
        if (headers_sent()) {
            return;
        }
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // XSS Protection (legacy but still useful for older browsers)
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy (formerly Feature-Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // HSTS for admin pages on HTTPS
        if (is_ssl() && is_admin()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Send Content Security Policy for admin pages
     */
    public static function sendAdminCSP(): void
    {
        if (headers_sent() || !is_admin()) {
            return;
        }
        
        $homeUrl = home_url();
        $adminUrl = admin_url();
        
        // Relatively permissive CSP for WordPress admin
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // WP admin needs these
            "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
            "font-src 'self' fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
        ];
        
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }
    
    /**
     * Send headers for REST API responses
     */
    public static function sendApiHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        
        self::send();
        
        // API-specific headers
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    
    /**
     * Send CORS headers for API endpoints
     * 
     * @param string|null $allowedOrigin Specific origin to allow, or null for same-origin only
     */
    public static function sendCorsHeaders(?string $allowedOrigin = null): void
    {
        if (headers_sent()) {
            return;
        }
        
        $origin = $allowedOrigin ?: home_url();
        
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    /**
     * Add security headers via WordPress hook
     */
    public static function register(): void
    {
        add_action('send_headers', [self::class, 'send'], 1);
        add_action('admin_init', [self::class, 'sendAdminCSP'], 1);
        add_action('rest_pre_serve_request', [self::class, 'sendApiHeaders'], 1);
    }
}
