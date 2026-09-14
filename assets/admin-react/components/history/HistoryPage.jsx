/**
 * History Page - Metronic v9 Premium Style
 *
 * Main page component for viewing conversation history with modern UI.
 * Features glassmorphism, gradient headers, and premium card designs.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache } from '../../hooks/useApiCache';
import SessionList from './SessionList';
import SessionDetail from './SessionDetail';

// Icon components for premium look
const ChatIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
	</svg>
);

const ToolIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
	</svg>
);

const CheckCircleIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
	</svg>
);

const UsersIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
	</svg>
);

const CalendarIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
	</svg>
);

const ArrowLeftIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
	</svg>
);

export default function HistoryPage() {
	const [view, setView] = useState('list');
	const [sessions, setSessions] = useState([]);
	const [selectedSession, setSelectedSession] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [stats, setStats] = useState(null);

	// Pagination
	const [page, setPage] = useState(1);
	const [totalPages, setTotalPages] = useState(1);
	const [total, setTotal] = useState(0);

	// Filters
	const [searchQuery, setSearchQuery] = useState('');
	const [period, setPeriod] = useState('30d');
	const apiBase = '/quark-agentflow-ai/v1';

	// Fetch sessions
	const fetchSessions = useCallback(async () => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: `${apiBase}/history/sessions?page=${page}&per_page=20&search=${encodeURIComponent(searchQuery)}`,
			});

			if (response.success) {
				setSessions(response.data.sessions || []);
				setTotalPages(response.data.pagination?.total_pages || 1);
				setTotal(response.data.pagination?.total || 0);
			} else {
				setError(response.error || 'Failed to load sessions');
			}
		} catch (err) {
			setError(err.message || 'Failed to load sessions');
		} finally {
			setLoading(false);
		}
	}, [apiBase, page, searchQuery]);

	// Fetch stats with caching
	const fetchStats = useCallback(async () => {
		// Check cache first for instant display
		const cacheKey = `history_stats_${period}`;
		const cached = getCached(cacheKey);

		if (cached) {
			setStats(cached);
			// Background refresh
			apiFetch({ path: `${apiBase}/history/stats?period=${period}` })
				.then(response => {
					if (response.success) {
						setStats(response.data);
						setCache(cacheKey, response.data);
					}
				})
				.catch(() => { });
			return;
		}

		try {
			const response = await apiFetch({
				path: `${apiBase}/history/stats?period=${period}`,
			});

			if (response.success) {
				setStats(response.data);
				setCache(cacheKey, response.data);
			}
		} catch (err) {
			console.error('Failed to load stats:', err);
		}
	}, [apiBase, period]);

	useEffect(() => {
		fetchSessions();
	}, [fetchSessions]);

	useEffect(() => {
		fetchStats();
	}, [fetchStats]);

	// View session detail
	const handleViewSession = async (session) => {
		setLoading(true);
		try {
			const response = await apiFetch({
				path: `${apiBase}/history/sessions/${session.session_id}`,
			});

			if (response.success) {
				setSelectedSession(response.data);
				setView('detail');
			} else {
				setError(response.error || 'Failed to load session');
			}
		} catch (err) {
			setError(err.message || 'Failed to load session');
		} finally {
			setLoading(false);
		}
	};

	// Delete session
	const handleDeleteSession = async (sessionId) => {
		if (!confirm(__('Are you sure you want to delete this session?', 'agentflow-ai'))) {
			return;
		}

		try {
			await apiFetch({
				path: `${apiBase}/history/sessions/${sessionId}`,
				method: 'DELETE',
			});

			fetchSessions();

			if (selectedSession?.session_id === sessionId) {
				setView('list');
				setSelectedSession(null);
			}
		} catch (err) {
			setError(err.message || 'Failed to delete session');
		}
	};

	const handleBack = () => {
		setView('list');
		setSelectedSession(null);
	};

	return (
		<div className="min-h-screen bg-slate-50 dark:bg-slate-900">
			{/* Page Header */}
			{/* Action Bar */}
			<div className="flex items-center justify-between px-8 py-3">
				<div className="flex items-center gap-3">
					{view === 'detail' && (
						<button
							onClick={handleBack}
							className="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all duration-200"
						>
							<ArrowLeftIcon />
						</button>
					)}
				</div>
				{view === 'list' && (
					<div className="flex items-center gap-3">
						<CalendarIcon />
						<select
							value={period}
							onChange={(e) => setPeriod(e.target.value)}
							className="h-10 px-4 text-sm rounded-xl border border-gray-200 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer"
						>
							<option value="today">{__('Today', 'agentflow-ai')}</option>
							<option value="7d">{__('Last 7 days', 'agentflow-ai')}</option>
							<option value="30d">{__('Last 30 days', 'agentflow-ai')}</option>
							<option value="90d">{__('Last 90 days', 'agentflow-ai')}</option>
						</select>
					</div>
				)}
			</div>

			{/* Error Alert */}
			{error && (
				<div className="mx-8 mt-6 px-5 py-4 rounded-2xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 shadow-sm">
					<div className="flex items-center gap-3">
						<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500 text-white">
							<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
							</svg>
						</div>
						<div>
							<p className="font-medium text-red-800 dark:text-red-300">{__('Error', 'agentflow-ai')}</p>
							<p className="text-sm text-red-600 dark:text-red-400">{error}</p>
						</div>
					</div>
				</div>
			)}

			{/* Content */}
			<main className="p-8">
				{/* Stats Summary (list view only) */}
				{view === 'list' && stats && (
					<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
						{/* Tool Calls Card */}
						<div className="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm hover:shadow-lg hover:border-primary/30 dark:hover:border-primary/30 transition-all duration-300">
							<div className="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full"></div>
							<div className="relative flex items-start justify-between">
								<div>
									<p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">{__('Tool Calls', 'agentflow-ai')}</p>
									<p className="text-3xl font-bold text-slate-900 dark:text-white">
										{stats.summary?.total_tool_calls?.toLocaleString() || 0}
									</p>
								</div>
								<div className="flex items-center justify-center w-12 h-12 rounded-2xl bg-primary text-white">
									<ToolIcon />
								</div>
							</div>
							<div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
								<span className="text-xs text-slate-400 dark:text-slate-500">{__('Total executions in period', 'agentflow-ai')}</span>
							</div>
						</div>

						{/* Success Rate Card */}
						<div className="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm hover:shadow-lg hover:border-primary/30 dark:hover:border-primary/30 transition-all duration-300">
							<div className="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full"></div>
							<div className="relative flex items-start justify-between">
								<div>
									<p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">{__('Success Rate', 'agentflow-ai')}</p>
									<p className="text-3xl font-bold text-primary">
										{stats.summary?.success_rate || 0}%
									</p>
								</div>
								<div className="flex items-center justify-center w-12 h-12 rounded-2xl bg-primary text-white">
									<CheckCircleIcon />
								</div>
							</div>
							<div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
								<div className="w-full h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
									<div
										className="h-full bg-primary rounded-full transition-all duration-500"
										style={{ width: `${stats.summary?.success_rate || 0}%` }}
									></div>
								</div>
							</div>
						</div>

						{/* Sessions Card */}
						<div className="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm hover:shadow-lg hover:border-primary/30 dark:hover:border-primary/30 transition-all duration-300">
							<div className="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full"></div>
							<div className="relative flex items-start justify-between">
								<div>
									<p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">{__('Total Sessions', 'agentflow-ai')}</p>
									<p className="text-3xl font-bold text-slate-900 dark:text-white">{total.toLocaleString()}</p>
								</div>
								<div className="flex items-center justify-center w-12 h-12 rounded-2xl bg-primary text-white">
									<UsersIcon />
								</div>
							</div>
							<div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
								<span className="text-xs text-slate-400 dark:text-slate-500">{__('Active conversations', 'agentflow-ai')}</span>
							</div>
						</div>

						{/* Quick Stats Card */}
						<div className="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm hover:shadow-lg hover:border-primary/30 dark:hover:border-primary/30 transition-all duration-300">
							<div className="absolute top-0 right-0 w-40 h-40 bg-primary/5 rounded-bl-full"></div>
							<div className="relative">
								<p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-3">{__('Quick Stats', 'agentflow-ai')}</p>
								<div className="space-y-3">
									<div className="flex items-center justify-between">
										<span className="text-sm text-slate-600 dark:text-slate-300">{__('Successful', 'agentflow-ai')}</span>
										<span className="text-sm font-semibold text-green-600 dark:text-green-400">
											{stats.summary?.successful_calls?.toLocaleString() || 0}
										</span>
									</div>
									<div className="flex items-center justify-between">
										<span className="text-sm text-slate-600 dark:text-slate-300">{__('Failed', 'agentflow-ai')}</span>
										<span className="text-sm font-semibold text-red-600 dark:text-red-400">
											{stats.summary?.failed_calls?.toLocaleString() || 0}
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				)}

				{/* Session List / Detail */}
				{view === 'list' && (
					<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
						<SessionList
							sessions={sessions}
							loading={loading}
							searchQuery={searchQuery}
							onSearchChange={setSearchQuery}
							onViewSession={handleViewSession}
							onDeleteSession={handleDeleteSession}
							page={page}
							totalPages={totalPages}
							onPageChange={setPage}
						/>
					</div>
				)}

				{view === 'detail' && selectedSession && (
					<SessionDetail
						session={selectedSession}
						onDelete={() => handleDeleteSession(selectedSession.session_id)}
						onBack={handleBack}
					/>
				)}
			</main>
		</div>
	);
}
