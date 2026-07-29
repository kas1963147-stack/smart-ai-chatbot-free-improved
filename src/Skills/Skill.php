<?php
declare(strict_types=1);


/**
 * Skill Data Class
 * 
 * Represents an agent skill following the Agent Skills open standard.
 * Skills are markdown files with YAML frontmatter that define specialized
 * behaviors and knowledge loaded on-demand.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Skill - Agent capability/behavior definition
 * 
 * Follows the Agent Skills specification (agentskills.io)
 */
class Skill
{
    /** Skill name (identifier) */
    public string $name;
    
    /** Short description for discovery */
    public string $description;
    
    /** Path to skill directory */
    public string $path;
    
    /** Full SKILL.md content (loaded on-demand) */
    public ?string $content = null;
    
    /** Required tools for this skill */
    public array $toolsRequired = [];
    
    /** Whether this skill is always included in context */
    public bool $alwaysOn = false;
    
    /** Custom metadata from frontmatter */
    public array $metadata = [];
    
    /** Compatibility requirements */
    public ?string $compatibility = null;
    
    /** License information */
    public ?string $license = null;
    
    // ============================================
    // Hierarchy & Organization
    // ============================================
    
    /** Parent skill ID (for nested skills) */
    public ?string $parentSkill = null;
    
    /** Child skill IDs (populated by registry) */
    public array $childSkills = [];
    
    /** Group/folder ID for organization */
    public ?string $group = null;
    
    /** Display order within group/parent */
    public int $order = 0;
    
    // ============================================
    // Dependencies
    // ============================================
    
    /** Skills that must be loaded before this one */
    public array $requires = [];
    
    /** Skills that are suggested to load after this */
    public array $suggests = [];
    
    /** Skills that conflict with this one */
    public array $conflicts = [];
    
    /**
     * Create skill from directory path
     */
    public function __construct(string $path)
    {
        $this->path = $path;
        $this->name = basename($path);
    }
    
    /**
     * Check if skill content is loaded
     */
    public function isLoaded(): bool
    {
        return $this->content !== null;
    }
    
    /**
     * Load full skill content from SKILL.md
     */
    public function load(): self
    {
        $skillFile = $this->path . '/SKILL.md';
        
        if (!file_exists($skillFile)) {
            return $this;
        }
        
        $this->content = file_get_contents($skillFile);
        return $this;
    }
    
    /**
     * Parse skill from SKILL.md file
     */
    public static function fromFile(string $skillPath): ?self
    {
        $skillFile = $skillPath . '/SKILL.md';
        
        if (!file_exists($skillFile)) {
            // Check for legacy .md file (not in folder)
            if (is_file($skillPath) && str_ends_with($skillPath, '.md')) {
                return self::fromLegacyFile($skillPath);
            }
            return null;
        }
        
        $content = file_get_contents($skillFile);
        return self::parse($content, $skillPath);
    }
    
    /**
     * Parse legacy skill file (flat .md file)
     */
    public static function fromLegacyFile(string $filePath): ?self
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        $dirPath = dirname($filePath);
        $name = pathinfo($filePath, PATHINFO_FILENAME);
        
        return self::parse($content, $dirPath, $name);
    }
    
    /**
     * Parse skill content and extract frontmatter
     */
    protected static function parse(string $content, string $path, ?string $legacyName = null): self
    {
        $skill = new self($path);
        
        // Extract YAML frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontmatter = $matches[1];
            $body = $matches[2];
            
            // Parse YAML manually (simple key: value)
            $metadata = self::parseYaml($frontmatter);
            
            $skill->name = $metadata['name'] ?? $metadata['slug'] ?? $legacyName ?? basename($path);
            $skill->description = $metadata['description'] ?? '';
            $skill->alwaysOn = ($metadata['always_on'] ?? false) === true || ($metadata['always_on'] ?? '') === 'true';
            $skill->compatibility = $metadata['compatibility'] ?? null;
            $skill->license = $metadata['license'] ?? null;
            
            // Parse tools_required (can be array or comma-separated)
            if (isset($metadata['tools_required'])) {
                if (is_array($metadata['tools_required'])) {
                    $skill->toolsRequired = $metadata['tools_required'];
                } else {
                    $skill->toolsRequired = array_map('trim', explode(',', 
                        str_replace(['[', ']'], '', $metadata['tools_required'])
                    ));
                }
            }
            
            // Parse hierarchy fields
            $skill->parentSkill = $metadata['parent'] ?? $metadata['parent_skill'] ?? null;
            $skill->group = $metadata['group'] ?? null;
            $skill->order = (int)($metadata['order'] ?? 0);
            
            // Parse dependencies
            $skill->requires = self::parseArrayField($metadata, 'requires');
            $skill->suggests = self::parseArrayField($metadata, 'suggests');
            $skill->conflicts = self::parseArrayField($metadata, 'conflicts');
            
            // Store extra metadata
            $reserved = ['name', 'slug', 'description', 'tools_required', 'always_on', 
                         'compatibility', 'license', 'parent', 'parent_skill', 'group', 
                         'order', 'requires', 'suggests', 'conflicts'];
            $skill->metadata = array_diff_key($metadata, array_flip($reserved));
            
        } else {
            // No frontmatter
            $skill->name = $legacyName ?? basename($path);
            $skill->description = '';
        }
        
        return $skill;
    }
    
    /**
     * Parse array field from metadata (handles string or array)
     */
    protected static function parseArrayField(array $metadata, string $key): array
    {
        if (!isset($metadata[$key])) {
            return [];
        }
        
        $value = $metadata[$key];
        
        if (is_array($value)) {
            return $value;
        }
        
        // Parse comma-separated or bracket notation
        $value = str_replace(['[', ']'], '', $value);
        return array_filter(array_map('trim', explode(',', $value)));
    }
    
    /**
     * Simple YAML parser for frontmatter
     */
    protected static function parseYaml(string $yaml): array
    {
        $result = [];
        $lines = explode("\n", $yaml);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $m)) {
                $key = $m[1];
                $value = trim($m[2]);
                
                // Remove quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                
                // Handle booleans
                if ($value === 'true') $value = true;
                elseif ($value === 'false') $value = false;
                
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Get body content (without frontmatter)
     */
    public function getBody(): string
    {
        if (!$this->content) {
            $this->load();
        }
        
        if (!$this->content) {
            return '';
        }
        
        // Remove frontmatter
        if (preg_match('/^---\s*\n.*?\n---\s*\n(.*)$/s', $this->content, $matches)) {
            return trim($matches[1]);
        }
        
        return trim($this->content);
    }
    
    /**
     * Get summary for system prompt (name + description only)
     */
    public function getSummary(): string
    {
        return "- **{$this->name}**: {$this->description}";
    }
    
    /**
     * Get full content for activation
     */
    public function getFullContent(): string
    {
        if (!$this->content) {
            $this->load();
        }
        
        return $this->content ?? '';
    }
    
    /**
     * Check if skill has reference files
     */
    public function hasReferences(): bool
    {
        return is_dir($this->path . '/references');
    }
    
    /**
     * Get reference file content
     */
    public function getReference(string $filename): ?string
    {
        $refPath = $this->path . '/references/' . $filename;
        
        if (!file_exists($refPath)) {
            return null;
        }
        
        return file_get_contents($refPath);
    }
    
    /**
     * List available reference files
     */
    public function listReferences(): array
    {
        $refDir = $this->path . '/references';
        
        if (!is_dir($refDir)) {
            return [];
        }
        
        return array_filter(scandir($refDir), function($f) use ($refDir) {
            return is_file($refDir . '/' . $f) && $f !== '.' && $f !== '..';
        });
    }
    
    /**
     * Get required tools for this skill
     * 
     * @return array List of required tool IDs
     */
    public function getRequiredTools(): array
    {
        return $this->toolsRequired;
    }
    
    /**
     * Check if skill has any tool requirements
     * 
     * @return bool
     */
    public function hasToolRequirements(): bool
    {
        return !empty($this->toolsRequired);
    }
    
    /**
     * Validate skill against a list of available tools
     * 
     * @param array $availableTools List of available tool IDs
     * @return array{compatible: bool, missing_tools: array}
     */
    public function validateAgainstTools(array $availableTools): array
    {
        if (empty($this->toolsRequired)) {
            return [
                'compatible' => true,
                'missing_tools' => [],
            ];
        }
        
        $missingTools = array_diff($this->toolsRequired, $availableTools);
        
        return [
            'compatible' => empty($missingTools),
            'missing_tools' => array_values($missingTools),
        ];
    }
    
    /**
     * Check if skill is compatible with given tools
     * 
     * @param array $availableTools List of available tool IDs
     * @return bool
     */
    public function isCompatibleWith(array $availableTools): bool
    {
        $result = $this->validateAgainstTools($availableTools);
        return $result['compatible'];
    }
}
