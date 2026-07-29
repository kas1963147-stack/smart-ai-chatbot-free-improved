/**
 * GroupEditor Component
 *
 * Edit/create agent groups with orchestration settings and member management.
 * Refactored to Metronic v9 Premium UI with Tailwind CSS.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import {
	Users,
	Settings,
	MessageSquare,
	Save,
	Loader2,
	Info,
	Zap,
	ArrowRight,
	Layers,
	RefreshCw,
	UserPlus,
	ToggleLeft,
	ToggleRight,
	GripVertical,
	Trash2,
	AlertCircle,
	ListOrdered,
	Plus,
	ChevronDown,
	ChevronUp,
	ChevronLeft,
	FileText,
	ClipboardList,
	Crown,
	Brain,
} from 'lucide-react';
import LocationAssigner from './LocationAssigner';
import SortableMemberList from './SortableMemberList';

// Orchestration mode configurations with visual styling
const ORCHESTRATION_MODES = [
	{
		value: 'router',
		label: __('Auto-Router (Best Agent)', 'smart-woo-chatbot'),
		description: __('Automatically routes user input to the most relevant agent.', 'smart-woo-chatbot'),
		icon: Zap,
		bgColor: 'bg-blue-50 dark:bg-blue-900/30',
		textColor: 'text-blue-600 dark:text-blue-400',
		borderColor: 'border-blue-200 dark:border-blue-800',
	},
	{
		value: 'sequential',
		label: __('Sequential (In Order)', 'smart-woo-chatbot'),
		description: __('Agents execute one by one in the order defined below.', 'smart-woo-chatbot'),
		icon: ArrowRight,
		bgColor: 'bg-green-50 dark:bg-green-900/30',
		textColor: 'text-green-600 dark:text-green-400',
		borderColor: 'border-green-200 dark:border-green-800',
	},
	{
		value: 'parallel',
		label: __('Parallel (All At Once)', 'smart-woo-chatbot'),
		description: __('All agents execute simultaneously and results are combined.', 'smart-woo-chatbot'),
		icon: Layers,
		bgColor: 'bg-purple-50 dark:bg-purple-900/30',
		textColor: 'text-purple-600 dark:text-purple-400',
		borderColor: 'border-purple-200 dark:border-purple-800',
	},
	{
		value: 'handoff',
		label: __('Manual Handoff', 'smart-woo-chatbot'),
		description: __('Manual handoff between agents with explicit triggers.', 'smart-woo-chatbot'),
		icon: RefreshCw,
		bgColor: 'bg-orange-50 dark:bg-orange-900/30',
		textColor: 'text-orange-600 dark:text-orange-400',
		borderColor: 'border-orange-200 dark:border-orange-800',
	},
	{
		value: 'supervisor',
		label: __('Supervisor (Autonomous Manager)', 'smart-woo-chatbot'),
		description: __('A Manager agent uses AI to autonomously decide which team member handles each task. The Primary agent acts as the Manager and delegates to other agents automatically.', 'smart-woo-chatbot'),
		icon: Brain,
		bgColor: 'bg-rose-50 dark:bg-rose-900/30',
		textColor: 'text-rose-600 dark:text-rose-400',
		borderColor: 'border-rose-200 dark:border-rose-800',
	},
];

export default function GroupEditor({ group, onSave, onCancel, isNew }) {
	const [formData, setFormData] = useState({
		group_id: '',
		name: '',
		description: '',
		avatar: '',
		orchestration_mode: 'router',
		welcome_message: '',
		is_active: true,
		members: [],
		routing_config: {},
		workflow_steps: [],
	});

	const [allAgents, setAllAgents] = useState([]);
	const [availableAgents, setAvailableAgents] = useState([]);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [errors, setErrors] = useState({});
	const [memberToAdd, setMemberToAdd] = useState('');

	// Load agents and group data
	useEffect(() => {
		const loadData = async () => {
			try {
				// Load all agents for selection
				const agentsResp = await apiFetch({
					path: `/smart-ai-chatbot/v1/agents?include_inactive=true&_t=${Date.now()}`,
				});
				setAllAgents(agentsResp.agents || []);

				if (group && !isNew) {
					// Load group members
					const membersResp = await apiFetch({
						path: `/smart-ai-chatbot/v1/agent-groups/${group.id}/members`,
					});

					const routingConfig = group.routing_config || {};
					setFormData({
						id: group.id,
						group_id: group.group_id,
						name: group.name,
						description: group.description || '',
						avatar: group.avatar || '',
						orchestration_mode:
							group.orchestration_mode || 'router',
						welcome_message: group.welcome_message || '',
						is_active: group.is_active !== false,
						members: membersResp || [],
						routing_config: routingConfig,
						workflow_steps: routingConfig.workflow_steps || [],
					});
				} else if (isNew) {
					setFormData((prev) => ({
						...prev,
						welcome_message:
							"Hello! We're a team of assistants here to help. How can we assist you today?",
					}));
				}
			} catch (err) {
				console.error('Failed to load data:', err);
				setErrors({ general: 'Failed to load data.' });
			} finally {
				setLoading(false);
			}
		};

		loadData();
	}, [group, isNew]);

	// Update available agents (exclude already added)
	useEffect(() => {
		if (!loading) {
			const memberIds = formData.members.map((m) =>
				parseInt(m.agent_db_id)
			);
			setAvailableAgents(
				allAgents.filter(
					(a) => !memberIds.includes(parseInt(a.id))
				)
			);
		}
	}, [allAgents, formData.members, loading]);

	const updateField = useCallback((field, value) => {
		setFormData((prev) => ({ ...prev, [field]: value }));
		if (errors[field]) {
			setErrors((prev) => ({ ...prev, [field]: null }));
		}
	}, [errors]);

	const addMember = useCallback((agentId) => {
		if (!agentId) return;

		const agent = allAgents.find(
			(a) => parseInt(a.id) === parseInt(agentId)
		);
		if (!agent) {
			return;
		}

		if (!agent.is_active) {
			window.alert(
				__('This agent is currently inactive. Please activate the agent first before adding it to a team.', 'smart-woo-chatbot')
			);
			return;
		}

		const newMember = {
			agent_db_id: agent.id,
			agent_name: agent.name,
			agent_avatar: agent.avatar || '',
			role: 'specialist',
			execution_order: formData.members.length,
			routing_keywords: '',
		};

		setFormData((prev) => ({
			...prev,
			members: [...prev.members, newMember],
		}));
		setMemberToAdd('');
	}, [allAgents, formData.members.length]);

	const removeMember = useCallback((index) => {
		const newMembers = [...formData.members];
		newMembers.splice(index, 1);
		setFormData((prev) => ({ ...prev, members: newMembers }));
	}, [formData.members]);

	const updateMember = useCallback((index, field, value) => {
		const newMembers = [...formData.members];
		newMembers[index] = { ...newMembers[index], [field]: value };
		setFormData((prev) => ({ ...prev, members: newMembers }));
	}, [formData.members]);

	// Workflow step management
	const addWorkflowStep = useCallback(() => {
		setFormData((prev) => ({
			...prev,
			workflow_steps: [
				...prev.workflow_steps,
				{ type: 'instruction', name: '', description: '', agent_id: '', input: '' },
			],
		}));
	}, []);

	const removeWorkflowStep = useCallback((index) => {
		setFormData((prev) => ({
			...prev,
			workflow_steps: prev.workflow_steps.filter((_, idx) => idx !== index),
		}));
	}, []);

	const updateWorkflowStep = useCallback((index, field, value) => {
		setFormData((prev) => {
			const newSteps = [...prev.workflow_steps];
			newSteps[index] = { ...newSteps[index], [field]: value };
			return { ...prev, workflow_steps: newSteps };
		});
	}, []);

	const moveWorkflowStep = useCallback((index, direction) => {
		setFormData((prev) => {
			const newSteps = [...prev.workflow_steps];
			const newIndex = index + direction;
			if (newIndex < 0 || newIndex >= newSteps.length) return prev;
			[newSteps[index], newSteps[newIndex]] = [newSteps[newIndex], newSteps[index]];
			return { ...prev, workflow_steps: newSteps };
		});
	}, []);

	const handleSubmit = async (e) => {
		e.preventDefault();
		setSaving(true);
		setErrors({});

		try {
			if (!formData.name) {
				throw new Error('Name is required');
			}
			if (isNew && !formData.group_id) {
				throw new Error('Group ID is required');
			}

			// Merge workflow_steps into routing_config before saving
			const dataToSave = {
				...formData,
				routing_config: {
					...formData.routing_config,
					workflow_steps: formData.workflow_steps,
				},
			};

			await onSave(dataToSave);
		} catch (err) {
			console.error('Failed to save group:', err);
			setErrors({ general: err.message || 'Failed to save group' });
			setSaving(false);
		}
	};

	const selectedMode = ORCHESTRATION_MODES.find((m) => m.value === formData.orchestration_mode) || ORCHESTRATION_MODES[0];

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-16">
				<div className="relative">
					<div className="w-12 h-12 border-4 border-primary/20 rounded-full" />
					<div className="absolute inset-0 w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
				</div>
				<p className="mt-4 text-gray-500 dark:text-slate-400 font-medium">{__('Loading...', 'smart-woo-chatbot')}</p>
			</div>
		);
	}

	return (
		<form onSubmit={handleSubmit} className="space-y-6 animate-in fade-in duration-300">
			{/* Header with Back + Action Buttons */}
			<div className="flex items-center justify-between">
				<div className="flex items-center gap-4">
					<button
						type="button"
						onClick={onCancel}
						className="flex items-center gap-1.5 px-3 py-2 text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-all duration-200"
					>
						<ChevronLeft className="w-5 h-5" />
						<span className="font-medium text-sm">{__('Back', 'smart-woo-chatbot')}</span>
					</button>
					<div className="flex items-center gap-3">
						<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-primary/10 text-primary">
							<Users className="w-5 h-5 text-primary" />
						</div>
						<div>
							<h2 className="text-xl font-bold text-gray-900 dark:text-white">
								{isNew ? __('New Agent Group', 'smart-woo-chatbot') : __('Edit Agent Group', 'smart-woo-chatbot')}
							</h2>
							<p className="text-sm text-gray-500 dark:text-slate-400">
								{__('Configure team settings and members', 'smart-woo-chatbot')}
							</p>
						</div>
					</div>
				</div>

				{/* Action Buttons */}
				<div className="flex items-center gap-3">
					<button
						type="button"
						onClick={onCancel}
						disabled={saving}
						className="px-4 py-2.5 text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-xl font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-all duration-200 disabled:opacity-50"
					>
						{__('Cancel', 'smart-woo-chatbot')}
					</button>
					<button
						type="submit"
						disabled={saving}
						className="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl font-medium shadow-md shadow-primary/20 hover:bg-primary/90 transition-all duration-200 disabled:opacity-50"
					>
						{saving ? (
							<>
								<Loader2 className="w-4 h-4 animate-spin" />
								{__('Saving...', 'smart-woo-chatbot')}
							</>
						) : (
							<>
								<Save className="w-4 h-4" />
								{isNew ? __('Create Team', 'smart-woo-chatbot') : __('Save Changes', 'smart-woo-chatbot')}
							</>
						)}
					</button>
				</div>
			</div>

			{/* Error Alert */}
			{errors.general && (
				<div className="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl flex items-start gap-3">
					<AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
					<div>
						<p className="font-medium text-red-800 dark:text-red-300">{__('Error', 'smart-woo-chatbot')}</p>
						<p className="text-sm text-red-600 dark:text-red-400">{errors.general}</p>
					</div>
				</div>
			)}

			<div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
				{/* Main Column */}
				<div className="lg:col-span-2 space-y-6">
					{/* Team Settings Card */}
					<div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
						{/* Card Header */}
						<div className="px-6 py-5 border-b border-gray-100 dark:border-slate-800 bg-gradient-to-r from-slate-50 to-white dark:from-slate-900/50 dark:to-slate-900/80">
							<div className="flex items-center gap-3">
								<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/50">
									<Settings className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
								</div>
								<div>
									<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
										{__('Team Settings', 'smart-woo-chatbot')}
									</h3>
									<p className="text-sm text-gray-500 dark:text-slate-400">
										{__('Basic information for your agent team', 'smart-woo-chatbot')}
									</p>
								</div>
							</div>
						</div>

						{/* Card Body */}
						<div className="p-6 space-y-5">


							{/* Team Name */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-slate-300">
									<Users className="w-4 h-4 text-gray-400 dark:text-slate-500" />
									{__('Team Name', 'smart-woo-chatbot')}
									<span className="text-red-500">*</span>
								</label>
								<input
									type="text"
									value={formData.name}
									onChange={(e) => {
										updateField('name', e.target.value);
										if (isNew) {
											updateField('group_id', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'));
										}
									}}
									placeholder="Support Team"
									className="w-full h-11 px-4 text-sm rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all duration-200"
								/>
							</div>

							{/* Description */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-slate-300">
									<MessageSquare className="w-4 h-4 text-gray-400 dark:text-slate-500" />
									{__('Description', 'smart-woo-chatbot')}
								</label>
								<textarea
									value={formData.description}
									onChange={(e) => updateField('description', e.target.value)}
									placeholder="Describe what this team does..."
									rows={3}
									className="w-full px-4 py-3 text-sm rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all duration-200 resize-none"
								/>
							</div>

							{/* Orchestration Strategy */}
							<div className="space-y-3">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-slate-300">
									<Zap className="w-4 h-4 text-gray-400 dark:text-slate-500" />
									{__('Orchestration Strategy', 'smart-woo-chatbot')}
								</label>
								<select
									value={formData.orchestration_mode}
									onChange={(e) => updateField('orchestration_mode', e.target.value)}
									className="w-full h-11 px-4 text-sm rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all duration-200 cursor-pointer"
								>
									{ORCHESTRATION_MODES.map((mode) => (
										<option key={mode.value} value={mode.value}>
											{mode.label}
										</option>
									))}
								</select>
								{/* Strategy Info Box */}
								<div className={`p-4 rounded-xl ${selectedMode.bgColor} border ${selectedMode.borderColor}`}>
									<div className="flex items-start gap-3">
										<div className={`p-2 rounded-lg ${selectedMode.bgColor} ${selectedMode.textColor}`}>
											<selectedMode.icon className="w-4 h-4" />
										</div>
										<div>
											<p className={`font-medium ${selectedMode.textColor}`}>{selectedMode.label}</p>
											<p className="text-sm text-gray-600 dark:text-slate-400 mt-0.5">{selectedMode.description}</p>
										</div>
									</div>
								</div>
							</div>

							{/* Welcome Message */}
							<div className="space-y-2">
								<label className="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-slate-300">
									<MessageSquare className="w-4 h-4 text-gray-400 dark:text-slate-500" />
									{__('Welcome Message', 'smart-woo-chatbot')}
								</label>
								<textarea
									value={formData.welcome_message}
									onChange={(e) => updateField('welcome_message', e.target.value)}
									placeholder="Hello! We're a team of assistants here to help. How can we assist you today?"
									rows={3}
									className="w-full px-4 py-3 text-sm rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all duration-200 resize-none"
								/>
								<p className="text-xs text-gray-500 dark:text-slate-400">
									{__('Message displayed when the group chat starts', 'smart-woo-chatbot')}
								</p>
							</div>
						</div>
					</div>

					{/* Team Members Card */}
					<div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
						{/* Card Header */}
						<div className="px-6 py-5 border-b border-gray-100 dark:border-slate-800 bg-gradient-to-r from-slate-50 to-white dark:from-slate-900/50 dark:to-slate-900/80">
							<div className="flex items-center justify-between">
								<div className="flex items-center gap-3">
									<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/50">
										<UserPlus className="w-5 h-5 text-purple-600 dark:text-purple-400" />
									</div>
									<div>
										<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
											{__('Team Members', 'smart-woo-chatbot')}
										</h3>
										<p className="text-sm text-gray-500 dark:text-slate-400">
											{formData.members.length} {formData.members.length === 1 ? __('agent', 'smart-woo-chatbot') : __('agents', 'smart-woo-chatbot')}
										</p>
									</div>
								</div>
								{/* Add Agent Dropdown */}
								<select
									value={memberToAdd}
									onChange={(e) => addMember(e.target.value)}
									disabled={availableAgents.length === 0}
									className="h-10 pl-4 pr-10 text-sm rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
								>
									<option value="">{__('+ Add Agent', 'smart-woo-chatbot')}</option>
									{availableAgents.map((agent) => (
										<option key={agent.id} value={agent.id}>
											{agent.name}
										</option>
									))}
								</select>
							</div>
						</div>

						{/* Card Body */}
						<div className="p-6">
							{/* Supervisor mode explainer */}
							{formData.orchestration_mode === 'supervisor' && formData.members.length > 0 && (
								<div className="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
									<div className="flex items-start gap-3">
										<Crown className="w-5 h-5 text-rose-500 mt-0.5 flex-shrink-0" />
										<div>
											<p className="text-sm font-medium text-rose-800 dark:text-rose-300">
												{__('How Supervisor Mode Works', 'smart-woo-chatbot')}
											</p>
											<p className="text-xs text-rose-700/80 dark:text-rose-400/80 mt-1">
												{__('The agent with the "Primary" role acts as the Manager. It uses AI to autonomously decide which task to delegate to which team member. All other agents become Workers.', 'smart-woo-chatbot')}
											</p>
											<div className="mt-2 flex flex-wrap gap-2">
												{formData.members.map((m, i) => (
													<span key={i} className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full ${
														m.role === 'primary'
															? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-300 dark:border-rose-700'
															: 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300'
													}`}>
														{m.role === 'primary' && <Crown className="w-3 h-3" />}
														{m.agent_name || `Agent ${i + 1}`}
														<span className="text-[10px] opacity-60">
															{m.role === 'primary' ? __('Manager', 'smart-woo-chatbot') : __('Worker', 'smart-woo-chatbot')}
														</span>
													</span>
												))}
											</div>
										</div>
									</div>
								</div>
							)}
							<SortableMemberList
								members={formData.members}
								orchestrationMode={formData.orchestration_mode}
								onMembersChange={(newMembers) =>
									setFormData((prev) => ({
										...prev,
										members: newMembers,
									}))
								}
								onUpdateMember={updateMember}
								onRemoveMember={removeMember}
							/>
						</div>
					</div>

					{/* Workflow Steps Card */}
					<div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
						{/* Card Header */}
						<div className="px-6 py-5 border-b border-gray-100 dark:border-slate-800 bg-gradient-to-r from-slate-50 to-white dark:from-slate-900/50 dark:to-slate-900/80">
							<div className="flex items-center justify-between">
								<div className="flex items-center gap-3">
									<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/50">
										<ClipboardList className="w-5 h-5 text-amber-600 dark:text-amber-400" />
									</div>
									<div>
										<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
											{formData.orchestration_mode === 'supervisor' 
												? __('Manager\'s Project Plan', 'smart-woo-chatbot')
												: __('Workflow Steps', 'smart-woo-chatbot')}
										</h3>
										<p className="text-sm text-gray-500 dark:text-slate-400">
											{formData.orchestration_mode === 'supervisor'
												? __('Explicitly define tasks the Manager must delegate and complete', 'smart-woo-chatbot')
												: __('Define the work process and instructions for this team', 'smart-woo-chatbot')}
										</p>
									</div>
								</div>
								<button
									type="button"
									onClick={addWorkflowStep}
									className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/50 border border-amber-200 dark:border-amber-800 transition-all"
								>
									<Plus className="w-4 h-4" />
									{__('Add Step', 'smart-woo-chatbot')}
								</button>
							</div>
						</div>

						{/* Card Body */}
						<div className="p-6">
							{formData.workflow_steps.length === 0 ? (
								<div className="text-center py-10 border-2 border-dashed border-gray-200 dark:border-slate-700 rounded-xl bg-gray-50/50 dark:bg-slate-800/30">
									<div className="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-amber-50 dark:bg-amber-900/30 mb-4">
										<ListOrdered className="w-7 h-7 text-amber-400 dark:text-amber-500" />
									</div>
									<h4 className="font-semibold text-gray-700 dark:text-slate-300 mb-1">
										{__('No workflow steps defined', 'smart-woo-chatbot')}
									</h4>
									<p className="text-sm text-gray-500 dark:text-slate-400 max-w-sm mx-auto mb-4">
										{__('Add steps to define the work process for this team. Steps can be instructions, agent tasks, approvals, or conditions.', 'smart-woo-chatbot')}
									</p>
									<button
										type="button"
										onClick={addWorkflowStep}
										className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors"
									>
										<Plus className="w-4 h-4" />
										{__('Add First Step', 'smart-woo-chatbot')}
									</button>
								</div>
							) : (
								<div className="space-y-4">
									{formData.workflow_steps.map((step, index) => (
										<div
											key={index}
											className="group p-5 bg-gray-50 dark:bg-slate-800/50 hover:bg-gray-100 dark:hover:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl transition-all duration-200"
										>
											{/* Step header */}
											<div className="flex items-center justify-between mb-4">
												<div className="flex items-center gap-3">
													<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 text-white text-sm font-bold shadow-sm">
														{index + 1}
													</div>
													<div>
														<p className="text-sm font-semibold text-gray-900 dark:text-white">
															{step.name || `${__('Step', 'smart-woo-chatbot')} ${index + 1}`}
														</p>
														<p className="text-xs text-gray-500 dark:text-slate-400 capitalize">
															{step.type || 'instruction'}
														</p>
													</div>
												</div>
												<div className="flex items-center gap-1">
													<button
														type="button"
														onClick={() => moveWorkflowStep(index, -1)}
														disabled={index === 0}
														className="p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-200 dark:hover:bg-slate-700 dark:hover:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
														title={__('Move up', 'smart-woo-chatbot')}
													>
														<ChevronUp className="w-4 h-4" />
													</button>
													<button
														type="button"
														onClick={() => moveWorkflowStep(index, 1)}
														disabled={index === formData.workflow_steps.length - 1}
														className="p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-200 dark:hover:bg-slate-700 dark:hover:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
														title={__('Move down', 'smart-woo-chatbot')}
													>
														<ChevronDown className="w-4 h-4" />
													</button>
													<button
														type="button"
														onClick={() => removeWorkflowStep(index)}
														className="p-1.5 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
														title={__('Remove step', 'smart-woo-chatbot')}
													>
														<Trash2 className="w-4 h-4" />
													</button>
												</div>
											</div>

											{/* Step fields */}
											<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
												<div>
													<label className="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">
														{formData.orchestration_mode === 'supervisor' 
															? __('Task Type', 'smart-woo-chatbot')
															: __('What should happen in this step?', 'smart-woo-chatbot')}
													</label>
													<select
														value={step.type || 'instruction'}
														onChange={(e) => updateWorkflowStep(index, 'type', e.target.value)}
														className="w-full h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
													>
														{formData.orchestration_mode === 'supervisor' ? (
															<>
																<option value="agent">{__('Delegate Task to Agent', 'smart-woo-chatbot')}</option>
																<option value="instruction">{__('Manager Self-Instruction', 'smart-woo-chatbot')}</option>
															</>
														) : (
															<>
																<option value="instruction">{__('Give an Instruction (System Prompt)', 'smart-woo-chatbot')}</option>
																<option value="agent">{__('Assign Task to a Specific Agent', 'smart-woo-chatbot')}</option>
																<option value="approval">{__('Wait for Human Approval', 'smart-woo-chatbot')}</option>
															</>
														)}
													</select>
												</div>
												<div>
													<label className="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">
														{formData.orchestration_mode === 'supervisor'
															? __('Task Name', 'smart-woo-chatbot')
															: (step.type === 'instruction' ? __('Name of this Instruction', 'smart-woo-chatbot') :
														 		step.type === 'agent' ? __('Task Title', 'smart-woo-chatbot') :
														 		step.type === 'approval' ? __('Approval Title', 'smart-woo-chatbot') :
														 		__('Step Name', 'smart-woo-chatbot'))}
													</label>
													<input
														type="text"
														value={step.name || ''}
														onChange={(e) => updateWorkflowStep(index, 'name', e.target.value)}
														className="w-full h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30"
														placeholder={
															step.type === 'instruction' ? __('e.g. Gather Context', 'smart-woo-chatbot') :
															step.type === 'agent' ? __('e.g. Write Email Draft', 'smart-woo-chatbot') :
															step.type === 'approval' ? __('e.g. Final Review', 'smart-woo-chatbot') :
															__('e.g. Review requirements', 'smart-woo-chatbot')
														}
													/>
												</div>
											</div>

											{/* Agent selector for agent-type steps */}
											{step.type === 'agent' && (
												<div className="mt-3">
													<label className="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">
														{__('Which agent should perform this task?', 'smart-woo-chatbot')}
													</label>
													<select
														value={step.agent_id || ''}
														onChange={(e) => updateWorkflowStep(index, 'agent_id', e.target.value)}
														className="w-full h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
													>
														<option value="">{__('Select an agent from the team...', 'smart-woo-chatbot')}</option>
														{allAgents.filter(a => formData.members.some(m => m.agent_db_id === a.id)).map((agent) => (
															<option key={agent.id} value={agent.agent_id || agent.id}>
																{agent.name}
															</option>
														))}
													</select>
													<p className="text-xs text-gray-500 mt-1">Make sure you have added the agent to the team members above.</p>
												</div>
											)}

											{/* Description / Input */}
											<div className="mt-3">
												<label className="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">
													{formData.orchestration_mode === 'supervisor' 
														? (step.type === 'agent' ? __('What exactly should the worker agent do?', 'smart-woo-chatbot') : __('Specific instruction for the manager', 'smart-woo-chatbot'))
														: (step.type === 'approval'
															? __('What exactly needs to be approved by the human?', 'smart-woo-chatbot')
															: step.type === 'instruction'
																? __('Provide detailed instructions for what needs to happen', 'smart-woo-chatbot')
																: __('Describe the exact task the agent needs to complete', 'smart-woo-chatbot'))
													}
												</label>
												<textarea
													value={step.input || step.description || ''}
													onChange={(e) => updateWorkflowStep(index, 'input', e.target.value)}
													rows={3}
													className="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 resize-none"
													placeholder={
														formData.orchestration_mode === 'supervisor'
															? __('E.g. Analyze the request, check the database, and compile a report.', 'smart-woo-chatbot')
															: (step.type === 'approval'
																? __('e.g. Please approve the email draft before continuing.', 'smart-woo-chatbot')
																: step.type === 'instruction'
																	? __('Describe the instruction in detail...', 'smart-woo-chatbot')
																	: __('Describe the task the agent needs to complete...', 'smart-woo-chatbot'))
													}
												/>
											</div>

											{/* Expected Output (Supervisor Mode Only) */}
											{formData.orchestration_mode === 'supervisor' && (
												<div className="mt-3">
													<label className="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">
														{__('Expected Output (Optional)', 'smart-woo-chatbot')}
													</label>
													<input
														type="text"
														value={step.expected_output || ''}
														onChange={(e) => updateWorkflowStep(index, 'expected_output', e.target.value)}
														className="w-full h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30"
														placeholder={__('E.g. A JSON object with the user details.', 'smart-woo-chatbot')}
													/>
												</div>
											)}
										</div>
									))}
								</div>
							)}
						</div>
					</div>
				</div>

				{/* Sidebar */}
				<div className="space-y-6">
					{/* Status Card */}
					<div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
						<div className="p-6">
							<h3 className="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-4">
								{__('Team Status', 'smart-woo-chatbot')}
							</h3>

							{/* Status Toggle */}
							<div className="p-4 rounded-xl bg-gradient-to-r from-gray-50 to-gray-100/50 dark:from-slate-800 dark:to-slate-800/50 border border-gray-200 dark:border-slate-700">
								<div className="flex items-center justify-between">
									<div className="flex items-center gap-3">
										<div className={`flex items-center justify-center w-10 h-10 rounded-xl transition-colors ${formData.is_active ? 'bg-green-100 dark:bg-green-900/50' : 'bg-gray-200 dark:bg-slate-700'}`}>
											{formData.is_active ? (
												<ToggleRight className="w-5 h-5 text-green-600 dark:text-green-400" />
											) : (
												<ToggleLeft className="w-5 h-5 text-gray-400 dark:text-slate-500" />
											)}
										</div>
										<div>
											<p className="font-medium text-gray-900 dark:text-white">
												{formData.is_active ? __('Active', 'smart-woo-chatbot') : __('Inactive', 'smart-woo-chatbot')}
											</p>
											<p className="text-xs text-gray-500 dark:text-slate-400">
												{formData.is_active
													? __('Team is available', 'smart-woo-chatbot')
													: __('Team is disabled', 'smart-woo-chatbot')
												}
											</p>
										</div>
									</div>

									{/* Toggle Switch */}
									<button
										type="button"
										role="switch"
										aria-checked={formData.is_active}
										onClick={() => updateField('is_active', !formData.is_active)}
										className={`relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${formData.is_active ? 'bg-primary' : 'bg-gray-300'}`}
									>
										<span
											className={`pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${formData.is_active ? 'translate-x-5' : 'translate-x-0'}`}
										/>
									</button>
								</div>
							</div>

							{/* Stats */}
							<div className="mt-4 grid grid-cols-3 gap-3">
								<div className="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-800">
									<p className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{formData.members.length}</p>
									<p className="text-xs text-indigo-600/70 dark:text-indigo-400/70">{__('Members', 'smart-woo-chatbot')}</p>
								</div>
								<div className="p-3 rounded-xl bg-purple-50 dark:bg-purple-900/30 border border-purple-100 dark:border-purple-800">
									<p className="text-2xl font-bold text-purple-600 dark:text-purple-400">{selectedMode.value.charAt(0).toUpperCase()}</p>
									<p className="text-xs text-purple-600/70 dark:text-purple-400/70">{__('Strategy', 'smart-woo-chatbot')}</p>
								</div>
								<div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/30 border border-amber-100 dark:border-amber-800">
									<p className="text-2xl font-bold text-amber-600 dark:text-amber-400">{formData.workflow_steps.length}</p>
									<p className="text-xs text-amber-600/70 dark:text-amber-400/70">{__('Steps', 'smart-woo-chatbot')}</p>
								</div>
							</div>
						</div>
					</div>

					{/* Quick Tips Card */}
					<div className="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/30 dark:to-purple-900/30 rounded-2xl border border-indigo-100 dark:border-indigo-800 p-6">
						<div className="flex items-center gap-2 mb-3">
							<Info className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
							<h4 className="font-semibold text-indigo-900 dark:text-indigo-300">{__('Quick Tips', 'smart-woo-chatbot')}</h4>
						</div>
						<ul className="space-y-2 text-sm text-indigo-800/80 dark:text-indigo-300/80">
							<li className="flex items-start gap-2">
								<span className="text-indigo-500 dark:text-indigo-400 mt-1">•</span>
								{formData.orchestration_mode === 'supervisor'
									? __('Set one agent as "Primary" \u2014 it becomes the Manager', 'smart-woo-chatbot')
									: __('Use Auto-Router for automatic agent selection', 'smart-woo-chatbot')}
							</li>
							<li className="flex items-start gap-2">
								<span className="text-indigo-500 dark:text-indigo-400 mt-1">•</span>
								{formData.orchestration_mode === 'supervisor'
									? __('All other agents become Workers the Manager delegates to', 'smart-woo-chatbot')
									: __('Add routing keywords to help with matching', 'smart-woo-chatbot')}
							</li>
							<li className="flex items-start gap-2">
								<span className="text-indigo-500 dark:text-indigo-400 mt-1">•</span>
								{formData.orchestration_mode === 'supervisor'
									? __('Give each Worker a clear description so the Manager knows their specialty', 'smart-woo-chatbot')
									: __('Assign a Primary agent as default', 'smart-woo-chatbot')}
							</li>
						</ul>
					</div>
				</div>
			</div>
		</form>
	);
}

GroupEditor.propTypes = {
	group: PropTypes.shape({
		id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
		group_id: PropTypes.string,
		name: PropTypes.string,
		description: PropTypes.string,
		avatar: PropTypes.string,
		orchestration_mode: PropTypes.string,
		is_active: PropTypes.oneOfType([PropTypes.bool, PropTypes.number]),
		welcome_message: PropTypes.string,
		members: PropTypes.array,
		routing_config: PropTypes.object,
	}),
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
	isNew: PropTypes.bool,
};
