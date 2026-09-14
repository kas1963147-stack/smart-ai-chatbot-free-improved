/**
 * Agent Performance Analytics - Premium Metronic v9 Style
 * 
 * Detailed agent comparison with performance metrics.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache } from '../../hooks/useApiCache';
import { Bot, MessageSquare, Zap, DollarSign, TrendingUp, Clock, Activity } from 'lucide-react';
import { cn } from '../../ui';
import Loading from '../common/Loading';

export default function AgentPerformance({ period }) {
	const [data, setData] = useState([]);
	const [loading, setLoading] = useState(true);
	const [viewMode, setViewMode] = useState('cards'); // 'cards' or 'table'

	useEffect(() => {
		let isMounted = true;
		const cacheKey = `analytics_agents_${period}`;
		const cached = getCached(cacheKey);

		if (cached) {
			setData(cached);
			setLoading(false);
		}

		const fetchData = async () => {
			setLoading(!cached);
			try {
				const response = await apiFetch({
					path: `/smart-ai-chatbot/v1/analytics/agents?period=${period}`,
				});
				if (isMounted && response.success) {
					setData(response.data);
					setCache(cacheKey, response.data);
				}
			} catch (err) {
				console.error(err);
			} finally {
				if (isMounted) {
					setLoading(false);
				}
			}
		};

		fetchData();

		return () => {
			isMounted = false;
		};
	}, [period]);

	const formatNumber = (num) => {
		if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
		return num?.toLocaleString() || '0';
	};

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-12 gap-4">
				<Loading message={__('Loading agent performance...', 'agentflow-ai')} />
			</div>
		);
	}

	if (!data || data.length === 0) {
		return (
			<div className="text-center py-12">
				<div className="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
					<Bot className="w-8 h-8 text-gray-400 dark:text-slate-500" />
				</div>
				<p className="text-sm text-gray-500 dark:text-slate-400">{__('No agent data available', 'agentflow-ai')}</p>
				<p className="text-xs text-gray-400 dark:text-slate-500 mt-1">{__('Agent metrics will appear as they are used', 'agentflow-ai')}</p>
			</div>
		);
	}

	// Calculate totals for comparison
	const totalSessions = data.reduce((sum, a) => sum + parseInt(a.sessions || 0), 0);
	const totalCost = data.reduce((sum, a) => sum + parseFloat(a.total_cost || 0), 0);

	// Colors for agents
	const agentColors = [
		'from-blue-500 to-indigo-600',
		'from-emerald-500 to-teal-600',
		'from-violet-500 to-purple-600',
		'from-amber-500 to-orange-600',
		'from-rose-500 to-pink-600',
		'from-cyan-500 to-sky-600',
	];

	return (
		<div className="space-y-4">
			{/* View toggle */}
			<div className="flex items-center justify-between">
				<div className="flex items-center gap-2 bg-gray-100 dark:bg-slate-700 rounded-lg p-1">
					<button
						onClick={() => setViewMode('cards')}
						className={cn(
							'px-3 py-1.5 text-xs font-medium rounded-md transition-all',
							viewMode === 'cards' ? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-slate-400'
						)}
					>
						{__('Cards', 'agentflow-ai')}
					</button>
					<button
						onClick={() => setViewMode('table')}
						className={cn(
							'px-3 py-1.5 text-xs font-medium rounded-md transition-all',
							viewMode === 'table' ? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-slate-400'
						)}
					>
						{__('Table', 'agentflow-ai')}
					</button>
				</div>
				<p className="text-sm text-gray-500 dark:text-slate-400">
					{data.length} {__('agents', 'agentflow-ai')}
				</p>
			</div>

			{/* Cards View */}
			{viewMode === 'cards' && (
				<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
					{data.map((agent, index) => {
						const sessions = parseInt(agent.sessions || 0);
						const requests = parseInt(agent.requests || 0);
						const cost = parseFloat(agent.total_cost || 0);
						const avgTime = Math.round(agent.avg_response_time || 0);
						const sessionShare = totalSessions > 0 ? (sessions / totalSessions) * 100 : 0;
						const colorClass = agentColors[index % agentColors.length];

						return (
							<div
								key={index}
								className="group relative rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 hover:shadow-lg hover:border-gray-300 dark:hover:border-slate-600 transition-all overflow-hidden"
							>
								{/* Decorative gradient */}
								<div
									className={cn(
										'absolute -top-20 -right-20 w-40 h-40 rounded-full opacity-0 group-hover:opacity-10 blur-3xl transition-opacity duration-500 bg-gradient-to-br',
										colorClass
									)}
								></div>

								{/* Header */}
								<div className="relative flex items-center gap-4 mb-4">
									<div
										className={cn(
											'w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-lg',
											colorClass
										)}
									>
										<Bot className="w-6 h-6 text-white" />
									</div>
									<div className="flex-1 min-w-0">
										<h4 className="text-sm font-semibold text-gray-900 dark:text-white truncate">
											{agent.agent_name || __('Unknown Agent', 'agentflow-ai')}
										</h4>
										<p className="text-xs text-gray-500 dark:text-slate-400">
											{sessionShare.toFixed(1)}% {__('of total sessions', 'agentflow-ai')}
										</p>
									</div>
								</div>

								{/* Stats */}
								<div className="relative grid grid-cols-2 gap-3">
									<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
										<div className="flex items-center gap-2 text-gray-500 dark:text-slate-400 mb-1">
											<MessageSquare className="w-3.5 h-3.5" />
											<span className="text-xs">{__('Sessions', 'agentflow-ai')}</span>
										</div>
										<p className="text-lg font-bold text-gray-900 dark:text-white">{formatNumber(sessions)}</p>
									</div>
									<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
										<div className="flex items-center gap-2 text-gray-500 dark:text-slate-400 mb-1">
											<Activity className="w-3.5 h-3.5" />
											<span className="text-xs">{__('Requests', 'agentflow-ai')}</span>
										</div>
										<p className="text-lg font-bold text-gray-900 dark:text-white">{formatNumber(requests)}</p>
									</div>
									<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
										<div className="flex items-center gap-2 text-gray-500 dark:text-slate-400 mb-1">
											<DollarSign className="w-3.5 h-3.5" />
											<span className="text-xs">{__('Cost', 'agentflow-ai')}</span>
										</div>
										<p className="text-lg font-bold text-emerald-600">${cost.toFixed(4)}</p>
									</div>
									<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3">
										<div className="flex items-center gap-2 text-gray-500 dark:text-slate-400 mb-1">
											<Zap className="w-3.5 h-3.5" />
											<span className="text-xs">{__('Latency', 'agentflow-ai')}</span>
										</div>
										<p className={cn(
											'text-lg font-bold',
											avgTime < 2000 ? 'text-emerald-600' : avgTime < 5000 ? 'text-amber-600' : 'text-red-600'
										)}>
											{avgTime}ms
										</p>
									</div>
								</div>
							</div>
						);
					})}
				</div>
			)}

			{/* Table View */}
			{viewMode === 'table' && (
				<div className="overflow-x-auto rounded-xl border border-gray-200 dark:border-slate-700">
					<table className="w-full">
						<thead>
							<tr className="bg-gray-50 dark:bg-slate-700 border-b border-gray-200 dark:border-slate-600">
								<th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">
									{__('Agent', 'agentflow-ai')}
								</th>
								<th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">
									{__('Sessions', 'agentflow-ai')}
								</th>
								<th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">
									{__('Requests', 'agentflow-ai')}
								</th>
								<th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">
									{__('Cost', 'agentflow-ai')}
								</th>
								<th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">
									{__('Avg Latency', 'agentflow-ai')}
								</th>
							</tr>
						</thead>
						<tbody className="divide-y divide-gray-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
							{data.map((agent, index) => (
								<tr key={index} className="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
									<td className="px-4 py-3">
										<div className="flex items-center gap-3">
											<div
												className={cn(
													'w-8 h-8 rounded-lg bg-gradient-to-br flex items-center justify-center',
													agentColors[index % agentColors.length]
												)}
											>
												<Bot className="w-4 h-4 text-white" />
											</div>
											<span className="text-sm font-medium text-gray-900 dark:text-white">
												{agent.agent_name || __('Unknown', 'agentflow-ai')}
											</span>
										</div>
									</td>
									<td className="px-4 py-3 text-sm text-gray-600 dark:text-slate-400">
										{formatNumber(agent.sessions)}
									</td>
									<td className="px-4 py-3 text-sm text-gray-600 dark:text-slate-400">
										{formatNumber(agent.requests)}
									</td>
									<td className="px-4 py-3">
										<span className="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
											${parseFloat(agent.total_cost).toFixed(4)}
										</span>
									</td>
									<td className="px-4 py-3">
										<span
											className={cn(
												'text-sm font-medium',
												agent.avg_response_time < 2000
													? 'text-emerald-600'
													: agent.avg_response_time < 5000
														? 'text-amber-600'
														: 'text-red-600'
											)}
										>
											{Math.round(agent.avg_response_time)}ms
										</span>
									</td>
								</tr>
							))}
						</tbody>
					</table>
				</div>
			)}
		</div>
	);
}

AgentPerformance.propTypes = {
	period: PropTypes.string.isRequired,
};
