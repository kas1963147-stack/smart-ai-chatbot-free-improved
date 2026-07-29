<?php
declare(strict_types=1);


/**
 * Skill Group
 * 
 * Represents a custom group for organizing skills.
 * Groups can contain skills from any category.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SkillGroup - Custom skill organization
 */
class SkillGroup
{
    /** WordPress option key for groups */
    const OPTION_KEY = 'swc_skill_groups';
    
    /** Group ID (slug) */
    public string $id;
    
    /** Display name */
    public string $name;
    
    /** Description */
    public string $description = '';
    
    /** Icon (emoji or icon class) */
    public string $icon = '';
    
    /** Color for UI */
    public string $color = '#6366f1';
    
    /** Display order */
    public int $order = 0;
    
    /** Parent group ID (for nesting) */
    public ?string $parentId = null;
    
    /** Skill IDs in this group */
    public array $skillIds = [];
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        $group = new self();
        
        $group->id = $data['id'] ?? self::generateId($data['name'] ?? 'group');
        $group->name = $data['name'] ?? 'Untitled Group';
        $group->description = $data['description'] ?? '';
        $group->icon = $data['icon'] ?? '';
        $group->color = $data['color'] ?? '#6366f1';
        $group->order = (int)($data['order'] ?? 0);
        $group->parentId = $data['parent_id'] ?? null;
        $group->skillIds = $data['skill_ids'] ?? [];
        
        return $group;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'order' => $this->order,
            'parent_id' => $this->parentId,
            'skill_ids' => $this->skillIds,
        ];
    }
    
    /**
     * Generate ID from name
     */
    public static function generateId(string $name): string
    {
        $id = strtolower($name);
        $id = preg_replace('/[^a-z0-9\s-]/', '', $id);
        $id = preg_replace('/[\s_]+/', '-', $id);
        return preg_replace('/-+/', '-', trim($id, '-'));
    }
    
    // ============================================
    // Static CRUD Methods
    // ============================================
    
    /**
     * Get all groups
     */
    public static function getAll(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }
        
        $data = get_option(self::OPTION_KEY, []);
        
        if (!is_array($data)) {
            return [];
        }
        
        $groups = [];
        foreach ($data as $groupData) {
            $groups[] = self::fromArray($groupData);
        }
        
        // Sort by order
        usort($groups, fn($a, $b) => $a->order <=> $b->order);
        
        return $groups;
    }
    
    /**
     * Get single group by ID
     */
    public static function get(string $id): ?self
    {
        $groups = self::getAll();
        
        foreach ($groups as $group) {
            if ($group->id === $id) {
                return $group;
            }
        }
        
        return null;
    }
    
    /**
     * Save group
     */
    public function save(): bool
    {
        if (!function_exists('update_option')) {
            return false;
        }
        
        $groups = self::getAll();
        $found = false;
        
        // Update existing or add new
        foreach ($groups as $i => $group) {
            if ($group->id === $this->id) {
                $groups[$i] = $this;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $groups[] = $this;
        }
        
        // Convert to arrays for storage
        $data = array_map(fn($g) => $g->toArray(), $groups);
        
        return update_option(self::OPTION_KEY, $data);
    }
    
    /**
     * Delete group
     */
    public function delete(): bool
    {
        if (!function_exists('update_option')) {
            return false;
        }
        
        $groups = self::getAll();
        $groups = array_filter($groups, fn($g) => $g->id !== $this->id);
        
        $data = array_map(fn($g) => $g->toArray(), $groups);
        
        return update_option(self::OPTION_KEY, array_values($data));
    }
    
    /**
     * Add skill to group
     */
    public function addSkill(string $skillId): self
    {
        if (!in_array($skillId, $this->skillIds, true)) {
            $this->skillIds[] = $skillId;
        }
        return $this;
    }
    
    /**
     * Remove skill from group
     */
    public function removeSkill(string $skillId): self
    {
        $this->skillIds = array_values(array_filter(
            $this->skillIds,
            fn($id) => $id !== $skillId
        ));
        return $this;
    }
    
    /**
     * Check if group contains skill
     */
    public function hasSkill(string $skillId): bool
    {
        return in_array($skillId, $this->skillIds, true);
    }
    
    /**
     * Get child groups
     */
    public function getChildren(): array
    {
        $all = self::getAll();
        return array_filter($all, fn($g) => $g->parentId === $this->id);
    }
    
    /**
     * Get groups as tree structure
     */
    public static function getTree(): array
    {
        $groups = self::getAll();
        $tree = [];
        $byId = [];
        
        // Index by ID
        foreach ($groups as $group) {
            $byId[$group->id] = $group;
        }
        
        // Build tree
        foreach ($groups as $group) {
            if ($group->parentId === null) {
                $tree[] = self::buildTreeNode($group, $byId);
            }
        }
        
        return $tree;
    }
    
    /**
     * Build tree node recursively
     */
    protected static function buildTreeNode(self $group, array &$byId): array
    {
        $node = $group->toArray();
        $node['children'] = [];
        
        foreach ($byId as $g) {
            if ($g->parentId === $group->id) {
                $node['children'][] = self::buildTreeNode($g, $byId);
            }
        }
        
        return $node;
    }
}
