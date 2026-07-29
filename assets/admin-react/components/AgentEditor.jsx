/**
 * AgentEditor Component
 *
 * Edit/create agent with tabs for settings, toolkits, and locations.
 * Metronic v9 style UI with Tailwind CSS.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import PropTypes from 'prop-types';
import {
	Settings,
	Wrench,
	Sparkles,
	FileText,
	BookOpen,
	Server,
	Bot,
	Check,
	ChevronRight,
	Save,
	User,
	ToggleLeft,
	ToggleRight,
} from 'lucide-react';

import ToolkitManager from './ToolkitManager';
import AgentSkillAssigner from './AgentSkillAssigner';
import AgentKnowledgeAssigner from './AgentKnowledgeAssigner';

import McpManager from './McpManager';
import InternalMcpManager from './InternalMcpManager';

export default function AgentEditor({
	agent,
	toolkits,
	onSave,
	isNew,
	activeTab: externalActiveTab,
	onTabChange: externalOnTabChange,
	hideTabs = false,
	onToolCountChange,
}) {
	// Tab state
	const [internalActiveTab, setInternalActiveTab] = useState('general');
	const activeTab =
		externalActiveTab !== undefined ? externalActiveTab : internalActiveTab;
	const setActiveTab = externalOnTabChange || setInternalActiveTab;

	// Tool count tracking
	const [toolCount, setToolCount] = useState(0);

	// Form state
	const [formData, setFormData] = useState({
		agent_id: '',
		name: '',
		description: '',
		avatar: 'AI',
		is_active: true,
		config: {
			enabled_toolkits: [],
			disabled_tools: [],
			welcome_message: 'Hi! How can I help you today?',
			quick_actions: [],
			starter_prompts: [],
			prompt_sections: {},
			tool_configs: {},
			toolkit_configs: {},
			skill_mode: 'all',
			enabled_skills: [],
			disabled_skills: [],
			enabled_sections: [],
			knowledge_sources: [],
			mcp_configs: {},
			provider_instance_id: '',
			internal_mcp_config: {
				enabled: true,
				mode: 'none',
				profile: 'custom',
				enabled_tools: [],
				disabled_tools: [],
			},
		},
	});

	const [errors, setErrors] = useState({});

	// Provider instances for dropdown
	const [providerInstances, setProviderInstances] = useState(
		window.swcChatbot?.providerInstances || []
	);

	// Fetch provider instances via API (always refresh for latest data)
	useEffect(() => {
		const apiFetch = window.wp?.apiFetch;
		if (apiFetch) {
			apiFetch({ path: '/smart-ai-chatbot/v1/provider-instances' })
				.then((res) => {
					const items = res?.data || [];
					if (Array.isArray(items) && items.length > 0) {
						const dropdown = items.map((inst) => {
							const name = (inst.display_name || inst.provider || 'Unknown').replace(' (Global Settings)', '');
							const model = inst.model ? ' (' + inst.model + ')' : '';
							const star = inst.is_default ? ' ' : '';
							const label = (inst.model && name.includes(inst.model)) ? name + star : name + model + star;
							return { id: inst.id || '', label };
						});
						setProviderInstances(dropdown);
					}
				})
				.catch(() => { });
		}
	}, []);

	// Populate form with existing agent data
	useEffect(() => {
		if (agent) {
			setFormData({
				agent_id: agent.agent_id || '',
				name: agent.name || '',
				description: agent.description || '',
				avatar: agent.avatar || 'AI',
				is_active: agent.is_active !== false,
				config: {
					enabled_toolkits: agent.config?.enabled_toolkits || [],
					disabled_tools: agent.config?.disabled_tools || [],
					welcome_message:
						agent.config?.welcome_message ||
						'Hi! How can I help you today?',
					quick_actions: agent.config?.quick_actions || [],
					starter_prompts: agent.config?.starter_prompts || [],
					prompt_sections: agent.config?.prompt_sections || {},
					tool_configs: agent.config?.tool_configs || {},
					toolkit_configs: agent.config?.toolkit_configs || {},
					skill_mode: agent.config?.skill_mode || 'all',
					enabled_skills: agent.config?.enabled_skills || [],
					disabled_skills: agent.config?.disabled_skills || [],
					enabled_sections: agent.config?.enabled_sections || [],
					knowledge_sources: agent.config?.knowledge_sources || [],
					mcp_configs:
						agent.config?.mcpConfigs ||
						agent.config?.mcp_configs ||
						{},
					provider_instance_id:
						agent.config?.provider_instance_id || '',
					internal_mcp_config:
						agent.config?.internal_mcp_config || {
							enabled: true,
							mode: 'none',
							profile: 'custom',
							enabled_tools: [],
							disabled_tools: [],
						},
				},
			});
		}
	}, [agent]);

	// Handle field changes
	const updateField = (field, value) => {
		setFormData((prev) => ({ ...prev, [field]: value }));
		if (errors[field]) {
			setErrors((prev) => ({ ...prev, [field]: null }));
		}
	};

	// Handle config changes
	const updateConfig = (field, value) => {
		setFormData((prev) => ({
			...prev,
			config: { ...prev.config, [field]: value },
		}));
	};

	// Validate form
	const validate = () => {
		const newErrors = {};

		if (!formData.name.trim()) {
			newErrors.name = __('Name is required', 'smart-woo-chatbot');
		}

		if (isNew && !formData.agent_id.trim()) {
			newErrors.agent_id = __(
				'Agent ID is required',
				'smart-woo-chatbot'
			);
		}

		if (
			isNew &&
			formData.agent_id &&
			!/^[a-z0-9_]+$/.test(formData.agent_id)
		) {
			newErrors.agent_id = __(
				'Agent ID must be lowercase letters, numbers, and underscores only',
				'smart-woo-chatbot'
			);
		}

		setErrors(newErrors);
		return Object.keys(newErrors).length === 0;
	};

	// Handle submit
	const handleSubmit = async (e) => {
		e.preventDefault();
		if (!validate()) return;
		await onSave(formData);
	};

	// Tab definitions
	const tabs = [
		{ id: 'general', label: __('General', 'smart-woo-chatbot'), icon: Settings },
		{ id: 'prompts', label: __('Prompts', 'smart-woo-chatbot'), icon: Bot },
		{ id: 'knowledge', label: __('Knowledge', 'smart-woo-chatbot'), icon: BookOpen }
	];

	return (
		<form
			id="swc-agent-editor-form"
			className="min-h-screen"
			onSubmit={handleSubmit}
		>
			{/* Metronic v9 Style Tabs */}
			{!hideTabs && (
				<div className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 mb-6 rounded-t-xl overflow-hidden">
					<div className="flex flex-wrap gap-0">
						{tabs.map((tab, index) => {
							const IconComponent = tab.icon;
							const isActive = activeTab === tab.id;
							return (
								<button
									key={tab.id}
									type="button"
									className={`
										group relative flex items-center gap-2.5 px-5 py-4 text-sm font-medium
										transition-all duration-200 border-b-2
										${isActive
											? 'text-primary border-primary bg-primary/5 dark:bg-primary/10'
											: 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50'
										}
									`}
									onClick={() => setActiveTab(tab.id)}
								>
									<IconComponent className={`w-4 h-4 ${isActive ? 'text-primary' : 'text-gray-400 group-hover:text-gray-500'}`} />
									<span>{tab.label}</span>
									{isActive && (
										<span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
									)}
								</button>
							);
						})}
					</div>
				</div>
			)}

			{/* Tab Content */}
			<div className="space-y-6">
				{/* General Tab */}
				{activeTab === 'general' && (
					<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
						{/* Card Header */}
						<div className="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
							<div className="flex items-center gap-3">
								<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
									<Settings className="w-5 h-5 text-primary" />
								</div>
								<div>
									<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
										{__('Basic Information', 'smart-woo-chatbot')}
									</h3>
									<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
										{__("Configure your agent's identity and status", 'smart-woo-chatbot')}
									</p>
								</div>
							</div>
						</div>

						{/* Card Body */}
						<div className="p-6 space-y-6">


							{/* Display Name */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
									<User className="w-4 h-4 text-gray-400" />
									{__('Display Name', 'smart-woo-chatbot')}
									<span className="text-red-500">*</span>
								</label>
								<input
									type="text"
									value={formData.name}
									onChange={(e) => {
										updateField('name', e.target.value);
										if (isNew) {
											updateField(
												'agent_id',
												e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_')
											);
										}
									}}
									placeholder="Shopping Assistant"
									className={`
										w-full h-11 px-4 text-sm rounded-lg border bg-gray-50 dark:bg-gray-900
										placeholder-gray-400 dark:placeholder-gray-500
										focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
										transition-all duration-200
										${errors.name
											? 'border-red-500 focus:border-red-500 focus:ring-red-500/30'
											: 'border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white'
										}
									`}
								/>
								<p className="text-xs text-gray-500 dark:text-gray-400">
									{__('The name users will see for this agent', 'smart-woo-chatbot')}
								</p>
								{errors.name && (
									<p className="text-xs text-red-500 flex items-center gap-1">
										<span className="w-1 h-1 rounded-full bg-red-500" />
										{errors.name}
									</p>
								)}
							</div>

							{/* Description */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
									<FileText className="w-4 h-4 text-gray-400" />
									{__('Description', 'smart-woo-chatbot')}
								</label>
								<textarea
									value={formData.description}
									onChange={(e) => updateField('description', e.target.value)}
									placeholder="Describe what this agent does..."
									rows={3}
									className="
										w-full px-4 py-3 text-sm rounded-lg border border-gray-200 dark:border-gray-600
										bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white
										placeholder-gray-400 dark:placeholder-gray-500
										focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
										transition-all duration-200 resize-none
									"
								/>
								<p className="text-xs text-gray-500 dark:text-gray-400">
									{__('Brief description of what this agent does', 'smart-woo-chatbot')}
								</p>
							</div>

							{/* Status Toggle - Premium Card Style */}
							<div className="mt-6 p-5 rounded-xl bg-gradient-to-r from-gray-50 to-gray-100/50 dark:from-gray-700/50 dark:to-gray-800/30 border border-gray-200/80 dark:border-gray-600/50">
								<div className="flex items-center justify-between">
									<div className="flex items-center gap-4">
										<div className={`
											flex items-center justify-center w-12 h-12 rounded-xl
											${formData.is_active
												? 'bg-green-100 dark:bg-green-900/30'
												: 'bg-gray-200 dark:bg-gray-600'
											}
										`}>
											{formData.is_active ? (
												<ToggleRight className="w-6 h-6 text-green-600 dark:text-green-400" />
											) : (
												<ToggleLeft className="w-6 h-6 text-gray-400" />
											)}
										</div>
										<div>
											<div className="text-sm font-semibold text-gray-900 dark:text-white">
												{__('Agent Status', 'smart-woo-chatbot')}
											</div>
											<div className={`text-sm ${formData.is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'}`}>
												{formData.is_active
													? __('Active - Available for assignment', 'smart-woo-chatbot')
													: __('Inactive - Not available for assignment', 'smart-woo-chatbot')
												}
											</div>
										</div>
									</div>

									{/* Premium Toggle Switch */}
									<label className="relative inline-flex items-center cursor-pointer">
										<input
											type="checkbox"
											checked={formData.is_active}
											onChange={(e) => updateField('is_active', e.target.checked)}
											className="sr-only peer"
										/>
										<div className="
											w-14 h-7 bg-gray-200 dark:bg-gray-600
											peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20
											rounded-full peer
											peer-checked:after:translate-x-7 rtl:peer-checked:after:-translate-x-7
											peer-checked:after:border-white
											after:content-[''] after:absolute after:top-0.5 after:start-[2px]
											after:bg-white after:border-gray-300 after:border after:rounded-full
											after:h-6 after:w-6 after:transition-all after:shadow-sm
											peer-checked:bg-primary
										"/>
									</label>
								</div>
							</div>

							{/* AI Provider Selection */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
									<span className="text-lg"></span>
									{__('AI Provider', 'smart-woo-chatbot')}
								</label>
								<select
									id="swc-agent-provider-select"
									value={formData.config.provider_instance_id || ''}
									onChange={(e) => updateConfig('provider_instance_id', e.target.value)}
									className="w-full h-11 px-4 pr-10 text-sm rounded-lg border bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all duration-200 cursor-pointer"
								>
									<option value="">{__('-- Use Global Settings --', 'smart-woo-chatbot')}</option>
									{providerInstances
										.filter((inst) => inst.id !== '' && inst.id)
										.map((inst) => (
											<option key={inst.id} value={inst.id}>{inst.label}</option>
										))}
								</select>
								<p className="text-xs text-gray-500 dark:text-gray-400">
									{__('Select which AI provider this agent should use', 'smart-woo-chatbot')}
								</p>
							</div>
						</div>
					</div>
				)}

				{/* Toolkits Tab */}
				{activeTab === 'toolkits' && (
					<ToolkitManager
						toolkits={toolkits}
						enabledToolkits={formData.config.enabled_toolkits}
						disabledTools={formData.config.disabled_tools}
						toolConfigs={formData.config.tool_configs}
						toolkitConfigs={formData.config.toolkit_configs}
						onToolkitsChange={(val) =>
							updateConfig('enabled_toolkits', val)
						}
						onToolsChange={(val) =>
							updateConfig('disabled_tools', val)
						}
						onToolConfigChange={(toolId, config) =>
							updateConfig('tool_configs', {
								...formData.config.tool_configs,
								[toolId]: config,
							})
						}
						onToolkitConfigChange={(toolkitId, config) =>
							updateConfig('toolkit_configs', {
								...formData.config.toolkit_configs,
								[toolkitId]: config,
							})
						}
						onToolCountChange={(count) => {
							setToolCount(count);
							if (onToolCountChange) onToolCountChange(count);
						}}
					/>
				)}






				{/* Knowledge Tab */}
				{activeTab === 'knowledge' && (
					<AgentKnowledgeAssigner
						enabledSources={
							formData.config.knowledge_sources || []
						}
						onEnabledChange={(val) =>
							updateConfig('knowledge_sources', val)
						}
					/>
				)}

				{/* Prompts Tab */}
				{activeTab === 'prompts' && (
					<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
						{/* Card Header */}
						<div className="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
							<div className="flex items-center gap-3">
								<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30">
									<Bot className="w-5 h-5 text-purple-600 dark:text-purple-400" />
								</div>
								<div>
									<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
										{__('Agent Personality & Behavior', 'smart-woo-chatbot')}
									</h3>
									<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
										{__('Define how your AI agent thinks and responds', 'smart-woo-chatbot')}
									</p>
								</div>
							</div>
						</div>

						{/* Card Body */}
						<div className="p-6 space-y-6">
							{/* Background Section */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
									<span className="flex items-center justify-center w-6 h-6 rounded-md bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold">1</span>
									{__('Background (Who is this agent?)', 'smart-woo-chatbot')}
								</label>
								<textarea
									value={formData.config.prompt_sections?.background || ''}
									onChange={(val) =>
										updateConfig('prompt_sections', {
											...formData.config.prompt_sections,
											background: val.target.value,
										})
									}
									rows={4}
									placeholder="You are a helpful AI assistant for [store name].&#10;You specialize in [product category] and customer service.&#10;You have access to WooCommerce tools to look up orders and products."
									className="
										w-full px-4 py-3 text-sm rounded-lg border border-gray-200 dark:border-gray-600
										bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white
										placeholder-gray-400 dark:placeholder-gray-500
										focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
										transition-all duration-200 resize-none font-mono
									"
								/>
								<p className="text-xs text-gray-500 dark:text-gray-400">
									{__("Define the agent's identity, role, and expertise", 'smart-woo-chatbot')}
								</p>
							</div>

							{/* Steps Section */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
									<span className="flex items-center justify-center w-6 h-6 rounded-md bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-xs font-bold">2</span>
									{__('Steps (How should it work?)', 'smart-woo-chatbot')}
								</label>
								<textarea
									value={formData.config.prompt_sections?.steps || ''}
									onChange={(val) =>
										updateConfig('prompt_sections', {
											...formData.config.prompt_sections,
											steps: val.target.value,
										})
									}
									rows={5}
									placeholder="1. Understand the user's request clearly.&#10;2. Determine if tools are needed to complete the request.&#10;3. Use appropriate tools to gather information or perform actions.&#10;4. Provide a helpful and accurate response.&#10;5. Ask for clarification if the request is unclear."
									className="
										w-full px-4 py-3 text-sm rounded-lg border border-gray-200 dark:border-gray-600
										bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white
										placeholder-gray-400 dark:placeholder-gray-500
										focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
										transition-all duration-200 resize-none font-mono
									"
								/>
								<p className="text-xs text-gray-500 dark:text-gray-400">
									{__('Define the thinking process', 'smart-woo-chatbot')}
								</p>
							</div>

							{/* Output Section */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
									<span className="flex items-center justify-center w-6 h-6 rounded-md bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 text-xs font-bold">3</span>
									{__('Output (Response format)', 'smart-woo-chatbot')}
								</label>
								<textarea
									value={formData.config.prompt_sections?.output || ''}
									onChange={(val) =>
										updateConfig('prompt_sections', {
											...formData.config.prompt_sections,
											output: val.target.value,
										})
									}
									rows={4}
									placeholder="Be friendly, helpful, and concise.&#10;Use markdown formatting for better readability.&#10;Always confirm before making changes or taking actions."
									className="
										w-full px-4 py-3 text-sm rounded-lg border border-gray-200 dark:border-gray-600
										bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white
										placeholder-gray-400 dark:placeholder-gray-500
										focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
										transition-all duration-200 resize-none font-mono
									"
								/>
								<p className="text-xs text-gray-500 dark:text-gray-400">
									{__('Define how responses should be formatted', 'smart-woo-chatbot')}
								</p>
							</div>

							{/* Tools Usage Section */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
									<span className="flex items-center justify-center w-6 h-6 rounded-md bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-xs font-bold">4</span>
									{__('Tools Usage (When to use tools)', 'smart-woo-chatbot')}
								</label>
								<textarea
									value={formData.config.prompt_sections?.tools_usage || ''}
									onChange={(val) =>
										updateConfig('prompt_sections', {
											...formData.config.prompt_sections,
											tools_usage: val.target.value,
										})
									}
									rows={4}
									placeholder="Use woo_order_lookup when customer asks about their order.&#10;Use woo_product_search to find products by name or category.&#10;Always verify order details before providing tracking information.&#10;Do not modify orders without explicit customer confirmation."
									className="
										w-full px-4 py-3 text-sm rounded-lg border border-gray-200 dark:border-gray-600
										bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white
										placeholder-gray-400 dark:placeholder-gray-500
										focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
										transition-all duration-200 resize-none font-mono
									"
								/>
								<p className="text-xs text-gray-500 dark:text-gray-400">
									{__('Instructions for when and how to use the available tools', 'smart-woo-chatbot')}
								</p>
							</div>
						</div>
					</div>
				)}



			</div>
		</form>
	);
}

AgentEditor.propTypes = {
	agent: PropTypes.shape({
		id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
		agent_id: PropTypes.string,
		name: PropTypes.string,
		description: PropTypes.string,
		avatar: PropTypes.string,
		is_active: PropTypes.oneOfType([PropTypes.bool, PropTypes.number]),
		config: PropTypes.object,
	}),
	toolkits: PropTypes.object,
	onSave: PropTypes.func.isRequired,
	isNew: PropTypes.bool,
	activeTab: PropTypes.string,
	onTabChange: PropTypes.func,
	hideTabs: PropTypes.bool,
	onToolCountChange: PropTypes.func,
};
