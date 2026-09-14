<?php

/**
 * WordPress Content Toolkit - Free Version
 * 
 * Essential toolkit for WordPress content reading.
 * 
 * @package Toolkits\WordPressContent
 */

namespace Quarksol\AgentFlowAI\Toolkits\WordPressContent;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Toolkits\ToolkitInterface;

/**
 * WordPress Content Toolkit - Essential Edition
 */
class WordPressContentToolkit implements ToolkitInterface
{
    /**
     * Toolkit guidelines
     */
    public function guidelines(): ?string
    {
        return "Use this tool to read WordPress content including posts and pages.";
    }

    /**
     * Get all tools in this toolkit
     */
    public function tools(): array
    {
        return [
            // Content Read Only
            new PostReadTool(),
        ];
    }

    /**
     * Exclude tools by class
     */
    public function exclude(array $classes): ToolkitInterface
    {
        return $this;
    }

    /**
     * Only include specific tools by class
     */
    public function only(array $classes): ToolkitInterface
    {
        return $this;
    }

    /**
     * Modify a specific tool with a callback
     */
    public function with(string $class, callable $callback): ToolkitInterface
    {
        return $this;
    }
    
    /**
     * Get tools count
     */
    public static function count(): int
    {
        return count(self::tools());
    }
    
    /**
     * Get toolkit info
     */
    public static function info(): array
    {
        return [
            'name' => 'WordPress Content Essential Toolkit',
            'version' => '1.0.0',
            'tools' => self::count(),
            'description' => 'Essential WordPress content reading for AI agents'
        ];
    }
}
