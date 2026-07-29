/**
 * Usage Trends Chart - Premium Metronic v9 Style
 * 
 * Interactive SVG line chart with gradient fills and tooltips.
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache } from '../../hooks/useApiCache';
import { TrendingUp, Activity, BarChart2 } from 'lucide-react';
import { cn } from '../../ui';
import Loading from '../common/Loading';

export default function UsageTrends({ period }) {
	const [data, setData] = useState([]);
	const [loading, setLoading] = useState(true);
	const [metric, setMetric] = useState('cost');
	const [hoveredPoint, setHoveredPoint] = useState(null);
	const chartRef = useRef(null);

	useEffect(() => {
		let isMounted = true;
		const cacheKey = `analytics_trends_${period}_${metric}`;
		const cached = getCached(cacheKey);

		if (cached) {
			setData(cached);
			setLoading(false);
		}

		const fetchData = async () => {
			setLoading(!cached);
			try {
				const response = await apiFetch({
					path: `/smart-ai-chatbot/v1/analytics/trends?period=${period}&metric=${metric}`,
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
	}, [period, metric]);

	const metricOptions = [
		{ value: 'cost', label: __('Cost', 'smart-woo-chatbot'), prefix: '$', icon: TrendingUp },
		{ value: 'sessions', label: __('Sessions', 'smart-woo-chatbot'), prefix: '', icon: Activity },
		{ value: 'tokens', label: __('Tokens', 'smart-woo-chatbot'), prefix: '', icon: BarChart2 },
	];

	const currentMetric = metricOptions.find((m) => m.value === metric);

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-12 gap-4">
				<Loading message={__('Loading trends...', 'smart-woo-chatbot')} />
			</div>
		);
	}

	if (!data || data.length === 0) {
		return (
			<div className="text-center py-12">
				<div className="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
					<TrendingUp className="w-8 h-8 text-gray-400 dark:text-slate-500" />
				</div>
				<p className="text-sm text-gray-500 dark:text-slate-400">{__('No trend data available', 'smart-woo-chatbot')}</p>
				<p className="text-xs text-gray-400 dark:text-slate-500 mt-1">{__('Data will appear as you use the chatbot', 'smart-woo-chatbot')}</p>
			</div>
		);
	}

	// Calculate chart dimensions and values
	const values = data.map((d) => parseFloat(d.value || 0));
	const maxVal = Math.max(...values, 0.0001);
	const minVal = Math.min(...values, 0);
	const totalVal = values.reduce((a, b) => a + b, 0);
	const avgVal = values.length > 0 ? totalVal / values.length : 0;

	// Generate SVG path
	const width = 100;
	const height = 100;
	const padding = 5;

	const points = data.map((d, i) => {
		const x = padding + (i / Math.max(data.length - 1, 1)) * (width - padding * 2);
		const y = height - padding - ((parseFloat(d.value || 0) - minVal) / (maxVal - minVal || 1)) * (height - padding * 2);
		return { x, y, data: d };
	});

	const pathD = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
	const areaD = `${pathD} L ${points[points.length - 1]?.x || 0} ${height - padding} L ${padding} ${height - padding} Z`;

	const formatValue = (val) => {
		if (metric === 'cost') return `$${parseFloat(val).toFixed(4)}`;
		if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
		if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
		return Math.round(val).toLocaleString();
	};

	return (
		<div className="space-y-5">
			{/* Header with metric selector */}
			<div className="flex items-center justify-between">
				<div className="flex items-center gap-2 bg-gray-100 dark:bg-slate-700 rounded-lg p-1">
					{metricOptions.map((option) => {
						const Icon = option.icon;
						return (
							<button
								key={option.value}
								onClick={() => setMetric(option.value)}
								className={cn(
									'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all',
									metric === option.value
										? 'bg-white text-gray-900 shadow-sm'
										: 'text-gray-500 hover:text-gray-700'
								)}
							>
								<Icon className="w-3.5 h-3.5" />
								{option.label}
							</button>
						);
					})}
				</div>

				{/* Summary */}
				<div className="text-right">
					<span className="text-lg font-bold text-gray-900 dark:text-white">
						{formatValue(totalVal)}
					</span>
					<span className="text-xs text-gray-500 dark:text-slate-400 ml-1">{__('total', 'smart-woo-chatbot')}</span>
				</div>
			</div>

			{/* Chart */}
			<div
				ref={chartRef}
				className="relative h-52 w-full bg-gradient-to-b from-gray-50/50 dark:from-slate-700/30 to-white dark:to-slate-800/50 rounded-xl border border-gray-200/60 dark:border-slate-700/60 overflow-hidden"
			>
				{/* Grid lines */}
				<svg className="absolute inset-0 w-full h-full" preserveAspectRatio="none">
					<defs>
						<pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
							<path d="M 20 0 L 0 0 0 20" fill="none" stroke="#e5e7eb" strokeWidth="0.5" />
						</pattern>
					</defs>
					<rect width="100%" height="100%" fill="url(#grid)" />
				</svg>

				{/* Chart SVG */}
				<svg
					viewBox={`0 0 ${width} ${height}`}
					preserveAspectRatio="none"
					className="absolute inset-0 w-full h-full"
				>
					<defs>
						<linearGradient id="areaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
							<stop offset="0%" stopColor="rgb(59, 130, 246)" stopOpacity="0.3" />
							<stop offset="100%" stopColor="rgb(59, 130, 246)" stopOpacity="0" />
						</linearGradient>
						<linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
							<stop offset="0%" stopColor="rgb(59, 130, 246)" />
							<stop offset="100%" stopColor="rgb(99, 102, 241)" />
						</linearGradient>
					</defs>

					{/* Area fill */}
					<path d={areaD} fill="url(#areaGradient)" />

					{/* Line */}
					<path
						d={pathD}
						fill="none"
						stroke="url(#lineGradient)"
						strokeWidth="2"
						vectorEffect="non-scaling-stroke"
						strokeLinecap="round"
						strokeLinejoin="round"
					/>

					{/* Data points */}
					{points.map((point, i) => (
						<circle
							key={i}
							cx={point.x}
							cy={point.y}
							r="1.5"
							fill="white"
							stroke="rgb(59, 130, 246)"
							strokeWidth="1"
							vectorEffect="non-scaling-stroke"
							className="cursor-pointer hover:r-3 transition-all"
							onMouseEnter={() => setHoveredPoint(i)}
							onMouseLeave={() => setHoveredPoint(null)}
						/>
					))}
				</svg>

				{/* Tooltip */}
				{hoveredPoint !== null && points[hoveredPoint] && (
					<div
						className="absolute bg-gray-900 text-white px-3 py-2 rounded-lg text-xs shadow-xl pointer-events-none z-10"
						style={{
							left: `${(points[hoveredPoint].x / width) * 100}%`,
							top: `${(points[hoveredPoint].y / height) * 100 - 10}%`,
							transform: 'translate(-50%, -100%)',
						}}
					>
						<p className="font-medium">{formatValue(points[hoveredPoint].data.value)}</p>
						<p className="text-gray-400">{points[hoveredPoint].data.date}</p>
					</div>
				)}

				{/* Date range */}
				{data.length > 0 && (
					<div className="absolute bottom-2 left-3 right-3 flex items-center justify-between text-[10px] text-gray-400 dark:text-slate-500">
						<span>{data[0].date}</span>
						<span>{data[data.length - 1].date}</span>
					</div>
				)}
			</div>

			{/* Stats */}
			<div className="grid grid-cols-3 gap-3">
				<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3 text-center">
					<p className="text-xs text-gray-500 dark:text-slate-400">{__('Average', 'smart-woo-chatbot')}</p>
					<p className="text-sm font-semibold text-gray-900 dark:text-white">{formatValue(avgVal)}</p>
				</div>
				<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3 text-center">
					<p className="text-xs text-gray-500 dark:text-slate-400">{__('Peak', 'smart-woo-chatbot')}</p>
					<p className="text-sm font-semibold text-gray-900 dark:text-white">{formatValue(maxVal)}</p>
				</div>
				<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-3 text-center">
					<p className="text-xs text-gray-500 dark:text-slate-400">{__('Data Points', 'smart-woo-chatbot')}</p>
					<p className="text-sm font-semibold text-gray-900 dark:text-white">{data.length}</p>
				</div>
			</div>
		</div>
	);
}

UsageTrends.propTypes = {
	period: PropTypes.string.isRequired,
};
