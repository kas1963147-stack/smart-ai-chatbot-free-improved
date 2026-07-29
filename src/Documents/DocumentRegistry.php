<?php
declare(strict_types=1);


/**
 * Document Registry
 * 
 * Central registry for discovering and loading documents.
 * Implements lazy loading - metadata at startup, full content on-demand.
 * 
 * @package Quarksol\SmartChatbot\Documents
 */

namespace Quarksol\SmartChatbot\Documents;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DocumentRegistry - Discovery and loading
 */
class DocumentRegistry
{
    /** Documents base path */
    protected static ?string $basePath = null;
    
    /** Discovered documents cache */
    protected static array $documents = [];
    
    /** Whether documents have been discovered */
    protected static bool $discovered = false;
    
    /**
     * Set the base path for documents
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = $path;
        self::$discovered = false;
        self::$documents = [];
    }
    
    /**
     * Get the base path for documents
     */
    public static function getBasePath(): string
    {
        if (self::$basePath === null) {
            self::$basePath = dirname(__DIR__, 2) . '/documents';
        }
        return self::$basePath;
    }
    
    /**
     * Discover all documents across all sections
     */
    public static function discover(): array
    {
        if (self::$discovered) {
            return self::$documents;
        }
        
        self::$documents = [];
        $sections = DocumentSection::getAll();
        
        foreach ($sections as $section) {
            $sectionPath = $section->getDocumentsPath();
            
            if (!is_dir($sectionPath)) {
                continue;
            }
            
            $files = scandir($sectionPath);
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $filePath = $sectionPath . '/' . $file;
                
                if (!is_file($filePath)) continue;
                
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $section->allowedTypes)) continue;
                
                $doc = Document::fromFile($filePath, $section->id);
                if ($doc) {
                    self::$documents[$doc->id] = $doc;
                }
            }
        }
        
        self::$discovered = true;
        return self::$documents;
    }
    
    /**
     * Get all sections
     */
    public static function getSections(): array
    {
        return DocumentSection::getAll();
    }
    
    /**
     * Get documents in a specific section
     */
    public static function getDocuments(?string $sectionId = null): array
    {
        $docs = self::discover();
        
        if ($sectionId === null) {
            return $docs;
        }
        
        return array_filter($docs, fn($doc) => $doc->sectionId === $sectionId);
    }
    
    /**
     * Get document by ID (without loading content)
     */
    public static function getDocument(string $docId): ?Document
    {
        $docs = self::discover();
        return $docs[$docId] ?? null;
    }
    
    /**
     * Load document with full content
     */
    public static function loadDocument(string $docId): ?Document
    {
        $doc = self::getDocument($docId);
        
        if (!$doc) {
            // Try fuzzy match
            foreach (self::$documents as $id => $document) {
                if (stripos($id, $docId) !== false || 
                    stripos($document->name, $docId) !== false ||
                    stripos($document->title, $docId) !== false) {
                    return $document->load();
                }
            }
            return null;
        }
        
        return $doc->load();
    }
    
    /**
     * Get section summaries for system prompt
     * 
     * @param array|null $enabledSections List of enabled section IDs, or null for all
     * @return string Formatted section summaries
     */
    public static function getSectionSummariesForPrompt(?array $enabledSections = null): string
    {
        $sections = self::getSections();
        
        if (empty($sections)) {
            return '';
        }
        
        // Filter by enabled sections if provided
        if ($enabledSections !== null) {
            if (empty($enabledSections)) {
                return ''; // No sections enabled
            }
            
            $sections = array_filter($sections, fn($s) => in_array($s->id, $enabledSections, true));
        }
        
        if (empty($sections)) {
            return '';
        }
        
        $summaries = ["## Available Document Sections\n"];
        $summaries[] = "Use `list_documents` to see files, `read_document` to read content, `search_documents` to search.\n";
        
        foreach ($sections as $section) {
            $summaries[] = $section->getSummary();
        }
        
        return implode("\n", $summaries);
    }
    
    /**
     * Search across all documents
     * 
     * @param string $query Search query
     * @param string|null $sectionId Limit to section
     * @param int $maxResults Maximum results
     * @return array Search results
     */
    public static function searchDocuments(string $query, ?string $sectionId = null, int $maxResults = 5): array
    {
        $docs = self::getDocuments($sectionId);
        $results = [];
        
        foreach ($docs as $doc) {
            $matches = $doc->search($query);
            
            foreach ($matches as $match) {
                $results[] = [
                    'document_id' => $doc->id,
                    'document_title' => $doc->title,
                    'section_id' => $doc->sectionId,
                    'line' => $match['line'],
                    'match' => $match['match'],
                    'context' => $match['context'],
                ];
                
                if (count($results) >= $maxResults) {
                    break 2;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Refresh discovery cache
     */
    public static function refresh(): void
    {
        self::$discovered = false;
        self::$documents = [];
        self::discover();
    }
    
    /**
     * Get summary for admin display
     */
    public static function getSummary(): array
    {
        $docs = self::discover();
        $sections = self::getSections();
        
        return [
            'total_sections' => count($sections),
            'total_documents' => count($docs),
            'sections' => array_map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'document_count' => $s->getDocumentCount(),
            ], $sections),
        ];
    }
}
