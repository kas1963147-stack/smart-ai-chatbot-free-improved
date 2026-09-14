/**
 * useChatsApi Hook
 *
 * React hook for interacting with Chat Widgets REST API.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { getCached, setCache, invalidateCache } from './useApiCache';

const API_BASE = window.swcChatbot?.apiUrl || '/wp-json/quark-agentflow-ai/v1';

const buildHeaders = (hasBody = false) => {
	const headers = {
		'X-WP-Nonce': window.swcChatbot?.nonce,
	};
	if (hasBody) {
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

export default function useChatsApi() {
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

	const fetchWidgets = useCallback(async (forceRefresh = false) => {
		safeSetState(setLoading, true);
		safeSetState(setError, null);

		const cached = !forceRefresh ? getCached('chat_widgets') : null;
		if (cached) {
			safeSetState(setLoading, false);
			return cached;
		}

		try {
			const resp = await fetch(`${API_BASE}/chat-widgets`, {
				headers: buildHeaders(),
			});
			const data = await safeJson(resp);
			const widgets = data?.widgets || data?.data || [];
			setCache('chat_widgets', widgets);
			return widgets;
		} catch (err) {
			safeSetState(setError, err.message || 'Failed to fetch widgets');
			throw err;
		} finally {
			safeSetState(setLoading, false);
		}
	}, [safeSetState]);

	const getWidget = useCallback(async (id) => {
		safeSetState(setLoading, true);
		safeSetState(setError, null);
		try {
			const resp = await fetch(`${API_BASE}/chat-widgets/${id}`, {
				headers: buildHeaders(),
			});
			const data = await safeJson(resp);
			return data?.widget || data?.data || data;
		} catch (err) {
			safeSetState(setError, err.message || 'Failed to fetch widget');
			throw err;
		} finally {
			safeSetState(setLoading, false);
		}
	}, [safeSetState]);

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
			invalidateCache('chat_widgets');
			return data;
		} catch (err) {
			safeSetState(setError, err.message || 'Failed to create widget');
			throw err;
		} finally {
			safeSetState(setLoading, false);
		}
	}, [safeSetState]);

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
			invalidateCache('chat_widgets');
			return data;
		} catch (err) {
			safeSetState(setError, err.message || 'Failed to update widget');
			throw err;
		} finally {
			safeSetState(setLoading, false);
		}
	}, [safeSetState]);

	const deleteWidget = useCallback(async (id) => {
		safeSetState(setLoading, true);
		safeSetState(setError, null);
		try {
			const resp = await fetch(`${API_BASE}/chat-widgets/${id}`, {
				method: 'DELETE',
				headers: buildHeaders(),
			});
			const data = await safeJson(resp);
			invalidateCache('chat_widgets');
			return data;
		} catch (err) {
			safeSetState(setError, err.message || 'Failed to delete widget');
			throw err;
		} finally {
			safeSetState(setLoading, false);
		}
	}, [safeSetState]);

	const fetchAssignments = useCallback(async (widgetId) => {
		if (!widgetId) return [];

		const cacheKey = `chat_widget_assignments_${widgetId}`;
		const cached = getCached(cacheKey);
		if (cached) {
			return cached;
		}

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
			invalidateCache(`chat_widget_assignments_${payload.widget_id}`);
			return data;
		} catch (err) {
			safeSetState(setError, err.message || 'Failed to create assignment');
			throw err;
		} finally {
			safeSetState(setLoading, false);
		}
	}, [safeSetState]);

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
			if (widgetId) invalidateCache(`chat_widget_assignments_${widgetId}`);
			return data;
		} catch (err) {
			safeSetState(setError, err.message || 'Failed to delete assignment');
			throw err;
		} finally {
			safeSetState(setLoading, false);
		}
	}, [safeSetState]);

	return {
		loading,
		error,
		fetchWidgets,
		getWidget,
		createWidget,
		updateWidget,
		deleteWidget,
		fetchAssignments,
		createAssignment,
		deleteAssignment,
	};
}
