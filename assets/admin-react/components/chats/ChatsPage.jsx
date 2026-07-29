/**
 * ChatsPage Component - Metronic v9 Premium Design
 *
 * Main page for chat widget management with modern UI.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ChatList from './ChatList';
import ChatEditor from './ChatEditor';
import Loading from '../common/Loading';
import useChatsApi from '../../hooks/useChatsApi';
import { getCached, setCache, invalidateCache } from '../../hooks/useApiCache';

const API_BASE = window.swcChatbot?.apiUrl || '/wp-json/smart-ai-chatbot/v1';

// Icon component
const Icon = ({ path, className = "w-5 h-5" }) => (
	<svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
		<path strokeLinecap="round" strokeLinejoin="round" d={path} />
	</svg>
);

export default function ChatsPage() {
	const {
		loading,
		error,
		fetchWidgets,
		createWidget,
		updateWidget,
		deleteWidget,
	} = useChatsApi();

	const [widgets, setWidgets] = useState([]);
	const [view, setView] = useState('list');
	const [selectedWidget, setSelectedWidget] = useState(null);
	const [notification, setNotification] = useState(null);
	const [agents, setAgents] = useState([]);

	useEffect(() => {
		loadWidgets();
		loadAgents();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	const loadWidgets = async () => {
		const cached = getCached('chat_widgets');
		if (cached) {
			setWidgets(cached);
		}

		try {
			const data = await fetchWidgets(true);
			setWidgets(Array.isArray(data) ? data : []);
			setCache('chat_widgets', Array.isArray(data) ? data : []);
		} catch (err) {
			// errors handled by hook
		}
	};

	const loadAgents = async () => {
		const cached = getCached('agents');
		if (cached) {
			setAgents(cached);
			return;
		}
		try {
			const resp = await fetch(`${API_BASE}/agents`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			const list = data?.agents || [];
			setAgents(list);
			setCache('agents', list);
		} catch (err) {
			// ignore
		}
	};

	const showNotification = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 5000);
	};

	const handleEdit = (widget) => {
		setSelectedWidget(widget);
		setView('edit');
	};

	const handleBack = () => {
		setView('list');
		setSelectedWidget(null);
		invalidateCache('chat_widgets');
		loadWidgets();
	};

	const handleSave = async (widgetData) => {
		try {
			const isCreate = false;
			const response = isCreate
				? await createWidget(widgetData)
				: await updateWidget(selectedWidget.id, widgetData);

			if (response?.success === false) {
				throw new Error(response?.message || __('Failed to save widget', 'smart-woo-chatbot'));
			}

			showNotification(
				isCreate
					? __('Widget created successfully!', 'smart-woo-chatbot')
					: __('Widget updated successfully!', 'smart-woo-chatbot'),
				'success'
			);
			handleBack();
		} catch (err) {
			showNotification(err.message || __('Failed to save widget', 'smart-woo-chatbot'), 'error');
		}
	};

	const handleDelete = async (id) => {
		if (!confirm(__('Are you sure you want to delete this widget?', 'smart-woo-chatbot'))) return;
		try {
			const response = await deleteWidget(id);
			if (response?.success === false) {
				throw new Error(response?.message || __('Failed to delete widget', 'smart-woo-chatbot'));
			}
			showNotification(__('Widget deleted successfully', 'smart-woo-chatbot'), 'success');
			invalidateCache('chat_widgets');
			loadWidgets();
		} catch (err) {
			showNotification(err.message || __('Failed to delete widget', 'smart-woo-chatbot'), 'error');
		}
	};

	return (
		<div className="space-y-6">
			{/* Page Header */}
			{/* Action Bar */}
			<div className="flex items-center justify-between">
				<div className="flex items-center gap-3">
					{/* Left side empty or reserved for future title/actions */}
				</div>
				<div className="flex items-center gap-3">
					{view !== 'list' && (
						<button
							onClick={handleBack}
							className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 transition-all duration-200"
						>
							<Icon path="M10 19l-7-7m0 0l7-7m-7 7h18" className="w-4 h-4" />
							{__('Back to List', 'smart-woo-chatbot')}
						</button>
					)}
				</div>
			</div>

			{/* Notifications */}
			{notification && (
				<div
					className={`flex items-center gap-3 px-5 py-4 rounded-xl border shadow-sm transition-all duration-300 ${notification.status === 'error'
						? 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800'
						: 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
						}`}
				>
					{notification.status === 'error' ? (
						<svg className="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
							<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
						</svg>
					) : (
						<svg className="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
							<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
						</svg>
					)}
					<span className="font-medium">{notification.message}</span>
					<button
						onClick={() => setNotification(null)}
						className="ml-auto p-1 rounded-lg hover:bg-black/5 transition-colors"
					>
						<svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
							<path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
						</svg>
					</button>
				</div>
			)}

			{/* Error State */}
			{error && (
				<div className="flex items-center gap-3 px-5 py-4 rounded-xl bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800">
					<svg className="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
						<path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
					</svg>
					<span className="font-medium">{error}</span>
				</div>
			)}

			{/* Content */}
			{view === 'list' && (
				<ChatList widgets={widgets} onEdit={handleEdit} onDelete={handleDelete} />
			)}

			{view !== 'list' && (
				<ChatEditor
					widget={selectedWidget}
					agents={agents}
					onSave={handleSave}
					onCancel={handleBack}
				/>
			)}

			{/* Loading State */}
			{loading && view === 'list' && widgets.length === 0 && (
				<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 py-8">
					<Loading message={__('Loading widgets…', 'smart-woo-chatbot')} fullPage />
				</div>
			)}

			{/* Empty State */}
			{!loading && view === 'list' && widgets.length === 0 && (
				<div className="text-center py-16 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700">
					<div className="w-20 h-20 mx-auto mb-6 rounded-3xl bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
						<Icon
							path="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
							className="w-10 h-10 text-indigo-500 dark:text-indigo-400"
						/>
					</div>
					<h3 className="text-xl font-semibold text-slate-900 dark:text-white mb-2">
						{__('No Widgets Yet', 'smart-woo-chatbot')}
					</h3>
					<p className="text-slate-500 dark:text-slate-400 mb-6 max-w-md mx-auto">
						{__('Default widgets will appear here after plugin setup.', 'smart-woo-chatbot')}
					</p>
				</div>
			)}
		</div>
	);
}
