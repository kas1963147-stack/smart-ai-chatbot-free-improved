/**
 * Admin React Entry Point
 *
 * Main entry for the Agent Management admin panel.
 * 
 * PERFORMANCE NOTES:
 * - Mantine CSS is required for UiProvider (MantineProvider)
 * - Core styles consolidated in index.css
 */
import { createRoot } from '@wordpress/element';
import { AppWithErrorBoundary } from './App';
import { UiProvider } from './ui';

// Tailwind CSS - Metronic v9 AI Style
import './styles/tailwind.css';
// Design system v2 - Premium navigation, layout, and component styles
import './styles/design-system-v2.css';
// Custom component styles - Loader, filters, misc
import './styles.css';
// Metronic theme — must load LAST to override all design system tokens/styles
import './styles/metronic-theme.css';

const setDarkModeClass = (enabled) => {
	const targets = [
		document.documentElement,
		document.getElementById('smart-ai-chatbot-manager-root'),
	].filter(Boolean);

	targets.forEach((node) => {
		node.classList.toggle('dark', enabled);
	});
};

// Wait for DOM ready
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('smart-ai-chatbot-manager-root');

	// Check localStorage first for instant dark mode, then fall back to server settings
	const localDarkMode = localStorage.getItem('swc-admin-dark-mode');
	const isDarkMode = localDarkMode !== null
		? localDarkMode === 'true'
		: !!window.swcChatbot?.settings?.dark_mode;

	setDarkModeClass(isDarkMode);

	if (container) {
		const root = createRoot(container);
		root.render(
			<UiProvider>
				<AppWithErrorBoundary />
			</UiProvider>
		);
	}
});
