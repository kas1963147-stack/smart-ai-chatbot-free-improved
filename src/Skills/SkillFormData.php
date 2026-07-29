<?php
declare(strict_types=1);


/**
 * Skill Form Data
 * 
 * Type-safe data structure for skill form data from admin UI.
 * Converts between form arrays and structured objects.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SkillFormData - Structured form data for skills
 */
class SkillFormData
{
    /** Skill identifier (slug) */
    public string $name = '';
    
    /** Display name */
    public string $displayName = '';
    
    /** Description for discovery */
    public string $description = '';
    
    /** Category: woocommerce, wordpress, support, general */
    public string $category = 'general';
    
    /** Required tool IDs */
    public array $toolsRequired = [];
    
    /** Always include in context */
    public bool $alwaysOn = false;
    
    /** Instruction sections */
    public array $instructions = [];
    
    /** Reference documents */
    public array $references = [];
    
    /** Additional metadata */
    public array $metadata = [];
    
    // ============================================
    // Hierarchy & Dependencies
    // ============================================
    
    /** Parent skill ID */
    public ?string $parentSkill = null;
    
    /** Custom group ID */
    public ?string $group = null;
    
    /** Display order */
    public int $order = 0;
    
    /** Required skills (must load before) */
    public array $requires = [];
    
    /** Suggested skills (recommend after) */
    public array $suggests = [];
    
    /** Conflicting skills (cannot use together) */
    public array $conflicts = [];
    
    /**
     * Create from array (API request)
     */
    public static function fromArray(array $data): self
    {
        $form = new self();
        
        $form->name = $data['name'] ?? '';
        $form->displayName = $data['display_name'] ?? $data['displayName'] ?? '';
        $form->description = $data['description'] ?? '';
        $form->category = $data['category'] ?? 'general';
        $form->toolsRequired = $data['tools_required'] ?? $data['toolsRequired'] ?? [];
        $form->alwaysOn = (bool)($data['always_on'] ?? $data['alwaysOn'] ?? false);
        $form->metadata = $data['metadata'] ?? [];
        
        // Hierarchy fields
        $form->parentSkill = $data['parent_skill'] ?? $data['parentSkill'] ?? null;
        $form->group = $data['group'] ?? null;
        $form->order = (int)($data['order'] ?? 0);
        
        // Dependency fields
        $form->requires = $data['requires'] ?? [];
        $form->suggests = $data['suggests'] ?? [];
        $form->conflicts = $data['conflicts'] ?? [];
        
        // Parse instructions
        $rawInstructions = $data['instructions'] ?? [];
        foreach ($rawInstructions as $section) {
            $form->instructions[] = InstructionSection::fromArray($section);
        }
        
        // Parse references
        $rawReferences = $data['references'] ?? [];
        foreach ($rawReferences as $ref) {
            $form->references[] = SkillReference::fromArray($ref);
        }
        
        return $form;
    }
    
    /**
     * Convert to array (for API response)
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'category' => $this->category,
            'tools_required' => $this->toolsRequired,
            'always_on' => $this->alwaysOn,
            'instructions' => array_map(fn($s) => $s->toArray(), $this->instructions),
            'references' => array_map(fn($r) => $r->toArray(), $this->references),
            'metadata' => $this->metadata,
            // Hierarchy
            'parent_skill' => $this->parentSkill,
            'group' => $this->group,
            'order' => $this->order,
            // Dependencies
            'requires' => $this->requires,
            'suggests' => $this->suggests,
            'conflicts' => $this->conflicts,
        ];
    }
    
    /**
     * Create from existing Skill
     */
    public static function fromSkill(Skill $skill): self
    {
        $form = new self();
        
        $form->name = $skill->name;
        $form->displayName = $skill->metadata['display_name'] ?? ucwords(str_replace('-', ' ', $skill->name));
        $form->description = $skill->description;
        $form->category = $skill->metadata['category'] ?? 'general';
        $form->toolsRequired = $skill->toolsRequired;
        $form->alwaysOn = $skill->alwaysOn;
        $form->metadata = $skill->metadata;
        
        // Hierarchy
        $form->parentSkill = $skill->parentSkill;
        $form->group = $skill->group;
        $form->order = $skill->order;
        
        // Dependencies
        $form->requires = $skill->requires;
        $form->suggests = $skill->suggests;
        $form->conflicts = $skill->conflicts;
        
        // Parse instructions from body
        $form->instructions = InstructionSection::parseFromMarkdown($skill->getBody());
        
        // Load references
        foreach ($skill->listReferences() as $refName) {
            $content = $skill->getReference($refName);
            if ($content) {
                $ref = new SkillReference();
                $ref->name = $refName;
                $ref->title = ucwords(str_replace(['-', '_', '.md'], [' ', ' ', ''], $refName));
                $ref->content = $content;
                $form->references[] = $ref;
            }
        }
        
        return $form;
    }
    
    /**
     * Generate slug from display name
     */
    public static function generateSlug(string $displayName): string
    {
        $slug = strtolower($displayName);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug;
    }
}
