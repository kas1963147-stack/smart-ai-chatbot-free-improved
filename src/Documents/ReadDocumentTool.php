<?php
declare(strict_types=1);


/**
 * Read Document Tool
 * 
 * Neuron AI tool for reading document content. Supports full or partial
 * reading with line ranges, similar to how coding agents explore files.
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
 * Tool for reading document content with line-range support
 */
class ReadDocumentTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'read_document',
            description: 'Read content from a document. Can read the full document or specific line ranges for large files. Use this after discovering documents with list_documents.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'document_id',
                type: PropertyType::STRING,
                description: 'Document ID to read (e.g., "policies/return-policy")',
                required: true
            ),
            new ToolProperty(
                name: 'start_line',
                type: PropertyType::INTEGER,
                description: 'Optional start line (1-indexed). Omit to start from beginning.',
                required: false
            ),
            new ToolProperty(
                name: 'line_count',
                type: PropertyType::INTEGER,
                description: 'Optional number of lines to read. Omit to read to end. Recommended: 50-100 for large files.',
                required: false
            ),
        ];
    }
    
    public function __invoke(string $document_id, ?int $start_line = null, ?int $line_count = null): string
    {
        $doc = DocumentRegistry::loadDocument($document_id);
        $allowedSections = AgentContext::getAllowedSections();
        
        if (!$doc) {
            // List available documents if not found
            $available = array_map(fn($d) => $d->id, array_values(DocumentRegistry::discover()));
            return json_encode([
                'error' => "Document not found: {$document_id}",
                'available_documents' => array_slice($available, 0, 10),
                'hint' => 'Use list_documents() to see all available documents.',
            ]);
        }

        if ($allowedSections !== null && !in_array($doc->sectionId, $allowedSections, true)) {
            return json_encode([
                'error' => "Document not enabled for this agent: {$doc->id}",
                'available_sections' => $allowedSections,
            ]);
        }
        
        // Determine reading mode
        if ($start_line !== null || $line_count !== null) {
            // Partial read
            $start = $start_line ?? 1;
            $result = $doc->getLines($start, $line_count);
            
            return json_encode([
                'document' => $doc->id,
                'title' => $doc->title,
                'content' => $result['content'],
                'showing' => "lines {$result['start']}-{$result['end']} of {$result['total']}",
                'total_lines' => $result['total'],
                'hint' => $result['end'] < $result['total'] 
                    ? "More content available. Use start_line=" . ($result['end'] + 1) . " to continue."
                    : null,
            ]);
        } else {
            // Full read - but warn if large
            $content = $doc->getContent();
            
            $response = [
                'document' => $doc->id,
                'title' => $doc->title,
                'content' => $content,
                'total_lines' => $doc->lines,
            ];
            
            if ($doc->lines > 100) {
                $response['warning'] = "Large document ({$doc->lines} lines). Consider using start_line and line_count for chunked reading.";
            }
            
            return json_encode($response);
        }
    }
}
