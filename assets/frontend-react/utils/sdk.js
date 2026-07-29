/**
 * Widget SDK - Public API
 *
 * Exposes a clean public API for the chat widget that can be used
 * by external scripts and integrations.
 */

let widgetInstance = null;
let eventListeners = {};

/**
 * Widget SDK
 */
const SwcChatbotSDK = {
    // Version
    version: '2.0.0',

    /**
     * Set the widget instance (called internally)
     */
    _setInstance(instance) {
        widgetInstance = instance;
        this._dispatchEvent('ready', { version: this.version });
    },

    /**
     * Open the chat widget
     */
    open() {
        if (widgetInstance?.open) {
            widgetInstance.open();
            return true;
        }
        console.warn('[SwcChatbot] Widget not initialized yet');
        return false;
    },

    /**
     * Close the chat widget
     */
    close() {
        if (widgetInstance?.close) {
            widgetInstance.close();
            return true;
        }
        return false;
    },

    /**
     * Toggle the chat widget
     */
    toggle() {
        if (widgetInstance?.toggle) {
            widgetInstance.toggle();
            return true;
        }
        return false;
    },

    /**
     * Send a message programmatically
     */
    sendMessage(text) {
        if (!text || typeof text !== 'string') {
            console.error('[SwcChatbot] sendMessage requires a string argument');
            return false;
        }
        if (widgetInstance?.sendMessage) {
            widgetInstance.sendMessage(text);
            return true;
        }
        return false;
    },

    /**
     * Get current session ID
     */
    getSessionId() {
        return widgetInstance?.sessionId || null;
    },

    /**
     * Get visitor ID
     */
    getVisitorId() {
        return widgetInstance?.visitorId || null;
    },

    /**
     * Get current agent info
     */
    getCurrentAgent() {
        return widgetInstance?.currentAgent || null;
    },

    /**
     * Check if widget is currently open
     */
    isOpen() {
        return widgetInstance?.isOpen || false;
    },

    /**
     * Get chat history
     */
    getMessages() {
        return widgetInstance?.messages || [];
    },

    /**
     * Clear chat history
     */
    clearHistory() {
        if (widgetInstance?.clearHistory) {
            widgetInstance.clearHistory();
            return true;
        }
        return false;
    },

    /**
     * Set user context/metadata
     */
    setUserContext(context) {
        if (typeof context !== 'object') {
            console.error('[SwcChatbot] setUserContext requires an object');
            return false;
        }
        if (widgetInstance?.setUserContext) {
            widgetInstance.setUserContext(context);
            return true;
        }
        // Store for later if widget not ready
        if (!window._swcPendingContext) {
            window._swcPendingContext = {};
        }
        Object.assign(window._swcPendingContext, context);
        return true;
    },

    /**
     * Add event listener
     */
    on(event, callback) {
        if (typeof callback !== 'function') {
            console.error('[SwcChatbot] Event callback must be a function');
            return this;
        }
        if (!eventListeners[event]) {
            eventListeners[event] = [];
        }
        eventListeners[event].push(callback);
        return this;
    },

    /**
     * Remove event listener
     */
    off(event, callback) {
        if (!eventListeners[event]) return this;

        if (callback) {
            eventListeners[event] = eventListeners[event].filter(cb => cb !== callback);
        } else {
            delete eventListeners[event];
        }
        return this;
    },

    /**
     * Dispatch an event
     */
    _dispatchEvent(event, data = {}) {
        // Call registered listeners
        if (eventListeners[event]) {
            eventListeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (err) {
                    console.error(`[SwcChatbot] Error in ${event} listener:`, err);
                }
            });
        }

        // Also dispatch DOM event for external listeners
        const customEvent = new CustomEvent(`swcChatbot:${event}`, {
            detail: data,
            bubbles: true,
        });
        document.dispatchEvent(customEvent);
    },

    /**
     * Show a proactive message
     */
    showProactiveMessage(message, options = {}) {
        if (!message) return false;

        if (widgetInstance?.showProactiveMessage) {
            widgetInstance.showProactiveMessage(message, options);
            return true;
        }
        return false;
    },

    /**
     * Update widget appearance
     */
    updateAppearance(settings) {
        if (typeof settings !== 'object') return false;

        if (widgetInstance?.updateAppearance) {
            widgetInstance.updateAppearance(settings);
            return true;
        }
        return false;
    },

    /**
     * Switch to a different agent
     */
    switchAgent(agentId) {
        if (!agentId) return false;

        if (widgetInstance?.switchAgent) {
            widgetInstance.switchAgent(agentId);
            return true;
        }
        return false;
    },

    /**
     * Wait for widget to be ready
     */
    ready(callback) {
        if (widgetInstance) {
            callback(this);
        } else {
            this.on('ready', () => callback(this));
        }
        return this;
    },

    /**
     * Destroy widget instance
     */
    destroy() {
        if (widgetInstance?.destroy) {
            widgetInstance.destroy();
        }
        widgetInstance = null;
        eventListeners = {};
    },
};

// Expose to window
if (typeof window !== 'undefined') {
    window.swcChatbot = SwcChatbotSDK;

    // Dispatch ready event when SDK is loaded
    document.dispatchEvent(new CustomEvent('swcChatbotSdkLoaded', {
        detail: { version: SwcChatbotSDK.version }
    }));
}

export default SwcChatbotSDK;
