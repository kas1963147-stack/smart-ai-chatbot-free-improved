<?php
declare(strict_types=1);
/**
 * Server Input Service
 * 
 * Provides safe access to $_SERVER variables with proper sanitization.
 * All $_SERVER access should go through this service.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Safe Server Input accessor
 * 
 * Wraps $_SERVER access with proper validation and sanitization.
 */
class ServerInput
{
    /**
     * Get the REQUEST_URI safely
     * 
     * @return string The sanitized request URI
     */
    public static function getRequestUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return esc_url_raw($uri);
    }
    
    /**
     * Get the REQUEST_METHOD
     * 
     * @return string The request method (GET, POST, etc.)
     */
    public static function getRequestMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $allowed = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        $method = strtoupper($method);
        
        return in_array($method, $allowed, true) ? $method : 'GET';
    }
    
    /**
     * Get REMOTE_ADDR safely
     * 
     * @return string The client IP address
     */
    public static function getRemoteAddr(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '127.0.0.1';
    }
    
    /**
     * Get a specific HTTP header
     * 
     * @param string $name Header name (without HTTP_ prefix)
     * @return string|null The header value or null
     */
    public static function getHeader(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        
        if (!isset($_SERVER[$key])) {
            return null;
        }
        
        return sanitize_text_field($_SERVER[$key]);
    }
    
    /**
     * Get Content-Type header
     * 
     * @return string|null The content type
     */
    public static function getContentType(): ?string
    {
        $type = sanitize_text_field(wp_unslash($_SERVER['CONTENT_TYPE'])) ?? sanitize_text_field(wp_unslash($_SERVER['HTTP_CONTENT_TYPE'])) ?? null;
        
        if (!$type) {
            return null;
        }
        
        // Extract just the mime type (before any ;charset=...)
        $parts = explode(';', $type);
        return sanitize_text_field(trim($parts[0]));
    }
    
    /**
     * Get the HTTP Host
     * 
     * @return string The host, validated
     */
    public static function getHost(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Validate host format
        if (!preg_match('/^[a-zA-Z0-9.-]+(:[0-9]+)?$/', $host)) {
            return parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';
        }
        
        return $host;
    }
    
    /**
     * Check if this is an HTTPS request
     * 
     * @return bool True if HTTPS
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the full request URL
     * 
     * @return string The full URL
     */
    public static function getFullUrl(): string
    {
        $scheme = self::isHttps() ? 'https' : 'http';
        $host = self::getHost();
        $uri = self::getRequestUri();
        
        return $scheme . '://' . $host . $uri;
    }
    
    /**
     * Get the User-Agent
     * 
     * @return string The user agent string
     */
    public static function getUserAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return sanitize_text_field(substr($ua, 0, 500)); // Limit length
    }
    
    /**
     * Get the Referer
     * 
     * @return string|null The referer URL
     */
    public static function getReferer(): ?string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        
        if (!$referer) {
            return null;
        }
        
        return esc_url_raw($referer);
    }
    
    /**
     * Check if this is an AJAX request
     * 
     * @return bool True if AJAX
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get the query string
     * 
     * @return string The query string
     */
    public static function getQueryString(): string
    {
        return isset($_SERVER['QUERY_STRING']) ? sanitize_text_field($_SERVER['QUERY_STRING']) : '';
    }
    
    /**
     * Get server protocol (HTTP/1.1, HTTP/2, etc.)
     * 
     * @return string The protocol
     */
    public static function getProtocol(): string
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        
        $allowed = ['HTTP/1.0', 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3'];
        return in_array($protocol, $allowed, true) ? $protocol : 'HTTP/1.1';
    }
    
    /**
     * Get the Authorization header
     * 
     * @return string|null The authorization header
     */
    public static function getAuthorization(): ?string
    {
        // Try standard header first
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        }
        
        // Apache fallback
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
        }
        
        // CGI fallback
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return sanitize_text_field($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        
        return null;
    }
    
    /**
     * Get Bearer token from Authorization header
     * 
     * @return string|null The bearer token
     */
    public static function getBearerToken(): ?string
    {
        $auth = self::getAuthorization();
        
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            return null;
        }
        
        return substr($auth, 7);
    }
}

