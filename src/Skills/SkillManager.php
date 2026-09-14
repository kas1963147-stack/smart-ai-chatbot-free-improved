<?php
declare(strict_types=1);


/**
 * Skill Manager
 * 
 * Central class for skill CRUD operations.
 * Converts form data to SKILL.md format and manages file system.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SkillManager - CRUD operations for skills
 */
class SkillManager
{
    /** Skills base path */
    protected static ?string $basePath = null;

    public static function setBasePath(string $path): void { self::$basePath = $path; }

    /**
     * Get base path for skills. Writes go to wp_upload_dir() - NOT the plugin folder.
     */
    public static function getBasePath(): string {
        if (self::$basePath === null) {
            self::$basePath = wp_upload_dir()['basedir'] . '/smart-ai-chatbot/skills';
        }
        return self::$basePath;
    }

    public static function create(SkillFormData $formData): Skill {
        $validator = new SkillValidator();
        if (!$validator->validate($formData->toArray())) {
            throw new SkillValidationException(
                'Skill validation failed',
                $validator->getErrors()
            );
        }
        
        // Create folder structure
        $skillPath = self::createFolderStructure($formData->category, $formData->name);
        
        // Generate and write SKILL.md
        $markdown = self::toMarkdown($formData);
        self::writeSkillFile($skillPath, $markdown);
        
        // Save reference documents
        foreach ($formData->references as $ref) {
            $ref->save($skillPath);
        }
        
        // Refresh registry and return skill
        SkillRegistry::refresh();
        return SkillRegistry::getSkill($formData->name);
    }
    
    /**
     * Update an existing skill
     * 
     * @param string $skillId Skill identifier
     * @param SkillFormData $formData Updated form data
     * @return Skill Updated skill
     */
    public static function update(string $skillId, SkillFormData $formData): Skill
    {
        // Get existing skill
        $existingSkill = SkillRegistry::getSkill($skillId);
        if (!$existingSkill) {
            throw new SkillValidationException("Skill not found: {$skillId}");
        }
        
        // Validate (with existing ID for uniqueness check)
        $validator = new SkillValidator();
        if (!$validator->validate($formData->toArray(), true, $skillId)) {
            throw new SkillValidationException(
                'Skill validation failed',
                $validator->getErrors()
            );
        }
        
        $skillPath = $existingSkill->path;
        
        // Generate and write SKILL.md
        $markdown = self::toMarkdown($formData);
        self::writeSkillFile($skillPath, $markdown);
        
        // Handle references
        // Get existing references
        $existingRefs = $existingSkill->listReferences();
        $newRefNames = array_map(fn($r) => $r->name, $formData->references);
        
        // Delete removed references
        foreach ($existingRefs as $refName) {
            if (!in_array($refName, $newRefNames)) {
                $ref = new SkillReference();
                $ref->name = $refName;
                $ref->delete($skillPath);
            }
        }
        
        // Save new/updated references
        foreach ($formData->references as $ref) {
            $ref->save($skillPath);
        }
        
        // Refresh registry and return skill
        SkillRegistry::refresh();
        return SkillRegistry::getSkill($formData->name);
    }
    
    /**
     * Delete a skill and all its files
     * 
     * @param string $skillId Skill identifier
     * @return bool Success
     */
    public static function delete(string $skillId): bool
    {
        $skill = SkillRegistry::getSkill($skillId);
        if (!$skill) {
            return false;
        }
        
        // Delete skill directory recursively
        $deleted = self::deleteDirectory($skill->path);
        
        if ($deleted) {
            SkillRegistry::refresh();
        }
        
        return $deleted;
    }
    
    /**
     * Get skill as form data (for editing)
     * 
     * @param string $skillId Skill identifier
     * @return SkillFormData|null Form data
     */
    public static function getFormData(string $skillId): ?SkillFormData
    {
        $skill = SkillRegistry::loadSkill($skillId);
        if (!$skill) {
            return null;
        }
        
        return SkillFormData::fromSkill($skill);
    }
    
    /**
     * Convert form data to SKILL.md content
     */
    public static function toMarkdown(SkillFormData $formData): string
    {
        $lines = [];
        
        // === YAML Frontmatter ===
        $lines[] = '---';
        $lines[] = "name: {$formData->name}";
        $lines[] = "description: {$formData->description}";
        
        if (!empty($formData->toolsRequired)) {
            $tools = implode(', ', $formData->toolsRequired);
            $lines[] = "tools_required: [{$tools}]";
        }
        
        $lines[] = 'always_on: ' . ($formData->alwaysOn ? 'true' : 'false');
        
        // Add metadata
        $lines[] = 'metadata:';
        $lines[] = "  display_name: \"{$formData->displayName}\"";
        $lines[] = "  category: {$formData->category}";
        
        foreach ($formData->metadata as $key => $value) {
            if (!in_array($key, ['display_name', 'category'])) {
                $lines[] = "  {$key}: {$value}";
            }
        }
        
        $lines[] = '---';
        $lines[] = '';
        
        // === Title ===
        $displayName = $formData->displayName ?: ucwords(str_replace('-', ' ', $formData->name));
        $lines[] = "# {$displayName}";
        $lines[] = '';
        
        // === Instruction Sections ===
        foreach ($formData->instructions as $section) {
            $sectionMarkdown = $section->toMarkdown();
            if (!empty($sectionMarkdown)) {
                $lines[] = $sectionMarkdown;
                $lines[] = '';
            }
        }
        
        // === Reference Documents ===
        if (!empty($formData->references)) {
            $lines[] = '## Reference Documents';
            foreach ($formData->references as $ref) {
                $title = $ref->title ?: SkillReference::generateTitleFromFilename($ref->name);
                $lines[] = "- [{$title}](references/{$ref->name})";
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Parse SKILL.md back to form data
     */
    public static function fromMarkdown(string $markdown): SkillFormData
    {
        // This is handled by SkillFormData::fromSkill()
        // Parse the skill first, then convert to form data
        $tempPath = sys_get_temp_dir() . '/skill-parse-' . uniqid();
        mkdir($tempPath);
        file_put_contents($tempPath . '/SKILL.md', $markdown);
        
        $skill = Skill::fromFile($tempPath);
        $skill->load();
        
        $formData = SkillFormData::fromSkill($skill);
        
        // Cleanup
        unlink($tempPath . '/SKILL.md');
        rmdir($tempPath);
        
        return $formData;
    }
    
    /**
     * Create folder structure for skill
     */
    protected static function createFolderStructure(string $category, string $skillName): string
    {
        $basePath = self::getBasePath();
        $categoryPath = $basePath . '/' . $category;
        $skillPath = $categoryPath . '/' . $skillName;
        
        // Create category directory if needed
        if (!is_dir($categoryPath)) {
            mkdir($categoryPath, 0755, true);
        }
        
        // Create skill directory
        if (!is_dir($skillPath)) {
            mkdir($skillPath, 0755, true);
        }
        
        return $skillPath;
    }
    
    /**
     * Write SKILL.md file
     */
    protected static function writeSkillFile(string $path, string $content): bool
    {
        $filePath = $path . '/SKILL.md';
        return file_put_contents($filePath, $content) !== false;
    }
    
    /**
     * Delete directory recursively
     */
    protected static function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Get available categories
     */
    public static function getCategories(): array
    {
        return [
            'woocommerce' => [
                'id' => 'woocommerce',
                'name' => 'WooCommerce',
                'icon' => '',
                'description' => 'E-commerce related skills',
            ],
            'content' => [
                'id' => 'content',
                'name' => 'Content',
                'icon' => '',
                'description' => 'Content creation skills',
            ],
            'support' => [
                'id' => 'support',
                'name' => 'Support',
                'icon' => '',
                'description' => 'Customer support skills',
            ],
            'admin' => [
                'id' => 'admin',
                'name' => 'Admin',
                'icon' => 'ï¸',
                'description' => 'Administrative skills',
            ],
            'compliance' => [
                'id' => 'compliance',
                'name' => 'Compliance',
                'icon' => '',
                'description' => 'Compliance and legal skills',
            ],
            'marketing' => [
                'id' => 'marketing',
                'name' => 'Marketing',
                'icon' => '',
                'description' => 'Marketing and promotion skills',
            ],
            'seo' => [
                'id' => 'seo',
                'name' => 'SEO',
                'icon' => '',
                'description' => 'Search engine optimization skills',
            ],
            'templates' => [
                'id' => 'templates',
                'name' => 'Templates',
                'icon' => '',
                'description' => 'Skill templates',
            ],
            'general' => [
                'id' => 'general',
                'name' => 'General',
                'icon' => '',
                'description' => 'General purpose skills',
            ],
        ];
    }
}

/**
 * Skill Validation Exception
 */
class SkillValidationException extends \Exception
{
    protected array $errors;
    
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}

