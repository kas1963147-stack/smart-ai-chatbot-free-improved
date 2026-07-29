/**
 * Loading Component - Premium Loading States
 * 
 * Beautiful, animated loading component with branded design for consistent UX.
 * Uses pure CSS animations for maximum compatibility.
 */
import { __ } from '@wordpress/i18n';

/**
 * Premium Branded Loader - Pure CSS animated orbit with gradient effects
 */
function PremiumSpinner({ size = 'md' }) {
    const sizeClasses = {
        sm: 'swc-loader--sm',
        md: 'swc-loader--md',
        lg: 'swc-loader--lg',
        xl: 'swc-loader--xl',
    };

    return (
        <div className={`swc-loader-container ${sizeClasses[size] || sizeClasses.md}`}>
            <div className="swc-loader-ring" />
            <div className="swc-loader-core" />
            <div className="swc-loader-particle swc-loader-particle--1" />
            <div className="swc-loader-particle swc-loader-particle--2" />
            <div className="swc-loader-particle swc-loader-particle--3" />
        </div>
    );
}

/**
 * Main Loading Component
 * @param {string} message - Loading message to display
 * @param {boolean} fullPage - If true, centers in viewport
 * @param {string} size - 'sm' | 'md' | 'lg' | 'xl'
 */
export default function Loading({
    message = __('Loading...', 'smart-woo-chatbot'),
    fullPage = false,
    size = 'md'
}) {
    if (fullPage) {
        return (
            <div className="swc-loading-fullpage">
                <PremiumSpinner size="lg" />
                {message && (
                    <p className="swc-loading-message swc-loading-message--shimmer">{message}</p>
                )}
            </div>
        );
    }

    return (
        <div className="swc-loading-inline">
            <PremiumSpinner size={size} />
            {message && (
                <p className="swc-loading-message">{message}</p>
            )}
        </div>
    );
}

/**
 * Inline Loading - For buttons, small areas
 */
export function InlineLoading({ message }) {
    return (
        <span className="inline-flex items-center gap-2">
            <PremiumSpinner size="sm" />
            {message && <span className="text-sm text-gray-500 dark:text-gray-400">{message}</span>}
        </span>
    );
}

/**
 * Dots Loading - Bouncing dots animation
 */
export function DotsLoading({ message }) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 py-8">
            <div className="flex gap-1.5">
                <span className="swc-dot-bounce" style={{ animationDelay: '0ms' }} />
                <span className="swc-dot-bounce" style={{ animationDelay: '150ms' }} />
                <span className="swc-dot-bounce" style={{ animationDelay: '300ms' }} />
            </div>
            {message && <p className="m-0 text-sm text-gray-500 dark:text-gray-400">{message}</p>}
        </div>
    );
}

/**
 * Skeleton Loading - For content placeholders
 */
export function Skeleton({ className = '', width = 'w-full', height = 'h-4' }) {
    return (
        <div className={`${width} ${height} bg-gray-200 dark:bg-gray-700 rounded animate-pulse ${className}`} />
    );
}

/**
 * Card Skeleton - For loading card content
 */
export function CardSkeleton() {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <Skeleton width="w-3/4" height="h-5" />
            <Skeleton width="w-full" height="h-4" />
            <Skeleton width="w-1/2" height="h-4" />
        </div>
    );
}

/**
 * Table Skeleton - For loading table content
 */
export function TableSkeleton({ rows = 5 }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div className="p-4 border-b border-gray-200 dark:border-gray-700">
                <Skeleton width="w-1/4" height="h-6" />
            </div>
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {[...Array(rows)].map((_, i) => (
                    <div key={i} className="p-4 flex items-center gap-4">
                        <Skeleton width="w-10" height="h-10" className="rounded-full flex-shrink-0" />
                        <div className="flex-1 space-y-2">
                            <Skeleton width="w-1/3" height="h-4" />
                            <Skeleton width="w-1/2" height="h-3" />
                        </div>
                        <Skeleton width="w-20" height="h-8" className="rounded-lg" />
                    </div>
                ))}
            </div>
        </div>
    );
}
