/**
 * Analytics Dashboard - Premium Metronic v9 Style
 *
 * Comprehensive analytics dashboard for AI chatbot administrators.
 * Features real-time stats, cost tracking, usage analytics, and system insights.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache } from '../../hooks/useApiCache';
import {
	TrendingUp,
	TrendingDown,
	DollarSign,
	MessageSquare,
	Users,
	Zap,
	Clock,
	Activity,
	BarChart3,
	PieChart,
	RefreshCw,
	Download,
	Calendar,
	Filter,
	ChevronRight,
	Bot,
	Wrench,
	AlertTriangle,
	CheckCircle,
	XCircle,
	ArrowUpRight,
	ArrowDownRight,
	Sparkles,
	Target,
	Brain,
	Settings,
} from 'lucide-react';

import Loading from '../common/Loading';
import AnalyticsSummary from './AnalyticsSummary';
import CostBreakdown from './CostBreakdown';
import UsageTrends from './UsageTrends';
import TopTools from './TopTools';
import AgentPerformance from './AgentPerformance';
import { LiveStatsPanel } from '../shared';
import { cn } from '../../ui';

export default function AnalyticsPage() {
	const [period, setPeriod] = useState('30d');
	const [loading, setLoading] = useState(true);
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [activeTab, setActiveTab] = useState('overview');
	const [isRefreshing, setIsRefreshing] = useState(false);

	const periods = [
		{ label: __('Today', 'agentflow-ai'), value: 'today', icon: Clock },
		{ label: __('7 Days', 'agentflow-ai'), value: '7d', icon: Calendar },
		{ label: __('30 Days', 'agentflow-ai'), value: '30d', icon: Calendar },
		{ label: __('90 Days', 'agentflow-ai'), value: '90d', icon: Calendar },
		{ label: __('All Time', 'agentflow-ai'), value: 'all', icon: BarChart3 },
	];

	const tabs = [
		{ id: 'overview', label: __('Overview', 'agentflow-ai'), icon: BarChart3 },
		{ id: 'costs', label: __('Costs & Usage', 'agentflow-ai'), icon: DollarSign },
		{ id: 'agents', label: __('Agent Performance', 'agentflow-ai'), icon: Bot },
		{ id: 'tools', label: __('Tool Analytics', 'agentflow-ai'), icon: Wrench },
	];

	const fetchData = useCallback(async () => {
		const cacheKey = `analytics_${period}`;
		const cached = getCached(cacheKey);

		if (cached && !isRefreshing) {
			setData(cached);
			setLoading(false);

			// Background refresh
			apiFetch({ path: `/smart-ai-chatbot/v1/analytics/dashboard?period=${period}` })
				.then((response) => {
					if (response.success) {
						setData(response.data);
						setCache(cacheKey, response.data);
					}
				})
				.catch(() => { });
			return;
		}

		setLoading(true);
		setError(null);
		try {
			const response = await apiFetch({
				path: `/smart-ai-chatbot/v1/analytics/dashboard?period=${period}`,
			});

			if (response.success) {
				setData(response.data);
				setCache(cacheKey, response.data);
			} else {
				setError(response.message || 'Failed to load data');
			}
		} catch (err) {
			setError(err.message || 'An error occurred');
		} finally {
			setLoading(false);
			setIsRefreshing(false);
		}
	}, [period, isRefreshing]);

	useEffect(() => {
		fetchData();
	}, [fetchData]);

	const handleRefresh = () => {
		setIsRefreshing(true);
		fetchData();
	};

	const handleExport = () => {
		const url = `${window.swcChatbot?.apiUrl || '/wp-json'}/smart-ai-chatbot/v1/analytics/export?period=${period}&format=csv&_wpnonce=${window.swcChatbot?.nonce}`;
		window.open(url, '_blank');
	};

	// Helper functions
	const formatNumber = (num) => {
		if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
		if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
		return num?.toLocaleString() || '0';
	};

	const formatCurrency = (amount) => {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 2,
			maximumFractionDigits: 4,
		}).format(amount || 0);
	};

	const getChangeColor = (change) => {
		if (change > 0) return 'text-emerald-600';
		if (change < 0) return 'text-red-500';
		return 'text-gray-500';
	};

	const getChangeIcon = (change) => {
		if (change > 0) return ArrowUpRight;
		if (change < 0) return ArrowDownRight;
		return null;
	};

	// Error state
	if (error) {
		return (
			<div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100/50 dark:from-slate-900 dark:to-slate-800 p-6">
				<div className="max-w-lg mx-auto mt-20">
					<div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-red-100 dark:border-red-900/50 p-8 text-center">
						<div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
							<XCircle className="w-8 h-8 text-red-500" />
						</div>
						<h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
							{__('Failed to Load Analytics', 'agentflow-ai')}
						</h3>
						<p className="text-sm text-gray-500 dark:text-slate-400 mb-6">{error}</p>
						<button
							onClick={fetchData}
							className="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium rounded-xl bg-primary text-white hover:bg-primary/90 shadow-lg shadow-primary/25 transition-all"
						>
							<RefreshCw className="w-4 h-4" />
							{__('Try Again', 'agentflow-ai')}
						</button>
					</div>
				</div>
			</div>
		);
	}

	// Loading state
	if (loading && !data) {
		return <Loading message={__('Loading analytics data…', 'agentflow-ai')} fullPage />;
	}

	// Calculate derived stats
	const stats = data
		? {
			totalCost: parseFloat(data.total_cost || 0),
			totalSessions: parseInt(data.total_sessions || 0),
			totalMessages: parseInt(data.total_messages || 0),
			avgResponse: parseInt(data.avg_response_time_ms || 0),
			successRate: parseFloat(data.success_rate || 0),
			totalTokens: parseInt(data.total_tokens || 0),
			costChange: parseFloat(data.cost_change_percent || 0),
			sessionChange: parseFloat(data.session_change_percent || 0),
		}
		: {};

	return (
		<div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50/80 dark:from-slate-900 dark:via-slate-900 dark:to-slate-800">
			{/* Toolbar */}
			<div className="px-6 py-4">
				<div className="flex flex-col lg:flex-row lg:items-center lg:justify-end gap-4">
					{/* Actions */}
					<div className="flex items-center gap-3">
						{/* Period Selector */}
						<div className="flex items-center bg-gray-100/80 dark:bg-slate-700/80 rounded-xl p-1">
							{periods.map((p) => (
								<button
									key={p.value}
									onClick={() => setPeriod(p.value)}
									className={cn(
										'px-4 py-2 text-sm font-medium rounded-lg transition-all',
										period === p.value
											? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm'
											: 'text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
									)}
								>
									{p.label}
								</button>
							))}
						</div>

						<div className="h-8 w-px bg-gray-200 dark:bg-slate-600"></div>

						{/* Export */}
						<button
							onClick={handleExport}
							className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600 hover:border-gray-300 dark:hover:border-slate-500 transition-all shadow-sm"
						>
							<Download className="w-4 h-4" />
							{__('Export', 'agentflow-ai')}
						</button>

						{/* Refresh */}
						<button
							onClick={handleRefresh}
							disabled={isRefreshing}
							className={cn(
								'inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl text-white bg-gradient-to-r from-primary to-primary/90 hover:from-primary/90 hover:to-primary shadow-lg shadow-primary/25 transition-all',
								isRefreshing && 'opacity-70 cursor-not-allowed'
							)}
						>
							<RefreshCw className={cn('w-4 h-4', isRefreshing && 'animate-spin')} />
							{isRefreshing ? __('Refreshing…', 'agentflow-ai') : __('Refresh', 'agentflow-ai')}
						</button>
					</div>
				</div>

				{/* Tabs */}
				<div className="flex items-center gap-1 mt-4 border-b border-gray-200/60 dark:border-slate-700/60">
					{tabs.map((tab) => {
						const Icon = tab.icon;
						return (
							<button
								key={tab.id}
								onClick={() => setActiveTab(tab.id)}
								className={cn(
									'inline-flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all',
									activeTab === tab.id
										? 'text-primary border-primary'
										: 'text-gray-500 dark:text-slate-400 border-transparent hover:text-gray-700 dark:hover:text-slate-300 hover:border-gray-300 dark:hover:border-slate-600'
								)}
							>
								<Icon className="w-4 h-4" />
								{tab.label}
							</button>
						);
					})}
				</div>
			</div>

			{/* Main Content */}
			<main className="p-6">
				{/* Live Stats */}
				<div className="mb-8">
					<LiveStatsPanel />
				</div>


				{/* Overview Tab */}
				{activeTab === 'overview' && (
					<div className="space-y-6">
						{/* Key Metrics Grid */}
						<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
							{/* Total Cost Card */}
							<MetricCard
								icon={DollarSign}
								iconBg="from-emerald-500 to-emerald-600"
								title={__('Total Cost', 'agentflow-ai')}
								value={formatCurrency(stats.totalCost)}
								change={stats.costChange}
								subtitle={__('vs previous period', 'agentflow-ai')}
							/>

							{/* Total Sessions Card */}
							<MetricCard
								icon={MessageSquare}
								iconBg="from-blue-500 to-blue-600"
								title={__('Total Sessions', 'agentflow-ai')}
								value={formatNumber(stats.totalSessions)}
								change={stats.sessionChange}
								subtitle={__('active conversations', 'agentflow-ai')}
							/>

							{/* Response Time Card */}
							<MetricCard
								icon={Zap}
								iconBg="from-amber-500 to-orange-500"
								title={__('Avg Response', 'agentflow-ai')}
								value={`${stats.avgResponse}ms`}
								indicator={stats.avgResponse < 2000 ? 'good' : stats.avgResponse < 5000 ? 'warning' : 'bad'}
								subtitle={stats.avgResponse < 2000 ? __('Excellent performance', 'agentflow-ai') : __('Needs optimization', 'agentflow-ai')}
							/>

							{/* Success Rate Card */}
							<MetricCard
								icon={Target}
								iconBg="from-violet-500 to-purple-600"
								title={__('Success Rate', 'agentflow-ai')}
								value={`${stats.successRate.toFixed(1)}%`}
								indicator={stats.successRate >= 95 ? 'good' : stats.successRate >= 80 ? 'warning' : 'bad'}
								subtitle={stats.successRate >= 95 ? __('Great reliability', 'agentflow-ai') : __('Room for improvement', 'agentflow-ai')}
							/>
						</div>

						{/* Secondary Stats */}
						<div className="grid grid-cols-1 md:grid-cols-3 gap-4">
							<div className="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200/60 dark:border-slate-700/60 shadow-sm p-5">
								<div className="flex items-center gap-3">
									<div className="w-10 h-10 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center">
										<Brain className="w-5 h-5 text-gray-600 dark:text-slate-400" />
									</div>
									<div>
										<p className="text-2xl font-bold text-gray-900 dark:text-white">{formatNumber(stats.totalTokens)}</p>
										<p className="text-xs text-gray-500 dark:text-slate-400">{__('Total Tokens Used', 'agentflow-ai')}</p>
									</div>
								</div>
							</div>

							<div className="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200/60 dark:border-slate-700/60 shadow-sm p-5">
								<div className="flex items-center gap-3">
									<div className="w-10 h-10 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center">
										<Activity className="w-5 h-5 text-gray-600 dark:text-slate-400" />
									</div>
									<div>
										<p className="text-2xl font-bold text-gray-900 dark:text-white">{formatNumber(stats.totalMessages)}</p>
										<p className="text-xs text-gray-500 dark:text-slate-400">{__('Total Messages', 'agentflow-ai')}</p>
									</div>
								</div>
							</div>

							<div className="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200/60 dark:border-slate-700/60 shadow-sm p-5">
								<div className="flex items-center gap-3">
									<div className="w-10 h-10 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center">
										<Sparkles className="w-5 h-5 text-gray-600 dark:text-slate-400" />
									</div>
									<div>
										<p className="text-2xl font-bold text-gray-900 dark:text-white">
											{stats.totalMessages > 0 ? (stats.totalCost / stats.totalMessages * 1000).toFixed(2) : '0.00'}¢
										</p>
										<p className="text-xs text-gray-500 dark:text-slate-400">{__('Cost per 1K Messages', 'agentflow-ai')}</p>
									</div>
								</div>
							</div>
						</div>

						{/* Charts Grid */}
						<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
							{/* Cost Breakdown */}
							<ChartCard
								title={__('Cost Breakdown', 'agentflow-ai')}
								subtitle={__('Token usage and costs by provider', 'agentflow-ai')}
								icon={PieChart}
							>
								<CostBreakdown period={period} />
							</ChartCard>

							{/* Usage Trends */}
							<ChartCard
								title={__('Usage Trends', 'agentflow-ai')}
								subtitle={__('Performance metrics over time', 'agentflow-ai')}
								icon={TrendingUp}
							>
								<UsageTrends period={period} />
							</ChartCard>
						</div>

						{/* Bottom Grid */}
						<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
							{/* Top Tools */}
							<ChartCard
								title={__('Top Tools', 'agentflow-ai')}
								subtitle={__('Most frequently used AI tools', 'agentflow-ai')}
								icon={Wrench}
							>
								<TopTools period={period} />
							</ChartCard>

							{/* Agent Performance */}
							<ChartCard
								title={__('Agent Performance', 'agentflow-ai')}
								subtitle={__('Compare agent efficiency', 'agentflow-ai')}
								icon={Bot}
							>
								<AgentPerformance period={period} />
							</ChartCard>
						</div>
					</div>
				)}

				{/* Costs Tab */}
				{activeTab === 'costs' && (
					<div className="space-y-6">
						{/* Cost Summary */}
						<div className="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-xl">
							<div className="flex items-center justify-between">
								<div>
									<p className="text-emerald-100 text-sm font-medium">{__('Total Spend This Period', 'agentflow-ai')}</p>
									<p className="text-4xl font-bold mt-1">{formatCurrency(stats.totalCost)}</p>
									<div className="flex items-center gap-2 mt-2">
										{stats.costChange !== 0 && (
											<span className={cn(
												'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium',
												stats.costChange > 0 ? 'bg-red-400/20 text-red-100' : 'bg-emerald-400/20 text-emerald-100'
											)}>
												{stats.costChange > 0 ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />}
												{Math.abs(stats.costChange).toFixed(1)}%
											</span>
										)}
										<span className="text-emerald-200 text-sm">{__('vs previous period', 'agentflow-ai')}</span>
									</div>
								</div>
								<div className="w-20 h-20 rounded-2xl bg-white/10 flex items-center justify-center">
									<DollarSign className="w-10 h-10 text-white/80" />
								</div>
							</div>
						</div>

						{/* Cost Charts */}
						<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
							<ChartCard
								title={__('Cost by Provider', 'agentflow-ai')}
								subtitle={__('See which providers cost the most', 'agentflow-ai')}
								icon={PieChart}
							>
								<CostBreakdown period={period} />
							</ChartCard>

							<ChartCard
								title={__('Cost Trend', 'agentflow-ai')}
								subtitle={__('Track spending over time', 'agentflow-ai')}
								icon={TrendingUp}
							>
								<UsageTrends period={period} />
							</ChartCard>
						</div>

						{/* Cost Optimization Tips */}
						<div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50 rounded-2xl p-6">
							<div className="flex items-start gap-4">
								<div className="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
									<Sparkles className="w-5 h-5 text-blue-600" />
								</div>
								<div>
									<h3 className="font-semibold text-blue-900 dark:text-blue-300">{__('Cost Optimization Tips', 'agentflow-ai')}</h3>
									<ul className="mt-2 space-y-1 text-sm text-blue-700 dark:text-blue-400">
										<li>• {__('Use caching for frequently asked questions', 'agentflow-ai')}</li>
										<li>• {__('Consider using smaller models for simple tasks', 'agentflow-ai')}</li>
										<li>• {__('Set token limits to prevent runaway costs', 'agentflow-ai')}</li>
										<li>• {__('Monitor and optimize high-cost agents', 'agentflow-ai')}</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				)}

				{/* Agents Tab */}
				{activeTab === 'agents' && (
					<div className="space-y-6">
						<ChartCard
							title={__('Agent Performance Comparison', 'agentflow-ai')}
							subtitle={__('Detailed metrics for each agent', 'agentflow-ai')}
							icon={Bot}
							fullWidth
						>
							<AgentPerformance period={period} />
						</ChartCard>
					</div>
				)}

				{/* Tools Tab */}
				{activeTab === 'tools' && (
					<div className="space-y-6">
						<ChartCard
							title={__('Tool Usage Analytics', 'agentflow-ai')}
							subtitle={__('Track which tools are most effective', 'agentflow-ai')}
							icon={Wrench}
							fullWidth
						>
							<TopTools period={period} />
						</ChartCard>
					</div>
				)}
			</main>
		</div>
	);
}

/**
 * Metric Card Component
 */
function MetricCard({ icon: Icon, iconBg, title, value, change, indicator, subtitle }) {
	const getIndicatorColor = () => {
		switch (indicator) {
			case 'good':
				return 'text-emerald-600';
			case 'warning':
				return 'text-amber-600';
			case 'bad':
				return 'text-red-500';
			default:
				return 'text-gray-500';
		}
	};

	return (
		<div className="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200/60 dark:border-slate-700/60 shadow-sm hover:shadow-md transition-shadow p-5">
			<div className="flex items-start justify-between">
				<div className={cn('w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-lg', iconBg)}>
					<Icon className="w-6 h-6 text-white" />
				</div>
				{change !== undefined && change !== 0 && (
					<span
						className={cn(
							'inline-flex items-center gap-1 text-xs font-medium',
							change > 0 ? 'text-emerald-600' : 'text-red-500'
						)}
					>
						{change > 0 ? <ArrowUpRight className="w-3 h-3" /> : <ArrowDownRight className="w-3 h-3" />}
						{Math.abs(change).toFixed(1)}%
					</span>
				)}
				{indicator && (
					<span className={cn('inline-flex items-center gap-1 text-xs font-medium', getIndicatorColor())}>
						{indicator === 'good' && <CheckCircle className="w-4 h-4" />}
						{indicator === 'warning' && <AlertTriangle className="w-4 h-4" />}
						{indicator === 'bad' && <XCircle className="w-4 h-4" />}
					</span>
				)}
			</div>
			<div className="mt-4">
				<p className="text-2xl font-bold text-gray-900 dark:text-white">{value}</p>
				<p className="text-sm font-medium text-gray-600 dark:text-slate-400 mt-0.5">{title}</p>
				{subtitle && <p className="text-xs text-gray-400 dark:text-slate-500 mt-1">{subtitle}</p>}
			</div>
		</div>
	);
}

/**
 * Chart Card Component
 */
function ChartCard({ title, subtitle, icon: Icon, children, fullWidth }) {
	return (
		<div className={cn('bg-white dark:bg-slate-800 rounded-2xl border border-gray-200/60 dark:border-slate-700/60 shadow-sm overflow-hidden', fullWidth && 'lg:col-span-2')}>
			<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
				<div className="flex items-center gap-3">
					{Icon && (
						<div className="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center">
							<Icon className="w-4 h-4 text-gray-600 dark:text-slate-400" />
						</div>
					)}
					<div>
						<h3 className="text-base font-semibold text-gray-900 dark:text-white">{title}</h3>
						{subtitle && <p className="text-sm text-gray-500 dark:text-slate-400 mt-0.5">{subtitle}</p>}
					</div>
				</div>
			</div>
			<div className="p-6">{children}</div>
		</div>
	);
}
