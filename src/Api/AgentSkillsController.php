<?php
declare(strict_types=1);


/**
 * Agent Skills Controller
 * 
 * WordPress REST API endpoints for skill-to-agent assignment.
 * 
 * @package Quarksol\SmartChatbot\Api
 */

namespace Quarksol\SmartChatbot\Api;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Skills\SkillRegistry;
use Quarksol\SmartChatbot\Config\ChatbotConfig;

/**
 * AgentSkillsController - REST API for agent skill assignment
 */
class AgentSkillsController
{
    const NAMESPACE = 'quark-agentflow-ai/v1';
    
    /**
     * Register routes
     */
    public static function register(): void
    {
        // Get skills for an agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>[a-z0-9-]+)/skills', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAgentSkills'],
            'permission_callback' => [self::class, 'canManageAgents'],
        ]);
        
        // Update skill assignments for an agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>[a-z0-9-]+)/skills', [
            'methods' => 'PUT',
            'callback' => [self::class, 'updateAgentSkills'],
            'permission_callback' => [self::class, 'canManageAgents'],
        ]);
        
        // Enable a single skill for agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>[a-z0-9-]+)/skills/(?P<skillId>[a-z0-9-]+)/enable', [
            'methods' => 'POST',
            'callback' => [self::class, 'enableSkillForAgent'],
            'permission_callback' => [self::class, 'canManageAgents'],
        ]);
        
        // Disable a single skill for agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>[a-z0-9-]+)/skills/(?P<skillId>[a-z0-9-]+)/disable', [
            'methods' => 'POST',
            'callback' => [self::class, 'disableSkillForAgent'],
            'permission_callback' => [self::class, 'canManageAgents'],
        ]);
    }
    
    /**
     * Permission check
     */
    public static function canManageAgents(): bool
    {
        
        return current_user_can('manage_options');
    }
    
    /**
     * Get skills assigned to an agent
     */
    public static function getAgentSkills(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        
        $config = null;
        if (class_exists('\Quarksol\SmartChatbot\Models\ChatAgent')) {
            $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
            if (!$chatAgent && is_numeric($agentId)) {
                $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find((int) $agentId);
            }
            if ($chatAgent && $chatAgent->config) {
                $config = $chatAgent->config;
            }
        }
        
        if (!$config) {
            $config = AgentConfig::fromDatabase($agentId);
        }
        
        if (!$config) {
            // Return default (all skills enabled)
            $config = new AgentConfig($agentId);
        }
        
        // Get all skills with enabled status
        $allSkills = SkillRegistry::getAll();
        $skillList = [];
        
        foreach ($allSkills as $skill) {
            $skillList[] = [
                'id' => $skill->name,
                'name' => $skill->name,
                'display_name' => $skill->metadata['display_name'] ?? ucwords(str_replace('-', ' ', $skill->name)),
                'description' => $skill->description,
                'category' => $skill->metadata['category'] ?? 'general',
                'enabled' => $config->isSkillEnabled($skill->name),
            ];
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'agent_id' => $agentId,
                'skill_mode' => $config->skillMode,
                'enabled_skills' => $config->enabledSkills,
                'disabled_skills' => $config->disabledSkills,
                'skills' => $skillList,
            ],
        ], 200);
    }
    
    /**
     * Update skill assignments for an agent
     */
    public static function updateAgentSkills(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        $data = $request->get_json_params();
        
        // Try to find the ChatAgent in database first
        $chatAgent = null;
        if (class_exists('\Quarksol\SmartChatbot\Models\ChatAgent')) {
            // Try by slug first
            $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
            
            // If not found by slug, try by numeric ID
            if (!$chatAgent && is_numeric($agentId)) {
                $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find((int) $agentId);
            }
        }
        
        if ($chatAgent && $chatAgent->config) {
            // Update skills on the ChatAgent's config
            $config = $chatAgent->config;
            
            if (isset($data['skill_mode'])) {
                $config->skillMode = $data['skill_mode'];
            }
            
            if (isset($data['enabled_skills'])) {
                $config->enabledSkills = $data['enabled_skills'];
            }
            
            if (isset($data['disabled_skills'])) {
                $config->disabledSkills = $data['disabled_skills'];
            }
            $config->skillsConfigured = true;
            
            // Save to ChatAgent database table (not wp_options)
            $chatAgent->save();
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'message' => 'Agent skills updated successfully',
                    'skill_mode' => $config->skillMode,
                    'enabled_skills' => $config->enabledSkills,
                    'disabled_skills' => $config->disabledSkills,
                ],
            ], 200);
        }
        
        // Fallback for folder-based agents: use wp_options
        $config = AgentConfig::fromDatabase($agentId);
        
        if (!$config) {
            $config = new AgentConfig($agentId);
        }
        
        // Update skill settings
        if (isset($data['skill_mode'])) {
            $config->skillMode = $data['skill_mode'];
        }
        
        if (isset($data['enabled_skills'])) {
            $config->enabledSkills = $data['enabled_skills'];
        }
        
        if (isset($data['disabled_skills'])) {
            $config->disabledSkills = $data['disabled_skills'];
        }
        $config->skillsConfigured = true;
        
        $config->save();
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'message' => 'Agent skills updated successfully',
                'skill_mode' => $config->skillMode,
                'enabled_skills' => $config->enabledSkills,
                'disabled_skills' => $config->disabledSkills,
            ],
        ], 200);
    }
    
    /**
     * Enable a single skill for agent
     */
    public static function enableSkillForAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        $skillId = $request->get_param('skillId');
        
        // Try to find the ChatAgent in database first
        $chatAgent = null;
        if (class_exists('\Quarksol\SmartChatbot\Models\ChatAgent')) {
            $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
            if (!$chatAgent && is_numeric($agentId)) {
                $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find((int) $agentId);
            }
        }
        
        if ($chatAgent && $chatAgent->config) {
            $chatAgent->config->enableSkill($skillId);
            $chatAgent->config->skillsConfigured = true;
            $chatAgent->save();
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'message' => "Skill '{$skillId}' enabled for agent '{$agentId}'",
                ],
            ], 200);
        }
        
        // Fallback for folder-based agents
        $config = AgentConfig::fromDatabase($agentId);
        
        if (!$config) {
            $config = new AgentConfig($agentId);
        }
        
        $config->enableSkill($skillId);
        $config->skillsConfigured = true;
        $config->save();
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'message' => "Skill '{$skillId}' enabled for agent '{$agentId}'",
            ],
        ], 200);
    }
    
    /**
     * Disable a single skill for agent
     */
    public static function disableSkillForAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        $skillId = $request->get_param('skillId');
        
        // Try to find the ChatAgent in database first
        $chatAgent = null;
        if (class_exists('\Quarksol\SmartChatbot\Models\ChatAgent')) {
            $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
            if (!$chatAgent && is_numeric($agentId)) {
                $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find((int) $agentId);
            }
        }
        
        if ($chatAgent && $chatAgent->config) {
            $chatAgent->config->disableSkill($skillId);
            $chatAgent->config->skillsConfigured = true;
            $chatAgent->save();
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'message' => "Skill '{$skillId}' disabled for agent '{$agentId}'",
                ],
            ], 200);
        }
        
        // Fallback for folder-based agents
        $config = AgentConfig::fromDatabase($agentId);
        
        if (!$config) {
            $config = new AgentConfig($agentId);
        }
        
        $config->disableSkill($skillId);
        $config->skillsConfigured = true;
        $config->save();
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'message' => "Skill '{$skillId}' disabled for agent '{$agentId}'",
            ],
        ], 200);
    }
}
