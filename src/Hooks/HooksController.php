<?php
declare(strict_types=1);
/**
 * Hooks REST Controller
 * 
 * Provides REST API endpoints for hook discovery and debugging.
 * 
 * @package Quarksol\SmartChatbot\Hooks
 */

namespace Quarksol\SmartChatbot\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks REST Controller
 */
class HooksController
{

    /**
     * Register REST routes
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes(): void
    {
        $namespace = 'swc/v1';

        // GET /swc/v1/hooks - List all registered hooks
        register_rest_route($namespace, '/hooks', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_hooks'],
            'permission_callback' => [self::class, 'check_admin_permission'],
            'args' => [
                'domain' => [
                    'type' => 'string',
                    'description' => 'Filter by hook domain (e.g., query, agent, tool)',
                    'required' => false,
                ],
            ],
        ]);

        // GET /swc/v1/hooks/{hook} - Get single hook details
        register_rest_route($namespace, '/hooks/(?P<hook>[a-z0-9/_-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_hook'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);

        // GET /swc/v1/hooks-stats - Get hook statistics
        register_rest_route($namespace, '/hooks-stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_stats'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);

        // GET /swc/v1/hooks-domains - Get all domains
        register_rest_route($namespace, '/hooks-domains', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_domains'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);

        // GET /swc/v1/hooks-traces - Get hook traces (debug mode)
        register_rest_route($namespace, '/hooks-traces', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_traces'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);
    }

    /**
     * Check admin permission
     */
    public static function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get all registered hooks
     */
    public static function get_hooks(\WP_REST_Request $request): \WP_REST_Response
    {
        $domain = $request->get_param('domain');
        $hooks = HookRegistry::getAll($domain);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'hooks' => array_values($hooks),
                'total' => count($hooks),
                'filtered_by' => $domain,
            ],
        ]);
    }

    /**
     * Get single hook details
     */
    public static function get_hook(\WP_REST_Request $request): \WP_REST_Response
    {
        $hookName = $request->get_param('hook');
        $hook = HookRegistry::get($hookName);

        if ($hook === null) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Hook not found',
            ], 404);
        }

        // Add runtime info
        $hook['callbacks_count'] = Hooks::countCallbacks($hookName);
        $hook['has_callbacks'] = Hooks::hasCallbacks($hookName);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $hook,
        ]);
    }

    /**
     * Get hook statistics
     */
    public static function get_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data' => HookRegistry::getStats(),
        ]);
    }

    /**
     * Get all domains
     */
    public static function get_domains(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data' => HookRegistry::getDomains(),
        ]);
    }

    /**
     * Get hook traces
     */
    public static function get_traces(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!HookRegistry::isTracingEnabled()) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Hook tracing is not enabled. Set SWC_TRACE_HOOKS constant to true.',
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'traces' => HookRegistry::getTraces(),
                'count' => count(HookRegistry::getTraces()),
            ],
        ]);
    }
}
