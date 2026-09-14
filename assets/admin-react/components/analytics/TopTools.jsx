/**
 * Top Tools Analytics - Premium Metronic v9 Style
 * 
 * Shows most used tools with success rates and performance metrics.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache } from '../../hooks/useApiCache';
import { Wrench, CheckCircle, Clock, TrendingUp, AlertTriangle } from 'lucide-react';
import { cn } from '../../ui';
import Loading from '../common/Loading';

export default function TopTools({ period }) {
	const [data, setData] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		let isMounted = true;
		const cacheKey = `analytics_tools_${period}`;
		const cached = getCached(cacheKey);

		if (cached) {
			setData(cached);
			setLoading(false);
		}

		const fetchData = async () => {
			setLoading(!cached);
			try {
				const response = await apiFetch({
					path: `/smart-ai-chatbot/v1/analytics/tools?period=${period}`,
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

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-12 gap-4">
				<Loading message={__('Loading tool analytics...', 'agentflow-ai')} />
			</div>
		);
	}

	if (!data || data.length === 0) {
		return (
			<div className="text-center py-12">
				<div className="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
					<Wrench className="w-8 h-8 text-gray-400 dark:text-slate-500" />
				</div>
				<p className="text-sm text-gray-500 dark:text-slate-400">{__('No tool usage recorded', 'agentflow-ai')}</p>
				<p className="text-xs text-gray-400 dark:text-slate-500 mt-1">{__('Tools will appear here when used', 'agentflow-ai')}</p>
			</div>
		);
	}

	const maxCalls = Math.max(...data.map((t) => t.call_count || 0));

	return (
		<div className="space-y-3">
			{data.slice(0, 8).map((tool, index) => {
				const successRate = parseFloat(tool.success_rate || 0);
				const avgTime = Math.round(tool.avg_duration_ms || 0);
				const callCount = parseInt(tool.call_count || 0);
				const barWidth = maxCalls > 0 ? (callCount / maxCalls) * 100 : 0;

				const getSuccessColor = () => {
					if (successRate >= 95) return 'bg-emerald-100 text-emerald-700';
					if (successRate >= 80) return 'bg-amber-100 text-amber-700';
					return 'bg-red-100 text-red-700';
				};

				const getSuccessIcon = () => {
					if (successRate >= 95) return CheckCircle;
					if (successRate >= 80) return AlertTriangle;
					return AlertTriangle;
				};

				const SuccessIcon = getSuccessIcon();

				return (
					<div
						key={index}
						className="group relative flex items-center gap-4 p-4 rounded-xl bg-gray-50/50 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/50 hover:shadow-md hover:border-gray-200 dark:hover:border-slate-600 border border-transparent transition-all"
					>
						{/* Rank indicator */}
						<div className="relative">
							<div className="w-10 h-10 rounded-xl bg-gradient-to-br from-gray-100 to-gray-200 dark:from-slate-600 dark:to-slate-700 flex items-center justify-center">
								<Wrench className="w-5 h-5 text-gray-600 dark:text-slate-300" />
							</div>
							<span className="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center shadow-sm">
								{index + 1}
							</span>
						</div>

						{/* Tool info */}
						<div className="flex-1 min-w-0">
							<div className="flex items-center gap-2">
								<span className="text-sm font-semibold text-gray-900 dark:text-white truncate">
									{tool.tool_name}
								</span>
							</div>
							<div className="flex items-center gap-4 mt-1 text-xs text-gray-500 dark:text-slate-400">
								<span className="inline-flex items-center gap-1">
									<TrendingUp className="w-3 h-3" />
									{callCount.toLocaleString()} {__('calls', 'agentflow-ai')}
								</span>
								<span className="inline-flex items-center gap-1">
									<Clock className="w-3 h-3" />
									{avgTime}ms
								</span>
							</div>
							{/* Progress bar */}
							<div className="mt-2 h-1.5 w-full rounded-full bg-gray-200 dark:bg-slate-600 overflow-hidden">
								<div
									className="h-full rounded-full bg-gradient-to-r from-primary to-primary/70 transition-all duration-500"
									style={{ width: `${barWidth}%` }}
								></div>
							</div>
						</div>

						{/* Success rate */}
						<div
							className={cn(
								'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold',
								getSuccessColor()
							)}
						>
							<SuccessIcon className="w-3.5 h-3.5" />
							{successRate.toFixed(0)}%
						</div>
					</div>
				);
			})}

			{/* Show more indicator */}
			{data.length > 8 && (
				<p className="text-xs text-center text-gray-400 dark:text-slate-500 pt-2">
					{__('+ ', 'agentflow-ai')}{data.length - 8}{__(' more tools', 'agentflow-ai')}
				</p>
			)}
		</div>
	);
}

TopTools.propTypes = {
	period: PropTypes.string.isRequired,
};
