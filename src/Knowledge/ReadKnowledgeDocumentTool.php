<?php
declare(strict_types=1);
/**
 * Read Knowledge Document Tool
 *
 * Read the full markdown content of a knowledge document by its title.
 *
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

if (!defined('ABSPATH')) {
    exit;
}

class ReadKnowledgeDocumentTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'read_knowledge',
            description: 'Read the full content of a company knowledge document. Use the exact title from the Available Knowledge Base list.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'title',
                type: PropertyType::STRING,
                description: 'The exact title of the knowledge document to read',
                required: true
            ),
        ];
    }

    public function __invoke(string $title): string
    {
        $doc = KnowledgeDocument::findByTitle($title);
        
        if (!$doc) {
            return json_encode([
                'error' => "Knowledge document not found: '{$title}'. Please check the Available Knowledge Base list for the exact title.",
            ]);
        }

        return json_encode([
            'title' => $doc->title,
            'source_url' => $doc->sourceUrl ?: null,
            'content' => $doc->content,
        ]);
    }
}
