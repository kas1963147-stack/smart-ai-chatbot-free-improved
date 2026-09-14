/**
 * SearchSettings â€” Core settings panel for AI Search Enhancement
 *
 * Migrated from Settings â†’ Advanced tab with expanded controls.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import PropTypes from 'prop-types';

export default function SearchSettings({ settings, onChange, onSave, saving }) {
    // Provider instances for search agent dropdown
    const [providerInstances, setProviderInstances] = useState(
        window.swcChatbot?.providerInstances || []
    );

    // Fetch provider instances via API if none were localized
    useEffect(() => {
        if (providerInstances.length <= 1) {
            apiFetch({ path: '/quark-agentflow-ai/v1/provider-instances' })
                .then((res) => {
                    const items = res?.data || [];
                    if (Array.isArray(items) && items.length > 0) {
                        const dropdown = items.map((inst) => ({
                            id: inst.id || '',
                            label: (inst.display_name || inst.provider || 'Unknown') + (inst.model ? ' (' + inst.model + ')' : '') + (inst.is_default ? ' ' : ''),
                        }));
                        setProviderInstances(dropdown);
                    }
                })
                .catch(() => { });
        }
    }, []);
    return (
        <div className="grid gap-6 lg:grid-cols-2">
            {/* Core Settings */}
            <div className="rounded-2xl border border-border bg-card shadow-sm">
                <div className="border-b border-border px-6 py-4">
                    <h3 className="text-lg font-semibold text-foreground">
                        {__('Core Settings', 'agentflow-ai')}
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {__('Enable and configure the AI search enhancement engine', 'agentflow-ai')}
                    </p>
                </div>
                <div className="space-y-5 p-6">
                    {/* Master Toggle */}
                    <div className="flex items-center justify-between rounded-xl bg-gradient-to-r from-primary/5 to-primary/10 p-4">
                        <div>
                            <div className="text-sm font-semibold text-foreground">
                                {__('Enable AI-Powered Search', 'agentflow-ai')}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {__('Use AI to expand search queries with related keywords', 'agentflow-ai')}
                            </div>
                        </div>
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={!!settings.search_enhancement_enabled}
                                onChange={(e) => onChange('search_enhancement_enabled', e.target.checked)}
                                className="peer sr-only"
                            />
                            <div className="h-6 w-11 rounded-full bg-muted after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-card after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-5 peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20" />
                        </label>
                    </div>

                    {/* AI Provider for Search */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-foreground">
                            <span className="flex items-center gap-2">
                                <span className="text-base"></span>
                                {__('AI Provider', 'agentflow-ai')}
                            </span>
                        </label>
                        <select
                            id="swc-search-provider-select"
                            value={settings.search_provider_instance_id || ''}
                            onChange={(e) => onChange('search_provider_instance_id', e.target.value)}
                            className="h-10 w-full rounded-lg border border-border bg-card px-4 text-sm text-foreground transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="">{__('-- Use Global Settings --', 'agentflow-ai')}</option>
                            {providerInstances
                                .filter((inst) => inst.id !== '' && inst.id)
                                .map((inst) => (
                                    <option key={inst.id} value={inst.id}>{inst.label}</option>
                                ))}
                        </select>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {__('Select which AI provider to use for search keyword generation', 'agentflow-ai')}
                        </p>
                    </div>

                    {/* Max Keywords */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-foreground">
                            {__('Max Keywords per Query', 'agentflow-ai')}
                            <span className="ml-2 inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                {settings.search_max_keywords || 5}
                            </span>
                        </label>
                        <input
                            type="range"
                            min="3"
                            max="15"
                            value={settings.search_max_keywords || 5}
                            onChange={(e) => onChange('search_max_keywords', parseInt(e.target.value) || 5)}
                            className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-muted accent-primary"
                        />
                        <div className="mt-1 flex justify-between text-xs text-muted-foreground">
                            <span>3 ({__('Faster', 'agentflow-ai')})</span>
                            <span>15 ({__('More results', 'agentflow-ai')})</span>
                        </div>
                    </div>

                    {/* Cache Duration */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-foreground">
                            {__('Cache Duration', 'agentflow-ai')}
                        </label>
                        <select
                            value={settings.search_cache_duration || 3600}
                            onChange={(e) => onChange('search_cache_duration', parseInt(e.target.value))}
                            className="h-10 w-full rounded-lg border border-border bg-card px-4 text-sm text-foreground transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="900">{__('15 minutes', 'agentflow-ai')}</option>
                            <option value="1800">{__('30 minutes', 'agentflow-ai')}</option>
                            <option value="3600">{__('1 hour', 'agentflow-ai')}</option>
                            <option value="7200">{__('2 hours', 'agentflow-ai')}</option>
                            <option value="21600">{__('6 hours', 'agentflow-ai')}</option>
                            <option value="86400">{__('24 hours', 'agentflow-ai')}</option>
                        </select>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {__('How long to cache AI-generated keywords before refreshing', 'agentflow-ai')}
                        </p>
                    </div>

                    {/* Min Query Length */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-foreground">
                            {__('Minimum Query Length', 'agentflow-ai')}
                        </label>
                        <div className="flex items-center gap-3">
                            <input
                                type="number"
                                min="1"
                                max="5"
                                value={settings.search_min_query_length || 2}
                                onChange={(e) => onChange('search_min_query_length', parseInt(e.target.value) || 2)}
                                className="h-10 w-24 rounded-lg border border-border bg-card px-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            />
                            <span className="text-sm text-muted-foreground">{__('characters', 'agentflow-ai')}</span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {__('Searches shorter than this will not be enhanced by AI', 'agentflow-ai')}
                        </p>
                    </div>
                </div>
            </div>

            {/* Content Types & Search Behavior */}
            <div className="space-y-6">
                {/* Content Types */}
                <div className="rounded-2xl border border-border bg-card shadow-sm">
                    <div className="border-b border-border px-6 py-4">
                        <h3 className="text-lg font-semibold text-foreground">
                            {__('Content Types', 'agentflow-ai')}
                        </h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {__('Select which content types should be enhanced by AI search', 'agentflow-ai')}
                        </p>
                    </div>
                    <div className="space-y-3 p-6">
                        {[
                            { id: 'posts', label: __('Posts', 'agentflow-ai'), description: __('Blog posts and articles', 'agentflow-ai'), defaultOn: true },
                            { id: 'pages', label: __('Pages', 'agentflow-ai'), description: __('Static pages', 'agentflow-ai'), defaultOn: true },
                            { id: 'products', label: __('Products', 'agentflow-ai'), description: __('WooCommerce products', 'agentflow-ai'), defaultOn: true, woo: true },
                        ].map((type) => {
                            const key = `search_content_${type.id}`;
                            const checked = settings[key] !== undefined ? !!settings[key] : type.defaultOn;
                            return (
                                <label
                                    key={type.id}
                                    className={`flex cursor-pointer items-center justify-between rounded-xl border p-3 transition-all ${checked
                                        ? 'border-primary/30 bg-primary/5'
                                        : 'border-border bg-muted/50 hover:bg-muted'
                                        }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="text-lg">{type.id === 'posts' ? '' : type.id === 'pages' ? '' : ''}</span>
                                        <div>
                                            <div className="text-sm font-medium text-foreground">{type.label}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {type.description}
                                                {type.woo && (
                                                    <span className="ml-1 inline-flex rounded bg-primary/10 px-1 text-[10px] font-medium text-primary">
                                                        WooCommerce
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={(e) => onChange(key, e.target.checked)}
                                        className="h-4 w-4 rounded border-border text-primary focus:ring-primary/30"
                                    />
                                </label>
                            );
                        })}
                    </div>
                </div>

                {/* Search Behavior */}
                <div className="rounded-2xl border border-border bg-card shadow-sm">
                    <div className="border-b border-border px-6 py-4">
                        <h3 className="text-lg font-semibold text-foreground">
                            {__('Search Behavior', 'agentflow-ai')}
                        </h3>
                    </div>
                    <div className="space-y-4 p-6">
                        {/* Fallback Strategy */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-foreground">
                                {__('Fallback Strategy', 'agentflow-ai')}
                            </label>
                            <select
                                value={settings.search_fallback_strategy || 'original'}
                                onChange={(e) => onChange('search_fallback_strategy', e.target.value)}
                                className="h-10 w-full rounded-lg border border-border bg-card px-4 text-sm text-foreground transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="original">{__('Show original results if AI fails', 'agentflow-ai')}</option>
                                <option value="empty">{__('Show no results on failure', 'agentflow-ai')}</option>
                                <option value="cached">{__('Use last cached keywords', 'agentflow-ai')}</option>
                            </select>
                        </div>

                        {/* WooCommerce-specific */}
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-sm font-medium text-foreground">
                                    {__('Enhance WooCommerce Search', 'agentflow-ai')}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {__('Apply AI expansion to product search queries', 'agentflow-ai')}
                                </div>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    checked={settings.search_enhance_woo !== false}
                                    onChange={(e) => onChange('search_enhance_woo', e.target.checked)}
                                    className="peer sr-only"
                                />
                                <div className="h-6 w-11 rounded-full bg-muted after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-card after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-5 peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20" />
                            </label>
                        </div>

                        {/* Search by SKU */}
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-sm font-medium text-foreground">
                                    {__('Search Product Attributes', 'agentflow-ai')}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {__('Include SKU, tags, and attributes in search', 'agentflow-ai')}
                                </div>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    checked={!!settings.search_product_attributes}
                                    onChange={(e) => onChange('search_product_attributes', e.target.checked)}
                                    className="peer sr-only"
                                />
                                <div className="h-6 w-11 rounded-full bg-muted after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-card after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-5 peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20" />
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {/* Info Box - Full Width */}
            <div className="lg:col-span-2">
                <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="flex gap-3">
                        <span className="text-xl"></span>
                        <div className="text-sm text-foreground">
                            <p className="mb-2 font-semibold">{__('How AI Search Enhancement Works', 'agentflow-ai')}</p>
                            <ul className="list-inside list-disc space-y-1 text-muted-foreground">
                                <li>{__('When a user searches your site, the AI generates related keywords', 'agentflow-ai')}</li>
                                <li>{__('These keywords are added to the search query automatically', 'agentflow-ai')}</li>
                                <li>{__('Results are cached to avoid repeated AI calls for the same query', 'agentflow-ai')}</li>
                                <li>{__('Uses the internal Search Agent â€” no additional configuration needed', 'agentflow-ai')}</li>
                                <li>{__('Add custom synonyms to always include specific related terms', 'agentflow-ai')}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

SearchSettings.propTypes = {
    settings: PropTypes.object.isRequired,
    onChange: PropTypes.func.isRequired,
    onSave: PropTypes.func.isRequired,
    saving: PropTypes.bool,
};
