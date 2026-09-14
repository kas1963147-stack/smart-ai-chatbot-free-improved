/**
 * AgentGroupEditor Component
 *
 * Premium Metronic v9 styled editor for creating/editing Agent Groups (Teams).
 * Features gradient headers, card-based layouts, and modern form controls.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import {
    Users,
    Settings,
    MessageSquare,
    ChevronLeft,
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
} from 'lucide-react';

// Orchestration mode configurations
const ORCHESTRATION_MODES = [
    {
        value: 'router',
        label: __('Auto-Router (Best Agent)', 'agentflow-ai'),
        description: __('Automatically routes user input to the most relevant agent.', 'agentflow-ai'),
        icon: Zap,
        color: 'from-blue-500 to-indigo-600',
        bgColor: 'bg-blue-50',
        textColor: 'text-blue-600',
    },
    {
        value: 'sequential',
        label: __('Sequential (In Order)', 'agentflow-ai'),
        description: __('Agents execute one by one in the order defined below.', 'agentflow-ai'),
        icon: ArrowRight,
        color: 'from-green-500 to-emerald-600',
        bgColor: 'bg-green-50',
        textColor: 'text-green-600',
    },
    {
        value: 'parallel',
        label: __('Parallel (All At Once)', 'agentflow-ai'),
        description: __('All agents execute simultaneously and results are combined.', 'agentflow-ai'),
        icon: Layers,
        color: 'from-purple-500 to-pink-600',
        bgColor: 'bg-purple-50',
        textColor: 'text-purple-600',
    },
    {
        value: 'handoff',
        label: __('Manual Handoff', 'agentflow-ai'),
        description: __('Manual handoff between agents with explicit triggers.', 'agentflow-ai'),
        icon: RefreshCw,
        color: 'from-orange-500 to-amber-600',
        bgColor: 'bg-orange-50',
        textColor: 'text-orange-600',
    },
];

const MEMBER_ROLES = [
    { value: 'primary', label: __('Primary', 'agentflow-ai'), color: 'bg-blue-100 text-blue-700' },
    { value: 'specialist', label: __('Specialist', 'agentflow-ai'), color: 'bg-purple-100 text-purple-700' },
    { value: 'fallback', label: __('Fallback', 'agentflow-ai'), color: 'bg-amber-100 text-amber-700' },
];

export default function AgentGroupEditor({ group, onSave, onCancel, isNew = true }) {
    // Form state
    const [formData, setFormData] = useState({
        group_id: '',
        name: '',
        description: '',
        avatar: '',
        orchestration_mode: 'router',
        welcome_message: "Hello! We're a team of assistants here to help. How can we assist you today?",
        is_active: true,
        members: [],
        routing_config: {},
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
                    path: '/quark-agentflow-ai/v1/agents',
                });
                setAllAgents(agentsResp.agents || []);

                if (group && !isNew) {
                    // Load group members
                    const membersResp = await apiFetch({
                        path: `/quark-agentflow-ai/v1/agent-groups/${group.id}/members`,
                    });

                    setFormData({
                        id: group.id,
                        group_id: group.group_id,
                        name: group.name,
                        description: group.description || '',
                        avatar: group.avatar || '',
                        orchestration_mode: group.orchestration_mode || 'router',
                        welcome_message: group.welcome_message || "Hello! We're a team of assistants here to help. How can we assist you today?",
                        is_active: group.is_active !== false,
                        members: membersResp || [],
                        routing_config: group.routing_config || {},
                    });
                }
            } catch (err) {
                console.error('Failed to load data:', err);
                setErrors({ general: __('Failed to load data.', 'agentflow-ai') });
            } finally {
                setLoading(false);
            }
        };

        loadData();
    }, [group, isNew]);

    // Update available agents (exclude already added)
    useEffect(() => {
        if (!loading) {
            const memberIds = formData.members.map((m) => parseInt(m.agent_db_id));
            setAvailableAgents(allAgents.filter((a) => !memberIds.includes(parseInt(a.id))));
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

        const agent = allAgents.find((a) => parseInt(a.id) === parseInt(agentId));
        if (!agent) return;

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
        // Recalculate execution order
        newMembers.forEach((m, idx) => {
            m.execution_order = idx;
        });
        setFormData((prev) => ({ ...prev, members: newMembers }));
    }, [formData.members]);

    const updateMember = useCallback((index, field, value) => {
        const newMembers = [...formData.members];
        newMembers[index] = { ...newMembers[index], [field]: value };
        setFormData((prev) => ({ ...prev, members: newMembers }));
    }, [formData.members]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            // Validation
            if (!formData.name.trim()) {
                throw new Error(__('Team name is required', 'agentflow-ai'));
            }
            if (isNew && !formData.group_id.trim()) {
                throw new Error(__('Team ID is required', 'agentflow-ai'));
            }

            await onSave(formData);
        } catch (err) {
            console.error('Failed to save group:', err);
            setErrors({ general: err.message || __('Failed to save team', 'agentflow-ai') });
            setSaving(false);
        }
    };

    const selectedMode = ORCHESTRATION_MODES.find((m) => m.value === formData.orchestration_mode) || ORCHESTRATION_MODES[0];

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center py-20">
                <div className="relative">
                    <div className="w-16 h-16 border-4 border-primary/20 rounded-full" />
                    <div className="absolute inset-0 w-16 h-16 border-4 border-primary border-t-transparent rounded-full animate-spin" />
                </div>
                <p className="mt-4 text-gray-500 font-medium">{__('Loading...', 'agentflow-ai')}</p>
            </div>
        );
    }

    return (
        <div>
            {/* Action Bar */}
            <form id="agent-group-form" onSubmit={handleSubmit} className="max-w-6xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="flex items-center gap-2 px-3 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all duration-200"
                    >
                        <ChevronLeft className="w-5 h-5" />
                        <span className="font-medium">{__('Back', 'agentflow-ai')}</span>
                    </button>
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={onCancel}
                            disabled={saving}
                            className="px-4 py-2.5 text-gray-700 bg-white border border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition-all duration-200 disabled:opacity-50"
                        >
                            {__('Cancel', 'agentflow-ai')}
                        </button>
                        <button
                            type="submit"
                            form="agent-group-form"
                            disabled={saving}
                            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-medium shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30 hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:hover:translate-y-0"
                        >
                            {saving ? (
                                <>
                                    <Loader2 className="w-4 h-4 animate-spin" />
                                    {__('Saving...', 'agentflow-ai')}
                                </>
                            ) : (
                                <>
                                    <Save className="w-4 h-4" />
                                    {isNew ? __('Create Team', 'agentflow-ai') : __('Save Changes', 'agentflow-ai')}
                                </>
                            )}
                        </button>
                    </div>
                </div>

            {/* Main Content */}
                {/* Error Alert */}
                {errors.general && (
                    <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3">
                        <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                        <div>
                            <p className="font-medium text-red-800">{__('Error', 'agentflow-ai')}</p>
                            <p className="text-sm text-red-600">{errors.general}</p>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Column */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Team Settings Card */}
                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                            {/* Card Header */}
                            <div className="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100">
                                        <Settings className="w-5 h-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900">
                                            {__('Team Settings', 'agentflow-ai')}
                                        </h3>
                                        <p className="text-sm text-gray-500">
                                            {__('Basic information for your agent team', 'agentflow-ai')}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Card Body */}
                            <div className="p-6 space-y-5">
                                {/* Team ID (only for new) */}
                                {isNew && (
                                    <div className="space-y-2">
                                        <label className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <span className="flex items-center justify-center w-5 h-5 rounded bg-gray-100 text-xs text-gray-500 font-bold">ID</span>
                                            {__('Team ID', 'agentflow-ai')}
                                            <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={formData.group_id}
                                            onChange={(e) => updateField('group_id', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                                            placeholder="my_support_team"
                                            className={`
												w-full h-12 px-4 text-sm rounded-xl border bg-gray-50
												placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
												transition-all duration-200
												${errors.group_id ? 'border-red-300 focus:border-red-500 focus:ring-red-500/30' : 'border-gray-200'}
											`}
                                        />
                                        <p className="text-xs text-gray-500 flex items-center gap-1">
                                            <Info className="w-3 h-3" />
                                            {__('Unique identifier (lowercase)', 'agentflow-ai')}
                                        </p>
                                    </div>
                                )}

                                {/* Team Name */}
                                <div className="space-y-2">
                                    <label className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <Users className="w-4 h-4 text-gray-400" />
                                        {__('Team Name', 'agentflow-ai')}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.name}
                                        onChange={(e) => updateField('name', e.target.value)}
                                        placeholder="Support Team"
                                        className={`
											w-full h-12 px-4 text-sm rounded-xl border bg-gray-50
											placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
											transition-all duration-200
											${errors.name ? 'border-red-300 focus:border-red-500 focus:ring-red-500/30' : 'border-gray-200'}
										`}
                                    />
                                </div>

                                {/* Description */}
                                <div className="space-y-2">
                                    <label className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <MessageSquare className="w-4 h-4 text-gray-400" />
                                        {__('Description', 'agentflow-ai')}
                                    </label>
                                    <textarea
                                        value={formData.description}
                                        onChange={(e) => updateField('description', e.target.value)}
                                        placeholder="Describe what this team does..."
                                        rows={3}
                                        className="
											w-full px-4 py-3 text-sm rounded-xl border border-gray-200 bg-gray-50
											placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
											transition-all duration-200 resize-none
										"
                                    />
                                </div>

                                {/* Orchestration Strategy */}
                                <div className="space-y-3">
                                    <label className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <Zap className="w-4 h-4 text-gray-400" />
                                        {__('Orchestration Strategy', 'agentflow-ai')}
                                    </label>
                                    <select
                                        value={formData.orchestration_mode}
                                        onChange={(e) => updateField('orchestration_mode', e.target.value)}
                                        className="
											w-full h-12 px-4 text-sm rounded-xl border border-gray-200 bg-gray-50
											focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
											transition-all duration-200 cursor-pointer
										"
                                    >
                                        {ORCHESTRATION_MODES.map((mode) => (
                                            <option key={mode.value} value={mode.value}>
                                                {mode.label}
                                            </option>
                                        ))}
                                    </select>
                                    {/* Strategy Info Box */}
                                    <div className={`p-4 rounded-xl ${selectedMode.bgColor} border border-${selectedMode.textColor.replace('text-', '')}/20`}>
                                        <div className="flex items-start gap-3">
                                            <div className={`p-2 rounded-lg bg-gradient-to-br ${selectedMode.color}`}>
                                                <selectedMode.icon className="w-4 h-4 text-white" />
                                            </div>
                                            <div>
                                                <p className={`font-medium ${selectedMode.textColor}`}>{selectedMode.label}</p>
                                                <p className="text-sm text-gray-600 mt-0.5">{selectedMode.description}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Welcome Message */}
                                <div className="space-y-2">
                                    <label className="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <MessageSquare className="w-4 h-4 text-gray-400" />
                                        {__('Welcome Message', 'agentflow-ai')}
                                    </label>
                                    <textarea
                                        value={formData.welcome_message}
                                        onChange={(e) => updateField('welcome_message', e.target.value)}
                                        placeholder="Hello! We're a team of assistants here to help. How can we assist you today?"
                                        rows={3}
                                        className="
											w-full px-4 py-3 text-sm rounded-xl border border-gray-200 bg-gray-50
											placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
											transition-all duration-200 resize-none
										"
                                    />
                                    <p className="text-xs text-gray-500">
                                        {__('Message displayed when the team chat starts', 'agentflow-ai')}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Team Members Card */}
                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                            {/* Card Header */}
                            <div className="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-100">
                                            <UserPlus className="w-5 h-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900">
                                                {__('Team Members', 'agentflow-ai')}
                                            </h3>
                                            <p className="text-sm text-gray-500">
                                                {formData.members.length} {formData.members.length === 1 ? __('agent', 'agentflow-ai') : __('agents', 'agentflow-ai')}
                                            </p>
                                        </div>
                                    </div>
                                    {/* Add Agent Dropdown */}
                                    <div className="relative">
                                        <select
                                            value={memberToAdd}
                                            onChange={(e) => addMember(e.target.value)}
                                            disabled={availableAgents.length === 0}
                                            className="
												h-10 pl-4 pr-10 text-sm rounded-xl border border-gray-200 bg-white
												focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
												transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed
												appearance-none
											"
                                        >
                                            <option value="">{__('+ Add Agent', 'agentflow-ai')}</option>
                                            {availableAgents.map((agent) => (
                                                <option key={agent.id} value={agent.id}>
                                                    {agent.avatar || ''} {agent.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {/* Card Body - Member List */}
                            <div className="p-6">
                                {formData.members.length === 0 ? (
                                    <div className="text-center py-12 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                                        <div className="flex items-center justify-center w-16 h-16 mx-auto rounded-2xl bg-gray-100 mb-4">
                                            <Users className="w-8 h-8 text-gray-400" />
                                        </div>
                                        <h4 className="font-semibold text-gray-700 mb-1">
                                            {__('No agents added yet', 'agentflow-ai')}
                                        </h4>
                                        <p className="text-sm text-gray-500 max-w-xs mx-auto">
                                            {__('Add agents to create a team. They will work together based on your orchestration strategy.', 'agentflow-ai')}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {formData.members.map((member, index) => (
                                            <div
                                                key={member.agent_db_id}
                                                className="group p-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-xl transition-all duration-200"
                                            >
                                                <div className="flex items-start gap-4">
                                                    {/* Drag Handle */}
                                                    <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-200/50 text-gray-400 cursor-grab hover:bg-gray-200 hover:text-gray-600 transition-colors">
                                                        <GripVertical className="w-4 h-4" />
                                                    </div>

                                                    {/* Order Badge */}
                                                    <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-sm font-bold shadow-sm">
                                                        {index + 1}
                                                    </div>

                                                    {/* Avatar */}
                                                    <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-gray-200 text-xl">
                                                        {member.agent_avatar || ''}
                                                    </div>

                                                    {/* Agent Info */}
                                                    <div className="flex-1 min-w-0">
                                                        <h4 className="font-semibold text-gray-900 truncate">
                                                            {member.agent_name}
                                                        </h4>
                                                        <div className="flex items-center gap-2 mt-1">
                                                            <select
                                                                value={member.role}
                                                                onChange={(e) => updateMember(index, 'role', e.target.value)}
                                                                className="h-7 px-2 text-xs rounded-md border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                                            >
                                                                {MEMBER_ROLES.map((role) => (
                                                                    <option key={role.value} value={role.value}>
                                                                        {role.label}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </div>

                                                        {/* Routing Keywords (for router mode) */}
                                                        {formData.orchestration_mode === 'router' && (
                                                            <div className="mt-2">
                                                                <input
                                                                    type="text"
                                                                    value={member.routing_keywords || ''}
                                                                    onChange={(e) => updateMember(index, 'routing_keywords', e.target.value)}
                                                                    placeholder={__('Routing keywords (comma-separated)', 'agentflow-ai')}
                                                                    className="w-full h-8 px-3 text-xs rounded-lg border border-gray-200 bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                                                />
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Remove Button */}
                                                    <button
                                                        type="button"
                                                        onClick={() => removeMember(index)}
                                                        className="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors opacity-0 group-hover:opacity-100"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        ))}

                                        {formData.orchestration_mode === 'sequential' && (
                                            <p className="text-sm text-gray-500 italic mt-4 flex items-center gap-2">
                                                <Info className="w-4 h-4" />
                                                {__('Drag to reorder. Agents will execute in the order shown above.', 'agentflow-ai')}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Status Card */}
                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                            <div className="p-6">
                                <h3 className="text-sm font-semibold text-gray-700 mb-4">
                                    {__('Team Status', 'agentflow-ai')}
                                </h3>

                                {/* Status Toggle */}
                                <div className="p-4 rounded-xl bg-gradient-to-r from-gray-50 to-gray-100/50 border border-gray-200">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className={`
												flex items-center justify-center w-10 h-10 rounded-xl transition-colors
												${formData.is_active ? 'bg-green-100' : 'bg-gray-200'}
											`}>
                                                {formData.is_active ? (
                                                    <ToggleRight className="w-5 h-5 text-green-600" />
                                                ) : (
                                                    <ToggleLeft className="w-5 h-5 text-gray-400" />
                                                )}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900">
                                                    {formData.is_active ? __('Active', 'agentflow-ai') : __('Inactive', 'agentflow-ai')}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {formData.is_active
                                                        ? __('Team is available for use', 'agentflow-ai')
                                                        : __('Team is disabled', 'agentflow-ai')
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
                                            className={`
												relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent
												transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
												${formData.is_active ? 'bg-indigo-600' : 'bg-gray-300'}
											`}
                                        >
                                            <span
                                                className={`
													pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0
													transition duration-200 ease-in-out
													${formData.is_active ? 'translate-x-5' : 'translate-x-0'}
												`}
                                            />
                                        </button>
                                    </div>
                                </div>

                                {/* Stats */}
                                <div className="mt-4 grid grid-cols-2 gap-3">
                                    <div className="p-3 rounded-xl bg-indigo-50 border border-indigo-100">
                                        <p className="text-2xl font-bold text-indigo-600">{formData.members.length}</p>
                                        <p className="text-xs text-indigo-600/70">{__('Team Members', 'agentflow-ai')}</p>
                                    </div>
                                    <div className="p-3 rounded-xl bg-purple-50 border border-purple-100">
                                        <p className="text-2xl font-bold text-purple-600">{selectedMode.value.charAt(0).toUpperCase()}</p>
                                        <p className="text-xs text-purple-600/70">{__('Strategy', 'agentflow-ai')}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Quick Tips Card */}
                        <div className="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6">
                            <div className="flex items-center gap-2 mb-3">
                                <Info className="w-4 h-4 text-indigo-600" />
                                <h4 className="font-semibold text-indigo-900">{__('Quick Tips', 'agentflow-ai')}</h4>
                            </div>
                            <ul className="space-y-2 text-sm text-indigo-800/80">
                                <li className="flex items-start gap-2">
                                    <span className="text-indigo-500 mt-1">â€¢</span>
                                    {__('Use Auto-Router for automatic agent selection', 'agentflow-ai')}
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-indigo-500 mt-1">â€¢</span>
                                    {__('Add routing keywords to help with agent matching', 'agentflow-ai')}
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-indigo-500 mt-1">â€¢</span>
                                    {__('Assign a Primary agent as the default handler', 'agentflow-ai')}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    );
}

AgentGroupEditor.propTypes = {
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
