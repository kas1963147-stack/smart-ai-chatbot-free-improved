/**
 * LazyWrapper Component
 * 
 * Provides a consistent loading experience for lazy-loaded components.
 */
import { Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Loading from '../common/Loading';

/**
 * Loading skeleton for lazy-loaded pages (now uses spinner)
 */
export function PageLoadingSkeleton() {
    return <Loading message={__('Loading…', 'smart-woo-chatbot')} fullPage />;
}

/**
 * Compact loading spinner for smaller components
 */
export function LoadingSpinner({ size = 'md', message }) {
    const sizeMap = { sm: 'sm', md: 'md', lg: 'lg' };
    return <Loading message={message} size={sizeMap[size] || 'md'} />;
}

/**
 * LazyWrapper - Wraps lazy-loaded components with Suspense
 */
export default function LazyWrapper({ children, fallback }) {
    return (
        <Suspense fallback={fallback || <PageLoadingSkeleton />}>
            {children}
        </Suspense>
    );
}

