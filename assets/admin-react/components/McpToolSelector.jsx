/**
 * McpToolSelector Component
 *
 * Select/deselect MCP tools for the server.
 * Uses the same UI pattern as ToolkitManager with category cards.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
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
    Puzzle,
} from 'lucide-react';
import { Button, Checkbox, Tabs, TextField, cn } from './ui';

// Icon mapping for categories
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
    // Extension icons
    custom_fields_acf: Database,
    seo_yoast: Search,
};

// Core category IDs (non-extension)
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
    wordpress: {
        label: 'WordPress',
        categories: CORE_CATEGORIES,
    },
    woocommerce: {
        label: 'WooCommerce',
        categories: WOO_CATEGORIES,
    },
    extensions: {
        label: 'Extensions',
        categories: null, // Dynamic - any category not in core/woo
        isExtensionTab: true,
    },
};

export default function McpToolSelector({ onStatsChange }) {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [categories, setCategories] = useState({});
    const [toolState, setToolState] = useState({});
    const [expandedCategories, setExpandedCategories] = useState({});
    const [searchQuery, setSearchQuery] = useState('');
    const [activeTab, setActiveTab] = useState('all');

    useEffect(() => {
        loadTools();
    }, []);

    const loadTools = async () => {
        try {
            const response = await apiFetch({
                path: '/quark-agentflow-ai/v1/mcp-server/tools',
                method: 'GET',
            });
            if (response.success && response.data.categories) {
                setCategories(response.data.categories);
                // Build initial tool state from default values
                const state = {};
                Object.values(response.data.categories).forEach((cat) => {
                    (cat.tools || []).forEach((tool) => {
                        state[tool.name] = tool.default ?? true;
                    });
                });
                setToolState(state);
                if (onStatsChange && response.data.stats) {
                    onStatsChange(response.data.stats);
                }
            }
        } catch (err) {
            console.error('Failed to load MCP tools:', err);
        } finally {
            setLoading(false);
        }
    };

    const saveToolState = async (newState) => {
        setSaving(true);
        try {
            const response = await apiFetch({
                path: '/quark-agentflow-ai/v1/mcp-server/tools',
                method: 'POST',
                data: { tools: newState },
            });
            if (response.success && onStatsChange && response.data.stats) {
                onStatsChange(response.data.stats);
            }
        } catch (err) {
            console.error('Failed to save tool state:', err);
        } finally {
            setSaving(false);
        }
    };

    const toggleTool = (toolName) => {
        const newState = { ...toolState, [toolName]: !toolState[toolName] };
        setToolState(newState);
        saveToolState(newState);
    };

    const toggleCategory = (categoryId) => {
        setExpandedCategories((prev) => ({
            ...prev,
            [categoryId]: !prev[categoryId],
        }));
    };

    const enableCategoryTools = (categoryId, tools) => {
        const newState = { ...toolState };
        tools.forEach((tool) => {
            newState[tool.name] = true;
        });
        setToolState(newState);
        saveToolState(newState);
    };

    const disableCategoryTools = (categoryId, tools) => {
        const newState = { ...toolState };
        tools.forEach((tool) => {
            newState[tool.name] = false;
        });
        setToolState(newState);
        saveToolState(newState);
    };

    const enableAllInView = () => {
        const newState = { ...toolState };
        filteredCategories.forEach(([catId, cat]) => {
            (cat.tools || []).forEach((tool) => {
                newState[tool.name] = true;
            });
        });
        setToolState(newState);
        saveToolState(newState);
    };

    const disableAllInView = () => {
        const newState = { ...toolState };
        filteredCategories.forEach(([catId, cat]) => {
            (cat.tools || []).forEach((tool) => {
                newState[tool.name] = false;
            });
        });
        setToolState(newState);
        saveToolState(newState);
    };

    // Filter categories by tab and search
    const filteredCategories = useMemo(() => {
        let result = Object.entries(categories);

        // Filter by tab
        const group = CATEGORY_GROUPS[activeTab];
        if (group) {
            if (group.isExtensionTab) {
                // Extensions tab: show only non-core/non-woo categories
                result = result.filter(([catId]) =>
                    !ALL_CORE_CATEGORIES.includes(catId)
                );
            } else if (group.categories) {
                // Specific category list
                result = result.filter(([catId]) =>
                    group.categories.includes(catId)
                );
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
                if (toolState[tool.name]) enabled++;
            });
        });
        return { total, enabled };
    }, [categories, toolState]);

    // Check if extensions exist
    const hasExtensions = useMemo(() => {
        return Object.keys(categories).some(
            (catId) => !ALL_CORE_CATEGORIES.includes(catId)
        );
    }, [categories]);

    // Build tabs, only showing Extensions if any exist
    const tabs = useMemo(() => {
        return Object.entries(CATEGORY_GROUPS)
            .filter(([id, group]) => {
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
                    {__('Loading MCP tools...', 'agentflow-ai')}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Search and Actions */}
            <div className="flex flex-col gap-3 md:flex-row md:items-center">
                <div className="flex-1 min-w-0">
                    <TextField
                        value={searchQuery}
                        onChange={setSearchQuery}
                        placeholder={__('Search tools...', 'agentflow-ai')}
                        className="w-full"
                    />
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={enableAllInView}
                        disabled={saving}
                    >
                        {__('Enable All', 'agentflow-ai')}
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={disableAllInView}
                        disabled={saving}
                    >
                        {__('Disable All', 'agentflow-ai')}
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
                        (t) => toolState[t.name]
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
                                onClick={() => toggleCategory(categoryId)}
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
                                                disableCategoryTools(
                                                    categoryId,
                                                    tools
                                                );
                                            } else {
                                                enableCategoryTools(
                                                    categoryId,
                                                    tools
                                                );
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
                                            {category.label}
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
                                            {__('tools', 'agentflow-ai')}
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
                                            const isEnabled =
                                                toolState[tool.name];
                                            return (
                                                <div
                                                    key={tool.name}
                                                    onClick={() =>
                                                        toggleTool(tool.name)
                                                    }
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
                    {__('tools enabled for MCP server', 'agentflow-ai')}
                </span>
                {saving && (
                    <span className="text-xs text-amber-600 ml-2">
                        {__('Saving...', 'agentflow-ai')}
                    </span>
                )}
            </div>
        </div>
    );
}

McpToolSelector.propTypes = {
    onStatsChange: PropTypes.func,
};
