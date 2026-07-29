/**
 * Agent Manager App
 *
 * Main application component for multi-agent management.
 * Now includes Groups, Templates, Skills, and Settings.
 * 
 * PERFORMANCE OPTIMIZED:
 * - Lazy loading for secondary page components only
 * - Core agent editing components loaded synchronously for stability
 * - Suspense boundaries for async loading
 */
import { useState, useEffect, useCallback, lazy, Suspense, useMemo, useRef } from '@wordpress/element';
import { settings, people } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

// Core components loaded immediately (critical for functionality)
import AgentList from './components/AgentList';
import AgentEditor from './components/AgentEditor';
import GroupList from './components/skills/GroupList';
import GroupEditor from './components/skills/GroupEditor';
import TemplateWizard from './components/TemplateWizard';
import { IntroductionPopup } from './components/shared';
import LazyWrapper, { PageLoadingSkeleton, LoadingSpinner } from './components/shared/LazyWrapper';
import ErrorBoundary from './components/shared/ErrorBoundary';
import useAgentApi from './hooks/useAgentApi';
import apiFetch from '@wordpress/api-fetch';
import { Layout } from './components/layout';
import { Icon, Notice } from './components/ui';
import Loading from './components/common/Loading';

// Lazy-loaded page components (free tier only)
const AnalyticsPage = lazy(() => import('./components/analytics/AnalyticsPage'));
const SettingsPage = lazy(() => import('./components/settings/SettingsPage'));
const KnowledgePage = lazy(() => import('./components/knowledge/KnowledgePage'));
const WorkspacePage = lazy(() => import('./components/workspace/WorkspacePage'));
const HistoryPage = lazy(() => import('./components/history/HistoryPage'));
const ChatsPage = lazy(() => import('./components/chats/ChatsPage'));
const ProviderHubPage = lazy(() => import('./components/providers/ProviderHubPage'));
const ProviderEditorPage = lazy(() => import('./components/providers/ProviderEditorPage'));


// SVG icon components for nav tabs
const NavIcons = {
	agents: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<rect x="3" y="11" width="18" height="10" rx="2" /><circle cx="12" cy="5" r="2" /><path d="M12 7v4" />
		</svg>
	),
	workspace: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8" /><path d="M12 17v4" />
		</svg>
	),
	knowledge: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M4 19.5A2.5 2.5 0 016.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" />
		</svg>
	),
	chats: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
		</svg>
	),
	// workflows icon removed — workflows now managed inside Teams
	history: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
		</svg>
	),
	providers: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M12 2L2 7l10 5 10-5-10-5z" /><path d="M2 17l10 5 10-5" /><path d="M2 12l10 5 10-5" />
		</svg>
	),

	analytics: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M21.21 15.89A10 10 0 118 2.83" /><path d="M22 12A10 10 0 0012 2v10z" />
		</svg>
	),
	settings: () => (
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" />
		</svg>
	),
};

// Top-level navigation tabs with grouping
const PRIMARY_TABS = [
	{ id: 'agents', label: 'AI Agents' },
	{ id: 'workspace', label: 'Workspace' },
	{ id: 'chats', label: 'Widgets' },
	{ id: 'providers', label: 'Providers' },
	{ id: 'knowledge', label: 'Knowledge' },
	{ id: 'analytics', label: 'Analytics' },
	{ id: 'history', label: 'History' },
	{ id: 'settings', label: 'Settings' },
];

const SECONDARY_TABS = [];

const NAV_TABS = [...PRIMARY_TABS, ...SECONDARY_TABS];

const resolveInitialTab = () => {
	const explicit = window.swcChatbot?.initialTab;
	const params = new URLSearchParams(window.location.search);
	const paramTab = params.get('tab');
	const candidate = explicit || paramTab;
	return NAV_TABS.some((tab) => tab.id === candidate) ? candidate : 'agents';
};

export default function App() {
	// Top-level navigation
	const [activeNav, setActiveNav] = useState(resolveInitialTab());

	// Sub-navigation (Agents vs Groups)
	const [agentSubNav, setAgentSubNav] = useState('agents');
	const [providerSubNavObj, setProviderSubNavObj] = useState(null);

	// Agent search state (lifted here so search bar can sit in the action bar)
	const [agentSearchQuery, setAgentSearchQuery] = useState('');

	// View state
	const [view, setView] = useState('list'); // 'list' | 'create-select' | 'create-custom' | 'create-template' | 'create-group' | 'edit-agent' | 'edit-group'
	const [selectedItem, setSelectedItem] = useState(null);
	const [notification, setNotification] = useState(null);

	// Editor tabs state (lifted so header can render tabs inline)
	const [editorActiveTab, setEditorActiveTab] = useState('general');



	// Introduction popup state
	const [showIntro, setShowIntro] = useState(false);
	const [introChecked, setIntroChecked] = useState(false);

	// Tool count tracking for save warning
	const [agentToolCount, setAgentToolCount] = useState(0);

	

	const {
		agents,
		groups,
		toolkits,
		loading,
		error,
		fetchAgents,
		fetchGroups,
		fetchToolkits,
		createAgent,
		updateAgent,
		deleteAgent,
		duplicateAgent,
		createGroup,
		updateGroup,
		deleteGroup,
	} = useAgentApi();

	// Initial data fetch + background prefetch for other sections
	useEffect(() => {
		fetchAgents(true);
		fetchGroups();
		fetchToolkits();

		

		// Prefetch skills and knowledge data in background after 500ms
		// This ensures data is ready when user navigates to those sections
		const prefetchTimer = setTimeout(() => {
			// Prefetch skills
			apiFetch({ path: '/smart-ai-chatbot/v1/skills' })
				.then(response => {
					if (response.success) {
						// Store in sessionStorage for cache
						try {
							const cacheKey = 'swc_api_cache_skills';
							const cacheData = {
								data: { skills: response.data.skills, categories: response.data.categories },
								expiry: Date.now() + 5 * 60 * 1000,
								timestamp: Date.now(),
							};
							sessionStorage.setItem(cacheKey, JSON.stringify(cacheData));
						} catch (e) { /* ignore */ }
					}
				})
				.catch(() => { /* prefetch failed - not critical */ });

			// Prefetch knowledge
			apiFetch({ path: '/smart-ai-chatbot/v1/knowledge/sources' })
				.then(response => {
					if (response.success) {
						try {
							const cacheKey = 'swc_api_cache_knowledge';
							const cacheData = {
								data: { sources: response.data },
								expiry: Date.now() + 5 * 60 * 1000,
								timestamp: Date.now(),
							};
							sessionStorage.setItem(cacheKey, JSON.stringify(cacheData));
						} catch (e) { /* ignore */ }
					}
				})
				.catch(() => { /* prefetch failed - not critical */ });
		}, 500);

		return () => clearTimeout(prefetchTimer);
	}, [fetchAgents, fetchGroups, fetchToolkits]);

	// Introduction popup check (only shows once after plugin activation)
	useEffect(() => {
		let isMounted = true;

		if (introChecked) {
			return () => {
				isMounted = false;
			};
		}

		const checkIntro = async () => {
			try {
				const response = await apiFetch({
					path: '/smart-ai-chatbot/v1/settings',
				});

				if (!isMounted) {
					return;
				}

				const loadedSettings = response.settings || response || {};

				// Show introduction popup only if activation flag is set
				if (loadedSettings.show_intro && !introChecked) {
					setShowIntro(true);
				}
				setIntroChecked(true);
			} catch (err) {
				// If settings fail to load, don't show intro
				if (isMounted) {
					setIntroChecked(true);
				}
			}
		};
		checkIntro();

		return () => {
			isMounted = false;
		};
	}, [introChecked]);

	// Handle editing
	const handleEditAgent = (agent) => {
		setSelectedItem(agent);
		setView('edit-agent');
	};

	const handleEditGroup = (group) => {
		setSelectedItem(group);
		setView('edit-group');
	};

	// Handle creation flow
	const handleCreateClick = () => {
		setView('create-select');
	};

	// Handle save (create or update)
	const handleSaveAgent = async (agentData) => {
		// Check tool count and show warning/error
		if (agentToolCount >= 128) {
			showNotification(
				__(`Cannot save: Too many tools enabled (${agentToolCount}/128). Please disable some toolkits.`, 'smart-woo-chatbot'),
				'error'
			);
			return;
		}

		if (agentToolCount >= 100) {
			const confirmed = confirm(
				__(`Warning: You have ${agentToolCount} tools enabled (limit: 128).\n\nAre you sure you want to save?`, 'smart-woo-chatbot')
			);
			if (!confirmed) return;
		}

		try {
			if (view === 'create-custom' || view === 'create-template') {
				await createAgent(agentData);
				showNotification(
					__('Agent created successfully!', 'smart-woo-chatbot'),
					'success'
				);
				setView('list');
				fetchAgents(true);
			} else {
				// Update existing agent - stay on same page
				const updatedAgent = await updateAgent(selectedItem.id, agentData);
				showNotification(
					__('Agent updated successfully!', 'smart-woo-chatbot'),
					'success'
				);
				// Update the selectedItem with fresh data to keep form in sync
				if (updatedAgent) {
					setSelectedItem(updatedAgent);
				}
				fetchAgents(true);
			}
		} catch (err) {
			showNotification(
				err.message ||
				__('Failed to save agent', 'smart-woo-chatbot'),
				'error'
			);
			throw err; // Re-throw for child components to handle
		}
	};

	const handleSaveGroup = async (groupData) => {
		try {
			if (view === 'create-group') {
				await createGroup(groupData);
				showNotification(
					__('Team created successfully!', 'smart-woo-chatbot'),
					'success'
				);
			} else {
				await updateGroup(selectedItem.id, groupData);
				showNotification(
					__('Team updated successfully!', 'smart-woo-chatbot'),
					'success'
				);
			}
			setView('list');
			setAgentSubNav('groups');
			fetchGroups();
		} catch (err) {
			showNotification(
				err.message ||
				__('Failed to save team', 'smart-woo-chatbot'),
				'error'
			);
			throw err;
		}
	};

	// Handle delete
	const handleDeleteAgent = async (agentId) => {
		if (
			!confirm(
				__(
					'Are you sure you want to delete this agent?',
					'smart-woo-chatbot'
				)
			)
		) {
			return;
		}
		try {
			await deleteAgent(agentId);
			showNotification(
				__('Agent deleted successfully!', 'smart-woo-chatbot'),
				'success'
			);
			fetchAgents(true);
		} catch (err) {
			showNotification(
				err.message ||
				__('Failed to delete agent', 'smart-woo-chatbot'),
				'error'
			);
		}
	};

	const handleDeleteGroup = async (groupId) => {
		if (
			!confirm(
				__(
					'Are you sure you want to delete this team?',
					'smart-woo-chatbot'
				)
			)
		) {
			return;
		}
		try {
			await deleteGroup(groupId);
			showNotification(
				__('Team deleted successfully!', 'smart-woo-chatbot'),
				'success'
			);
			fetchGroups();
		} catch (err) {
			showNotification(
				err.message ||
				__('Failed to delete team', 'smart-woo-chatbot'),
				'error'
			);
		}
	};

	// Handle duplicate
	const handleDuplicateAgent = async (agent) => {
		try {
			await duplicateAgent(agent.id, {
				agent_id: agent.agent_id + '_copy',
				name: agent.name + ' (Copy)',
			});
			showNotification(
				__('Agent duplicated successfully!', 'smart-woo-chatbot'),
				'success'
			);
			fetchAgents(true);
		} catch (err) {
			showNotification(
				err.message ||
				__('Failed to duplicate agent', 'smart-woo-chatbot'),
				'error'
			);
		}
	};



	// Handle back to list
	const handleBack = () => {
		setView('list');
		setSelectedItem(null);
	};

	// Show notification
	const showNotification = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 5000);
	};

	// Switch nav and reset view
	const switchNav = (navId) => {
		setActiveNav(navId);
		setView('list');
		setSelectedItem(null);
		try {
			const url = new URL(window.location.href);
			url.searchParams.set('tab', navId);
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			// ignore url update errors
		}
	};

	// End of logic, start of rendering

	// More dropdown state (must be before any early returns - React Rules of Hooks)
	const [moreOpen, setMoreOpen] = useState(false);
	const moreRef = useRef(null);

	// Close dropdown on outside click
	useEffect(() => {
		const handleClickOutside = (e) => {
			if (moreRef.current && !moreRef.current.contains(e.target)) {
				setMoreOpen(false);
			}
		};
		document.addEventListener('mousedown', handleClickOutside);
		return () => document.removeEventListener('mousedown', handleClickOutside);
	}, []);

	const isSecondaryActive = SECONDARY_TABS.some((t) => t.id === activeNav);
	const activeSecondaryLabel = SECONDARY_TABS.find((t) => t.id === activeNav)?.label;

	if (loading && agents.length === 0 && activeNav === 'agents') {
		return (
			<Layout>
				<Loading message={__('Loading Smart Chatbot…', 'smart-woo-chatbot')} fullPage />
			</Layout>
		);
	}

	const pluginLogoUrl = window.swcChatbot?.logoUrl || '';

	// Navigation Content
	const navigationContent = (
		<nav className="flex flex-col gap-4 rounded-2xl border border-slate-200/70 bg-white/90 px-4 py-3 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/70 md:flex-row md:items-center md:justify-between">
			<div className="flex items-center gap-3">
				<span className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
					{pluginLogoUrl ? (
						<img
							src={pluginLogoUrl}
							alt=""
							className="h-full w-full object-contain"
							loading="eager"
						/>
					) : (
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
							<rect x="3" y="11" width="18" height="10" rx="2" />
							<circle cx="12" cy="5" r="2" />
							<path d="M12 7v4" />
							<line x1="8" y1="16" x2="8" y2="16" />
							<line x1="16" y1="16" x2="16" y2="16" />
						</svg>
					)}
				</span>
				<span className="text-base font-semibold tracking-tight">
					Smart Chatbot
				</span>
			</div>
			<div className="flex flex-wrap items-center gap-2">
				{PRIMARY_TABS.map((tab) => {
					const IconComp = NavIcons[tab.id];
					return (
						<button
							key={tab.id}
							className={`inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition ${activeNav === tab.id
								? 'bg-primary text-primary-foreground shadow-sm'
								: 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
								}`}
							onClick={() => { switchNav(tab.id); setMoreOpen(false); }}
						>
							{IconComp && <IconComp />}
							<span>{tab.label}</span>
						</button>
					);
				})}

				
				<a
					href="https://plugins.quarksol.org/plugin/smart-bot"
					target="_blank"
					rel="noopener noreferrer"
					className="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:from-amber-600 hover:to-orange-600 hover:shadow-md"
				>
					<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
					Get Pro
				</a>
			</div>
		</nav>
	);

	return (
		<Layout
			navigation={navigationContent}
			isFullWidth={activeNav === 'workspace'}
		>
			<div className={'space-y-6'}>
				{ /* Notifications */}
				{notification && (
					<Notice
						status={notification.status}
						isDismissible={true}
						onRemove={() => setNotification(null)}
					>
						{notification.message}
					</Notice>
				)}

				{ /* Error */}
				{error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}

				{ /* Agents & Groups Section */}
				{activeNav === 'agents' && (
					<>
						{ /* Action Bar — only for list view and agent editor views */}
						{(view === 'list' || view === 'edit-agent' || view === 'create-custom' || view === 'create-select' || view === 'create-template') && (
						<div className="flex flex-wrap items-center justify-between gap-3">
							<div className="min-w-0 flex-1">
								{view === 'edit-agent' && selectedItem?.name && (
									<div className="truncate text-lg font-semibold text-slate-900 dark:text-slate-100">
										{selectedItem.name}
									</div>
								)}
							</div>
							<div className="flex flex-wrap items-center justify-end gap-3">
								{view === 'list' && agentSubNav === 'agents' && (
									<>
										<input
											type="text"
											placeholder={__('Search agents...', 'smart-woo-chatbot')}
											value={agentSearchQuery}
											onChange={(e) => setAgentSearchQuery(e.target.value)}
											className="w-56 h-9 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all shadow-sm"
										/>
										<span className="text-sm text-primary font-medium">
											{agents.length}{' '}
											{agents.length === 1
												? __('agent', 'smart-woo-chatbot')
												: __('agents', 'smart-woo-chatbot')}
										</span>
										{/* New Agent button hidden */}
									</>
								)}
								{/* New Team button hidden — backend preserved for future use */}
								{view !== 'list' && (
									<button
										className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800"
										onClick={handleBack}
									>
										<span className="text-base">{'<-'}</span>
										{__('Back', 'smart-woo-chatbot')}
									</button>
								)}
								{(view === 'edit-agent' || view === 'create-custom') && (
									<button
										type="submit"
										form="swc-agent-editor-form"
										className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
									>
										{view === 'create-custom'
											? __('Create Agent', 'smart-woo-chatbot')
											: __('Save Changes', 'smart-woo-chatbot')}
									</button>
								)}
							</div>
						</div>
						)}
						{ /* Content Area */}

						{ /* List View */}
						{view === 'list' && agentSubNav === 'agents' && (
							<AgentList
								agents={agents}
							searchQuery={agentSearchQuery}
								onEdit={handleEditAgent}
								onDelete={handleDeleteAgent}
								onDuplicate={handleDuplicateAgent}
								onTest={(agent) => {
									const agentId = agent.agent_id || agent.id;
									localStorage.setItem('swc-selected-agents', JSON.stringify([agentId]));
									switchNav('workspace');
								}}
								onUseAgent={(agents) => {
									const agentList = Array.isArray(agents) ? agents : [agents];
									const agentIds = agentList.map(a => a.agent_id || a.id);
									localStorage.setItem('swc-selected-agents', JSON.stringify(agentIds));
									switchNav('workspace');
								}}
							/>
						)}

						{/* GroupList hidden — backend preserved for future use
						{view === 'list' && agentSubNav === 'groups' && (
							<GroupList
								groups={groups}
								onEdit={handleEditGroup}
								onDelete={handleDeleteGroup}
							/>
						)}
						*/}

						{ /* Creation Selection */}
						{view === 'create-select' && (
							<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
								<div
									className="group cursor-pointer rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
									onClick={() =>
										setView('create-template')
									}
								>
									<h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
										{__(
											'Copy Existing Agent',
											'smart-woo-chatbot'
										)}
									</h3>
									<p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
										{__(
											'Browse pre-built agent templates, copy one, and customize it to fit your needs.',
											'smart-woo-chatbot'
										)}
									</p>
								</div>
								<div
									className="group cursor-pointer rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
									onClick={() => setView('create-custom')}
								>
									<h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
										{__(
											'Custom Agent',
											'smart-woo-chatbot'
										)}
									</h3>
									<p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
										{__(
											'Create an agent from scratch with full control.',
											'smart-woo-chatbot'
										)}
									</p>
								</div>
							</div>
						)}

						{ /* Editors */}
						{(view === 'create-custom' ||
							view === 'edit-agent') && (
								<AgentEditor
									agent={selectedItem}
									toolkits={toolkits}
									onSave={handleSaveAgent}
									onCancel={handleBack}
									isNew={view === 'create-custom'}
									activeTab={editorActiveTab}
									onTabChange={setEditorActiveTab}

									onToolCountChange={setAgentToolCount}
								/>
							)}

						{view === 'create-template' && (
							<TemplateWizard
								agents={agents}
								onSave={handleSaveAgent}
								onCancel={handleBack}
								onSelectTemplate={(template) => {
									setSelectedItem({
										agent_id: '', // Ensure new agent gets user-defined ID
										name: template.name + ' (Copy)',
										description: template.description || '',
										avatar: template.avatar || 'AI',
										is_active: true,
										config: {
											...template.config,
											name: template.name + ' (Copy)',
										},
									});
									setView('create-custom');
								}}
							/>
						)}

						{/* GroupEditor hidden — backend preserved for future use
						{(view === 'create-group' ||
							view === 'edit-group') && (
								<GroupEditor
									group={selectedItem}
									onSave={handleSaveGroup}
									onCancel={handleBack}
									isNew={view === 'create-group'}
								/>
							)}
						*/}
					</>
				)}

				{ /* Other Sections - Lazy Loaded with Suspense */}
				{activeNav === 'workspace' && (
					<LazyWrapper>
						<WorkspacePage />
					</LazyWrapper>
				)}
				{activeNav === 'knowledge' && (
					<LazyWrapper>
						<KnowledgePage />
					</LazyWrapper>
				)}
				{activeNav === 'chats' && (
					<LazyWrapper>
						<ChatsPage />
					</LazyWrapper>
				)}
				{activeNav === 'history' && (
					<LazyWrapper>
						<HistoryPage />
					</LazyWrapper>
				)}
				{activeNav === 'providers' && view === 'list' && (
					<LazyWrapper>
						<ProviderHubPage
							onEditProvider={(inst) => {
								setSelectedItem(inst);
								setView('edit-provider');
							}}
							onAddNew={(payload) => {
								setSelectedItem(payload || null);
								if (payload?.create_new_group) {
									setView('create-provider');
									return;
								}
								setView(payload ? 'add-provider-model' : 'create-provider');
							}}
						/>
					</LazyWrapper>
				)}

				{activeNav === 'providers' && (view === 'edit-provider' || view === 'create-provider' || view === 'add-provider-model') && (
					<LazyWrapper>
						<ProviderEditorPage
							providerToEdit={view === 'edit-provider' ? selectedItem : null}
							isAddingModelFor={view === 'add-provider-model' ? selectedItem : null}
							providerSeed={view === 'create-provider' ? selectedItem : null}
							onSaveSuccess={(msg) => {
								showNotification(msg, 'success');
								setView('list');
								setSelectedItem(null);
							}}
							onCancel={() => {
								setView('list');
								setSelectedItem(null);
							}}
						/>
					</LazyWrapper>
				)}

				{activeNav === 'analytics' && (
					<LazyWrapper>
						<AnalyticsPage />
					</LazyWrapper>
				)}
				{activeNav === 'settings' && (
					<LazyWrapper>
						<SettingsPage />
					</LazyWrapper>
				)}

				{ /* Introduction Popup (shown once after plugin activation) */}
				<IntroductionPopup
					isOpen={showIntro}
					onClose={async () => {
						setShowIntro(false);
						// Dismiss intro permanently
						try {
							await apiFetch({
								path: '/smart-ai-chatbot/v1/settings/dismiss-intro',
								method: 'POST',
							});
						} catch (err) {
							console.error('Failed to dismiss intro:', err);
						}
					}}
				/>
			</div>
		</Layout>
	);
}

// Wrap App with ErrorBoundary for top-level error catching
export function AppWithErrorBoundary() {
	return (
		<ErrorBoundary>
			<App />
		</ErrorBoundary>
	);
}



