/**
 * SearchAnalytics — Search analytics dashboard
 *
 * Shows cache stats, recent search activity, and performance metrics.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Loading from '../common/Loading';

export default function SearchAnalytics({ enabled }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [clearing, setClearing] = useState(false);

    useEffect(() => {
        loadStats();
    }, []);

    const loadStats = async () => {
        setLoading(true);
        try {
            const res = await apiFetch({
                path: '/smart-ai-chatbot/v1/search/stats',
                method: 'GET',
            });
            if (res.success) {
                setStats(res.data);
            }
        } catch (err) {
            // Stats endpoint might not exist yet — show defaults
            setStats({
                total_cached: 0,
                cache_size_bytes: 0,
                enhancement_active: enabled,
            });
        } finally {
            setLoading(false);
        }
    };

    const clearAllCache = async () => {
        setClearing(true);
        try {
            await apiFetch({
                path: '/smart-ai-chatbot/v1/search/clear-cache',
                method: 'POST',
                data: {},
            });
            await loadStats();
        } catch (err) {
            // ignore
        } finally {
            setClearing(false);
        }
    };

    if (loading) {
        return <Loading message={__('Loading analytics…', 'smart-woo-chatbot')} fullPage />;
    }

    if (!enabled) {
        return (
            <div className="flex flex-col items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 py-16 dark:border-amber-800 dark:bg-amber-900/20">
                <span className="text-4xl"></span>
                <h3 className="mt-4 text-lg font-semibold text-amber-800 dark:text-amber-200">
                    {__('Analytics Unavailable', 'smart-woo-chatbot')}
                </h3>
                <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">
                    {__('Enable AI Search to start collecting analytics data.', 'smart-woo-chatbot')}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Stats Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    {
                        label: __('Cached Terms', 'smart-woo-chatbot'),
                        value: stats?.total_cached || 0,
                        icon: '',
                        color: 'from-violet-500 to-purple-600',
                        bgLight: 'bg-violet-50 dark:bg-violet-900/20',
                    },
                    {
                        label: __('Status', 'smart-woo-chatbot'),
                        value: enabled ? __('Active', 'smart-woo-chatbot') : __('Inactive', 'smart-woo-chatbot'),
                        icon: enabled ? '' : '',
                        color: enabled ? 'from-emerald-500 to-teal-600' : 'from-red-500 to-rose-600',
                        bgLight: enabled ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20',
                    },
                    {
                        label: __('Search Agent', 'smart-woo-chatbot'),
                        value: stats?.agent_status || __('Ready', 'smart-woo-chatbot'),
                        icon: '',
                        color: 'from-blue-500 to-indigo-600',
                        bgLight: 'bg-blue-50 dark:bg-blue-900/20',
                    },
                    {
                        label: __('Cache Size', 'smart-woo-chatbot'),
                        value: stats?.cache_size_bytes ? `${Math.round(stats.cache_size_bytes / 1024)} KB` : '0 KB',
                        icon: '',
                        color: 'from-amber-500 to-orange-600',
                        bgLight: 'bg-amber-50 dark:bg-amber-900/20',
                    },
                ].map((card, idx) => (
                    <div
                        key={idx}
                        className={`rounded-2xl border border-slate-200 ${card.bgLight} p-5 shadow-sm dark:border-slate-800`}
                    >
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{card.label}</p>
                                <p className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{card.value}</p>
                            </div>
                            <span className="text-2xl">{card.icon}</span>
                        </div>
                    </div>
                ))}
            </div>

            {/* Cache Management */}
            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                            {__('Cache Management', 'smart-woo-chatbot')}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {__('Manage the keyword cache to keep search results fresh', 'smart-woo-chatbot')}
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <button
                            onClick={loadStats}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                             {__('Refresh', 'smart-woo-chatbot')}
                        </button>
                        <button
                            onClick={clearAllCache}
                            disabled={clearing}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-red-100 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-200 disabled:opacity-50 dark:bg-red-900/30 dark:text-red-400"
                        >
                             {clearing ? __('Clearing…', 'smart-woo-chatbot') : __('Clear All Cache', 'smart-woo-chatbot')}
                        </button>
                    </div>
                </div>
                <div className="p-6">
                    <div className="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/50">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{__('Total Cached Terms', 'smart-woo-chatbot')}</p>
                                <p className="mt-1 text-xl font-bold text-slate-900 dark:text-white">{stats?.total_cached || 0}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{__('Cache Duration', 'smart-woo-chatbot')}</p>
                                <p className="mt-1 text-xl font-bold text-slate-900 dark:text-white">
                                    {stats?.cache_duration ? `${Math.round(stats.cache_duration / 60)} min` : '60 min'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{__('Min Query Length', 'smart-woo-chatbot')}</p>
                                <p className="mt-1 text-xl font-bold text-slate-900 dark:text-white">
                                    {stats?.min_query_length || 2} {__('chars', 'smart-woo-chatbot')}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* How It Works */}
            <div className="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-800 dark:bg-violet-900/20">
                <div className="flex gap-3">
                    <span className="text-xl"></span>
                    <div className="text-sm text-violet-800 dark:text-violet-200">
                        <p className="mb-1 font-semibold">{__('Search Flow', 'smart-woo-chatbot')}</p>
                        <p className="text-violet-700 dark:text-violet-300">
                            {__('User searches → Check cache → If miss, call Search Agent → AI generates keywords → Keywords added to SQL query → Enhanced results returned → Cache keywords for next time', 'smart-woo-chatbot')}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
