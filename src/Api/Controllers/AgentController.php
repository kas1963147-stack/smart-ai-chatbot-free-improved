<?php
declare(strict_types=1);
/**
 * Agent REST API Controller
 * 
 * REST API endpoints for managing chat agents, assignments, and sessions.
 * 
 * @package SWC\API
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Models\ChatAssignment;
use Quarksol\SmartChatbot\Models\ChatSession;
use Quarksol\SmartChatbot\Models\AgentGroup;
use Quarksol\SmartChatbot\Services\AgentResolver;
use Quarksol\SmartChatbot\Services\PageContext;
use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Config\ToolRegistry;
use Quarksol\SmartChatbot\Services\RateLimiter;
use Quarksol\SmartChatbot\Services\SecurityMiddleware;
use Quarksol\SmartChatbot\Services\AgentSyncService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent REST API Controller
 */
class AgentController
{

    /** API namespace */
    const NAMESPACE = 'smart-ai-chatbot/v1';

    /**
     * Register REST routes
     */
    public static function register(): void
    {
        // Agents CRUD
        register_rest_route(self::NAMESPACE , '/agents', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'listAgents'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createAgent'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE , '/agents/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getAgent'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateAgent'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteAgent'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE , '/agents/(?P<id>\d+)/duplicate', [
            'methods' => 'POST',
            'callback' => [self::class, 'duplicateAgent'],
            'permission_callback' => [self::class, 'canManage'],
        ]);

        // Assignments
        register_rest_route(self::NAMESPACE , '/agents/(?P<id>\d+)/assignments', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getAssignments'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createAssignment'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE , '/assignments/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateAssignment'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteAssignment'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        // Agent resolution (for frontend) - rate limited
        register_rest_route(self::NAMESPACE , '/resolve', [
            'methods' => 'GET',
            'callback' => [self::class, 'resolveAgents'],
            'permission_callback' => [self::class, 'canResolve'],
        ]);

        // Sessions - rate limited and session validated
        register_rest_route(self::NAMESPACE , '/sessions', [
            'methods' => 'POST',
            'callback' => [self::class, 'createSession'],
            'permission_callback' => [self::class, 'canCreateSession'],
        ]);

        register_rest_route(self::NAMESPACE , '/sessions/(?P<session_id>[a-f0-9-]+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getSession'],
                'permission_callback' => [self::class, 'canAccessSession'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'addMessage'],
                'permission_callback' => [self::class, 'canAccessSession'],
            ],
        ]);

        // Toolkits info (for admin UI)
        register_rest_route(self::NAMESPACE , '/toolkits', [
            'methods' => 'GET',
            'callback' => [self::class, 'getToolkits'],
            'permission_callback' => [self::class, 'canRead'],
        ]);

        // Connections info (for tool config selectors)
        register_rest_route(self::NAMESPACE , '/connections', [
            'methods' => 'GET',
            'callback' => [self::class, 'getConnections'],
            'permission_callback' => [self::class, 'canRead'],
        ]);

        // Templates
        register_rest_route(self::NAMESPACE , '/templates', [
            'methods' => 'GET',
            'callback' => [self::class, 'getTemplates'],
            'permission_callback' => [self::class, 'canRead'],
        ]);

        register_rest_route(self::NAMESPACE , '/templates/(?P<template_id>[a-z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'getTemplate'],
            'permission_callback' => [self::class, 'canRead'],
        ]);

        // Agent Groups
        register_rest_route(self::NAMESPACE , '/agent-groups', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'listGroups'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createGroup'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE , '/agent-groups/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getGroup'],
                'permission_callback' => [self::class, 'canRead'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateGroup'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteGroup'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE , '/agent-groups/(?P<id>\d+)/members', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getGroupMembers'],
                'permission_callback' => [self::class, 'canRead'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'addGroupMember'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE , '/agent-groups/(?P<id>\d+)/members/(?P<agent_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'removeGroupMember'],
            'permission_callback' => [self::class, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE , '/agent-groups/(?P<id>\d+)/assignments', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getGroupAssignments'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createGroupAssignment'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        // Export/Import
        register_rest_route(self::NAMESPACE , '/agents/(?P<id>\d+)/export', [
            'methods' => 'GET',
            'callback' => [self::class, 'exportAgent'],
            'permission_callback' => [self::class, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE , '/agents/import', [
            'methods' => 'POST',
            'callback' => [self::class, 'importAgent'],
            'permission_callback' => [self::class, 'canManage'],
        ]);

        // Ratings - rate limited
        register_rest_route(self::NAMESPACE , '/ratings', [
            'methods' => 'POST',
            'callback' => [self::class, 'submitRating'],
            'permission_callback' => [self::class, 'canSubmitRating'],
        ]);

        register_rest_route(self::NAMESPACE , '/agents/(?P<id>\d+)/ratings', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAgentRatings'],
            'permission_callback' => [self::class, 'canRead'],
        ]);

        // Sync agents from folder
        register_rest_route(self::NAMESPACE , '/agents/sync', [
            'methods' => 'POST',
            'callback' => [self::class, 'syncAgents'],
            'permission_callback' => [self::class, 'canManage'],
        ]);
    }

    // =========================================================================
    // Permission Callbacks
    // =========================================================================

    public static function canRead(\WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public static function canManage(\WP_REST_Request $request): bool
    {
        $result = current_user_can('manage_options');
        error_log('[AgentController::canManage] Route: ' . $request->get_route() . ' | User ID: ' . get_current_user_id() . ' | Result: ' . ($result ? 'ALLOWED' : 'DENIED'));
        return $result;
    }

    /**
     * Rate-limited permission for /resolve endpoint
     */
    public static function canResolve(\WP_REST_Request $request): bool
    {
        $identifier = RateLimiter::getIdentifier($request);
        return RateLimiter::check('resolve', $identifier);
    }

    /**
     * Rate-limited permission for session creation
     */
    public static function canCreateSession(\WP_REST_Request $request): bool
    {
        $identifier = RateLimiter::getIdentifier($request);
        return RateLimiter::check('session', $identifier);
    }

    /**
     * Session ownership validation for session access
     */
    public static function canAccessSession(\WP_REST_Request $request): bool
    {
        $sessionId = sanitize_text_field($request->get_param('session_id'));

        if (empty($sessionId)) {
            return false;
        }

        // First check rate limit
        $identifier = RateLimiter::getIdentifier($request);
        if (!RateLimiter::check('chat', $identifier)) {
            return false;
        }

        return SecurityMiddleware::validateSessionOwnership($sessionId, $request);
    }

    /**
     * Rate-limited permission for rating submission
     */
    public static function canSubmitRating(\WP_REST_Request $request): bool
    {
        $identifier = RateLimiter::getIdentifier($request);
        return RateLimiter::check('rating', $identifier);
    }

    // =========================================================================
    // Agent Endpoints
    // =========================================================================

    /**
     * GET /agents - List all agents
     * 
     * Automatically syncs agents from filesystem before returning,
     * ensuring the database always has the latest agent definitions.
     */
    public static function listAgents(\WP_REST_Request $request): \WP_REST_Response
    {
        // Auto-sync: ensure folder agents are in DB and stale agents are purged
        try {
            AgentSyncService::syncFromFolder();
        } catch (\Throwable $e) {
            error_log('[AgentController::listAgents] Auto-sync failed: ' . $e->getMessage());
        }

        $includeInactive = $request->get_param('include_inactive') === 'true';
        $agents = ChatAgent::all(!$includeInactive);

        return new \WP_REST_Response([
            'agents' => array_map(fn($a) => $a->toArray(), $agents),
            'count' => count($agents),
        ], 200);
    }

    /**
     * GET /agents/{id} - Get single agent
     */
    public static function getAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agent = ChatAgent::find((int) $request->get_param('id'));

        if (!$agent) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        return new \WP_REST_Response($agent->toArray(), 200);
    }

    /**
     * POST /agents - Create new agent
     */
    public static function createAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $agentId = sanitize_key($params['agent_id'] ?? '');
        $name = sanitize_text_field($params['name'] ?? '');

        if (empty($agentId) || empty($name)) {
            return new \WP_REST_Response([
                'error' => 'agent_id and name are required'
            ], 400);
        }


        // Check for duplicate slug
        if (ChatAgent::findBySlug($agentId)) {
            return new \WP_REST_Response([
                'error' => 'An agent with this ID already exists'
            ], 409);
        }

        $agent = new ChatAgent();
        $agent->agentId = $agentId;
        $agent->name = $name;
        $agent->description = sanitize_textarea_field($params['description'] ?? '');
        $agent->avatar = sanitize_text_field($params['avatar'] ?? '');
        $agent->isActive = !isset($params['is_active']) || $params['is_active'];
        $agent->isDefault = !empty($params['is_default']);

        // Build config
        $config = new AgentConfig($agentId, $name);
        if (!empty($params['config']) && is_array($params['config'])) {
            if (
                array_key_exists('enabled_skills', $params['config']) ||
                array_key_exists('disabled_skills', $params['config']) ||
                array_key_exists('skill_mode', $params['config'])
            ) {
                $params['config']['skills_configured'] = true;
            }

            if (array_key_exists('enabled_sections', $params['config'])) {
                $params['config']['sections_configured'] = true;
            }

            if (array_key_exists('knowledge_sources', $params['config'])) {
                $params['config']['knowledge_sources_configured'] = true;
            }

            $config = AgentConfig::fromArray(array_merge(
                ['agent_id' => $agentId, 'name' => $name],
                $params['config']
            ));

            // Explicitly set provider_instance_id to ensure it persists
            if (isset($params['config']['provider_instance_id'])) {
                $pid = $params['config']['provider_instance_id'];
                $config->providerInstanceId = !empty($pid) ? sanitize_text_field($pid) : null;
            }
        }
        $agent->config = $config;

        // Validate tool count (API maximum is 128)
        $enabledTools = ToolRegistry::getToolsForConfig($config);
        if (count($enabledTools) > 128) {
            return new \WP_REST_Response([
                'error' => 'Too many tools selected (' . count($enabledTools) . '). Maximum is 128 tools.',
                'tool_count' => count($enabledTools),
                'max_tools' => 128,
            ], 400);
        }

        if (!$agent->save()) {
            return new \WP_REST_Response(['error' => 'Failed to create agent'], 500);
        }

        // Sync to folder
        AgentSyncService::syncToFolder($agent);


        return new \WP_REST_Response($agent->toArray(), 201);
    }

    /**
     * PUT /agents/{id} - Update agent
     */
    public static function updateAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        // Debug logging for cloud issues
        error_log('[AgentController::updateAgent] === REQUEST START ===');
        error_log('[AgentController::updateAgent] Agent ID param: ' . $request->get_param('id'));

        try {
            $agent = ChatAgent::find((int) $request->get_param('id'));

            if (!$agent) {
                return new \WP_REST_Response(['error' => 'Agent not found'], 404);
            }

            $params = $request->get_json_params();

            if (isset($params['name'])) {
                $agent->name = sanitize_text_field($params['name']);
            }
            if (isset($params['description'])) {
                $agent->description = sanitize_textarea_field($params['description']);
            }
            if (isset($params['avatar'])) {
                $agent->avatar = sanitize_text_field($params['avatar']);
            }
            if (isset($params['is_active'])) {
                $newIsActive = (bool) $params['is_active'];
                
                
                $agent->isActive = $newIsActive;
            }
            if (isset($params['is_default'])) {
                $agent->isDefault = (bool) $params['is_default'];
            }

            // Update config
            if (!empty($params['config']) && is_array($params['config'])) {
                if (
                    array_key_exists('enabled_skills', $params['config']) ||
                    array_key_exists('disabled_skills', $params['config']) ||
                    array_key_exists('skill_mode', $params['config'])
                ) {
                    $params['config']['skills_configured'] = true;
                }

                if (array_key_exists('enabled_sections', $params['config'])) {
                    $params['config']['sections_configured'] = true;
                }

                if (array_key_exists('knowledge_sources', $params['config'])) {
                    $params['config']['knowledge_sources_configured'] = true;
                }

                $existingConfig = $agent->config ? $agent->config->toArray() : [];
                $agent->config = AgentConfig::fromArray(array_merge(
                    $existingConfig,
                    $params['config'],
                    ['agent_id' => $agent->agentId, 'name' => $agent->name]
                ));

                // Explicitly set provider_instance_id to ensure it persists
                if (isset($params['config']['provider_instance_id'])) {
                    $pid = $params['config']['provider_instance_id'];
                    $agent->config->providerInstanceId = !empty($pid) ? sanitize_text_field($pid) : null;
                }
            }

            // Validate tool count (API maximum is 128)
            if ($agent->config) {
                try {
                    $enabledTools = ToolRegistry::getToolsForConfig($agent->config);
                    $toolCount = count($enabledTools);
                    error_log('[AgentController::updateAgent] Tool count: ' . $toolCount);
                    if ($toolCount > 128) {
                        error_log('[AgentController::updateAgent] REJECTING: Too many tools (' . $toolCount . ')');
                        return new \WP_REST_Response([
                            'error' => 'Too many tools selected (' . $toolCount . '). Maximum is 128 tools.',
                            'tool_count' => $toolCount,
                            'max_tools' => 128,
                        ], 400);
                    }
                } catch (\Throwable $e) {
                    error_log('[AgentController::updateAgent] Tool count validation failed: ' . $e->getMessage());
                    // Don't block save due to tool count validation failure
                }
            }

            error_log('[AgentController::updateAgent] About to save agent ID: ' . $agent->id);
            $saveResult = $agent->save();
            error_log('[AgentController::updateAgent] Save result: ' . ($saveResult ? 'SUCCESS' : 'FAILED'));

            if (!$saveResult) {
                error_log('[AgentController::updateAgent] Save failed for agent ID: ' . $agent->id);
                return new \WP_REST_Response(['error' => 'Failed to update agent. Check server error logs for details.'], 500);
            }

            // Clear MCP tool cache (agent config may include MCP changes)
            delete_transient('swc_mcp_tools_cache');
            delete_transient('swc_mcp_servers_cache');

            // Sync to folder (non-critical - don't fail the save if sync fails)
            try {
                AgentSyncService::syncToFolder($agent);
            } catch (\Throwable $e) {
                error_log('[AgentController::updateAgent] Folder sync failed (non-critical): ' . $e->getMessage());
            }

            return new \WP_REST_Response($agent->toArray(), 200);

        } catch (\Throwable $e) {
            error_log('[AgentController::updateAgent] EXCEPTION: ' . $e->getMessage());
            error_log('[AgentController::updateAgent] File: ' . $e->getFile() . ':' . $e->getLine());
            error_log('[AgentController::updateAgent] Trace: ' . $e->getTraceAsString());
            return new \WP_REST_Response([
                'error' => 'Failed to update agent: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /agents/{id} - Delete agent
     */
    public static function deleteAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agent = ChatAgent::find((int) $request->get_param('id'));

        if (!$agent) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        // Prevent deleting default agent
        if ($agent->isDefault) {
            return new \WP_REST_Response([
                'error' => 'Cannot delete the default agent'
            ], 403);
        }

        // Store agent ID before deletion
        $agentId = $agent->agentId;

        if (!$agent->delete()) {
            return new \WP_REST_Response(['error' => 'Failed to delete agent'], 500);
        }

        // Remove from folder
        AgentSyncService::deleteFromFolder($agentId);

        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * POST /agents/{id}/duplicate - Duplicate agent
     */
    public static function duplicateAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agent = ChatAgent::find((int) $request->get_param('id'));

        if (!$agent) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        $params = $request->get_json_params();
        $newSlug = sanitize_key($params['agent_id'] ?? $agent->agentId . '_copy');
        $newName = sanitize_text_field($params['name'] ?? $agent->name . ' (Copy)');

        // Check for duplicate slug
        if (ChatAgent::findBySlug($newSlug)) {
            return new \WP_REST_Response([
                'error' => 'An agent with this ID already exists'
            ], 409);
        }

        $newAgent = $agent->duplicate($newSlug, $newName);

        if (!$newAgent->save()) {
            return new \WP_REST_Response(['error' => 'Failed to duplicate agent'], 500);
        }

        return new \WP_REST_Response($newAgent->toArray(), 201);
    }

    /**
     * POST /agents/sync - Sync agents from folder to database
     */
    public static function syncAgents(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $results = AgentSyncService::syncFromFolder();
            
            // Get updated agent list
            $agents = ChatAgent::all(false);
            
            // Check if there were errors during sync
            if (!empty($results['errors'])) {
                error_log('[AgentController::syncAgents] Sync completed with errors: ' . wp_json_encode($results['errors']));
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Agents synchronized successfully',
                'created' => $results['created'] ?? [],
                'updated' => $results['updated'] ?? [],
                'deleted' => $results['deleted'] ?? [],
                'errors' => $results['errors'] ?? [],
                'agents' => array_map(fn($a) => $a->toArray(), $agents),
                'count' => count($agents),
            ], 200);
        } catch (\Throwable $e) {
            error_log('[AgentController::syncAgents] Sync failed with exception: ' . $e->getMessage());
            error_log('[AgentController::syncAgents] Stack trace: ' . $e->getTraceAsString());
            return new \WP_REST_Response([
                'error' => 'Failed to sync agents: ' . $e->getMessage(),
                'trace' => defined('WP_DEBUG') && \WP_DEBUG ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Assignment Endpoints
    // =========================================================================

    /**
     * GET /agents/{id}/assignments
     */
    public static function getAssignments(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = (int) $request->get_param('id');
        $assignments = ChatAssignment::forAgent($agentId);

        return new \WP_REST_Response([
            'assignments' => array_map(fn($a) => $a->toArray(), $assignments),
            'location_types' => ChatAssignment::getLocationTypes(),
        ], 200);
    }

    /**
     * POST /agents/{id}/assignments
     */
    public static function createAssignment(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $agent = ChatAgent::find($agentId);
        if (!$agent) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        $locationType = sanitize_key($params['location_type'] ?? '');
        $locationValue = sanitize_text_field($params['location_value'] ?? '');

        $validTypes = array_keys(ChatAssignment::getLocationTypes());
        if (!in_array($locationType, $validTypes)) {
            return new \WP_REST_Response(['error' => 'Invalid location_type'], 400);
        }

        $assignment = new ChatAssignment();
        $assignment->agentDbId = $agentId;
        $assignment->locationType = $locationType;
        $assignment->locationValue = $locationValue ?: null;
        $assignment->priority = (int) ($params['priority'] ?? 0);
        $assignment->isActive = !isset($params['is_active']) || $params['is_active'];

        if (!$assignment->save()) {
            return new \WP_REST_Response(['error' => 'Failed to create assignment'], 500);
        }

        return new \WP_REST_Response($assignment->toArray(), 201);
    }

    /**
     * GET /agent-groups/{id}/assignments
     */
    public static function getGroupAssignments(\WP_REST_Request $request): \WP_REST_Response
    {
        $groupId = (int) $request->get_param('id');
        $assignments = ChatAssignment::forGroup($groupId);

        return new \WP_REST_Response([
            'assignments' => array_map(fn($a) => $a->toArray(), $assignments),
            'location_types' => ChatAssignment::getLocationTypes(),
        ], 200);
    }

    /**
     * POST /agent-groups/{id}/assignments
     */
    public static function createGroupAssignment(\WP_REST_Request $request): \WP_REST_Response
    {
        $groupId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $group = AgentGroup::find($groupId);
        if (!$group) {
            return new \WP_REST_Response(['error' => 'Group not found'], 404);
        }

        $locationType = sanitize_key($params['location_type'] ?? '');
        $locationValue = sanitize_text_field($params['location_value'] ?? '');

        $validTypes = array_keys(ChatAssignment::getLocationTypes());
        if (!in_array($locationType, $validTypes)) {
            return new \WP_REST_Response(['error' => 'Invalid location_type'], 400);
        }

        $assignment = new ChatAssignment();
        $assignment->groupDbId = $groupId;
        $assignment->locationType = $locationType;
        $assignment->locationValue = $locationValue ?: null;
        $assignment->priority = (int) ($params['priority'] ?? 0);
        $assignment->isActive = !isset($params['is_active']) || $params['is_active'];

        if (!$assignment->save()) {
            return new \WP_REST_Response(['error' => 'Failed to create assignment'], 500);
        }

        return new \WP_REST_Response($assignment->toArray(), 201);
    }

    /**
     * PUT /assignments/{id}
     */
    public static function updateAssignment(\WP_REST_Request $request): \WP_REST_Response
    {
        $assignment = ChatAssignment::find((int) $request->get_param('id'));

        if (!$assignment) {
            return new \WP_REST_Response(['error' => 'Assignment not found'], 404);
        }

        $params = $request->get_json_params();

        if (isset($params['priority'])) {
            $assignment->priority = (int) $params['priority'];
        }
        if (isset($params['is_active'])) {
            $assignment->isActive = (bool) $params['is_active'];
        }

        if (!$assignment->save()) {
            return new \WP_REST_Response(['error' => 'Failed to update assignment'], 500);
        }

        return new \WP_REST_Response($assignment->toArray(), 200);
    }

    /**
     * DELETE /assignments/{id}
     */
    public static function deleteAssignment(\WP_REST_Request $request): \WP_REST_Response
    {
        $assignment = ChatAssignment::find((int) $request->get_param('id'));

        if (!$assignment) {
            return new \WP_REST_Response(['error' => 'Assignment not found'], 404);
        }

        if (!$assignment->delete()) {
            return new \WP_REST_Response(['error' => 'Failed to delete assignment'], 500);
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    // =========================================================================
    // Resolution Endpoint (Public)
    // =========================================================================

    /**
     * GET /resolve - Get agents for current page context
     */
    public static function resolveAgents(\WP_REST_Request $request): \WP_REST_Response
    {
        // Rate limiting is already handled by the canResolve permission callback

        $contextData = [];

        // Build context from query params
        if ($request->get_param('page_id')) {
            $contextData['page_id'] = (int) $request->get_param('page_id');
        }
        if ($request->get_param('post_type')) {
            $contextData['post_type'] = sanitize_key($request->get_param('post_type'));
        }
        if ($request->get_param('url')) {
            $contextData['url'] = esc_url_raw($request->get_param('url'));
        }
        $contextData['is_cart'] = $request->get_param('is_cart') === 'true';
        $contextData['is_checkout'] = $request->get_param('is_checkout') === 'true';
        $contextData['is_account'] = $request->get_param('is_account') === 'true';

        $context = PageContext::fromArray($contextData);
        $resolver = new AgentResolver();

        $agents = $resolver->getAgentsForContext($context);

        return new \WP_REST_Response([
            'agents' => array_map(fn($a) => [
                'id' => $a->id,
                'agent_id' => $a->agentId,
                'name' => $a->name,
                'avatar' => $a->avatar,
                'welcome_message' => $a->config->welcomeMessage ?? '',
                'quick_actions' => $a->config->quickActions ?? [],
                'starter_prompts' => $a->config->starterPrompts ?? [],
            ], $agents),
            'requires_selection' => count($agents) > 1,
            'count' => count($agents),
        ], 200);
    }

    // =========================================================================
    // Session Endpoints
    // =========================================================================

    /**
     * POST /sessions - Create or get session
     */
    public static function createSession(\WP_REST_Request $request): \WP_REST_Response
    {
        // Rate limiting check
        $identifier = RateLimiter::getIdentifier($request);
        if (!RateLimiter::check('session', $identifier)) {
            return RateLimiter::limitExceededResponse('session', $identifier);
        }

        $params = $request->get_json_params();

        $agentDbId = (int) ($params['agent_id'] ?? 0);
        $visitorId = sanitize_text_field($params['visitor_id'] ?? '');

        if (!$agentDbId) {
            return new \WP_REST_Response(['error' => 'agent_id is required'], 400);
        }

        $agent = ChatAgent::find($agentDbId);
        if (!$agent) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        $userId = is_user_logged_in() ? get_current_user_id() : null;

        $metadata = [
            'url' => sanitize_text_field($params['url'] ?? ''),
            'user_agent' => class_exists('\Quarksol\SmartChatbot\Services\ServerInput')
                ? \Quarksol\SmartChatbot\Services\ServerInput::getUserAgent()
                : substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
        ];

        $session = ChatSession::findOrCreate($agentDbId, $userId, $visitorId, $metadata);

        return new \WP_REST_Response([
            'session_id' => $session->sessionId,
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'avatar' => $agent->avatar,
            ],
            'messages' => $session->getMessages(),
        ], 200);
    }

    /**
     * GET /sessions/{session_id} - Get session with messages
     */
    public static function getSession(\WP_REST_Request $request): \WP_REST_Response
    {
        $sessionId = sanitize_text_field($request->get_param('session_id'));
        $session = ChatSession::find($sessionId);

        if (!$session) {
            return new \WP_REST_Response(['error' => 'Session not found'], 404);
        }

        $agent = $session->getAgent();

        return new \WP_REST_Response([
            'session' => $session->toArray(),
            'agent' => $agent ? [
                'id' => $agent->id,
                'name' => $agent->name,
                'avatar' => $agent->avatar,
            ] : null,
        ], 200);
    }

    /**
     * POST /sessions/{session_id} - Add message to session
     */
    public static function addMessage(\WP_REST_Request $request): \WP_REST_Response
    {
        $sessionId = sanitize_text_field($request->get_param('session_id'));
        $session = ChatSession::find($sessionId);

        if (!$session) {
            return new \WP_REST_Response(['error' => 'Session not found'], 404);
        }

        $params = $request->get_json_params();
        $role = sanitize_key($params['role'] ?? 'user');
        $content = sanitize_textarea_field($params['content'] ?? '');

        if (empty($content)) {
            return new \WP_REST_Response(['error' => 'content is required'], 400);
        }

        if (!in_array($role, ['user', 'assistant', 'system'])) {
            return new \WP_REST_Response(['error' => 'Invalid role'], 400);
        }

        $session->addMessage($role, $content);
        $session->save();

        return new \WP_REST_Response([
            'success' => true,
            'message_count' => count($session->getMessages()),
        ], 200);
    }

    // =========================================================================
    // Toolkits Info
    // =========================================================================

    /**
     * GET /toolkits - Get available toolkits for admin UI
     */
    public static function getToolkits(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(ToolRegistry::getToolkits(), 200);
    }

    /**
     * GET /connections - Get available connections for tool configs
     */
    public static function getConnections(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data' => [],
        ], 200);
    }

    // =========================================================================
    // Templates
    // =========================================================================

    /**
     * GET /templates - List available agent templates
     */
    public static function getTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        $templates = \Quarksol\SmartChatbot\Templates\TemplateRegistry::getSummary();
        $categories = \Quarksol\SmartChatbot\Templates\TemplateRegistry::getCategories();

        return new \WP_REST_Response([
            'templates' => $templates,
            'categories' => $categories,
        ], 200);
    }

    /**
     * GET /templates/{id} - Get single template
     */
    public static function getTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $templateId = sanitize_key($request->get_param('template_id'));
        $template = \Quarksol\SmartChatbot\Templates\TemplateRegistry::get($templateId);

        if (!$template) {
            return new \WP_REST_Response(['error' => 'Template not found'], 404);
        }

        return new \WP_REST_Response($template, 200);
    }

    // =========================================================================
    // Agent Groups
    // =========================================================================

    /**
     * GET /agent-groups - List all groups
     */
    public static function listGroups(\WP_REST_Request $request): \WP_REST_Response
    {
        $includeInactive = $request->get_param('include_inactive') === 'true';
        $groups = AgentGroup::all(!$includeInactive);

        return new \WP_REST_Response([
            'groups' => array_map(fn($g) => $g->toArray(), $groups),
            'count' => count($groups),
        ], 200);
    }

    /**
     * GET /agent-groups/{id} - Get single group
     */
    public static function getGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $group = AgentGroup::find((int) $request->get_param('id'));

        if (!$group) {
            return new \WP_REST_Response(['error' => 'Group not found'], 404);
        }

        return new \WP_REST_Response($group->toArray(), 200);
    }

    /**
     * POST /agent-groups - Create new group
     */
    public static function createGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $groupId = sanitize_key($params['group_id'] ?? '');
        $name = sanitize_text_field($params['name'] ?? '');

        if (empty($groupId) || empty($name)) {
            return new \WP_REST_Response([
                'error' => 'group_id and name are required'
            ], 400);
        }

        // Check for duplicate slug
        if (AgentGroup::findBySlug($groupId)) {
            return new \WP_REST_Response([
                'error' => 'A group with this ID already exists'
            ], 409);
        }

        $group = new AgentGroup();
        $group->groupId = $groupId;
        $group->name = $name;
        $group->description = sanitize_textarea_field($params['description'] ?? '');
        $group->avatar = sanitize_text_field($params['avatar'] ?? '');
        $group->orchestrationMode = sanitize_key($params['orchestration_mode'] ?? AgentGroup::MODE_ROUTER);
        $group->welcomeMessage = sanitize_textarea_field($params['welcome_message'] ?? '');
        $group->isActive = !isset($params['is_active']) || $params['is_active'];

        if (isset($params['routing_config']) && is_array($params['routing_config'])) {
            $group->routingConfig = self::sanitizeRoutingConfig($params['routing_config']);
        }

        if (!$group->save()) {
            return new \WP_REST_Response(['error' => 'Failed to create group'], 500);
        }

        // Add members if provided
        if (!empty($params['members']) && is_array($params['members'])) {
            foreach ($params['members'] as $member) {
                if (!empty($member['agent_db_id'])) {
                    $group->addMember(
                        (int) $member['agent_db_id'],
                        sanitize_key($member['role'] ?? AgentGroup::ROLE_SPECIALIST),
                        (int) ($member['execution_order'] ?? 0),
                        sanitize_text_field($member['routing_keywords'] ?? '')
                    );
                }
            }
        }

        return new \WP_REST_Response($group->toArray(), 201);
    }

    /**
     * PUT /agent-groups/{id} - Update group
     */
    public static function updateGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $group = AgentGroup::find((int) $request->get_param('id'));

        if (!$group) {
            return new \WP_REST_Response(['error' => 'Group not found'], 404);
        }

        $params = $request->get_json_params();

        if (isset($params['name'])) {
            $group->name = sanitize_text_field($params['name']);
        }
        if (isset($params['description'])) {
            $group->description = sanitize_textarea_field($params['description']);
        }
        if (isset($params['avatar'])) {
            $group->avatar = sanitize_text_field($params['avatar']);
        }
        if (isset($params['orchestration_mode'])) {
            $group->orchestrationMode = sanitize_key($params['orchestration_mode']);
        }
        if (isset($params['welcome_message'])) {
            $group->welcomeMessage = sanitize_textarea_field($params['welcome_message']);
        }
        if (isset($params['is_active'])) {
            $group->isActive = (bool) $params['is_active'];
        }
        if (isset($params['routing_config']) && is_array($params['routing_config'])) {
            $group->routingConfig = self::sanitizeRoutingConfig($params['routing_config']);
        }

        if (!$group->save()) {
            return new \WP_REST_Response(['error' => 'Failed to update group'], 500);
        }

        return new \WP_REST_Response($group->toArray(), 200);
    }

    /**
     * DELETE /agent-groups/{id} - Delete group
     */
    public static function deleteGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $group = AgentGroup::find((int) $request->get_param('id'));

        if (!$group) {
            return new \WP_REST_Response(['error' => 'Group not found'], 404);
        }

        if (!$group->delete()) {
            return new \WP_REST_Response(['error' => 'Failed to delete group'], 500);
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * Sanitize routing_config JSON data, including workflow_steps.
     *
     * Recursively sanitizes all values within the routing config
     * and validates workflow step structure.
     */
    private static function sanitizeRoutingConfig(array $config): array
    {
        $sanitized = [];

        // Pass through orchestration-related scalar keys
        foreach ($config as $key => $value) {
            if ($key === 'workflow_steps' && is_array($value)) {
                $sanitized['workflow_steps'] = self::sanitizeWorkflowSteps($value);
            } elseif (is_array($value)) {
                $sanitized[sanitize_key($key)] = self::sanitizeRoutingConfig($value);
            } elseif (is_string($value)) {
                $sanitized[sanitize_key($key)] = sanitize_textarea_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[sanitize_key($key)] = $value;
            } elseif (is_bool($value)) {
                $sanitized[sanitize_key($key)] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize workflow_steps array within routing_config.
     *
     * Validates each step has a valid type and sanitizes all text fields.
     */
    private static function sanitizeWorkflowSteps(array $steps): array
    {
        $validTypes = ['instruction', 'agent', 'approval', 'condition'];
        $sanitized = [];

        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }

            $type = sanitize_key($step['type'] ?? 'instruction');
            if (!in_array($type, $validTypes, true)) {
                $type = 'instruction';
            }

            $sanitizedStep = [
                'type'        => $type,
                'name'        => sanitize_text_field($step['name'] ?? ''),
                'description' => sanitize_textarea_field($step['description'] ?? ''),
                'input'       => sanitize_textarea_field($step['input'] ?? ''),
                'agent_id'    => sanitize_text_field($step['agent_id'] ?? ''),
            ];

            $sanitized[] = $sanitizedStep;
        }

        return $sanitized;
    }

    /**
     * GET /agent-groups/{id}/members - Get group members
     */
    public static function getGroupMembers(\WP_REST_Request $request): \WP_REST_Response
    {
        $group = AgentGroup::find((int) $request->get_param('id'));

        if (!$group) {
            return new \WP_REST_Response(['error' => 'Group not found'], 404);
        }

        return new \WP_REST_Response($group->getMembers(), 200);
    }

    /**
     * POST /agent-groups/{id}/members - Add/update member
     */
    public static function addGroupMember(\WP_REST_Request $request): \WP_REST_Response
    {
        $group = AgentGroup::find((int) $request->get_param('id'));

        if (!$group) {
            return new \WP_REST_Response(['error' => 'Group not found'], 404);
        }

        $params = $request->get_json_params();
        $agentId = (int) ($params['agent_db_id'] ?? 0);

        if (!$agentId) {
            return new \WP_REST_Response(['error' => 'agent_db_id is required'], 400);
        }

        if (!ChatAgent::find($agentId)) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        $success = $group->addMember(
            $agentId,
            sanitize_key($params['role'] ?? AgentGroup::ROLE_SPECIALIST),
            (int) ($params['execution_order'] ?? 0),
            sanitize_text_field($params['routing_keywords'] ?? '')
        );

        if (!$success) {
            return new \WP_REST_Response(['error' => 'Failed to add member'], 500);
        }

        return new \WP_REST_Response($group->getMembers(), 201);
    }

    /**
     * DELETE /agent-groups/{id}/members/{agent_id} - Remove member
     */
    public static function removeGroupMember(\WP_REST_Request $request): \WP_REST_Response
    {
        $group = AgentGroup::find((int) $request->get_param('id'));

        if (!$group) {
            return new \WP_REST_Response(['error' => 'Group not found'], 404);
        }

        $agentId = (int) $request->get_param('agent_id');

        if (!$group->removeMember($agentId)) {
            return new \WP_REST_Response(['error' => 'Failed to remove member'], 500);
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    // =========================================================================
    // Export/Import
    // =========================================================================

    /**
     * GET /agents/{id}/export - Export agent as JSON
     */
    public static function exportAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agent = ChatAgent::find((int) $request->get_param('id'));

        if (!$agent) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        $assignments = ChatAssignment::forAgent($agent->id);

        $export = [
            'version' => '1.0',
            'exported_at' => current_time('c'),
            'plugin_version' => SWC_CHATBOT_VERSION ?? '1.0.0',
            'agent' => [
                'agent_id' => $agent->agentId,
                'name' => $agent->name,
                'description' => $agent->description,
                'avatar' => $agent->avatar,
                'config' => $agent->config ? $agent->config->toArray() : [],
            ],
            'assignments' => array_map(function ($a) {
                return [
                    'location_type' => $a->locationType,
                    'location_value' => $a->locationValue,
                    'priority' => $a->priority,
                ];
            }, $assignments),
        ];

        return new \WP_REST_Response($export, 200);
    }

    /**
     * POST /agents/import - Import agent from JSON
     */
    public static function importAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        // Validate import format
        if (empty($params['agent']) || empty($params['version'])) {
            return new \WP_REST_Response([
                'error' => 'Invalid import format. Expected version and agent data.'
            ], 400);
        }

        $agentData = $params['agent'];
        $agentId = sanitize_key($agentData['agent_id'] ?? '');
        $name = sanitize_text_field($agentData['name'] ?? '');

        if (empty($agentId) || empty($name)) {
            return new \WP_REST_Response([
                'error' => 'agent_id and name are required in import data'
            ], 400);
        }

        // Handle conflict resolution
        $conflictMode = sanitize_key($params['conflict_mode'] ?? 'reject');
        $existingAgent = ChatAgent::findBySlug($agentId);

        if ($existingAgent) {
            switch ($conflictMode) {
                case 'overwrite':
                    // Delete existing and continue
                    if (!$existingAgent->isDefault) {
                        $existingAgent->delete();
                    } else {
                        return new \WP_REST_Response([
                            'error' => 'Cannot overwrite the default agent'
                        ], 403);
                    }
                    break;

                case 'rename':
                    // Generate new unique ID
                    $counter = 1;
                    $originalId = $agentId;
                    while (ChatAgent::findBySlug($agentId)) {
                        $agentId = $originalId . '_imported_' . $counter++;
                    }
                    $name .= ' (Imported)';
                    break;

                case 'reject':
                default:
                    return new \WP_REST_Response([
                        'error' => 'An agent with this ID already exists',
                        'conflict' => true,
                        'existing_agent' => $existingAgent->name
                    ], 409);
            }
        }

        // Create agent
        $agent = new ChatAgent();
        $agent->agentId = $agentId;
        $agent->name = $name;
        $agent->description = sanitize_textarea_field($agentData['description'] ?? '');
        $agent->avatar = sanitize_text_field($agentData['avatar'] ?? '');
        $agent->isActive = true;
        $agent->isDefault = false;

        // Build config
        if (!empty($agentData['config']) && is_array($agentData['config'])) {
            $agent->config = AgentConfig::fromArray(array_merge(
                $agentData['config'],
                ['agent_id' => $agentId, 'name' => $name]
            ));
        }

        if (!$agent->save()) {
            return new \WP_REST_Response(['error' => 'Failed to import agent'], 500);
        }

        // Import assignments
        $assignmentsImported = 0;
        if (!empty($params['assignments']) && is_array($params['assignments'])) {
            foreach ($params['assignments'] as $assignData) {
                $assignment = new ChatAssignment();
                $assignment->agentDbId = $agent->id;
                $assignment->locationType = sanitize_key($assignData['location_type'] ?? 'global');
                $assignment->locationValue = sanitize_text_field($assignData['location_value'] ?? '');
                $assignment->priority = (int) ($assignData['priority'] ?? 0);
                $assignment->isActive = true;

                if ($assignment->save()) {
                    $assignmentsImported++;
                }
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'agent' => $agent->toArray(),
            'assignments_imported' => $assignmentsImported,
        ], 201);
    }

    // =========================================================================
    // Ratings
    // =========================================================================

    /**
     * POST /ratings - Submit a rating
     */
    public static function submitRating(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        $sessionId = sanitize_text_field($params['session_id'] ?? '');
        $ratingType = sanitize_key($params['rating_type'] ?? '');

        if (empty($sessionId) || empty($ratingType)) {
            return new \WP_REST_Response([
                'error' => 'session_id and rating_type are required'
            ], 400);
        }

        // Validate rating type
        $validTypes = ['up', 'down', 'stars'];
        if (!in_array($ratingType, $validTypes)) {
            return new \WP_REST_Response(['error' => 'Invalid rating_type'], 400);
        }

        // Get session
        $session = ChatSession::find($sessionId);
        if (!$session) {
            return new \WP_REST_Response(['error' => 'Session not found'], 404);
        }

        // Check for duplicate
        $messageIndex = isset($params['message_index']) ? (int) $params['message_index'] : null;

        if ($messageIndex !== null && \Quarksol\SmartChatbot\Models\ChatRating::isMessageRated($sessionId, $messageIndex)) {
            return new \WP_REST_Response(['error' => 'Message already rated'], 409);
        }

        if ($ratingType === 'stars' && \Quarksol\SmartChatbot\Models\ChatRating::hasSessionRating($sessionId)) {
            return new \WP_REST_Response(['error' => 'Session already rated'], 409);
        }

        // Create rating
        $rating = new \Quarksol\SmartChatbot\Models\ChatRating();
        $rating->sessionId = $sessionId;
        $rating->agentDbId = $session->agentDbId;
        $rating->messageIndex = $messageIndex;
        $rating->ratingType = $ratingType;
        $rating->ratingValue = isset($params['rating_value']) ? (int) $params['rating_value'] : null;
        $rating->comment = sanitize_textarea_field($params['comment'] ?? '');
        $rating->userId = is_user_logged_in() ? get_current_user_id() : null;
        $rating->visitorId = sanitize_text_field($params['visitor_id'] ?? '');

        if (!$rating->save()) {
            return new \WP_REST_Response(['error' => 'Failed to save rating'], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'rating_id' => $rating->id,
        ], 201);
    }

    /**
     * GET /agents/{id}/ratings - Get agent rating summary
     */
    public static function getAgentRatings(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = (int) $request->get_param('id');

        $agent = ChatAgent::find($agentId);
        if (!$agent) {
            return new \WP_REST_Response(['error' => 'Agent not found'], 404);
        }

        $summary = \Quarksol\SmartChatbot\Models\ChatRating::getAgentSummary($agentId);
        $recentRatings = \Quarksol\SmartChatbot\Models\ChatRating::forAgent($agentId, 20);

        return new \WP_REST_Response([
            'agent_id' => $agentId,
            'summary' => $summary,
            'recent' => array_map(fn($r) => $r->toArray(), $recentRatings),
        ], 200);
    }
}
