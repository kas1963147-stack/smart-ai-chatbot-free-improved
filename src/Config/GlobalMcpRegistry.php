<?php
declare(strict_types=1);



namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global MCP Registry
 * 
 * Manages user-defined MCP servers stored in the database.
 * These are "Global" MCPs that can be reused across multiple agents.
 * 
 * @package Quarksol\SmartChatbot\Config
 */
class GlobalMcpRegistry
{
    /** Option key for storing user MCPs */
    const OPTION_KEY = 'swc_global_mcps';

    /**
     * Get all user-defined MCPs
     * 
     * @return array Array of MCP configurations
     */
    public static function getUserMcps(): array
    {
        return get_option(self::OPTION_KEY, []);
    }

    /**
     * Get a specific user MCP by ID
     */
    public static function get(string $mcpId): ?array
    {
        $mcps = self::getUserMcps();
        return $mcps[$mcpId] ?? null;
    }

    /**
     * Save a user-defined MCP (Create or Update)
     * 
     * @param string $mcpId Unique ID for the MCP
     * @param array $config Configuration array
     * @return bool Success
     */
    public static function save(string $mcpId, array $config): bool
    {
        $mcps = self::getUserMcps();
        
        // Ensure ID matches
        $config['id'] = $mcpId;
        
        // Add timestamps
        if (!isset($mcps[$mcpId])) {
            $config['created_at'] = time();
        } else {
            $config['created_at'] = $mcps[$mcpId]['created_at'] ?? time();
        }
        $config['updated_at'] = time();
        
        $mcps[$mcpId] = $config;
        
        return update_option(self::OPTION_KEY, $mcps);
    }

    /**
     * Delete a user-defined MCP
     */
    public static function delete(string $mcpId): bool
    {
        $mcps = self::getUserMcps();
        
        if (!isset($mcps[$mcpId])) {
            return false;
        }
        
        unset($mcps[$mcpId]);
        return update_option(self::OPTION_KEY, $mcps);
    }

    /**
     * Get all available MCPs (System Examples + User Defined)
     * 
     * Only includes system MCPs from the current registry plus
     * genuinely custom user-created MCPs (category = 'custom').
     * Stale database entries from removed system MCPs are cleaned up.
     */
    public static function getAll(): array
    {
        $system = McpRegistry::getExamples();
        $user = self::getUserMcps();
        
        // Run one-time cleanup of stale database entries per request
        static $cleaned = false;
        if (!$cleaned && !empty($user)) {
            $cleaned = true;
            self::cleanupStaleEntries($system);
            // Re-read after cleanup
            $user = self::getUserMcps();
        }
        
        // Build the final result:
        // 1. System MCPs as base (fresh URLs, descriptions from McpRegistry)
        // 2. Overlay user-saved config (API keys, tokens) for system MCPs
        // 3. Add truly custom user-created MCPs
        $result = $system;
        
        foreach ($user as $id => $mcp) {
            if (isset($system[$id])) {
                // System MCP: merge user's API credentials into the system template
                // This preserves the official URL while keeping user's API key/token
                $userConfig = $mcp['default_config'] ?? [];
                $sysConfig = $system[$id]['default_config'] ?? [];
                
                // Only merge credentials, always keep the system URL
                $mergedConfig = $sysConfig;
                if (!empty($userConfig['api_key'])) {
                    $mergedConfig['api_key'] = $userConfig['api_key'];
                }
                if (!empty($userConfig['token'])) {
                    $mergedConfig['token'] = $userConfig['token'];
                }
                
                $result[$id] = array_merge($system[$id], [
                    'default_config' => $mergedConfig,
                ]);
            } elseif (($mcp['category'] ?? '') === 'custom') {
                // Genuinely custom user-created MCP
                $result[$id] = $mcp;
            }
        }
        
        return $result;
    }

    /**
     * Remove stale database entries that no longer exist in the system registry
     * 
     * This handles two cases:
     * 1. MCPs that were completely removed from McpRegistry
     * 2. System MCPs that were saved to DB with old URLs (shadows/overrides)
     *    These are removed so the fresh system templates show instead.
     */
    private static function cleanupStaleEntries(array $currentSystem): void
    {
        $user = self::getUserMcps();
        $changed = false;
        
        // IDs of MCPs that were removed from the registry
        $removedIds = [
            'gmail', 'google-calendar', 'google-drive', 'jira', 
            'trello', 'asana', 'airtable', 'postgresql', 
            '1mcpserver', 'deepwiki',
        ];
        
        foreach ($removedIds as $staleId) {
            if (isset($user[$staleId])) {
                unset($user[$staleId]);
                $changed = true;
            }
        }
        
        // Also remove database copies of system MCPs that were saved with old URLs
        // This ensures the fresh system templates with official URLs are used
        foreach ($currentSystem as $sysId => $sysMcp) {
            if (isset($user[$sysId])) {
                $userUrl = $user[$sysId]['default_config']['url'] ?? '';
                $sysUrl = $sysMcp['default_config']['url'] ?? '';
                
                // If the user-saved URL still uses old @anthropics Smithery URLs
                // or doesn't match the current system URL, remove the stale entry
                if (
                    str_contains($userUrl, '@anthropics/') ||
                    ($userUrl !== $sysUrl && $userUrl !== '' && !empty($sysUrl))
                ) {
                    unset($user[$sysId]);
                    $changed = true;
                }
            }
        }
        
        if ($changed) {
            update_option(self::OPTION_KEY, $user);
            // Clear the MCP list cache
            delete_transient('swc_mcps_list_v2');
        }
    }
}

