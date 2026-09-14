<?php
declare(strict_types=1);

/**
 * Agent Registry
 *
 * Discovers and loads agents from the agents/ folder.
 * Provides a centralized registry of all available agents.
 *
 * @package Quarksol\SmartChatbot\Agent
 */

namespace Quarksol\SmartChatbot\Agent;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Registry - Discovers agents from the filesystem
 */
class AgentRegistry
{
    /** @var string Path to agents directory (always within wp_upload_dir()) */
    private static string $agentsPath;

    /** @var array Cached agent configurations */
    private static array $cache = [];

    /** @var bool Whether the registry has been initialized */
    private static bool $initialized = false;

    /**
     * Initialize the registry.
     *
     * NOTE: All agent configuration files are stored in the WordPress uploads
     * directory (wp_upload_dir()), NOT in the plugin folder. This complies
     * with WordPress.org guidelines which prohibit writing to the plugin directory.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        // Writes go to uploads directory — not the plugin folder
        self::$agentsPath = wp_upload_dir()['basedir'] . '/smart-ai-chatbot/agents';
        self::discover();
        self::$initialized = true;
    }

    /**
     * Get the agents directory path (always within wp_upload_dir())
     */
    public static function getAgentsPath(): string
    {
        self::init();
        return self::$agentsPath;
    }

    /**
     * Discover all agents in the agents folder
     */
    public static function discover(): array
    {
        // Ensure agentsPath is set
        if (!isset(self::$agentsPath) || empty(self::$agentsPath)) {
            self::$agentsPath = wp_upload_dir()['basedir'] . '/smart-ai-chatbot/agents';
        }

        if (!is_dir(self::$agentsPath)) {
            return [];
        }

        $agents = [];
        $dirs   = glob(self::$agentsPath . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $agentFile = $dir . '/AGENT.php';
            if (file_exists($agentFile)) {
                $agentId           = basename($dir);
                $agents[$agentId]  = $agentFile;
            }
        }

        return $agents;
    }

    /**
     * Get all agent IDs
     *
     * @return string[]
     */
    public static function getAgentIds(): array
    {
        return array_keys(self::discover());
    }

    /**
     * Check if an agent exists
     */
    public static function exists(string $agentId): bool
    {
        $agents = self::discover();
        return isset($agents[$agentId]);
    }

    /**
     * Get agent configuration by ID
     *
     * @param string $agentId Agent identifier
     * @return array|null Agent configuration or null if not found
     */
    public static function get(string $agentId): ?array
    {
        // Check cache first
        if (isset(self::$cache[$agentId])) {
            return self::$cache[$agentId];
        }

        $agents = self::discover();

        if (!isset($agents[$agentId])) {
            return null;
        }

        $config = require $agents[$agentId];

        if (!is_array($config)) {
            return null;
        }

        // Ensure required fields
        $config = array_merge([
            'id'            => $agentId,
            'name'          => ucwords(str_replace('_', ' ', $agentId)),
            'description'   => '',
            'avatar'        => '',
            'is_active'     => true,
            'is_default'    => false,
            'toolkits'      => [],
            'disabled_tools' => [],
            'skills'        => ['mode' => 'all', 'enabled' => [], 'disabled' => []],
            'knowledge'     => ['enabled' => false, 'namespace' => $agentId],
            'prompt'        => [],
            'widget'        => [],
        ], $config);

        // Cache the configuration
        self::$cache[$agentId] = $config;

        return $config;
    }

    /**
     * Get all agent configurations
     *
     * @param bool $activeOnly Only return active agents
     * @return array[]
     */
    public static function getAll(bool $activeOnly = false): array
    {
        $agents = [];

        foreach (self::discover() as $agentId => $path) {
            $config = self::get($agentId);

            if ($config === null) {
                continue;
            }

            if ($activeOnly && empty($config['is_active'])) {
                continue;
            }

            $agents[$agentId] = $config;
        }

        return $agents;
    }

    /**
     * Get agent summaries for list display
     *
     * @return array[]
     */
    public static function getSummaries(): array
    {
        $summaries = [];

        foreach (self::getAll() as $agentId => $config) {
            $summaries[] = [
                'id'          => $agentId,
                'name'        => $config['name'],
                'description' => $config['description'],
                'avatar'      => $config['avatar'],
                'is_active'   => $config['is_active'],
                'is_default'  => $config['is_default'],
                'toolkits'    => array_keys(array_filter($config['toolkits'] ?? [])),
            ];
        }

        return $summaries;
    }

    /**
     * Clear the cache
     */
    public static function clearCache(): void
    {
        self::$cache       = [];
        self::$initialized = false;
    }

    /**
     * Get the path for a specific agent
     */
    public static function getAgentPath(string $agentId): string
    {
        self::init();
        return self::$agentsPath . '/' . $agentId;
    }

    /**
     * Create a new agent directory and config file.
     *
     * Files are written to the WordPress uploads directory (wp_upload_dir()), not the plugin folder.
     */
    public static function createAgent(array $config): bool
    {
        $agentId = $config['id'] ?? '';

        if (empty($agentId)) {
            return false;
        }

        $agentPath = self::getAgentPath($agentId);

        // Create directory if it doesn't exist
        if (!is_dir($agentPath)) {
            if (!mkdir($agentPath, 0755, true)) {
                return false;
            }
        }

        // Generate and write the AGENT.php config file to the uploads directory
        $content = self::generateAgentFile($config);
        $result  = file_put_contents($agentPath . '/AGENT.php', $content);

        if ($result !== false) {
            // Clear cache so new agent is picked up
            self::clearCache();
            self::updateRegistry();
        }

        return $result !== false;
    }

    /**
     * Update an existing agent
     */
    public static function updateAgent(string $agentId, array $config): bool
    {
        $config['id'] = $agentId;
        return self::createAgent($config);
    }

    /**
     * Delete an agent
     */
    public static function deleteAgent(string $agentId): bool
    {
        $agentPath = self::getAgentPath($agentId);

        if (!is_dir($agentPath)) {
            return false;
        }

        // Remove AGENT.php
        $agentFile = $agentPath . '/AGENT.php';
        if (file_exists($agentFile)) {
            unlink($agentFile);
        }

        // Remove directory (only if empty)
        @rmdir($agentPath);

        self::clearCache();
        self::updateRegistry();

        return true;
    }

    /**
     * Generate AGENT.php file content from configuration
     */
    public static function generateAgentFile(array $config): string
    {
        $agentId     = $config['id'] ?? 'unknown';
        $name        = $config['name'] ?? ucwords(str_replace('_', ' ', $agentId));
        $description = $config['description'] ?? '';

        $php  = "<?php\n";
        $php .= "/**\n";
        $php .= " * {$name} Agent Configuration\n";
        $php .= " *\n";
        $php .= " * {$description}\n";
        $php .= " *\n";
        $php .= " * Auto-generated by AgentRegistry\n";
        $php .= " */\n\n";
        $php .= "return " . self::varExport($config, true) . ";\n";

        return $php;
    }

    /**
     * Better var_export that formats arrays nicely
     */
    private static function varExport($value, bool $return = false): string
    {
        $export = var_export($value, true);

        // Convert array() to []
        $export = preg_replace("/^array \(/", '[', $export);
        $export = preg_replace("/\)$/", ']', $export);
        $export = preg_replace("/array \(/", '[', $export);
        $export = preg_replace("/\),/", '],', $export);
        $export = preg_replace("/\)$/m", ']', $export);

        // Fix indentation
        $lines  = explode("\n", $export);
        $result = [];
        foreach ($lines as $line) {
            $line     = preg_replace("/^  /", '    ', $line);
            $result[] = $line;
        }

        return implode("\n", $result);
    }

    /**
     * Update the _registry.json file
     */
    public static function updateRegistry(): void
    {
        self::init();

        $agentIds = self::getAgentIds();

        $registry = [
            'version'     => '1.0.0',
            'last_synced' => date('Y-m-d\TH:i:sP'),
            'agents'      => $agentIds,
            'note'        => 'This file is auto-generated. Do not edit manually.',
        ];

        update_option('swc_agent_registry', $registry);
    }
}
