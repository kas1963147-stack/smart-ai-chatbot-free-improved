/**
 * SynonymManager — Custom synonym pairs for search enhancement
 *
 * Allows admins to define custom keyword synonyms that are always included
 * alongside AI-generated keywords.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Loading from '../common/Loading';

export default function SynonymManager() {
    const [synonyms, setSynonyms] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    // New synonym form
    const [newTerm, setNewTerm] = useState('');
    const [newSynonyms, setNewSynonyms] = useState('');

    useEffect(() => {
        loadSynonyms();
    }, []);

    const loadSynonyms = async () => {
        setLoading(true);
        try {
            const res = await apiFetch({
                path: '/smart-ai-chatbot/v1/search/synonyms',
                method: 'GET',
            });
            if (res.success) {
                setSynonyms(res.data || []);
            }
        } catch {
            // Synonyms endpoint might not exist yet — start with empty
            setSynonyms([]);
        } finally {
            setLoading(false);
        }
    };

    const saveSynonyms = async (data) => {
        setSaving(true);
        try {
            await apiFetch({
                path: '/smart-ai-chatbot/v1/search/synonyms',
                method: 'POST',
                data: { synonyms: data },
            });
            setNotice({ type: 'success', message: __('Synonyms saved!', 'smart-woo-chatbot') });
            setTimeout(() => setNotice(null), 3000);
        } catch (err) {
            setNotice({ type: 'error', message: err.message || 'Failed to save' });
        } finally {
            setSaving(false);
        }
    };

    const addSynonym = () => {
        if (!newTerm.trim() || !newSynonyms.trim()) return;

        const entry = {
            term: newTerm.trim().toLowerCase(),
            synonyms: newSynonyms
                .split(',')
                .map((s) => s.trim().toLowerCase())
                .filter(Boolean),
        };

        if (entry.synonyms.length === 0) return;

        const updated = [...synonyms, entry];
        setSynonyms(updated);
        saveSynonyms(updated);
        setNewTerm('');
        setNewSynonyms('');
    };

    const removeSynonym = (index) => {
        const updated = synonyms.filter((_, i) => i !== index);
        setSynonyms(updated);
        saveSynonyms(updated);
    };

    if (loading) {
        return <Loading message={__('Loading synonyms…', 'smart-woo-chatbot')} fullPage />;
    }

    return (
        <div className="space-y-6">
            {/* Notice */}
            {notice && (
                <div
                    className={`flex items-center justify-between rounded-xl border px-4 py-3 text-sm ${notice.type === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300'
                        : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300'
                        }`}
                >
                    <span>{notice.message}</span>
                    <button onClick={() => setNotice(null)} className="ml-4 opacity-70 hover:opacity-100">×</button>
                </div>
            )}

            {/* Add New Synonym */}
            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                        {__('Add Synonym Pair', 'smart-woo-chatbot')}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {__('Define custom synonyms that are always included when a user searches for the given term', 'smart-woo-chatbot')}
                    </p>
                </div>
                <div className="p-6">
                    <div className="flex items-end gap-3">
                        <div className="flex-1">
                            <label className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {__('Search Term', 'smart-woo-chatbot')}
                            </label>
                            <input
                                type="text"
                                value={newTerm}
                                onChange={(e) => setNewTerm(e.target.value)}
                                placeholder={__('e.g. laptop', 'smart-woo-chatbot')}
                                className="h-10 w-full rounded-lg border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>
                        <div className="flex items-center px-2 pb-2 text-slate-400">→</div>
                        <div className="flex-[2]">
                            <label className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {__('Synonyms (comma-separated)', 'smart-woo-chatbot')}
                            </label>
                            <input
                                type="text"
                                value={newSynonyms}
                                onChange={(e) => setNewSynonyms(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && addSynonym()}
                                placeholder={__('e.g. notebook, macbook, chromebook, ultrabook', 'smart-woo-chatbot')}
                                className="h-10 w-full rounded-lg border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>
                        <button
                            onClick={addSynonym}
                            disabled={!newTerm.trim() || !newSynonyms.trim() || saving}
                            className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:opacity-50"
                        >
                            <span>+</span> {__('Add', 'smart-woo-chatbot')}
                        </button>
                    </div>
                </div>
            </div>

            {/* Existing Synonyms */}
            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                            {__('Configured Synonyms', 'smart-woo-chatbot')}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {synonyms.length} {__('synonym pairs configured', 'smart-woo-chatbot')}
                        </p>
                    </div>
                    {saving && (
                        <span className="text-xs text-amber-600 dark:text-amber-400">{__('Saving…', 'smart-woo-chatbot')}</span>
                    )}
                </div>

                {synonyms.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                        <span className="text-3xl"></span>
                        <p className="mt-3 text-sm">
                            {__('No synonyms configured yet. Add your first synonym pair above.', 'smart-woo-chatbot')}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                        {synonyms.map((entry, idx) => (
                            <div
                                key={idx}
                                className="flex items-center justify-between px-6 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="inline-flex min-w-[80px] items-center justify-center rounded-lg bg-violet-100 px-3 py-1.5 text-sm font-semibold text-violet-800 dark:bg-violet-900/30 dark:text-violet-300">
                                        {entry.term}
                                    </span>
                                    <span className="text-slate-400">→</span>
                                    <div className="flex flex-wrap gap-1.5">
                                        {entry.synonyms.map((syn, sidx) => (
                                            <span
                                                key={sidx}
                                                className="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                            >
                                                {syn}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                                <button
                                    onClick={() => removeSynonym(idx)}
                                    className="ml-4 rounded-lg p-1.5 text-slate-400 transition hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                    title={__('Remove', 'smart-woo-chatbot')}
                                >
                                    
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Info */}
            <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                <div className="flex gap-3">
                    <span className="text-xl"></span>
                    <div className="text-sm text-blue-800 dark:text-blue-200">
                        <p className="font-semibold">{__('About Synonyms', 'smart-woo-chatbot')}</p>
                        <p className="mt-1 text-blue-700 dark:text-blue-300">
                            {__('Custom synonyms are always applied in addition to AI-generated keywords. They are instant (no AI call needed) and work even when the AI search is temporarily unavailable. Use them for domain-specific terms that the AI might not know about.', 'smart-woo-chatbot')}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
