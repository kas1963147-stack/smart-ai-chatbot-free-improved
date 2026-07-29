import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import AI_PROVIDERS from '../../constants/providers';

const PROVIDER_LIST = AI_PROVIDERS;

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

const maskProviderSecret = (value = '') => {
    const text = String(value || '').trim();

    if (!text) {
        return '';
    }

    if (text.includes('...')) {
        return text;
    }

    if (text.length <= 8) {
        return '***';
    }

    return `${text.slice(0, 4)}...${text.slice(-4)}`;
};

const buildProviderTestResult = ({ result, error, context = {} }) => {
    const payload = result || {};
    const success = typeof payload.success === 'boolean' ? payload.success : !error;

    return {
        id: context.id || payload.id || 'new',
        success,
        message: normalizeProviderTestValue(error?.message ?? payload.message, ''),
        response: normalizeProviderTestValue(payload.response, ''),
        model: normalizeProviderModelValue(payload.model || context.model, ''),
        configuration: normalizeProviderTestValue(context.configuration, ''),
        providerLabel: normalizeProviderTestValue(context.providerLabel, ''),
        apiGroupName: normalizeProviderTestValue(context.apiGroupName, ''),
        apiKeyMasked: normalizeProviderTestValue(context.apiKeyMasked, ''),
        testedAt: formatProviderTestTimestamp(context.testedAt || new Date()),
    };
};

const ProviderTestInfoBlock = ({
    label,
    value,
    note,
    mono = false,
    labelClassName,
    valueClassName,
    noteClassName,
}) => (
    <div className="min-w-0">
        <div className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${labelClassName}`}>
            {label}
        </div>
        <div className={`mt-2 break-words text-sm font-semibold ${mono ? 'font-mono text-[13px]' : ''} ${valueClassName}`}>
            {value}
        </div>
        {note && (
            <div className={`mt-1 break-words text-xs ${noteClassName}`}>
                {note}
            </div>
        )}
    </div>
);

const ProviderTestStatusIcon = ({ success }) => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        {success ? (
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
        ) : (
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        )}
    </svg>
);

function ProviderTestResultCard({ result, onDismiss, className = '' }) {
    const theme = result?.success
        ? {
            shell: 'border-emerald-200 bg-emerald-50/80',
            icon: 'bg-white text-emerald-600 ring-1 ring-emerald-200',
            eyebrow: 'text-emerald-700',
            title: 'text-emerald-950',
            time: 'text-emerald-700/80',
            label: 'text-emerald-700/80',
            value: 'text-emerald-950',
            note: 'text-emerald-700/80',
            panel: 'border-emerald-100 bg-white/85',
            panelLabel: 'text-emerald-700/80',
            panelText: 'text-emerald-950',
            dismiss: 'text-emerald-700 hover:bg-white/70',
        }
        : {
            shell: 'border-red-200 bg-red-50/90',
            icon: 'bg-white text-red-500 ring-1 ring-red-100',
            eyebrow: 'text-red-700',
            title: 'text-red-900',
            time: 'text-red-700/80',
            label: 'text-red-700/80',
            value: 'text-red-900',
            note: 'text-red-700/80',
            panel: 'border-red-100 bg-white/85',
            panelLabel: 'text-red-700/80',
            panelText: 'text-red-900',
            dismiss: 'text-red-700 hover:bg-white/70',
        };

    const configurationValue = result?.configuration || result?.providerLabel || __('Not available', 'smartwoo-chatbot');
    const modelValue = result?.model || __('Not available', 'smartwoo-chatbot');
    const apiGroupValue = result?.apiKeyMasked || result?.apiGroupName || __('Not available', 'smartwoo-chatbot');
    const apiGroupNote = result?.apiKeyMasked && result?.apiGroupName ? result.apiGroupName : '';
    const statusLabel = result?.success
        ? __('Connection successful', 'smartwoo-chatbot')
        : __('Connection failed', 'smartwoo-chatbot');
    const showMessage = !!result?.message;
    const showResponse = !!result?.response && result.response !== result.message;

    return (
        <div className={`rounded-xl border px-4 py-3.5 shadow-sm ${theme.shell} ${className}`.trim()}>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex items-start gap-4">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${theme.icon}`}>
                            <ProviderTestStatusIcon success={!!result?.success} />
                        </div>

                        <div className="min-w-0">
                            <div className={`text-sm font-semibold ${theme.eyebrow}`}>
                                {__('Provider Test Result', 'smartwoo-chatbot')}
                            </div>
                            <div className={`mt-1 text-lg font-semibold leading-none ${theme.title}`}>
                                {statusLabel}
                            </div>
                            {result?.testedAt && (
                                <div className={`mt-2 text-sm ${theme.time}`}>
                                    {result.testedAt}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="mt-3 grid gap-4 md:grid-cols-3">
                        <ProviderTestInfoBlock
                            label={__('Configuration', 'smartwoo-chatbot')}
                            value={configurationValue}
                            labelClassName={theme.label}
                            valueClassName={theme.value}
                            noteClassName={theme.note}
                        />
                        <ProviderTestInfoBlock
                            label={__('Model', 'smartwoo-chatbot')}
                            value={modelValue}
                            mono={!!result?.model}
                            labelClassName={theme.label}
                            valueClassName={theme.value}
                            noteClassName={theme.note}
                        />
                        <ProviderTestInfoBlock
                            label={__('API Group', 'smartwoo-chatbot')}
                            value={apiGroupValue}
                            note={apiGroupNote}
                            mono={!!result?.apiKeyMasked}
                            labelClassName={theme.label}
                            valueClassName={theme.value}
                            noteClassName={theme.note}
                        />
                    </div>

                    {showMessage && (
                        <div className={`mt-3 rounded-xl border px-3 py-2.5 ${theme.panel}`}>
                            <div className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${theme.panelLabel}`}>
                                {__('Message', 'smartwoo-chatbot')}
                            </div>
                            <div className={`mt-2 whitespace-pre-wrap break-words text-sm leading-6 ${theme.panelText}`}>
                                {result.message}
                            </div>
                        </div>
                    )}

                    {showResponse && (
                        <div className={`mt-3 rounded-xl border px-3 py-2.5 ${theme.panel}`}>
                            <div className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${theme.panelLabel}`}>
                                {__('Response', 'smartwoo-chatbot')}
                            </div>
                            <div className={`mt-2 whitespace-pre-wrap break-words font-mono text-[13px] leading-6 ${theme.panelText}`}>
                                {result.response}
                            </div>
                        </div>
                    )}
                </div>

                {onDismiss && (
                    <button
                        type="button"
                        onClick={onDismiss}
                        className={`shrink-0 rounded-xl px-3 py-2 text-sm font-medium transition ${theme.dismiss}`}
                    >
                        {__('Dismiss', 'smartwoo-chatbot')}
                    </button>
                )}
            </div>
        </div>
    );
}

const EMPTY_FORM = {
    id: '',
    display_name: '',
    provider: 'openrouter',
    api_key: '',
    api_key_masked: '',
    model: '',
    base_url: '',
    is_default: false,
    inherit_key_from: '',
    api_group_id: '',
    api_group_name: '',
    apply_to_group: false,
    edit_api_group: false,
    group_model_count: 0,
    input_price: '',
    output_price: '',
    max_tokens: '',
    temperature: '',
};

const getProviderLabel = (id) => {
    const provider = PROVIDER_LIST.find((item) => item.id === id);
    return provider?.label?.split(' (')[0] || id.charAt(0).toUpperCase() + id.slice(1);
};

export default function ProviderEditorPage({
    providerToEdit,
    onSaveSuccess,
    onCancel,
    isAddingModelFor,
    providerSeed,
}) {
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [testing, setTesting] = useState(null);
    const [notice, setNotice] = useState(null);
    const [providerModels, setProviderModels] = useState([]);
    const [modelsLoading, setModelsLoading] = useState(false);
    const [modelSearch, setModelSearch] = useState('');
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [modelsError, setModelsError] = useState('');

    const currentProviderInfo = useMemo(
        () => PROVIDER_LIST.find((item) => item.id === form.provider),
        [form.provider]
    );
    const hasModelApi = currentProviderInfo?.hasModelApi ?? false;
    const isEditingApiGroup = !!form.edit_api_group;
    const modelLookupInstanceId = form.inherit_key_from || form.id || '';

    useEffect(() => {
        if (providerToEdit) {
            setForm({
                ...EMPTY_FORM,
                id: providerToEdit.id || '',
                display_name: providerToEdit.display_name || '',
                provider: providerToEdit.provider || 'openrouter',
                api_key: '',
                api_key_masked: providerToEdit.api_key_masked || '',
                model: providerToEdit.model || '',
                base_url: providerToEdit.base_url || '',
                is_default: !!providerToEdit.is_default,
                api_group_id: providerToEdit.api_group_id || '',
                api_group_name: providerToEdit.api_group_name || '',
                apply_to_group: !!providerToEdit.edit_api_group,
                edit_api_group: !!providerToEdit.edit_api_group,
                group_model_count: providerToEdit.group_model_count || 0,
                input_price: providerToEdit.input_price !== undefined ? providerToEdit.input_price : '',
                output_price: providerToEdit.output_price !== undefined ? providerToEdit.output_price : '',
                max_tokens: providerToEdit.extra?.max_tokens || '',
                temperature: providerToEdit.extra?.temperature || '',
            });
            setEditing(true);
        } else if (isAddingModelFor) {
            setForm({
                ...EMPTY_FORM,
                provider: isAddingModelFor.provider || 'openrouter',
                base_url: isAddingModelFor.base_url || '',
                inherit_key_from: isAddingModelFor.id || '',
                api_group_id: isAddingModelFor.api_group_id || '',
                api_group_name: isAddingModelFor.api_group_name || '',
                group_model_count: isAddingModelFor.group_model_count || 0,
                display_name: '',
                model: '',
            });
            setEditing(false);
        } else if (providerSeed?.create_new_group) {
            setForm({
                ...EMPTY_FORM,
                provider: providerSeed.provider || 'openrouter',
                api_group_name: providerSeed.provider ? `${getProviderLabel(providerSeed.provider)} API` : '',
            });
            setEditing(false);
        } else {
            setForm({ ...EMPTY_FORM });
            setEditing(false);
        }
    }, [providerToEdit, isAddingModelFor, providerSeed]);

    useEffect(() => {
        if (!providerToEdit || providerToEdit.edit_api_group) {
            return undefined;
        }

        apiFetch({ path: '/smart-ai-chatbot/v1/pricing', method: 'GET' })
            .then((res) => {
                if (!res.success) {
                    return;
                }

                const match = (res.data || []).find(
                    (item) => item.provider === providerToEdit.provider && item.model === providerToEdit.model
                );

                if (match) {
                    setForm((prev) => ({
                        ...prev,
                        input_price: match.custom_input !== null ? match.custom_input : (match.default_input ?? ''),
                        output_price: match.custom_output !== null ? match.custom_output : (match.default_output ?? ''),
                    }));
                }
            })
            .catch(() => {});

        return undefined;
    }, [providerToEdit]);

    const loadProviderModels = useCallback(async (provider, apiKey, baseUrl, instanceId = '') => {
        setModelsLoading(true);
        setModelsError('');

        try {
            let path = `/smart-ai-chatbot/v1/provider-models?provider=${encodeURIComponent(provider)}`;
            if (apiKey) {
                path += `&api_key=${encodeURIComponent(apiKey)}`;
            }
            if (baseUrl) {
                path += `&base_url=${encodeURIComponent(baseUrl)}`;
            }
            if (instanceId) {
                path += `&instance_id=${encodeURIComponent(instanceId)}`;
            }

            const res = await apiFetch({ path });
            if (res?.success && Array.isArray(res.data)) {
                setProviderModels(res.data);
            } else if (res?.requires_key) {
                setModelsError(__('Enter your API key first to load available models', 'smartwoo-chatbot'));
                setProviderModels([]);
            } else {
                setProviderModels([]);
            }
        } catch (err) {
            setProviderModels([]);
            setModelsError(err.message || __('Failed to load models', 'smartwoo-chatbot'));
        } finally {
            setModelsLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!hasModelApi || isEditingApiGroup) {
            setProviderModels([]);
            return undefined;
        }

        loadProviderModels(form.provider, form.api_key, form.base_url, modelLookupInstanceId);
        return undefined;
    }, [form.provider, hasModelApi, isEditingApiGroup, form.api_key, form.base_url, modelLookupInstanceId, loadProviderModels]);

    useEffect(() => {
        if (!notice) {
            return undefined;
        }

        const timer = setTimeout(() => setNotice(null), 5000);
        return () => clearTimeout(timer);
    }, [notice]);

    const updateForm = (patch) => {
        setForm((prev) => ({ ...prev, ...patch }));
    };

    const handleModelChange = (model) => {
        setForm((prev) => {
            const next = { ...prev, model };
            if (!prev.display_name || prev.display_name === getProviderLabel(prev.provider)) {
                const providerName = getProviderLabel(prev.provider);
                next.display_name = model ? `${providerName} (${model})` : providerName;
            }
            return next;
        });
    };

    const selectProviderModel = (model) => {
        setDropdownOpen(false);
        setModelSearch('');
        setForm((prev) => ({
            ...prev,
            model: model.id,
            display_name: prev.display_name || `${getProviderLabel(prev.provider)} (${model.id})`,
            input_price: model.input_price !== undefined ? String(model.input_price) : prev.input_price,
            output_price: model.output_price !== undefined ? String(model.output_price) : prev.output_price,
        }));
    };

    const filteredModels = useMemo(() => {
        const search = modelSearch.trim().toLowerCase();
        if (!search) {
            return providerModels;
        }

        return providerModels.filter((item) => {
            const haystack = `${item.id || ''} ${item.name || ''}`.toLowerCase();
            return haystack.includes(search);
        });
    }, [providerModels, modelSearch]);

    const handleSave = async () => {
        setSaving(true);

        try {
            const saveData = {
                id: form.id,
                display_name: form.display_name,
                provider: form.provider,
                api_key: form.api_key,
                model: form.model,
                base_url: form.base_url,
                is_default: form.is_default,
                inherit_key_from: form.inherit_key_from,
                api_group_id: form.api_group_id,
                api_group_name: form.api_group_name,
                apply_to_group: form.apply_to_group,
            };

            saveData.extra = {
                max_tokens: form.max_tokens !== '' ? parseInt(form.max_tokens, 10) : null,
                temperature: form.temperature !== '' ? parseFloat(form.temperature) : null,
            };

            const res = await apiFetch({
                path: '/smart-ai-chatbot/v1/provider-instances',
                method: 'POST',
                data: saveData,
            });

            if (!res.success) {
                throw new Error(res.message || __('Failed to save', 'smartwoo-chatbot'));
            }

            if (!isEditingApiGroup && (form.input_price !== '' || form.output_price !== '')) {
                try {
                    await apiFetch({
                        path: '/smart-ai-chatbot/v1/pricing',
                        method: 'POST',
                        data: {
                            provider: form.provider,
                            model: form.model,
                            input_price: form.input_price !== '' ? parseFloat(form.input_price) : null,
                            output_price: form.output_price !== '' ? parseFloat(form.output_price) : null,
                            cache_write_price: null,
                            cache_read_price: null,
                        },
                    });
                } catch (err) {
                    // Pricing failure should not block the provider save flow.
                }
            }

            onSaveSuccess(
                isEditingApiGroup
                    ? __('API group updated', 'smartwoo-chatbot')
                    : editing
                        ? __('Configuration updated', 'smartwoo-chatbot')
                        : __('Configuration created', 'smartwoo-chatbot')
            );
        } catch (err) {
            setNotice({ type: 'error', message: err.message });
        } finally {
            setSaving(false);
        }
    };

    const handleTest = async () => {
        const id = form.id || 'new';
        setTesting(id);
        setTestResult(null);

        try {
            const res = await apiFetch({
                path: '/smart-ai-chatbot/v1/provider-instances/test',
                method: 'POST',
                data: {
                    provider: form.provider,
                    api_key: form.api_key,
                    model: form.model,
                    base_url: form.base_url,
                    instance_id: form.id || form.inherit_key_from || '',
                },
            });

            setTestResult(buildProviderTestResult({
                result: res?.data,
                context: {
                    id,
                    configuration: form.display_name || '',
                    providerLabel: getProviderLabel(form.provider),
                    model: form.model || '',
                    apiGroupName: form.api_group_name || '',
                    apiKeyMasked: form.api_key_masked || maskProviderSecret(form.api_key),
                    testedAt: new Date(),
                },
            }));
        } catch (err) {
            setTestResult(buildProviderTestResult({
                error: err,
                context: {
                    id,
                    configuration: form.display_name || '',
                    providerLabel: getProviderLabel(form.provider),
                    model: form.model || '',
                    apiGroupName: form.api_group_name || '',
                    apiKeyMasked: form.api_key_masked || maskProviderSecret(form.api_key),
                    testedAt: new Date(),
                },
            }));
        } finally {
            setTesting(null);
        }
    };

    const saveDisabled = saving
        || !form.provider
        || !form.display_name
        || (!isEditingApiGroup && !form.model);

    return (
        <div className="swc-admin w-full space-y-6">
            <div className="flex flex-col justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center">
                <div className="flex items-center gap-5">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-[14px] bg-blue-50 text-blue-600">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>

                    <div>
                        <h1 className="mb-1.5 text-[22px] font-semibold leading-none text-gray-900">
                            {isEditingApiGroup
                                ? __('Edit API Group', 'smartwoo-chatbot')
                                : editing
                                    ? __('Edit Configuration', 'smartwoo-chatbot')
                                    : form.inherit_key_from
                                        ? `${__('Add New Model', 'smartwoo-chatbot')} - ${getProviderLabel(form.provider)}`
                                        : __('New Provider Configuration', 'smartwoo-chatbot')}
                        </h1>
                        <p className="text-[14px] leading-none text-gray-500">
                            {isEditingApiGroup
                                ? __('Update the shared API key and endpoint for every model in this API group.', 'smartwoo-chatbot')
                                : form.inherit_key_from
                                    ? __('This model will join the same API group and reuse the existing API credentials.', 'smartwoo-chatbot')
                                    : editing
                                        ? __('Update your provider and model details.', 'smartwoo-chatbot')
                                        : __('Configure a new provider API group and add its first model.', 'smartwoo-chatbot')}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    {notice && (
                        <div className={`rounded-lg px-4 py-2 text-sm font-medium shadow-sm ${notice.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
                            {notice.message}
                        </div>
                    )}

                    <button
                        onClick={onCancel}
                        className="group inline-flex items-center gap-2 px-1 py-2 text-sm text-slate-500 transition-colors hover:text-primary"
                    >
                        <svg className="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span className="font-semibold">{__('Back to Providers', 'smartwoo-chatbot')}</span>
                    </button>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
                <div className="space-y-5 p-6">
                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label className="swc-label block">
                                {__('Provider', 'smartwoo-chatbot')} <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={form.provider}
                                onChange={(event) => updateForm({ provider: event.target.value })}
                                disabled={!!form.inherit_key_from || isEditingApiGroup || !!providerSeed?.create_new_group}
                                className="swc-input w-full disabled:opacity-60"
                            >
                                {PROVIDER_LIST.map((provider) => (
                                    <option key={provider.id} value={provider.id}>
                                        {provider.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="swc-label block">
                                {__('API Group Name', 'smartwoo-chatbot')}
                            </label>
                            <input
                                type="text"
                                value={form.api_group_name}
                                onChange={(event) => updateForm({ api_group_name: event.target.value })}
                                placeholder={__('e.g. Azure Production, Azure Backup', 'smartwoo-chatbot')}
                                className="swc-input w-full"
                            />
                            <p className="mt-1 text-xs text-gray-400">
                                {__('Used to distinguish multiple API keys under the same provider.', 'smartwoo-chatbot')}
                            </p>
                        </div>
                    </div>

                    {(isEditingApiGroup || form.inherit_key_from) && (
                        <div className="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                            {isEditingApiGroup
                                ? __('Changes in this screen update the shared API credentials for every model in this group.', 'smartwoo-chatbot')
                                : __('This model will reuse the API key and endpoint from the selected API group.', 'smartwoo-chatbot')}
                            {!!form.group_model_count && (
                                <span className="ml-1 font-semibold">
                                    {`(${form.group_model_count} ${form.group_model_count === 1 ? __('existing model', 'smartwoo-chatbot') : __('existing models', 'smartwoo-chatbot')})`}
                                </span>
                            )}
                        </div>
                    )}

                    {!isEditingApiGroup && (
                        <>
                            <div>
                                <label className="swc-label block">
                                    {__('Model ID', 'smartwoo-chatbot')} <span className="text-red-500">*</span>
                                </label>

                                {hasModelApi ? (
                                    <div className="relative">
                                        <input
                                            type="text"
                                            value={dropdownOpen ? modelSearch : (form.model || '')}
                                            onChange={(event) => {
                                                setModelSearch(event.target.value);
                                                if (!dropdownOpen) {
                                                    setDropdownOpen(true);
                                                }
                                            }}
                                            onFocus={() => {
                                                setDropdownOpen(true);
                                                setModelSearch('');
                                            }}
                                            onBlur={() => {
                                                window.setTimeout(() => {
                                                    const typed = modelSearch.trim();
                                                    if (typed && typed !== form.model && dropdownOpen) {
                                                        handleModelChange(typed);
                                                    }
                                                    setDropdownOpen(false);
                                                }, 150);
                                            }}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Escape') {
                                                    setDropdownOpen(false);
                                                    setModelSearch('');
                                                }
                                                if (event.key === 'Enter') {
                                                    event.preventDefault();
                                                    const typed = modelSearch.trim();
                                                    if (typed) {
                                                        const match = providerModels.find((item) => item.id === typed);
                                                        if (match) {
                                                            selectProviderModel(match);
                                                        } else {
                                                            handleModelChange(typed);
                                                            setDropdownOpen(false);
                                                            setModelSearch('');
                                                        }
                                                    }
                                                }
                                            }}
                                            placeholder={modelsLoading ? __('Loading models...', 'smartwoo-chatbot') : modelsError || __('Search or type model ID...', 'smartwoo-chatbot')}
                                            className="swc-input w-full"
                                        />

                                        {dropdownOpen && (
                                            <div className="absolute z-20 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl">
                                                {modelsLoading ? (
                                                    <div className="px-4 py-3 text-sm text-gray-500">{__('Loading models...', 'smartwoo-chatbot')}</div>
                                                ) : filteredModels.length === 0 ? (
                                                    <div className="px-4 py-3 text-sm text-gray-500">
                                                        {modelSearch
                                                            ? __('No matching models. Press Enter to use a custom model ID.', 'smartwoo-chatbot')
                                                            : __('No models found for this API group.', 'smartwoo-chatbot')}
                                                    </div>
                                                ) : (
                                                    filteredModels.map((model) => (
                                                        <button
                                                            key={model.id}
                                                            type="button"
                                                            onMouseDown={(event) => event.preventDefault()}
                                                            onClick={() => selectProviderModel(model)}
                                                            className="flex w-full items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-gray-50"
                                                        >
                                                            <div className="min-w-0">
                                                                <div className="truncate text-sm font-medium text-gray-900">
                                                                    {model.name || model.id}
                                                                </div>
                                                                <div className="mt-0.5 truncate font-mono text-xs text-gray-500">
                                                                    {model.id}
                                                                </div>
                                                            </div>
                                                            <div className="shrink-0 text-right">
                                                                {model.context_length > 0 && (
                                                                    <div className="text-[10px] text-gray-400">
                                                                        {model.context_length >= 1000 ? `${Math.round(model.context_length / 1000)}K ctx` : `${model.context_length} ctx`}
                                                                    </div>
                                                                )}
                                                                {!model.is_free && (model.input_price > 0 || model.output_price > 0) && (
                                                                    <div className="whitespace-nowrap text-[10px] font-mono text-amber-600">
                                                                        ${Number(model.input_price || 0).toFixed(2)} / ${Number(model.output_price || 0).toFixed(2)}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </button>
                                                    ))
                                                )}
                                            </div>
                                        )}

                                        {modelsError && (
                                            <button
                                                type="button"
                                                onClick={() => loadProviderModels(form.provider, form.api_key, form.base_url, modelLookupInstanceId)}
                                                className="mt-1 text-xs text-primary hover:underline"
                                            >
                                                {__('Retry loading models', 'smartwoo-chatbot')}
                                            </button>
                                        )}
                                    </div>
                                ) : (
                                    <input
                                        type="text"
                                        value={form.model}
                                        onChange={(event) => handleModelChange(event.target.value)}
                                        placeholder="e.g. gpt-4o, claude-3-5-sonnet, llama-3.1-70b"
                                        className="swc-input w-full"
                                    />
                                )}
                            </div>

                            <div>
                                <label className="swc-label block">
                                    {__('Display Name', 'smartwoo-chatbot')} <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.display_name}
                                    onChange={(event) => updateForm({ display_name: event.target.value })}
                                    placeholder={__('e.g. Azure GPT-5 Mini, Groq Fast Model', 'smartwoo-chatbot')}
                                    className="swc-input w-full"
                                />
                                <p className="mt-1 text-xs text-gray-400">
                                    {__('This name appears in the agent configuration dropdown.', 'smartwoo-chatbot')}
                                </p>
                            </div>
                        </>
                    )}

                    {isEditingApiGroup && (
                        <div>
                            <label className="swc-label block">
                                {__('Display Name', 'smartwoo-chatbot')} <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={form.display_name}
                                onChange={(event) => updateForm({ display_name: event.target.value })}
                                className="swc-input w-full"
                            />
                            <p className="mt-1 text-xs text-gray-400">
                                {__('Used internally to keep the original model configuration valid while the shared API group is updated.', 'smartwoo-chatbot')}
                            </p>
                        </div>
                    )}

                    {!form.inherit_key_from && (
                        <div>
                            <label className="swc-label block">
                                {__('API Key', 'smartwoo-chatbot')} {!editing && <span className="text-red-500">*</span>}
                            </label>
                            <input
                                type="password"
                                value={form.api_key}
                                onChange={(event) => updateForm({ api_key: event.target.value })}
                                placeholder={editing ? __('Leave empty to keep existing key', 'smartwoo-chatbot') : __('Enter API key...', 'smartwoo-chatbot')}
                                className="swc-input w-full font-mono text-sm"
                            />
                            {editing && form.api_key_masked && (
                                <p className="mt-1 text-xs text-gray-500">
                                    {__('Current key:', 'smartwoo-chatbot')} <span className="font-mono">{form.api_key_masked}</span>
                                </p>
                            )}
                        </div>
                    )}

                    {!form.inherit_key_from && (
                        <div>
                            <label className="swc-label block">
                                {__('Base URL', 'smartwoo-chatbot')}
                            </label>
                            <input
                                type="text"
                                value={form.base_url}
                                onChange={(event) => updateForm({ base_url: event.target.value })}
                                placeholder="https://..."
                                className="swc-input w-full font-mono text-sm"
                            />
                            <p className="mt-1 text-xs text-gray-400">
                                {__('Used for Azure, local providers, or any custom endpoint.', 'smartwoo-chatbot')}
                            </p>
                        </div>
                    )}

                    {!isEditingApiGroup && (
                        <label className="flex cursor-pointer items-center gap-3 select-none">
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    checked={form.is_default}
                                    onChange={(event) => updateForm({ is_default: event.target.checked })}
                                    className="peer sr-only"
                                />
                                <div className="h-6 w-11 rounded-full bg-gray-300 transition-colors peer-checked:bg-primary" />
                                <div className="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-full" />
                            </div>
                            <span className="text-sm font-medium text-gray-700">
                                {__('Set as default (used when agents do not specify a provider)', 'smartwoo-chatbot')}
                            </span>
                        </label>
                    )}

                    {!isEditingApiGroup && (
                        <>
                            <div className="border-t border-gray-200 pt-4">
                                <div className="mb-3 flex items-center gap-2">
                                    <span className="text-sm font-semibold text-gray-700">{__('Token Cost', 'smartwoo-chatbot')}</span>
                                    <span className="text-xs font-normal text-gray-400">{__('(optional, per million tokens, USD)', 'smartwoo-chatbot')}</span>
                                </div>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">
                                            {__('Input Price', 'smartwoo-chatbot')}
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.input_price}
                                            onChange={(event) => updateForm({ input_price: event.target.value })}
                                            className="swc-input w-full font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">
                                            {__('Output Price', 'smartwoo-chatbot')}
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.output_price}
                                            onChange={(event) => updateForm({ output_price: event.target.value })}
                                            className="swc-input w-full font-mono"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="border-t border-gray-200 pt-4">
                                <div className="mb-3 flex items-center gap-2">
                                    <span className="text-sm font-semibold text-gray-700">{__('Technical Limits', 'smartwoo-chatbot')}</span>
                                    <span className="text-xs font-normal text-gray-400">{__('(optional overrides)', 'smartwoo-chatbot')}</span>
                                </div>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">
                                            {__('Max Tokens', 'smartwoo-chatbot')}
                                        </label>
                                        <input
                                            type="number"
                                            step="1"
                                            min="1"
                                            value={form.max_tokens}
                                            onChange={(event) => updateForm({ max_tokens: event.target.value })}
                                            className="swc-input w-full font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">
                                            {__('Temperature', 'smartwoo-chatbot')}
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="2"
                                            value={form.temperature}
                                            onChange={(event) => updateForm({ temperature: event.target.value })}
                                            className="swc-input w-full font-mono"
                                        />
                                    </div>
                                </div>
                            </div>
                        </>
                    )}

                    {testResult && testResult.id === (form.id || 'new') && (
                        <ProviderTestResultCard
                            result={testResult}
                            onDismiss={() => setTestResult(null)}
                        />
                    )}

                    <div className="-mx-6 -mb-6 flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50/30 px-6 py-4">
                        <button
                            onClick={handleSave}
                            disabled={saveDisabled}
                            className="swc-btn swc-btn--primary px-6 py-2.5 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {saving
                                ? __('Saving...', 'smartwoo-chatbot')
                                : isEditingApiGroup
                                    ? __('Update API Group', 'smartwoo-chatbot')
                                    : editing
                                        ? __('Update', 'smartwoo-chatbot')
                                        : __('Create Configuration', 'smartwoo-chatbot')}
                        </button>
                        <button
                            onClick={handleTest}
                            disabled={testing === (form.id || 'new')}
                            className="swc-btn swc-btn--secondary px-5 py-2.5"
                        >
                            {testing === (form.id || 'new') ? __('Testing...', 'smartwoo-chatbot') : __('Test Connection', 'smartwoo-chatbot')}
                        </button>
                        <button onClick={onCancel} className="swc-btn swc-btn--ghost px-5 py-2.5">
                            {__('Cancel', 'smartwoo-chatbot')}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
