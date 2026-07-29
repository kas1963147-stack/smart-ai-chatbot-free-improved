/**
 * API Cache Manager
 * 
 * Provides caching for API responses to reduce load times
 * on repeated page visits. Uses sessionStorage for persistence
 * across page reloads within the same session.
 */

// Cache configuration
const CACHE_CONFIG = {
    skills: { ttl: 5 * 60 * 1000 }, // 5 minutes
    knowledge: { ttl: 5 * 60 * 1000 }, // 5 minutes
    agents: { ttl: 2 * 60 * 1000 }, // 2 minutes
    analytics: { ttl: 2 * 60 * 1000 }, // 2 minutes
    settings: { ttl: 10 * 60 * 1000 }, // 10 minutes
    tools: { ttl: 30 * 60 * 1000 }, // 30 minutes (rarely changes)
    tools_config: { ttl: 10 * 60 * 1000 }, // 10 minutes
    mcps: { ttl: 5 * 60 * 1000 }, // 5 minutes
    history: { ttl: 2 * 60 * 1000 }, // 2 minutes
    tasks: { ttl: 2 * 60 * 1000 }, // 2 minutes
    tasks_stats: { ttl: 2 * 60 * 1000 }, // 2 minutes
    schedule_options: { ttl: 30 * 60 * 1000 }, // 30 minutes (rarely changes)
    providers: { ttl: 30 * 60 * 1000 }, // 30 minutes (rarely changes)
    chat_widgets: { ttl: 2 * 60 * 1000 }, // 2 minutes
    knowledge_items: { ttl: 5 * 60 * 1000 }, // 5 minutes
    workflows: { ttl: 2 * 60 * 1000 }, // 2 minutes
};

// In-memory cache (faster than sessionStorage for frequent access)
const memoryCache = new Map();

/**
 * Get cache key
 */
function getCacheKey(endpoint, params = {}) {
    const paramStr = Object.keys(params).length
        ? '_' + JSON.stringify(params)
        : '';
    return `swc_api_cache_${endpoint}${paramStr}`;
}

/**
 * Get cached data
 */
export function getCached(endpoint, params = {}) {
    const key = getCacheKey(endpoint, params);

    // Check memory cache first (fastest)
    if (memoryCache.has(key)) {
        const cached = memoryCache.get(key);
        if (Date.now() < cached.expiry) {
            return cached.data;
        }
        memoryCache.delete(key);
    }

    // Check sessionStorage
    try {
        const stored = sessionStorage.getItem(key);
        if (stored) {
            const cached = JSON.parse(stored);
            if (Date.now() < cached.expiry) {
                // Restore to memory cache
                memoryCache.set(key, cached);
                return cached.data;
            }
            sessionStorage.removeItem(key);
        }
    } catch (e) {
        // SessionStorage not available or quota exceeded
    }

    return null;
}

/**
 * Set cached data
 */
export function setCache(endpoint, data, params = {}) {
    const key = getCacheKey(endpoint, params);
    const config = CACHE_CONFIG[endpoint] || { ttl: 2 * 60 * 1000 };

    const cacheEntry = {
        data,
        expiry: Date.now() + config.ttl,
        timestamp: Date.now(),
    };

    // Store in memory
    memoryCache.set(key, cacheEntry);

    // Store in sessionStorage for persistence
    try {
        sessionStorage.setItem(key, JSON.stringify(cacheEntry));
    } catch (e) {
        // Quota exceeded or not available - memory cache still works
    }
}

/**
 * Invalidate cache for an endpoint
 */
export function invalidateCache(endpoint) {
    // Clear from memory
    for (const key of memoryCache.keys()) {
        if (key.includes(endpoint)) {
            memoryCache.delete(key);
        }
    }

    // Clear from sessionStorage
    try {
        for (let i = sessionStorage.length - 1; i >= 0; i--) {
            const key = sessionStorage.key(i);
            if (key && key.includes(endpoint)) {
                sessionStorage.removeItem(key);
            }
        }
    } catch (e) {
        // Ignore
    }
}

/**
 * Clear all cache
 */
export function clearAllCache() {
    memoryCache.clear();
    try {
        for (let i = sessionStorage.length - 1; i >= 0; i--) {
            const key = sessionStorage.key(i);
            if (key && key.startsWith('swc_api_cache_')) {
                sessionStorage.removeItem(key);
            }
        }
    } catch (e) {
        // Ignore
    }
}

/**
 * Prefetch data for common endpoints
 * Call this on app initialization
 */
export async function prefetchCommonData(apiFetch) {
    const API_BASE = '/smart-ai-chatbot/v1';

    // Prefetch all major endpoints in background without blocking
    const prefetchEndpoints = [
        { endpoint: 'skills', path: `${API_BASE}/skills` },
        { endpoint: 'knowledge', path: `${API_BASE}/knowledge/sources` },
        { endpoint: 'tools', path: `${API_BASE}/tools/registry` },
        { endpoint: 'mcps', path: `${API_BASE}/mcps` },
        { endpoint: 'settings', path: `${API_BASE}/settings` },
    ];

    // Prefetch in parallel for speed
    await Promise.allSettled(
        prefetchEndpoints.map(async ({ endpoint, path }) => {
            // Only prefetch if not already cached
            if (!getCached(endpoint)) {
                try {
                    const response = await apiFetch({ path });
                    if (response.success !== false) {
                        // Handle different response formats
                        setCache(endpoint, response.data || response);
                    }
                } catch (e) {
                    // Prefetch failed - not critical
                }
            }
        })
    );
}

export default {
    getCached,
    setCache,
    invalidateCache,
    clearAllCache,
    prefetchCommonData,
};
