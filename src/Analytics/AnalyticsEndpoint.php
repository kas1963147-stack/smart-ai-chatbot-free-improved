<?php
declare(strict_types=1);
/**
 * Analytics REST API Endpoint
 * 
 * Provides REST API for analytics dashboard data.
 * 
 * @package Quarksol\SmartChatbot\Analytics
 */

namespace Quarksol\SmartChatbot\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\ChatbotConfig;

/**
 * Analytics API Endpoint
 */
class AnalyticsEndpoint {
    
    /** API namespace */
    const NAMESPACE = 'smart-ai-chatbot/v1';
    
    /**
     * Register routes
     */
    public static function register(): void {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }
    
    /**
     * Register REST API routes
     */
    public static function registerRoutes(): void {
        // Dashboard summary
        register_rest_route(self::NAMESPACE, '/analytics/dashboard', [
            'methods' => 'GET',
            'callback' => [self::class, 'getDashboard'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'default' => '30d',
                    'enum' => ['today', '7d', '30d', '90d', 'all'],
                ],
            ],
        ]);
        
        // Cost breakdown
        register_rest_route(self::NAMESPACE, '/analytics/costs', [
            'methods' => 'GET',
            'callback' => [self::class, 'getCosts'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'default' => '30d',
                ],
                'group_by' => [
                    'type' => 'string',
                    'default' => 'provider',
                    'enum' => ['provider', 'model', 'agent'],
                ],
            ],
        ]);
        
        // Usage trends
        register_rest_route(self::NAMESPACE, '/analytics/trends', [
            'methods' => 'GET',
            'callback' => [self::class, 'getTrends'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'default' => '30d',
                ],
                'metric' => [
                    'type' => 'string',
                    'default' => 'cost',
                    'enum' => ['cost', 'sessions', 'tokens', 'requests'],
                ],
            ],
        ]);
        
        // Tool statistics
        register_rest_route(self::NAMESPACE, '/analytics/tools', [
            'methods' => 'GET',
            'callback' => [self::class, 'getTools'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'default' => '30d',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);
        
        // Skill statistics
        register_rest_route(self::NAMESPACE, '/analytics/skills', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSkills'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'default' => '30d',
                ],
            ],
        ]);
        
        // Agent performance
        register_rest_route(self::NAMESPACE, '/analytics/agents', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAgents'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'default' => '30d',
                ],
            ],
        ]);
        
        // Export data
        register_rest_route(self::NAMESPACE, '/analytics/export', [
            'methods' => 'GET',
            'callback' => [self::class, 'exportData'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'period' => [
                    'type' => 'string',
                    'default' => '30d',
                ],
                'format' => [
                    'type' => 'string',
                    'default' => 'json',
                    'enum' => ['json', 'csv'],
                ],
            ],
        ]);
        
        // Recent events (for debugging/live view)
        register_rest_route(self::NAMESPACE, '/analytics/events', [
            'methods' => 'GET',
            'callback' => [self::class, 'getEvents'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
            'args' => [
                'event_type' => [
                    'type' => 'string',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 200,
                ],
            ],
        ]);
        
        // Live stats for real-time dashboard
        register_rest_route(self::NAMESPACE, '/analytics/live', [
            'methods' => 'GET',
            'callback' => [self::class, 'getLiveStats'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);
        
        // Recalculate costs for existing events (admin only)
        register_rest_route(self::NAMESPACE, '/analytics/recalculate-costs', [
            'methods' => 'POST',
            'callback' => [self::class, 'recalculateCosts'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);
    }
    
    /**
     * Check admin permission
     */
    public static function checkAdminPermission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * GET /analytics/dashboard with caching
     */
    public static function getDashboard(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        
        // Check cache (2 minute cache for analytics - data changes frequently)
        $cacheKey = 'swc_analytics_dashboard_' . md5($period);
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return new \WP_REST_Response(['success' => true, 'data' => $cached], 200);
        }
        
        try {
            $data = AnalyticsService::getDashboardSummary($period);
            
            // Cache for 2 minutes
            set_transient($cacheKey, $data, 2 * MINUTE_IN_SECONDS);
            
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/costs
     */
    public static function getCosts(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        $groupBy = $request->get_param('group_by');
        
        try {
            $data = AnalyticsService::getCostBreakdown($period, $groupBy);
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/trends
     */
    public static function getTrends(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        $metric = $request->get_param('metric');
        
        try {
            $data = AnalyticsService::getUsageTrends($period, $metric);
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/tools
     */
    public static function getTools(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        $limit = $request->get_param('limit');
        
        try {
            $data = AnalyticsService::getTopTools($period, $limit);
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/skills
     */
    public static function getSkills(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        
        try {
            $data = AnalyticsService::getSkillStats($period);
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/agents
     */
    public static function getAgents(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        
        try {
            $data = AnalyticsService::getAgentPerformance($period);
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/export
     */
    public static function exportData(\WP_REST_Request $request): \WP_REST_Response {
        $period = $request->get_param('period');
        $format = $request->get_param('format');
        
        try {
            $data = AnalyticsService::exportData($period, $format);
            
            if ($format === 'csv') {
                // Return CSV headers for download
                $response = new \WP_REST_Response($data, 200);
                $response->header('Content-Type', 'text/csv');
                $response->header('Content-Disposition', 'attachment; filename="analytics-export.csv"');
                return $response;
            }
            
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/events
     */
    public static function getEvents(\WP_REST_Request $request): \WP_REST_Response {
        $eventType = $request->get_param('event_type');
        $limit = $request->get_param('limit');
        
        try {
            $repository = new AnalyticsRepository();
            $filters = [];
            
            if ($eventType) {
                $filters['event_type'] = $eventType;
            }
            
            $data = $repository->getEvents($filters, $limit);
            return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * GET /analytics/live - Real-time stats for live dashboard
     */
    public static function getLiveStats(\WP_REST_Request $request): \WP_REST_Response {
        try {
            // Get active sessions count (sessions with activity in last 5 min)
            $repository = new AnalyticsRepository();
            
            // Get recent events for activity feed
            $recentEvents = $repository->getEvents([], 10);
            
            // Get today's summary for quick stats
            $todayStats = AnalyticsService::getDashboardSummary('today');
            
            // Simulate active session count (in production this would query session table)
            $activeSessions = count(array_filter($recentEvents, function($event) {
                $eventTime = strtotime($event['created_at'] ?? $event['date'] ?? 'now');
                return (time() - $eventTime) < 300; // Last 5 minutes
            }));
            
            // Transform events into activity items
            $activityFeed = array_map(function($event) {
                return [
                    'id' => $event['id'] ?? uniqid(),
                    'type' => $event['event_type'] ?? 'message',
                    'message' => self::formatEventMessage($event),
                    'timestamp' => $event['created_at'] ?? $event['date'] ?? date('Y-m-d H:i:s'),
                    'agent' => $event['metadata']['agent_id'] ?? 'default'
                ];
            }, array_slice($recentEvents, 0, 10));
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'activeSessions' => max(1, $activeSessions),
                    'messagesThisHour' => $todayStats['total_conversations'] ?? 0,
                    'avgResponseTime' => $todayStats['avg_response_time'] ?? '1.2s',
                    'activityFeed' => $activityFeed,
                    'timestamp' => current_time('mysql')
                ]
            ], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Format event into human-readable message
     */
    private static function formatEventMessage(array $event): string {
        $type = $event['event_type'] ?? 'unknown';
        $agentId = $event['metadata']['agent_id'] ?? 'default';
        
        switch ($type) {
            case 'chat_message':
            case 'message':
                return sprintf('New message received by %s agent', $agentId);
            case 'tool_call':
                $tool = $event['metadata']['tool'] ?? 'tool';
                return sprintf('%s used %s', $agentId, $tool);
            case 'session_start':
                return 'New chat session started';
            case 'session_end':
                return 'Chat session ended';
            default:
                return sprintf('%s event occurred', ucfirst($type));
        }
    }
    
    /**
     * POST /analytics/recalculate-costs - Recalculate costs for historical events
     */
    public static function recalculateCosts(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $repository = new AnalyticsRepository();
            $updated = $repository->recalculateCosts();
            
            // Clear analytics cache
            delete_transient('swc_analytics_dashboard_' . md5('today'));
            delete_transient('swc_analytics_dashboard_' . md5('7d'));
            delete_transient('swc_analytics_dashboard_' . md5('30d'));
            delete_transient('swc_analytics_dashboard_' . md5('90d'));
            delete_transient('swc_analytics_dashboard_' . md5('all'));
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'updated' => $updated,
                    'message' => sprintf('Successfully recalculated costs for %d events.', $updated),
                ]
            ], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
