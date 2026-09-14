<?php
declare(strict_types=1);


/**
 * Skills API Controller
 * 
 * WordPress REST API endpoints for skill management.
 * 
 * @package Quarksol\SmartChatbot\Api
 */

namespace Quarksol\SmartChatbot\Api;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Skills\SkillRegistry;
use Quarksol\SmartChatbot\Skills\SkillManager;
use Quarksol\SmartChatbot\Skills\SkillValidator;
use Quarksol\SmartChatbot\Skills\SkillValidationException;
use Quarksol\SmartChatbot\Config\ChatbotConfig;

/**
 * SkillsController - REST API for skills
 */
class SkillsController
{
    const NAMESPACE = 'quark-agentflow-ai/v1';
    
    /**
     * Register routes
     */
    public static function register(): void
    {
        // List all skills
        register_rest_route(self::NAMESPACE, '/skills', [
            'methods' => 'GET',
            'callback' => [self::class, 'list'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Get single skill
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Create skill
        register_rest_route(self::NAMESPACE, '/skills', [
            'methods' => 'POST',
            'callback' => [self::class, 'create'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Update skill
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)', [
            'methods' => 'PUT',
            'callback' => [self::class, 'update'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Delete skill
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'delete'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // List references for a skill
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)/references', [
            'methods' => 'GET',
            'callback' => [self::class, 'listReferences'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Get reference content
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)/references/(?P<ref>[a-z0-9-.]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'getReference'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Create/update reference
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)/references', [
            'methods' => 'POST',
            'callback' => [self::class, 'createReference'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Delete reference
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)/references/(?P<ref>[a-z0-9-.]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'deleteReference'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Get categories
        register_rest_route(self::NAMESPACE, '/skill-categories', [
            'methods' => 'GET',
            'callback' => [self::class, 'getCategories'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // ============================================
        // Skill Groups Endpoints
        // ============================================
        
        // List all groups
        register_rest_route(self::NAMESPACE, '/skill-groups', [
            'methods' => 'GET',
            'callback' => [self::class, 'listGroups'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Create group
        register_rest_route(self::NAMESPACE, '/skill-groups', [
            'methods' => 'POST',
            'callback' => [self::class, 'createGroup'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Update group
        register_rest_route(self::NAMESPACE, '/skill-groups/(?P<group_id>[a-z0-9-]+)', [
            'methods' => 'PUT',
            'callback' => [self::class, 'updateGroup'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Delete group
        register_rest_route(self::NAMESPACE, '/skill-groups/(?P<group_id>[a-z0-9-]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'deleteGroup'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // ============================================
        // Analytics Endpoints
        // ============================================
        
        // Get overall skill analytics
        register_rest_route(self::NAMESPACE, '/skill-analytics', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAnalytics'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Get single skill analytics
        register_rest_route(self::NAMESPACE, '/skills/(?P<id>[a-z0-9-]+)/analytics', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSkillAnalytics'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Test skill with AI
        register_rest_route(self::NAMESPACE, '/skills/test', [
            'methods' => 'POST',
            'callback' => [self::class, 'testSkill'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
        
        // Get skill templates
        register_rest_route(self::NAMESPACE, '/skill-templates/(?P<id>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'getTemplate'],
            'permission_callback' => [self::class, 'canManageSkills'],
        ]);
    }
    
    /**
     * Permission check
     */
    public static function canManageSkills(): bool
    {
        
        return current_user_can('manage_options');
    }
    
    /**
     * List all skills with caching
     */
    public static function list(\WP_REST_Request $request): \WP_REST_Response
    {
        // Check for cached result (5 minute cache)
        $cacheKey = 'swc_skills_list_v2';
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => $cached,
            ], 200);
        }
        
        $skills = SkillRegistry::getAll();
        $categories = SkillManager::getCategories();
        
        $skillList = [];
        $categoryCounts = [];
        
        foreach ($skills as $skill) {
            $category = $skill->metadata['category'] ?? 'general';
            
            $skillList[] = [
                'id' => $skill->name,
                'name' => $skill->name,
                'display_name' => $skill->metadata['display_name'] ?? ucwords(str_replace('-', ' ', $skill->name)),
                'description' => $skill->description,
                'category' => $category,
                'tools_required' => $skill->toolsRequired,
                'always_on' => $skill->alwaysOn,
                'has_references' => $skill->hasReferences(),
                'reference_count' => count($skill->listReferences()),
                'group' => $skill->metadata['group'] ?? null,
            ];
            
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        }
        
        $data = [
            'skills' => $skillList,
            'categories' => array_map(fn($cat) => [
                ...$cat,
                'count' => $categoryCounts[$cat['id']] ?? 0,
            ], $categories),
            'total' => count($skillList),
        ];
        
        // Cache for 5 minutes
        set_transient($cacheKey, $data, 5 * MINUTE_IN_SECONDS);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }
    
    /**
     * Get single skill (for editing)
     */
    public static function get(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        
        $formData = SkillManager::getFormData($skillId);
        
        if (!$formData) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => "Skill not found: {$skillId}",
                ],
            ], 404);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $formData->toArray(),
        ], 200);
    }
    
    /**
     * Create skill
     */
    public static function create(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        
        // Sanitize
        $validator = new SkillValidator();
        $data = $validator->sanitize($data);
        
        // Convert to form data
        $formData = SkillFormData::fromArray($data);
        
        try {
            $skill = SkillManager::create($formData);
            
            // Invalidate cache
            delete_transient('swc_skills_list_v2');
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $skill->name,
                    'message' => 'Skill created successfully',
                    'path' => $skill->path,
                ],
            ], 201);
            
        } catch (SkillValidationException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                    'details' => $e->getErrors(),
                ],
            ], 400);
        }
    }
    
    /**
     * Update skill
     */
    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        $data = $request->get_json_params();
        
        // Sanitize
        $validator = new SkillValidator();
        $data = $validator->sanitize($data);
        
        // Ensure name matches
        $data['name'] = $skillId;
        
        // Convert to form data
        $formData = SkillFormData::fromArray($data);
        
        try {
            $skill = SkillManager::update($skillId, $formData);
            
            // Invalidate cache
            delete_transient('swc_skills_list_v2');
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $skill->name,
                    'message' => 'Skill updated successfully',
                ],
            ], 200);
            
        } catch (SkillValidationException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                    'details' => $e->getErrors(),
                ],
            ], 400);
        }
    }
    
    /**
     * Delete skill
     */
    public static function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        
        $deleted = SkillManager::delete($skillId);
        
        if ($deleted) {
            // Invalidate cache
            delete_transient('swc_skills_list_v2');
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'message' => 'Skill deleted successfully',
                ],
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => 'DELETE_FAILED',
                'message' => "Failed to delete skill: {$skillId}",
            ],
        ], 400);
    }
    
    /**
     * List references
     */
    public static function listReferences(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        
        $skill = SkillRegistry::getSkill($skillId);
        
        if (!$skill) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Skill not found'],
            ], 404);
        }
        
        $references = [];
        foreach ($skill->listReferences() as $refName) {
            $refPath = $skill->path . '/references/' . $refName;
            $references[] = [
                'name' => $refName,
                'title' => ucwords(str_replace(['-', '_', '.md'], [' ', ' ', ''], $refName)),
                'size' => file_exists($refPath) ? filesize($refPath) : 0,
            ];
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $references,
        ], 200);
    }
    
    /**
     * Get reference content
     */
    public static function getReference(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        $refName = $request->get_param('ref');
        
        $skill = SkillRegistry::getSkill($skillId);
        
        if (!$skill) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Skill not found'],
            ], 404);
        }
        
        $content = $skill->getReference($refName);
        
        if ($content === null) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Reference not found'],
            ], 404);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'name' => $refName,
                'title' => ucwords(str_replace(['-', '_', '.md'], [' ', ' ', ''], $refName)),
                'content' => $content,
            ],
        ], 200);
    }
    
    /**
     * Create/update reference
     */
    public static function createReference(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        $data = $request->get_json_params();
        
        $skill = SkillRegistry::getSkill($skillId);
        
        if (!$skill) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Skill not found'],
            ], 404);
        }
        
        $ref = \Quarksol\SmartChatbot\Skills\SkillReference::fromArray($data);
        $saved = $ref->save($skill->path);
        
        if ($saved) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'name' => $ref->name,
                    'message' => 'Reference saved successfully',
                ],
            ], 201);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'SAVE_FAILED', 'message' => 'Failed to save reference'],
        ], 400);
    }
    
    /**
     * Delete reference
     */
    public static function deleteReference(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        $refName = $request->get_param('ref');
        
        $skill = SkillRegistry::getSkill($skillId);
        
        if (!$skill) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Skill not found'],
            ], 404);
        }
        
        $ref = new \Quarksol\SmartChatbot\Skills\SkillReference();
        $ref->name = $refName;
        $deleted = $ref->delete($skill->path);
        
        if ($deleted) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => ['message' => 'Reference deleted successfully'],
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'DELETE_FAILED', 'message' => 'Failed to delete reference'],
        ], 400);
    }
    
    /**
     * Get categories
     */
    public static function getCategories(\WP_REST_Request $request): \WP_REST_Response
    {
        $categories = SkillManager::getCategories();
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $categories,
        ], 200);
    }
    
    // ============================================
    // Skill Groups Handlers
    // ============================================
    
    /**
     * List all groups
     */
    public static function listGroups(\WP_REST_Request $request): \WP_REST_Response
    {
        $groups = \Quarksol\SmartChatbot\Skills\SkillGroup::getAll();
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => array_map(fn($g) => $g->toArray(), $groups),
        ], 200);
    }
    
    /**
     * Create group
     */
    public static function createGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        
        // Generate ID from name if not provided
        if (empty($data['id']) && !empty($data['name'])) {
            $data['id'] = \Quarksol\SmartChatbot\Skills\SkillGroup::generateId($data['name']);
        }
        
        // Check if already exists
        $existing = \Quarksol\SmartChatbot\Skills\SkillGroup::get($data['id']);
        if ($existing) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'EXISTS', 'message' => 'Group already exists'],
            ], 400);
        }
        
        $group = \Quarksol\SmartChatbot\Skills\SkillGroup::fromArray($data);
        $saved = $group->save();
        
        if ($saved) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => $group->toArray(),
            ], 201);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'SAVE_FAILED', 'message' => 'Failed to save group'],
        ], 400);
    }
    
    /**
     * Update group
     */
    public static function updateGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $groupId = $request->get_param('group_id');
        $data = $request->get_json_params();
        
        $group = \Quarksol\SmartChatbot\Skills\SkillGroup::get($groupId);
        
        if (!$group) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Group not found'],
            ], 404);
        }
        
        // Update fields
        if (isset($data['name'])) $group->name = $data['name'];
        if (isset($data['description'])) $group->description = $data['description'];
        if (isset($data['icon'])) $group->icon = $data['icon'];
        if (isset($data['color'])) $group->color = $data['color'];
        if (isset($data['order'])) $group->order = (int)$data['order'];
        if (isset($data['parent_id'])) $group->parentId = $data['parent_id'];
        
        $saved = $group->save();
        
        if ($saved) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => $group->toArray(),
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'SAVE_FAILED', 'message' => 'Failed to update group'],
        ], 400);
    }
    
    /**
     * Delete group
     */
    public static function deleteGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $groupId = $request->get_param('group_id');
        
        $group = \Quarksol\SmartChatbot\Skills\SkillGroup::get($groupId);
        
        if (!$group) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Group not found'],
            ], 404);
        }
        
        $deleted = $group->delete();
        
        if ($deleted) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => ['message' => 'Group deleted successfully'],
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'DELETE_FAILED', 'message' => 'Failed to delete group'],
        ], 400);
    }
    
    // ============================================
    // Analytics Handlers
    // ============================================
    
    /**
     * Get overall analytics
     */
    public static function getAnalytics(\WP_REST_Request $request): \WP_REST_Response
    {
        $days = (int)($request->get_param('days') ?? 30);
        
        $stats = [
            'loads' => 0,
            'successes' => 0,
            'failures' => 0,
            'success_rate' => 0,
            'avg_tokens' => 0,
            'avg_response_time_ms' => 0,
        ];
        
        // Get topSkills and timeline from analytics
        $topSkills = [];
        $timeline = [];
        
        // Check if analytics class exists and table is ready
        if (class_exists('\Quarksol\SmartChatbot\Skills\SkillAnalytics')) {
            $topSkills = \Quarksol\SmartChatbot\Skills\SkillAnalytics::getTopSkills(10, $days);
            $timeline = \Quarksol\SmartChatbot\Skills\SkillAnalytics::getUsageTimeline($days);
            
            // Calculate aggregate stats
            foreach ($topSkills as $skill) {
                $stats['loads'] += $skill['loads'];
                $stats['successes'] += $skill['successes'];
            }
            
            if ($stats['loads'] > 0) {
                $stats['success_rate'] = round(($stats['successes'] / $stats['loads']) * 100, 1);
            }
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'topSkills' => $topSkills,
                'timeline' => $timeline,
            ],
        ], 200);
    }
    
    /**
     * Get single skill analytics
     */
    public static function getSkillAnalytics(\WP_REST_Request $request): \WP_REST_Response
    {
        $skillId = $request->get_param('id');
        $days = (int)($request->get_param('days') ?? 30);
        
        $stats = [];
        $timeline = [];
        
        if (class_exists('\Quarksol\SmartChatbot\Skills\SkillAnalytics')) {
            $stats = \Quarksol\SmartChatbot\Skills\SkillAnalytics::getStats($skillId, $days);
            $timeline = \Quarksol\SmartChatbot\Skills\SkillAnalytics::getUsageTimeline($days, $skillId);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'timeline' => $timeline,
            ],
        ], 200);
    }
    
    /**
     * Test skill with AI (simplified)
     */
    public static function testSkill(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $skillId = $data['skill_id'] ?? null;
        $message = $data['message'] ?? '';
        
        // For testing, return a simulated response
        // In production, this would call the actual AI with the skill context
        $responses = [
            "Based on the skill instructions, here's how I would respond to your query...",
            "I understand you're asking about {$message}. Let me help with that...",
            "Using the skill guidelines, I can assist you with this request...",
        ];
        
        $startTime = microtime(true);
        
        // Simulate some processing time
        usleep(random_int(100000, 500000));
        
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        $response = $responses[array_rand($responses)];
        $tokens = (int)(strlen($message . $response) / 4);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'response' => $response,
                'tokens_used' => $tokens,
                'response_time_ms' => $responseTime,
                'skill_id' => $skillId,
            ],
        ], 200);
    }
    
    /**
     * Get skill template
     */
    public static function getTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $templateId = $request->get_param('id');
        
        // Look for template in skills/templates directory
        $templatePath = SWC_CHATBOT_PATH . 'skills/templates/' . $templateId . '/SKILL.md';
        
        if (!file_exists($templatePath)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Template not found'],
            ], 404);
        }
        
        $content = file_get_contents($templatePath);
        
        // Parse frontmatter
        $metadata = [];
        $body = $content;
        
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontmatter = $matches[1];
            $body = $matches[2];
            
            // Simple YAML parsing
            foreach (explode("\n", $frontmatter) as $line) {
                if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $kv)) {
                    $metadata[$kv[1]] = trim($kv[2]);
                }
            }
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'id' => $templateId,
                'metadata' => $metadata,
                'body' => trim($body),
            ],
        ], 200);
    }
}
