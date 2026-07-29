/**
 * SearchTestPanel — Live test interface for AI Search Enhancement
 *
 * Allows admins to test search queries and see AI-generated keywords in real-time.
 * Uses the existing SearchEnhancer::test() backend method.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function SearchTestPanel({ enabled }) {
    const [query, setQuery] = useState('');
    const [result, setResult] = useState(null);
    const [testing, setTesting] = useState(false);
    const [history, setHistory] = useState([]);
    const [clearing, setClearing] = useState(false);

    const runTest = async () => {
        if (!query.trim()) return;
        setTesting(true);
        setResult(null);
        try {
            const res = await apiFetch({
                path: '/smart-ai-chatbot/v1/search/test',
                method: 'POST',
                data: { search_term: query.trim() },
            });
            if (res.success) {
                setResult(res.data);
                setHistory((prev) => [
                    { query: query.trim(), result: res.data, timestamp: new Date() },
                    ...prev.slice(0, 9),
                ]);
            } else {
                setResult({ error: res.message || 'Test failed' });
            }
        } catch (err) {
            setResult({ error: err.message || 'Failed to test search' });
        } finally {
            setTesting(false);
        }
    };

    const clearCache = async (term) => {
        setClearing(true);
        try {
            await apiFetch({
                path: '/smart-ai-chatbot/v1/search/clear-cache',
                method: 'POST',
                data: term ? { search_term: term } : {},
            });
        } catch (err) {
            // ignore
        } finally {
            setClearing(false);
        }
    };

    if (!enabled) {
        return (
            <div className="flex flex-col items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 py-16 dark:border-amber-800 dark:bg-amber-900/20">
                <span className="text-4xl"></span>
                <h3 className="mt-4 text-lg font-semibold text-amber-800 dark:text-amber-200">
                    {__('AI Search is Disabled', 'smart-woo-chatbot')}
                </h3>
                <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">
                    {__('Enable AI-Powered Search in the Settings tab to test it here.', 'smart-woo-chatbot')}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Test Input */}
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                    {__('Test Search Enhancement', 'smart-woo-chatbot')}
                </h3>
                <div className="flex gap-3">
                    <div className="relative flex-1">
                        <input
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && runTest()}
                            placeholder={__('Type a search query to test… e.g. "laptop"', 'smart-woo-chatbot')}
                            className="h-12 w-full rounded-xl border border-slate-300 bg-white pl-4 pr-4 text-sm text-slate-900 transition-all focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-200 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                    <button
                        onClick={runTest}
                        disabled={testing || !query.trim()}
                        className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-purple-600 px-6 py-3 text-sm font-semibold text-white shadow-md shadow-violet-200 transition hover:from-violet-700 hover:to-purple-700 disabled:opacity-50 dark:shadow-violet-900/30"
                    >
                        {testing ? (
                            <>
                                <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                {__('Testing…', 'smart-woo-chatbot')}
                            </>
                        ) : (
                            <>
                                {__('Test', 'smart-woo-chatbot')}
                            </>
                        )}
                    </button>
                </div>
            </div>

            {/* Results */}
            {result && (
                <div className={`rounded-2xl border shadow-sm ${result.error ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' : 'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-slate-900'}`}>
                    <div className="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className={`inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold text-white ${result.error ? 'bg-red-500' : 'bg-emerald-500'}`}>
                                    {result.error ? '' : ''}
                                </span>
                                <span className={`font-semibold ${result.error ? 'text-red-800 dark:text-red-300' : 'text-emerald-800 dark:text-emerald-300'}`}>
                                    {result.error ? __('Test Failed', 'smart-woo-chatbot') : __('Test Successful', 'smart-woo-chatbot')}
                                </span>
                            </div>
                            {!result.error && (
                                <div className="flex items-center gap-4 text-xs text-slate-500">
                                    <span className="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2.5 py-1 font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                         {result.duration_ms}ms
                                    </span>
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                         {result.keyword_count} {__('keywords', 'smart-woo-chatbot')}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="p-6">
                        {result.error ? (
                            <p className="text-sm text-red-700 dark:text-red-300">{result.error}</p>
                        ) : (
                            <div className="space-y-4">
                                {/* Original Query */}
                                <div>
                                    <div className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        {__('Original Search Term', 'smart-woo-chatbot')}
                                    </div>
                                    <div className="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-900 dark:bg-slate-800 dark:text-white">
                                         {result.search_term}
                                    </div>
                                </div>

                                {/* Generated Keywords */}
                                <div>
                                    <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        {__('AI-Generated Keywords', 'smart-woo-chatbot')}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {(result.keywords || []).map((kw, idx) => (
                                            <span
                                                key={idx}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-800 dark:border-violet-700 dark:bg-violet-900/20 dark:text-violet-300"
                                            >
                                                <span className="text-xs text-violet-400">{idx + 1}.</span>
                                                {kw}
                                            </span>
                                        ))}
                                        {(!result.keywords || result.keywords.length === 0) && (
                                            <span className="text-sm text-slate-400">{__('No keywords generated', 'smart-woo-chatbot')}</span>
                                        )}
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                                    <button
                                        onClick={() => {
                                            const text = (result.keywords || []).join(', ');
                                            navigator.clipboard.writeText(text);
                                        }}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                    >
                                         {__('Copy Keywords', 'smart-woo-chatbot')}
                                    </button>
                                    <button
                                        onClick={() => clearCache(result.search_term)}
                                        disabled={clearing}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-200 disabled:opacity-50 dark:bg-amber-900/30 dark:text-amber-400"
                                    >
                                         {clearing ? __('Clearing…', 'smart-woo-chatbot') : __('Clear Cache for This Term', 'smart-woo-chatbot')}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Test History */}
            {history.length > 0 && (
                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 className="text-base font-semibold text-slate-900 dark:text-white">
                            {__('Recent Tests', 'smart-woo-chatbot')}
                        </h3>
                        <button
                            onClick={() => setHistory([])}
                            className="text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300"
                        >
                            {__('Clear History', 'smart-woo-chatbot')}
                        </button>
                    </div>
                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                        {history.map((item, idx) => (
                            <div
                                key={idx}
                                className="flex items-center justify-between px-6 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="text-sm font-medium text-slate-900 dark:text-white">"{item.query}"</span>
                                    <span className="text-xs text-slate-400">→</span>
                                    <span className="text-xs text-slate-500">
                                        {item.result.keyword_count} {__('keywords', 'smart-woo-chatbot')} · {item.result.duration_ms}ms
                                    </span>
                                </div>
                                <button
                                    onClick={() => {
                                        setQuery(item.query);
                                        setResult(item.result);
                                    }}
                                    className="text-xs text-violet-600 hover:text-violet-800 dark:text-violet-400"
                                >
                                    {__('Rerun', 'smart-woo-chatbot')}
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
