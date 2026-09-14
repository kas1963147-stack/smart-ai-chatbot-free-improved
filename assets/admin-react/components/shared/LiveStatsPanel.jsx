/**
 * Real-time Stats Panel Component
 *
 * Live activity panel showing current chat sessions and real-time metrics.
 * Uses polling or WebSocket for updates.
 *
 * @version 1.0.0
 * @package
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { IconButton } from '../ui';

const REFRESH_INTERVAL = 30000; // 30 seconds

/**
 * LiveStatsPanel Component
 */
export default function LiveStatsPanel() {
	const [stats, setStats] = useState({
		activeSessions: 0,
		todayConversations: 0,
		todayMessages: 0,
		avgResponseTime: 0,
		recentActivity: [],
	});
	const [loading, setLoading] = useState(true);
	const [lastUpdate, setLastUpdate] = useState(null);

	const fetchLiveStats = useCallback(async () => {
		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/analytics/live',
			});

			if (response.success) {
				setStats(response.data);
				setLastUpdate(new Date());
			}
		} catch (err) {
			console.error('Live stats fetch failed:', err);
		} finally {
			setLoading(false);
		}
	}, []);

	// Initial fetch and polling
	useEffect(() => {
		fetchLiveStats();
		const interval = setInterval(fetchLiveStats, REFRESH_INTERVAL);
		return () => clearInterval(interval);
	}, [fetchLiveStats]);

	const formatTime = (seconds) => {
		// Handle NaN, undefined, or null
		if (seconds === null || seconds === undefined || isNaN(seconds)) {
			return '0s';
		}
		if (seconds < 60) {
			return `${seconds}s`;
		}
		return `${Math.round(seconds / 60)}m`;
	};

	const formatLastUpdate = () => {
		if (!lastUpdate) {
			return '';
		}
		const seconds = Math.floor(
			(Date.now() - lastUpdate.getTime()) / 1000
		);
		if (seconds < 5) {
			return __('Just now', 'agentflow-ai');
		}
		if (seconds < 60) {
			return `${seconds}s ago`;
		}
		return `${Math.floor(seconds / 60)}m ago`;
	};

	return (
		<div className="rounded-2xl border border-slate-200/70 bg-white px-4 py-3 shadow-sm dark:border-slate-800/70 dark:bg-slate-900">
			<div className="flex flex-wrap items-center gap-4">
				<div className="flex items-center gap-2">
					<span className="relative flex h-3 w-3">
						<span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70" />
						<span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-500" />
					</span>
					<span className="text-sm font-semibold text-slate-900 dark:text-slate-100">
						{__('Live Activity', 'agentflow-ai')}
					</span>
				</div>

				<div className="flex flex-1 flex-wrap items-center gap-4 text-sm text-slate-600 dark:text-slate-300 md:justify-center">
					{loading ? (
						<div className="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
					) : (
						<>
							<div className="flex items-center gap-2">
								<span className="text-xs uppercase tracking-wide text-slate-400">
									{__('Active Now', 'agentflow-ai')}
								</span>
								<span className="rounded-full bg-emerald-50 px-2 py-0.5 text-sm font-semibold text-emerald-700">
									{stats.activeSessions}
								</span>
							</div>
							<span className="hidden h-4 w-px bg-slate-200 md:block" />
							<div className="flex items-center gap-2">
								<span className="text-xs uppercase tracking-wide text-slate-400">
									{__('Today', 'agentflow-ai')}
								</span>
								<span className="text-sm font-semibold text-slate-900 dark:text-slate-100">
									{stats.todayConversations}
								</span>
							</div>
							<span className="hidden h-4 w-px bg-slate-200 md:block" />
							<div className="flex items-center gap-2">
								<span className="text-xs uppercase tracking-wide text-slate-400">
									{__('Messages', 'agentflow-ai')}
								</span>
								<span className="text-sm font-semibold text-slate-900 dark:text-slate-100">
									{stats.todayMessages}
								</span>
							</div>
							<span className="hidden h-4 w-px bg-slate-200 md:block" />
							<div className="flex items-center gap-2">
								<span className="text-xs uppercase tracking-wide text-slate-400">
									{__('Avg Response', 'agentflow-ai')}
								</span>
								<span className="text-sm font-semibold text-slate-900 dark:text-slate-100">
									{formatTime(stats.avgResponseTime || 0)}
								</span>
							</div>
						</>
					)}
				</div>

				<div className="ml-auto flex items-center gap-3">
					<span className="text-xs text-slate-400">
						{formatLastUpdate()}
					</span>
					<IconButton
						onClick={fetchLiveStats}
						title={__('Refresh', 'agentflow-ai')}
						aria-label={__('Refresh', 'agentflow-ai')}
						variant="ghost"
						size="sm"
					>
						<svg
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 20 20"
							fill="currentColor"
							className="h-4 w-4"
						>
							<path
								fillRule="evenodd"
								d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z"
								clipRule="evenodd"
							/>
						</svg>
					</IconButton>
				</div>
			</div>
		</div>
	);
}
