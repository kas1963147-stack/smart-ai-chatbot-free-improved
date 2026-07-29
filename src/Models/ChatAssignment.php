<?php
declare(strict_types=1);
/**
 * ChatAssignment Model
 * 
 * Maps agents to page locations (URLs, post types, specific pages, etc.)
 * Supports multiple agents per location and priority-based resolution.
 * 
 * @package SWC\Models
 */

namespace Quarksol\SmartChatbot\Models;

use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatAssignment Model
 */
class ChatAssignment {
    
    // Location type constants
    const TYPE_PAGE_ID     = 'page_id';
    const TYPE_POST_TYPE   = 'post_type';
    const TYPE_URL_PATTERN = 'url_pattern';
    const TYPE_TAXONOMY    = 'taxonomy';
    const TYPE_CART        = 'cart';
    const TYPE_CHECKOUT    = 'checkout';
    const TYPE_ACCOUNT     = 'account';
    const TYPE_GLOBAL      = 'global';
    
    /** Database ID */
    public int $id = 0;
    
    /** Agent database ID (FK to chat_agents) - NULL for group assignments */
    public ?int $agentDbId = null;
    
    /** Group database ID (FK to agent_groups) - NULL for agent assignments */
    public ?int $groupDbId = null;
    
    /** Location type */
    public string $locationType = self::TYPE_GLOBAL;
    
    /** Location value (page ID, URL pattern, etc.) */
    public ?string $locationValue = null;
    
    /** Priority (higher = more specific) */
    public int $priority = 0;
    
    /** Whether assignment is active */
    public bool $isActive = true;
    
    /** Created timestamp */
    public ?string $createdAt = null;
    
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
        $this->agentDbId = !empty($data['agent_db_id']) ? (int) $data['agent_db_id'] : null;
        $this->groupDbId = !empty($data['group_db_id']) ? (int) $data['group_db_id'] : null;
        $this->locationType = $data['location_type'] ?? self::TYPE_GLOBAL;
        $this->locationValue = $data['location_value'] ?? null;
        $this->priority = (int) ($data['priority'] ?? 0);
        $this->isActive = (bool) ($data['is_active'] ?? true);
        $this->createdAt = $data['created_at'] ?? null;
    }
    
    /**
     * Check if this is a group assignment
     */
    public function isGroup(): bool {
        return $this->groupDbId !== null && $this->agentDbId === null;
    }
    
    /**
     * Get the group for this assignment (if group assignment)
     */
    public function getGroup(): ?AgentGroup {
        if (!$this->isGroup()) {
            return null;
        }
        return AgentGroup::find($this->groupDbId);
    }
    
    /**
     * Get valid location types
     */
    public static function getLocationTypes(): array {
        return [
            self::TYPE_PAGE_ID => 'Specific Page',
            self::TYPE_POST_TYPE => 'Post Type',
            self::TYPE_URL_PATTERN => 'URL Pattern',
            self::TYPE_TAXONOMY => 'Taxonomy Term',
            self::TYPE_CART => 'Cart Page',
            self::TYPE_CHECKOUT => 'Checkout Page',
            self::TYPE_ACCOUNT => 'My Account',
            self::TYPE_GLOBAL => 'All Pages (Global)',
        ];
    }
    
    /**
     * Find by ID
     */
    public static function find(int $id): ?self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['assignments']} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get all assignments for an agent
     */
    public static function forAgent(int $agentDbId): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['assignments']} WHERE agent_db_id = %d ORDER BY priority DESC",
                $agentDbId
            ),
            ARRAY_A
        );
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Get all assignments for a group
     */
    public static function forGroup(int $groupDbId): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['assignments']} WHERE group_db_id = %d ORDER BY priority DESC",
                $groupDbId
            ),
            ARRAY_A
        );
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Get all assignments for a location
     */
    public static function forLocation(string $type, ?string $value = null): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        if ($value === null) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT a.* FROM {$tables['assignments']} a
                     JOIN {$tables['agents']} ag ON a.agent_db_id = ag.id
                     WHERE a.location_type = %s AND a.is_active = 1 AND ag.is_active = 1
                     ORDER BY a.priority DESC",
                    $type
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT a.* FROM {$tables['assignments']} a
                     JOIN {$tables['agents']} ag ON a.agent_db_id = ag.id
                     WHERE a.location_type = %s AND a.location_value = %s 
                     AND a.is_active = 1 AND ag.is_active = 1
                     ORDER BY a.priority DESC",
                    $type,
                    $value
                ),
                ARRAY_A
            );
        }
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Get assignments for a location type and multiple values (batch)
     * e.g. for bulk taxonomy lookup
     */
    public static function forLocationValues(string $type, array $values): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        if (empty($values)) {
            return [];
        }
        
        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        
        $sql = $wpdb->prepare(
            "SELECT a.* FROM {$tables['assignments']} a
             JOIN {$tables['agents']} ag ON a.agent_db_id = ag.id
             WHERE a.location_type = %s 
             AND a.location_value IN ($placeholders)
             AND a.is_active = 1 AND ag.is_active = 1",
            array_merge([$type], $values)
        );
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Get all active assignments with their agents
     * Returns assignments grouped by location
     */
    public static function allWithAgents(): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $rows = $wpdb->get_results(
            "SELECT a.*, ag.agent_id, ag.name as agent_name, ag.avatar
             FROM {$tables['assignments']} a
             JOIN {$tables['agents']} ag ON a.agent_db_id = ag.id
             WHERE a.is_active = 1 AND ag.is_active = 1
             ORDER BY a.location_type, a.priority DESC",
            ARRAY_A
        );
        
        return $rows ?: [];
    }
    
    /**
     * Save assignment
     */
    public function save(): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $data = [
            'agent_db_id' => $this->agentDbId,
            'group_db_id' => $this->groupDbId,
            'location_type' => $this->locationType,
            'location_value' => $this->locationValue,
            'priority' => $this->priority,
            'is_active' => $this->isActive ? 1 : 0,
        ];
        
        $format = ['%d', '%d', '%s', '%s', '%d', '%d'];
        
        if ($this->id > 0) {
            $result = $wpdb->update($tables['assignments'], $data, ['id' => $this->id], $format, ['%d']);
            return $result !== false;
        } else {
            $result = $wpdb->insert($tables['assignments'], $data, $format);
            if ($result) {
                $this->id = $wpdb->insert_id;
                return true;
            }
            return false;
        }
    }
    
    /**
     * Delete assignment
     */
    public function delete(): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        if ($this->id <= 0) {
            return false;
        }
        
        return $wpdb->delete($tables['assignments'], ['id' => $this->id], ['%d']) !== false;
    }
    
    /**
     * Get the agent for this assignment
     */
    public function getAgent(): ?ChatAgent {
        return ChatAgent::find($this->agentDbId);
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'agent_db_id' => $this->agentDbId,
            'location_type' => $this->locationType,
            'location_value' => $this->locationValue,
            'priority' => $this->priority,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
        ];
    }
}
