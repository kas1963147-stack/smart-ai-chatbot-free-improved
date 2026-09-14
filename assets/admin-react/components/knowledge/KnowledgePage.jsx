/**
 * KnowledgePage Component - Metronic v9 Style
 *
 * Main container for knowledge base document management with modern UI.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache } from '../../hooks/useApiCache';
import useKnowledgeApi from '../../hooks/useKnowledgeApi';

import KnowledgeStats from './KnowledgeStats';
import KnowledgeCard from './KnowledgeCard';
import KnowledgeEditor from './KnowledgeEditor';
import Loading from '../common/Loading';
import { FileText, Plus } from 'lucide-react';

export default function KnowledgePage() {
    const [stats, setStats] = useState(null);
    const [knowledgeItems, setKnowledgeItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [editingKnowledge, setEditingKnowledge] = useState(null);
    const [showKnowledgeModal, setShowKnowledgeModal] = useState(false);

    const {
        fetchKnowledgeItems,
        getKnowledgeItem,
        createKnowledgeItem,
        updateKnowledgeItem,
        deleteKnowledgeItem,
    } = useKnowledgeApi();

    const fetchStats = useCallback(async () => {
        const cached = getCached('knowledge');
        if (cached?.stats) {
            setStats(cached.stats);
        }

        try {
            const response = await apiFetch({
                path: '/quark-agentflow-ai/v1/knowledge/stats',
            });
            if (response.success) {
                setStats(response.data);
                const currentCache = getCached('knowledge') || {};
                setCache('knowledge', { ...currentCache, stats: response.data });
            }
        } catch (err) {
            // Stats fetch failed silently - non-critical
        }
    }, []);

    const fetchKnowledge = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const items = await fetchKnowledgeItems();
            setKnowledgeItems(Array.isArray(items) ? items : []);
        } catch (err) {
            setError(err.message || __('Failed to load knowledge documents', 'agentflow-ai'));
        } finally {
            setLoading(false);
        }
    }, [fetchKnowledgeItems]);

    useEffect(() => {
        fetchStats();
        fetchKnowledge();
    }, [fetchStats, fetchKnowledge]);

    const handleSaveKnowledge = async (itemData) => {
        try {
            const isNew = !itemData.id;
            const response = isNew
                ? await createKnowledgeItem(itemData)
                : await updateKnowledgeItem(itemData.id, itemData);

            if (response?.success === false) {
                throw new Error(response?.message || __('Failed to save knowledge document', 'agentflow-ai'));
            }

            setShowKnowledgeModal(false);
            setEditingKnowledge(null);
            await fetchKnowledge();
        } catch (err) {
            setError(err.message || __('Failed to save knowledge document', 'agentflow-ai'));
            throw err; // Re-throw to be caught by the modal
        }
    };

    const handleEditClick = async (item) => {
        try {
            // Set a temporary loading state for the specific item if desired,
            // or just use the global loader briefly
            setLoading(true);
            const fullItem = await getKnowledgeItem(item.id);
            setEditingKnowledge(fullItem);
        } catch (err) {
            setError(err.message || __('Failed to load document details', 'agentflow-ai'));
        } finally {
            setLoading(false);
        }
    };

    const handleDeleteKnowledge = async (itemId) => {
        if (!confirm(__('Are you sure you want to delete this document?', 'agentflow-ai'))) {
            return;
        }
        try {
            const response = await deleteKnowledgeItem(itemId);
            if (response?.success === false) {
                throw new Error(response?.message || __('Failed to delete knowledge document', 'agentflow-ai'));
            }
            await fetchKnowledge();
        } catch (err) {
            setError(err.message || __('Failed to delete knowledge document', 'agentflow-ai'));
        }
    };

    if (loading && knowledgeItems.length === 0) {
        return (
            <div className="min-h-screen bg-gray-50/50 dark:bg-slate-900 p-6">
                <div className="grid grid-cols-4 gap-4 mb-6">
                    {[1, 2, 3, 4].map(i => (
                        <div key={i} className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 animate-pulse">
                            <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-20 mb-2"></div>
                            <div className="h-6 bg-gray-100 dark:bg-slate-600 rounded w-16"></div>
                        </div>
                    ))}
                </div>
                <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 animate-pulse">
                    <div className="space-y-4">
                        <div className="h-32 bg-gray-100 dark:bg-slate-700 rounded"></div>
                        <div className="h-32 bg-gray-100 dark:bg-slate-700 rounded"></div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50/50 dark:bg-slate-900">
            {/* Header / Action Bar */}
            <div className="flex items-center justify-between px-6 py-6 border-b border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <FileText className="w-6 h-6 text-primary" />
                        {__('Knowledge Documents', 'agentflow-ai')}
                        <span className="text-[8px] opacity-10 self-end mb-1">v1.12-pro</span>
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">
                        {__('Manage the knowledge base documents your agents use as reference.', 'agentflow-ai')}
                    </p>
                </div>
                <button
                    onClick={() => setShowKnowledgeModal(true)}
                    className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
                >
                    <Plus className="w-4 h-4" />
                    {__('Add Document', 'agentflow-ai')}
                </button>
            </div>

            {/* Error Alert */}
            {error && (
                <div className="mx-6 mt-6 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-400 border border-red-200 dark:border-red-800 flex items-center justify-between">
                    <span className="text-sm">{error}</span>
                    <button onClick={() => setError(null)} className="text-lg opacity-70 hover:opacity-100">
                        Ã—
                    </button>
                </div>
            )}

            {/* Content */}
            <main className="p-6">
                {/* Stats */}
                {stats && <KnowledgeStats stats={stats} />}

                {/* Document Grid */}
                <div className="mt-6">
                    {knowledgeItems.length === 0 ? (
                        <div className="text-center py-16 border-2 border-dashed border-gray-200 dark:border-slate-700 rounded-2xl bg-white dark:bg-slate-800/50">
                            <div className="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-4">
                                <FileText className="w-8 h-8 text-primary" />
                            </div>
                            <h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                                {__('No Documents Yet', 'agentflow-ai')}
                            </h3>
                            <p className="text-gray-500 dark:text-slate-400 max-w-sm mx-auto mb-6">
                                {__('Create documents from text, PDFs, or URLs to give your AI agents factual context.', 'agentflow-ai')}
                            </p>
                            <button
                                onClick={() => setShowKnowledgeModal(true)}
                                className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-xl text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                {__('Create First Document', 'agentflow-ai')}
                            </button>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            {knowledgeItems.map((item) => (
                                <KnowledgeCard
                                    key={item.id}
                                    item={item}
                                    onEdit={handleEditClick}
                                    onDelete={handleDeleteKnowledge}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </main>

            {/* Editor Modal */}
            {(showKnowledgeModal || editingKnowledge) && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center overflow-auto px-4 py-6">
                    <button
                        type="button"
                        className="absolute inset-0 bg-gray-900/50 dark:bg-slate-900/80 backdrop-blur-sm cursor-default"
                        aria-label="Close"
                        onClick={() => {
                            setShowKnowledgeModal(false);
                            setEditingKnowledge(null);
                        }}
                    />
                    <div className="relative z-10 w-full max-w-4xl mx-4">
                        <KnowledgeEditor
                            item={editingKnowledge}
                            onSave={handleSaveKnowledge}
                            onCancel={() => {
                                setShowKnowledgeModal(false);
                                setEditingKnowledge(null);
                            }}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
