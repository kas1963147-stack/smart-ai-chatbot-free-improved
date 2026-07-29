<?php
declare(strict_types=1);
/**
 * Knowledge Augmenter
 * 
 * Automatically injects relevant knowledge context into agent prompts.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Augmenter
 */
class KnowledgeAugmenter {
    
    /** @var RAGRetriever */
    protected RAGRetriever $retriever;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->retriever = new RAGRetriever();
    }
    
    /**
     * Augment system prompt with relevant knowledge
     */
    public function augment(string $userMessage, string $systemPrompt): string {
        if (!KnowledgeConfig::isAutoAugmentEnabled()) {
            return $systemPrompt;
        }
        
        if (!$this->retriever->isAvailable()) {
            return $systemPrompt;
        }
        
        $context = $this->retriever->getContext($userMessage, 3);
        
        if (empty($context)) {
            return $systemPrompt;
        }
        
        return $systemPrompt . "\n\n" . $this->formatContext($context);
    }
    
    /**
     * Format context for prompt
     */
    protected function formatContext(string $context): string {
        return "<KNOWLEDGE_CONTEXT>
The following information from the company knowledge base may be relevant to the user's question. 
Use this information to provide accurate answers. If the information doesn't apply, you can ignore it.

{$context}
</KNOWLEDGE_CONTEXT>
";
    }
    
    /**
     * Get augmented prompt with explicit context
     */
    public function getAugmentedPrompt(string $userMessage, string $systemPrompt, int $limit = 3): array {
        $context = $this->retriever->getContextArray($userMessage, $limit);
        
        $augmentedPrompt = $systemPrompt;
        
        if (!empty($context)) {
            $contextStr = $this->retriever->getContext($userMessage, $limit);
            $augmentedPrompt = $this->augment($userMessage, $systemPrompt);
        }
        
        return [
            'prompt' => $augmentedPrompt,
            'sources' => $context,
            'augmented' => !empty($context),
        ];
    }
    
    /**
     * Check if augmentation is available
     */
    public function isAvailable(): bool {
        return KnowledgeConfig::isAutoAugmentEnabled() && $this->retriever->isAvailable();
    }
}
