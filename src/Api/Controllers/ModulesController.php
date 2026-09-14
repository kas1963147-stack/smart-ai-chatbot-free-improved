<?php
declare(strict_types=1);
/**
 * Modules REST API Controller
 * 
 * Provides REST API endpoints for managing plugin modules.
 * 
 * @package Quarksol\SmartChatbot\Api\Controllers
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

use Quarksol\SmartChatbot\Modules\ModuleLoader;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class ModulesController {
    
    /** REST API namespace */
    private const NAMESPACE = 'quark-agentflow-ai/v1';
    
    /**
     * Register REST routes
     */
    public static function register(): void {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }
    
    /**
     * Register REST API routes
     */
    public static function registerRoutes(): void {
        // GET /modules - List all modules
        register_rest_route(self::NAMESPACE, '/modules', [
            'methods' => 'GET',
            'callback' => [self::class, 'listModules'],
            'permission_callback' => [self::class, 'canManageModules'],
        ]);
        
        // POST /modules/{slug}/enable - Enable a module
        register_rest_route(self::NAMESPACE, '/modules/(?P<slug>[a-z_]+)/enable', [
            'methods' => 'POST',
            'callback' => [self::class, 'enableModule'],
            'permission_callback' => [self::class, 'canManageModules'],
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
        
        // POST /modules/{slug}/disable - Disable a module
        register_rest_route(self::NAMESPACE, '/modules/(?P<slug>[a-z_]+)/disable', [
            'methods' => 'POST',
            'callback' => [self::class, 'disableModule'],
            'permission_callback' => [self::class, 'canManageModules'],
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }
    
    /**
     * Check if current user can manage modules
     */
    public static function canManageModules(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * List all modules with their status
     */
    public static function listModules(WP_REST_Request $request): WP_REST_Response {
        $loader = ModuleLoader::getInstance();
        $modules = $loader->getModuleStatus();
        
        return new WP_REST_Response([
            'modules' => array_values($modules),
        ], 200);
    }
    
    /**
     * Enable a module
     */
    public static function enableModule(WP_REST_Request $request): WP_REST_Response {
        $slug = $request->get_param('slug');
        $loader = ModuleLoader::getInstance();
        
        $success = $loader->setModuleEnabled($slug, true);
        
        if (!$success) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Cannot enable module. It may be a core module or not registered.',
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => "Module '{$slug}' enabled successfully.",
        ], 200);
    }
    
    /**
     * Disable a module
     */
    public static function disableModule(WP_REST_Request $request): WP_REST_Response {
        $slug = $request->get_param('slug');
        $loader = ModuleLoader::getInstance();
        
        $success = $loader->setModuleEnabled($slug, false);
        
        if (!$success) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Cannot disable module. It may be a core module or not registered.',
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => "Module '{$slug}' disabled successfully.",
        ], 200);
    }
}
