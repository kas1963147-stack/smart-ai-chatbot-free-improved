<?php
declare(strict_types=1);
/**
 * Hook Registry
 * 
 * Central registry for all plugin hooks with documentation and discovery.
 * Provides self-documenting hooks that can be queried via REST API.
 * 
 * @package Quarksol\SmartChatbot\Hooks
 */

namespace Quarksol\SmartChatbot\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook Registry Service
 * 
 * Registers and documents all plugin hooks for discoverability.
 */
class HookRegistry {
    
    /**
     * Registered hooks with documentation
     * @var array<string, array>
     */
    private static array $registeredHooks = [];
    
    /**
     * Whether tracing is enabled
     * @var bool
     */
    private static bool $tracingEnabled = false;
    
    /**
     * Traced hook executions
     * @var array
     */
    private static array $traces = [];
    
    /**
     * Register a hook with documentation
     * 
     * @param string $hook Hook name (e.g., 'swc/query/created')
     * @param array $config Hook configuration
     */
    public static function register(string $hook, array $config): void {
        self::$registeredHooks[$hook] = [
            'name' => $hook,
            'type' => $config['type'] ?? 'filter', // 'filter' or 'action'
            'description' => $config['description'] ?? '',
            'params' => $config['params'] ?? [],
            'returns' => $config['returns'] ?? 'mixed',
            'since' => $config['since'] ?? '1.0.0',
            'example' => $config['example'] ?? null,
            'domain' => self::extractDomain($hook),
        ];
    }
    
    /**
     * Get all registered hooks
     * 
     * @param string|null $domain Optional domain filter (e.g., 'query', 'agent')
     * @return array
     */
    public static function getAll(?string $domain = null): array {
        if ($domain === null) {
            return self::$registeredHooks;
        }
        
        return array_filter(self::$registeredHooks, function($hook) use ($domain) {
            return ($hook['domain'] ?? '') === $domain;
        });
    }
    
    /**
     * Get a specific hook's documentation
     * 
     * @param string $hook Hook name
     * @return array|null
     */
    public static function get(string $hook): ?array {
        return self::$registeredHooks[$hook] ?? null;
    }
    
    /**
     * Get all available domains
     * 
     * @return array
     */
    public static function getDomains(): array {
        $domains = [];
        foreach (self::$registeredHooks as $hook) {
            $domain = $hook['domain'] ?? 'other';
            if (!in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }
        sort($domains);
        return $domains;
    }
    
    /**
     * Check if a hook is registered
     * 
     * @param string $hook Hook name
     * @return bool
     */
    public static function exists(string $hook): bool {
        return isset(self::$registeredHooks[$hook]);
    }
    
    /**
     * Enable hook tracing for debugging
     */
    public static function enableTracing(): void {
        self::$tracingEnabled = true;
        self::$traces = [];
    }
    
    /**
     * Disable hook tracing
     */
    public static function disableTracing(): void {
        self::$tracingEnabled = false;
    }
    
    /**
     * Check if tracing is enabled
     * 
     * @return bool
     */
    public static function isTracingEnabled(): bool {
        return self::$tracingEnabled;
    }
    
    /**
     * Record a hook trace
     * 
     * @param string $hook Hook name
     * @param array $args Hook arguments
     * @param mixed $result Hook result (for filters)
     */
    public static function trace(string $hook, array $args = [], $result = null): void {
        if (!self::$tracingEnabled) {
            return;
        }
        
        self::$traces[] = [
            'hook' => $hook,
            'timestamp' => microtime(true),
            'args_count' => count($args),
            'has_result' => $result !== null,
            'memory' => memory_get_usage(true),
        ];
        
        // Also log if \WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SWC Hook] $hook");
        }
    }
    
    /**
     * Get all traces
     * 
     * @return array
     */
    public static function getTraces(): array {
        return self::$traces;
    }
    
    /**
     * Clear all traces
     */
    public static function clearTraces(): void {
        self::$traces = [];
    }
    
    /**
     * Extract domain from hook name
     * 
     * @param string $hook Hook name (e.g., 'swc/query/created')
     * @return string Domain (e.g., 'query')
     */
    private static function extractDomain(string $hook): string {
        $parts = explode('/', $hook);
        return $parts[1] ?? 'other';
    }
    
    /**
     * Get hook statistics
     * 
     * @return array
     */
    public static function getStats(): array {
        $byDomain = [];
        $byType = ['filter' => 0, 'action' => 0];
        
        foreach (self::$registeredHooks as $hook) {
            $domain = $hook['domain'] ?? 'other';
            $type = $hook['type'] ?? 'filter';
            
            $byDomain[$domain] = ($byDomain[$domain] ?? 0) + 1;
            $byType[$type]++;
        }
        
        return [
            'total' => count(self::$registeredHooks),
            'by_domain' => $byDomain,
            'by_type' => $byType,
            'domains' => self::getDomains(),
        ];
    }
}
