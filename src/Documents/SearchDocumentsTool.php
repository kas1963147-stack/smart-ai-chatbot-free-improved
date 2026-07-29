<?php
declare(strict_types=1);


/**
 * Search Documents Tool
 * 
 * Neuron AI tool for searching across documents.
 * Returns matching snippets with context without loading full content.
 * 
 * @package Quarksol\SmartChatbot\Documents
 */

namespace Quarksol\SmartChatbot\Documents;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;
use Quarksol\SmartChatbot\Services\AgentContext;

/**
 * Tool for searching across documents
 */
class SearchDocumentsTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'search_documents',
            description: 'Search for keywords across documents. Returns matching snippets with surrounding context. Use this to find relevant information without reading entire documents.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Search query (keywords to find)',
                required: true
            ),
            new ToolProperty(
                name: 'section',
                type: PropertyType::STRING,
                description: 'Optional section ID to limit search scope',
                required: false
            ),
            new ToolProperty(
                name: 'max_results',
                type: PropertyType::INTEGER,
                description: 'Maximum number of results (default: 5)',
                required: false
            ),
        ];
    }
    
    public function __invoke(string $query, ?string $section = null, ?int $max_results = 5): string
    {
        if (empty(trim($query))) {
            return json_encode([
                'error' => 'Search query is required',
            ]);
        }
        
        $allowedSections = AgentContext::getAllowedSections();
        if ($allowedSections !== null && empty($allowedSections)) {
            return json_encode([
                'query' => $query,
                'results' => [],
                'message' => 'No document sections are enabled for this agent.',
            ]);
        }
        
        // Validate section if provided
        if ($section !== null && !DocumentSection::exists($section)) {
            $available = array_map(fn($s) => $s->id, DocumentSection::getAll());
            return json_encode([
                'error' => "Section not found: {$section}",
                'available_sections' => $available,
            ]);
        }
        
        if ($section !== null && $allowedSections !== null && !in_array($section, $allowedSections, true)) {
            return json_encode([
                'error' => "Section not enabled for this agent: {$section}",
                'available_sections' => $allowedSections,
            ]);
        }
        
        $limit = $max_results ?? 5;
        $results = [];
        
        if ($allowedSections !== null && $section === null) {
            foreach ($allowedSections as $allowedSection) {
                $sectionResults = DocumentRegistry::searchDocuments($query, $allowedSection, $limit);
                foreach ($sectionResults as $result) {
                    $results[] = $result;
                    if (count($results) >= $limit) {
                        break 2;
                    }
                }
            }
        } else {
            $results = DocumentRegistry::searchDocuments($query, $section, $limit);
        }
        
        if (empty($results)) {
            return json_encode([
                'query' => $query,
                'results' => [],
                'message' => 'No matches found. Try different keywords or use list_documents to browse.',
            ]);
        }
        
        return json_encode([
            'query' => $query,
            'section_filter' => $section,
            'result_count' => count($results),
            'results' => array_map(fn($r) => [
                'document' => $r['document_id'],
                'title' => $r['document_title'],
                'line' => $r['line'],
                'match' => $r['match'],
                'context' => $r['context'],
            ], $results),
            'hint' => 'Use read_document(document_id) to read the full document or read_document(document_id, line) to read from a specific line.',
        ]);
    }
}
