<?php

/**
 * MCP Extension Interface
 *
 * Contract for plugin-specific tool extensions.
 * Extensions are auto-discovered and loaded when their plugin is active.
 */

namespace Quarksol\SmartChatbot\MCP\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

interface McpExtension
{
    /**
     * Check if the extension's plugin is active.
     * @return bool True if plugin is installed and active
     */
    public static function isActive(): bool;

    /**
     * Get category metadata for the extension.
     * @return array ['id' => string, 'label' => string, 'icon' => string]
     */
    public static function getCategory(): array;

    /**
     * Get tool definitions provided by this extension.
     * @return array List of tool definitions
     */
    public static function getTools(): array;

    /**
     * Execute a tool by name.
     * @param string $toolName The tool name
     * @param array $params Tool parameters
     * @return mixed Tool result
     */
    public static function executeTool(string $toolName, array $params): mixed;
}
