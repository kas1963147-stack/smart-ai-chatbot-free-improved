/**
 * Search Enhancement Visuals
 * 
 * Adds visual indicators to search inputs when AI search enhancement is active.
 * Uses MutationObserver to catch dynamically loaded search bars (modals, infinite scroll, etc).
 */
document.addEventListener('DOMContentLoaded', function () {
    // Check if enhancement is enabled (passed via wp_localize_script)
    if (typeof swcSearchEnhancement === 'undefined' || !swcSearchEnhancement.enabled) {
        return;
    }

    // Configuration
    const CONFIG = {
        selectors: ['input[name="s"]', 'input.search-field', 'input[type="search"]'],
        icon: '✨',
        tooltip: 'AI Enhanced Search Active',
        class: 'swc-ai-enhanced-search'
    };

    /**
     * enhanceInput
     * Adds visual cues to a specific input element
     */
    const enhanceInput = (input) => {
        // Validation: Must be an input, visible, and not already enhanced
        if (input.tagName !== 'INPUT' || input.dataset.swcEnhanced) return;

        // Skip hidden inputs
        if (input.type === 'hidden') return;

        // Skip standard admin interface (only enhance frontend styling)
        if (document.body.classList.contains('wp-admin')) return;

        // Mark as processed
        input.classList.add(CONFIG.class);
        input.dataset.swcEnhanced = 'true';

        // Add tooltip title for accessibility/hover
        if (!input.getAttribute('title')) {
            input.setAttribute('title', CONFIG.tooltip);
        }

        // Optional: Add event listeners for interactions
        input.addEventListener('focus', () => {
            input.classList.add('swc-focused');
        });

        input.addEventListener('blur', () => {
            input.classList.remove('swc-focused');
        });
    };

    /**
     * scanForInputs
     * Scans the document or a specific container for search inputs
     */
    const scanForInputs = (root = document) => {
        CONFIG.selectors.forEach(selector => {
            const inputs = root.querySelectorAll(selector);
            inputs.forEach(enhanceInput);
        });
    };

    // Initial scan
    scanForInputs();

    // Use MutationObserver for dynamic content (modals, popups, AJAX content)
    const observer = new MutationObserver((mutations) => {
        let shouldScan = false;

        mutations.forEach(mutation => {
            if (mutation.addedNodes.length > 0) {
                shouldScan = true;
            }
        });

        if (shouldScan) {
            scanForInputs();
        }
    });

    // Start observing the body for added nodes
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
