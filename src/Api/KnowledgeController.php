<?php
declare(strict_types=1);
/**
 * Knowledge API Controller
 *
 * REST endpoints for simplified knowledge items.
 */

namespace Quarksol\SmartChatbot\Api;

use Quarksol\SmartChatbot\Knowledge\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

class KnowledgeController
{
    public static function register(): void
    {
        register_rest_route('quark-agentflow-ai/v1', '/knowledge-items', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getItems'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createItem'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/knowledge-items/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getItem'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateItem'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteItem'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
        ]);
    }

    public static function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public static function getItems(\WP_REST_Request $request): \WP_REST_Response
    {
        $items = Knowledge::all();
        return new \WP_REST_Response([
            'success' => true,
            'items' => array_map(fn($item) => $item->toArray(), $items),
        ]);
    }

    public static function getItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $item = Knowledge::find($id);
        if (!$item) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Knowledge item not found',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'item' => $item->toArray(),
        ]);
    }

    public static function createItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $displayName = sanitize_text_field($data['display_name'] ?? '');
        $slug = sanitize_title($data['name'] ?? $displayName);

        if (!$displayName) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Display name is required',
            ], 400);
        }

        if (Knowledge::findBySlug($slug)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Knowledge slug already exists',
            ], 409);
        }

        $item = new Knowledge();
        $item->name = $slug;
        $item->displayName = $displayName;
        $item->description = sanitize_textarea_field($data['description'] ?? '');
        $item->category = sanitize_text_field($data['category'] ?? 'general');
        $item->content = $data['content'] ?? '';
        $item->alwaysOn = !empty($data['always_on']);
        $item->isActive = !empty($data['is_active']);

        if (!$item->save()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create knowledge item',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'item' => $item->toArray(),
        ]);
    }

    public static function updateItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $item = Knowledge::find($id);
        if (!$item) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Knowledge item not found',
            ], 404);
        }

        $data = $request->get_json_params();
        if (isset($data['name'])) {
            $item->name = sanitize_title($data['name']);
        }
        if (isset($data['display_name'])) {
            $item->displayName = sanitize_text_field($data['display_name']);
        }
        if (isset($data['description'])) {
            $item->description = sanitize_textarea_field($data['description']);
        }
        if (isset($data['category'])) {
            $item->category = sanitize_text_field($data['category']);
        }
        if (isset($data['content'])) {
            $item->content = $data['content'];
        }
        if (isset($data['always_on'])) {
            $item->alwaysOn = (bool) $data['always_on'];
        }
        if (isset($data['is_active'])) {
            $item->isActive = (bool) $data['is_active'];
        }

        if (!$item->save()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update knowledge item',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'item' => $item->toArray(),
        ]);
    }

    public static function deleteItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $item = Knowledge::find($id);
        if (!$item) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Knowledge item not found',
            ], 404);
        }

        $item->delete();

        return new \WP_REST_Response([
            'success' => true,
        ]);
    }
}
