<?php
declare(strict_types=1);
/**
 * Session Validator Service
 * 
 * Provides server-side session validation and visitor management.
 * Uses WordPress transients instead of PHP sessions for stateless operation.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Config\ChatbotConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session Validator
 * 
 * Handles session creation, validation, and visitor identification
 * in a secure, stateless manner using WordPress transients.
 */
class SessionValidator
{
    /** Transient prefix for sessions */
    const SESSION_PREFIX = 'swc_session_';
    
    /** Transient prefix for visitors */
    const VISITOR_PREFIX = 'swc_visitor_';
    
    /**
     * Get or create a visitor ID from cookie
     */
    public static function getOrCreateVisitorId(): string
    {
        $cookieName = ChatbotConfig::VISITOR_COOKIE_NAME;
        
        // Check for existing cookie
        if (isset($_COOKIE[$cookieName])) {
            $visitorId = sanitize_key($_COOKIE[$cookieName]);
            if (self::isValidUUID($visitorId)) {
                return $visitorId;
            }
        }
        
        // Generate new visitor ID
        $visitorId = wp_generate_uuid4();
        
        // Set cookie (secure, httponly)
        if (!headers_sent()) {
            $cookieLifetime = ChatbotConfig::VISITOR_COOKIE_LIFETIME;
            setcookie(
                $cookieName,
                $visitorId,
                [
                    'expires' => time() + $cookieLifetime,
                    'path' => COOKIEPATH ?: '/',
                    'domain' => COOKIE_DOMAIN ?: '',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
        
        return $visitorId;
    }
    
    /**
     * Create a new session for a visitor
     */
    public static function createSession(string $visitorId): string
    {
        $sessionId = wp_generate_uuid4();
        $sessionKey = self::SESSION_PREFIX . $sessionId;
        
        $sessionData = [
            'visitor_id' => $visitorId,
            'created_at' => time(),
            'last_activity' => time(),
            'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
        ];
        
        // Store with timeout
        $timeout = ChatbotConfig::SESSION_TIMEOUT;
        set_transient($sessionKey, $sessionData, $timeout);
        
        return $sessionId;
    }
    
    /**
     * Validate a session belongs to a visitor
     */
    public static function validateSession(string $sessionId, string $visitorId): bool
    {
        if (empty($sessionId) || empty($visitorId)) {
            return false;
        }
        
        $sessionKey = self::SESSION_PREFIX . $sessionId;
        $sessionData = get_transient($sessionKey);
        
        if (!$sessionData) {
            return false;
        }
        
        // Check visitor ownership
        if ($sessionData['visitor_id'] !== $visitorId) {
            Logger::warning('Session ownership mismatch', [
                'session_id' => $sessionId,
                'expected_visitor' => $sessionData['visitor_id'],
                'actual_visitor' => $visitorId,
            ]);
            return false;
        }
        
        // Update last activity
        $sessionData['last_activity'] = time();
        $timeout = ChatbotConfig::SESSION_TIMEOUT;
        set_transient($sessionKey, $sessionData, $timeout);
        
        return true;
    }
    
    /**
     * Get session data
     */
    public static function getSession(string $sessionId): ?array
    {
        $sessionKey = self::SESSION_PREFIX . $sessionId;
        $sessionData = get_transient($sessionKey);
        
        return $sessionData ?: null;
    }
    
    /**
     * Destroy a session
     */
    public static function destroySession(string $sessionId): void
    {
        $sessionKey = self::SESSION_PREFIX . $sessionId;
        delete_transient($sessionKey);
    }
    
    /**
     * Store data in visitor transient (for guest wishlist, etc.)
     */
    public static function setVisitorData(string $visitorId, string $key, $value): void
    {
        $transientKey = self::VISITOR_PREFIX . $visitorId . '_' . $key;
        $timeout = ChatbotConfig::VISITOR_COOKIE_LIFETIME;
        set_transient($transientKey, $value, $timeout);
    }
    
    /**
     * Get data from visitor transient
     */
    public static function getVisitorData(string $visitorId, string $key, $default = null)
    {
        $transientKey = self::VISITOR_PREFIX . $visitorId . '_' . $key;
        $value = get_transient($transientKey);
        return $value !== false ? $value : $default;
    }
    
    /**
     * Clear visitor data
     */
    public static function clearVisitorData(string $visitorId, string $key): void
    {
        $transientKey = self::VISITOR_PREFIX . $visitorId . '_' . $key;
        delete_transient($transientKey);
    }
    
    /**
     * Validate UUID format
     */
    protected static function isValidUUID(string $uuid): bool
    {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $uuid);
    }
}
