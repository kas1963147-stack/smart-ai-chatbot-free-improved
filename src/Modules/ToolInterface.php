<?php
declare(strict_types=1);


/**
 * Tool Interface
 * 
 * Contract for all tools that can be used by the AI agent.
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for AI-callable tools
 */
interface ToolInterface {
    
    /**
     * Get unique tool identifier
     */
    public function getName(): string;
    
    /**
     * Get tool description for AI
     */
    public function getDescription(): string;
    
    /**
     * Get JSON schema for tool parameters
     */
    public function getParameters(): array;
    
    /**
     * Execute the tool with given arguments
     */
    public function execute(array $arguments): array;
    
    /**
     * Get AI prompt describing how to use this tool
     */
    public function getPrompt(): string;
}
