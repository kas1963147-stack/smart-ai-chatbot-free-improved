/**
 * ToolkitManager Component - Metronic v9 Style
 *
 * Toggle toolkits and individual tools for an agent.
 * Premium Tailwind styling with Lucide React icons.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { Wrench, Package, Search } from 'lucide-react';

import ToolConfigModal from './ToolConfigModal';
import { Button, Checkbox, Tabs, TextField, cn } from './ui';

// Category configuration for toolkit grouping
const CATEGORY_MAP = {
	woocommerce: 'WooCommerce',
	wordpress: 'WordPress',
	content: 'Content',
	integrations: 'Integrations',
	file: 'File Access',
	security: 'Security',
	seo: 'SEO',
	general: 'General',
};

export default function ToolkitManager({
	toolkits,
	enabledToolkits,
	disabledTools,
	toolConfigs = {},
	toolkitConfigs = {},
	onToolkitsChange,
	onToolsChange,
	onToolConfigChange,
	onToolkitConfigChange,
	onToolCountChange,
}) {
	const [expandedToolkit, setExpandedToolkit] = useState(null);
	const [configModal, setConfigModal] = useState(null);
	const [connections, setConnections] = useState([]);
	const [searchQuery, setSearchQuery] = useState('');
	const [activeCategory, setActiveCategory] = useState('all');

	// Fetch available connections for connection selector
	useEffect(() => {
		fetchConnections();
	}, []);

	const fetchConnections = async () => {
		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/connections',
			});
			if (response.success && response.data) {
				setConnections(response.data);
			}
		} catch (err) {
			// Connections fetch may fail if not configured - silent fail
		}
	};

	// Get toolkit category based on id/name
	const getToolkitCategory = (toolkitId) => {
		const id = toolkitId.toLowerCase();
		if (id.includes('woo') || id.includes('commerce')) {
			return 'woocommerce';
		}
		if (
			id.includes('wordpress') ||
			id.includes('core') ||
			id.includes('content')
		) {
			return 'wordpress';
		}
		if (id.includes('integr') || id.includes('connect')) {
			return 'integrations';
		}
		if (id.includes('file')) {
			return 'file';
		}
		if (id.includes('secur')) {
			return 'security';
		}
		if (id.includes('seo')) {
			return 'seo';
		}
		return 'general';
	};

	// Get unique categories from toolkits
	const availableCategories = useMemo(() => {
		if (!toolkits) {
			return [];
		}
		const cats = new Set();
		Object.entries(toolkits).forEach(([id]) => {
			cats.add(getToolkitCategory(id));
		});
		return Array.from(cats);
	}, [toolkits]);

	// Filter toolkits by search and category
	const filteredToolkits = useMemo(() => {
		if (!toolkits) {
			return [];
		}

		let result = Object.entries(toolkits);

		// Filter by category
		if (activeCategory !== 'all') {
			result = result.filter(
				([id]) => getToolkitCategory(id) === activeCategory
			);
		}

		// Filter by search query
		if (searchQuery.trim()) {
			const query = searchQuery.toLowerCase();
			result = result.filter(
				([id, toolkit]) =>
					toolkit.name.toLowerCase().includes(query) ||
					toolkit.description?.toLowerCase().includes(query) ||
					id.toLowerCase().includes(query)
			);
		}

		return result;
	}, [toolkits, activeCategory, searchQuery]);

	// Group filtered toolkits by category
	const groupedToolkits = useMemo(() => {
		const groups = {};
		filteredToolkits.forEach(([id, toolkit]) => {
			const cat = getToolkitCategory(id, toolkit);
			if (!groups[cat]) {
				groups[cat] = [];
			}
			groups[cat].push([id, toolkit]);
		});
		return groups;
	}, [filteredToolkits]);

	const getEnabledToolkits = () => {
		return (enabledToolkits || []).filter((id) => id !== '__none__');
	};

	// Check if a toolkit is enabled (empty array means none enabled)
	const isToolkitEnabled = (toolkitId) => {
		const current = getEnabledToolkits();
		return current.includes(toolkitId);
	};

	// Check if a tool is disabled
	const isToolDisabled = (toolId) => {
		return disabledTools?.includes(toolId) || false;
	};

	// Check if tool/toolkit has config schema
	const hasConfigSchema = (toolkit) => {
		return (
			toolkit.config_schema &&
			(toolkit.config_schema.toolkit?.length > 0 ||
				Object.keys(toolkit.config_schema).some(
					(k) =>
						k !== 'toolkit' &&
						toolkit.config_schema[k]?.length > 0
				))
		);
	};

	// Check if tool has required config
	const getToolConfigStatus = (toolkitId, toolId, toolkit) => {
		const schema = toolkit.config_schema?.[toolId];
		if (!schema || schema.length === 0) {
			return 'none';
		}

		const config = toolConfigs[toolId] || {};
		const hasRequired = schema.some((f) => f.required);

		if (!hasRequired) {
			return 'optional';
		}

		const allRequiredSet = schema
			.filter((f) => f.required)
			.every((f) => config[f.id]);

		return allRequiredSet ? 'configured' : 'needs_config';
	};

	// Toggle toolkit
	const toggleToolkit = (toolkitId) => {
		const enabled = isToolkitEnabled(toolkitId);
		const current = getEnabledToolkits();
		const next = enabled
			? current.filter((id) => id !== toolkitId)
			: [...current, toolkitId];

		onToolkitsChange(next);
	};

	// Toggle individual tool
	const toggleTool = (toolId, enabled) => {
		let newDisabled;

		if (enabled) {
			newDisabled = disabledTools.filter((id) => id !== toolId);
		} else {
			newDisabled = [...disabledTools, toolId];
		}

		onToolsChange(newDisabled);
	};

	// Select All in view
	const selectAllInView = () => {
		const viewedIds = filteredToolkits.map(([id]) => id);
		const currentEnabled = getEnabledToolkits();
		const newEnabled = [
			...new Set([...currentEnabled, ...viewedIds]),
		];
		onToolkitsChange(newEnabled);
	};

	// Deselect All in view
	const deselectAllInView = () => {
		const viewedIds = filteredToolkits.map(([id]) => id);
		const currentEnabled = getEnabledToolkits();
		const remaining = currentEnabled.filter(
			(id) => !viewedIds.includes(id)
		);
		onToolkitsChange(remaining);
	};

	// Count enabled toolkits
	const getEnabledCount = () => {
		if (!toolkits) {
			return 0;
		}
		const currentEnabled = getEnabledToolkits();
		return currentEnabled.filter((id) => toolkits[id]).length;
	};

	// Calculate total tool count from enabled toolkits (minus disabled tools)
	const getTotalToolCount = () => {
		if (!toolkits) return 0;
		const currentEnabled = getEnabledToolkits();
		let total = 0;
		currentEnabled.forEach((toolkitId) => {
			const toolkit = toolkits[toolkitId];
			if (toolkit) {
				total += toolkit.tool_count || 0;
			}
		});
		// Subtract disabled tools count
		const disabledCount = (disabledTools || []).length;
		return Math.max(0, total - disabledCount);
	};

	// Notify parent of tool count changes
	const totalToolCount = getTotalToolCount();
	useEffect(() => {
		if (onToolCountChange) {
			onToolCountChange(totalToolCount);
		}
	}, [totalToolCount, onToolCountChange]);


	// Open config modal for toolkit
	const openToolkitConfig = (e, toolkitId, toolkit) => {
		e.stopPropagation();
		setConfigModal({
			type: 'toolkit',
			id: toolkitId,
			name: toolkit.name,
			schema: toolkit.config_schema?.toolkit || [],
		});
	};

	// Open config modal for tool
	const openToolConfig = (toolkitId, toolId, toolkit) => {
		setConfigModal({
			type: 'tool',
			id: toolId,
			name: toolId,
			schema: toolkit.config_schema?.[toolId] || [],
		});
	};

	// Handle config save
	const handleConfigSave = async (id, config) => {
		if (configModal.type === 'toolkit') {
			onToolkitConfigChange?.(id, config);
		} else {
			onToolConfigChange?.(id, config);
		}
		setConfigModal(null);
	};

	if (!toolkits || Object.keys(toolkits).length === 0) {
		return (
			<div className="rounded-2xl border border-slate-200/70 bg-white p-6 text-sm text-slate-500 shadow-sm dark:border-slate-800/70 dark:bg-slate-900">
				<p>{__('No toolkits available.', 'agentflow-ai')}</p>
			</div>
		);
	}

	const totalToolkits = Object.keys(toolkits).length;
	const enabledCount = getEnabledCount();
	const categoryTabs = [
		{
			id: 'all',
			label: `${__('All', 'agentflow-ai')} (${totalToolkits})`,
		},
		...availableCategories.map((catId) => ({
			id: catId,
			label: CATEGORY_MAP[catId] || catId,
		})),
	];

	return (
		<div className="w-full">
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
				{/* Header with Icon */}
				<div className="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
					<div className="flex items-center gap-3">
						<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30">
							<Wrench className="w-5 h-5 text-orange-600 dark:text-orange-400" />
						</div>
						<div>
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
								{__('Agent Toolkits', 'agentflow-ai')}
							</h3>
							<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
								{__('Select toolkits to enable for this agent. Only checked toolkits will be available.', 'agentflow-ai')}
							</p>
						</div>
					</div>
				</div>

				<div className="flex flex-col gap-3 border-b border-slate-200/70 px-5 py-4 dark:border-slate-800/70 md:flex-row md:items-center">
					<div className="flex-1 min-w-0">
						<TextField
							value={searchQuery}
							onChange={setSearchQuery}
							placeholder={__(
								'Search toolkits...',
								'agentflow-ai'
							)}
							className="w-full"
						/>
					</div>
					<div className="flex flex-wrap items-center gap-2">
						<Button
							variant="primary"
							size="sm"
							onClick={selectAllInView}
						>
							{__('Enable All', 'agentflow-ai')}
						</Button>
						<Button
							variant="secondary"
							size="sm"
							onClick={deselectAllInView}
						>
							{__('Disable All', 'agentflow-ai')}
						</Button>
					</div>
				</div>

				<div className="px-5 py-3">
					<Tabs
						tabs={categoryTabs}
						activeId={activeCategory}
						onChange={setActiveCategory}
					/>
				</div>

				<div className="px-5 py-4">
					{Object.entries(groupedToolkits).map(
						([category, categoryToolkits]) => (
							<div key={category} className="mb-6">
								<div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
									{CATEGORY_MAP[category] || category}
									<span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-300">
										{categoryToolkits.length}
									</span>
								</div>

								<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
									{categoryToolkits.map(
										([toolkitId, toolkit]) => {
											const enabled =
												isToolkitEnabled(toolkitId);
											const hasConfig =
												hasConfigSchema(toolkit);
											const isExpanded =
												expandedToolkit === toolkitId;

											return (
												<div
													key={toolkitId}
													onClick={() =>
														toggleToolkit(
															toolkitId
														)
													}
													className={cn(
														'cursor-pointer rounded-xl border p-4 transition',
														enabled
															? 'border-primary/60 bg-primary/5 shadow-sm'
															: 'border-slate-200 bg-white hover:border-primary/30 hover:shadow-sm dark:border-slate-800 dark:bg-slate-950'
													)}
												>
													<div className="flex items-start gap-3">
														<Checkbox
															checked={enabled}
															onChange={() =>
																toggleToolkit(
																	toolkitId
																)
															}
															onClick={(e) =>
																e.stopPropagation()
															}
														/>
														<div className="min-w-0 flex-1">
															<div
																className={cn(
																	'flex items-center gap-2 text-sm font-semibold',
																	enabled
																		? 'text-primary'
																		: 'text-slate-900 dark:text-slate-100'
																)}
															>
																{toolkit.name}
																<span
																	className={cn(
																		'rounded-md px-1.5 py-0.5 text-[10px] font-medium',
																		enabled
																			? 'bg-primary/10 text-primary'
																			: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
																	)}
																>
																	{
																		toolkit.tool_count
																	}{' '}
																	{__(
																		'tools',
																		'agentflow-ai'
																	)}
																</span>
															</div>
															<div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
																{toolkit.description?.substring(
																	0,
																	100
																)}
																{toolkit
																	.description
																	?.length >
																	100
																	? '...'
																	: ''}
															</div>

															{enabled && (
																<div className="mt-3 flex flex-wrap gap-2">
																	{hasConfig &&
																		toolkit
																			.config_schema
																			?.toolkit
																			?.length >
																		0 && (
																			<Button
																				variant="ghost"
																				size="sm"
																				onClick={(
																					e
																				) =>
																					openToolkitConfig(
																						e,
																						toolkitId,
																						toolkit
																					)
																				}
																			>
																				{__(
																					'Configure',
																					'agentflow-ai'
																				)}
																			</Button>
																		)}
																	<Button
																		variant={
																			isExpanded
																				? 'secondary'
																				: 'ghost'
																		}
																		size="sm"
																		onClick={(
																			e
																		) => {
																			e.stopPropagation();
																			setExpandedToolkit(
																				isExpanded
																					? null
																					: toolkitId
																			);
																		}}
																	>
																		{isExpanded
																			? __(
																				'Hide Tools',
																				'agentflow-ai'
																			)
																			: __(
																				'Show Tools',
																				'agentflow-ai'
																			)}
																	</Button>
																</div>
															)}
														</div>
													</div>

													{enabled &&
														isExpanded &&
														toolkit.categories && (
															<div
																onClick={(
																	e
																) =>
																	e.stopPropagation()
																}
																className="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800"
															>
																{Object.entries(
																	toolkit.categories
																).map(
																	([
																		catId,
																		toolCategory,
																	]) => (
																		<div
																			key={
																				catId
																			}
																			className="mb-3"
																		>
																			<div className="mb-1 text-xs font-semibold text-slate-600 dark:text-slate-300">
																				{
																					toolCategory.name
																				}
																			</div>
																			{Object.entries(
																				toolCategory.tools
																			).map(
																				([
																					toolId,
																				]) => {
																					const configStatus =
																						getToolConfigStatus(
																							toolkitId,
																							toolId,
																							toolkit
																						);
																					const hasToolConfig =
																						configStatus !==
																						'none';

																					return (
																						<div
																							key={
																								toolId
																							}
																							className="flex flex-wrap items-center gap-2 py-1 text-xs"
																						>
																							<Checkbox
																								checked={
																									!isToolDisabled(
																										toolId
																									)
																								}
																								onChange={(
																									value
																								) =>
																									toggleTool(
																										toolId,
																										value
																									)
																								}
																							/>
																							<span
																								className={cn(
																									'flex-1',
																									isToolDisabled(toolId)
																										? 'text-slate-400'
																										: 'text-slate-700 dark:text-slate-200'
																								)}
																							>
																								{
																									toolId
																								}
																							</span>
																							{configStatus ===
																								'needs_config' && (
																									<span className="rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700">
																										{__(
																											'Setup',
																											'agentflow-ai'
																										)}
																									</span>
																								)}
																							{hasToolConfig &&
																								!isToolDisabled(
																									toolId
																								) && (
																									<Button
																										variant="ghost"
																										size="sm"
																										onClick={() =>
																											openToolConfig(
																												toolkitId,
																												toolId,
																												toolkit
																											)
																										}
																									>
																										{__(
																											'Edit',
																											'agentflow-ai'
																										)}
																									</Button>
																								)}
																						</div>
																					);
																				}
																			)}
																		</div>
																	)
																)}
															</div>
														)}

													{!toolkit.available && (
														<div className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-700">
															{__(
																'Requires additional plugins',
																'agentflow-ai'
															)}
														</div>
													)}
												</div>
											);
										}
									)}
								</div>
							</div>
						)
					)}

					{filteredToolkits.length === 0 && (
						<div className="py-10 text-center text-sm text-slate-500">
							{totalToolkits === 0
								? __(
									'No toolkits available.',
									'agentflow-ai'
								)
								: __(
									'No toolkits match your search.',
									'agentflow-ai'
								)}
						</div>
					)}
				</div>

				{/* Summary Footer */}
				<div className="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
					<div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
						<div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
							<div className="flex items-center justify-center w-6 h-6 rounded-full bg-orange-100 dark:bg-orange-900/30">
								<Wrench className="w-3.5 h-3.5 text-orange-600 dark:text-orange-400" />
							</div>
							<span>
								<strong className="text-gray-900 dark:text-white">{enabledCount}</strong>
								{' / '}
								{totalToolkits}
								{' '}
								{__('toolkits enabled', 'agentflow-ai')}
							</span>
						</div>
						{/* Tool count indicator with color coding */}
						<div className={cn(
							'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium',
							totalToolCount >= 128
								? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
								: totalToolCount >= 100
									? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
									: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
						)}>
							<span className={cn(
								'w-2 h-2 rounded-full',
								totalToolCount >= 128 ? 'bg-red-500' : totalToolCount >= 100 ? 'bg-amber-500' : 'bg-green-500'
							)} />
							<span>
								<strong>{totalToolCount}</strong> / 128 {__('tools', 'agentflow-ai')}
							</span>
							{totalToolCount >= 128 && (
								<span className="text-xs">({__('over limit!', 'agentflow-ai')})</span>
							)}
						</div>
					</div>
				</div>
			</div>

			{configModal && (
				<ToolConfigModal
					tool={{
						id: configModal.id,
						name: configModal.name,
					}}
					schema={configModal.schema}
					currentConfig={
						configModal.type === 'toolkit'
							? toolkitConfigs[configModal.id]
							: toolConfigs[configModal.id]
					}
					connections={connections}
					onSave={handleConfigSave}
					onCancel={() => setConfigModal(null)}
				/>
			)}
		</div>
	);
}

ToolkitManager.propTypes = {
	toolkits: PropTypes.object,
	enabledToolkits: PropTypes.array,
	disabledTools: PropTypes.array,
	toolConfigs: PropTypes.object,
	toolkitConfigs: PropTypes.object,
	onToolkitsChange: PropTypes.func.isRequired,
	onToolsChange: PropTypes.func.isRequired,
	onToolConfigChange: PropTypes.func,
	onToolkitConfigChange: PropTypes.func,
	onToolCountChange: PropTypes.func,
};
