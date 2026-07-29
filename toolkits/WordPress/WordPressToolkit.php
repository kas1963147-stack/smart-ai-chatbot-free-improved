<?php

/**
 * WordPress Core Toolkit - Free Version
 * 
 * Essential WordPress admin toolkit!
 * 
 * @package Toolkits\WordPress
 */

namespace Toolkits\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Toolkits\ToolkitInterface;

/**
 * WordPress Core Toolkit
 */
class WordPressToolkit implements ToolkitInterface
{
    /**
     * Toolkit guidelines
     */
    public function guidelines(): ?string
    {
        return "Use these tools to manage WordPress core functionality.";
    }

    /**
     * Get all tools in this toolkit
     */
    public function tools(): array
    {
        return [
            new SearchTool(),        // Global content search
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
     * Get tool categories
     */
    public static function categories(): array
    {
        return [
            'infrastructure' => [
                'name' => 'Infrastructure',
                'icon' => '🔧',
                'tools' => ['wp_search']
            ]
        ];
    }
    
    /**
     * Get toolkit info
     */
    public static function info(): array
    {
        return [
            'name' => 'WordPress Core Essential Toolkit',
            'version' => '2.0.0',
            'tool_count' => 1,
            'description' => 'Essential WordPress admin tools for free AI assistants',
            'capabilities' => [
                'Search' => 'Global content search'
            ]
        ];
    }
}
