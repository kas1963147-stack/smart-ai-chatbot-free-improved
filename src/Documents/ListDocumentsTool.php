<?php
declare(strict_types=1);


/**
 * List Documents Tool
 * 
 * Neuron AI tool that allows agents to discover available document sections
 * and their contents. This is the first step in the three-tier loading model.
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
 * Tool for listing document sections and files
 */
class ListDocumentsTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'list_documents',
            description: 'List available document sections and their files. Use to discover what knowledge is available before reading specific documents.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'section',
                type: PropertyType::STRING,
                description: 'Optional section ID to list documents from. If not provided, lists all sections and their documents.',
                required: false
            ),
        ];
    }
    
    public function __invoke(?string $section = null): string
    {
        $result = [];
        $allowedSections = AgentContext::getAllowedSections();
        
        if ($allowedSections !== null && empty($allowedSections)) {
            return json_encode([
                'sections' => [],
                'message' => 'No document sections are enabled for this agent.',
            ]);
        }
        
        if ($section !== null) {
            // List documents in specific section
            if ($allowedSections !== null && !in_array($section, $allowedSections, true)) {
                return json_encode([
                    'error' => "Section not enabled for this agent: {$section}",
                    'available_sections' => $allowedSections,
                ]);
            }
            
            $sectionObj = DocumentSection::get($section);
            
            if (!$sectionObj) {
                $available = array_map(fn($s) => $s->id, DocumentSection::getAll());
                return json_encode([
                    'error' => "Section not found: {$section}",
                    'available_sections' => $available,
                ]);
            }
            
            $documents = DocumentRegistry::getDocuments($section);
            
            $result = [
                'section' => [
                    'id' => $sectionObj->id,
                    'name' => $sectionObj->name,
                    'description' => $sectionObj->description,
                ],
                'documents' => array_map(fn($doc) => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'type' => $doc->type,
                    'lines' => $doc->lines,
                    'size_kb' => round($doc->size / 1024, 1),
                ], array_values($documents)),
            ];
        } else {
            // List all sections with document summaries
            $sections = DocumentSection::getAll();
            if ($allowedSections !== null) {
                $sections = array_filter($sections, fn($s) => in_array($s->id, $allowedSections, true));
            }
            
            $result['sections'] = [];
            
            foreach ($sections as $sectionObj) {
                $documents = DocumentRegistry::getDocuments($sectionObj->id);
                
                $result['sections'][] = [
                    'id' => $sectionObj->id,
                    'name' => $sectionObj->name,
                    'description' => $sectionObj->description,
                    'document_count' => count($documents),
                    'documents' => array_map(fn($doc) => [
                        'id' => $doc->id,
                        'title' => $doc->title,
                    ], array_values($documents)),
                ];
            }
            
            $result['hint'] = 'Use read_document(document_id) to read content or search_documents(query) to search.';
        }
        
        return json_encode($result);
    }
}
