/**
 * useSkillsApi Hook
 *
 * React hook for Skills REST API operations.
 * 
 * OPTIMIZED:
 * - Caching for faster subsequent loads
 * - Optimistic UI updates
 * - Background refresh
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache, invalidateCache } from './useApiCache';

const API_BASE = '/quark-agentflow-ai/v1';

export default function useSkillsApi() {
	const [skills, setSkills] = useState([]);
	const [categories, setCategories] = useState({});
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	// Track if initial fetch has been done
	const initialFetchDone = useRef(false);

	// Fetch all skills with caching
	const fetchSkills = useCallback(async (forceRefresh = false) => {
		// Check cache first (unless force refresh)
		if (!forceRefresh) {
			const cached = getCached('skills');
			if (cached) {
				setSkills(cached.skills || []);
				setCategories(cached.categories || {});

				// Return cached data immediately, but refresh in background
				if (!initialFetchDone.current) {
					initialFetchDone.current = true;
					// Background refresh after 100ms
					setTimeout(() => fetchSkills(true), 100);
				}
				return;
			}
		}

		setLoading(!getCached('skills')); // Only show loading if no cache
		setError(null);

		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills`,
			});

			if (response.success) {
				const data = {
					skills: response.data.skills || [],
					categories: response.data.categories || {},
				};
				setSkills(data.skills);
				setCategories(data.categories);
				setCache('skills', data);
			} else {
				throw new Error(response.error?.message || 'Failed to fetch skills');
			}
		} catch (err) {
			setError(err.message);
			console.error('Failed to fetch skills:', err);
		} finally {
			setLoading(false);
		}
	}, []);

	// Get single skill
	const getSkill = useCallback(async (skillId) => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills/${skillId}`,
			});

			if (response.success) {
				return response.data;
			}
			throw new Error(response.error?.message || 'Failed to fetch skill');
		} catch (err) {
			setError(err.message);
			throw err;
		} finally {
			setLoading(false);
		}
	}, []);

	// Create skill with cache invalidation
	const createSkill = useCallback(async (skillData) => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills`,
				method: 'POST',
				data: skillData,
			});

			if (response.success) {
				invalidateCache('skills');
				return response.data;
			}
			const err = new Error(response.error?.message || 'Failed to create skill');
			err.details = response.error?.details;
			throw err;
		} catch (err) {
			setError(err.message);
			throw err;
		} finally {
			setLoading(false);
		}
	}, []);

	// Update skill with optimistic update
	const updateSkill = useCallback(async (skillId, skillData) => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills/${skillId}`,
				method: 'PUT',
				data: skillData,
			});

			if (response.success) {
				invalidateCache('skills');
				return response.data;
			}
			const err = new Error(response.error?.message || 'Failed to update skill');
			err.details = response.error?.details;
			throw err;
		} catch (err) {
			setError(err.message);
			throw err;
		} finally {
			setLoading(false);
		}
	}, []);

	// Delete skill
	const deleteSkill = useCallback(async (skillId) => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills/${skillId}`,
				method: 'DELETE',
			});

			if (response.success) {
				invalidateCache('skills');
				// Optimistic update
				setSkills(prev => prev.filter(s => s.id !== skillId));
				return true;
			}
			throw new Error(response.error?.message || 'Failed to delete skill');
		} catch (err) {
			setError(err.message);
			throw err;
		} finally {
			setLoading(false);
		}
	}, []);

	// Fetch categories
	const fetchCategories = useCallback(async () => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skill-categories`,
			});

			if (response.success) {
				setCategories(response.data);
				return response.data;
			}
		} catch (err) {
			console.error('Failed to fetch categories:', err);
		}
	}, []);

	// Get references for a skill
	const getReferences = useCallback(async (skillId) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills/${skillId}/references`,
			});

			if (response.success) {
				return response.data;
			}
			return [];
		} catch (err) {
			console.error('Failed to fetch references:', err);
			return [];
		}
	}, []);

	// Save reference
	const saveReference = useCallback(async (skillId, refData) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills/${skillId}/references`,
				method: 'POST',
				data: refData,
			});

			if (response.success) {
				return response.data;
			}
			throw new Error(response.error?.message || 'Failed to save reference');
		} catch (err) {
			throw err;
		}
	}, []);

	// Delete reference
	const deleteReference = useCallback(async (skillId, refName) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skills/${skillId}/references/${refName}`,
				method: 'DELETE',
			});

			return response.success;
		} catch (err) {
			console.error('Failed to delete reference:', err);
			return false;
		}
	}, []);

	// --------------------------------------------
	// Group Management
	// --------------------------------------------
	const [groups, setGroups] = useState([]);

	// Fetch groups
	const fetchGroups = useCallback(async () => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skill-groups`,
			});

			if (response.success) {
				setGroups(response.data || []);
				return response.data;
			}
		} catch (err) {
			console.error('Failed to fetch groups:', err);
		}
	}, []);

	// Create group
	const createGroup = useCallback(async (groupData) => {
		setLoading(true);
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skill-groups`,
				method: 'POST',
				data: groupData,
			});

			if (response.success) {
				await fetchGroups();
				return response.data;
			}
			throw new Error(response.error?.message || 'Failed to create group');
		} catch (err) {
			throw err;
		} finally {
			setLoading(false);
		}
	}, [fetchGroups]);

	// Update group
	const updateGroup = useCallback(async (groupId, groupData) => {
		setLoading(true);
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skill-groups/${groupId}`,
				method: 'PUT',
				data: groupData,
			});

			if (response.success) {
				await fetchGroups();
				return response.data;
			}
			throw new Error(response.error?.message || 'Failed to update group');
		} catch (err) {
			throw err;
		} finally {
			setLoading(false);
		}
	}, [fetchGroups]);

	// Delete group
	const deleteGroup = useCallback(async (groupId) => {
		setLoading(true);
		try {
			const response = await apiFetch({
				path: `${API_BASE}/skill-groups/${groupId}`,
				method: 'DELETE',
			});

			if (response.success) {
				await fetchGroups();
				return true;
			}
			throw new Error(response.error?.message || 'Failed to delete group');
		} catch (err) {
			throw err;
		} finally {
			setLoading(false);
		}
	}, [fetchGroups]);

	return {
		skills,
		categories,
		loading,
		error,
		fetchSkills,
		getSkill,
		createSkill,
		updateSkill,
		deleteSkill,
		fetchCategories,
		getReferences,
		saveReference,
		deleteReference,
		groups,
		fetchGroups,
		createGroup,
		updateGroup,
		deleteGroup,
	};
}
