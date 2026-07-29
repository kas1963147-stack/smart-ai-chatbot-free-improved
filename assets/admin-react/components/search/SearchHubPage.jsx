/**
 * SearchHubPage — Independent AI Search Enhancement Hub
 *
 * Full-page admin panel for managing AI-powered site search.
 * Pulled out of Settings → Advanced into its own top-level tab.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import SearchSettings from './SearchSettings';
import SearchTestPanel from './SearchTestPanel';
import SearchAnalytics from './SearchAnalytics';
import SynonymManager from './SynonymManager';
import Loading from '../common/Loading';

const TABS = [
    { id: 'settings', label: 'Settings', icon: '' },
    { id: 'test', label: 'Test Search', icon: '' },
    { id: 'analytics', label: 'Analytics', icon: '' },
    { id: 'synonyms', label: 'Synonyms', icon: '' },
];

export default function SearchHubPage() {
    const [activeTab, setActiveTab] = useState('settings');
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    // Load settings on mount
    useEffect(() => {
        loadSettings();
    }, []);

    const loadSettings = async () => {
        setLoading(true);
        try {
            const res = await apiFetch({ path: '/smart-ai-chatbot/v1/settings', method: 'GET' });
            const s = res.settings || res || {};
            setSettings(s);
        } catch (err) {
            showNotice('error', err.message || 'Failed to load settings');
        } finally {
            setLoading(false);
        }
    };

    const updateSetting = useCallback((key, value) => {
        setSettings((prev) => ({ ...prev, [key]: value }));
    }, []);

    const saveSettings = async () => {
        setSaving(true);
        try {
            await apiFetch({
                path: '/smart-ai-chatbot/v1/settings',
                method: 'POST',
                data: { settings },
            });
            showNotice('success', 'Search settings saved successfully!');
        } catch (err) {
            showNotice('error', err.message || 'Failed to save settings');
        } finally {
            setSaving(false);
        }
    };

    const showNotice = (type, message) => {
        setNotice({ type, message });
        if (type === 'success') {
            setTimeout(() => setNotice(null), 4000);
        }
    };

    if (loading) {
        return <Loading message={__('Loading search settings…', 'smart-woo-chatbot')} fullPage />;
    }

    return (
        <div className="space-y-6">
            {/* Hero Header */}
            <div className="relative overflow-hidden rounded-2xl bg-white border border-gray-200 p-8 shadow-sm">
                <div className="relative z-10 flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-2xl"></span>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                    {__('AI Search Enhancement', 'smart-woo-chatbot')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {__('Supercharge your site search with AI-powered keyword expansion', 'smart-woo-chatbot')}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Status Badge */}
                        <span
                            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ${settings.search_enhancement_enabled
                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                : 'bg-red-50 text-red-700 ring-1 ring-red-200'
                                }`}
                        >
                            <span className={`h-2 w-2 rounded-full ${settings.search_enhancement_enabled ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
                            {settings.search_enhancement_enabled ? __('Active', 'smart-woo-chatbot') : __('Inactive', 'smart-woo-chatbot')}
                        </span>
                        <button
                            onClick={saveSettings}
                            disabled={saving}
                            className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            {saving ? __('Saving…', 'smart-woo-chatbot') : __('Save Settings', 'smart-woo-chatbot')}
                        </button>
                    </div>
                </div>
            </div>

            {/* Notice */}
            {notice && (
                <div
                    className={`flex items-center justify-between rounded-xl border px-4 py-3 text-sm ${notice.type === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300'
                        : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300'
                        }`}
                >
                    <span>{notice.message}</span>
                    <button onClick={() => setNotice(null)} className="ml-4 text-lg opacity-70 hover:opacity-100">×</button>
                </div>
            )}

            {/* Tab Navigation */}
            <div className="flex items-center gap-1 rounded-xl border border-border bg-card p-1.5 shadow-sm">
                {TABS.map((tab) => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        className={`inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all ${activeTab === tab.id
                            ? 'bg-primary text-primary-foreground shadow-md shadow-primary/20'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            }`}
                    >
                        <span className="text-base">{tab.icon}</span>
                        <span>{tab.label}</span>
                    </button>
                ))}
            </div>

            {/* Tab Content */}
            <div className="min-h-[400px]">
                {activeTab === 'settings' && (
                    <SearchSettings settings={settings} onChange={updateSetting} onSave={saveSettings} saving={saving} />
                )}
                {activeTab === 'test' && (
                    <SearchTestPanel enabled={!!settings.search_enhancement_enabled} />
                )}
                {activeTab === 'analytics' && (
                    <SearchAnalytics enabled={!!settings.search_enhancement_enabled} />
                )}
                {activeTab === 'synonyms' && (
                    <SynonymManager />
                )}
            </div>
        </div>
    );
}
