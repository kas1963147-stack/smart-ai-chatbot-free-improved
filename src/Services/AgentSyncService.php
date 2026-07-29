<?php
declare(strict_types=1);


/**
 * Agent Sync Service
 * 
 * Handles bidirectional synchronization between the database (ChatAgent)
 * and the filesystem (agents/ folder).
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Agent\AgentRegistry;

/**
 * Agent Sync Service
 * 
 * Synchronizes agents between database and filesystem.
 */
class AgentSyncService
{
    /**
     * Sync all agents from folder to database
     * 
     * Called on plugin activation or when needed.
     */
    public static function syncFromFolder(): array
    {
        $results = [
            'created' => [],
            'updated' => [],
            'deleted' => [],
            'errors' => [],
        ];
        
        $folderAgents = AgentRegistry::getAll();
        $folderAgentIds = array_keys($folderAgents);
        
        // Step 1: Create or update agents from folder
        foreach ($folderAgents as $agentId => $config) {
            try {
                $dbAgent = ChatAgent::findBySlug($agentId);
                
                if ($dbAgent) {
                    // Update existing agent
                    self::updateDbAgentFromConfig($dbAgent, $config);
                    $results['updated'][] = $agentId;
                } else {
                    // Create new agent
                    $dbAgent = self::createDbAgentFromConfig($config);
                    
                    $results['created'][] = $agentId;
                }
            } catch (\Exception $e) {
                $results['errors'][$agentId] = $e->getMessage();
            }
        }
        
        // Step 2: Purge DB agents that no longer exist in the /agents/ folder
        // DISABLED for Free version: We don't want to nuke the default database agents 
        // just because the JSON filesystem sync folder is empty.
        /*
        $allDbAgents = ChatAgent::all(false, true); // include inactive + hidden
        
        foreach ($allDbAgents as $dbAgent) {
            if (!in_array($dbAgent->agentId, $folderAgentIds, true)) {
                try {
                    $dbAgent->delete();
                    $results['deleted'][] = $dbAgent->agentId;
                } catch (\Exception $e) {
                    $results['errors'][$dbAgent->agentId] = 'Purge failed: ' . $e->getMessage();
                }
            }
        }
        */
        
        return $results;
    }
    
    /**
     * Sync a single agent from folder to database
     */
    public static function syncAgentFromFolder(string $agentId): ?ChatAgent
    {
        $config = AgentRegistry::get($agentId);
        
        if ($config === null) {
            return null;
        }
        
        $dbAgent = ChatAgent::findBySlug($agentId);
        
        if ($dbAgent) {
            self::updateDbAgentFromConfig($dbAgent, $config);
            return $dbAgent;
        } else {
            return self::createDbAgentFromConfig($config);
        }
    }
    
    /**
     * Sync agent from database to folder
     * 
     * Called when agent is created/updated via API.
     */
    public static function syncToFolder(ChatAgent $agent): bool
    {
        $config = self::buildConfigFromDbAgent($agent);
        return AgentRegistry::createAgent($config);
    }
    
    /**
     * Delete agent from folder when deleted from database
     */
    public static function deleteFromFolder(string $agentId): bool
    {
        return AgentRegistry::deleteAgent($agentId);
    }
    
    /**
     * Create a database agent from folder configuration
     */
    private static function createDbAgentFromConfig(array $config): ChatAgent
    {
        $agent = new ChatAgent();
        $agent->agentId = $config['id'];
        $agent->name = $config['name'];
        $agent->description = $config['description'] ?? '';
        $agent->avatar = $config['avatar'] ?? '';
        
        $configuredByDefault = self::isAgentConfigured($config);
        $agent->isActive = $config['is_active'] ?? $configuredByDefault;
        if (!$configuredByDefault) {
            $agent->isActive = false;
        }
        
        $agent->isDefault = $config['is_default'] ?? false;
        $agent->isHidden = $config['is_hidden'] ?? false;
        
        // Build AgentConfig
        $agentConfig = new AgentConfig($config['id'], $config['name']);
        $agentConfig->description = $config['description'] ?? '';
        
        // Toolkits
        $agentConfig->enabledToolkits = array_keys(array_filter($config['toolkits'] ?? []));
        $agentConfig->toolkitsConfigured = array_key_exists('toolkits', $config);
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
        
        // Prompts
        $prompt = $config['prompt'] ?? [];
        $agentConfig->promptSections = [
            'background' => implode("\n", $prompt['background'] ?? []),
            'steps' => implode("\n", $prompt['steps'] ?? []),
            'output' => implode("\n", $prompt['output'] ?? []),
            'tools_usage' => implode("\n", $prompt['tools_usage'] ?? []),
        ];
        
        // Widget
        $widget = $config['widget'] ?? [];
        $agentConfig->welcomeMessage = $widget['welcome_message'] ?? '';
        $agentConfig->quickActions = $widget['quick_actions'] ?? [];
        
        $agent->config = $agentConfig;
        $agent->save();
        
        return $agent;
    }
    
    /**
     * Update an existing database agent from folder configuration
     */
    private static function updateDbAgentFromConfig(ChatAgent $agent, array $config): void
    {
        $agent->name = $config['name'];
        $agent->description = $config['description'] ?? '';
        $agent->avatar = $config['avatar'] ?? '';
        
        // IMPORTANT: Do NOT overwrite is_active from folder config.
        // The is_active flag is a user preference set via the admin UI.
        // Only initial creation (createDbAgentFromConfig) should set the default.
        // The user's explicit toggle must be respected.
        
        $agent->isDefault = $config['is_default'] ?? false;
        $agent->isHidden = $config['is_hidden'] ?? false;
        
        // Update config
        $agentConfig = $agent->config ?? new AgentConfig($config['id'], $config['name']);
        $agentConfig->name = $config['name'];
        $agentConfig->description = $config['description'] ?? '';
        
        // Toolkits
        $agentConfig->enabledToolkits = array_keys(array_filter($config['toolkits'] ?? []));
        $agentConfig->toolkitsConfigured = array_key_exists('toolkits', $config);
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
        
        // Prompts
        $prompt = $config['prompt'] ?? [];
        $agentConfig->promptSections = [
            'background' => implode("\n", $prompt['background'] ?? []),
            'steps' => implode("\n", $prompt['steps'] ?? []),
            'output' => implode("\n", $prompt['output'] ?? []),
            'tools_usage' => implode("\n", $prompt['tools_usage'] ?? []),
        ];
        
        // Widget
        $widget = $config['widget'] ?? [];
        $agentConfig->welcomeMessage = $widget['welcome_message'] ?? '';
        $agentConfig->quickActions = $widget['quick_actions'] ?? [];
        
        $agent->config = $agentConfig;
        $agent->save();
    }
    
    /**
     * Build folder configuration from database agent
     */
    public static function buildConfigFromDbAgent(ChatAgent $agent): array
    {
        $config = $agent->config;
        
        // Build toolkits map
        $toolkits = [];
        foreach ($config->enabledToolkits ?? [] as $toolkit) {
            $toolkits[$toolkit] = true;
        }
        
        // Parse prompt sections back to arrays
        $promptSections = $config->promptSections ?? [];
        $prompt = [
            'background' => self::splitLines($promptSections['background'] ?? ''),
            'steps' => self::splitLines($promptSections['steps'] ?? ''),
            'output' => self::splitLines($promptSections['output'] ?? ''),
            'tools_usage' => self::splitLines($promptSections['tools_usage'] ?? ''),
        ];

        $skills = null;
        if ($config->skillsConfigured) {
            $skills = [
                'mode' => $config->skillMode ?? 'all',
                'enabled' => $config->enabledSkills ?? [],
                'disabled' => $config->disabledSkills ?? [],
            ];
        }

        $documents = null;
        if ($config->sectionsConfigured) {
            $documents = [
                'enabled_sections' => $config->enabledSections ?? [],
            ];
        }
        
        $payload = [
            'id' => $agent->agentId,
            'name' => $agent->name,
            'description' => $agent->description,
            'avatar' => $agent->avatar,
            'is_active' => $agent->isActive,
            'is_default' => $agent->isDefault,
            'toolkits' => $toolkits,
            'disabled_tools' => $config->disabledTools ?? [],
            'knowledge' => [
                'enabled' => $config->knowledgeEnabled ?? true,
                'namespace' => $agent->agentId,
            ],
            'mcp_configs' => $config->mcpConfigs ?? [],
            'mcp_widget_security' => $config->mcpWidgetSecurity ?? [],
            'prompt' => $prompt,
            'widget' => [
                'welcome_message' => $config->welcomeMessage ?? '',
                'quick_actions' => $config->quickActions ?? [],
            ],
        ];

        if ($config->knowledgeSourcesConfigured) {
            $payload['knowledge']['sources'] = $config->enabledKnowledgeSources ?? [];
        }

        if ($skills !== null) {
            $payload['skills'] = $skills;
        }

        if ($documents !== null) {
            $payload['documents'] = $documents;
        }

        return $payload;
    }
    
    /**
     * Split string into array of non-empty lines
     */
    private static function splitLines(string $text): array
    {
        if (empty($text)) {
            return [];
        }
        
        $lines = explode("\n", $text);
        return array_values(array_filter(array_map('trim', $lines)));
    }
    
    /**
     * Ensure all folder agents exist in database
     * 
     * Useful for initial setup or recovery.
     */
    public static function ensureAllAgentsInDatabase(): void
    {
        self::syncFromFolder();
    }

    /**
     * Check if an agent has any required tool or setup already configured.
     * Returns true if NO setup is required, or AT LEAST ONE required setup is configured.
     */
    public static function isAgentConfigured(array $config): bool
    {
        $hasRequiredTools = false;
        $isConfigured = false;

        // 1. Check Google Calendar for appointment booker
        $skills = $config['skills']['enabled'] ?? [];
        if (in_array('appointment-booking', $skills)) {
            $hasRequiredTools = true;
            if (class_exists('\Toolkits\GoogleWorkspace\GoogleOAuthHandler') && \Toolkits\GoogleWorkspace\GoogleOAuthHandler::isConfigured()) {
                $isConfigured = true;
            }
        }

        // 2. Check MCPs
        $mcps = array_keys($config['mcp_configs'] ?? []);
        if (!empty($mcps)) {
            $hasRequiredTools = true;
            foreach ($mcps as $mcp) {
                $optionKey = self::getToolOptionKey($mcp);
                if ($optionKey && !empty(get_option($optionKey))) {
                    $isConfigured = true;
                    break;
                }
            }
        }

        if ($hasRequiredTools) {
            return $isConfigured;
        }

        // No special tools required, automatically configured
        return true;
    }

    /**
     * Get the option key used to store the API key for an MCP.
     */
    private static function getToolOptionKey(string $toolId): ?string
    {
        $map = [
            'tavily' => 'swc_external_tavily_api_key',
            'github' => 'swc_external_github_token',
            'gmail' => 'swc_external_google_client_id',
            'slack' => 'swc_external_slack_bot_token',
            'notion' => 'swc_external_notion_token',
            'google-maps' => 'swc_external_google_client_id',
            'google_calendar' => 'swc_external_google_client_id',
        ];
        return $map[$toolId] ?? null;
    }

    /**
     * Re-evaluate inactive agents to see if their required tools are now configured.
     * Called when tools or API keys are updated.
     */
    public static function checkAndActivateAgents(): void
    {
        // Get all inactive agents (activeOnly = false, includeHidden = true)
        $allAgents = ChatAgent::all(false, true);
        
        foreach ($allAgents as $agent) {
            if (!$agent->isActive) {
                // Return true config directly from filesystem to check requirements accurately
                $rawConfig = AgentRegistry::get($agent->agentId);
                
                // Fallback to DB config if not found
                if (!$rawConfig) {
                    $rawConfig = self::buildConfigFromDbAgent($agent);
                }

                if (self::isAgentConfigured($rawConfig)) {
                    $agent->isActive = true;
                    $agent->save();
                }
            }
        }
    }

    /**
     * One-time migration: apply curated internal_mcp_config from default-agents.php
     * to existing pre-built agents in the database.
     * 
     * Runs once on init. Only updates agents whose internal_mcp_config is empty
     * (never configured by the user). If user has already customized, skip.
     */
    public static function migrateInternalMcpConfig(): void
    {
        $optionKey = 'swc_migrated_internal_mcp_v1';

        // Already migrated? Skip.
        if (get_option($optionKey)) {
            return;
        }

        $defaultAgentsPath = dirname(__DIR__) . '/Config/default-agents.php';
        if (!file_exists($defaultAgentsPath)) {
            return;
        }

        $defaultAgents = include $defaultAgentsPath;
        if (!is_array($defaultAgents)) {
            return;
        }

        // Build a map: agent_id => curated internal_mcp_config
        $curatedConfigs = [];
        foreach ($defaultAgents as $agentDef) {
            $agentId = $agentDef['agent_id'] ?? '';
            $mcpConfig = $agentDef['config']['internal_mcp_config'] ?? null;
            if ($agentId && $mcpConfig) {
                $curatedConfigs[$agentId] = $mcpConfig;
            }
        }

        if (empty($curatedConfigs)) {
            update_option($optionKey, time());
            return;
        }

        // Load all agents from DB (including inactive + hidden)
        $allAgents = ChatAgent::all(false, true);
        $updated = 0;

        foreach ($allAgents as $agent) {
            // Only update if this agent has a curated config AND the user hasn't configured it
            if (!isset($curatedConfigs[$agent->agentId])) {
                continue;
            }

            $existingMcpConfig = $agent->config->internalMcpConfig ?? [];
            
            // Skip if user has already explicitly configured internal MCP
            if (!empty($existingMcpConfig) && isset($existingMcpConfig['mode'])) {
                continue;
            }

            // Apply curated config
            $agent->config->internalMcpConfig = $curatedConfigs[$agent->agentId];
            $agent->save();
            $updated++;

            error_log("[MCP Migration] Updated agent '{$agent->agentId}' with curated internal_mcp_config (" 
                . count($curatedConfigs[$agent->agentId]['enabled_tools'] ?? []) . " tools)");
        }

        // Mark migration as done
        update_option($optionKey, time());
        error_log("[MCP Migration] Complete. Updated {$updated} agents.");
    }
}
