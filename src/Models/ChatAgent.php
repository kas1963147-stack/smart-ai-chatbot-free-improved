<?php
declare(strict_types=1);
/**
 * ChatAgent Model
 * 
 * Represents a chat agent with its configuration, toolkits, and prompts.
 * Agents can be created, duplicated, and assigned to page locations.
 * 
 * @package SWC\Models
 */

namespace Quarksol\SmartChatbot\Models;

use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Services\Logger;
use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatAgent Model
 */
class ChatAgent
{

    /** Database ID */
    public int $id = 0;

    /** Unique slug identifier (e.g., 'booking_agent') */
    public string $agentId = '';

    /** Display name */
    public string $name = '';

    /** Description */
    public string $description = '';

    /** Avatar emoji or URL */
    public string $avatar = '';

    /** Whether agent is active */
    public bool $isActive = true;

    /** Whether this is a base template agent */
    public bool $isDefault = false;

    /** Whether agent is hidden from admin UI (system agents) */
    public bool $isHidden = false;

    /** Parent agent ID (for duplicated agents) */
    public ?int $parentAgentId = null;

    /** Agent configuration (toolkits, tools, prompts) */
    public ?AgentConfig $config = null;

    /** Created timestamp */
    public ?string $createdAt = null;

    /** Updated timestamp */
    public ?string $updatedAt = null;

    /**
     * Constructor
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * Hydrate model from database row
     */
    protected function hydrate(array $data): void
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->agentId = $data['agent_id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->avatar = $data['avatar'] ?? '';
        $this->isActive = (bool) ($data['is_active'] ?? true);
        $this->isDefault = (bool) ($data['is_default'] ?? false);
        $this->isHidden = (bool) ($data['is_hidden'] ?? false);
        $this->parentAgentId = !empty($data['parent_agent_id']) ? (int) $data['parent_agent_id'] : null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;

        Logger::debug('ChatAgent hydrate: raw config loaded', [
            'agent_id' => $this->agentId,
            'config_length' => strlen((string) ($data['config'] ?? '')),
        ]);

        // Parse config JSON
        if (!empty($data['config'])) {
            $configArray = is_string($data['config']) ? json_decode($data['config'], true) : $data['config'];

            Logger::debug('ChatAgent hydrate: parsed config', [
                'agent_id' => $this->agentId,
                'has_mcp_configs' => isset($configArray['mcp_configs']),
            ]);

            if (is_array($configArray)) {
                $this->config = AgentConfig::fromArray(array_merge(
                    ['agent_id' => $this->agentId, 'name' => $this->name],
                    $configArray
                ));
            }
        }

        if (!$this->config) {
            $this->config = new AgentConfig($this->agentId, $this->name);
        }
    }

    /**
     * Find agent by database ID
     */
    public static function find(int $id): ?self
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['agents']} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Find multiple agents by ID
     * 
     * @param int[] $ids
     * @return self[] Keyed by ID
     */
    public static function findMultiple(array $ids): array
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        if (empty($ids)) {
            return [];
        }

        $ids = array_map('intval', $ids);
        $idList = implode(',', $ids);

        $rows = $wpdb->get_results(
            "SELECT * FROM {$tables['agents']} WHERE id IN ($idList)",
            ARRAY_A
        );

        $agents = [];
        foreach ($rows ?: [] as $row) {
            $agent = new self($row);
            $agents[$agent->id] = $agent;
        }

        return $agents;
    }

    /**
     * Find agent by slug
     */
    public static function findBySlug(string $agentId): ?self
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['agents']} WHERE agent_id = %s", $agentId),
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Get all agents
     * 
     * @param bool $activeOnly Only return active agents
     * @param bool $includeHidden Include hidden system agents
     */
    public static function all(bool $activeOnly = true, bool $includeHidden = false): array
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $conditions = [];
        if ($activeOnly) {
            $conditions[] = 'is_active = 1';
        }
        if (!$includeHidden) {
            $conditions[] = '(is_hidden = 0 OR is_hidden IS NULL)';
        }

        $sql = "SELECT * FROM {$tables['agents']}";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY is_default DESC, name ASC";

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(fn($row) => new self($row), $rows ?: []);
    }

    /**
     * Get default agent
     */
    public static function getDefault(): ?self
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $row = $wpdb->get_row(
            "SELECT * FROM {$tables['agents']} WHERE is_default = 1 AND is_active = 1 LIMIT 1",
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Save agent to database
     */
    public function save(): bool
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        // Encode config data and check for JSON encoding errors
        $configArray = $this->config ? $this->config->toArray() : [];
        $configJson = wp_json_encode($configArray);

        if ($configJson === false) {
            error_log('[ChatAgent::save] JSON encoding failed for agent ' . $this->agentId);
            error_log('[ChatAgent::save] JSON error: ' . json_last_error_msg());
            // Attempt UTF-8 sanitization and retry
            $configArray = $this->sanitizeForJson($configArray);
            $configJson = wp_json_encode($configArray);
            if ($configJson === false) {
                error_log('[ChatAgent::save] JSON encoding still failed after sanitization');
                return false;
            }
        }

        $data = [
            'agent_id' => $this->agentId,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'is_active' => $this->isActive ? 1 : 0,
            'is_default' => $this->isDefault ? 1 : 0,
            'is_hidden' => $this->isHidden ? 1 : 0,
            'config' => $configJson,
        ];

        $format = ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s'];

        // Only include parent_agent_id when it has a value (preserve NULL in DB)
        if ($this->parentAgentId !== null) {
            $data['parent_agent_id'] = $this->parentAgentId;
            $format[] = '%d';
        }

        if ($this->id > 0) {
            // Update existing agent
            $result = $wpdb->update($tables['agents'], $data, ['id' => $this->id], $format, ['%d']);

            if ($result === false) {
                error_log('[ChatAgent::save] Database UPDATE failed for agent id=' . $this->id);
                error_log('[ChatAgent::save] DB Error: ' . $wpdb->last_error);
                error_log('[ChatAgent::save] Last Query: ' . $wpdb->last_query);
                return false;
            }

            // $result === 0 means no rows were updated (data unchanged), which is still a success
            if ($result === 0) {
                error_log('[ChatAgent::save] No rows updated for agent id=' . $this->id . ' (data may be unchanged)');
            }

            return true;
        } else {
            // Insert new agent
            $result = $wpdb->insert($tables['agents'], $data, $format);

            if (!$result) {
                error_log('[ChatAgent::save] Database INSERT failed for agent ' . $this->agentId);
                error_log('[ChatAgent::save] DB Error: ' . $wpdb->last_error);
                error_log('[ChatAgent::save] Last Query: ' . $wpdb->last_query);
                return false;
            }

            $this->id = $wpdb->insert_id;
            return true;
        }
    }

    /**
     * Recursively sanitize data for JSON encoding (fix UTF-8 issues)
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitizeForJson($data)
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->sanitizeForJson($value);
            }
            return $result;
        } elseif (is_string($data)) {
            // Remove invalid UTF-8 sequences
            $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // Remove null bytes and other problematic characters
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);
        }
        return $data;
    }

    /**
     * Delete agent
     */
    public function delete(): bool
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        if ($this->id <= 0) {
            return false;
        }

        // Delete assignments first
        $wpdb->delete($tables['assignments'], ['agent_db_id' => $this->id], ['%d']);

        // Delete agent
        $result = $wpdb->delete($tables['agents'], ['id' => $this->id], ['%d']);

        return $result !== false;
    }

    /**
     * Duplicate agent with new slug and name
     */
    public function duplicate(string $newSlug, string $newName): self
    {
        $newAgent = new self();
        $newAgent->agentId = $newSlug;
        $newAgent->name = $newName;
        $newAgent->description = $this->description;
        $newAgent->avatar = $this->avatar;
        $newAgent->isActive = true;
        $newAgent->isDefault = false;
        $newAgent->parentAgentId = $this->id;

        // Clone config
        if ($this->config) {
            $newAgent->config = AgentConfig::fromArray(array_merge(
                $this->config->toArray(),
                ['agent_id' => $newSlug, 'name' => $newName]
            ));
        }

        return $newAgent;
    }

    /**
     * Get assignments for this agent
     */
    public function getAssignments(): array
    {
        return ChatAssignment::forAgent($this->id);
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agentId,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'is_active' => $this->isActive,
            'is_default' => $this->isDefault,
            'is_hidden' => $this->isHidden,
            'parent_agent_id' => $this->parentAgentId,
            'config' => $this->config ? $this->config->toArray() : [],
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Create NeuronAgent instance from this ChatAgent
     */
    public function toNeuronAgent(): \Quarksol\SmartChatbot\Agent\NeuronAgent
    {
        return \Quarksol\SmartChatbot\Agent\AgentFactory::createFromChatAgent($this);
    }
}
