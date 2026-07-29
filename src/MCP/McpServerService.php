<?php

/**
 * MCP Server Service
 * 
 * Manages MCP server settings including token generation, enabling/disabling,
 * and connection URL generation.
 * 
 * @package Quarksol\SmartChatbot\MCP
 */

namespace Quarksol\SmartChatbot\MCP;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MCP Server Service
 * 
 * Handles:
 * - Server enable/disable state
 * - Token generation and management
 * - Connection URLs
 * - Settings persistence
 */
class McpServerService
{
    private const OPTION_PREFIX = 'swc_mcp_server_';
    private const TOKEN_PREFIX = 'sw_mcp_';

    /**
     * Check if MCP server is enabled
     */
    public static function isEnabled(): bool
    {
        return (bool) get_option(self::OPTION_PREFIX . 'enabled', true);
    }

    /**
     * Enable or disable the MCP server
     */
    public static function setEnabled(bool $enabled): bool
    {
        return update_option(self::OPTION_PREFIX . 'enabled', $enabled);
    }

    /**
     * Get the current bearer token
     */
    public static function getToken(): ?string
    {
        return get_option(self::OPTION_PREFIX . 'token', null);
    }

    /**
     * Generate a new token
     */
    public static function generateToken(): string
    {
        $token = self::TOKEN_PREFIX . wp_generate_password(32, false, false);
        update_option(self::OPTION_PREFIX . 'token', $token);
        return $token;
    }

    /**
     * Validate a token
     */
    public static function validateToken(string $token): bool
    {
        $storedToken = self::getToken();
        return $storedToken && hash_equals($storedToken, $token);
    }

    /**
     * Get the site URL for MCP endpoints
     */
    public static function getSiteUrl(): string
    {
        return rest_url();
    }

    /**
     * Get the SSE endpoint URL
     */
    public static function getSseUrl(): string
    {
        return rest_url('mcp/v1/sse');
    }

    /**
     * Get the messages endpoint URL
     */
    public static function getMessagesUrl(): string
    {
        return rest_url('mcp/v1/messages');
    }

    /**
     * Get the no-auth URL (token embedded in path)
     */
    public static function getNoAuthUrl(): string
    {
        $token = self::getToken();
        if (!$token) {
            return '';
        }
        return rest_url('mcp/v1/' . $token . '/sse');
    }

    /**
     * Get Claude Desktop configuration JSON
     */
    public static function getClaudeConfig(): array
    {
        $siteName = sanitize_title(get_bloginfo('name')) ?: 'wordpress';
        $token = self::getToken();

        return [
            'mcpServers' => [
                $siteName => [
                    'type' => 'sse',
                    'url' => self::getSseUrl(),
                    'headers' => [
                        'Authorization' => 'Bearer ' . ($token ?: 'YOUR_TOKEN_HERE'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Get all server configuration for admin UI
     */
    public static function getServerConfig(): array
    {
        $token = self::getToken();

        return [
            'enabled' => self::isEnabled(),
            'token' => $token,
            'has_token' => !empty($token),
            'endpoints' => [
                'sse' => self::getSseUrl(),
                'messages' => self::getMessagesUrl(),
                'no_auth' => self::getNoAuthUrl(),
            ],
            'claude_config' => self::getClaudeConfig(),
            'tools' => McpToolRegistry::getStats(),
        ];
    }

    /**
     * Get internal access status for Quarksol agents
     */
    public static function allowInternalAgents(): bool
    {
        return (bool) get_option(self::OPTION_PREFIX . 'allow_internal', true);
    }

    /**
     * Set internal access for Quarksol agents
     */
    public static function setAllowInternalAgents(bool $allow): bool
    {
        return update_option(self::OPTION_PREFIX . 'allow_internal', $allow);
    }

    /**
     * Get the protocol version
     */
    public static function getProtocolVersion(): string
    {
        return '2025-06-18';
    }

    /**
     * Get server capabilities
     */
    public static function getCapabilities(): array
    {
        return [
            'tools' => new \stdClass(),
        ];
    }

    /**
     * Get server metadata for `initialize` response
     */
    public static function getServerInfo(): array
    {
        return [
            'name' => get_bloginfo('name') . ' MCP Server',
            'version' => '1.0.0',
        ];
    }
}
