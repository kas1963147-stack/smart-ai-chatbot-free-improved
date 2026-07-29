<?php
declare(strict_types=1);


/**
 * Document Section
 * 
 * Represents a section/folder for organizing documents.
 * Sections are stored in WordPress options and have required descriptions
 * that are shown to the AI in the system prompt.
 * 
 * @package Quarksol\SmartChatbot\Documents
 */

namespace Quarksol\SmartChatbot\Documents;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DocumentSection - Container for documents
 */
class DocumentSection
{
    /** Unique identifier (slug) */
    public string $id = '';
    
    /** Display name */
    public string $name = '';
    
    /** Description shown to AI (required) */
    public string $description = '';
    
    /** Filesystem path */
    public string $path = '';
    
    /** Maximum file size in bytes (default 100KB) */
    public int $maxFileSize = 102400;
    
    /** Allowed file extensions */
    public array $allowedTypes = ['md', 'txt', 'html'];
    
    /** Display order */
    public int $order = 0;
    
    /** Creation timestamp */
    public int $createdAt = 0;
    
    /** Update timestamp */
    public int $updatedAt = 0;
    
    /** WordPress option key */
    const OPTION_KEY = 'swc_document_sections';
    
    /**
     * Create section from array
     */
    public static function fromArray(array $data): self
    {
        $section = new self();
        
        $section->id = $data['id'] ?? '';
        $section->name = $data['name'] ?? '';
        $section->description = $data['description'] ?? '';
        $section->path = $data['path'] ?? '';
        $section->maxFileSize = (int)($data['max_file_size'] ?? 102400);
        $section->allowedTypes = $data['allowed_types'] ?? ['md', 'txt', 'html'];
        $section->order = (int)($data['order'] ?? 0);
        $section->createdAt = (int)($data['created_at'] ?? time());
        $section->updatedAt = (int)($data['updated_at'] ?? time());
        
        return $section;
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
            'path' => $this->path,
            'max_file_size' => $this->maxFileSize,
            'allowed_types' => $this->allowedTypes,
            'order' => $this->order,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
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
        $id = preg_replace('/-+/', '-', $id);
        return trim($id, '-');
    }
    
    /**
     * Get all sections
     */
    public static function getAll(): array
    {
        $data = get_option(self::OPTION_KEY, []);
        
        if (!is_array($data)) {
            return [];
        }
        
        $sections = [];
        foreach ($data as $item) {
            $sections[] = self::fromArray($item);
        }
        
        // Sort by order
        usort($sections, fn($a, $b) => $a->order <=> $b->order);
        
        return $sections;
    }
    
    /**
     * Get section by ID
     */
    public static function get(string $id): ?self
    {
        $sections = self::getAll();
        
        foreach ($sections as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }
        
        return null;
    }
    
    /**
     * Save section
     */
    public function save(): bool
    {
        $this->updatedAt = time();
        
        $allSections = get_option(self::OPTION_KEY, []);
        if (!is_array($allSections)) {
            $allSections = [];
        }
        
        // Find existing or add new
        $found = false;
        foreach ($allSections as $i => $item) {
            if (($item['id'] ?? '') === $this->id) {
                $allSections[$i] = $this->toArray();
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $this->createdAt = time();
            $allSections[] = $this->toArray();
        }
        
        return update_option(self::OPTION_KEY, $allSections);
    }
    
    /**
     * Delete section
     */
    public function delete(): bool
    {
        $allSections = get_option(self::OPTION_KEY, []);
        if (!is_array($allSections)) {
            return false;
        }
        
        $filtered = array_filter($allSections, fn($item) => ($item['id'] ?? '') !== $this->id);
        
        if (count($filtered) === count($allSections)) {
            return false; // Not found
        }
        
        return update_option(self::OPTION_KEY, array_values($filtered));
    }
    
    /**
     * Check if section exists
     */
    public static function exists(string $id): bool
    {
        return self::get($id) !== null;
    }
    
    /**
     * Get section's document folder path
     */
    public function getDocumentsPath(): string
    {
        if (!empty($this->path)) {
            return $this->path;
        }
        
        return DocumentManager::getBasePath() . '/' . $this->id;
    }
    
    /**
     * Count documents in this section
     */
    public function getDocumentCount(): int
    {
        $path = $this->getDocumentsPath();
        
        if (!is_dir($path)) {
            return 0;
        }
        
        $count = 0;
        $files = scandir($path);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (is_file($path . '/' . $file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, $this->allowedTypes)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get summary for system prompt
     */
    public function getSummary(): string
    {
        $count = $this->getDocumentCount();
        return "- **{$this->name}** ({$count} docs): {$this->description}";
    }
}
