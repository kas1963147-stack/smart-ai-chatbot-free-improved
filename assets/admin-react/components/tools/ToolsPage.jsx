/**
 * ToolsPage Component - Metronic v9 Style
 *
 * Management page for external tool API keys and configurations.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache, invalidateCache } from '../../hooks/useApiCache';
import Loading from '../common/Loading';

export default function ToolsPage() {
    const [registry, setRegistry] = useState(null);
    const [config, setConfig] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [expandedCategory, setExpandedCategory] = useState('search');
    const [googleStatus, setGoogleStatus] = useState(null);
    const [googleConnecting, setGoogleConnecting] = useState(false);

    // Check for Google OAuth callback params in URL
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('google_connected') === '1') {
            setSuccess(__('Google Calendar connected successfully!', 'agentflow-ai'));
            setExpandedCategory('google_workspace');
            // Clean URL
            window.history.replaceState({}, '', window.location.pathname + '?page=swc_chatbot&tab=tools');
        }
        if (params.get('google_error')) {
            setError(params.get('google_error'));
            setExpandedCategory('google_workspace');
            window.history.replaceState({}, '', window.location.pathname + '?page=swc_chatbot&tab=tools');
        }
    }, []);

    // Fetch Google OAuth status
    const fetchGoogleStatus = async () => {
        try {
            const status = await apiFetch({ path: '/smart-ai-chatbot/v1/tools/google/status' });
            setGoogleStatus(status);
        } catch (e) { /* ignore */ }
    };

    const handleGoogleConnect = async () => {
        setGoogleConnecting(true);
        try {
            const res = await apiFetch({ path: '/smart-ai-chatbot/v1/tools/google/connect', method: 'POST' });
            if (res.auth_url) {
                window.location.href = res.auth_url;
            } else if (res.error) {
                setError(res.error);
                setGoogleConnecting(false);
            }
        } catch (err) {
            setError(err.message || 'Failed to start Google connection');
            setGoogleConnecting(false);
        }
    };

    const handleGoogleDisconnect = async () => {
        try {
            await apiFetch({ path: '/smart-ai-chatbot/v1/tools/google/disconnect', method: 'POST' });
            setGoogleStatus({ configured: true, connected: false });
            setSuccess(__('Google account disconnected.', 'agentflow-ai'));
        } catch (err) {
            setError(err.message);
        }
    };

    // Category icons (use empty - icons handled by section headers)
    const categoryIcons = {
        search: '',
        scraping: '',
        database: '',
        ai: '',
        integration: '',
        google_workspace: '',
        productivity: '',
    };

    useEffect(() => {
        fetchData();
        fetchGoogleStatus();
    }, []);

    const fetchData = async () => {
        // Check cache first for instant display
        const cachedRegistry = getCached('tools');
        const cachedConfig = getCached('tools_config');

        if (cachedRegistry && cachedConfig) {
            setRegistry(cachedRegistry);
            setConfig(cachedConfig);
            setLoading(false);

            // Background refresh
            Promise.all([
                apiFetch({ path: '/smart-ai-chatbot/v1/tools/registry' }),
                apiFetch({ path: '/smart-ai-chatbot/v1/tools/config' }),
            ]).then(([registryData, configData]) => {
                setRegistry(registryData);
                setConfig(configData);
                setCache('tools', registryData);
                setCache('tools_config', configData);
            }).catch(() => { });
            return;
        }

        try {
            setLoading(true);
            const [registryData, configData] = await Promise.all([
                apiFetch({ path: '/smart-ai-chatbot/v1/tools/registry' }),
                apiFetch({ path: '/smart-ai-chatbot/v1/tools/config' }),
            ]);
            setRegistry(registryData);
            setConfig(configData);
            setCache('tools', registryData);
            setCache('tools_config', configData);
        } catch (err) {
            setError(err.message || 'Failed to load tool configurations');
        } finally {
            setLoading(false);
        }
    };

    const updateField = (toolId, fieldId, value) => {
        setConfig((prev) => ({
            ...prev,
            [toolId]: {
                ...prev[toolId],
                [fieldId]: value,
            },
        }));
    };

    const handleSave = async () => {
        try {
            setSaving(true);
            setError(null);
            setSuccess(null);

            const dataToSend = {};
            Object.entries(config).forEach(([toolId, fields]) => {
                const nonEmptyFields = {};
                Object.entries(fields).forEach(([fieldId, value]) => {
                    if (!fieldId.endsWith('_configured') && value) {
                        nonEmptyFields[fieldId] = value;
                    }
                });
                if (Object.keys(nonEmptyFields).length > 0) {
                    dataToSend[toolId] = nonEmptyFields;
                }
            });

            await apiFetch({
                path: '/smart-ai-chatbot/v1/tools/config',
                method: 'POST',
                data: dataToSend,
            });

            setSuccess(__('Settings saved successfully!', 'agentflow-ai'));
            setTimeout(() => fetchData(), 1000);
        } catch (err) {
            setError(err.message || 'Failed to save settings');
        } finally {
            setSaving(false);
        }
    };

    // Loading state
    if (loading) {
        return <Loading message={__('Loading tools…', 'agentflow-ai')} fullPage />;
    }

    return (
        <div className="min-h-screen bg-gray-50/50 dark:bg-gray-900">
            {/* Page Header */}
			{/* Action Bar */}
			<div className="flex items-center justify-end px-6 py-3">
				<button
					onClick={handleSave}
					disabled={saving}
					className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors disabled:opacity-50"
				>
					{saving ? __('Saving...', 'agentflow-ai') : __('Save All', 'agentflow-ai')}
				</button>
			</div>

            {/* Notices */}
            {error && (
                <div className="mx-6 mt-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800 flex items-center justify-between">
                    <span className="text-sm">{error}</span>
                    <button onClick={() => setError(null)} className="text-lg opacity-70 hover:opacity-100">×</button>
                </div>
            )}
            {success && (
                <div className="mx-6 mt-4 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800 flex items-center justify-between">
                    <span className="text-sm">{success}</span>
                    <button onClick={() => setSuccess(null)} className="text-lg opacity-70 hover:opacity-100">×</button>
                </div>
            )}

            {/* Content */}
            <main className="p-6">
                {/* Tool Categories */}
                <div className="space-y-4">
                    {registry &&
                        Object.entries(registry).map(([categoryId, category]) => (
                            <div key={categoryId} className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                                {/* Category Header */}
                                <button
                                    onClick={() => setExpandedCategory(expandedCategory === categoryId ? null : categoryId)}
                                    className="w-full flex items-center gap-4 px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <span className="flex-1 text-base font-semibold text-gray-900 dark:text-white">{category.label}</span>
                                    <span className="text-sm text-gray-500 dark:text-gray-400">{Object.keys(category.tools).length} tools</span>
                                    <span className="text-gray-400 dark:text-gray-500 text-lg">{expandedCategory === categoryId ? '−' : '+'}</span>
                                </button>

                                {/* Tools List */}
                                {expandedCategory === categoryId && (
                                    <div className="border-t border-gray-100 dark:border-gray-700 p-5 space-y-4">
                                        {/* Google OAuth Connect Banner */}
                                        {categoryId === 'google_workspace' && (
                                            <div className="bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-4 mb-4">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <h4 className="text-sm font-semibold text-blue-900 dark:text-blue-200">
                                                            Google Account Connection
                                                        </h4>
                                                        <p className="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                                            {googleStatus?.connected
                                                                ? 'Connected - Your Google Calendar is linked and ready to use.'
                                                                : 'Enter your Client ID and Client Secret below, save, then connect your Google account.'}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        {googleStatus?.connected ? (
                                                            <button
                                                                onClick={handleGoogleDisconnect}
                                                                className="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg text-red-700 bg-red-100 dark:bg-red-900/30 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"
                                                            >
                                                                {__('Disconnect', 'agentflow-ai')}
                                                            </button>
                                                        ) : (
                                                            <button
                                                                onClick={handleGoogleConnect}
                                                                disabled={googleConnecting}
                                                                className="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                                            >
                                                                {googleConnecting ? __('Connecting...', 'agentflow-ai') : __('Connect Google Account', 'agentflow-ai')}
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                                {googleStatus?.connected && googleStatus?.connected_at && (
                                                    <p className="text-xs text-blue-600 dark:text-blue-400 mt-2">
                                                        Connected on: {new Date(googleStatus.connected_at * 1000).toLocaleDateString()}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                        {Object.entries(category.tools).map(([toolId, tool]) => {
                                            const isConfigured = config[toolId] &&
                                                Object.keys(tool.fields).every((f) => config[toolId][f + '_configured']);

                                            return (
                                                <div key={toolId} className="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
                                                    {/* Tool Header */}
                                                    <div className="flex items-center gap-3 mb-4">
                                                        <span className="text-base font-medium text-gray-900 dark:text-white flex-1">{tool.name}</span>
                                                        {isConfigured && (
                                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300">
                                                                {__('Configured', 'agentflow-ai')}
                                                            </span>
                                                        )}
                                                        {tool.docs_url && (
                                                            <a
                                                                href={tool.docs_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-xs text-primary hover:underline"
                                                            >
                                                                {__('Docs', 'agentflow-ai')}
                                                            </a>
                                                        )}
                                                    </div>

                                                    {/* Tool Fields */}
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        {Object.entries(tool.fields).map(([fieldId, field]) => (
                                                            <div key={fieldId}>
                                                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                                    {field.label}
                                                                </label>
                                                                <input
                                                                    type={field.type === 'password' ? 'password' : 'text'}
                                                                    value={config[toolId]?.[fieldId] || ''}
                                                                    onChange={(e) => updateField(toolId, fieldId, e.target.value)}
                                                                    placeholder={field.type === 'password' ? '••••••••' : ''}
                                                                    className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
                                                                />
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        ))}
                </div>

                {/* Info Box */}
                <div className="mt-6 bg-blue-50 dark:bg-blue-900/30 rounded-xl border border-blue-200 dark:border-blue-800 p-4">
                    <p className="text-sm text-blue-800 dark:text-blue-300">
                        <strong>Tip:</strong>{' '}
                        {__('Some tools (like YouTube, ArXiv) have FREE modes that work without API keys. API keys are optional for enhanced functionality.', 'agentflow-ai')}
                    </p>
                </div>
            </main>
        </div>
    );
}
