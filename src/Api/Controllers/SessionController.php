<?php
declare(strict_types=1);
/**
 * Session REST Controller
 * 
 * REST API endpoints for chat session management and history persistence.
 * 
 * @package SWC_Chatbot
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Models\ChatSession;
use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Services\RateLimiter;
use Quarksol\SmartChatbot\Services\SecurityMiddleware;

/**
 * Class SessionController
 */
class SessionController {

    const NAMESPACES = ['quark-agentflow-ai/v1', 'quark-agentflow-ai/v1'];

    /**
     * Register REST routes
     */
    public static function register(): void {
        foreach (self::NAMESPACES as $namespace) {
            register_rest_route($namespace, '/sessions', [
                'methods' => 'POST',
                'callback' => [self::class, 'createSession'],
                'permission_callback' => [self::class, 'canCreateSession'],
            ]);

            register_rest_route($namespace, '/sessions/(?P<session_id>[a-f0-9-]+)', [
                'methods' => 'GET',
                'callback' => [self::class, 'getSession'],
                'permission_callback' => [self::class, 'canAccessSession'],
            ]);

            register_rest_route($namespace, '/sessions/(?P<session_id>[a-f0-9-]+)/messages', [
                'methods' => 'GET',
                'callback' => [self::class, 'getMessages'],
                'permission_callback' => [self::class, 'canAccessSession'],
            ]);

            register_rest_route($namespace, '/sessions/(?P<session_id>[a-f0-9-]+)/messages', [
                'methods' => 'POST',
                'callback' => [self::class, 'addMessage'],
                'permission_callback' => [self::class, 'canAccessSession'],
            ]);

            register_rest_route($namespace, '/sessions/(?P<session_id>[a-f0-9-]+)', [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteSession'],
                'permission_callback' => [self::class, 'canDeleteSession'],
            ]);
        }
    }
    
    // =========================================================================
    // Permission Callbacks - SECURITY FIX: Prevent IDOR attacks
    // =========================================================================
    
    /**
     * Check if user can create a session (rate limited)
     */
    public static function canCreateSession(\WP_REST_Request $request): bool {
        $identifier = RateLimiter::getIdentifier($request);
        return RateLimiter::check('session', $identifier);
    }
    
    /**
     * Check if user can access a session
     * Allows: session owner, admin, or valid session token
     */
    public static function canAccessSession(\WP_REST_Request $request): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chat_sessions';
        $session_id = sanitize_text_field($request->get_param('session_id'));
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, session_id FROM {$table} WHERE session_id = %s",
            $session_id
        ));
        
        if (!$session) {
            return true; // Let callback handle 404
        }
        
        // Admin can access any session
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Logged-in user owns the session
        if ($session->user_id && is_user_logged_in() && (int)$session->user_id === get_current_user_id()) {
            return true;
        }
        
        // Anonymous session - validate via session token header or cookie
        if (!$session->user_id || (int)$session->user_id === 0) {
            return self::validateSessionToken($session_id, $request);
        }
        
        return false;
    }
    
    /**
     * Check if user can delete a session (stricter than read access)
     * Only allows: session owner or admin
     */
    public static function canDeleteSession(\WP_REST_Request $request): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chat_sessions';
        $session_id = sanitize_text_field($request->get_param('session_id'));
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE session_id = %s",
            $session_id
        ));
        
        if (!$session) {
            return true; // Let callback handle 404
        }
        
        // Admin can delete any session
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Logged-in user owns the session
        if ($session->user_id && is_user_logged_in() && (int)$session->user_id === get_current_user_id()) {
            return true;
        }
        
        // Anonymous users can delete their own sessions via token
        if (!$session->user_id || (int)$session->user_id === 0) {
            return self::validateSessionToken($session_id, $request);
        }
        
        return false;
    }
    
    /**
     * Validate session token for anonymous session access
     * Token can be passed via X-Session-Token header or swc_session_token cookie
     */
    private static function validateSessionToken(string $session_id, \WP_REST_Request $request): bool {
        return SecurityMiddleware::validateSessionToken($session_id, $request);
    }
    
    /**
     * Generate a session token for anonymous access validation
     */
    public static function generateSessionToken(string $session_id): string {
        return SecurityMiddleware::generateSessionToken($session_id);
    }

    /**
     * Create a new session
     */
    public static function createSession(\WP_REST_Request $request): \WP_REST_Response {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $agentId = (int) ($params['agent_id'] ?? $request->get_param('agent_id') ?? 0);
        if (!$agentId) {
            $defaultAgent = ChatAgent::getDefault();
            if ($defaultAgent) {
                $agentId = $defaultAgent->id;
            }
        }

        if (!$agentId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Agent is required',
            ], 400);
        }

        $visitorId = sanitize_text_field((string) ($params['visitor_id'] ?? $request->get_param('visitor_id') ?? ''));
        $pageUrl = sanitize_text_field((string) ($params['url'] ?? $request->get_param('url') ?? ''));

        $metadata = [
            'url' => $pageUrl,
            'user_agent' => class_exists('\Quarksol\SmartChatbot\Services\ServerInput')
                ? \Quarksol\SmartChatbot\Services\ServerInput::getUserAgent()
                : substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
            'ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        // Process timezone into a location proxy
        $timezone = sanitize_text_field((string) ($params['timezone'] ?? $request->get_param('timezone') ?? ''));
        if (!empty($timezone)) {
            $metadata['timezone'] = $timezone;
            $parts = explode('/', $timezone);
            if (count($parts) >= 2) {
                // e.g. "America/New_York" -> "New York"
                $city = str_replace('_', ' ', end($parts));
                $metadata['location'] = $city;
            } else {
                $metadata['location'] = $timezone;
            }
        }

        $userId = is_user_logged_in() ? get_current_user_id() : null;
        $session = ChatSession::findOrCreate($agentId, $userId, $visitorId, $metadata);

        // Generate session token for anonymous users
        $session_token = self::generateSessionToken($session->sessionId);

        $response = new \WP_REST_Response([
            'success' => true,
            'session_id' => $session->sessionId,
            'session_token' => $session_token, // Client should store this for future requests
            'session_token_version' => 'v2',
            'session' => $session->toArray(),
            'messages' => $session->getMessages(),
        ]);
        $identifier = RateLimiter::getIdentifier($request);
        return RateLimiter::addHeaders($response, 'session', $identifier);
    }

    /**
     * Get session details
     */
    public static function getSession(\WP_REST_Request $request): \WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));

        $session = ChatSession::findBySessionId($session_id);
        if (!$session) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        $sessionData = $session->toArray();
        $response = new \WP_REST_Response([
            'success' => true,
            'session' => [
                'id' => $sessionData['id'],
                'session_id' => $sessionData['session_id'],
                'agent_id' => $sessionData['agent_db_id'],
                'messages' => $sessionData['messages'],
                'context' => [],
                'metadata' => $sessionData['metadata'],
                'status' => $sessionData['status'],
                'started_at' => $sessionData['started_at'],
                'last_message_at' => $sessionData['last_message_at'],
            ]
        ]);
        self::maybeAttachSessionToken($request, $response, $session->sessionId, (int) ($session->userId ?? 0));
        return $response;
    }

    /**
     * Get session messages
     */
    public static function getMessages(\WP_REST_Request $request): \WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));

        $session = ChatSession::findBySessionId($session_id);
        if (!$session) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        $messages = $session->getMessages();
        $response = new \WP_REST_Response([
            'success' => true,
            'messages' => $messages,
        ]);
        self::maybeAttachSessionToken($request, $response, $session_id, (int) ($session->userId ?? 0));
        return $response;
    }

    /**
     * Add message to session
     */
    public static function addMessage(\WP_REST_Request $request): \WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));

        $session = ChatSession::findBySessionId($session_id);
        if (!$session) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        $role = sanitize_text_field($request->get_param('role') ?? 'user');
        $content = sanitize_textarea_field($request->get_param('content') ?? '');
        $type = sanitize_text_field($request->get_param('type') ?? 'text');

        if ($content === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Content is required'
            ], 400);
        }

        $extra = [
            'id' => uniqid('msg_', true),
            'type' => $type,
        ];

        $session->addMessage($role, $content, $extra);
        $session->save();

        $messages = $session->getMessages();
        $new_message = $messages[count($messages) - 1] ?? [
            'role' => $role,
            'content' => $content,
            'type' => $type,
            'timestamp' => current_time('mysql'),
        ];

        $response = new \WP_REST_Response([
            'success' => true,
            'message' => $new_message,
        ]);
        self::maybeAttachSessionToken($request, $response, $session_id, (int) ($session->userId ?? 0));
        return $response;
    }

    /**
     * Delete session (clear conversation)
     */
    public static function deleteSession(\WP_REST_Request $request): \WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));

        $session = ChatSession::findBySessionId($session_id);
        if (!$session) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        $session->archive();
        if (class_exists('\Quarksol\SmartChatbot\History\\ActionLog')) {
            \Quarksol\SmartChatbot\History\ActionLog::deleteForSession($session_id);
        }

        return new \WP_REST_Response([
            'success' => true,
        ]);
    }

    /**
     * Attach a refreshed session token for anonymous sessions.
     */
    private static function maybeAttachSessionToken(
        \WP_REST_Request $request,
        \WP_REST_Response $response,
        string $sessionId,
        int $userId
    ): void {
        if ($userId > 0 || is_user_logged_in()) {
            return;
        }

        $token = SecurityMiddleware::getSessionTokenFromRequest($request);
        if ($token && SecurityMiddleware::needsSessionTokenRefresh($token)) {
            $response->header('X-Session-Token', SecurityMiddleware::generateSessionToken($sessionId));
        }
    }
}
