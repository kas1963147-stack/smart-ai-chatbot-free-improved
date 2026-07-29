<?php
declare(strict_types=1);


/**
 * Skill Reference
 * 
 * Represents a reference document within a skill.
 * Reference documents contain detailed policies, procedures, etc.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SkillReference - Reference document in a skill
 */
class SkillReference
{
    /** Filename: refund-policy.md */
    public string $name = '';
    
    /** Display title: Refund Policy */
    public string $title = '';
    
    /** File content */
    public string $content = '';
    
    /** File size in bytes */
    public int $size = 0;
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        $ref = new self();
        
        $ref->name = $data['name'] ?? '';
        $ref->title = $data['title'] ?? '';
        $ref->content = $data['content'] ?? '';
        $ref->size = $data['size'] ?? strlen($ref->content);
        
        // Generate filename from title if not provided
        if (empty($ref->name) && !empty($ref->title)) {
            $ref->name = self::generateFilename($ref->title);
        }
        
        return $ref;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'content' => $this->content,
            'size' => $this->size,
        ];
    }
    
    /**
     * Convert to array without content (for listing)
     */
    public function toArrayWithoutContent(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'size' => $this->size,
        ];
    }
    
    /**
     * Save reference to skill folder
     */
    public function save(string $skillPath): bool
    {
        $refsDir = $skillPath . '/references';
        
        // Create references directory if needed
        if (!is_dir($refsDir)) {
            if (!mkdir($refsDir, 0755, true)) {
                return false;
            }
        }
        
        $filePath = $refsDir . '/' . $this->name;
        
        $result = file_put_contents($filePath, $this->content);
        
        if ($result !== false) {
            $this->size = $result;
            return true;
        }
        
        return false;
    }
    
    /**
     * Load reference from skill folder
     */
    public static function load(string $skillPath, string $name): ?self
    {
        $filePath = $skillPath . '/references/' . $name;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $ref = new self();
        $ref->name = $name;
        $ref->content = file_get_contents($filePath);
        $ref->size = strlen($ref->content);
        $ref->title = self::generateTitleFromFilename($name);
        
        return $ref;
    }
    
    /**
     * Delete reference from skill folder
     */
    public function delete(string $skillPath): bool
    {
        $filePath = $skillPath . '/references/' . $this->name;
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return false;
    }
    
    /**
     * Generate filename from title
     */
    public static function generateFilename(string $title): string
    {
        $filename = strtolower($title);
        $filename = preg_replace('/[^a-z0-9\s-]/', '', $filename);
        $filename = preg_replace('/[\s_]+/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-');
        
        return $filename . '.md';
    }
    
    /**
     * Generate title from filename
     */
    public static function generateTitleFromFilename(string $filename): string
    {
        $title = str_replace(['-', '_', '.md'], [' ', ' ', ''], $filename);
        return ucwords(trim($title));
    }
}
