<?php
declare(strict_types=1);
/**
 * Security Middleware Service
 * 
 * Centralized security helpers for session validation, token generation,
 * and input sanitization across REST API controllers.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Middleware
 * 
 * Provides reusable security functions for REST API endpoints.
 */
class SecurityMiddleware
{
    /** Session token cookie name */
    const SESSION_TOKEN_COOKIE = 'swc_session_token';
    
    /** Session token header name */
    const SESSION_TOKEN_HEADER = 'X-Session-Token';

    /** Session token version prefix */
    const SESSION_TOKEN_PREFIX = 'v2:';
    
    /** Visitor ID cookie name */
    const VISITOR_ID_COOKIE = 'swc_visitor_id';
    
    /**
     * Validate session ownership
     * 
     * @param string $sessionId The session ID to validate
     * @param \WP_REST_Request $request The incoming request
     * @return bool True if user can access this session
     */
    public static function validateSessionOwnership(string $sessionId, \WP_REST_Request $request): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chat_sessions';
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, session_id FROM {$table} WHERE session_id = %s",
            $sessionId
        ));
        
        if (!$session) {
            return false;
        }
        
        // Admin can access any session
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Logged-in user owns the session
        if ($session->user_id && is_user_logged_in() && (int)$session->user_id === get_current_user_id()) {
            return true;
        }
        
        // Anonymous session - validate via session token
        if (!$session->user_id || (int)$session->user_id === 0) {
            return self::validateSessionToken($sessionId, $request);
        }
        
        return false;
    }
    
    /**
     * Validate session token for anonymous session access
     * Token can be passed via X-Session-Token header or swc_session_token cookie
     */
    public static function validateSessionToken(string $sessionId, \WP_REST_Request $request): bool {
        $token = self::getSessionTokenFromRequest($request);
        if (empty($token)) {
            return false;
        }

        $expected = self::generateSessionToken($sessionId);
        if (hash_equals($expected, $token)) {
            return true;
        }

        $legacy = self::generateLegacySessionToken($sessionId);
        if ($legacy && hash_equals($legacy, $token)) {
            return true;
        }

        return false;
    }
    
    /**
     * Generate a session token for anonymous access validation
     */
    public static function generateSessionToken(string $sessionId): string {
        $secret = wp_salt('auth') . 'swc_session_token_v2';
        return self::SESSION_TOKEN_PREFIX . hash_hmac('sha256', $sessionId, $secret);
    }

    /**
     * Determine if a session token should be refreshed to the latest format.
     */
    public static function needsSessionTokenRefresh(string $token): bool {
        return !str_starts_with($token, self::SESSION_TOKEN_PREFIX);
    }

    /**
     * Extract session token from request headers/cookies.
     */
    public static function getSessionTokenFromRequest(\WP_REST_Request $request): string {
        $token = $request->get_header(self::SESSION_TOKEN_HEADER);
        if (empty($token)) {
            $token = $_COOKIE[self::SESSION_TOKEN_COOKIE] ?? '';
        }

        return $token ?: '';
    }

    /**
     * Generate legacy v1 session token using AUTH_KEY (if defined).
     */
    private static function generateLegacySessionToken(string $sessionId): ?string {
        if (!defined('AUTH_KEY')) {
            return null;
        }

        return hash_hmac('sha256', $sessionId, AUTH_KEY);
    }
    
    /**
     * Generate a visitor ID for anonymous users
     */
    public static function generateVisitorId(): string {
        $visitorId = wp_generate_uuid4();
        
        // Set cookie with 30-day expiry
        if (!headers_sent()) {
            setcookie(
                self::VISITOR_ID_COOKIE,
                $visitorId,
                [
                    'expires' => time() + (30 * DAY_IN_SECONDS),
                    'path' => '/',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
        
        return $visitorId;
    }
    
    /**
     * Get or create visitor ID
     */
    public static function getVisitorId(): string {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        $visitorId = $_COOKIE[self::VISITOR_ID_COOKIE] ?? '';
        
        if (empty($visitorId)) {
            $visitorId = self::generateVisitorId();
        }
        
        return $visitorId;
    }
    
    /**
     * Validate agent ID exists
     */
    public static function validateAgentId(int $agentId): bool {
        if ($agentId <= 0) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_agents';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $agentId
        ));
        
        return (int)$exists > 0;
    }
    
    /**
     * Validate message length
     * 
     * @param string $message The message to validate
     * @param int $maxLength Maximum allowed length (default 10000)
     * @return bool True if valid length
     */
    public static function validateMessageLength(string $message, int $maxLength = 10000): bool {
        return mb_strlen($message) <= $maxLength;
    }
    
    /**
     * Validate UUID format
     * 
     * @param string $uuid The string to validate
     * @return bool True if valid UUID v4 format
     */
    public static function validateUUID(string $uuid): bool {
        return (bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $uuid);
    }
    
    /**
     * Sanitize and validate table name
     * Prevents SQL injection in dynamic table name usage
     */
    public static function validateTableName(string $tableName): bool {
        return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $tableName);
    }
    
    /**
     * Create a rate-limited permission callback
     * 
     * @param string $action The rate limit action type
     * @return callable Permission callback function
     */
    public static function rateLimitedCallback(string $action): callable {
        return function(\WP_REST_Request $request) use ($action): bool {
            $identifier = RateLimiter::getIdentifier($request);
            return RateLimiter::check($action, $identifier);
        };
    }
    
    /**
     * Create a session-validated permission callback
     */
    public static function sessionValidatedCallback(): callable {
        return function(\WP_REST_Request $request): bool {
            $sessionId = sanitize_text_field($request->get_param('session_id'));
            
            if (empty($sessionId)) {
                return false;
            }
            
            return self::validateSessionOwnership($sessionId, $request);
        };
    }
}
