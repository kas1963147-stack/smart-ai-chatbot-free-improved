/**
 * Metronic v9 Style Tabs Component
 * 
 * Premium tabs with modern styling - horizontal layout with pill/underline variants.
 */
import { useState } from '@wordpress/element';
import PropTypes from 'prop-types';
import { cn } from '../../ui';

export default function MetronicTabs({
    tabs,
    activeId,
    onChange,
    variant = 'pills', // 'pills' | 'underline' | 'boxed'
    size = 'md', // 'sm' | 'md' | 'lg'
    fullWidth = false,
    className = '',
}) {
    const [hoveredId, setHoveredId] = useState(null);

    const sizeClasses = {
        sm: 'px-3 py-1 text-xs',
        md: 'px-4 py-2 text-sm',
        lg: 'px-5 py-2.5 text-base',
    };

    const variantClasses = {
        pills: {
            base: 'rounded-full border border-transparent',
            active: 'bg-primary text-primary-foreground shadow-sm',
            idle: 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
        },
        underline: {
            base: 'rounded-none border-b-2 border-transparent',
            active: 'border-primary text-slate-900 dark:text-slate-100',
            idle: 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100',
        },
        boxed: {
            base: 'rounded-lg border border-slate-200 dark:border-slate-800',
            active: 'bg-slate-900 text-white dark:bg-white dark:text-slate-900',
            idle: 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
        },
    };

    const variantStyle = variantClasses[variant] || variantClasses.pills;

    return (
        <div className={cn('w-full', className)}>
            <div className="flex flex-wrap items-center gap-2" role="tablist">
                {tabs.map((tab) => {
                    const isActive = activeId === tab.id;
                    const isHovered = hoveredId === tab.id;

                    return (
                        <button
                            key={tab.id}
                            role="tab"
                            aria-selected={isActive}
                            aria-controls={`panel-${tab.id}`}
                            className={cn(
                                'inline-flex items-center gap-2 font-medium transition',
                                sizeClasses[size] || sizeClasses.md,
                                fullWidth ? 'flex-1 justify-center' : '',
                                variantStyle.base,
                                isActive ? variantStyle.active : variantStyle.idle,
                                isHovered && !isActive ? 'opacity-90' : '',
                                tab.disabled ? 'cursor-not-allowed opacity-50' : ''
                            )}
                            onClick={() => !tab.disabled && onChange?.(tab.id)}
                            onMouseEnter={() => setHoveredId(tab.id)}
                            onMouseLeave={() => setHoveredId(null)}
                            disabled={tab.disabled}
                        >
                            {tab.icon && (
                                <span className="text-base">{tab.icon}</span>
                            )}
                            <span>{tab.label}</span>
                            {tab.badge && (
                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {tab.badge}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

MetronicTabs.propTypes = {
    tabs: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.string.isRequired,
            label: PropTypes.node.isRequired,
            icon: PropTypes.node,
            badge: PropTypes.node,
            disabled: PropTypes.bool,
        })
    ).isRequired,
    activeId: PropTypes.string,
    onChange: PropTypes.func,
    variant: PropTypes.oneOf(['pills', 'underline', 'boxed']),
    size: PropTypes.oneOf(['sm', 'md', 'lg']),
    fullWidth: PropTypes.bool,
    className: PropTypes.string,
};
