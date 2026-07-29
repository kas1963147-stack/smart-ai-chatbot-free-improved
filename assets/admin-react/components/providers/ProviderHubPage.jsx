import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import AI_PROVIDERS from '../../constants/providers';

const PROVIDER_LIST = AI_PROVIDERS;

const cleanConfigurationLabel = (value = '') => String(value || '').replace(' (Global Settings)', '').trim();

const normalizeProviderTestValue = (value, fallback = '') => {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'bigint') {
        return String(value);
    }

    if (value instanceof Error) {
        return value.message || fallback;
    }

    if (typeof value === 'object') {
        try {
            return JSON.stringify(value, null, 2);
        } catch (error) {
            return Object.prototype.toString.call(value);
        }
    }

    return String(value);
};

const normalizeProviderModelValue = (value, fallback = '') => {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    if (Array.isArray(value)) {
        const values = value
            .map((item) => normalizeProviderModelValue(item, ''))
            .filter(Boolean);

        return values.length ? values.join(', ') : fallback;
    }

    if (typeof value === 'object') {
        const preferredKeys = ['id', 'model', 'name', 'label', 'value'];

        for (const key of preferredKeys) {
            const candidate = value?.[key];

            if (typeof candidate === 'string' || typeof candidate === 'number' || typeof candidate === 'boolean' || typeof candidate === 'bigint') {
                return String(candidate);
            }
        }
    }

    return normalizeProviderTestValue(value, fallback);
};

const formatProviderTestTimestamp = (value = new Date()) => {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(date).replace(',', '');
};

const buildProviderTestResult = ({ result, error, context = {} }) => {
    const payload = result || {};
    const success = typeof payload.success === 'boolean' ? payload.success : !error;

    return {
        id: context.id || payload.id || 'new',
        success,
        message: normalizeProviderTestValue(error?.message ?? payload.message, ''),
        response: normalizeProviderTestValue(payload.response, ''),
        model: normalizeProviderModelValue(payload.model || payload.model_id || context.model, ''),
        configuration: normalizeProviderTestValue(context.configuration || payload.display_name, ''),
        providerLabel: normalizeProviderTestValue(context.providerLabel, ''),
        apiGroupName: normalizeProviderTestValue(context.apiGroupName, ''),
        apiKeyMasked: normalizeProviderTestValue(context.apiKeyMasked, ''),
        testedAt: formatProviderTestTimestamp(context.testedAt || new Date()),
    };
};

const getProviderMeta = (id) => PROVIDER_LIST.find((provider) => provider.id === id) || null;

const getProviderLabel = (id) => {
    const provider = getProviderMeta(id);
    return provider?.label?.split(' (')[0] || id.charAt(0).toUpperCase() + id.slice(1);
};

const getProviderFullLabel = (id) => getProviderMeta(id)?.label || id;

const sortByName = (items) => (
    [...items].sort((a, b) => {
        const aName = a.display_name || a.model || '';
        const bName = b.display_name || b.model || '';
        return aName.localeCompare(bName);
    })
);

export default function ProviderHubPage({ onEditProvider, onAddNew }) {
    const [instances, setInstances] = useState([]);
    const [loading, setLoading] = useState(true);
    const [testResults, setTestResults] = useState({});
    const [testing, setTesting] = useState(null);
    const [notice, setNotice] = useState(null);
    const [pricingOpen, setPricingOpen] = useState(null);
    const [pricingData, setPricingData] = useState({});
    const [pricingEdits, setPricingEdits] = useState({});
    const [pricingSaving, setPricingSaving] = useState(false);

    const loadInstances = useCallback(async () => {
        setLoading(true);
        try {
            const res = await apiFetch({ path: '/smart-ai-chatbot/v1/provider-instances' });
            setInstances(res?.data || []);
        } catch (err) {
            setNotice({ type: 'error', message: err.message });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        loadInstances();
    }, [loadInstances]);

    useEffect(() => {
        if (!notice) {
            return undefined;
        }

        const timer = setTimeout(() => setNotice(null), 5000);
        return () => clearTimeout(timer);
    }, [notice]);

    const groupedProviders = useMemo(() => {
        const providers = {};

        instances.forEach((instance, index) => {
            const providerId = instance.provider || 'unknown';
            const groupId = instance.api_group_id || `group-${providerId}-${instance.id || index}`;

            if (!providers[providerId]) {
                providers[providerId] = {
                    id: providerId,
                    providerLabel: getProviderFullLabel(providerId),
                    groups: {},
                };
            }

            if (!providers[providerId].groups[groupId]) {
                providers[providerId].groups[groupId] = {
                    id: groupId,
                    providerId,
                    providerLabel: getProviderFullLabel(providerId),
                    name: instance.api_group_name || `${getProviderLabel(providerId)} API`,
                    apiKeyMasked: instance.api_key_masked || '',
                    baseUrl: instance.base_url || '',
                    sourceInstance: instance,
                    models: [],
                };
            }

            const group = providers[providerId].groups[groupId];
            group.models.push(instance);

            if (!group.apiKeyMasked && instance.api_key_masked) {
                group.apiKeyMasked = instance.api_key_masked;
            }

            if (!group.baseUrl && instance.base_url) {
                group.baseUrl = instance.base_url;
            }

            if (instance.is_default) {
                group.sourceInstance = instance;
            }
        });

        return Object.values(providers)
            .map((provider) => ({
                ...provider,
                groups: Object.values(provider.groups)
                    .map((group, index) => ({
                        ...group,
                        fallbackName: `${getProviderLabel(provider.id)} API ${index + 1}`,
                        models: sortByName(group.models),
                    }))
                    .sort((a, b) => {
                        const aDefault = a.models.some((item) => item.is_default);
                        const bDefault = b.models.some((item) => item.is_default);

                        if (aDefault !== bDefault) {
                            return aDefault ? -1 : 1;
                        }

                        return (a.name || '').localeCompare(b.name || '');
                    }),
                modelCount: Object.values(provider.groups).reduce((total, group) => total + group.models.length, 0),
            }))
            .sort((a, b) => a.providerLabel.localeCompare(b.providerLabel));
    }, [instances]);

    const handleDelete = async (id) => {
        if (!window.confirm(__('Delete this provider configuration? Agents using it will fall back to default.', 'smartwoo-chatbot'))) {
            return;
        }

        try {
            await apiFetch({
                path: '/smart-ai-chatbot/v1/provider-instances/delete',
                method: 'POST',
                data: { id },
            });
            setNotice({ type: 'success', message: __('Configuration deleted', 'smartwoo-chatbot') });
            loadInstances();
        } catch (err) {
            setNotice({ type: 'error', message: err.message });
        }
    };

    const handleTest = async (instance, group) => {
        const id = instance?.id || 'new';

        setTesting(id);
        setTestResults((prev) => ({
            ...prev,
            [group.id]: null,
        }));

        try {
            const res = await apiFetch({
                path: '/smart-ai-chatbot/v1/provider-instances/test',
                method: 'POST',
                data: {
                    provider: instance?.provider,
                    model: instance?.model,
                    base_url: instance?.base_url,
                    instance_id: instance?.id || '',
                },
            });

            setTestResults((prev) => ({
                ...prev,
                [group.id]: buildProviderTestResult({
                    result: res?.data,
                    context: {
                        id,
                        configuration: cleanConfigurationLabel(instance?.display_name || ''),
                        providerLabel: getProviderFullLabel(instance?.provider || group?.providerId || ''),
                        model: instance?.model || '',
                        apiGroupName: group?.name || '',
                        apiKeyMasked: group?.apiKeyMasked || '',
                        testedAt: new Date(),
                    },
                }),
            }));
        } catch (err) {
            setTestResults((prev) => ({
                ...prev,
                [group.id]: buildProviderTestResult({
                    error: err,
                    context: {
                        id,
                        configuration: cleanConfigurationLabel(instance?.display_name || ''),
                        providerLabel: getProviderFullLabel(instance?.provider || group?.providerId || ''),
                        model: instance?.model || '',
                        apiGroupName: group?.name || '',
                        apiKeyMasked: group?.apiKeyMasked || '',
                        testedAt: new Date(),
                    },
                }),
            }));
        } finally {
            setTesting(null);
        }
    };

    const handleEditModel = (instance) => {
        if (onEditProvider) {
            onEditProvider(instance);
        }
    };

    const handleEditApiGroup = (group) => {
        if (onEditProvider) {
            onEditProvider({
                ...group.sourceInstance,
                api_group_id: group.id,
                api_group_name: group.name,
                group_model_count: group.models.length,
                edit_api_group: true,
            });
        }
    };

    const handleAddModel = (group) => {
        if (onAddNew) {
            onAddNew({
                ...group.sourceInstance,
                api_group_id: group.id,
                api_group_name: group.name,
                group_model_count: group.models.length,
            });
        }
    };

    const handleAddApiGroup = (providerId) => {
        if (onAddNew) {
            onAddNew({
                provider: providerId,
                create_new_group: true,
            });
        }
    };

    const togglePricing = async (instance) => {
        const key = instance.id;

        if (pricingOpen === key) {
            setPricingOpen(null);
            return;
        }

        setPricingOpen(key);

        if (pricingData[key]) {
            return;
        }

        try {
            const res = await apiFetch({ path: '/smart-ai-chatbot/v1/pricing', method: 'GET' });

            if (!res.success) {
                throw new Error(__('Failed to load pricing', 'smartwoo-chatbot'));
            }

            const match = (res.data || []).find((item) => item.provider === instance.provider && item.model === instance.model);
            const data = match || {
                default_input: null,
                default_output: null,
                default_cache_write: null,
                default_cache_read: null,
                custom_input: null,
                custom_output: null,
                custom_cache_write: null,
                custom_cache_read: null,
                has_custom: false,
            };

            setPricingData((prev) => ({ ...prev, [key]: data }));
            setPricingEdits((prev) => ({
                ...prev,
                [key]: {
                    input_price: data.custom_input !== null ? data.custom_input : (data.default_input ?? ''),
                    output_price: data.custom_output !== null ? data.custom_output : (data.default_output ?? ''),
                    cache_write_price: data.custom_cache_write !== null ? data.custom_cache_write : (data.default_cache_write ?? ''),
                    cache_read_price: data.custom_cache_read !== null ? data.custom_cache_read : (data.default_cache_read ?? ''),
                },
            }));
        } catch (err) {
            setNotice({ type: 'error', message: err.message || __('Failed to load pricing', 'smartwoo-chatbot') });
        }
    };

    const savePricing = async (instance) => {
        const key = instance.id;
        const edits = pricingEdits[key];

        if (!edits) {
            return;
        }

        setPricingSaving(true);

        try {
            const res = await apiFetch({
                path: '/smart-ai-chatbot/v1/pricing',
                method: 'POST',
                data: {
                    provider: instance.provider,
                    model: instance.model,
                    input_price: edits.input_price !== '' ? parseFloat(edits.input_price) : null,
                    output_price: edits.output_price !== '' ? parseFloat(edits.output_price) : null,
                    cache_write_price: edits.cache_write_price !== '' ? parseFloat(edits.cache_write_price) : null,
                    cache_read_price: edits.cache_read_price !== '' ? parseFloat(edits.cache_read_price) : null,
                },
            });

            if (res.success) {
                setNotice({ type: 'success', message: __('Pricing saved', 'smartwoo-chatbot') });
                setPricingData((prev) => ({
                    ...prev,
                    [key]: {
                        ...prev[key],
                        has_custom: true,
                        custom_input: edits.input_price,
                        custom_output: edits.output_price,
                        custom_cache_write: edits.cache_write_price,
                        custom_cache_read: edits.cache_read_price,
                    },
                }));
            }
        } catch (err) {
            setNotice({ type: 'error', message: err.message || __('Failed to save pricing', 'smartwoo-chatbot') });
        } finally {
            setPricingSaving(false);
        }
    };

    const resetPricing = async (instance) => {
        if (!window.confirm(__('Reset to default pricing for this model?', 'smartwoo-chatbot'))) {
            return;
        }

        const key = instance.id;

        try {
            await apiFetch({
                path: `/smart-ai-chatbot/v1/pricing/${instance.provider}/${instance.model}`,
                method: 'DELETE',
            });

            setNotice({ type: 'success', message: __('Reset to default pricing', 'smartwoo-chatbot') });
            setPricingData((prev) => {
                const next = { ...prev };
                delete next[key];
                return next;
            });
            setPricingOpen(null);
        } catch (err) {
            setNotice({ type: 'error', message: err.message || __('Failed to reset', 'smartwoo-chatbot') });
        }
    };

    const dismissTestResult = (groupId) => {
        setTestResults((prev) => {
            const next = { ...prev };
            delete next[groupId];
            return next;
        });
    };

    const renderTestResultPanel = (group) => {
        const activeTestingInstance = group.models.find((instance) => testing === instance.id);
        const result = testResults[group.id];

        if (!activeTestingInstance && !result) {
            return null;
        }

        const activeInstance = activeTestingInstance
            || group.models.find((instance) => instance.id === result?.id)
            || group.sourceInstance
            || group.models[0];

        const isLoading = !!activeTestingInstance && !result;
        const isSuccess = !!result?.success;
        const statusLabel = isLoading
            ? __('Testing...', 'smartwoo-chatbot')
            : isSuccess
                ? __('Connection successful', 'smartwoo-chatbot')
                : __('Connection failed', 'smartwoo-chatbot');

        const statusClasses = isLoading
            ? 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200'
            : isSuccess
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200'
                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200';

        const iconClasses = isLoading
            ? 'border-blue-500 border-t-transparent'
            : isSuccess
                ? 'text-emerald-600 dark:text-emerald-300'
                : 'text-red-600 dark:text-red-300';

        const configurationValue = result?.configuration
            || cleanConfigurationLabel(activeInstance?.display_name || '')
            || __('Unnamed configuration', 'smartwoo-chatbot');
        const modelValue = result?.model || activeInstance?.model || __('Not set', 'smartwoo-chatbot');
        const apiGroupValue = result?.apiKeyMasked || group.apiKeyMasked || group.name || __('Configured endpoint', 'smartwoo-chatbot');
        const apiGroupNote = (result?.apiKeyMasked || group.apiKeyMasked) && group.name ? group.name : '';
        const showMessage = !isLoading && !!result?.message;
        const showResponse = !isLoading && !!result?.response && result.response !== result.message;

        return (
            <div className="border-t border-gray-100 bg-gray-50/70 px-5 py-4 dark:border-gray-700/50 dark:bg-gray-900/20">
                <div className={`rounded-xl border px-4 py-4 ${statusClasses}`}>
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div className="min-w-0 flex items-start gap-3">
                            <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/70 dark:bg-gray-900/40">
                                {isLoading ? (
                                    <span className={`h-4 w-4 animate-spin rounded-full border-2 ${iconClasses}`} />
                                ) : isSuccess ? (
                                    <svg className={`h-4 w-4 ${iconClasses}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                ) : (
                                    <svg className={`h-4 w-4 ${iconClasses}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                )}
                            </div>

                            <div className="min-w-0">
                                <div className="text-sm font-bold">{__('Provider Test Result', 'smartwoo-chatbot')}</div>
                                <div className="mt-1 text-sm font-medium">{statusLabel}</div>
                                {!isLoading && result?.testedAt && (
                                    <div className="mt-0.5 text-xs opacity-75">{result.testedAt}</div>
                                )}
                            </div>
                        </div>

                        {!isLoading && (
                            <button
                                type="button"
                                onClick={() => dismissTestResult(group.id)}
                                className="self-start rounded-lg px-2 py-1 text-xs font-semibold opacity-70 transition hover:bg-white/70 hover:opacity-100 dark:hover:bg-gray-900/40"
                            >
                                {__('Dismiss', 'smartwoo-chatbot')}
                            </button>
                        )}
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-3 text-xs md:grid-cols-3">
                        <div>
                            <div className="font-semibold uppercase tracking-wide opacity-70">{__('Configuration', 'smartwoo-chatbot')}</div>
                            <div className="mt-1 break-words font-medium">{configurationValue}</div>
                        </div>
                        <div>
                            <div className="font-semibold uppercase tracking-wide opacity-70">{__('Model', 'smartwoo-chatbot')}</div>
                            <div className="mt-1 break-all font-mono">{modelValue}</div>
                        </div>
                        <div>
                            <div className="font-semibold uppercase tracking-wide opacity-70">{__('API Group', 'smartwoo-chatbot')}</div>
                            <div className="mt-1 break-words font-medium">{apiGroupValue}</div>
                            {apiGroupNote && (
                                <div className="mt-1 break-words text-[11px] opacity-75">{apiGroupNote}</div>
                            )}
                        </div>
                    </div>

                    {showMessage && (
                        <div className="mt-4 rounded-lg bg-white/70 px-3 py-2 text-sm leading-6 text-current shadow-sm dark:bg-gray-900/40">
                            <div className="mb-1 text-xs font-semibold uppercase tracking-wide opacity-70">{__('Message', 'smartwoo-chatbot')}</div>
                            <div className="whitespace-pre-wrap break-words">{result.message}</div>
                        </div>
                    )}

                    {showResponse && (
                        <div className="mt-3 rounded-lg bg-white/70 px-3 py-2 text-sm leading-6 text-current shadow-sm dark:bg-gray-900/40">
                            <div className="mb-1 text-xs font-semibold uppercase tracking-wide opacity-70">{__('Response', 'smartwoo-chatbot')}</div>
                            <div className="whitespace-pre-wrap break-words font-mono">{result.response}</div>
                        </div>
                    )}
                </div>
            </div>
        );
    };

    const renderModelRow = (instance, group) => {
        const pricingKey = instance.id;
        const isPricingOpen = pricingOpen === pricingKey;
        const pricing = pricingData[pricingKey];
        const edits = pricingEdits[pricingKey] || {};

        return (
            <div key={instance.id}>
                <div className="group flex items-center gap-4 px-6 py-4 transition hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <span className="truncate font-semibold text-gray-900 dark:text-white">
                                {cleanConfigurationLabel(instance.display_name || '')}
                            </span>
                            {instance.is_default && (
                                <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-700">
                                    {__('Default', 'smartwoo-chatbot')}
                                </span>
                            )}
                            {pricing?.has_custom && (
                                <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    {__('Custom $', 'smartwoo-chatbot')}
                                </span>
                            )}
                        </div>

                        <div className="mt-1 flex items-center gap-4">
                            {instance.model && (
                                <span className="inline-flex items-center gap-1 text-xs">
                                    <span className="text-gray-400 dark:text-gray-500">{__('Model:', 'smartwoo-chatbot')}</span>
                                    <span className="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">
                                        {instance.model}
                                    </span>
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-1 opacity-70 transition-opacity group-hover:opacity-100">
                        <button
                            onClick={() => togglePricing(instance)}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                isPricingOpen
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                    : 'text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20'
                            }`}
                            title={__('Configure pricing for analytics', 'smartwoo-chatbot')}
                        >
                            <svg className="mr-0.5 inline-block h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <line x1="12" x2="12" y1="2" y2="22" />
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                            </svg>
                            {__('Cost', 'smartwoo-chatbot')}
                        </button>

                        <button
                            onClick={() => handleTest(instance, group)}
                            disabled={testing === instance.id}
                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/10 dark:hover:bg-primary/20"
                            title={__('Test connection', 'smartwoo-chatbot')}
                        >
                            <svg className="mr-0.5 inline-block h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            {testing === instance.id ? __('Testing...', 'smartwoo-chatbot') : __('Test', 'smartwoo-chatbot')}
                        </button>

                        <button
                            onClick={() => handleEditModel(instance)}
                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700/50"
                            title={__('Edit model configuration', 'smartwoo-chatbot')}
                        >
                            <svg className="mr-0.5 inline-block h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {__('Edit Model', 'smartwoo-chatbot')}
                        </button>

                        <button
                            onClick={() => handleDelete(instance.id)}
                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-500 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                            title={__('Delete configuration', 'smartwoo-chatbot')}
                        >
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                {isPricingOpen && (
                    <div className="border-t border-gray-100 bg-gray-50/50 px-6 pb-4 dark:border-gray-700/50 dark:bg-gray-800/50">
                        {!pricing ? (
                            <div className="flex items-center justify-center py-4">
                                <div className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                            </div>
                        ) : (
                            <div className="space-y-3 pt-3">
                                <div className="mb-2 flex items-center gap-2">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {__('Token Pricing (per million tokens, USD)', 'smartwoo-chatbot')}
                                    </span>
                                    {pricing.has_custom && (
                                        <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                            {__('Custom Override', 'smartwoo-chatbot')}
                                        </span>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                    {[
                                        { key: 'input_price', label: __('Input', 'smartwoo-chatbot'), defaultValue: pricing.default_input },
                                        { key: 'output_price', label: __('Output', 'smartwoo-chatbot'), defaultValue: pricing.default_output },
                                        { key: 'cache_write_price', label: __('Cache Write', 'smartwoo-chatbot'), defaultValue: pricing.default_cache_write },
                                        { key: 'cache_read_price', label: __('Cache Read', 'smartwoo-chatbot'), defaultValue: pricing.default_cache_read },
                                    ].map((field) => (
                                        <div key={field.key}>
                                            <label className="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-400">
                                                {field.label}
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                placeholder={field.defaultValue != null ? `Default: $${field.defaultValue}` : 'N/A'}
                                                value={edits[field.key] ?? ''}
                                                onChange={(event) => setPricingEdits((prev) => ({
                                                    ...prev,
                                                    [pricingKey]: {
                                                        ...prev[pricingKey],
                                                        [field.key]: event.target.value,
                                                    },
                                                }))}
                                                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-mono text-xs text-gray-900 focus:ring-2 focus:ring-primary/30 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>
                                    ))}
                                </div>

                                <div className="flex items-center gap-2 pt-1">
                                    <button
                                        onClick={() => savePricing(instance)}
                                        disabled={pricingSaving}
                                        className="rounded-lg bg-primary px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-primary/90 disabled:opacity-50"
                                    >
                                        {pricingSaving ? __('Saving...', 'smartwoo-chatbot') : __('Save Pricing', 'smartwoo-chatbot')}
                                    </button>

                                    {pricing.has_custom && (
                                        <button
                                            onClick={() => resetPricing(instance)}
                                            className="rounded-lg bg-amber-50 px-4 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/40"
                                        >
                                            {__('Reset to Default', 'smartwoo-chatbot')}
                                        </button>
                                    )}

                                    <span className="ml-auto text-[10px] text-gray-400">
                                        {__('Optional - defaults used if not set', 'smartwoo-chatbot')}
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:flex-row md:items-center">
                <div className="flex items-center gap-5">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-[14px] bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>

                    <div>
                        <h1 className="mb-1.5 text-[22px] font-semibold leading-none text-gray-900 dark:text-white">
                            {__('Provider Hub', 'smartwoo-chatbot')}
                        </h1>
                        <p className="text-[14px] leading-none text-gray-500 dark:text-gray-400">
                            {__('Manage providers, API groups, and model configurations for your agents.', 'smartwoo-chatbot')}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <span className="font-bold text-gray-700 dark:text-gray-200">{instances.length}</span>
                        <span className="text-gray-500 dark:text-gray-400">{__('Configurations', 'smartwoo-chatbot')}</span>
                    </div>

                    <button
                        onClick={() => onAddNew?.()}
                        className="swc-btn swc-btn--primary inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-medium shadow-sm"
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {__('Add Provider Configuration', 'smartwoo-chatbot')}
                    </button>
                </div>
            </div>

            {notice && (
                <div
                    className={`rounded-xl border px-5 py-3 text-sm font-medium shadow-sm ${
                        notice.type === 'success'
                            ? 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-200'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-200'
                    }`}
                >
                    {notice.message}
                </div>
            )}

            {loading ? (
                <div className="flex items-center justify-center py-16">
                    <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary/20 border-t-primary dark:border-primary/30" />
                </div>
            ) : instances.length === 0 ? (
                <div className="rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 py-16 text-center dark:border-gray-700 dark:bg-gray-800/50">
                    <svg className="mx-auto mb-4 h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <h3 className="text-lg font-bold text-gray-700 dark:text-gray-300">{__('No Provider Configurations', 'smartwoo-chatbot')}</h3>
                    <p className="mx-auto mt-2 max-w-md text-gray-500 dark:text-gray-400">
                        {__('Create your first provider configuration to start assigning different AI models to different agents.', 'smartwoo-chatbot')}
                    </p>
                </div>
            ) : (
                <div className="space-y-6">
                    {groupedProviders.map((providerGroup) => {
                        const providerMeta = getProviderMeta(providerGroup.id);

                        return (
                            <div
                                key={providerGroup.id}
                                className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                            >
                                <div className="flex flex-col justify-between gap-3 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-slate-50 px-6 py-4 dark:border-gray-700 dark:from-gray-800 dark:to-gray-800/80 lg:flex-row lg:items-center">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/80 text-sm font-bold text-white shadow-lg">
                                            {getProviderLabel(providerGroup.id)[0].toUpperCase()}
                                        </div>

                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                                                    {providerGroup.providerLabel}
                                                </h3>
                                                {providerMeta?.premium && (
                                                    <span className="swc-badge swc-badge--pro">
                                                        {__('Premium', 'smartwoo-chatbot')}
                                                    </span>
                                                )}
                                            </div>

                                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                                {providerGroup.groups.length} {providerGroup.groups.length === 1 ? __('API group', 'smartwoo-chatbot') : __('API groups', 'smartwoo-chatbot')}
                                                {' / '}
                                                {providerGroup.modelCount} {providerGroup.modelCount === 1 ? __('model', 'smartwoo-chatbot') : __('models', 'smartwoo-chatbot')}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        onClick={() => handleAddApiGroup(providerGroup.id)}
                                        className="swc-btn swc-btn--secondary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold"
                                        title={__('Add another API key or endpoint for this provider', 'smartwoo-chatbot')}
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v12m6-6H6" />
                                        </svg>
                                        {__('Add API Group', 'smartwoo-chatbot')}
                                    </button>
                                </div>

                                <div className="space-y-4 p-4">
                                    {providerGroup.groups.map((group, groupIndex) => {
                                        const defaultGroupName = `${getProviderLabel(providerGroup.id)} API`;
                                        const showCustomGroupName = group.name && group.name !== defaultGroupName;

                                        return (
                                            <div
                                                key={group.id}
                                                className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                                            >
                                                <div className="flex flex-col justify-between gap-3 border-b border-gray-200 bg-gray-50/80 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30 xl:flex-row xl:items-center">
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="text-sm font-bold text-gray-900 dark:text-white">
                                                                {__('API Group', 'smartwoo-chatbot')} {groupIndex + 1}
                                                            </span>
                                                            {group.apiKeyMasked && (
                                                                <span className="rounded border border-gray-200 bg-white px-2 py-0.5 font-mono text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                                    {group.apiKeyMasked}
                                                                </span>
                                                            )}
                                                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                                                {group.models.length} {group.models.length === 1 ? __('model', 'smartwoo-chatbot') : __('models', 'smartwoo-chatbot')}
                                                            </span>
                                                        </div>

                                                        {showCustomGroupName && (
                                                            <div className="mt-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                                                                {group.name}
                                                            </div>
                                                        )}

                                                        {group.baseUrl && (
                                                            <div className="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                                                {__('Endpoint:', 'smartwoo-chatbot')} <span className="font-mono">{group.baseUrl}</span>
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="flex shrink-0 items-center gap-2">
                                                        <button
                                                            onClick={() => handleEditApiGroup(group)}
                                                            className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                                            title={__('Edit API key and endpoint for this group', 'smartwoo-chatbot')}
                                                        >
                                                            <svg className="mr-0.5 inline-block h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 5.25a3 3 0 014.243 4.243L9 20.486H4.757v-4.243L15.75 5.25z" />
                                                            </svg>
                                                            {__('Edit API', 'smartwoo-chatbot')}
                                                        </button>

                                                        <button
                                                            onClick={() => handleAddModel(group)}
                                                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/10 dark:hover:bg-primary/20"
                                                            title={__('Add another model using this API group', 'smartwoo-chatbot')}
                                                        >
                                                            <svg className="mr-0.5 inline-block h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v12m6-6H6" />
                                                            </svg>
                                                            {__('Add Model', 'smartwoo-chatbot')}
                                                        </button>
                                                    </div>
                                                </div>

                                                <div className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                                    {group.models.map((instance) => renderModelRow(instance, group))}
                                                </div>

                                                {renderTestResultPanel(group)}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="rounded-2xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800/30">
                <h3 className="mb-4 text-lg font-bold text-gray-900 dark:text-white">{__('How It Works', 'smartwoo-chatbot')}</h3>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="flex gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary dark:bg-primary/20">
                            1
                        </div>
                        <div>
                            <div className="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {__('Create API Groups', 'smartwoo-chatbot')}
                            </div>
                            <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {__('Each provider can have one or more API groups, each with its own API key and endpoint.', 'smartwoo-chatbot')}
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary dark:bg-primary/20">
                            2
                        </div>
                        <div>
                            <div className="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {__('Add Models', 'smartwoo-chatbot')}
                            </div>
                            <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {__('Use Add Model inside an API group to create multiple model configurations that share those credentials.', 'smartwoo-chatbot')}
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary dark:bg-primary/20">
                            3
                        </div>
                        <div>
                            <div className="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {__('Edit Once', 'smartwoo-chatbot')}
                            </div>
                            <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {__('Edit API updates the key and endpoint for every model in that API group.', 'smartwoo-chatbot')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
