<?php
declare(strict_types=1);


/**
 * System Tool Registry
 * 
 * Registry for core, non-removable tools that are fundamental to agent operation.
 * These tools cannot be disabled by admin configuration.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Skills\LoadSkillTool;
use Quarksol\SmartChatbot\Skills\LoadSkillReferenceTool;
use Quarksol\SmartChatbot\Documents\ListDocumentsTool;
use Quarksol\SmartChatbot\Documents\ReadDocumentTool;
use Quarksol\SmartChatbot\Documents\SearchDocumentsTool;
use Quarksol\SmartChatbot\Knowledge\ReadKnowledgeDocumentTool;
use Quarksol\SmartChatbot\Knowledge\FreeSearchProductsTool;
use Quarksol\SmartChatbot\Knowledge\FreeSearchPostsTool;
use Quarksol\SmartChatbot\Knowledge\FreeReadPostTool;

/**
 * System Tool Registry
 * 
 * Manages non-removable system tools that form the core of agent capabilities.
 * Unlike configurable tools (WooCommerce, WordPress), these cannot be disabled.
 */
class SystemToolRegistry
{
    /** Tool category: Skill loading */
    const CATEGORY_SKILLS = 'skills';
    
    /** Tool category: Document access */
    const CATEGORY_DOCUMENTS = 'documents';

    /** Tool category: Knowledge base access */
    const CATEGORY_KNOWLEDGE = 'knowledge';
    
    /** Tool category: Memory management (future) */
    const CATEGORY_MEMORY = 'memory';
    
    /** Tool category: Reasoning/thinking (future) */
    const CATEGORY_REASONING = 'reasoning';
    
    /** Cached system tools */
    protected static ?array $tools = null;
    
    /**
     * Get all system tools
     * 
     * These are always present and cannot be removed by configuration.
     * 
     * @return array Array of Tool instances
     */
    public static function getSystemTools(): array
    {
        if (self::$tools !== null) {
            return self::$tools;
        }
        
        self::$tools = [
            // Skills category - on-demand skill loading
            new LoadSkillTool(),
            new LoadSkillReferenceTool(),
            
            // Documents category - knowledge base access
            new ListDocumentsTool(),
            new ReadDocumentTool(),
            new SearchDocumentsTool(),

            // Knowledge category - reading documents
            new ReadKnowledgeDocumentTool(),

            // Free-tier tools - always available, hidden from admin UI
            new FreeSearchProductsTool(),
            new FreeSearchPostsTool(),
            new FreeReadPostTool(),
            
            // Future: Memory category
            // new StoreMemoryTool(),
            // new RecallMemoryTool(),
            
            // Future: Reasoning category
            // new ThinkTool(),
        ];
        
        return self::$tools;
    }
    
    /**
     * Check if a tool name is a system tool
     */
    public static function isSystemTool(string $toolName): bool
    {
        $systemToolNames = self::getSystemToolNames();
        return in_array($toolName, $systemToolNames, true);
    }
    
    /**
     * Get system tool names
     */
    public static function getSystemToolNames(): array
    {
        return array_map(
            fn($tool) => $tool->getName(),
            self::getSystemTools()
        );
    }
    
    /**
     * Get system tool metadata for documentation
     */
    public static function getSystemToolInfo(): array
    {
        return [
            self::CATEGORY_SKILLS => [
                'name' => 'Skills',
                'icon' => '',
                'description' => 'On-demand skill loading for specialized knowledge',
                'tools' => [
                    'load_skill' => [
                        'name' => 'Load Skill',
                        'description' => 'Load detailed instructions for a skill when needed',
                        'removable' => false,
                    ],
                    'load_skill_reference' => [
                        'name' => 'Load Skill Reference',
                        'description' => 'Load reference documents from a skill',
                        'removable' => false,
                    ],
                ],
            ],
            self::CATEGORY_KNOWLEDGE => [
                'name' => 'Knowledge',
                'icon' => '️',
                'description' => 'Internal knowledge search and reading',
                'tools' => [
                    'read_knowledge' => [
                        'name' => 'Read Knowledge Document',
                        'description' => 'Read the full content of a company knowledge document',
                        'removable' => false,
                    ],
                ],
            ],
            self::CATEGORY_MEMORY => [
                'name' => 'Memory',
                'icon' => '',
                'description' => 'Conversation memory and context (coming soon)',
                'tools' => [],
            ],
            self::CATEGORY_REASONING => [
                'name' => 'Reasoning',
                'icon' => '',
                'description' => 'Step-by-step thinking (coming soon)',
                'tools' => [],
            ],
        ];
    }
    
    /**
     * Get guidelines for all system tools
     * 
     * Returns instructions for the agent on how to use system tools.
     */
    public static function getGuidelines(): string
    {
        $guidelines = [
            "## System Tools",
            "",
            "These tools are always available to help you perform your tasks:",
            "",
        ];
        
        // Add skill tool guidelines
        $guidelines[] = "### Skill Loading";
        $guidelines[] = "";
        $guidelines[] = "Use `load_skill` when a user's request matches a skill from the Available Skills list.";
        $guidelines[] = "**ALWAYS** load the skill BEFORE performing specialized tasks - the skill contains the specific instructions you need.";
        $guidelines[] = "";
        $guidelines[] = "Example:";
        $guidelines[] = "- User: \"I want to return my order\" → Call `load_skill(\"refund-handling\")`";
        $guidelines[] = "- User: \"Help me find products\" → Call `load_skill(\"shopping-assistant\")`";
        $guidelines[] = "";
        $guidelines[] = "After loading a skill, follow its instructions. ONLY use `load_skill_reference` to load policies or procedures IF the `load_skill` response explicitly provides an `available_references` list.";
        $guidelines[] = "";
        $guidelines[] = "⚡ **CRITICAL PROFESSIONALISM RULE** ⚡";
        $guidelines[] = "NEVER narrate your internal thought process to the user. Do not say \"Let me load the skill\", \"I am loading the instructions\", or \"I will find out the options\".";
        $guidelines[] = "Instead, seamlessly call the `load_skill` tool and immediately respond with the outcome or by directly asking the essential questions in a mature, professional manner.";
        $guidelines[] = "";
        $guidelines[] = "### Knowledge Base";
        $guidelines[] = "";
        $guidelines[] = "The **Available Knowledge Base** section optionally lists documents you can access. **You do NOT have this content memorized** — you MUST use tools to retrieve it.";
        $guidelines[] = "";
        $guidelines[] = "- Use `read_knowledge(title)` to load the full markdown content from a specific knowledge document — use this when a user's question clearly matches a listed document title.";
        $guidelines[] = "";
        $guidelines[] = "**IMPORTANT**: When a user asks about a topic that matches an Available Knowledge Document, ALWAYS call `read_knowledge` first. Do NOT guess or hallucinate answers.";
        
        return implode("\n", $guidelines);
    }
    
    /**
     * Get summary for admin display
     */
    public static function getSummary(): array
    {
        $tools = self::getSystemTools();
        
        return [
            'total' => count($tools),
            'categories' => self::getSystemToolInfo(),
            'tools' => array_map(fn($t) => [
                'name' => $t->getName(),
                'description' => $t->getDescription(),
                'removable' => false,
            ], $tools),
        ];
    }
    
    /**
     * Refresh cached tools
     */
    public static function refresh(): void
    {
        self::$tools = null;
    }
}
