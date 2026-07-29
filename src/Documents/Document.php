<?php
declare(strict_types=1);


/**
 * Document Data Class
 * 
 * Represents a document file within a section.
 * Supports on-demand content loading and line-range reading.
 * 
 * @package Quarksol\SmartChatbot\Documents
 */

namespace Quarksol\SmartChatbot\Documents;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Document - Knowledge base document
 */
class Document
{
    /** Unique identifier (generated from section + filename) */
    public string $id = '';
    
    /** Filename */
    public string $name = '';
    
    /** Display title */
    public string $title = '';
    
    /** Parent section ID */
    public string $sectionId = '';
    
    /** Full file path */
    public string $path = '';
    
    /** File size in bytes */
    public int $size = 0;
    
    /** Total line count */
    public int $lines = 0;
    
    /** File extension */
    public string $type = '';
    
    /** Full content (loaded on-demand) */
    public ?string $content = null;
    
    /** Line array cache */
    protected ?array $lineCache = null;
    
    /**
     * Create document from file path
     */
    public static function fromFile(string $path, string $sectionId): ?self
    {
        if (!file_exists($path) || !is_file($path)) {
            return null;
        }
        
        $doc = new self();
        $doc->path = $path;
        $doc->name = basename($path);
        $doc->sectionId = $sectionId;
        $doc->id = self::generateId($sectionId, $doc->name);
        $doc->type = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $doc->size = filesize($path);
        $doc->title = self::generateTitle($doc->name);
        
        // Count lines without loading full content
        $doc->lines = self::countLines($path);
        
        return $doc;
    }
    
    /**
     * Generate document ID from section and filename
     */
    public static function generateId(string $sectionId, string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        return $sectionId . '/' . trim($name, '-');
    }
    
    /**
     * Generate title from filename
     */
    public static function generateTitle(string $filename): string
    {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['-', '_'], ' ', $title);
        return ucwords(trim($title));
    }
    
    /**
     * Count lines in file efficiently
     */
    protected static function countLines(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'r');
        
        if ($handle) {
            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line !== false) {
                    $count++;
                }
            }
            fclose($handle);
        }
        
        return $count;
    }
    
    /**
     * Check if content is loaded
     */
    public function isLoaded(): bool
    {
        return $this->content !== null;
    }
    
    /**
     * Load full content
     */
    public function load(): self
    {
        if (!file_exists($this->path)) {
            return $this;
        }
        
        $this->content = file_get_contents($this->path);
        $this->lineCache = null; // Reset cache
        
        return $this;
    }
    
    /**
     * Get full content
     */
    public function getContent(): string
    {
        if (!$this->isLoaded()) {
            $this->load();
        }
        
        return $this->content ?? '';
    }
    
    /**
     * Get lines as array
     */
    protected function getLinesArray(): array
    {
        if ($this->lineCache !== null) {
            return $this->lineCache;
        }
        
        if (!$this->isLoaded()) {
            $this->load();
        }
        
        $this->lineCache = explode("\n", $this->content ?? '');
        return $this->lineCache;
    }
    
    /**
     * Get specific lines (1-indexed)
     * 
     * @param int $startLine Starting line (1-indexed)
     * @param int|null $count Number of lines (null = to end)
     * @return array{content: string, start: int, end: int, total: int}
     */
    public function getLines(int $startLine = 1, ?int $count = null): array
    {
        $lines = $this->getLinesArray();
        $total = count($lines);
        
        // Clamp start line
        $startLine = max(1, min($startLine, $total));
        $startIndex = $startLine - 1;
        
        // Determine end
        if ($count === null) {
            $endIndex = $total - 1;
        } else {
            $endIndex = min($startIndex + $count - 1, $total - 1);
        }
        
        // Extract lines
        $extracted = array_slice($lines, $startIndex, $endIndex - $startIndex + 1);
        
        return [
            'content' => implode("\n", $extracted),
            'start' => $startLine,
            'end' => $endIndex + 1,
            'total' => $total,
        ];
    }
    
    /**
     * Search for keyword in content
     * 
     * @param string $query Search query
     * @param int $contextLines Lines of context around match
     * @return array Search results with line numbers and snippets
     */
    public function search(string $query, int $contextLines = 2): array
    {
        $lines = $this->getLinesArray();
        $results = [];
        $queryLower = strtolower($query);
        
        foreach ($lines as $index => $line) {
            if (stripos($line, $query) !== false) {
                // Get context
                $start = max(0, $index - $contextLines);
                $end = min(count($lines) - 1, $index + $contextLines);
                
                $contextArray = array_slice($lines, $start, $end - $start + 1);
                
                $results[] = [
                    'line' => $index + 1,
                    'match' => trim($line),
                    'context' => implode("\n", $contextArray),
                    'context_start' => $start + 1,
                    'context_end' => $end + 1,
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get metadata for API response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'section_id' => $this->sectionId,
            'size' => $this->size,
            'lines' => $this->lines,
            'type' => $this->type,
        ];
    }
    
    /**
     * Get summary for listing
     */
    public function getSummary(): string
    {
        $sizeKb = round($this->size / 1024, 1);
        return "- {$this->title} ({$this->type}, {$sizeKb}KB, {$this->lines} lines)";
    }
}
