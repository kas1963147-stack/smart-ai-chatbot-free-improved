/**
 * McpManager Component - Metronic v9 Style
 *
 * Manage Model Context Protocol (MCP) connections for an agent.
 * Shows clear configuration status for each MCP.
 * Premium Tailwind styling with Lucide React icons.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { Button, McpCard, Modal, TextField } from './ui';
import { getConfigStatus } from './ui/McpCard';
import { Server, Plug, AlertTriangle, Info } from 'lucide-react';

export default function McpManager({ mcpConfigs = {}, onConfigChange }) {
	const [configModal, setConfigModal] = useState(null);
	const [activeCategory, setActiveCategory] = useState('all');
	const [globalMcps, setGlobalMcps] = useState([]);
	const [isLoading, setIsLoading] = useState(true);

	// Fetch Global MCPs on mount
	useEffect(() => {
		setIsLoading(true);
		apiFetch({ path: '/quark-agentflow-ai/v1/mcps' })
			.then((mcps) => {
				setGlobalMcps(mcps);
			})
			.catch((err) =>
				console.error('Failed to load global MCPs:', err)
			)
			.finally(() => setIsLoading(false));
	}, []);

	// All MCPs come from the API now
	const allMcps = useMemo(() => {
		return globalMcps.map((m) => ({
			...m,
			category: m.category || 'custom',
		}));
	}, [globalMcps]);

	// Get categories â€” add config-status-based filters
	const categories = useMemo(() => {
		const cats = new Set(allMcps.map((m) => m.category));
		return ['all', 'configured', 'not_configured', ...Array.from(cats)];
	}, [allMcps]);

	// Configuration stats
	const configStats = useMemo(() => {
		let configured = 0;
		let notConfigured = 0;
		let noKeyNeeded = 0;

		allMcps.forEach((mcp) => {
			const status = getConfigStatus(mcp);
			if (status === 'configured') configured++;
			else if (status === 'not_required') noKeyNeeded++;
			else notConfigured++;
		});

		return { configured, notConfigured, noKeyNeeded, ready: configured + noKeyNeeded };
	}, [allMcps]);

	// Filter MCPs
	const filteredMcps = useMemo(() => {
		let result = allMcps;

		if (activeCategory === 'configured') {
			result = allMcps.filter((m) => {
				const status = getConfigStatus(m);
				return status === 'configured' || status === 'not_required';
			});
		} else if (activeCategory === 'not_configured') {
			result = allMcps.filter((m) => getConfigStatus(m) === 'not_configured');
		} else if (activeCategory !== 'all') {
			result = allMcps.filter((m) => m.category === activeCategory);
		}

		// Sort: configured/ready first, then not_configured
		return result.sort((a, b) => {
			const statusOrder = { configured: 0, not_required: 0, not_configured: 1 };
			const aOrder = statusOrder[getConfigStatus(a)] ?? 1;
			const bOrder = statusOrder[getConfigStatus(b)] ?? 1;
			return aOrder - bOrder;
		});
	}, [activeCategory, allMcps]);

	// Check if an MCP is enabled
	const isEnabled = (mcpId) => {
		return !!mcpConfigs[mcpId]?.enabled;
	};

	// Toggle MCP enabled state
	const toggleMcp = (mcpId) => {
		const current = mcpConfigs[mcpId] || {};
		const mcpDef = allMcps.find((m) => m.id === mcpId);

		const newConfigs = {
			...mcpConfigs,
			[mcpId]: {
				...current,
				enabled: !current.enabled,
				config: current.config
					? current.config
					: mcpDef?.isGlobal
						? {}
						: mcpDef?.default_config || {},
			},
		};
		onConfigChange(newConfigs);
	};

	// Open config modal
	const openConfig = (e, mcp) => {
		e.stopPropagation();
		const currentConfig =
			mcpConfigs[mcp.id]?.config || mcp.default_config || {};

		setConfigModal({
			mcp,
			config: { ...currentConfig },
		});
	};

	// Save configuration from modal
	const handleSaveConfig = () => {
		if (!configModal) {
			return;
		}

		const newConfigs = {
			...mcpConfigs,
			[configModal.mcp.id]: {
				...(mcpConfigs[configModal.mcp.id] || { enabled: true }),
				config: configModal.config,
			},
		};
		onConfigChange(newConfigs);
		setConfigModal(null);
	};

	// Update specific config field in modal
	const updateModalConfig = (key, value) => {
		setConfigModal((prev) => ({
			...prev,
			config: {
				...prev.config,
				[key]: value,
			},
		}));
	};

	// Count enabled MCPs
	const enabledCount = Object.values(mcpConfigs).filter(c => c.enabled).length;

	// Count enabled MCPs that are not configured
	const enabledButNotConfigured = useMemo(() => {
		return Object.entries(mcpConfigs)
			.filter(([id, c]) => c.enabled)
			.filter(([id]) => {
				const mcp = allMcps.find(m => m.id === id);
				return mcp && getConfigStatus(mcp) === 'not_configured';
			}).length;
	}, [mcpConfigs, allMcps]);

	// Category display labels
	const getCategoryLabel = (cat) => {
		if (cat === 'configured') return `Ready (${configStats.ready})`;
		if (cat === 'not_configured') return `Needs Setup (${configStats.notConfigured})`;
		return cat.charAt(0).toUpperCase() + cat.slice(1);
	};

	if (isLoading) {
		return (
			<div className="flex flex-col items-center justify-center py-16">
				<div className="relative">
					<div className="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin" />
					<Server className="w-5 h-5 text-primary absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
				</div>
				<p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
					{__('Loading external MCP servers...', 'agentflow-ai')}
				</p>
			</div>
		);
	}

	return (
		<div className="w-full space-y-4">
			{/* Main Card */}
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
				{/* Header */}
				<div className="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
					<div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
						<div className="flex items-center gap-3">
						<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
							<Server className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
						</div>
						<div className="flex-1">
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
								{__('External MCPs', 'agentflow-ai')}
							</h3>
							<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
								{__('Enable external MCP servers for this agent and review setup status below.', 'agentflow-ai')}
							</p>
						</div>
					</div>
						{enabledButNotConfigured > 0 && (
							<div className="inline-flex items-center gap-2 self-start rounded-full border border-amber-200 bg-amber-50/80 px-3 py-1.5 text-xs font-medium text-amber-700">
								<AlertTriangle className="h-3.5 w-3.5 flex-shrink-0" />
								<span>
									{enabledButNotConfigured} {__('enabled', 'agentflow-ai')} MCP{enabledButNotConfigured > 1 ? 's' : ''} {enabledButNotConfigured > 1 ? __('need API keys', 'agentflow-ai') : __('needs an API key', 'agentflow-ai')}
								</span>
							</div>
						)}
					</div>
				</div>

				{/* Categories */}
				<div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
					<div className="flex flex-wrap gap-2">
						{categories.map((cat) => (
							<button
								key={cat}
								type="button"
								onClick={() => setActiveCategory(cat)}
								className={`
									inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg
									transition-all duration-200
									${activeCategory === cat
										? cat === 'configured'
											? 'bg-emerald-600 text-white shadow-sm'
											: cat === 'not_configured'
												? 'bg-amber-500 text-white shadow-sm'
												: 'bg-primary text-white shadow-sm'
										: cat === 'configured'
											? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100'
											: cat === 'not_configured'
												? 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100'
												: 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'
									}
								`}
							>
								{getCategoryLabel(cat)}
							</button>
						))}
					</div>
				</div>

				{/* MCP List */}
				<div className="p-6">
					{filteredMcps.length === 0 ? (
						<div className="text-center py-12">
							<div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
								<Plug className="w-8 h-8 text-gray-400" />
							</div>
							<h4 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
								{activeCategory === 'not_configured'
									? __('All external MCPs are configured!', 'agentflow-ai')
									: __('No external MCP servers available', 'agentflow-ai')
								}
							</h4>
							<p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
								{activeCategory === 'not_configured'
									? __('All your external MCP servers have their API keys set up and are ready to use.', 'agentflow-ai')
									: __('Configure external MCP servers in the global settings to connect external services.', 'agentflow-ai')
								}
							</p>
						</div>
					) : (
						<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
							{filteredMcps.map((mcp) => (
								<McpCard
									key={mcp.id}
									mcp={mcp}
									enabled={isEnabled(mcp.id)}
									onToggle={toggleMcp}
									showConfigButton={false}
									showUrl={true}
									showStatus={true}
								/>
							))}
						</div>
					)}
				</div>

				{/* Summary Footer */}
				<div className="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
					<div className="flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between">
						<div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
							<div className="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
								<Server className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
							</div>
							<span>
								<strong className="text-gray-900 dark:text-white">{enabledCount}</strong>
								{' / '}
								{allMcps.length}
								{' '}
								{__('external MCP servers enabled for this agent', 'agentflow-ai')}
							</span>
						</div>
						<div className="flex items-center gap-1 text-xs text-gray-500">
							<Info className="w-3.5 h-3.5" />
							{__('API keys are managed from the main External MCPs page', 'agentflow-ai')}
						</div>
					</div>
				</div>
			</div>

			{/* Config Modal */}
			{configModal && (
				<Modal
					title={__(`Configure ${configModal.mcp.name}`, 'agentflow-ai')}
					isOpen={!!configModal}
					onClose={() => setConfigModal(null)}
					size="md"
					footer={
						<div className="flex justify-end gap-2">
							<Button
								variant="secondary"
								onClick={() => setConfigModal(null)}
							>
								{__('Cancel', 'agentflow-ai')}
							</Button>
							<Button
								variant="primary"
								onClick={handleSaveConfig}
							>
								{__('Save Configuration', 'agentflow-ai')}
							</Button>
						</div>
					}
				>
					<div className="space-y-5">
						{configModal.mcp.type === 'sse' ? (
							<TextField
								label={__('Server URL (SSE)', 'agentflow-ai')}
								help={__('The full URL to the MCP SSE endpoint.', 'agentflow-ai')}
								value={configModal.config.url || ''}
								onChange={(val) => updateModalConfig('url', val)}
								placeholder="https://..."
							/>
						) : (
							<div className="space-y-5">
								<TextField
									label={__('Command', 'agentflow-ai')}
									value={configModal.config.command || ''}
									onChange={(val) => updateModalConfig('command', val)}
									placeholder="npx"
								/>
								<TextField
									label={__('Arguments (JSON Array)', 'agentflow-ai')}
									help={__('Arguments to pass to the command. Must be valid JSON.', 'agentflow-ai')}
									value={
										Array.isArray(configModal.config.args)
											? JSON.stringify(configModal.config.args)
											: configModal.config.args || '[]'
									}
									onChange={(val) => {
										try {
											const parsed = JSON.parse(val);
											updateModalConfig('args', parsed);
										} catch (e) {
											// Silent
										}
									}}
									rows={3}
									multiline
								/>
							</div>
						)}

						{/* API Key (Remote MCPs) */}
						{configModal.mcp.type === 'sse' && (
							<div className="space-y-5">
								<TextField
									label={
										configModal.mcp.api_key_field
											? __('API Key', 'agentflow-ai') + ` (${configModal.mcp.api_key_field})`
											: __('API Key', 'agentflow-ai')
									}
									help={
										configModal.mcp.docs_url
											? __('Required API key for this service.', 'agentflow-ai')
											: __('API key for authentication (if required).', 'agentflow-ai')
									}
									value={configModal.config.api_key || ''}
									onChange={(val) => updateModalConfig('api_key', val)}
									type="password"
									placeholder={configModal.mcp.api_key_field || 'sk-...'}
								/>
								{configModal.mcp.docs_url && (
									<p className="-mt-2 text-xs text-primary">
										<a
											href={configModal.mcp.docs_url}
											target="_blank"
											rel="noopener noreferrer"
											className="hover:underline"
										>
											{__('Get API Key ->', 'agentflow-ai')}
										</a>
									</p>
								)}
								<TextField
									label={__('Auth Token (Optional)', 'agentflow-ai')}
									help={__('Bearer token if the server requires additional authentication.', 'agentflow-ai')}
									value={configModal.config.token || ''}
									onChange={(val) => updateModalConfig('token', val)}
									type="password"
								/>
							</div>
						)}

						{/* Environment Variables */}
						<div>
							<h4 className="text-sm font-semibold text-gray-900 dark:text-white">
								{__('Environment Variables', 'agentflow-ai')}
							</h4>
							<p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
								{__('Sensitive keys required by this MCP (e.g., API_KEY). Saved in database.', 'agentflow-ai')}
							</p>

							<TextField
								label={__('Environment Variables (JSON Object)', 'agentflow-ai')}
								value={
									typeof configModal.config.env === 'string'
										? configModal.config.env
										: JSON.stringify(configModal.config.env || {}, null, 2)
								}
								onChange={(val) => {
									try {
										const parsed = JSON.parse(val);
										updateModalConfig('env', parsed);
									} catch (e) {
										// Silent
									}
								}}
								rows={5}
								multiline
							/>
						</div>
					</div>
				</Modal>
			)}
		</div>
	);
}

McpManager.propTypes = {
	mcpConfigs: PropTypes.object,
	onConfigChange: PropTypes.func.isRequired,
};
