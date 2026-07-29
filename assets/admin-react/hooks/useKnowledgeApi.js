/**
 * useKnowledgeApi Hook
 *
 * React hook for interacting with Knowledge Documents REST API.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { getCached, setCache, invalidateCache } from './useApiCache';

const API_BASE = window.swcChatbot?.apiUrl || '/wp-json/smart-ai-chatbot/v1';

const buildHeaders = (hasBody = false, isFormData = false) => {
    const headers = {
        'X-WP-Nonce': window.swcChatbot?.nonce,
    };
    if (hasBody && !isFormData) {
        headers['Content-Type'] = 'application/json';
    }
    return headers;
};

const safeJson = async (response) => {
    try {
        return await response.json();
    } catch (err) {
        return null;
    }
};

export default function useKnowledgeApi() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const isMounted = useRef(true);

    useEffect(() => {
        isMounted.current = true;
        return () => {
            isMounted.current = false;
        };
    }, []);

    const safeSetState = useCallback((setter, value) => {
        if (isMounted.current) {
            setter(value);
        }
    }, []);

    const fetchKnowledgeItems = useCallback(async (forceRefresh = false) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);

        const cached = !forceRefresh ? getCached('knowledge_items') : null;
        if (cached) {
            safeSetState(setLoading, false);
            return cached;
        }

        try {
            const resp = await fetch(`${API_BASE}/knowledge/documents`, {
                headers: buildHeaders(),
            });
            const data = await safeJson(resp);
            const items = data?.items || data?.data || [];
            setCache('knowledge_items', items);
            return items;
        } catch (err) {
            safeSetState(setError, err.message || 'Failed to fetch knowledge documents');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    const getKnowledgeItem = useCallback(async (id) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);
        try {
            const resp = await fetch(`${API_BASE}/knowledge/documents/${id}`, {
                headers: buildHeaders(),
            });
            const data = await safeJson(resp);
            return data?.item || data?.data || data;
        } catch (err) {
            safeSetState(setError, err.message || 'Failed to fetch knowledge document');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    const createKnowledgeItem = useCallback(async (payload) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);
        try {
            const isFormData = payload instanceof FormData;
            const resp = await fetch(`${API_BASE}/knowledge/documents`, {
                method: 'POST',
                headers: buildHeaders(true, isFormData),
                body: isFormData ? payload : JSON.stringify(payload),
            });
            const data = await safeJson(resp);
            invalidateCache('knowledge_items');
            invalidateCache('knowledge'); // Invalidate knowledge stats
            return data;
        } catch (err) {
            safeSetState(setError, err.message || 'Failed to create knowledge document');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    const updateKnowledgeItem = useCallback(async (id, payload) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);
        try {
            const isFormData = payload instanceof FormData;
            const resp = await fetch(`${API_BASE}/knowledge/documents/${id}`, {
                method: 'PUT',
                headers: buildHeaders(true, isFormData),
                body: isFormData ? payload : JSON.stringify(payload),
            });
            const data = await safeJson(resp);
            invalidateCache('knowledge_items');
            invalidateCache('knowledge'); // Invalidate knowledge stats
            return data;
        } catch (err) {
            safeSetState(setError, err.message || 'Failed to update knowledge document');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    const deleteKnowledgeItem = useCallback(async (id) => {
        safeSetState(setLoading, true);
        safeSetState(setError, null);
        try {
            const resp = await fetch(`${API_BASE}/knowledge/documents/${id}`, {
                method: 'DELETE',
                headers: buildHeaders(),
            });
            const data = await safeJson(resp);
            invalidateCache('knowledge_items');
            invalidateCache('knowledge'); // Invalidate knowledge stats
            return data;
        } catch (err) {
            safeSetState(setError, err.message || 'Failed to delete knowledge document');
            throw err;
        } finally {
            safeSetState(setLoading, false);
        }
    }, [safeSetState]);

    return {
        loading,
        error,
        fetchKnowledgeItems,
        getKnowledgeItem,
        createKnowledgeItem,
        updateKnowledgeItem,
        deleteKnowledgeItem,
    };
}
