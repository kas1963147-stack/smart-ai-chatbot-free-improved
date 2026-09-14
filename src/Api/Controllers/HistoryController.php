<?php
declare(strict_types=1);
/**
 * History REST Controller
 * 
 * REST API endpoints for viewing conversation history and action logs.
 * Admin-only access for viewing all sessions and their tool execution details.
 * 
 * @package Quarksol\SmartChatbot\Api\Controllers
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

use Quarksol\SmartChatbot\Models\ChatSession;
use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\History\ActionLog;
use Quarksol\SmartChatbot\Config\ChatbotConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * History Controller
 */
class HistoryController {
    
    /**
     * Register REST routes
     */
    public static function register(): void {
        self::registerRoutes('swc/v1');
        self::registerRoutes('quark-agentflow-ai/v1');
    }

    /**
     * Register routes for a namespace
     */
    private static function registerRoutes(string $namespace): void {
        // List all sessions with history
        register_rest_route($namespace, '/history/sessions', [
            'methods' => 'GET',
            'callback' => [self::class, 'listSessions'],
            'permission_callback' => [self::class, 'canViewHistory'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
                'agent_id' => [
                    'default' => null,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Get single session with messages and actions
        register_rest_route($namespace, '/history/sessions/(?P<session_id>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSession'],
            'permission_callback' => [self::class, 'canViewHistory'],
        ]);
        
        // Get actions for a session
        register_rest_route($namespace, '/history/sessions/(?P<session_id>[a-zA-Z0-9-]+)/actions', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSessionActions'],
            'permission_callback' => [self::class, 'canViewHistory'],
        ]);
        
        // Delete/archive a session
        register_rest_route($namespace, '/history/sessions/(?P<session_id>[a-zA-Z0-9-]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'deleteSession'],
            'permission_callback' => [self::class, 'canDeleteHistory'],
        ]);
        
        // Get action log statistics
        register_rest_route($namespace, '/history/stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'getStats'],
            'permission_callback' => [self::class, 'canViewHistory'],
            'args' => [
                'period' => [
                    'default' => '30d',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'agent_id' => [
                    'default' => null,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        // Get recent actions across all sessions
        register_rest_route($namespace, '/history/actions', [
            'methods' => 'GET',
            'callback' => [self::class, 'getRecentActions'],
            'permission_callback' => [self::class, 'canViewHistory'],
            'args' => [
                'limit' => [
                    'default' => 50,
                    'sanitize_callback' => 'absint',
                ],
                'agent_id' => [
                    'default' => null,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }
    
    /**
     * Check if user can view history (admin only)
     */
    public static function canViewHistory(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Check if user can delete history (admin only)
     */
    public static function canDeleteHistory(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * List sessions with pagination
     */
    public static function listSessions(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        
        $page = max(1, $request->get_param('page'));
        $perPage = min(100, max(1, $request->get_param('per_page')));
        $agentId = $request->get_param('agent_id');
        $search = $request->get_param('search');
        
        $tables = \SWC\Database\Schema::getTableNames();
        $table = $tables['sessions'];
        
        // Build query
        $where = ["status = 'active'"];
        $params = [];
        
        if ($agentId) {
            $where[] = "agent_db_id = %d";
            $params[] = $agentId;
        }
        
        if ($search) {
            $where[] = "messages LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$whereClause}";
        $total = empty($params) 
            ? (int) $wpdb->get_var($countSql)
            : (int) $wpdb->get_var($wpdb->prepare($countSql, ...$params));
        
        // Get sessions
        $sql = "SELECT * FROM {$table} WHERE {$whereClause} ORDER BY last_message_at DESC LIMIT %d OFFSET %d";
        $params[] = $perPage;
        $params[] = $offset;
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        
        $sessions = [];
        foreach ($rows ?: [] as $row) {
            $session = new ChatSession($row);
            $agent = $session->getAgent();
            
            // Get action count for this session
            $actionCount = self::getActionCountForSession($session->sessionId);
            
            $sessions[] = [
                'id' => $session->id,
                'session_id' => $session->sessionId,
                'agent_db_id' => $session->agentDbId,
                'agent_name' => $agent ? $agent->name : 'Unknown Agent',
                'user_id' => $session->userId,
                'visitor_id' => $session->visitorId,
                'message_count' => count($session->messages),
                'action_count' => $actionCount,
                'preview' => self::getSessionPreview($session),
                'started_at' => $session->startedAt,
                'last_message_at' => $session->lastMessageAt,
            ];
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'sessions' => $sessions,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }
    
    /**
     * Get single session with messages and embedded actions
     */
    public static function getSession(\WP_REST_Request $request): \WP_REST_Response {
        $sessionId = $request->get_param('session_id');
        
        $session = ChatSession::find($sessionId);
        if (!$session) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }
        
        $agent = $session->getAgent();
        
        // Get all actions for this session
        $actions = ActionLog::forSession($sessionId);
        $actionsByMessageId = [];
        $actionsByIndex = [];
        foreach ($actions as $action) {
            $actionArray = $action->toArray();
            if (!empty($action->messageId)) {
                $actionsByMessageId[$action->messageId][] = $actionArray;
            }
            if ($action->messageIndex !== null) {
                $actionsByIndex[$action->messageIndex][] = $actionArray;
            }
        }

        $messageIds = [];
        foreach ($session->messages as $message) {
            if (!empty($message['id'])) {
                $messageIds[] = $message['id'];
            }
        }
        $messageIndexes = array_keys($session->messages);

        $unlinkedActions = [];
        foreach ($actions as $action) {
            $actionArray = $action->toArray();
            if (!empty($action->messageId) && in_array($action->messageId, $messageIds, true)) {
                continue;
            }
            if ($action->messageIndex !== null && in_array($action->messageIndex, $messageIndexes, true)) {
                continue;
            }
            $unlinkedActions[] = $actionArray;
        }
        
        // Enrich messages with actions
        $messagesWithActions = [];
        foreach ($session->messages as $idx => $message) {
            $messageId = $message['id'] ?? null;
            $toolCalls = [];
            if ($messageId && isset($actionsByMessageId[$messageId])) {
                $toolCalls = $actionsByMessageId[$messageId];
            } elseif (isset($actionsByIndex[$idx])) {
                $toolCalls = $actionsByIndex[$idx];
            }

            $messagesWithActions[] = array_merge($message, [
                'index' => $idx,
                'tool_calls' => $toolCalls,
            ]);
        }
        // Check for any linked appointments
        global $wpdb;
        $appointment = null;
        
        // Check if the Appointment feature exists (Pro)
        if (class_exists('\Toolkits\AppointmentBooking\AppointmentTable') && \Toolkits\AppointmentBooking\AppointmentTable::tableExists()) {
            $appointmentTable = \Toolkits\AppointmentBooking\AppointmentTable::tableName();
            $appointmentRow = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$appointmentTable} WHERE conversation_id = %s ORDER BY id DESC LIMIT 1",
                $sessionId
            ), ARRAY_A);
            if ($appointmentRow) {
                $appointment = $appointmentRow;
            }
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'id' => $session->id,
                'session_id' => $session->sessionId,
                'agent_db_id' => $session->agentDbId,
                'agent_name' => $agent ? $agent->name : 'Unknown Agent',
                'user_id' => $session->userId,
                'visitor_id' => $session->visitorId,
                'status' => $session->status,
                'messages' => $messagesWithActions,
                'metadata' => $session->metadata,
                'unlinked_tool_calls' => $unlinkedActions,
                'started_at' => $session->startedAt,
                'last_message_at' => $session->lastMessageAt,
            ],
        ]);
    }
    
    /**
     * Get actions for a session
     */
    public static function getSessionActions(\WP_REST_Request $request): \WP_REST_Response {
        $sessionId = $request->get_param('session_id');
        
        $session = ChatSession::find($sessionId);
        if (!$session) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }
        
        $actions = ActionLog::forSession($sessionId);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'session_id' => $sessionId,
                'actions' => array_map(fn($a) => $a->toArray(), $actions),
            ],
        ]);
    }
    
    /**
     * Delete/archive a session
     */
    public static function deleteSession(\WP_REST_Request $request): \WP_REST_Response {
        $sessionId = $request->get_param('session_id');
        
        $session = ChatSession::find($sessionId);
        if (!$session) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }
        
        // Archive the session
        $session->archive();
        
        // Optionally delete action logs
        ActionLog::deleteForSession($sessionId);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Session archived successfully',
        ]);
    }
    
    /**
     * Get action log statistics with caching
     */
    public static function getStats(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        $agentId = $request->get_param('agent_id');
        
        // Check cache (2 minute cache for stats)
        $cacheKey = 'swc_history_stats_' . md5($period . '_' . ($agentId ?? 'all'));
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => $cached,
            ]);
        }
        
        $toolStats = ActionLog::getToolStats($period, $agentId);
        
        // Calculate totals
        $totalCalls = array_sum(array_column($toolStats, 'call_count'));
        $totalSuccess = array_sum(array_column($toolStats, 'success_count'));
        $totalFailures = array_sum(array_column($toolStats, 'failure_count'));
        
        $data = [
            'period' => $period,
            'summary' => [
                'total_tool_calls' => $totalCalls,
                'successful_calls' => $totalSuccess,
                'failed_calls' => $totalFailures,
                'success_rate' => $totalCalls > 0 ? round(($totalSuccess / $totalCalls) * 100, 2) : 0,
            ],
            'tools' => $toolStats,
        ];
        
        // Cache for 2 minutes
        set_transient($cacheKey, $data, 2 * MINUTE_IN_SECONDS);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ]);
    }
    
    /**
     * Get recent actions across all sessions
     */
    public static function getRecentActions(\WP_REST_Request $request): \WP_REST_Response {
        $limit = min(100, max(1, $request->get_param('limit')));
        $agentId = $request->get_param('agent_id');
        
        $actions = ActionLog::getRecent($limit, $agentId);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'actions' => array_map(fn($a) => $a->toArray(), $actions),
            ],
        ]);
    }
    
    /**
     * Get action count for a session
     */
    private static function getActionCountForSession(string $sessionId): int {
        global $wpdb;
        $table = ActionLog::getTableName();
        
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE session_id = %s", $sessionId)
        );
    }
    
    /**
     * Get preview text for a session
     */
    private static function getSessionPreview(ChatSession $session): string {
        if (empty($session->messages)) {
            return 'Empty conversation';
        }
        
        // Get first user message
        foreach ($session->messages as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $content = $msg['content'] ?? '';
                return strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content;
            }
        }
        
        return 'Conversation started';
    }
}

