<?php
declare(strict_types=1);
/**
 * AgentGroup Model
 * 
 * Represents a group of agents that work together with orchestration.
 * Groups can be assigned to locations instead of individual agents.
 * 
 * @package SWC\Models
 */

namespace Quarksol\SmartChatbot\Models;

use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Group Model
 * 
 * Orchestration modes:
 * - router: Automatically select best agent based on message
 * - sequential: Run agents in order, passing context
 * - parallel: Run all agents simultaneously
 * - handoff: Manual handoff between agents (default behavior)
 */
class AgentGroup {
    
    // Orchestration mode constants
    const MODE_ROUTER = 'router';
    const MODE_SEQUENTIAL = 'sequential';
    const MODE_PARALLEL = 'parallel';
    const MODE_HANDOFF = 'handoff';
    const MODE_SUPERVISOR = 'supervisor';
    
    // Member role constants
    const ROLE_PRIMARY = 'primary';
    const ROLE_SPECIALIST = 'specialist';
    const ROLE_FALLBACK = 'fallback';
    
    /** Database ID */
    public int $id = 0;
    
    /** Unique slug identifier */
    public string $groupId = '';
    
    /** Display name */
    public string $name = '';
    
    /** Description */
    public string $description = '';
    
    /** Avatar emoji or URL */
    public string $avatar = '';
    
    /** Orchestration mode */
    public string $orchestrationMode = self::MODE_ROUTER;
    
    /** Routing configuration (keywords, rules) */
    public array $routingConfig = [];
    
    /** Welcome message for the group */
    public string $welcomeMessage = '';
    
    /** Whether group is active */
    public bool $isActive = true;
    
    /** Created timestamp */
    public ?string $createdAt = null;
    
    /** Updated timestamp */
    public ?string $updatedAt = null;
    
    /** Cached members */
    protected ?array $members = null;
    
    /**
     * Constructor
     */
    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    /**
     * Hydrate from database row
     */
    protected function hydrate(array $data): void {
        $this->id = (int) ($data['id'] ?? 0);
        $this->groupId = $data['group_id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->avatar = $data['avatar'] ?? '';
        $this->orchestrationMode = $data['orchestration_mode'] ?? self::MODE_ROUTER;
        $this->welcomeMessage = $data['welcome_message'] ?? '';
        $this->isActive = (bool) ($data['is_active'] ?? true);
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
        
        // Parse routing config JSON
        if (!empty($data['routing_config'])) {
            $config = is_string($data['routing_config']) 
                ? json_decode($data['routing_config'], true) 
                : $data['routing_config'];
            $this->routingConfig = is_array($config) ? $config : [];
        }
    }
    
    /**
     * Find by database ID
     */
    public static function find(int $id): ?self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['agent_groups']} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find by slug
     */
    public static function findBySlug(string $groupId): ?self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['agent_groups']} WHERE group_id = %s", $groupId),
            ARRAY_A
        );
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get all groups
     */
    public static function all(bool $activeOnly = true): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $sql = "SELECT * FROM {$tables['agent_groups']}";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name ASC";
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }

    /**
     * Find group that an agent belongs to
     */
    public static function findByMember(int $agentDbId): ?self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT g.* FROM {$tables['agent_groups']} g 
                 JOIN {$tables['group_members']} m ON g.id = m.group_db_id 
                 WHERE m.agent_db_id = %d AND m.is_active = 1 LIMIT 1",
                $agentDbId
            ),
            ARRAY_A
        );
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Save group to database
     */
    public function save(): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $data = [
            'group_id' => $this->groupId,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'orchestration_mode' => $this->orchestrationMode,
            'routing_config' => wp_json_encode($this->routingConfig),
            'welcome_message' => $this->welcomeMessage,
            'is_active' => $this->isActive ? 1 : 0,
        ];
        
        $format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'];
        
        if ($this->id > 0) {
            // Update
            $result = $wpdb->update($tables['agent_groups'], $data, ['id' => $this->id], $format, ['%d']);
            return $result !== false;
        } else {
            // Insert
            $result = $wpdb->insert($tables['agent_groups'], $data, $format);
            if ($result) {
                $this->id = $wpdb->insert_id;
                return true;
            }
            return false;
        }
    }
    
    /**
     * Delete group and its memberships
     */
    public function delete(): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        if ($this->id <= 0) {
            return false;
        }
        
        // Delete memberships first
        $wpdb->delete($tables['group_members'], ['group_db_id' => $this->id], ['%d']);
        
        // Delete group assignments
        $wpdb->delete($tables['assignments'], ['group_db_id' => $this->id], ['%d']);
        
        // Delete group
        $result = $wpdb->delete($tables['agent_groups'], ['id' => $this->id], ['%d']);
        
        return $result !== false;
    }
    
    /**
     * Get group members
     * 
     * @return array Array of member records with agent info
     */
    public function getMembers(): array {
        if ($this->members !== null) {
            return $this->members;
        }
        
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, a.agent_id, a.name as agent_name, a.avatar as agent_avatar, a.description as agent_description
                 FROM {$tables['group_members']} m
                 JOIN {$tables['agents']} a ON m.agent_db_id = a.id
                 WHERE m.group_db_id = %d AND m.is_active = 1 AND a.is_active = 1
                 ORDER BY m.execution_order ASC, m.role DESC",
                $this->id
            ),
            ARRAY_A
        );
        
        $this->members = $rows ?: [];
        return $this->members;
    }
    
    /**
     * Get member agents as ChatAgent objects
     * 
     * @return ChatAgent[]
     */
    public function getMemberAgents(): array {
        $members = $this->getMembers();
        $agents = [];
        
        foreach ($members as $member) {
            $agent = ChatAgent::find((int) $member['agent_db_id']);
            if ($agent) {
                $agents[] = $agent;
            }
        }
        
        return $agents;
    }
    
    /**
     * Add agent to group
     */
    public function addMember(
        int $agentDbId, 
        string $role = self::ROLE_SPECIALIST, 
        int $executionOrder = 0,
        string $routingKeywords = ''
    ): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        // Check if already a member
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tables['group_members']} 
                 WHERE group_db_id = %d AND agent_db_id = %d",
                $this->id, $agentDbId
            )
        );
        
        if ($existing) {
            // Update existing
            return $wpdb->update(
                $tables['group_members'],
                [
                    'role' => $role,
                    'execution_order' => $executionOrder,
                    'routing_keywords' => $routingKeywords,
                    'is_active' => 1,
                ],
                ['id' => $existing],
                ['%s', '%d', '%s', '%d'],
                ['%d']
            ) !== false;
        }
        
        // Insert new
        $result = $wpdb->insert(
            $tables['group_members'],
            [
                'group_db_id' => $this->id,
                'agent_db_id' => $agentDbId,
                'role' => $role,
                'execution_order' => $executionOrder,
                'routing_keywords' => $routingKeywords,
                'is_active' => 1,
            ],
            ['%d', '%d', '%s', '%d', '%s', '%d']
        );
        
        // Clear cache
        $this->members = null;
        
        return $result !== false;
    }
    
    /**
     * Remove agent from group
     */
    public function removeMember(int $agentDbId): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $result = $wpdb->delete(
            $tables['group_members'],
            [
                'group_db_id' => $this->id,
                'agent_db_id' => $agentDbId,
            ],
            ['%d', '%d']
        );
        
        // Clear cache
        $this->members = null;
        
        return $result !== false;
    }
    
    /**
     * Get workflow steps from routing config.
     *
     * @return array Array of workflow step definitions, empty if none defined.
     */
    public function getWorkflowSteps(): array {
        return $this->routingConfig['workflow_steps'] ?? [];
    }

    /**
     * Check if this group has workflow steps defined.
     */
    public function hasWorkflowSteps(): bool {
        $steps = $this->getWorkflowSteps();
        return !empty($steps);
    }

    /**
     * Get primary agent for the group
     */
    public function getPrimaryAgent(): ?ChatAgent {
        $members = $this->getMembers();
        
        foreach ($members as $member) {
            if ($member['role'] === self::ROLE_PRIMARY) {
                return ChatAgent::find((int) $member['agent_db_id']);
            }
        }
        
        // Fall back to first agent
        return $members ? ChatAgent::find((int) $members[0]['agent_db_id']) : null;
    }
    
    /**
     * Get available orchestration modes
     */
    public static function getOrchestrationModes(): array {
        return [
            self::MODE_ROUTER => 'Auto-Router (select best agent)',
            self::MODE_SEQUENTIAL => 'Sequential (agents work in order)',
            self::MODE_PARALLEL => 'Parallel (all agents respond)',
            self::MODE_HANDOFF => 'Handoff (manual transfers)',
            self::MODE_SUPERVISOR => 'Supervisor (manager delegates to workers)',
        ];
    }
    
    /**
     * Get available member roles
     */
    public static function getMemberRoles(): array {
        return [
            self::ROLE_PRIMARY => 'Primary (default agent)',
            self::ROLE_SPECIALIST => 'Specialist (routed by keywords)',
            self::ROLE_FALLBACK => 'Fallback (last resort)',
        ];
    }
    
    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'group_id' => $this->groupId,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'orchestration_mode' => $this->orchestrationMode,
            'routing_config' => $this->routingConfig,
            'welcome_message' => $this->welcomeMessage,
            'is_active' => $this->isActive,
            'members' => $this->getMembers(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
