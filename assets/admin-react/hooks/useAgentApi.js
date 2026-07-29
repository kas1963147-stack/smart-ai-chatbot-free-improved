/**
 * useAgentApi Hook
 *
 * React hook for interacting with the Agent REST API.
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const API_BASE = '/smart-ai-chatbot/v1';

export default function useAgentApi() {
	const [agents, setAgents] = useState([]);
	const [toolkits, setToolkits] = useState({});
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	// Mounted ref for safe state updates
	const isMounted = useRef(true);

	useEffect(() => {
		isMounted.current = true;
		return () => {
			isMounted.current = false;
		};
	}, []);

	/**
	 * Safe state setter
	 */
	const safeSetState = useCallback((setter, value) => {
		if (isMounted.current) {
			setter(value);
		}
	}, []);

	/**
	 * Fetch all agents
	 */
	const fetchAgents = useCallback(
		async (includeInactive = false) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				const params = includeInactive ? `?include_inactive=true&_t=${Date.now()}` : `?_t=${Date.now()}`;
				const response = await apiFetch({
					path: `${API_BASE}/agents${params}`,
				});
				safeSetState(setAgents, response.agents || []);
				return response.agents;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to fetch agents'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Fetch available toolkits
	 */
	const fetchToolkits = useCallback(async () => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/toolkits`,
			});
			safeSetState(setToolkits, response || {});
			return response;
		} catch (err) {
			console.error('Failed to fetch toolkits:', err);
			throw err;
		}
	}, [safeSetState]);

	/**
	 * Get single agent
	 */
	const getAgent = useCallback(
		async (id) => {
			safeSetState(setLoading, true);
			try {
				const response = await apiFetch({
					path: `${API_BASE}/agents/${id}`,
				});
				return response;
			} catch (err) {
				safeSetState(setError, err.message);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Create new agent
	 */
	const createAgent = useCallback(
		async (agentData) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				const response = await apiFetch({
					path: `${API_BASE}/agents`,
					method: 'POST',
					data: agentData,
				});
				return response;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to create agent'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Update existing agent
	 */
	const updateAgent = useCallback(
		async (id, agentData) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				const response = await apiFetch({
					path: `${API_BASE}/agents/${id}`,
					method: 'PUT',
					data: agentData,
				});
				return response;
			} catch (err) {
				// Extract the actual error message from API response
				const errorMessage = err?.data?.error || err?.error || err.message || 'Failed to update agent';
				safeSetState(
					setError,
					errorMessage
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Delete agent
	 */
	const deleteAgent = useCallback(
		async (id) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				await apiFetch({
					path: `${API_BASE}/agents/${id}`,
					method: 'DELETE',
				});
				return true;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to delete agent'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Duplicate agent
	 */
	const duplicateAgent = useCallback(
		async (id, newData) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				const response = await apiFetch({
					path: `${API_BASE}/agents/${id}/duplicate`,
					method: 'POST',
					data: newData,
				});
				return response;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to duplicate agent'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Get agent assignments
	 */
	const getAssignments = useCallback(async (agentId) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/agents/${agentId}/assignments`,
			});
			return response;
		} catch (err) {
			throw err;
		}
	}, []);

	/**
	 * Create assignment
	 */
	const createAssignment = useCallback(async (agentId, assignmentData) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/agents/${agentId}/assignments`,
				method: 'POST',
				data: assignmentData,
			});
			return response;
		} catch (err) {
			throw err;
		}
	}, []);

	/**
	 * Delete assignment
	 */
	const deleteAssignment = useCallback(async (assignmentId) => {
		try {
			await apiFetch({
				path: `${API_BASE}/assignments/${assignmentId}`,
				method: 'DELETE',
			});
			return true;
		} catch (err) {
			throw err;
		}
	}, []);

	// Group state
	const [groups, setGroups] = useState([]);

	/**
	 * Fetch all groups
	 */
	const fetchGroups = useCallback(
		async (includeInactive = false) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				const params = includeInactive ? '?include_inactive=true' : '';
				const response = await apiFetch({
					path: `${API_BASE}/agent-groups${params}`,
				});
				safeSetState(setGroups, response.groups || []);
				return response.groups;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to fetch groups'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Get single group
	 */
	const getGroup = useCallback(
		async (id) => {
			safeSetState(setLoading, true);
			try {
				const response = await apiFetch({
					path: `${API_BASE}/agent-groups/${id}`,
				});
				return response;
			} catch (err) {
				safeSetState(setError, err.message);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Create new group
	 */
	const createGroup = useCallback(
		async (groupData) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				const response = await apiFetch({
					path: `${API_BASE}/agent-groups`,
					method: 'POST',
					data: groupData,
				});
				return response;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to create group'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Update existing group
	 */
	const updateGroup = useCallback(
		async (id, groupData) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				const response = await apiFetch({
					path: `${API_BASE}/agent-groups/${id}`,
					method: 'PUT',
					data: groupData,
				});
				return response;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to update group'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Delete group
	 */
	const deleteGroup = useCallback(
		async (id) => {
			safeSetState(setLoading, true);
			safeSetState(setError, null);

			try {
				await apiFetch({
					path: `${API_BASE}/agent-groups/${id}`,
					method: 'DELETE',
				});
				return true;
			} catch (err) {
				safeSetState(
					setError,
					err.message || 'Failed to delete group'
				);
				throw err;
			} finally {
				safeSetState(setLoading, false);
			}
		},
		[safeSetState]
	);

	/**
	 * Get group assignments
	 */
	const getGroupAssignments = useCallback(async (groupId) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/agent-groups/${groupId}/assignments`,
			});
			return response;
		} catch (err) {
			throw err;
		}
	}, []);

	/**
	 * Create group assignment
	 */
	const createGroupAssignment = useCallback(
		async (groupId, assignmentData) => {
			try {
				const response = await apiFetch({
					path: `${API_BASE}/agent-groups/${groupId}/assignments`,
					method: 'POST',
					data: assignmentData,
				});
				return response;
			} catch (err) {
				throw err;
			}
		},
		[]
	);

	return {
		// State
		agents,
		groups,
		toolkits,
		loading,
		error,

		// Agent methods
		fetchAgents,
		fetchToolkits,
		getAgent,
		createAgent,
		updateAgent,
		deleteAgent,
		duplicateAgent,

		// Group methods
		fetchGroups,
		getGroup,
		createGroup,
		updateGroup,
		deleteGroup,

		// Assignment methods
		getAssignments,
		createAssignment,
		deleteAssignment,
		getGroupAssignments,
		createGroupAssignment,
	};
}
