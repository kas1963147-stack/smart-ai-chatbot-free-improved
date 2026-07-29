/**
 * Chat Widget Entry Point
 *
 * Initializes the React-based chat widget and mounts it to the DOM.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import ChatWidget from './components/ChatWidget';
import { useProactiveTriggers, ProactivePopup } from './components/ProactiveTriggers';
import SDK from './utils/sdk';

// Import Tailwind CSS
import './tailwind.css';

/**
 * Enhanced Widget Wrapper with Proactive Triggers
 */
function WidgetWrapper({ config }) {
    const widgetRef = React.useRef(null);
    const [isOpen, setIsOpen] = React.useState(false);

    // Handle proactive triggers - behavior now contains trigger settings (with legacy fallback)
    const { activePopup, dismissPopup } = useProactiveTriggers({
        triggers: { ...(config.triggers || {}), ...(config.behavior || {}) },
        isWidgetOpen: isOpen,
        onTrigger: () => {
            widgetRef.current?.open?.();
        },
    });

    // Expose methods to SDK
    React.useEffect(() => {
        const instance = {
            open: () => {
                setIsOpen(true);
                widgetRef.current?.open?.();
            },
            close: () => {
                setIsOpen(false);
                widgetRef.current?.close?.();
            },
            toggle: () => {
                setIsOpen(prev => !prev);
                widgetRef.current?.toggle?.();
            },
            sendMessage: (text) => widgetRef.current?.sendMessage?.(text),
            clearHistory: () => widgetRef.current?.clearHistory?.(),
            get isOpen() { return isOpen; },
            get sessionId() { return widgetRef.current?.sessionId; },
            get visitorId() { return widgetRef.current?.visitorId; },
            get currentAgent() { return widgetRef.current?.currentAgent; },
            get messages() { return widgetRef.current?.messages || []; },
        };

        SDK._setInstance(instance);

        return () => {
            SDK._setInstance(null);
        };
    }, [isOpen]);

    return (
        <>
            <ChatWidget
                ref={widgetRef}
                config={config}
                onOpenChange={setIsOpen}
            />
            <ProactivePopup
                popup={activePopup}
                onDismiss={dismissPopup}
                onChat={() => dismissPopup(true)}
                appearance={config.appearance}
            />
        </>
    );
}

/**
 * Initialize the widget
 */
function initWidget() {
    // Get configuration from global
    const config = window.swcChatbotConfig || {};
    console.log('[SWC] Bot Config:', config);

    if (!config.apiUrl) {
        console.warn('[SwcChatbot] Missing apiUrl in configuration');
        return;
    }

    // Find or create container
    let container = document.getElementById('smart-ai-chatbot-root');
    if (!container) {
        container = document.createElement('div');
        container.id = 'smart-ai-chatbot-root';
        document.body.appendChild(container);
    }

    // Create React root and render
    const root = createRoot(container);
    root.render(<WidgetWrapper config={config} />);

    // Store root for cleanup
    window._swcChatbotRoot = root;
}

/**
 * Cleanup function
 */
function destroyWidget() {
    if (window._swcChatbotRoot) {
        window._swcChatbotRoot.unmount();
        delete window._swcChatbotRoot;
    }

    const container = document.getElementById('smart-ai-chatbot-root');
    if (container) {
        container.remove();
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWidget);
} else {
    initWidget();
}

// Expose init/destroy functions
export { initWidget, destroyWidget };
export default { initWidget, destroyWidget };
