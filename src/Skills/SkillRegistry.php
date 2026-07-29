<?php
declare(strict_types=1);


/**
 * Skill Registry
 * 
 * Central registry for discovering and loading agent skills.
 * Implements two-phase loading: metadata at startup, full content on-demand.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Skill Registry - Discovery and loading
 */
class SkillRegistry
{
    /** Skills base path */
    protected static ?string $basePath = null;
    
    /** Discovered skills (metadata only) */
    protected static array $skills = [];
    
    /** Whether skills have been discovered */
    protected static bool $discovered = false;
    
    /**
     * Set the base path for skills
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = $path;
        self::$discovered = false;
        self::$skills = [];
    }
    
    /**
     * Get the base path for skills
     */
    public static function getBasePath(): string
    {
        if (self::$basePath === null) {
            // Default to plugin's skills folder
            self::$basePath = dirname(__DIR__, 2) . '/skills';
        }
        return self::$basePath;
    }
    
    /**
     * Discover all skills (loads metadata only)
     */
    public static function discover(): array
    {
        if (self::$discovered) {
            return self::$skills;
        }
        
        self::$skills = [];
        $basePath = self::getBasePath();
        
        if (!is_dir($basePath)) {
            return self::$skills;
        }
        
        // Scan for skills in nested structure: category/skill-name/SKILL.md
        self::discoverDirectory($basePath);
        
        // Also check for legacy flat files
        self::discoverLegacyFiles($basePath);
        
        self::$discovered = true;
        return self::$skills;
    }
    
    /**
     * Recursively discover skills in directories
     */
    protected static function discoverDirectory(string $dir, int $depth = 0): void
    {
        if ($depth > 2) return; // Max depth: category/skill-name
        
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            // Skip templates directory from active skills
            if ($depth === 0 && $item === 'templates') continue;
            
            $path = $dir . '/' . $item;
            
            // Check for SKILL.md in this directory
            if (file_exists($path . '/SKILL.md')) {
                $skill = Skill::fromFile($path);
                if ($skill) {
                    self::$skills[$skill->name] = $skill;
                }
            } elseif (is_dir($path)) {
                // Recurse into subdirectory
                self::discoverDirectory($path, $depth + 1);
            }
        }
    }
    
    /**
     * Discover legacy flat markdown files
     */
    protected static function discoverLegacyFiles(string $dir): void
    {
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            
            // Check for .md files (legacy format)
            if (is_file($path) && str_ends_with($item, '.md') && $item !== 'SKILL.md') {
                $skill = Skill::fromLegacyFile($path);
                if ($skill && !isset(self::$skills[$skill->name])) {
                    self::$skills[$skill->name] = $skill;
                }
            }
        }
    }
    
    /**
     * Get skill summaries for system prompt
     * 
     * Returns a compact list of skill names and descriptions.
     * If enabledSkills is provided, only those skills are included.
     * If enabledSkills is empty array, returns empty (agent has no skills enabled).
     * If enabledSkills is null, returns all skills (for backward compatibility).
     * 
     * @param array|null $enabledSkills List of enabled skill names, or null for all
     * @return string Formatted skill summaries for system prompt
     */
    public static function getSummariesForPrompt(?array $enabledSkills = null): string
    {
        $skills = self::discover();
        
        if (empty($skills)) {
            return '';
        }
        
        // If enabledSkills is an empty array, agent has no skills enabled
        if ($enabledSkills !== null && empty($enabledSkills)) {
            return '';
        }
        
        // Filter skills if enabledSkills list is provided
        if ($enabledSkills !== null) {
            $filteredSkills = [];
            foreach ($skills as $name => $skill) {
                // Include if in enabled list OR if skill is always_on
                if (in_array($name, $enabledSkills, true) || $skill->alwaysOn) {
                    $filteredSkills[$name] = $skill;
                }
            }
            $skills = $filteredSkills;
        }
        
        if (empty($skills)) {
            return '';
        }
        
        $summaries = ["## Available Skills\n"];
        $summaries[] = "You can load detailed instructions using the `load_skill` tool when needed.\n";
        
        foreach ($skills as $skill) {
            $summaries[] = $skill->getSummary();
        }
        
        return implode("\n", $summaries);
    }
    
    /**
     * Load full skill content by name
     */
    public static function loadSkill(string $skillName): ?Skill
    {
        $skills = self::discover();
        
        if (!isset($skills[$skillName])) {
            // Try case-insensitive match
            foreach ($skills as $name => $skill) {
                if (strtolower($name) === strtolower($skillName)) {
                    return $skill->load();
                }
            }
            return null;
        }
        
        return $skills[$skillName]->load();
    }
    
    /**
     * Get skill by name (without loading content)
     */
    public static function getSkill(string $skillName): ?Skill
    {
        $skills = self::discover();
        return $skills[$skillName] ?? null;
    }
    
    /**
     * Get all skills (metadata only)
     */
    public static function getAll(): array
    {
        return self::discover();
    }
    
    /**
     * Get always-on skills (included in every conversation)
     */
    public static function getAlwaysOnSkills(): array
    {
        $skills = self::discover();
        
        return array_filter($skills, fn($skill) => $skill->alwaysOn);
    }
    
    /**
     * Get skills for a specific agent (filtered by config)
     */
    public static function getSkillsForAgent(\Quarksol\SmartChatbot\Config\AgentConfig $config): array
    {
        $skills = self::discover();
        
        return array_filter($skills, function($skill) use ($config) {
            return $skill->alwaysOn || $config->isSkillEnabled($skill->name);
        });
    }
    
    /**
     * Get skill summaries for a specific agent
     */
    public static function getSummariesForAgent(\Quarksol\SmartChatbot\Config\AgentConfig $config): string
    {
        $skills = self::getSkillsForAgent($config);
        
        if (empty($skills)) {
            return '';
        }
        
        $summaries = ["## Available Skills\n"];
        $summaries[] = "You can load detailed instructions using the `load_skill` tool when needed.\n";
        
        foreach ($skills as $skill) {
            $summaries[] = $skill->getSummary();
        }
        
        return implode("\n", $summaries);
    }
    
    /**
     * Get skills that require specific tools
     */
    public static function getSkillsRequiringTool(string $toolName): array
    {
        $skills = self::discover();
        
        return array_filter($skills, function($skill) use ($toolName) {
            return in_array($toolName, $skill->toolsRequired, true);
        });
    }
    
    /**
     * Get skill categories (based on folder structure)
     */
    public static function getCategories(): array
    {
        $basePath = self::getBasePath();
        $categories = [];
        
        if (!is_dir($basePath)) {
            return $categories;
        }
        
        $items = scandir($basePath);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if ($item === 'templates') continue; // Skip templates category
            
            $path = $basePath . '/' . $item;
            
            if (is_dir($path)) {
                $categories[$item] = [
                    'name' => ucfirst(str_replace('-', ' ', $item)),
                    'path' => $path,
                    'skills' => [],
                ];
                
                // Count skills in this category
                $subItems = scandir($path);
                foreach ($subItems as $subItem) {
                    if ($subItem === '.' || $subItem === '..') continue;
                    if (file_exists($path . '/' . $subItem . '/SKILL.md')) {
                        $categories[$item]['skills'][] = $subItem;
                    }
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * Refresh skill discovery
     */
    public static function refresh(): void
    {
        self::$discovered = false;
        self::$skills = [];
        self::discover();
    }
    
    /**
     * Get summary for admin display
     */
    public static function getSummary(): array
    {
        $skills = self::discover();
        
        return [
            'total' => count($skills),
            'always_on' => count(array_filter($skills, fn($s) => $s->alwaysOn)),
            'skills' => array_map(fn($s) => [
                'name' => $s->name,
                'description' => $s->description,
                'tools_required' => $s->toolsRequired,
                'always_on' => $s->alwaysOn,
            ], $skills),
        ];
    }
    
    /**
     * Validate skills for an agent configuration
     * 
     * Returns validation results showing which skills have missing tool requirements.
     * This is a convenience wrapper around SkillToolValidator.
     * 
     * @param \Quarksol\SmartChatbot\Config\AgentConfig $config Agent configuration
     * @return array Validation results from SkillToolValidator
     */
    public static function validateSkillsForAgent(\Quarksol\SmartChatbot\Config\AgentConfig $config): array
    {
        return SkillToolValidator::validateAll($config);
    }
    
    /**
     * Get skills that have missing tool requirements for an agent
     * 
     * @param \Quarksol\SmartChatbot\Config\AgentConfig $config Agent configuration
     * @return array Skills with missing tools
     */
    public static function getSkillsWithMissingTools(\Quarksol\SmartChatbot\Config\AgentConfig $config): array
    {
        return SkillToolValidator::getSkillsWithMissingTools($config);
    }
    
    /**
     * Get warning message for skills with missing tools
     * 
     * @param \Quarksol\SmartChatbot\Config\AgentConfig $config Agent configuration
     * @return string|null Warning message or null if no issues
     */
    public static function getToolWarningMessage(\Quarksol\SmartChatbot\Config\AgentConfig $config): ?string
    {
        $results = self::validateSkillsForAgent($config);
        return SkillToolValidator::getWarningMessage($results);
    }
}
