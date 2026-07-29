/**
 * ProactiveTriggers - Frontend Component
 *
 * Handles proactive engagement triggers:
 * - Auto-open after delay
 * - Exit intent detection
 * - Scroll depth tracking
 * - Time on page
 * - Cart abandonment (WooCommerce)
 * - Returning visitor greeting
 */
import { useCallback, useEffect, useRef, useState } from 'react';

const STORAGE_KEYS = {
    proactiveShown: 'swc_proactive_shown',
    exitIntentShown: 'swc_exit_intent_shown',
    scrollTriggered: 'swc_scroll_triggered',
    lastVisit: 'swc_last_visit',
    cartAbandonment: 'swc_cart_abandonment_shown',
};

/**
 * Hook for managing proactive triggers
 */
export function useProactiveTriggers({
    triggers = {},
    isWidgetOpen = false,
    onTrigger,
}) {
    const [activePopup, setActivePopup] = useState(null);
    const timersRef = useRef({});
    const scrollHandlerRef = useRef(null);

    /**
     * Show a proactive popup
     */
    const showPopup = useCallback((type, message) => {
        if (isWidgetOpen) return;
        setActivePopup({ type, message });
    }, [isWidgetOpen]);

    /**
     * Dismiss popup and optionally open widget
     */
    const dismissPopup = useCallback((openWidget = false) => {
        setActivePopup(null);
        if (openWidget && onTrigger) {
            onTrigger();
        }
    }, [onTrigger]);

    /**
     * Check if trigger was already shown in this session
     */
    const wasShown = useCallback((key) => {
        try {
            return localStorage.getItem(key) === 'true';
        } catch {
            return false;
        }
    }, []);

    /**
     * Mark trigger as shown
     */
    const markShown = useCallback((key) => {
        try {
            localStorage.setItem(key, 'true');
        } catch {
            // Ignore storage errors
        }
    }, []);

    /**
     * Auto-open after delay
     */
    useEffect(() => {
        if (!triggers.auto_open_enabled || isWidgetOpen) return;
        if (wasShown(STORAGE_KEYS.proactiveShown)) return;

        const delay = (triggers.auto_open_delay || 10) * 1000;

        timersRef.current.autoOpen = setTimeout(() => {
            markShown(STORAGE_KEYS.proactiveShown);
            showPopup('auto_open', triggers.auto_open_message || "Hi there! Can I help you with anything?");
        }, delay);

        return () => {
            if (timersRef.current.autoOpen) {
                clearTimeout(timersRef.current.autoOpen);
            }
        };
    }, [triggers, isWidgetOpen, wasShown, markShown, showPopup]);

    /**
     * Exit intent detection
     */
    useEffect(() => {
        if (!triggers.exit_intent_enabled || isWidgetOpen) return;
        if (wasShown(STORAGE_KEYS.exitIntentShown)) return;

        const handleMouseLeave = (e) => {
            // Only trigger when mouse leaves from the top
            if (e.clientY <= 5) {
                markShown(STORAGE_KEYS.exitIntentShown);
                showPopup(
                    'exit_intent',
                    triggers.exit_intent_message || "Wait! Before you go, can I help you find what you're looking for?"
                );
            }
        };

        document.addEventListener('mouseleave', handleMouseLeave);

        return () => {
            document.removeEventListener('mouseleave', handleMouseLeave);
        };
    }, [triggers, isWidgetOpen, wasShown, markShown, showPopup]);

    /**
     * Scroll depth tracking
     */
    useEffect(() => {
        if (!triggers.scroll_depth_enabled || isWidgetOpen) return;
        if (wasShown(STORAGE_KEYS.scrollTriggered)) return;

        const threshold = triggers.scroll_depth_percent || 50;

        const handleScroll = () => {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollPercent = (scrollTop / docHeight) * 100;

            if (scrollPercent >= threshold) {
                markShown(STORAGE_KEYS.scrollTriggered);
                showPopup(
                    'scroll_depth',
                    triggers.scroll_depth_message || "You seem interested! Need any help?"
                );
                // Remove listener after triggering
                window.removeEventListener('scroll', scrollHandlerRef.current);
            }
        };

        // Throttle scroll handler
        let lastCall = 0;
        scrollHandlerRef.current = () => {
            const now = Date.now();
            if (now - lastCall >= 200) {
                lastCall = now;
                handleScroll();
            }
        };

        window.addEventListener('scroll', scrollHandlerRef.current, { passive: true });

        return () => {
            if (scrollHandlerRef.current) {
                window.removeEventListener('scroll', scrollHandlerRef.current);
            }
        };
    }, [triggers, isWidgetOpen, wasShown, markShown, showPopup]);

    /**
     * Time on page trigger
     */
    useEffect(() => {
        if (!triggers.time_on_page_enabled || isWidgetOpen) return;

        const seconds = triggers.time_on_page_seconds || 30;

        timersRef.current.timeOnPage = setTimeout(() => {
            if (!isWidgetOpen) {
                showPopup(
                    'time_on_page',
                    triggers.time_on_page_message || "Still browsing? Let me know if you need anything!"
                );
            }
        }, seconds * 1000);

        return () => {
            if (timersRef.current.timeOnPage) {
                clearTimeout(timersRef.current.timeOnPage);
            }
        };
    }, [triggers, isWidgetOpen, showPopup]);

    /**
     * Cart abandonment (WooCommerce)
     */
    useEffect(() => {
        if (!triggers.cart_abandonment_enabled || isWidgetOpen) return;
        if (wasShown(STORAGE_KEYS.cartAbandonment)) return;

        // Check if we're on WooCommerce and have items in cart
        const checkCart = () => {
            const wc = window.wc_add_to_cart_params || window.woocommerce_params;
            if (!wc) return;

            // Listen for page unload with items in cart
            const handleBeforeUnload = (e) => {
                const cartHasItems = document.querySelector('.woocommerce-cart-fragment .cart-contents-count')?.textContent > 0;

                if (cartHasItems && !isWidgetOpen) {
                    markShown(STORAGE_KEYS.cartAbandonment);
                    showPopup(
                        'cart_abandonment',
                        triggers.cart_abandonment_message || "Don't forget your items! Need help completing your order?"
                    );
                }
            };

            window.addEventListener('beforeunload', handleBeforeUnload);

            return () => {
                window.removeEventListener('beforeunload', handleBeforeUnload);
            };
        };

        const cleanup = checkCart();
        return cleanup;
    }, [triggers, isWidgetOpen, wasShown, markShown, showPopup]);

    /**
     * Returning visitor greeting
     */
    useEffect(() => {
        if (!triggers.returning_visitor_greeting || isWidgetOpen) return;

        try {
            const lastVisit = localStorage.getItem(STORAGE_KEYS.lastVisit);
            const now = Date.now();

            // Update last visit time
            localStorage.setItem(STORAGE_KEYS.lastVisit, now.toString());

            // If visited before (at least 1 hour ago) and not already shown today
            if (lastVisit) {
                const hoursSinceLastVisit = (now - parseInt(lastVisit, 10)) / (1000 * 60 * 60);

                if (hoursSinceLastVisit >= 1 && hoursSinceLastVisit < 24 * 30) {
                    // Show returning visitor greeting after a short delay
                    timersRef.current.returning = setTimeout(() => {
                        showPopup(
                            'returning_visitor',
                            triggers.returning_visitor_greeting
                        );
                    }, 2000);
                }
            }
        } catch {
            // Ignore storage errors
        }

        return () => {
            if (timersRef.current.returning) {
                clearTimeout(timersRef.current.returning);
            }
        };
    }, [triggers, isWidgetOpen, showPopup]);

    // Cleanup all timers on unmount
    useEffect(() => {
        return () => {
            Object.values(timersRef.current).forEach((timer) => {
                if (timer) clearTimeout(timer);
            });
        };
    }, []);

    return {
        activePopup,
        dismissPopup,
    };
}

/**
 * Proactive Popup Component
 */
export function ProactivePopup({ popup, onDismiss, onChat, appearance = {} }) {
    if (!popup) return null;

    const icons = {
        auto_open: <svg className="w-8 h-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M10.05 4.575a1.575 1.575 0 10-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 013.15 0v1.5m-3.15 0l.075 5.925m3.075-5.925a1.575 1.575 0 20-3.15 0v3m3.15-3v1.5m0 6v-6a1.575 1.575 0 113.15 0v5.85l-2.925 8.925h-9.9l-1-7.2-2.1-.9a1.575 1.575 0 01.9-3l2.85 1.2 1.35 6" /></svg>,
        exit_intent: <svg className="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>,
        scroll_depth: <svg className="w-8 h-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>,
        time_on_page: <svg className="w-8 h-8 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
        cart_abandonment: <svg className="w-8 h-8 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>,
        returning_visitor: <svg className="w-8 h-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a2.25 2.25 0 00-1.551-1.551L15 6.75l1.19-.414a2.25 2.25 0 001.551-1.551L18 3.75l.259 1.035a2.25 2.25 0 001.551 1.551L21 6.75l-1.19.414a2.25 2.25 0 00-1.551 1.551z" /></svg>,
    };

    return (
        <div className="animate-slide-up z-10" style={{ position: 'fixed', bottom: '100px', right: '24px' }}>
            <div
                className="relative bg-white rounded-2xl shadow-xl border border-gray-200 p-4 max-w-xs"
                style={{ backgroundColor: appearance.color_bg_main || '#ffffff' }}
            >
                {/* Close button */}
                <button
                    onClick={() => onDismiss(false)}
                    className="absolute -top-2 -right-2 w-6 h-6 bg-gray-100 hover:bg-gray-200 rounded-full flex items-center justify-center text-gray-500 text-sm transition-colors"
                >
                    ×
                </button>

                {/* Icon */}
                <div className="mb-2">{icons[popup.type] || <svg className="w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>}</div>

                {/* Message */}
                <p
                    className="text-sm mb-3"
                    style={{ color: appearance.color_text_primary || '#1e293b' }}
                >
                    {popup.message}
                </p>

                {/* CTA Button */}
                <button
                    onClick={() => onDismiss(true)}
                    className="w-full px-4 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-all"
                    style={{ backgroundColor: appearance.color_primary || '#6366f1' }}
                >
                    Chat with us
                </button>
            </div>
        </div>
    );
}

export default {
    useProactiveTriggers,
    ProactivePopup,
};
