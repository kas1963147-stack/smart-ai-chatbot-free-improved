<?php
declare(strict_types=1);


/**
 * Agent Configuration
 * 
 * Data class for storing and managing agent configuration.
 * Supports admin-controlled tool selection and editable prompts.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Services\Logger;

/**
 * Agent Configuration Data Class
 * 
 * Stores configuration for an agent including:
 * - Enabled/disabled toolkits and tools
 * - Editable prompt sections
 * - Agent metadata
 */
class AgentConfig
{
    /** WordPress option prefix */
    const OPTION_PREFIX = 'swc_chatbot_config_';

    /** Agent identifier */
    public string $agentId;

    /** Display name */
    public string $name;

    /** Description */
    public string $description = '';

    /** Enabled toolkit IDs */
    public array $enabledToolkits = [];

    /** Whether toolkits have been explicitly configured (distinguishes [] from unconfigured) */
    public bool $toolkitsConfigured = false;

    /** Explicitly enabled tool IDs (whitelist) */
    public array $enabledTools = [];

    /** Explicitly disabled tool IDs (blacklist) */
    public array $disabledTools = [];

    /** Editable prompt sections */
    public array $promptSections = [];

    /** Whether this is a default/built-in agent */
    public bool $isDefault = false;

    /** Creation timestamp */
    public ?int $createdAt = null;

    /** Last modified timestamp */
    public ?int $modifiedAt = null;

    // ============================================
    // Chat-Specific Configuration (Phase 2 extensions)
    // ============================================

    /** Welcome message for chat widget */
    public string $welcomeMessage = 'Hi! How can I help you today?';

    /** Quick action buttons shown in chat */
    public array $quickActions = [];

    /** Starter prompts (auto-suggestions) shown when chat opens */
    public array $starterPrompts = [];

    // ============================================
    // MCP Configuration (New)
    // ============================================

    /** 
     * Configured MCP servers 
     * Structure: [ 'mcp-id' => [ 'enabled' => bool, 'config' => array ] ]
     */
    public array $mcpConfigs = [];

    /** Agent personality settings (tone, style) */
    public array $personality = [];

    /** Maximum number of messages to keep in session history */
    public int $maxHistoryLength = 50;

    // ============================================
    // Skill Assignment Configuration
    // ============================================

    /** Enabled skill IDs (for whitelist mode) */
    public array $enabledSkills = [];

    /** Disabled skill IDs (for blacklist mode) */
    public array $disabledSkills = [];

    /** Skill mode: 'all', 'whitelist', 'blacklist' */
    public string $skillMode = 'all';

    /** Whether skill assignments have been explicitly configured */
    public bool $skillsConfigured = false;

    // ============================================
    // Document Section Assignment
    // ============================================

    /** Enabled document section IDs */
    public array $enabledSections = [];

    /** Whether document sections have been explicitly configured */
    public bool $sectionsConfigured = false;

    // ============================================
    // Knowledge/RAG Configuration
    // ============================================

    /** Whether knowledge base augmentation is enabled for this agent */
    public bool $knowledgeEnabled = true;

    /** Enabled knowledge source IDs */
    public array $enabledKnowledgeSources = [];

    /** Whether knowledge sources have been explicitly configured */
    public bool $knowledgeSourcesConfigured = false;

    // ============================================
    // Tool Configuration
    // ============================================

    /** Tool-specific configurations (API keys, settings, etc.) */
    public array $toolConfigs = [];

    /** Global toolkit configurations */
    public array $toolkitConfigs = [];

    // ============================================
    // Provider Instance Selection
    // ============================================

    /** Selected provider instance ID (null = use global/default) */
    public ?string $providerInstanceId = null;

    // ============================================
    // Internal MCP Configuration
    // ============================================

    /** Internal MCP tool configuration (per-agent WordPress tool access control) */
    public array $internalMcpConfig = [];

    // ============================================
    // MCP Widget Security
    // ============================================

    /**
     * MCP widget security configuration.
     * 
     * Controls which external MCP tools are accessible when this agent
     * is used in a public-facing widget (non-admin context).
     * 
     * Structure:
     * [
     *   'allow_external_mcp' => bool,     // Master switch (default: false)
     *   'allowed_tools'      => string[], // Whitelist of tool names allowed in widget
     *   'blocked_tools'      => string[], // Blacklist (overrides allowed, always blocked)
     * ]
     */
    public array $mcpWidgetSecurity = [];

    // ============================================
    // Interactive Options (AI-Driven Quick Replies)
    // ============================================

    /** Whether the AI should present clickable option buttons during conversation */
    public bool $interactiveOptionsEnabled = true;

    /**
     * Create new configuration
     */
    public function __construct(string $agentId, string $name = '')
    {
        $this->agentId = $agentId;
        $this->name = $name ?: ucfirst($agentId) . ' Agent';
        $this->createdAt = time();
        $this->modifiedAt = time();
    }

    /**
     * Load configuration from WordPress database
     */
    public static function fromDatabase(string $agentId): ?self
    {
        if (!function_exists('get_option')) {
            return null;
        }

        $data = get_option(self::OPTION_PREFIX . $agentId, null);

        if (!$data || !is_array($data)) {
            return null;
        }

        return self::fromArray($data);
    }

    /**
     * Alias for fromDatabase for consistency with other registries
     */
    public static function load(int $agentId): ?self
    {
        return self::fromDatabase((string) $agentId);
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        Logger::debug('AgentConfig loading from array', [
            'agent_id' => $data['agent_id'] ?? 'unknown',
            'has_mcp_configs' => array_key_exists('mcp_configs', $data),
        ]);

        $config = new self(
            $data['agent_id'] ?? 'unknown',
            $data['name'] ?? ''
        );

        $config->description = $data['description'] ?? '';
        $config->enabledToolkits = $data['enabled_toolkits'] ?? [];
        // Mark as configured if: 
        // 1. toolkits_configured flag is explicitly true in saved data, OR
        // 2. enabled_toolkits key exists (for backwards compatibility with existing saves)
        $config->toolkitsConfigured = ($data['toolkits_configured'] ?? false) || array_key_exists('enabled_toolkits', $data);
        $config->enabledTools = $data['enabled_tools'] ?? [];
        $config->disabledTools = $data['disabled_tools'] ?? [];
        $config->promptSections = $data['prompt_sections'] ?? [];
        $config->isDefault = $data['is_default'] ?? false;
        $config->createdAt = $data['created_at'] ?? null;
        $config->modifiedAt = $data['modified_at'] ?? null;

        // Sanitize Chat-specific fields
        $config->welcomeMessage = sanitize_textarea_field($data['welcome_message'] ?? 'Hi! How can I help you today?');
        $config->quickActions = isset($data['quick_actions']) && is_array($data['quick_actions'])
            ? array_map(function ($action) {
                return [
                    'label' => sanitize_text_field($action['label'] ?? ''),
                    'action' => sanitize_text_field($action['action'] ?? ''),
                    'params' => isset($action['params']) && is_array($action['params']) ? array_map('sanitize_text_field', $action['params']) : []
                ];
            }, $data['quick_actions'])
            : [];

        $config->starterPrompts = isset($data['starter_prompts']) && is_array($data['starter_prompts'])
            ? array_map('sanitize_text_field', $data['starter_prompts'])
            : [];

        // Load MCP Configs (No sanitization here as it contains specific structure, validation happens at runtime)
        $config->mcpConfigs = isset($data['mcp_configs']) && is_array($data['mcp_configs'])
            ? $data['mcp_configs']
            : [];

        Logger::debug('AgentConfig MCP configs loaded', [
            'mcp_configs_count' => count($config->mcpConfigs),
            'mcp_config_keys' => array_keys($config->mcpConfigs),
        ]);


        $config->personality = isset($data['personality']) && is_array($data['personality'])
            ? array_map('sanitize_text_field', $data['personality'])
            : [];

        $config->maxHistoryLength = absint($data['max_history_length'] ?? 50);

        // Sanitize prompt sections
        if (isset($data['prompt_sections']) && is_array($data['prompt_sections'])) {
            $config->promptSections = array_map('sanitize_textarea_field', $data['prompt_sections']);
        }

        // Skill assignment fields
        $config->enabledSkills = array_map('sanitize_key', $data['enabled_skills'] ?? []);
        $config->disabledSkills = array_map('sanitize_key', $data['disabled_skills'] ?? []);
        $config->skillMode = sanitize_key($data['skill_mode'] ?? 'all');
        if (array_key_exists('skills_configured', $data)) {
            $config->skillsConfigured = (bool) $data['skills_configured'];
        } else {
            $config->skillsConfigured = array_key_exists('enabled_skills', $data)
                || array_key_exists('disabled_skills', $data)
                || array_key_exists('skill_mode', $data);
        }

        // Document section fields
        $config->enabledSections = array_map('sanitize_title', $data['enabled_sections'] ?? []);
        if (array_key_exists('sections_configured', $data)) {
            $config->sectionsConfigured = (bool) $data['sections_configured'];
        } else {
            $config->sectionsConfigured = array_key_exists('enabled_sections', $data);
        }

        // Knowledge/RAG fields
        if (isset($data['knowledge_enabled'])) {
            $config->knowledgeEnabled = (bool) $data['knowledge_enabled'];
        } elseif (isset($data['knowledge']) && is_array($data['knowledge']) && array_key_exists('enabled', $data['knowledge'])) {
            $config->knowledgeEnabled = (bool) $data['knowledge']['enabled'];
        }

        $knowledgeSources = [];
        if (isset($data['knowledge_sources']) && is_array($data['knowledge_sources'])) {
            $knowledgeSources = $data['knowledge_sources'];
        } elseif (isset($data['knowledge']) && is_array($data['knowledge']) && isset($data['knowledge']['sources'])) {
            $knowledgeSources = is_array($data['knowledge']['sources']) ? $data['knowledge']['sources'] : [];
        }
        $config->enabledKnowledgeSources = array_map('absint', $knowledgeSources);
        if (array_key_exists('knowledge_sources_configured', $data)) {
            $config->knowledgeSourcesConfigured = (bool) $data['knowledge_sources_configured'];
        } else {
            // Only mark as "configured" if there are actual source IDs selected.
            // An empty array means the user hasn't picked specific sources yet,
            // so we should treat it as "unrestricted" (null), not "none allowed" ([]).
            $config->knowledgeSourcesConfigured = !empty($knowledgeSources);
        }

        // Tool configuration fields (recursive sanitization)
        $sanitize_config_recursive = function ($item) use (&$sanitize_config_recursive) {
            if (is_array($item)) {
                return array_map($sanitize_config_recursive, $item);
            }
            return sanitize_text_field($item);
        };

        $config->toolConfigs = isset($data['tool_configs']) && is_array($data['tool_configs'])
            ? $sanitize_config_recursive($data['tool_configs'])
            : [];

        $config->toolkitConfigs = isset($data['toolkit_configs']) && is_array($data['toolkit_configs'])
            ? $sanitize_config_recursive($data['toolkit_configs'])
            : [];

        // Provider instance selection
        $config->providerInstanceId = isset($data['provider_instance_id']) && !empty($data['provider_instance_id'])
            ? sanitize_text_field($data['provider_instance_id'])
            : null;

        // Internal MCP configuration (per-agent WordPress tool access control)
        $config->internalMcpConfig = isset($data['internal_mcp_config']) && is_array($data['internal_mcp_config'])
            ? $data['internal_mcp_config']
            : [];

        // MCP Widget Security configuration
        $config->mcpWidgetSecurity = isset($data['mcp_widget_security']) && is_array($data['mcp_widget_security'])
            ? $data['mcp_widget_security']
            : [];

        // Interactive Options (AI-driven quick replies)
        $config->interactiveOptionsEnabled = (bool) ($data['interactive_options_enabled'] ?? true);

        // Legacy fallback: ensure content_editor has WooCommerce access when not explicitly configured
        if (
            $config->agentId === 'content_editor'
            && !$config->toolkitsConfigured
            && !in_array('WooCommerce', $config->enabledToolkits, true)
        ) {
            $config->enabledToolkits[] = 'WooCommerce';
        }

        return $config;
    }

    /**
     * Convert to array for storage
     */
    public function toArray(): array
    {
        return [
            'agent_id' => $this->agentId,
            'name' => $this->name,
            'description' => $this->description,
            'enabled_toolkits' => $this->enabledToolkits,
            'toolkits_configured' => $this->toolkitsConfigured,
            'enabled_tools' => $this->enabledTools,
            'disabled_tools' => $this->disabledTools,
            'prompt_sections' => $this->promptSections,
            'is_default' => $this->isDefault,
            'created_at' => $this->createdAt,
            'modified_at' => $this->modifiedAt,
            // Chat-specific fields
            'welcome_message' => $this->welcomeMessage,
            'quick_actions' => $this->quickActions,
            'starter_prompts' => $this->starterPrompts,
            'mcp_configs' => $this->mcpConfigs,
            'personality' => $this->personality,
            'max_history_length' => $this->maxHistoryLength,
            'enabled_skills' => $this->enabledSkills,
            'disabled_skills' => $this->disabledSkills,
            'skill_mode' => $this->skillMode,
            'skills_configured' => $this->skillsConfigured,
            // Document section fields
            'enabled_sections' => $this->enabledSections,
            'sections_configured' => $this->sectionsConfigured,
            // Knowledge/RAG fields
            'knowledge_enabled' => $this->knowledgeEnabled,
            'knowledge_sources' => $this->enabledKnowledgeSources,
            'knowledge_sources_configured' => $this->knowledgeSourcesConfigured,
            // Tool configuration fields
            'tool_configs' => $this->toolConfigs,
            'toolkit_configs' => $this->toolkitConfigs,
            // Provider instance selection
            'provider_instance_id' => $this->providerInstanceId,
            // Internal MCP configuration
            'internal_mcp_config' => $this->internalMcpConfig,
            // MCP Widget Security
            'mcp_widget_security' => $this->mcpWidgetSecurity,
            // Interactive Options
            'interactive_options_enabled' => $this->interactiveOptionsEnabled,
        ];
    }

    /**
     * Save configuration to WordPress database
     */
    public function save(): bool
    {
        if (!function_exists('update_option')) {
            return false;
        }

        $this->modifiedAt = time();
        return update_option(self::OPTION_PREFIX . $this->agentId, $this->toArray());
    }

    /**
     * Delete configuration from database
     */
    public function delete(): bool
    {
        if (!function_exists('delete_option')) {
            return false;
        }

        return delete_option(self::OPTION_PREFIX . $this->agentId);
    }

    /**
     * Check if a toolkit is enabled
     */
    public function isToolkitEnabled(string $toolkitId): bool
    {
        // Treat explicit "none" sentinel as all disabled
        if (in_array('__none__', $this->enabledToolkits, true)) {
            return false;
        }

        // If toolkits have NOT been explicitly configured, only enable core toolkits (sensible defaults)
        // This prevents the 128-tool API limit from being exceeded by enabling all ~200 tools
        if (!$this->toolkitsConfigured) {
            $coreToolkits = [
                'wordpress_core',
                'wordpress_content',
                'gutenberg',
                'integrations',
                'woocommerce', // Only if WooCommerce is active
            ];
            return in_array(strtolower($toolkitId), array_map('strtolower', $coreToolkits), true);
        }

        // If toolkits ARE configured but empty, none are enabled
        if (empty($this->enabledToolkits)) {
            return false;
        }

        // Case-insensitive match - UI may save 'WooCommerce' but ToolRegistry uses 'woocommerce'
        $toolkitIdLower = strtolower($toolkitId);
        foreach ($this->enabledToolkits as $enabledId) {
            if (strtolower($enabledId) === $toolkitIdLower) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a tool is enabled
     * 
     * Logic:
     * 1. If tool is in disabledTools → disabled
     * 2. If enabledTools is not empty and tool not in it → disabled
     * 3. Otherwise → enabled
     */
    public function isToolEnabled(string $toolId): bool
    {
        // Explicit disable takes priority
        if (in_array($toolId, $this->disabledTools, true)) {
            return false;
        }

        // If whitelist is empty, all tools are enabled (except blacklisted)
        if (empty($this->enabledTools)) {
            return true;
        }

        // Check whitelist
        return in_array($toolId, $this->enabledTools, true);
    }

    /**
     * Enable a toolkit
     */
    public function enableToolkit(string $toolkitId): self
    {
        if (!in_array($toolkitId, $this->enabledToolkits, true)) {
            $this->enabledToolkits[] = $toolkitId;
        }
        return $this;
    }

    /**
     * Disable a toolkit
     */
    public function disableToolkit(string $toolkitId): self
    {
        $this->enabledToolkits = array_filter(
            $this->enabledToolkits,
            fn($id) => $id !== $toolkitId
        );
        return $this;
    }

    /**
     * Enable a specific tool
     */
    public function enableTool(string $toolId): self
    {
        // Remove from disabled list
        $this->disabledTools = array_filter(
            $this->disabledTools,
            fn($id) => $id !== $toolId
        );

        // Add to enabled list if using whitelist mode
        if (!empty($this->enabledTools) && !in_array($toolId, $this->enabledTools, true)) {
            $this->enabledTools[] = $toolId;
        }

        return $this;
    }

    /**
     * Disable a specific tool
     */
    public function disableTool(string $toolId): self
    {
        // Add to disabled list
        if (!in_array($toolId, $this->disabledTools, true)) {
            $this->disabledTools[] = $toolId;
        }

        // Remove from enabled list
        $this->enabledTools = array_filter(
            $this->enabledTools,
            fn($id) => $id !== $toolId
        );

        return $this;
    }

    /**
     * Set a prompt section
     */
    public function setPromptSection(string $sectionId, string $content): self
    {
        $this->promptSections[$sectionId] = $content;
        return $this;
    }

    /**
     * Get a prompt section
     */
    public function getPromptSection(string $sectionId, string $default = ''): string
    {
        return $this->promptSections[$sectionId] ?? $default;
    }

    /**
     * Get all agent configurations from database
     */
    public static function getAllConfigs(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }

        global $wpdb;

        $prefix = self::OPTION_PREFIX;
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix . '%'
            )
        );

        $configs = [];
        foreach ($results as $optionName) {
            $agentId = str_replace($prefix, '', $optionName);
            $config = self::fromDatabase($agentId);
            if ($config) {
                $configs[$agentId] = $config;
            }
        }

        return $configs;
    }

    // ============================================
    // Skill Assignment Methods
    // ============================================

    /**
     * Check if a skill is enabled for this agent
     */
    public function isSkillEnabled(string $skillId): bool
    {
        $mode = $this->skillMode;

        // UI uses enabled_skills without setting skill_mode - treat as whitelist if list is present.
        if ($mode === 'all' && $this->skillsConfigured && empty($this->enabledSkills) && empty($this->disabledSkills)) {
            return false;
        }

        if ($mode === 'all' && !empty($this->enabledSkills)) {
            $mode = 'whitelist';
        }

        switch ($mode) {
            case 'whitelist':
            case 'selected':
                return in_array($skillId, $this->enabledSkills, true);
            case 'blacklist':
                return !in_array($skillId, $this->disabledSkills, true);
            case 'none':
                return false;
            case 'all':
            default:
                return !in_array($skillId, $this->disabledSkills, true);
        }
    }

    /**
     * Enable a skill for this agent
     */
    public function enableSkill(string $skillId): self
    {
        // Remove from disabled list
        $this->disabledSkills = array_values(array_filter(
            $this->disabledSkills,
            fn($id) => $id !== $skillId
        ));

        // Add to enabled list if using whitelist mode
        if ($this->skillMode === 'whitelist' && !in_array($skillId, $this->enabledSkills, true)) {
            $this->enabledSkills[] = $skillId;
        }

        return $this;
    }

    /**
     * Disable a skill for this agent
     */
    public function disableSkill(string $skillId): self
    {
        // Add to disabled list
        if (!in_array($skillId, $this->disabledSkills, true)) {
            $this->disabledSkills[] = $skillId;
        }

        // Remove from enabled list
        $this->enabledSkills = array_values(array_filter(
            $this->enabledSkills,
            fn($id) => $id !== $skillId
        ));

        return $this;
    }

    /**
     * Set skill mode
     */
    public function setSkillMode(string $mode): self
    {
        if (in_array($mode, ['all', 'whitelist', 'blacklist'])) {
            $this->skillMode = $mode;
        }
        return $this;
    }

    // ============================================
    // Document Section Assignment Methods
    // ============================================

    /**
     * Get enabled document sections for this agent
     */
    public function getEnabledSections(): array
    {
        return $this->enabledSections;
    }

    /**
     * Set enabled document sections for this agent
     */
    public function setEnabledSections(array $sections): self
    {
        $this->enabledSections = array_values($sections);
        return $this;
    }

    /**
     * Check if a document section is enabled for this agent
     */
    public function isSectionEnabled(string $sectionId): bool
    {
        if (!$this->sectionsConfigured) {
            return true;
        }

        if (empty($this->enabledSections)) {
            return false;
        }

        return in_array($sectionId, $this->enabledSections, true);
    }

    // ============================================
    // Tool Configuration Methods
    // ============================================

    /**
     * Get configuration for a specific tool
     */
    public function getToolConfig(string $toolId): array
    {
        return $this->toolConfigs[$toolId] ?? [];
    }

    /**
     * Set configuration for a specific tool
     */
    public function setToolConfig(string $toolId, array $config): self
    {
        $this->toolConfigs[$toolId] = $config;
        return $this;
    }

    /**
     * Get a single config value for a tool
     */
    public function getToolConfigValue(string $toolId, string $key, $default = null): mixed
    {
        return $this->toolConfigs[$toolId][$key] ?? $default;
    }

    /**
     * Set a single config value for a tool
     */
    public function setToolConfigValue(string $toolId, string $key, $value): self
    {
        if (!isset($this->toolConfigs[$toolId])) {
            $this->toolConfigs[$toolId] = [];
        }
        $this->toolConfigs[$toolId][$key] = $value;
        return $this;
    }

    /**
     * Check if a tool has all required configuration
     * 
     * @param string $toolId Tool identifier
     * @param array $schema Configuration schema from toolkit
     * @return bool True if all required fields are set
     */
    public function isToolConfigured(string $toolId, array $schema = []): bool
    {
        $config = $this->getToolConfig($toolId);

        foreach ($schema as $field) {
            if (!empty($field['required'])) {
                $fieldId = $field['id'] ?? '';
                if (empty($config[$fieldId])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get configuration for a toolkit (global settings)
     */
    public function getToolkitConfig(string $toolkitId): array
    {
        return $this->toolkitConfigs[$toolkitId] ?? [];
    }

    /**
     * Set configuration for a toolkit
     */
    public function setToolkitConfig(string $toolkitId, array $config): self
    {
        $this->toolkitConfigs[$toolkitId] = $config;
        return $this;
    }

    /**
     * Get a single config value for a toolkit
     */
    public function getToolkitConfigValue(string $toolkitId, string $key, $default = null): mixed
    {
        return $this->toolkitConfigs[$toolkitId][$key] ?? $default;
    }

    /**
     * Check if toolkit has required configuration
     */
    public function isToolkitConfigured(string $toolkitId, array $schema = []): bool
    {
        $config = $this->getToolkitConfig($toolkitId);

        foreach ($schema as $field) {
            if (!empty($field['required'])) {
                $fieldId = $field['id'] ?? '';
                if (empty($config[$fieldId])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get all tool configurations
     */
    public function getAllToolConfigs(): array
    {
        return $this->toolConfigs;
    }

    /**
     * Clear configuration for a tool
     */
    public function clearToolConfig(string $toolId): self
    {
        unset($this->toolConfigs[$toolId]);
        return $this;
    }
}
