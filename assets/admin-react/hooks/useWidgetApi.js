/**
 * useWidgetApi Hook
 *
 * Enhanced React hook for Chat Widget API interactions.
 * Features: request deduplication, pagination, abort controllers, optimistic updates
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { getCached, setCache, invalidateCache } from './useApiCache';

const API_BASE = window.swcChatbot?.apiUrl || '/wp-json/quark-agentflow-ai/v1';

// Request deduplication map
const pendingRequests = new Map();

/**
 * Build headers for API requests
 */
const buildHeaders = (hasBody = false) => {
    const headers = {
        'X-WP-Nonce': window.swcChatbot?.nonce,
    };
    if (hasBody) {
        headers['Content-Type'] = 'application/json';
    }
    return headers;
};

/**
 * Safe JSON parsing
 */
const safeJson = async (response) => {
    try {
        return await response.json();
    } catch {
        return null;
    }
};

/**
 * Deduplicated fetch - prevents duplicate requests for same endpoint
 */
const deduplicatedFetch = async (key, fetchFn) => {
    // If request is already pending, return existing promise
    if (pendingRequests.has(key)) {
        return pendingRequests.get(key);
    }

    // Create new request promise
    const promise = fetchFn().finally(() => {
        pendingRequests.delete(key);
    });

    pendingRequests.set(key, promise);
    return promise;
};

/**
 * Main hook
 */
export default function useWidgetApi() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const abortControllerRef = useRef(null);
    const isMounted = useRef(true);

    // Cleanup on unmount
    useEffect(() => {
        isMounted.current = true;
        return () => {
            isMounted.current = false;
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, []);

    const safeSetState = useCallback((setter, value) => {
        if (isMounted.current) {
            setter(value);
        }
    }, []);

    /**
     * Fetch widgets with pagination support
     */
    const fetchWidgets = useCallback(async ({
        page = 1,
        perPage = 50,
        forceRefresh = false,
    } = {}) => {
        const cacheKey = `chat_widgets_${page}_${perPage}`;

        // Check cache first
        if (!forceRefresh) {
            const cached = getCached(cacheKey);
            if (cached) {
                return cached;
            }
        }

        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            // Create new abort controller
            abortControllerRef.current?.abort();
            abortControllerRef.current = new AbortController();

            const result = await deduplicatedFetch(cacheKey, async () => {
                const resp = await fetch(
                    `${API_BASE}/chat-widgets?page=${page}&per_page=${perPage}`,
                    {
                        headers: buildHeaders(),
                        signal: abortControllerRef.current.signal,
                    }
                );

                if (!resp.ok) {
                    throw new Error(`HTTP ${resp.status}`);
                }

                return safeJson(resp);
            });

            const widgets = result?.widgets || result?.data || [];
            const pagination = result?.pagination || {
                total: widgets.length,
                page,
                per_page: perPage,
                total_pages: 1,
            };

            const cacheData = { widgets, pagination };
            setCache(cacheKey, cacheData);

            return cacheData;

        } catch (err) {
            if (err.name === 'AbortError') {
                return { widgets: [], pagination: {} };
            }
            safeSetState(setError, err.message || 'Failed to fetch widgets');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    /**
     * Get single widget by ID
     */
    const getWidget = useCallback(async (id) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            const resp = await fetch(`${API_BASE}/chat-widgets/${id}`, {
                headers: buildHeaders(),
            });

            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}`);
            }

            const data = await safeJson(resp);
            return data?.widget || data?.data || data;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to fetch widget');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    /**
     * Create a new widget with optimistic cache invalidation
     */
    const createWidget = useCallback(async (payload) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            const resp = await fetch(`${API_BASE}/chat-widgets`, {
                method: 'POST',
                headers: buildHeaders(true),
                body: JSON.stringify(payload),
            });

            const data = await safeJson(resp);

            if (!resp.ok || data?.success === false) {
                throw new Error(data?.message || `HTTP ${resp.status}`);
            }

            // Invalidate all widget caches
            invalidateCache('chat_widgets');

            return data;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to create widget');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    /**
     * Update an existing widget
     */
    const updateWidget = useCallback(async (id, payload) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            const resp = await fetch(`${API_BASE}/chat-widgets/${id}`, {
                method: 'PUT',
                headers: buildHeaders(true),
                body: JSON.stringify(payload),
            });

            const data = await safeJson(resp);

            if (!resp.ok || data?.success === false) {
                throw new Error(data?.message || `HTTP ${resp.status}`);
            }

            invalidateCache('chat_widgets');

            return data;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to update widget');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    /**
     * Delete a widget
     */
    const deleteWidget = useCallback(async (id) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            const resp = await fetch(`${API_BASE}/chat-widgets/${id}`, {
                method: 'DELETE',
                headers: buildHeaders(),
            });

            const data = await safeJson(resp);

            if (!resp.ok || data?.success === false) {
                throw new Error(data?.message || `HTTP ${resp.status}`);
            }

            invalidateCache('chat_widgets');

            return data;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to delete widget');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    /**
     * Duplicate a widget
     */
    const duplicateWidget = useCallback(async (id, newName) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            // Fetch original widget
            const original = await getWidget(id);

            // Create copy with new name
            const copyName = newName || `${original.display_name || original.name} (Copy)`;
            const payload = {
                ...original,
                id: undefined,
                name: copyName,
                display_name: copyName,
            };

            return await createWidget(payload);

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to duplicate widget');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState, getWidget, createWidget]);

    /**
     * Fetch assignments for a widget
     */
    const fetchAssignments = useCallback(async (widgetId) => {
        if (!widgetId) return [];

        const cacheKey = `widget_assignments_${widgetId}`;
        const cached = getCached(cacheKey);
        if (cached) return cached;

        try {
            const resp = await fetch(`${API_BASE}/chat-widgets/${widgetId}/assignments`, {
                headers: buildHeaders(),
            });
            const data = await safeJson(resp);
            const assignments = data?.assignments || data?.data || [];
            setCache(cacheKey, assignments);
            return assignments;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to fetch assignments');
            throw err;
        }
    }, [safeSetState]);

    /**
     * Create an assignment
     */
    const createAssignment = useCallback(async (payload) => {
        if (!payload?.widget_id) return null;

        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            const resp = await fetch(`${API_BASE}/chat-widgets/${payload.widget_id}/assignments`, {
                method: 'POST',
                headers: buildHeaders(true),
                body: JSON.stringify(payload),
            });

            const data = await safeJson(resp);

            if (!resp.ok || data?.success === false) {
                throw new Error(data?.message || 'Failed to create assignment');
            }

            invalidateCache(`widget_assignments_${payload.widget_id}`);
            return data;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to create assignment');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    /**
     * Delete an assignment
     */
    const deleteAssignment = useCallback(async (id, widgetId) => {
        if (!id) return null;

        safeSetState(setLoading, true);
        safeSetState(setError, null);

        try {
            const resp = await fetch(`${API_BASE}/chat-widgets/assignments/${id}`, {
                method: 'DELETE',
                headers: buildHeaders(),
            });

            const data = await safeJson(resp);

            if (!resp.ok || data?.success === false) {
                throw new Error(data?.message || 'Failed to delete assignment');
            }

            if (widgetId) {
                invalidateCache(`widget_assignments_${widgetId}`);
            }
            return data;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to delete assignment');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    /**
     * Fetch teams (agent groups)
     */
    const fetchTeams = useCallback(async (forceRefresh = false) => {
        const cacheKey = 'agent_groups';

        if (!forceRefresh) {
            const cached = getCached(cacheKey);
            if (cached) return cached;
        }

        try {
            const resp = await fetch(`${API_BASE}/agent-groups`, {
                headers: buildHeaders(),
            });
            const data = await safeJson(resp);
            const teams = data?.groups || data?.data || [];
            setCache(cacheKey, teams);
            return teams;

        } catch (err) {
            safeSetState(setError, err.message || 'Failed to fetch teams');
            throw err;
        }
    }, [safeSetState]);

    return {
        // State
        loading,
        error,
        clearError: () => setError(null),

        // Widget CRUD
        fetchWidgets,
        getWidget,
        createWidget,
        updateWidget,
        deleteWidget,
        duplicateWidget,

        // Assignments
        fetchAssignments,
        createAssignment,
        deleteAssignment,

        // Teams
        fetchTeams,
    };
}
