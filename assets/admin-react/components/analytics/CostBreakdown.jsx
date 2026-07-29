/**
 * Cost Breakdown Chart - Premium Metronic v9 Style
 * 
 * Visualizes cost distribution with animated progress bars.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache } from '../../hooks/useApiCache';
import { DollarSign, Filter, TrendingUp } from 'lucide-react';
import { cn } from '../../ui';
import Loading from '../common/Loading';

const COLORS = [
	'from-blue-500 to-blue-600',
	'from-emerald-500 to-emerald-600',
	'from-violet-500 to-violet-600',
	'from-amber-500 to-amber-600',
	'from-rose-500 to-rose-600',
	'from-cyan-500 to-cyan-600',
	'from-indigo-500 to-indigo-600',
];

export default function CostBreakdown({ period }) {
	const [data, setData] = useState([]);
	const [loading, setLoading] = useState(true);
	const [groupBy, setGroupBy] = useState('provider');

	useEffect(() => {
		let isMounted = true;
		const cacheKey = `analytics_costs_${period}_${groupBy}`;
		const cached = getCached(cacheKey);

		if (cached) {
			setData(cached);
			setLoading(false);
		}

		const fetchData = async () => {
			setLoading(!cached);
			try {
				const response = await apiFetch({
					path: `/smart-ai-chatbot/v1/analytics/costs?period=${period}&group_by=${groupBy}`,
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
	}, [period, groupBy]);

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-12 gap-4">
				<Loading message={__('Loading cost data...', 'smart-woo-chatbot')} />
			</div>
		);
	}

	if (!data || data.length === 0) {
		return (
			<div className="text-center py-12">
				<div className="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
					<DollarSign className="w-8 h-8 text-gray-400 dark:text-slate-500" />
				</div>
				<p className="text-sm text-gray-500 dark:text-slate-400">{__('No cost data available', 'smart-woo-chatbot')}</p>
				<p className="text-xs text-gray-400 dark:text-slate-500 mt-1">{__('Start chatting to generate usage data', 'smart-woo-chatbot')}</p>
			</div>
		);
	}

	// Calculate totals
	const totalCost = data.reduce((sum, item) => sum + parseFloat(item.total_cost || 0), 0);
	const maxCost = Math.max(...data.map((item) => parseFloat(item.total_cost || 0)));

	return (
		<div className="space-y-5">
			{/* Filter */}
			<div className="flex items-center justify-between">
				<div className="flex items-center gap-2 bg-gray-100 dark:bg-slate-700 rounded-lg p-1">
					{[
						{ value: 'provider', label: __('Provider', 'smart-woo-chatbot') },
						{ value: 'model', label: __('Model', 'smart-woo-chatbot') },
						{ value: 'agent', label: __('Agent', 'smart-woo-chatbot') },
					].map((option) => (
						<button
							key={option.value}
							onClick={() => setGroupBy(option.value)}
							className={cn(
								'px-3 py-1.5 text-xs font-medium rounded-md transition-all',
								groupBy === option.value
									? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm'
									: 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300'
							)}
						>
							{option.label}
						</button>
					))}
				</div>
				<div className="text-sm font-semibold text-gray-900 dark:text-white">
					${totalCost.toFixed(2)}
				</div>
			</div>

			{/* Cost Items */}
			<div className="space-y-3">
				{data.slice(0, 6).map((item, index) => {
					const cost = parseFloat(item.total_cost || 0);
					const label = item.provider || item.model || item.agent_name || 'Unknown';
					const percentage = totalCost > 0 ? (cost / totalCost) * 100 : 0;
					const barWidth = maxCost > 0 ? (cost / maxCost) * 100 : 0;
					const colorClass = COLORS[index % COLORS.length];

					// Token counts
					const inputTokens = parseInt(item.total_input_tokens || 0);
					const outputTokens = parseInt(item.total_output_tokens || 0);
					const cacheWriteTokens = parseInt(item.total_cache_write_tokens || 0);
					const cacheReadTokens = parseInt(item.total_cache_read_tokens || 0);
					const totalTokens = inputTokens + outputTokens + cacheWriteTokens + cacheReadTokens;

					return (
						<div
							key={index}
							className="group relative flex items-center gap-4 p-3 rounded-xl bg-gray-50/50 dark:bg-slate-700/30 hover:bg-gray-100/50 dark:hover:bg-slate-700/50 transition-colors"
						>
							{/* Rank Badge */}
							<div
								className={cn(
									'w-8 h-8 rounded-full bg-gradient-to-br flex items-center justify-center text-xs font-bold text-white shadow-sm',
									colorClass
								)}
							>
								{index + 1}
							</div>

							{/* Content */}
							<div className="flex-1 min-w-0">
								<div className="flex items-center justify-between mb-1.5">
									<span className="text-sm font-medium text-gray-900 dark:text-white truncate">
										{label}
									</span>
									<span className="text-xs text-gray-500 dark:text-slate-400">
										{percentage.toFixed(1)}%
									</span>
								</div>
								<div className="h-2 w-full rounded-full bg-gray-200 dark:bg-slate-600 overflow-hidden mb-2">
									<div
										className={cn(
											'h-full rounded-full bg-gradient-to-r transition-all duration-500',
											colorClass
										)}
										style={{ width: `${barWidth}%` }}
									></div>
								</div>

								{/* Token Breakdown */}
								{totalTokens > 0 && (
									<div className="flex items-center gap-3 text-xs text-gray-600 dark:text-slate-400">
										<span title="Input tokens">
											 {inputTokens.toLocaleString()}
										</span>
										<span title="Output tokens">
											 {outputTokens.toLocaleString()}
										</span>
										{cacheWriteTokens > 0 && (
											<span title="Cache write tokens" className="text-amber-600 dark:text-amber-400">
												 {cacheWriteTokens.toLocaleString()}
											</span>
										)}
										{cacheReadTokens > 0 && (
											<span title="Cache read tokens" className="text-green-600 dark:text-green-400">
												 {cacheReadTokens.toLocaleString()}
											</span>
										)}
									</div>
								)}
							</div>

							{/* Cost Badge */}
							<div className="bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-1.5 shadow-sm">
								<span className="text-sm font-semibold text-gray-900 dark:text-white">
									${cost.toFixed(4)}
								</span>
							</div>
						</div>
					);
				})}
			</div>

			{/* Show more indicator */}
			{data.length > 6 && (
				<p className="text-xs text-center text-gray-400 dark:text-slate-500">
					{__('+ ', 'smart-woo-chatbot')}{data.length - 6}{__(' more items', 'smart-woo-chatbot')}
				</p>
			)}
		</div>
	);
}

CostBreakdown.propTypes = {
	period: PropTypes.string.isRequired,
};
