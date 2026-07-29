<?php
declare(strict_types=1);


/**
 * Agent Factory
 * 
 * Creates NeuronAgent instances from configuration.
 * This is the single point of entry for instantiating agents at runtime.
 * 
 * @package Quarksol\SmartChatbot\Agent
 */

namespace Quarksol\SmartChatbot\Agent;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Config\ToolRegistry;
use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Services\Logger;
use Quarksol\SmartChatbot\Types\ProviderSettings;
use NeuronAI\SystemPrompt;

/**
 * Agent Factory
 * 
 * Creates properly configured NeuronAgent instances.
 */
class AgentFactory
{
    /**
     * Create an agent from agent ID
     * 
     * First checks database, then falls back to folder.
     * 
     * @param string $agentId Agent identifier
     * @param ProviderSettings|null $providerSettings Optional provider settings override
     * @return NeuronAgent
     * @throws \InvalidArgumentException If agent not found
     */
    public static function create(string $agentId, ?ProviderSettings $providerSettings = null): NeuronAgent
    {
        // Try database first
        $dbAgent = ChatAgent::findBySlug($agentId);
        
        if ($dbAgent) {
            return self::createFromChatAgent($dbAgent, $providerSettings);
        }
        
        // Fallback to folder
        $config = AgentRegistry::get($agentId);
        
        if ($config) {
            return self::createFromFolderConfig($config, $providerSettings);
        }
        
        throw new \InvalidArgumentException("Agent not found: {$agentId}");
    }
    
    /**
     * Create from database ChatAgent model
     */
    public static function createFromChatAgent(ChatAgent $chatAgent, ?ProviderSettings $providerSettings = null): NeuronAgent
    {
        $agent = NeuronAgent::make($providerSettings);
        
        if ($chatAgent->config) {
            Logger::debug('AgentFactory using ChatAgent config', [
                'agent_id' => $chatAgent->agentId,
                'mcp_configs_count' => count($chatAgent->config->mcpConfigs),
            ]);
            $agent->withConfig($chatAgent->config);
        } else {
            Logger::warning('AgentFactory missing ChatAgent config', [
                'agent_id' => $chatAgent->agentId,
            ]);
        }
        
        return $agent;
    }
    
    /**
     * Create from database ID
     */
    public static function createFromId(int $id, ?ProviderSettings $providerSettings = null): NeuronAgent
    {
        $dbAgent = ChatAgent::find($id);
        
        if (!$dbAgent) {
            throw new \InvalidArgumentException("Agent not found with ID: {$id}");
        }
        
        return self::createFromChatAgent($dbAgent, $providerSettings);
    }
    
    /**
     * Create from folder configuration array
     */
    public static function createFromFolderConfig(array $config, ?ProviderSettings $providerSettings = null): NeuronAgent
    {
        $agent = NeuronAgent::make($providerSettings);
        
        // Build AgentConfig from folder config
        $agentConfig = self::buildAgentConfigFromArray($config);
        $agent->withConfig($agentConfig);
        
        return $agent;
    }
    
    /**
     * Build AgentConfig from folder configuration array
     */
    public static function buildAgentConfigFromArray(array $config): AgentConfig
    {
        $agentId = $config['id'] ?? 'unknown';
        $name = $config['name'] ?? 'Agent';
        
        $agentConfig = new AgentConfig($agentId, $name);
        $agentConfig->description = $config['description'] ?? '';
        $agentConfig->isDefault = $config['is_default'] ?? false;
        
        // Toolkits
        $agentConfig->enabledToolkits = array_keys(array_filter($config['toolkits'] ?? []));
        $agentConfig->toolkitsConfigured = true;
        $agentConfig->disabledTools = $config['disabled_tools'] ?? [];
        
        // Skills
        $skills = $config['skills'] ?? [];
        $agentConfig->skillMode = $skills['mode'] ?? 'all';
        $agentConfig->enabledSkills = $skills['enabled'] ?? [];
        $agentConfig->disabledSkills = $skills['disabled'] ?? [];
        $agentConfig->skillsConfigured = array_key_exists('skills', $config);
        
        // Documents
        if (isset($config['documents']) && is_array($config['documents'])) {
            $agentConfig->enabledSections = $config['documents']['enabled_sections'] ?? [];
            $agentConfig->sectionsConfigured = true;
        } elseif (isset($config['enabled_sections'])) {
            $agentConfig->enabledSections = $config['enabled_sections'];
            $agentConfig->sectionsConfigured = true;
        }
        
        // MCP configs
        if (isset($config['mcp_configs']) && is_array($config['mcp_configs'])) {
            $agentConfig->mcpConfigs = $config['mcp_configs'];
        }

        // MCP Widget Security
        if (isset($config['mcp_widget_security']) && is_array($config['mcp_widget_security'])) {
            $agentConfig->mcpWidgetSecurity = $config['mcp_widget_security'];
        }
        
        // Knowledge toggle
        if (isset($config['knowledge_enabled'])) {
            $agentConfig->knowledgeEnabled = (bool) $config['knowledge_enabled'];
        } elseif (isset($config['knowledge']) && is_array($config['knowledge']) && array_key_exists('enabled', $config['knowledge'])) {
            $agentConfig->knowledgeEnabled = (bool) $config['knowledge']['enabled'];
        }

        // Knowledge sources
        $knowledgeSourcesConfigured = $config['knowledge_sources_configured'] ?? null;
        if (isset($config['knowledge_sources']) && is_array($config['knowledge_sources'])) {
            $agentConfig->enabledKnowledgeSources = array_map('absint', $config['knowledge_sources']);
            $agentConfig->knowledgeSourcesConfigured = true;
        } elseif (isset($config['knowledge']) && is_array($config['knowledge']) && isset($config['knowledge']['sources'])) {
            $sources = is_array($config['knowledge']['sources']) ? $config['knowledge']['sources'] : [];
            $agentConfig->enabledKnowledgeSources = array_map('absint', $sources);
            $agentConfig->knowledgeSourcesConfigured = true;
        } elseif ($knowledgeSourcesConfigured === true) {
            $agentConfig->enabledKnowledgeSources = [];
            $agentConfig->knowledgeSourcesConfigured = true;
        }
        
        // Prompts - store as sections
        $prompt = $config['prompt'] ?? [];
        $agentConfig->promptSections = [];
        
        if (!empty($prompt['background'])) {
            $agentConfig->promptSections['background'] = is_array($prompt['background']) 
                ? implode("\n", $prompt['background']) 
                : $prompt['background'];
        }
        if (!empty($prompt['steps'])) {
            $agentConfig->promptSections['steps'] = is_array($prompt['steps']) 
                ? implode("\n", $prompt['steps']) 
                : $prompt['steps'];
        }
        if (!empty($prompt['output'])) {
            $agentConfig->promptSections['output'] = is_array($prompt['output']) 
                ? implode("\n", $prompt['output']) 
                : $prompt['output'];
        }
        if (!empty($prompt['tools_usage'])) {
            $agentConfig->promptSections['tools_usage'] = is_array($prompt['tools_usage']) 
                ? implode("\n", $prompt['tools_usage']) 
                : $prompt['tools_usage'];
        }
        
        // Widget
        $widget = $config['widget'] ?? [];
        $agentConfig->welcomeMessage = $widget['welcome_message'] ?? '';
        $agentConfig->quickActions = $widget['quick_actions'] ?? [];
        
        return $agentConfig;
    }
    
    /**
     * Get default agent
     * 
     * Returns the agent marked as default, or the first available.
     */
    public static function getDefault(?ProviderSettings $providerSettings = null): NeuronAgent
    {
        // Try database default first
        $dbDefault = ChatAgent::getDefault();
        
        if ($dbDefault) {
            return self::createFromChatAgent($dbDefault, $providerSettings);
        }
        
        // Fallback to folder default
        $folderAgents = AgentRegistry::getAll();
        
        foreach ($folderAgents as $agentId => $config) {
            if (!empty($config['is_default'])) {
                return self::createFromFolderConfig($config, $providerSettings);
            }
        }
        
        // Return first available
        $first = reset($folderAgents);
        if ($first) {
            return self::createFromFolderConfig($first, $providerSettings);
        }
        
        // Absolute fallback - return base agent
        return NeuronAgent::make($providerSettings);
    }
    
    /**
     * Get all available agent IDs
     */
    public static function getAvailableAgentIds(): array
    {
        $ids = [];
        
        // From database
        $dbAgents = ChatAgent::all();
        foreach ($dbAgents as $agent) {
            $ids[$agent->agentId] = true;
        }
        
        // From folder
        foreach (AgentRegistry::getAgentIds() as $id) {
            $ids[$id] = true;
        }
        
        return array_keys($ids);
    }
}
