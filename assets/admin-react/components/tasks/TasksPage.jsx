/**
 * Tasks Page - Metronic v9 Style
 *
 * Main component for scheduled task management with modern UI.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getCached, setCache, invalidateCache } from '../../hooks/useApiCache';

import Loading from '../common/Loading';
import TaskList from './TaskList';
import TaskEditor from './TaskEditor';
import ExecutionHistory from './ExecutionHistory';

const API_BASE = window.swcChatbot?.apiUrl || '/wp-json/smart-ai-chatbot/v1';

export default function TasksPage() {
	const [view, setView] = useState('list');
	const [tasks, setTasks] = useState([]);
	const [agents, setAgents] = useState([]);
	const [stats, setStats] = useState(null);
	const [selectedTask, setSelectedTask] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [notification, setNotification] = useState(null);
	const [scheduleOptions, setScheduleOptions] = useState(null);

	useEffect(() => {
		fetchTasks();
		fetchStats();
		fetchScheduleOptions();
		fetchAgents();
	}, []);

	const fetchAgents = async () => {
		// Check cache first
		const cached = getCached('agents');
		if (cached) {
			setAgents(cached);
			return;
		}

		try {
			const resp = await fetch(`${API_BASE}/agents`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			const agentsList = data.agents || [];
			setAgents(agentsList);
			setCache('agents', agentsList);
		} catch (err) {
			console.error('Failed to fetch agents:', err);
		}
	};

	const fetchTasks = async () => {
		// Check cache first for instant display
		const cached = getCached('tasks');
		if (cached) {
			setTasks(cached);
			setLoading(false);

			// Background refresh
			fetch(`${API_BASE}/tasks`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			})
				.then(resp => resp.json())
				.then(data => {
					const tasksList = data.tasks || [];
					setTasks(tasksList);
					setCache('tasks', tasksList);
				})
				.catch(() => { });
			return;
		}

		try {
			setLoading(true);
			const resp = await fetch(`${API_BASE}/tasks`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			const tasksList = data.tasks || [];
			setTasks(tasksList);
			setCache('tasks', tasksList);
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	const fetchStats = async () => {
		// Check cache first
		const cached = getCached('tasks_stats');
		if (cached) {
			setStats(cached);
			// Background refresh
			fetch(`${API_BASE}/tasks/stats`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			})
				.then(resp => resp.json())
				.then(data => {
					setStats(data.stats || null);
					setCache('tasks_stats', data.stats || null);
				})
				.catch(() => { });
			return;
		}

		try {
			const resp = await fetch(`${API_BASE}/tasks/stats`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			setStats(data.stats || null);
			setCache('tasks_stats', data.stats || null);
		} catch (err) {
			console.error('Failed to fetch stats:', err);
		}
	};

	const fetchScheduleOptions = async () => {
		// Check cache first
		const cached = getCached('schedule_options');
		if (cached) {
			setScheduleOptions(cached);
			return;
		}

		try {
			const resp = await fetch(`${API_BASE}/tasks/schedule-options`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			setScheduleOptions(data.options || null);
			setCache('schedule_options', data.options || null);
		} catch (err) {
			console.error('Failed to fetch schedule options:', err);
		}
	};

	const handleCreate = () => {
		setSelectedTask(null);
		setView('create');
	};

	const handleEdit = (task) => {
		setSelectedTask(task);
		setView('edit');
	};

	const handleViewHistory = (task) => {
		setSelectedTask(task);
		setView('history');
	};

	const handleBack = () => {
		setView('list');
		setSelectedTask(null);
		invalidateCache('tasks');
		invalidateCache('tasks_stats');
		fetchTasks();
		fetchStats();
	};

	const handleSave = async (taskData) => {
		try {
			const isCreate = view === 'create';
			const url = isCreate ? `${API_BASE}/tasks` : `${API_BASE}/tasks/${selectedTask.id}`;
			const method = isCreate ? 'POST' : 'PUT';

			const resp = await fetch(url, {
				method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.swcChatbot?.nonce,
				},
				body: JSON.stringify(taskData),
			});

			const data = await resp.json();

			if (data.success) {
				showNotification(
					isCreate
						? __('Task created successfully!', 'agentflow-ai')
						: __('Task updated successfully!', 'agentflow-ai'),
					'success'
				);
				handleBack();
			} else {
				showNotification(data.error || __('Failed to save task', 'agentflow-ai'), 'error');
			}
		} catch (err) {
			showNotification(err.message, 'error');
		}
	};

	const handleDelete = async (taskId) => {
		if (!confirm(__('Are you sure you want to delete this task?', 'agentflow-ai'))) return;

		try {
			const resp = await fetch(`${API_BASE}/tasks/${taskId}`, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();

			if (data.success) {
				showNotification(__('Task deleted successfully!', 'agentflow-ai'), 'success');
				invalidateCache('tasks');
				invalidateCache('tasks_stats');
				fetchTasks();
				fetchStats();
			}
		} catch (err) {
			showNotification(err.message, 'error');
		}
	};

	const handleRunNow = async (taskId) => {
		try {
			const resp = await fetch(`${API_BASE}/tasks/${taskId}/run`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();

			if (data.success) {
				showNotification(__('Task queued for execution!', 'agentflow-ai'), 'success');
				fetchStats();
			}
		} catch (err) {
			showNotification(err.message, 'error');
		}
	};

	const handlePause = async (taskId) => {
		try {
			await fetch(`${API_BASE}/tasks/${taskId}/pause`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			fetchTasks();
		} catch (err) {
			showNotification(err.message, 'error');
		}
	};

	const handleResume = async (taskId) => {
		try {
			await fetch(`${API_BASE}/tasks/${taskId}/resume`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			fetchTasks();
		} catch (err) {
			showNotification(err.message, 'error');
		}
	};

	const showNotification = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 5000);
	};

	// Get page title
	const getPageTitle = () => {
		switch (view) {
			case 'create': return __('Create New Task', 'agentflow-ai');
			case 'edit': return __('Edit Task', 'agentflow-ai');
			case 'history': return selectedTask ? `${__('Execution History', 'agentflow-ai')}: ${selectedTask.name}` : __('Execution History', 'agentflow-ai');
			default: return __('Scheduled Tasks', 'agentflow-ai');
		}
	};

	// Loading state
	if (loading && tasks.length === 0) {
		return <Loading message={__('Loading scheduled tasks…', 'agentflow-ai')} fullPage />;
	}

	return (
		<div className="min-h-screen bg-gray-50/50 dark:bg-gray-900">
			{/* Page Header */}
			{/* Action Bar */}
			<div className="flex items-center justify-end px-6 py-3">
				<div className="flex items-center gap-3">
					{view === 'list' ? (
						<button
							onClick={handleCreate}
							className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
						>
							<span>+</span>
							{__('New Task', 'agentflow-ai')}
						</button>
					) : (
						<button
							onClick={handleBack}
							className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
						>
							<span>←</span>
							{__('Back to Tasks', 'agentflow-ai')}
						</button>
					)}
				</div>
			</div>

			{/* Notifications */}
			{notification && (
				<div className={`mx-6 mt-4 px-4 py-3 rounded-lg flex items-center justify-between ${notification.status === 'success'
					? 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800'
					: 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800'
					}`}>
					<span className="text-sm">{notification.message}</span>
					<button onClick={() => setNotification(null)} className="text-lg opacity-70 hover:opacity-100">×</button>
				</div>
			)}

			{/* Error */}
			{error && (
				<div className="mx-6 mt-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800">
					<span className="text-sm">{error}</span>
				</div>
			)}

			{/* Content */}
			<main className="p-6">
				{/* Stats Dashboard (only on list view) */}
				{view === 'list' && stats && (
					<div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
						<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
							<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-blue-100 text-blue-700 text-xl">

							</div>
							<div>
								<div className="text-2xl font-bold text-gray-900 dark:text-white">{stats.active_tasks || 0}</div>
								<div className="text-sm text-gray-500 dark:text-gray-400">{__('Active Tasks', 'agentflow-ai')}</div>
							</div>
						</div>
						<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
							<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-green-100 text-green-700 text-xl">

							</div>
							<div>
								<div className="text-2xl font-bold text-green-600">{stats.recent_successes || 0}</div>
								<div className="text-sm text-gray-500 dark:text-gray-400">{__('Successes (24h)', 'agentflow-ai')}</div>
							</div>
						</div>
						<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
							<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-red-100 text-red-700 text-xl">

							</div>
							<div>
								<div className="text-2xl font-bold text-red-600">{stats.recent_failures || 0}</div>
								<div className="text-sm text-gray-500 dark:text-gray-400">{__('Failures (24h)', 'agentflow-ai')}</div>
							</div>
						</div>
						<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
							<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-purple-100 text-purple-700 text-xl">

							</div>
							<div>
								<div className="text-2xl font-bold text-gray-900 dark:text-white">{stats.total_executions || 0}</div>
								<div className="text-sm text-gray-500 dark:text-gray-400">{__('Total Executions', 'agentflow-ai')}</div>
							</div>
						</div>
					</div>
				)}

				{/* Task List */}
				{view === 'list' && (
					<TaskList
						tasks={tasks}
						onEdit={handleEdit}
						onDelete={handleDelete}
						onViewHistory={handleViewHistory}
						onRunNow={handleRunNow}
						onPause={handlePause}
						onResume={handleResume}
					/>
				)}

				{/* Task Editor */}
				{(view === 'create' || view === 'edit') && (
					<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
						<TaskEditor
							task={selectedTask}
							scheduleOptions={scheduleOptions}
							agents={agents}
							onSave={handleSave}
							onCancel={handleBack}
							isNew={view === 'create'}
						/>
					</div>
				)}

				{/* Execution History */}
				{view === 'history' && selectedTask && (
					<ExecutionHistory task={selectedTask} onBack={handleBack} />
				)}
			</main>
		</div>
	);
}
