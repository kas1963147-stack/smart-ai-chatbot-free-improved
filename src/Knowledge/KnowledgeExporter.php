<?php
declare(strict_types=1);
/**
 * Knowledge Exporter/Importer
 * 
 * Export and import knowledge base data.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Exporter
 */
class KnowledgeExporter {
    
    /**
     * Export all knowledge data
     */
    public static function exportAll(): array {
        $sources = KnowledgeSource::all();
        $export = [
            'version' => '1.0',
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'sources' => [],
            'config' => KnowledgeConfig::all(),
        ];
        
        foreach ($sources as $source) {
            $docs = KnowledgeDocument::forSource($source->id);
            
            $sourceData = $source->toArray();
            $sourceData['documents'] = [];
            
            foreach ($docs as $doc) {
                $docData = $doc->toArray();
                $docData['content'] = $doc->getContent();
                $sourceData['documents'][] = $docData;
            }
            
            $export['sources'][] = $sourceData;
        }
        
        return $export;
    }
    
    /**
     * Export to JSON file
     */
    public static function exportToFile(): string {
        $data = self::exportAll();
        
        $filename = 'knowledge-export-' . date('Y-m-d-His') . '.json';
        $path = KnowledgeConfig::getKnowledgePath() . '/' . $filename;
        
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        
        return $path;
    }
    
    /**
     * Export single source
     */
    public static function exportSource(int $sourceId): ?array {
        $source = KnowledgeSource::find($sourceId);
        if (!$source) {
            return null;
        }
        
        $docs = KnowledgeDocument::forSource($sourceId);
        
        $export = $source->toArray();
        $export['documents'] = [];
        
        foreach ($docs as $doc) {
            $docData = $doc->toArray();
            $docData['content'] = $doc->getContent();
            $export['documents'][] = $docData;
        }
        
        return $export;
    }
}

/**
 * Knowledge Importer
 */
class KnowledgeImporter {
    
    /**
     * Import from JSON data
     */
    public static function import(array $data, bool $overwrite = false): array {
        $results = [
            'sources_created' => 0,
            'sources_updated' => 0,
            'docs_created' => 0,
            'errors' => [],
        ];
        
        if (!isset($data['sources']) || !is_array($data['sources'])) {
            $results['errors'][] = 'Invalid import data';
            return $results;
        }
        
        foreach ($data['sources'] as $sourceData) {
            try {
                $result = self::importSource($sourceData, $overwrite);
                $results['sources_created'] += $result['created'] ? 1 : 0;
                $results['sources_updated'] += $result['created'] ? 0 : 1;
                $results['docs_created'] += $result['docs_created'];
            } catch (\Throwable $e) {
                $results['errors'][] = "Source '{$sourceData['name']}': " . $e->getMessage();
            }
        }
        
        // Import config if present
        if (isset($data['config']) && !empty($data['config'])) {
            if ($overwrite) {
                KnowledgeConfig::save($data['config']);
            }
        }
        
        return $results;
    }
    
    /**
     * Import single source
     */
    protected static function importSource(array $sourceData, bool $overwrite): array {
        $existing = null;
        
        // Check for existing by name
        $sources = KnowledgeSource::all();
        foreach ($sources as $s) {
            if ($s->name === $sourceData['name']) {
                $existing = $s;
                break;
            }
        }
        
        if ($existing && !$overwrite) {
            return ['created' => false, 'docs_created' => 0];
        }
        
        $source = $existing ?: new KnowledgeSource();
        $source->name = $sourceData['name'];
        $source->sourceType = $sourceData['source_type'] ?? SourceType::FOLDER;
        $source->config = $sourceData['config'] ?? [];
        $source->save();
        
        $docsCreated = 0;
        
        // Import documents
        if (isset($sourceData['documents']) && is_array($sourceData['documents'])) {
            foreach ($sourceData['documents'] as $docData) {
                $doc = new KnowledgeDocument();
                $doc->sourceId = $source->id;
                $doc->title = $docData['title'];
                $doc->path = $docData['path'];
                $doc->contentType = $docData['content_type'] ?? 'md';
                $doc->wordCount = $docData['word_count'] ?? 0;
                
                if (isset($docData['metadata'])) {
                    foreach ($docData['metadata'] as $key => $value) {
                        $doc->setMetadata($key, $value);
                    }
                }
                
                // Save content to file
                if (!empty($docData['content'])) {
                    $path = $source->getFolderPath() . '/' . $doc->path;
                    $dir = dirname($path);
                    
                    if (!is_dir($dir)) {
                        wp_mkdir_p($dir);
                    }
                    
                    file_put_contents($path, $docData['content']);
                }
                
                $doc->save();
                $docsCreated++;
            }
            
            $source->updateDocCount();
        }
        
        return ['created' => !$existing, 'docs_created' => $docsCreated];
    }
    
    /**
     * Import from uploaded file
     */
    public static function importFromFile(array $file, bool $overwrite = false): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['errors' => ['Upload failed']];
        }
        
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['errors' => ['Invalid JSON file']];
        }
        
        return self::import($data, $overwrite);
    }
}
