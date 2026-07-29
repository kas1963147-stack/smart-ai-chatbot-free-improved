/**
 * InternalMcpManager Component
 *
 * Per-agent control over which internal WordPress tools this agent can use.
 * Matches the UI pattern from McpToolSelector (Settings page) for consistency.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
    Server,
    ChevronDown,
    ChevronRight,
    Check,
    X,
    Zap,
    ShoppingCart,
    FileText,
    Users,
    Image,
    Tag,
    Settings,
    Package,
    Menu,
    MessageSquare,
    File,
    Receipt,
    Truck,
    Percent,
    BarChart2,
    Sliders,
    UserCheck,
    ShoppingBag,
    Database,
    Search,
} from 'lucide-react';
import PropTypes from 'prop-types';
import { Button, Checkbox, Tabs, TextField, cn } from './ui';

// Icon mapping for categories (same as McpToolSelector)
const CATEGORY_ICONS = {
    core: Zap,
    posts: FileText,
    pages: File,
    comments: MessageSquare,
    users: Users,
    media: Image,
    taxonomies: Tag,
    options: Settings,
    plugins: Package,
    menus: Menu,
    system: Server,
    wc_products: ShoppingBag,
    wc_orders: ShoppingCart,
    wc_customers: UserCheck,
    wc_coupons: Percent,
    wc_shipping: Truck,
    wc_tax: Receipt,
    wc_reports: BarChart2,
    wc_settings: Sliders,
    custom_fields_acf: Database,
    seo_yoast: Search,
};

// Core category IDs
const CORE_CATEGORIES = [
    'core', 'posts', 'pages', 'comments', 'users', 'media',
    'taxonomies', 'options', 'plugins', 'menus', 'system',
];
const WOO_CATEGORIES = [
    'wc_products', 'wc_orders', 'wc_customers', 'wc_coupons',
    'wc_shipping', 'wc_tax', 'wc_reports', 'wc_settings',
];
const ALL_CORE_CATEGORIES = [...CORE_CATEGORIES, ...WOO_CATEGORIES];

// Category groupings for tabs
const CATEGORY_GROUPS = {
    all: { label: 'All', categories: null },
    wordpress: { label: 'WordPress', categories: CORE_CATEGORIES },
    woocommerce: { label: 'WooCommerce', categories: WOO_CATEGORIES },
    extensions: { label: 'Extensions', categories: null, isExtensionTab: true },
};

export default function InternalMcpManager({
    internalMcpConfig = {},
    onConfigChange,
}) {
    const [loading, setLoading] = useState(true);
    const [categories, setCategories] = useState({});
    const [expandedCategories, setExpandedCategories] = useState({});
    const [searchQuery, setSearchQuery] = useState('');
    const [activeTab, setActiveTab] = useState('all');

    // Internal MCP config structure
    const config = {
        enabled: internalMcpConfig.enabled ?? true,
        mode: internalMcpConfig.mode ?? 'all',
        profile: internalMcpConfig.profile ?? 'full_access',
        enabled_tools: internalMcpConfig.enabled_tools ?? [],
        disabled_tools: internalMcpConfig.disabled_tools ?? [],
    };

    useEffect(() => {
        loadTools();
    }, []);

    const loadTools = async () => {
        try {
            const response = await apiFetch({
                path: '/smart-ai-chatbot/v1/mcp-server/tools',
                method: 'GET',
            });
            if (response.success && response.data.categories) {
                setCategories(response.data.categories);
            }
        } catch (err) {
            console.error('Failed to load MCP tools:', err);
        } finally {
            setLoading(false);
        }
    };

    const updateConfig = (updates) => {
        onConfigChange({ ...config, ...updates });
    };

    const toggleCategoryExpand = (categoryId) => {
        setExpandedCategories((prev) => ({
            ...prev,
            [categoryId]: !prev[categoryId],
        }));
    };

    const isToolEnabled = (toolName) => {
        if (config.mode === 'all') return true;
        if (config.mode === 'none') return false;
        if (config.mode === 'whitelist' || config.mode === 'selected') {
            return config.enabled_tools.includes(toolName);
        }
        if (config.mode === 'custom') {
            if (config.enabled_tools.length > 0) {
                return config.enabled_tools.includes(toolName);
            }
            return !config.disabled_tools.includes(toolName);
        }
        return true;
    };

    const toggleTool = (toolName) => {
        const currentlyEnabled = isToolEnabled(toolName);

        if (config.mode !== 'custom' && config.mode !== 'whitelist' && config.mode !== 'selected' && config.mode !== 'whitelist' && config.mode !== 'selected') {
            // Switch to custom mode when toggling individual tools
            // Build initial enabled_tools list based on current state
            const allTools = [];
            Object.values(categories).forEach((cat) => {
                (cat.tools || []).forEach((tool) => allTools.push(tool.name));
            });

            if (config.mode === 'all') {
                // Was all enabled, now disabling one
                updateConfig({
                    mode: 'custom',
                    enabled_tools: allTools.filter((t) => t !== toolName),
                    disabled_tools: [],
                });
            } else {
                // Was none, now enabling one
                updateConfig({
                    mode: 'custom',
                    enabled_tools: [toolName],
                    disabled_tools: [],
                });
            }
        } else {
            // Already in custom mode - use whitelist
            const newEnabled = currentlyEnabled
                ? config.enabled_tools.filter((t) => t !== toolName)
                : [...config.enabled_tools, toolName];
            updateConfig({
                enabled_tools: newEnabled,
                disabled_tools: [],
            });
        }
    };

    const enableCategoryTools = (tools) => {
        const toolNames = tools.map((t) => t.name);
        if (config.mode !== 'custom' && config.mode !== 'whitelist' && config.mode !== 'selected') {
            // Switch to custom, enable all then add these
            const allTools = [];
            Object.values(categories).forEach((cat) => {
                (cat.tools || []).forEach((tool) => allTools.push(tool.name));
            });
            updateConfig({
                mode: 'custom',
                enabled_tools: allTools,
                disabled_tools: [],
            });
        } else {
            const newEnabled = [...new Set([...config.enabled_tools, ...toolNames])];
            updateConfig({
                enabled_tools: newEnabled,
                disabled_tools: [],
            });
        }
    };

    const disableCategoryTools = (tools) => {
        const toolNames = tools.map((t) => t.name);
        if (config.mode !== 'custom' && config.mode !== 'whitelist' && config.mode !== 'selected') {
            // Switch to custom, start with all enabled then remove these
            const allTools = [];
            Object.values(categories).forEach((cat) => {
                (cat.tools || []).forEach((tool) => allTools.push(tool.name));
            });
            updateConfig({
                mode: 'custom',
                enabled_tools: allTools.filter((t) => !toolNames.includes(t)),
                disabled_tools: [],
            });
        } else {
            updateConfig({
                enabled_tools: config.enabled_tools.filter((t) => !toolNames.includes(t)),
                disabled_tools: [],
            });
        }
    };

    const enableAllInView = () => {
        const allToolNames = [];
        filteredCategories.forEach(([, cat]) => {
            (cat.tools || []).forEach((tool) => allToolNames.push(tool.name));
        });

        if (config.mode !== 'custom' && config.mode !== 'whitelist' && config.mode !== 'selected') {
            updateConfig({
                mode: 'custom',
                enabled_tools: allToolNames,
                disabled_tools: [],
            });
        } else {
            const newEnabled = [...new Set([...config.enabled_tools, ...allToolNames])];
            updateConfig({
                enabled_tools: newEnabled,
                disabled_tools: [],
            });
        }
    };

    const disableAllInView = () => {
        const toolNamesToDisable = [];
        filteredCategories.forEach(([, cat]) => {
            (cat.tools || []).forEach((tool) => toolNamesToDisable.push(tool.name));
        });

        if (config.mode !== 'custom' && config.mode !== 'whitelist' && config.mode !== 'selected') {
            // Go from all to custom with these disabled
            const allTools = [];
            Object.values(categories).forEach((cat) => {
                (cat.tools || []).forEach((tool) => allTools.push(tool.name));
            });
            updateConfig({
                mode: 'custom',
                enabled_tools: allTools.filter((t) => !toolNamesToDisable.includes(t)),
                disabled_tools: [],
            });
        } else {
            updateConfig({
                enabled_tools: config.enabled_tools.filter((t) => !toolNamesToDisable.includes(t)),
                disabled_tools: [],
            });
        }
    };

    // Filter categories by tab and search
    const filteredCategories = useMemo(() => {
        let result = Object.entries(categories);

        // Filter by tab
        const group = CATEGORY_GROUPS[activeTab];
        if (group) {
            if (group.isExtensionTab) {
                result = result.filter(([catId]) => !ALL_CORE_CATEGORIES.includes(catId));
            } else if (group.categories) {
                result = result.filter(([catId]) => group.categories.includes(catId));
            }
        }

        // Filter by search
        if (searchQuery.trim()) {
            const query = searchQuery.toLowerCase();
            result = result
                .map(([catId, cat]) => {
                    const matchingTools = (cat.tools || []).filter(
                        (tool) =>
                            tool.name.toLowerCase().includes(query) ||
                            tool.description?.toLowerCase().includes(query)
                    );
                    if (matchingTools.length > 0) {
                        return [catId, { ...cat, tools: matchingTools }];
                    }
                    return null;
                })
                .filter(Boolean);
        }

        return result;
    }, [categories, activeTab, searchQuery]);

    // Count stats
    const stats = useMemo(() => {
        let total = 0;
        let enabled = 0;
        Object.values(categories).forEach((cat) => {
            (cat.tools || []).forEach((tool) => {
                total++;
                if (config.enabled && isToolEnabled(tool.name)) enabled++;
            });
        });
        return { total, enabled };
    }, [categories, config.enabled, config.mode, config.enabled_tools, config.disabled_tools]);

    // Check if extensions exist
    const hasExtensions = useMemo(() => {
        return Object.keys(categories).some(
            (catId) => !ALL_CORE_CATEGORIES.includes(catId)
        );
    }, [categories]);

    // Build tabs
    const tabs = useMemo(() => {
        return Object.entries(CATEGORY_GROUPS)
            .filter(([, group]) => {
                if (group.isExtensionTab && !hasExtensions) return false;
                return true;
            })
            .map(([id, group]) => ({
                id,
                label: group.label,
            }));
    }, [hasExtensions]);

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center py-12">
                <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
                <p className="text-gray-500">
                    {__('Loading internal MCP tools...', 'smart-woo-chatbot')}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Simple Card Header */}
            <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                {__('Manage Tools', 'smart-woo-chatbot')}
                            </h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {__('Select which tools to expose via the MCP server', 'smart-woo-chatbot')}
                            </p>
                        </div>
                        <label className="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                checked={config.enabled}
                                onChange={(e) => updateConfig({ enabled: e.target.checked })}
                                className="sr-only peer"
                            />
                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>
                </div>

                {config.enabled && (
                    <div className="p-6 space-y-4">
                        {/* Search and Actions */}
                        <div className="flex flex-col gap-3 md:flex-row md:items-center">
                            <div className="flex-1 min-w-0">
                                <TextField
                                    value={searchQuery}
                                    onChange={setSearchQuery}
                                    placeholder={__('Search tools...', 'smart-woo-chatbot')}
                                    className="w-full"
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="primary"
                                    size="sm"
                                    onClick={enableAllInView}
                                >
                                    {__('Enable All', 'smart-woo-chatbot')}
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={disableAllInView}
                                >
                                    {__('Disable All', 'smart-woo-chatbot')}
                                </Button>
                            </div>
                        </div>

                        {/* Category Tabs */}
                        <Tabs tabs={tabs} activeId={activeTab} onChange={setActiveTab} />

                        {/* Category Cards */}
                        <div className="space-y-4">
                            {filteredCategories.map(([categoryId, category]) => {
                                const Icon = CATEGORY_ICONS[categoryId] || Package;
                                const tools = category.tools || [];
                                const enabledCount = tools.filter(
                                    (t) => isToolEnabled(t.name)
                                ).length;
                                const isExpanded = expandedCategories[categoryId];

                                return (
                                    <div
                                        key={categoryId}
                                        className={cn(
                                            'rounded-xl border transition',
                                            enabledCount === tools.length
                                                ? 'border-primary/60 bg-primary/5'
                                                : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950'
                                        )}
                                    >
                                        {/* Category Header */}
                                        <div
                                            className="flex items-center justify-between px-4 py-3 cursor-pointer"
                                            onClick={() => toggleCategoryExpand(categoryId)}
                                        >
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    checked={enabledCount === tools.length}
                                                    indeterminate={
                                                        enabledCount > 0 &&
                                                        enabledCount < tools.length
                                                    }
                                                    onChange={() => {
                                                        if (enabledCount === tools.length) {
                                                            disableCategoryTools(tools);
                                                        } else {
                                                            enableCategoryTools(tools);
                                                        }
                                                    }}
                                                    onClick={(e) => e.stopPropagation()}
                                                />
                                                <Icon
                                                    className={cn(
                                                        'w-5 h-5',
                                                        enabledCount > 0
                                                            ? 'text-primary'
                                                            : 'text-slate-400'
                                                    )}
                                                />
                                                <div>
                                                    <span
                                                        className={cn(
                                                            'font-semibold',
                                                            enabledCount > 0
                                                                ? 'text-primary'
                                                                : 'text-slate-900 dark:text-slate-100'
                                                        )}
                                                    >
                                                        {category.label || category.info?.name || categoryId}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'ml-2 rounded-md px-1.5 py-0.5 text-[10px] font-medium',
                                                            enabledCount > 0
                                                                ? 'bg-primary/10 text-primary'
                                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                                        )}
                                                    >
                                                        {enabledCount}/{tools.length}{' '}
                                                        {__('tools', 'smart-woo-chatbot')}
                                                    </span>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                className="text-slate-400 hover:text-slate-600"
                                            >
                                                {isExpanded ? (
                                                    <ChevronDown className="w-5 h-5" />
                                                ) : (
                                                    <ChevronRight className="w-5 h-5" />
                                                )}
                                            </button>
                                        </div>

                                        {/* Expanded Tools */}
                                        {isExpanded && (
                                            <div className="border-t border-slate-100 dark:border-slate-800 px-4 py-3">
                                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                    {tools.map((tool) => {
                                                        const isEnabled = isToolEnabled(tool.name);
                                                        return (
                                                            <div
                                                                key={tool.name}
                                                                onClick={() => toggleTool(tool.name)}
                                                                className={cn(
                                                                    'flex items-start gap-2 p-2 rounded-lg cursor-pointer transition',
                                                                    isEnabled
                                                                        ? 'bg-green-50 dark:bg-green-900/20'
                                                                        : 'bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100'
                                                                )}
                                                            >
                                                                <div className="mt-0.5">
                                                                    {isEnabled ? (
                                                                        <Check className="w-4 h-4 text-green-600" />
                                                                    ) : (
                                                                        <X className="w-4 h-4 text-slate-400" />
                                                                    )}
                                                                </div>
                                                                <div className="min-w-0 flex-1">
                                                                    <div
                                                                        className={cn(
                                                                            'text-xs font-medium truncate',
                                                                            isEnabled
                                                                                ? 'text-green-700 dark:text-green-400'
                                                                                : 'text-slate-600 dark:text-slate-400'
                                                                        )}
                                                                    >
                                                                        {tool.name}
                                                                    </div>
                                                                    <div className="text-[10px] text-slate-500 truncate">
                                                                        {tool.description}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        {/* Footer Stats */}
                        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 pt-2">
                            <div className="flex items-center justify-center w-6 h-6 rounded-full bg-cyan-100 dark:bg-cyan-900/30">
                                <Server className="w-3.5 h-3.5 text-cyan-600 dark:text-cyan-400" />
                            </div>
                            <span>
                                <strong className="text-gray-900 dark:text-white">
                                    {stats.enabled}
                                </strong>{' '}
                                / {stats.total}{' '}
                                {__('tools enabled for this agent', 'smart-woo-chatbot')}
                            </span>
                        </div>
                    </div>
                )}

                {!config.enabled && (
                    <div className="p-6 text-center text-gray-500 dark:text-gray-400">
                        <p>
                            {__(
                                'Internal MCP tools are disabled for this agent. Enable to give the agent access to WordPress tools.',
                                'smart-woo-chatbot'
                            )}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}

InternalMcpManager.propTypes = {
    internalMcpConfig: PropTypes.shape({
        enabled: PropTypes.bool,
        mode: PropTypes.string,
        profile: PropTypes.string,
        enabled_tools: PropTypes.array,
        disabled_tools: PropTypes.array,
    }),
    onConfigChange: PropTypes.func.isRequired,
};
