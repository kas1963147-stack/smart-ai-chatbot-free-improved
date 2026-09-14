<?php
declare(strict_types=1);
/**
 * Chat Widget REST Controller
 *
 * REST API endpoints for managing chat widgets, assignments, and frontend resolution.
 * Supports both individual agents and teams (agent groups).
 *
 * @package SWC\Api\Controllers
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

use Quarksol\SmartChatbot\Models\ChatWidget;
use Quarksol\SmartChatbot\Models\AgentGroup;
use Quarksol\SmartChatbot\Models\ChatAgent;
use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class ChatWidgetController
{
    public static function register(): void
    {
        // Widget CRUD
        register_rest_route('quark-agentflow-ai/v1', '/chat-widgets', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getWidgets'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createWidget'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/chat-widgets/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getWidget'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateWidget'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteWidget'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
        ]);

        // Assignments
        register_rest_route('quark-agentflow-ai/v1', '/chat-widgets/(?P<id>\d+)/assignments', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getAssignments'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createAssignment'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/chat-widgets/assignments/(?P<id>\d+)', [
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteAssignment'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
        ]);

        // Frontend resolution (public - no auth required)
        register_rest_route('quark-agentflow-ai/v1', '/resolve', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'resolveWidget'],
                'permission_callback' => '__return_true', // Public endpoint
            ],
        ]);

        // Widget configuration for frontend
        register_rest_route('quark-agentflow-ai/v1', '/widget-config', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getWidgetConfig'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get all widgets with optional pagination
     */
    public static function getWidgets(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = (int) ($request->get_param('page') ?: 1);
        $perPage = min(100, (int) ($request->get_param('per_page') ?: 50));

        $widgets = ChatWidget::all($page, $perPage);
        $total = ChatWidget::count();

        return new \WP_REST_Response([
            'success' => true,
            'widgets' => array_map(fn($widget) => $widget->toArray(), $widgets),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public static function getWidget(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $widget = ChatWidget::find($id);
        if (!$widget) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Widget not found',
            ], 404);
        }

        $result = $widget->toArray();
        
        // Include resolved agent/team info
        if ($widget->usesTeam()) {
            $team = $widget->getTeam();
            if ($team) {
                $result['team_info'] = $team->toArray();
            }
        } else {
            $agent = $widget->getAgent();
            if ($agent) {
                $result['agent_info'] = $agent->toArray();
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'widget' => $result,
        ]);
    }

    public static function createWidget(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $name = sanitize_text_field($data['name'] ?? '');
        $displayName = sanitize_text_field($data['display_name'] ?? '');

        // Auto-sync: use name as display_name if not provided separately
        if (empty($displayName)) {
            $displayName = $name;
        }
        if (empty($name)) {
            $name = sanitize_title($displayName ?: 'chat_widget');
        }

        if (empty($displayName)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Name is required',
            ], 400);
        }

        if (ChatWidget::findByName($name)) {
            // Auto-generate unique name
            $i = 2;
            while (ChatWidget::findByName($name . '_' . $i)) {
                $i++;
            }
            $name = $name . '_' . $i;
        }

        $widget = new ChatWidget();
        $widget->name = $name;
        $widget->displayName = $displayName;
        $widget->description = sanitize_textarea_field($data['description'] ?? '');
        $widget->appearance = self::sanitizeRecursive($data['appearance'] ?? []);
        $widget->behavior = self::sanitizeRecursive($data['behavior'] ?? []);
        $widget->triggers = self::sanitizeRecursive($data['triggers'] ?? []);
        $widget->display = self::sanitizeRecursive($data['display'] ?? []);
        $widget->engagement = self::sanitizeRecursive($data['engagement'] ?? []);
        $widget->agentId = sanitize_text_field($data['agent_id'] ?? '');
        $widget->isActive = !empty($data['is_active']);

        if (!$widget->save()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create widget',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'widget' => $widget->toArray(),
        ], 201);
    }

    public static function updateWidget(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $widget = ChatWidget::find($id);
        if (!$widget) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Widget not found',
            ], 404);
        }

        $data = $request->get_json_params();
        
        if (isset($data['name'])) {
            $widget->name = sanitize_text_field($data['name']);
        }
        if (isset($data['display_name'])) {
            $widget->displayName = sanitize_text_field($data['display_name']);
        } elseif (isset($data['name'])) {
            // Auto-sync display_name from name if not provided separately
            $widget->displayName = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $widget->description = sanitize_textarea_field($data['description']);
        }
        if (isset($data['appearance'])) {
            $widget->appearance = array_merge(
                $widget->appearance,
                self::sanitizeRecursive($data['appearance'])
            );
        }
        if (isset($data['behavior'])) {
            $widget->behavior = array_merge(
                $widget->behavior,
                self::sanitizeRecursive($data['behavior'])
            );
        }
        if (isset($data['triggers'])) {
            $widget->triggers = array_merge(
                $widget->triggers,
                self::sanitizeRecursive($data['triggers'])
            );
        }
        if (isset($data['display'])) {
            $widget->display = array_merge(
                $widget->display,
                self::sanitizeRecursive($data['display'])
            );
        }
        if (isset($data['engagement'])) {
            $widget->engagement = array_merge(
                $widget->engagement,
                self::sanitizeRecursive($data['engagement'])
            );
        }
        if (isset($data['agent_id'])) {
            $widget->agentId = sanitize_text_field($data['agent_id']);
        }
        if (isset($data['is_active'])) {
            $widget->isActive = (bool) $data['is_active'];
        }

        if (!$widget->save()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update widget',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'widget' => $widget->toArray(),
        ]);
    }

    public static function deleteWidget(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $widget = ChatWidget::find($id);
        if (!$widget) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Widget not found',
            ], 404);
        }

        $widget->delete();

        return new \WP_REST_Response([
            'success' => true,
        ]);
    }

    /**
     * Resolve widget and agents for current page (frontend)
     */
    public static function resolveWidget(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = sanitize_text_field($request->get_param('url') ?? '');
        $pageId = (int) ($request->get_param('page_id') ?: 0);
        $postType = sanitize_text_field($request->get_param('post_type') ?? '');
        $isCart = $request->get_param('is_cart') === 'true';
        $isCheckout = $request->get_param('is_checkout') === 'true';

        $context = [
            'url' => $url,
            'page_id' => $pageId,
            'post_type' => $postType,
            'is_cart' => $isCart,
            'is_checkout' => $isCheckout,
            'user_id' => get_current_user_id(),
            'device' => self::detectDevice(),
        ];

        // Find active widgets that should display
        $widgets = ChatWidget::findActive();
        $matchedWidget = null;

        foreach ($widgets as $widget) {
            if ($widget->shouldDisplay($context)) {
                $matchedWidget = $widget;
                break;
            }
        }

        if (!$matchedWidget) {
            return new \WP_REST_Response([
                'success' => true,
                'widget' => null,
                'agents' => [],
                'requires_selection' => false,
            ]);
        }

        // Get agents for the widget
        $agents = $matchedWidget->getAgents();
        $agentData = array_map(function($agent) {
            return [
                'id'              => $agent->agentId ?? $agent->id ?? '',
                'name'            => $agent->name ?? 'Assistant',
                'avatar'          => $agent->avatar ?? '',
                'description'     => $agent->description ?? '',
                // welcomeMessage is a separate public-facing greeting.
                // systemPrompt is internal and must never be exposed publicly.
                'welcome_message' => $agent->welcomeMessage ?? $agent->description ?? '',
            ];
        }, $agents);

        // Determine if selection is required (multiple agents in team)
        $requiresSelection = count($agents) > 1 && $matchedWidget->usesTeam();

        return new \WP_REST_Response([
            'success' => true,
            'widget' => [
                'id' => $matchedWidget->id,
                'name' => $matchedWidget->displayName,
                'appearance' => $matchedWidget->appearance,
                'behavior' => $matchedWidget->behavior,
                'triggers' => $matchedWidget->triggers,
                'engagement' => $matchedWidget->engagement,
            ],
            'agents' => $agentData,
            'requires_selection' => $requiresSelection,
        ]);
    }

    /**
     * Get widget configuration for frontend embed
     */
    public static function getWidgetConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        $widgetId = (int) ($request->get_param('widget_id') ?: 0);

        $widget = null;
        if ($widgetId > 0) {
            // Only return active widgets — inactive widgets must not be exposed publicly.
            $candidate = ChatWidget::find($widgetId);
            if ($candidate && $candidate->isActive) {
                $widget = $candidate;
            }
        }

        if (!$widget) {
            // Fall back to first active widget
            $widgets = ChatWidget::findActive();
            $widget = $widgets[0] ?? null;
        }

        if (!$widget) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No active widget found',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'config'  => [
                'widgetId'    => $widget->id,
                'displayName' => $widget->displayName,
                'appearance'  => $widget->appearance,
                'behavior'    => $widget->behavior,
                'triggers'    => $widget->triggers,
                'engagement'  => $widget->engagement,
                'apiUrl'      => rest_url('quark-agentflow-ai/v1'),
                'nonce'       => wp_create_nonce('wp_rest'),
            ],
        ]);
    }

    // === Assignments ===

    public static function getAssignments(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $tables = Schema::getTableNames();
        $widgetId = (int) $request->get_param('id');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, p.post_title FROM {$tables['assignments']} a
                 LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
                 WHERE a.widget_id = %d
                 ORDER BY a.created_at DESC",
                $widgetId
            ),
            ARRAY_A
        );

        return new \WP_REST_Response([
            'success' => true,
            'assignments' => $rows ?: [],
        ]);
    }

    public static function createAssignment(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $tables = Schema::getTableNames();
        $widgetId = (int) $request->get_param('id');

        if (!ChatWidget::find($widgetId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Widget not found',
            ], 404);
        }

        $data = $request->get_json_params();
        $assignmentType = sanitize_text_field($data['assignment_type'] ?? '');
        $postId = isset($data['post_id']) ? (int) $data['post_id'] : 0;
        $postType = sanitize_text_field($data['post_type'] ?? '');

        if (empty($assignmentType)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Assignment type is required',
            ], 400);
        }

        if (in_array($assignmentType, ['page', 'post'], true) && $postId <= 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Post ID is required for this assignment type',
            ], 400);
        }

        // Auto-determine post_type
        if (!$postType) {
            $typeMap = [
                'page' => 'page',
                'post' => 'post',
                'all_posts' => 'post',
                'woocommerce_product' => 'product',
            ];
            $postType = $typeMap[$assignmentType] ?? 'page';
        }

        $locationValue = $assignmentType;
        if ($postId > 0) {
            $locationValue .= ':' . $postId;
        }

        $inserted = $wpdb->insert(
            $tables['assignments'],
            [
                'agent_db_id' => null,
                'group_db_id' => null,
                'widget_id' => $widgetId,
                'location_type' => 'widget',
                'location_value' => $locationValue,
                'assignment_type' => $assignmentType,
                'post_id' => $postId ?: null,
                'post_type' => $postType ?: null,
                'priority' => 0,
                'is_active' => 1,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d']
        );

        if (!$inserted) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create assignment',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'assignment_id' => $wpdb->insert_id,
        ], 201);
    }

    public static function deleteAssignment(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $tables = Schema::getTableNames();
        $assignmentId = (int) $request->get_param('id');

        $wpdb->delete($tables['assignments'], ['id' => $assignmentId], ['%d']);

        return new \WP_REST_Response([
            'success' => true,
        ]);
    }

    // === Helpers ===

    protected static function detectDevice(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/mobile|android|iphone|ipad|ipod|blackberry|phone/i', $userAgent)) {
            if (preg_match('/ipad|tablet/i', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }

    protected static function sanitizeRecursive($data)
    {
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $value) {
                $cleanKey = is_string($key) ? sanitize_text_field($key) : $key;
                $clean[$cleanKey] = self::sanitizeRecursive($value);
            }
            return $clean;
        }

        if (is_bool($data) || is_numeric($data)) {
            return $data;
        }

        if ($data === null) {
            return null;
        }

        return sanitize_text_field((string) $data);
    }
}
