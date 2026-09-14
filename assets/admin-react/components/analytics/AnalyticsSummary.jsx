/**
 * Analytics Summary Stats - Premium Metronic v9 Style
 * 
 * Animated stat cards with micro-interactions and gradient accents.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import {
	DollarSign,
	MessageSquare,
	Mail,
	Zap,
	CheckCircle,
	TrendingUp,
	TrendingDown,
} from 'lucide-react';
import { cn } from '../../ui';

export default function AnalyticsSummary({ data, loading }) {
	if (loading || !data) {
		return (
			<div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
				{[...Array(5)].map((_, i) => (
					<div key={i} className="rounded-2xl border border-gray-200/60 bg-white p-5 animate-pulse">
						<div className="flex items-center gap-4">
							<div className="w-12 h-12 rounded-xl bg-gray-200"></div>
							<div className="flex-1 space-y-2">
								<div className="h-6 w-24 bg-gray-200 rounded"></div>
								<div className="h-4 w-16 bg-gray-100 rounded"></div>
							</div>
						</div>
					</div>
				))}
			</div>
		);
	}

	// Helper to safely format numbers
	const safeNumber = (val) => {
		const num = Number(val);
		return isNaN(num) ? 0 : num;
	};

	const formatNumber = (num) => {
		if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
		if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
		return num?.toLocaleString() || '0';
	};

	const stats = [
		{
			label: __('Total Cost', 'agentflow-ai'),
			value: `$${parseFloat(data.total_cost || 0).toFixed(2)}`,
			icon: DollarSign,
			gradient: 'from-emerald-500 to-teal-600',
			bgLight: 'bg-emerald-50',
			textColor: 'text-emerald-600',
			change: data.cost_change_percent,
		},
		{
			label: __('Total Sessions', 'agentflow-ai'),
			value: formatNumber(safeNumber(data.total_sessions)),
			icon: MessageSquare,
			gradient: 'from-blue-500 to-indigo-600',
			bgLight: 'bg-blue-50',
			textColor: 'text-blue-600',
			change: data.session_change_percent,
		},
		{
			label: __('Total Messages', 'agentflow-ai'),
			value: formatNumber(safeNumber(data.total_messages)),
			icon: Mail,
			gradient: 'from-violet-500 to-purple-600',
			bgLight: 'bg-violet-50',
			textColor: 'text-violet-600',
		},
		{
			label: __('Avg Response', 'agentflow-ai'),
			value: `${safeNumber(data.avg_response_time_ms)}ms`,
			icon: Zap,
			gradient: 'from-amber-500 to-orange-600',
			bgLight: 'bg-amber-50',
			textColor: 'text-amber-600',
			indicator: safeNumber(data.avg_response_time_ms) < 2000 ? 'good' : 'warning',
		},
		{
			label: __('Success Rate', 'agentflow-ai'),
			value: `${safeNumber(data.success_rate)}%`,
			icon: CheckCircle,
			gradient: 'from-rose-500 to-pink-600',
			bgLight: 'bg-rose-50',
			textColor: 'text-rose-600',
			indicator: safeNumber(data.success_rate) >= 95 ? 'good' : 'warning',
		},
	];

	return (
		<div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
			{stats.map((stat, index) => {
				const Icon = stat.icon;
				return (
					<div
						key={index}
						className="group relative rounded-2xl border border-gray-200/60 bg-white p-5 shadow-sm hover:shadow-lg hover:border-gray-300/60 transition-all duration-300 overflow-hidden"
					>
						{/* Decorative gradient blur */}
						<div
							className={cn(
								'absolute -top-10 -right-10 w-32 h-32 rounded-full opacity-0 group-hover:opacity-20 blur-2xl transition-opacity duration-500 bg-gradient-to-br',
								stat.gradient
							)}
						></div>

						<div className="relative flex items-center gap-4">
							{/* Icon */}
							<div
								className={cn(
									'w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-lg',
									stat.gradient
								)}
							>
								<Icon className="w-6 h-6 text-white" />
							</div>

							{/* Content */}
							<div className="flex-1 min-w-0">
								<div className="flex items-center gap-2">
									<span className="text-xl font-bold text-gray-900 truncate">
										{stat.value}
									</span>
									{/* Change indicator */}
									{stat.change !== undefined && stat.change !== 0 && (
										<span
											className={cn(
												'inline-flex items-center gap-0.5 text-xs font-medium px-1.5 py-0.5 rounded-full',
												stat.change > 0
													? 'bg-emerald-100 text-emerald-700'
													: 'bg-red-100 text-red-700'
											)}
										>
											{stat.change > 0 ? (
												<TrendingUp className="w-3 h-3" />
											) : (
												<TrendingDown className="w-3 h-3" />
											)}
											{Math.abs(stat.change).toFixed(0)}%
										</span>
									)}
									{/* Status indicator */}
									{stat.indicator && (
										<span
											className={cn(
												'w-2 h-2 rounded-full',
												stat.indicator === 'good' ? 'bg-emerald-500' : 'bg-amber-500'
											)}
										></span>
									)}
								</div>
								<p className="text-sm text-gray-500 mt-0.5">{stat.label}</p>
							</div>
						</div>

						{/* Mini sparkline placeholder */}
						<div className="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-gray-100 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
					</div>
				);
			})}
		</div>
	);
}

AnalyticsSummary.propTypes = {
	data: PropTypes.shape({
		total_cost: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
		total_sessions: PropTypes.number,
		total_messages: PropTypes.number,
		avg_response_time_ms: PropTypes.number,
		success_rate: PropTypes.number,
		cost_change_percent: PropTypes.number,
		session_change_percent: PropTypes.number,
	}),
	loading: PropTypes.bool,
};
