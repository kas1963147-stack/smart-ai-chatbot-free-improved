/**
 * Settings Page Component - Metronic v9 Style
 *
 * Consolidated settings panel with modern UI.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Zap } from 'lucide-react';
import { getCached, setCache, invalidateCache } from '../../hooks/useApiCache';
import Loading from '../common/Loading';


const SETTINGS_TABS = [
	{ id: 'advanced', label: 'Advanced', IconComponent: Zap },
];

const applyDarkModeClass = (enabled) => {
	const targets = [
		document.documentElement,
		document.getElementById('smart-ai-chatbot-manager-root'),
	].filter(Boolean);

	targets.forEach((node) => {
		node.classList.toggle('dark', enabled);
	});
};

export default function SettingsPage() {
	const [activeTab, setActiveTab] = useState('advanced');
	const [settings, setSettings] = useState({});
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [notification, setNotification] = useState(null);

	useEffect(() => {
		loadSettings();
	}, []);

	useEffect(() => {
		if (settings.dark_mode !== undefined) {
			applyDarkModeClass(!!settings.dark_mode);
			localStorage.setItem('swc-admin-dark-mode', settings.dark_mode ? 'true' : 'false');
		}
	}, [settings.dark_mode]);

	const loadSettings = async () => {
		const cached = getCached('settings');
		if (cached) {
			setSettings(cached);
			setLoading(false);

			apiFetch({ path: '/quark-agentflow-ai/v1/settings', method: 'GET' })
				.then((response) => {
					const loadedSettings = response.settings || response || {};
					setSettings(loadedSettings);
					setCache('settings', loadedSettings);
				})
				.catch(() => {});
			return;
		}

		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/settings',
				method: 'GET',
			});
			const loadedSettings = response.settings || response || {};
			setSettings(loadedSettings);
			setCache('settings', loadedSettings);
		} catch (err) {
			if (window.swcChatbot?.settings) {
				setSettings(window.swcChatbot.settings);
			} else {
				setNotification({
					type: 'error',
					message: err.message || 'Failed to load settings',
				});
			}
		} finally {
			setLoading(false);
		}
	};

	const saveSettings = async () => {
		setSaving(true);
		try {
			await apiFetch({
				path: '/quark-agentflow-ai/v1/settings',
				method: 'POST',
				data: { settings },
			});

			invalidateCache('settings');
			setNotification({ type: 'success', message: 'Settings saved successfully!' });
		} catch (err) {
			setNotification({ type: 'error', message: err.message || 'Failed to save settings' });
		} finally {
			setSaving(false);
		}
	};

	const updateSetting = (key, value) => {
		setSettings((prev) => ({ ...prev, [key]: value }));
	};

	


	if (loading) {
		return <Loading message={__('Loading settings...', 'agentflow-ai')} fullPage />;
	}

	return (
		<div className="min-h-screen bg-gray-50/50 dark:bg-slate-900">
			<div className="flex items-center justify-between px-6 py-3">
				<div className="flex flex-wrap gap-1">
					{SETTINGS_TABS.map((tab) => (
						<button
							key={tab.id}
							onClick={() => setActiveTab(tab.id)}
							className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${activeTab === tab.id
								? 'text-primary bg-primary/10 dark:bg-primary/20'
								: 'text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700'
							}`}
						>
							{tab.IconComponent && <tab.IconComponent className="w-4 h-4 inline-block mr-1.5" />}
							{tab.label}
						</button>
					))}
				</div>
				<button
					onClick={saveSettings}
					disabled={saving}
					className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors disabled:opacity-50"
				>
					{saving ? __('Saving...', 'agentflow-ai') : __('Save Settings', 'agentflow-ai')}
				</button>
			</div>

			{notification && (
				<div className={`mx-6 mt-4 px-4 py-3 rounded-lg flex items-center justify-between ${notification.type === 'success'
					? 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800'
					: 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800'
				}`}>
					<span className="text-sm">{notification.message}</span>
					<button onClick={() => setNotification(null)} className="text-lg opacity-70 hover:opacity-100">x</button>
				</div>
			)}

			<main className="p-6">


				{activeTab === 'advanced' && (
					<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
						<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">Advanced Settings</h3>
						</div>
						<div className="p-6 space-y-6">
							<div>
								<h4 className="text-base font-medium text-gray-900 dark:text-white mb-4">AI Settings</h4>
								<div className="flex items-center justify-between mb-4">
									<div>
										<div className="text-sm font-medium text-gray-900 dark:text-white">{__('Enable AI Responses', 'agentflow-ai')}</div>
										<div className="text-sm text-gray-500 dark:text-slate-400">{__('Use AI to generate chatbot responses', 'agentflow-ai')}</div>
									</div>
									<label className="relative inline-flex items-center cursor-pointer">
										<input
											type="checkbox"
											checked={!!settings.ai_enabled}
											onChange={(e) => updateSetting('ai_enabled', e.target.checked)}
											className="sr-only peer"
										/>
										<div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
									</label>
								</div>
							</div>

							<div className="pt-6 border-t border-gray-100 dark:border-slate-700">
								<h4 className="text-base font-medium text-gray-900 dark:text-white mb-4">Admin Theme</h4>
								<div className="flex items-center justify-between">
									<div>
										<div className="text-sm font-medium text-gray-900 dark:text-white">{__('Dark Mode', 'agentflow-ai')}</div>
									</div>
									<label className="relative inline-flex items-center cursor-pointer">
										<input
											type="checkbox"
											checked={!!settings.dark_mode}
											onChange={(e) => {
												updateSetting('dark_mode', e.target.checked);
												applyDarkModeClass(e.target.checked);
											}}
											className="sr-only peer"
										/>
										<div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
									</label>
								</div>
							</div>

							<div className="pt-6 border-t border-gray-100 dark:border-slate-700 mt-6">
								<h4 className="text-base font-medium text-gray-900 dark:text-white mb-4">Other Settings</h4>
							</div>

							<div className="flex items-center justify-between">
								<div className="text-sm font-medium text-gray-900 dark:text-white">{__('Enable Logging', 'agentflow-ai')}</div>
								<label className="relative inline-flex items-center cursor-pointer">
									<input
										type="checkbox"
										checked={!!settings.enable_logging}
										onChange={(e) => updateSetting('enable_logging', e.target.checked)}
										className="sr-only peer"
									/>
									<div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
								</label>
							</div>

							<div className="flex items-center justify-between">
								<div className="text-sm font-medium text-gray-900 dark:text-white">{__('Enable Streaming', 'agentflow-ai')}</div>
								<label className="relative inline-flex items-center cursor-pointer">
									<input
										type="checkbox"
										checked={!!settings.enable_streaming}
										onChange={(e) => updateSetting('enable_streaming', e.target.checked)}
										className="sr-only peer"
									/>
									<div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
								</label>
							</div>
						</div>
					</div>
				)}
			</main>
		</div>
	);
}
