<?php
declare(strict_types=1);


/**
 * Agent Documents Controller
 * 
 * WordPress REST API endpoints for document section-to-agent assignment.
 * Mirrors AgentSkillsController.php for consistency.
 * 
 * @package Quarksol\SmartChatbot\Api
 */

namespace Quarksol\SmartChatbot\Api;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Documents\DocumentRegistry;
use Quarksol\SmartChatbot\Documents\DocumentSection;
use Quarksol\SmartChatbot\Models\ChatAgent;

/**
 * AgentDocumentsController - REST API for agent document assignment
 */
class AgentDocumentsController
{
    const NAMESPACE = 'smart-ai-chatbot/v1';
    
    /**
     * Register routes
     */
    public static function register(): void
    {
        // Get sections assigned to an agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>\d+)/documents', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAgentDocuments'],
            'permission_callback' => [self::class, 'canManageAgents'],
        ]);
        
        // Update section assignments for an agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>\d+)/documents', [
            'methods' => 'POST',
            'callback' => [self::class, 'updateAgentDocuments'],
            'permission_callback' => [self::class, 'canManageAgents'],
        ]);
        
        // Enable a single section for agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>\d+)/documents/(?P<section_id>[a-z0-9-]+)/enable', [
            'methods' => 'POST',
            'callback' => [self::class, 'enableSectionForAgent'],
            'permission_callback' => [self::class, 'canManageAgents'],
        ]);
        
        // Disable a single section for agent
        register_rest_route(self::NAMESPACE, '/agents/(?P<id>\d+)/documents/(?P<section_id>[a-z0-9-]+)/disable', [
            'methods' => 'POST',
            'callback' => [self::class, 'disableSectionForAgent'],
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
     * Get sections assigned to an agent
     */
    public static function getAgentDocuments(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        
        $config = null;
        $chatAgent = null;
        if (class_exists(ChatAgent::class)) {
            $chatAgent = is_numeric($agentId) ? ChatAgent::find((int) $agentId) : ChatAgent::findBySlug((string) $agentId);
            if ($chatAgent && $chatAgent->config) {
                $config = $chatAgent->config;
            }
        }
        
        if (!$config && is_numeric($agentId)) {
            $config = AgentConfig::load((int) $agentId);
        }
        if (!$config) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Agent not found'],
            ], 404);
        }
        
        $allSections = DocumentSection::getAll();
        
        $sectionData = [];
        $enabledCount = 0;
        foreach ($allSections as $section) {
            $enabled = $config->isSectionEnabled($section->id);
            if ($enabled) {
                $enabledCount++;
            }
            $sectionData[] = [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
                'document_count' => $section->getDocumentCount(),
                'enabled' => $enabled,
            ];
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'agent_id' => $agentId,
                'sections' => $sectionData,
                'enabled_count' => $enabledCount,
            ],
        ], 200);
    }
    
    /**
     * Update section assignments for an agent
     */
    public static function updateAgentDocuments(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        $data = $request->get_json_params();
        
        $config = null;
        $chatAgent = null;
        if (class_exists(ChatAgent::class)) {
            $chatAgent = is_numeric($agentId) ? ChatAgent::find((int) $agentId) : ChatAgent::findBySlug((string) $agentId);
            if ($chatAgent && $chatAgent->config) {
                $config = $chatAgent->config;
            }
        }
        
        if (!$config && is_numeric($agentId)) {
            $config = AgentConfig::load((int) $agentId);
        }
        if (!$config) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Agent not found'],
            ], 404);
        }
        
        // Get section IDs to enable
        $sectionIds = $data['enabled_sections'] ?? [];
        
        // Validate section IDs
        $validSections = [];
        $allSections = DocumentSection::getAll();
        $allSectionIds = array_map(fn($s) => $s->id, $allSections);
        
        foreach ($sectionIds as $sectionId) {
            if (in_array($sectionId, $allSectionIds, true)) {
                $validSections[] = $sectionId;
            }
        }
        
        // Update config
        $config->setEnabledSections($validSections);
        $config->sectionsConfigured = true;
        
        $saved = $chatAgent ? $chatAgent->save() : $config->save();
        
        if ($saved) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'agent_id' => $agentId,
                    'enabled_sections' => $validSections,
                    'message' => 'Document sections updated successfully',
                ],
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'SAVE_FAILED', 'message' => 'Failed to save section assignments'],
        ], 400);
    }
    
    /**
     * Enable a single section for agent
     */
    public static function enableSectionForAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        $sectionId = $request->get_param('section_id');
        
        $config = null;
        $chatAgent = null;
        if (class_exists(ChatAgent::class)) {
            $chatAgent = is_numeric($agentId) ? ChatAgent::find((int) $agentId) : ChatAgent::findBySlug((string) $agentId);
            if ($chatAgent && $chatAgent->config) {
                $config = $chatAgent->config;
            }
        }
        
        if (!$config && is_numeric($agentId)) {
            $config = AgentConfig::load((int) $agentId);
        }
        if (!$config) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Agent not found'],
            ], 404);
        }
        
        // Validate section exists
        if (!DocumentSection::exists($sectionId)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => "Section not found: {$sectionId}"],
            ], 404);
        }
        
        $enabledSections = $config->getEnabledSections();
        
        if (!in_array($sectionId, $enabledSections, true)) {
            $enabledSections[] = $sectionId;
            $config->setEnabledSections($enabledSections);
            $config->sectionsConfigured = true;
            $chatAgent ? $chatAgent->save() : $config->save();
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'section_id' => $sectionId,
                'enabled' => true,
                'message' => 'Section enabled for agent',
            ],
        ], 200);
    }
    
    /**
     * Disable a single section for agent
     */
    public static function disableSectionForAgent(\WP_REST_Request $request): \WP_REST_Response
    {
        $agentId = $request->get_param('id');
        $sectionId = $request->get_param('section_id');
        
        $config = null;
        $chatAgent = null;
        if (class_exists(ChatAgent::class)) {
            $chatAgent = is_numeric($agentId) ? ChatAgent::find((int) $agentId) : ChatAgent::findBySlug((string) $agentId);
            if ($chatAgent && $chatAgent->config) {
                $config = $chatAgent->config;
            }
        }
        
        if (!$config && is_numeric($agentId)) {
            $config = AgentConfig::load((int) $agentId);
        }
        if (!$config) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Agent not found'],
            ], 404);
        }
        
        $enabledSections = $config->getEnabledSections();
        
        if (in_array($sectionId, $enabledSections, true)) {
            $enabledSections = array_filter($enabledSections, fn($id) => $id !== $sectionId);
            $config->setEnabledSections(array_values($enabledSections));
            $config->sectionsConfigured = true;
            $chatAgent ? $chatAgent->save() : $config->save();
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'section_id' => $sectionId,
                'enabled' => false,
                'message' => 'Section disabled for agent',
            ],
        ], 200);
    }
}
