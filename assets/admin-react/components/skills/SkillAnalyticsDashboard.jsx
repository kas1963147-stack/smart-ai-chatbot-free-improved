/**
 * SkillAnalyticsDashboard Component
 *
 * Dashboard showing skill usage metrics and trends with premium dark mode support.
 */
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { BarChart2, CheckCircle, Zap, Clock, RefreshCw, TrendingUp } from 'lucide-react';
import Loading from '../common/Loading';

export default function SkillAnalyticsDashboard({ skillId = null }) {
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(true);
	const [timeRange, setTimeRange] = useState(30);
	const [error, setError] = useState(null);

	const fetchData = useCallback(async () => {
		setLoading(true);
		setError(null);
		try {
			const endpoint = skillId
				? `/quark-agentflow-ai/v1/skills/${skillId}/analytics`
				: '/quark-agentflow-ai/v1/skill-analytics';

			const response = await apiFetch({
				path: `${endpoint}?days=${timeRange}`,
			});

			if (response.success) {
				setData(response.data);
			} else {
				setError(response.error?.message || 'Failed to load analytics');
			}
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	}, [skillId, timeRange]);

	// Fetch analytics data
	useEffect(() => {
		fetchData();
	}, [fetchData]);

	// Loading state with premium loader
	if (loading) {
		return (
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8">
				<Loading message={__('Loading analyticsâ€¦', 'agentflow-ai')} fullPage />
			</div>
		);
	}

	// Error state
	if (error) {
		return (
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8">
				<div className="flex flex-col items-center justify-center py-12 text-center">
					<div className="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-4">
						<span className="text-3xl"></span>
					</div>
					<h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
						{__('Error Loading Analytics', 'agentflow-ai')}
					</h3>
					<p className="text-gray-500 dark:text-gray-400 mb-6 max-w-md">{error}</p>
					<button
						onClick={fetchData}
						className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 transition-colors"
					>
						<RefreshCw className="w-4 h-4" />
						{__('Retry', 'agentflow-ai')}
					</button>
				</div>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			{/* Header with time range filter */}
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
				<div className="flex items-center justify-between">
					<div className="flex items-center gap-3">
						<div className="w-10 h-10 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
							<BarChart2 className="w-5 h-5 text-primary" />
						</div>
						<div>
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
								{skillId
									? __('Skill Analytics', 'agentflow-ai')
									: __('All Skills Analytics', 'agentflow-ai')}
							</h3>
							<p className="text-sm text-gray-500 dark:text-gray-400">
								{__('Performance metrics and usage trends', 'agentflow-ai')}
							</p>
						</div>
					</div>

					<select
						value={timeRange}
						onChange={(e) => setTimeRange(parseInt(e.target.value, 10))}
						className="h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer"
					>
						<option value={7}>{__('Last 7 days', 'agentflow-ai')}</option>
						<option value={30}>{__('Last 30 days', 'agentflow-ai')}</option>
						<option value={90}>{__('Last 90 days', 'agentflow-ai')}</option>
					</select>
				</div>
			</div>

			{/* Stats cards */}
			<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
				<StatCard
					label={__('Total Loads', 'agentflow-ai')}
					value={data?.stats?.loads || 0}
					icon={<TrendingUp className="w-5 h-5" />}
					color="blue"
				/>
				<StatCard
					label={__('Success Rate', 'agentflow-ai')}
					value={`${data?.stats?.success_rate || 0}%`}
					icon={<CheckCircle className="w-5 h-5" />}
					color={data?.stats?.success_rate >= 80 ? 'green' : 'yellow'}
				/>
				<StatCard
					label={__('Avg Tokens', 'agentflow-ai')}
					value={data?.stats?.avg_tokens || 0}
					icon={<Zap className="w-5 h-5" />}
					color="purple"
				/>
				<StatCard
					label={__('Avg Response', 'agentflow-ai')}
					value={`${data?.stats?.avg_response_time_ms || 0}ms`}
					icon={<Clock className="w-5 h-5" />}
					color="orange"
				/>
			</div>

			{/* Usage chart */}
			{data?.timeline && data.timeline.length > 0 && (
				<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
					<h4 className="text-base font-semibold text-gray-900 dark:text-white mb-4">
						{__('Usage Over Time', 'agentflow-ai')}
					</h4>
					<SimpleBarChart data={data.timeline} />
				</div>
			)}

			{/* Top skills table */}
			{!skillId && data?.topSkills && data.topSkills.length > 0 && (
				<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
					<div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
						<h4 className="text-base font-semibold text-gray-900 dark:text-white">
							{__('Top Skills', 'agentflow-ai')}
						</h4>
					</div>
					<div className="overflow-x-auto">
						<table className="w-full text-sm">
							<thead>
								<tr className="bg-gray-50 dark:bg-gray-900/50">
									<th className="px-6 py-3 text-left font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">
										{__('Skill', 'agentflow-ai')}
									</th>
									<th className="px-6 py-3 text-left font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">
										{__('Loads', 'agentflow-ai')}
									</th>
									<th className="px-6 py-3 text-left font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">
										{__('Success', 'agentflow-ai')}
									</th>
									<th className="px-6 py-3 text-left font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">
										{__('Rate', 'agentflow-ai')}
									</th>
								</tr>
							</thead>
							<tbody className="divide-y divide-gray-200 dark:divide-gray-700">
								{data.topSkills.map((skill, idx) => (
									<tr key={skill.skill_id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
										<td className="px-6 py-4">
											<div className="flex items-center gap-2">
												<span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300">
													#{idx + 1}
												</span>
												<span className="font-medium text-gray-900 dark:text-white">{skill.skill_id}</span>
											</div>
										</td>
										<td className="px-6 py-4 text-gray-600 dark:text-gray-300">{skill.loads}</td>
										<td className="px-6 py-4 text-gray-600 dark:text-gray-300">{skill.successes}</td>
										<td className="px-6 py-4">
											<span
												className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${skill.loads > 0 && skill.successes / skill.loads >= 0.8
														? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300'
														: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300'
													}`}
											>
												{skill.loads > 0
													? Math.round((skill.successes / skill.loads) * 100)
													: 0}%
											</span>
										</td>
									</tr>
								))}
							</tbody>
						</table>
					</div>
				</div>
			)}

			{/* Empty state */}
			{(!data?.stats?.loads || data.stats.loads === 0) && (
				<div className="bg-white dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-12">
					<div className="flex flex-col items-center justify-center text-center">
						<div className="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
							<BarChart2 className="w-8 h-8 text-gray-400 dark:text-gray-500" />
						</div>
						<h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
							{__('No Data Yet', 'agentflow-ai')}
						</h3>
						<p className="text-gray-500 dark:text-gray-400 max-w-md">
							{__(
								'Analytics will appear once skills are used in conversations.',
								'agentflow-ai'
							)}
						</p>
					</div>
				</div>
			)}
		</div>
	);
}

/**
 * Stat card component with dark mode support
 */
function StatCard({ label, value, icon, color = 'blue' }) {
	const colorClasses = {
		blue: 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400',
		green: 'bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400',
		purple: 'bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400',
		orange: 'bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400',
		yellow: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-600 dark:text-yellow-400',
	};

	return (
		<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
			<div className="flex items-center gap-4">
				<div className={`w-12 h-12 rounded-xl flex items-center justify-center ${colorClasses[color]}`}>
					{icon}
				</div>
				<div>
					<div className="text-2xl font-bold text-gray-900 dark:text-white">{value}</div>
					<div className="text-sm text-gray-500 dark:text-gray-400">{label}</div>
				</div>
			</div>
		</div>
	);
}

StatCard.propTypes = {
	label: PropTypes.string.isRequired,
	value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
	icon: PropTypes.node,
	color: PropTypes.oneOf(['blue', 'green', 'purple', 'orange', 'yellow']),
};

/**
 * Simple bar chart component with dark mode support
 */
function SimpleBarChart({ data }) {
	const maxValue = useMemo(() => {
		return Math.max(...data.map((d) => d.count), 1);
	}, [data]);

	return (
		<div className="flex items-end justify-between gap-1 h-48">
			{data.map((item, idx) => (
				<div key={idx} className="flex-1 flex flex-col items-center gap-2 h-full">
					<div className="flex-1 w-full flex items-end">
						<div
							className="w-full bg-primary/80 dark:bg-primary/60 hover:bg-primary transition-all rounded-t-md cursor-pointer"
							style={{
								height: `${(item.count / maxValue) * 100}%`,
								minHeight: item.count > 0 ? '4px' : '0',
							}}
							title={`${item.date}: ${item.count} loads`}
						/>
					</div>
					<div className="text-xs text-gray-400 dark:text-gray-500 font-medium">
						{new Date(item.date).getDate()}
					</div>
				</div>
			))}
		</div>
	);
}

SimpleBarChart.propTypes = {
	data: PropTypes.arrayOf(
		PropTypes.shape({
			date: PropTypes.string,
			count: PropTypes.number,
		})
	),
};

SkillAnalyticsDashboard.propTypes = {
	skillId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
};
