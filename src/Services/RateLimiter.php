<?php
declare(strict_types=1);
/**
 * Rate Limiter Service
 * 
 * Prevents API abuse with per-user/IP rate limiting.
 * Uses WordPress transients for storage.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limiter
 */
class RateLimiter {
    
    /**
     * Default rate limits per action type (fallback values)
     * [requests per window, window in seconds]
     */
    const DEFAULT_LIMITS = [
        'chat' => ['requests' => 120, 'window' => 60],      // 120 messages/minute (fallback)
        'session' => ['requests' => 10, 'window' => 60],    // 10 sessions/minute
        'rating' => ['requests' => 60, 'window' => 60],     // 60 ratings/minute
        'resolve' => ['requests' => 30, 'window' => 60],    // 30 resolves/minute
        'export' => ['requests' => 5, 'window' => 60],      // 5 exports/minute
        'import' => ['requests' => 3, 'window' => 60],      // 3 imports/minute
        'workspace' => ['requests' => 30, 'window' => 60],  // 30 workspace ops/minute
    ];

    /**
     * Backward-compat alias for DEFAULT_LIMITS
     */
    const LIMITS = self::DEFAULT_LIMITS;
    
    /**
     * Runtime overrides (set per-request from widget config)
     * @var array<string, array{requests: int, window: int}>
     */
    protected static array $overrides = [];
    
    /**
     * Cached widget rate limit (loaded once per request)
     * @var array|null|false  null = not loaded yet, false = no widget config, array = limit config
     */
    protected static $widgetChatLimit = null;
    
    /**
     * Set a runtime rate limit override for an action.
     * Call this before check() to apply a dynamic limit from widget config.
     */
    public static function setOverride(string $action, int $requests, int $window = 60): void {
        self::$overrides[$action] = ['requests' => $requests, 'window' => $window];
    }
    
    /**
     * Check if action is rate limited
     * 
     * @param string $action Action type
     * @param string $identifier User/IP identifier
     * @return bool True if allowed, false if rate limited
     */
    public static function check(string $action, string $identifier): bool {
        $limit = self::getLimit($action);
        
        if (!$limit) {
            return true; // Unknown action, allow
        }
        
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);
        
        if ($data === false) {
            // First request in window
            $data = [
                'count' => 1,
                'reset' => time() + $limit['window']
            ];
            set_transient($key, $data, $limit['window']);
            return true;
        }
        
        // Check if window has expired
        if (time() > $data['reset']) {
            // Start new window
            $data = [
                'count' => 1,
                'reset' => time() + $limit['window']
            ];
            set_transient($key, $data, $limit['window']);
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $limit['requests']) {
            return false;
        }
        
        // Increment counter
        $data['count']++;
        set_transient($key, $data, $limit['window']);
        
        return true;
    }
    
    /**
     * Get remaining requests for action
     */
    public static function getRemainingRequests(string $action, string $identifier): int {
        $limit = self::getLimit($action);
        
        if (!$limit) {
            return 999; // Unknown action
        }
        
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);
        
        if ($data === false) {
            return $limit['requests'];
        }
        
        // Check if window has expired
        if (time() > $data['reset']) {
            return $limit['requests'];
        }
        
        return max(0, $limit['requests'] - $data['count']);
    }
    
    /**
     * Get seconds until rate limit resets
     */
    public static function getRetryAfter(string $action, string $identifier): int {
        $key = self::getKey($action, $identifier);
        $data = get_transient($key);
        
        if ($data === false) {
            return 0;
        }
        
        return max(0, $data['reset'] - time());
    }
    
    /**
     * Get identifier from request
     * Uses user ID for logged in users, IP for guests
     */
    public static function getIdentifier(?\WP_REST_Request $request = null): string {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // Get IP address
        $ip = self::getClientIP();
        return 'ip_' . md5($ip);
    }
    
    /**
     * Get client IP address securely
     * 
     * Only trusts forwarded headers when request comes from a trusted proxy.
     * This prevents IP spoofing attacks that could bypass rate limiting.
     */
    protected static function getClientIP(): string {
        /**
         * Filter to define trusted proxy IPs.
         * Only when REMOTE_ADDR matches one of these will we trust forwarded headers.
         * 
         * Example: add_filter('swc_trusted_proxies', function($proxies) {
         *     return array_merge($proxies, ['10.0.0.1', '192.168.1.1']);
         * });
         */
        $trustedProxies = apply_filters('swc_trusted_proxies', []);
        
        // Always start with REMOTE_ADDR as the most reliable source
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $remoteAddr = filter_var($remoteAddr, FILTER_VALIDATE_IP) ?: '127.0.0.1';
        
        // Only trust forwarded headers if request comes from a trusted proxy
        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            // Order matters - most specific first
            $forwardedHeaders = [
                'HTTP_CF_CONNECTING_IP',     // Cloudflare
                'HTTP_X_REAL_IP',            // Nginx
                'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            ];
            
            foreach ($forwardedHeaders as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                    
                    // Handle comma-separated IPs (X-Forwarded-For: client, proxy1, proxy2)
                    if (strpos($ip, ',') !== false) {
                        $ips = explode(',', $ip);
                        $ip = trim($ips[0]); // First IP is the original client
                    }
                    
                    // Validate and reject private/reserved ranges for client IPs
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        // Fall back to REMOTE_ADDR (most secure default)
        return $remoteAddr;
    }
    
    /**
     * Get limit config for action
     * 
     * Priority: runtime overrides > widget engagement settings > hardcoded defaults
     */
    protected static function getLimit(string $action): ?array {
        // 1. Check runtime overrides first
        if (isset(self::$overrides[$action])) {
            return self::$overrides[$action];
        }
        
        // 2. For 'chat' action, check widget engagement settings
        if ($action === 'chat') {
            $widgetLimit = self::getWidgetChatLimit();
            if ($widgetLimit !== false) {
                return $widgetLimit;
            }
        }
        
        // 3. Fall back to hardcoded defaults
        return self::DEFAULT_LIMITS[$action] ?? null;
    }
    
    /**
     * Load the chat rate limit from active widget engagement settings.
     * 
     * Reads rate_limiting_enabled and rate_limit_per_minute from the
     * first active widget's engagement config. Cached per-request.
     * 
     * @return array|false  Limit config array, or false if not configured
     */
    protected static function getWidgetChatLimit() {
        // Return cached result if already loaded
        if (self::$widgetChatLimit !== null) {
            return self::$widgetChatLimit;
        }
        
        // Default: not configured
        self::$widgetChatLimit = false;

        try {
            if (!class_exists('\Quarksol\SmartChatbot\Models\\ChatWidget')) {
                return self::$widgetChatLimit;
            }
            
            $activeWidgets = \Quarksol\SmartChatbot\Models\ChatWidget::findActive();
            
            foreach ($activeWidgets as $widget) {
                $engagement = $widget->engagement ?? [];
                
                // Check if this widget has rate limiting enabled
                if (!empty($engagement['rate_limiting_enabled'])) {
                    $perMinute = (int) ($engagement['rate_limit_per_minute'] ?? 10);
                    // Clamp to reasonable range (1-999)
                    $perMinute = max(1, min(999, $perMinute));
                    
                    self::$widgetChatLimit = [
                        'requests' => $perMinute,
                        'window' => 60,
                    ];
                    return self::$widgetChatLimit;
                }
            }
        } catch (\Throwable $e) {
            // Silent fail — fall back to defaults
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('RateLimiter: Failed to load widget config', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return self::$widgetChatLimit;
    }
    
    /**
     * Reset the cached widget limit (useful for testing)
     */
    public static function resetCache(): void {
        self::$widgetChatLimit = null;
        self::$overrides = [];
    }
    
    /**
     * Generate transient key
     */
    protected static function getKey(string $action, string $identifier): string {
        return 'sl_rate_' . $action . '_' . substr(md5($identifier), 0, 12);
    }
    
    /**
     * Add rate limit headers to response
     */
    public static function addHeaders(\WP_REST_Response $response, string $action, string $identifier): \WP_REST_Response {
        $limit = self::getLimit($action);
        
        if (!$limit) {
            return $response;
        }
        
        $remaining = self::getRemainingRequests($action, $identifier);
        $resetAfter = self::getRetryAfter($action, $identifier);
        
        $response->header('X-RateLimit-Limit', $limit['requests']);
        $response->header('X-RateLimit-Remaining', $remaining);
        $response->header('X-RateLimit-Reset', time() + $resetAfter);
        
        return $response;
    }
    
    /**
     * Create rate limit exceeded response
     */
    public static function limitExceededResponse(string $action, string $identifier): \WP_REST_Response {
        $retryAfter = self::getRetryAfter($action, $identifier);
        
        $response = new \WP_REST_Response([
            'error' => 'Rate limit exceeded',
            'code' => 'rate_limit_exceeded',
            'retry_after' => $retryAfter,
        ], 429);
        
        $response->header('Retry-After', $retryAfter);
        
        return self::addHeaders($response, $action, $identifier);
    }
    
    /**
     * Middleware to check rate limit before processing
     */
    public static function middleware(string $action, callable $handler): callable {
        return function(\WP_REST_Request $request) use ($action, $handler) {
            $identifier = self::getIdentifier($request);
            
            if (!self::check($action, $identifier)) {
                return self::limitExceededResponse($action, $identifier);
            }
            
            $response = $handler($request);
            
            // Add rate limit headers if WP_REST_Response
            if ($response instanceof \WP_REST_Response) {
                return self::addHeaders($response, $action, $identifier);
            }
            
            return $response;
        };
    }
    
    /**
     * Clear rate limit for identifier
     * Useful for testing or admin override
     */
    public static function clear(string $action, string $identifier): void {
        $key = self::getKey($action, $identifier);
        delete_transient($key);
    }
    
    /**
     * Clear all rate limits for an identifier
     */
    public static function clearAll(string $identifier): void {
        foreach (array_keys(self::LIMITS) as $action) {
            self::clear($action, $identifier);
        }
    }
}
