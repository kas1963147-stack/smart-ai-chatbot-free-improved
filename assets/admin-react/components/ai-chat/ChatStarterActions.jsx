/**
 * Chat Starter Actions - Metronic v9 AI Style
 * Quick action cards dynamically based on selected agent
 */
import * as React from 'react';
import {
    FileText, Search, Settings, BarChart3,
    ShoppingCart, HelpCircle, MessageSquare, Package,
    TrendingUp, Globe, Pencil, Tag,
    Users, CreditCard, Truck, RefreshCw,
    Brain, Zap, Target, LineChart
} from 'lucide-react';
import { cn } from '../../ui/utils';

// Agent-specific action mappings
const AGENT_ACTIONS = {
    // SEO Agent
    seo: [
        { id: 'seo-audit', name: 'SEO Audit', description: 'Analyze page SEO', icon: Search },
        { id: 'meta-tags', name: 'Meta Tags', description: 'Optimize titles & descriptions', icon: Tag },
        { id: 'keywords', name: 'Keywords', description: 'Research keywords', icon: Target },
        { id: 'rankings', name: 'Rankings', description: 'Track positions', icon: TrendingUp },
    ],
    seo_optimizer: [
        { id: 'seo-audit', name: 'SEO Audit', description: 'Analyze page SEO', icon: Search },
        { id: 'meta-tags', name: 'Meta Tags', description: 'Optimize titles & descriptions', icon: Tag },
        { id: 'keywords', name: 'Keywords', description: 'Research keywords', icon: Target },
        { id: 'rankings', name: 'Rankings', description: 'Track positions', icon: TrendingUp },
    ],

    // Content Editor
    content: [
        { id: 'write-post', name: 'Write Post', description: 'Create blog content', icon: Pencil },
        { id: 'edit-page', name: 'Edit Page', description: 'Update page content', icon: FileText },
        { id: 'generate-ideas', name: 'Generate Ideas', description: 'Content suggestions', icon: Brain },
        { id: 'optimize-text', name: 'Optimize Text', description: 'Improve readability', icon: Zap },
    ],
    content_editor: [
        { id: 'write-post', name: 'Write Post', description: 'Create blog content', icon: Pencil },
        { id: 'edit-page', name: 'Edit Page', description: 'Update page content', icon: FileText },
        { id: 'generate-ideas', name: 'Generate Ideas', description: 'Content suggestions', icon: Brain },
        { id: 'optimize-text', name: 'Optimize Text', description: 'Improve readability', icon: Zap },
    ],

    // Customer Support
    customer_support: [
        { id: 'view-orders', name: 'View Orders', description: 'Customer orders', icon: ShoppingCart },
        { id: 'handle-returns', name: 'Returns', description: 'Process returns', icon: RefreshCw },
        { id: 'faq', name: 'FAQ', description: 'Common questions', icon: HelpCircle },
        { id: 'respond', name: 'Respond', description: 'Draft responses', icon: MessageSquare },
    ],
    support: [
        { id: 'view-orders', name: 'View Orders', description: 'Customer orders', icon: ShoppingCart },
        { id: 'handle-returns', name: 'Returns', description: 'Process returns', icon: RefreshCw },
        { id: 'faq', name: 'FAQ', description: 'Common questions', icon: HelpCircle },
        { id: 'respond', name: 'Respond', description: 'Draft responses', icon: MessageSquare },
    ],

    // Order Manager
    order_manager: [
        { id: 'pending-orders', name: 'Pending Orders', description: 'View pending', icon: Package },
        { id: 'process-order', name: 'Process Order', description: 'Update status', icon: Truck },
        { id: 'refunds', name: 'Refunds', description: 'Issue refunds', icon: CreditCard },
        { id: 'order-stats', name: 'Order Stats', description: 'View analytics', icon: LineChart },
    ],
    orders: [
        { id: 'pending-orders', name: 'Pending Orders', description: 'View pending', icon: Package },
        { id: 'process-order', name: 'Process Order', description: 'Update status', icon: Truck },
        { id: 'refunds', name: 'Refunds', description: 'Issue refunds', icon: CreditCard },
        { id: 'order-stats', name: 'Order Stats', description: 'View analytics', icon: LineChart },
    ],

    // Sales Assistant
    sales_assistant: [
        { id: 'recommend', name: 'Recommend', description: 'Product suggestions', icon: ShoppingCart },
        { id: 'upsell', name: 'Upsell', description: 'Cross-sell items', icon: TrendingUp },
        { id: 'customers', name: 'Customers', description: 'Customer insights', icon: Users },
        { id: 'promotions', name: 'Promotions', description: 'Create offers', icon: Tag },
    ],
    sales: [
        { id: 'recommend', name: 'Recommend', description: 'Product suggestions', icon: ShoppingCart },
        { id: 'upsell', name: 'Upsell', description: 'Cross-sell items', icon: TrendingUp },
        { id: 'customers', name: 'Customers', description: 'Customer insights', icon: Users },
        { id: 'promotions', name: 'Promotions', description: 'Create offers', icon: Tag },
    ],

    // Default / General
    default: [
        { id: 'create', name: 'Create content', description: 'Pages, posts & products', icon: FileText },
        { id: 'search', name: 'Search site', description: 'Find content & data', icon: Search },
        { id: 'settings', name: 'Update settings', description: 'Configure your site', icon: Settings },
        { id: 'analyze', name: 'Analyze data', description: 'Orders & analytics', icon: BarChart3 },
    ],
};

// Get actions for a specific agent
const getActionsForAgent = (agentId, agentName) => {
    if (!agentId && !agentName) return AGENT_ACTIONS.default;

    // Try to match by agent_id first
    const idLower = (agentId || '').toLowerCase();
    if (AGENT_ACTIONS[idLower]) return AGENT_ACTIONS[idLower];

    // Try to match by name patterns
    const nameLower = (agentName || '').toLowerCase();

    if (nameLower.includes('seo')) return AGENT_ACTIONS.seo;
    if (nameLower.includes('content') || nameLower.includes('editor') || nameLower.includes('writer')) return AGENT_ACTIONS.content;
    if (nameLower.includes('support') || nameLower.includes('customer')) return AGENT_ACTIONS.customer_support;
    if (nameLower.includes('order') || nameLower.includes('manager')) return AGENT_ACTIONS.order_manager;
    if (nameLower.includes('sales') || nameLower.includes('assistant')) return AGENT_ACTIONS.sales_assistant;

    return AGENT_ACTIONS.default;
};

export function ChatStarterActions({ onSelect, selectedAgent }) {
    // Get dynamic actions based on selected agent
    const actions = React.useMemo(() => {
        return getActionsForAgent(
            selectedAgent?.agent_id || selectedAgent?.id,
            selectedAgent?.name
        );
    }, [selectedAgent]);

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
            {actions.map((action) => {
                const Icon = action.icon;
                return (
                    <div
                        key={action.id}
                        className={cn(
                            'flex flex-row items-center gap-3 p-3 cursor-pointer',
                            'hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors',
                            'border border-dashed rounded-lg',
                            'bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-700'
                        )}
                        onClick={() => onSelect?.(action)}
                    >
                        <div className="flex items-center justify-center size-9 shrink-0 bg-gray-100 dark:bg-slate-700 rounded-lg">
                            <Icon className="size-4 text-gray-700 dark:text-white" />
                        </div>
                        <div className="flex flex-col gap-0.5 min-w-0">
                            <h3 className="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                {action.name}
                            </h3>
                            <p className="text-xs text-gray-500 dark:text-slate-400 truncate">
                                {action.description}
                            </p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export { AGENT_ACTIONS, getActionsForAgent };
