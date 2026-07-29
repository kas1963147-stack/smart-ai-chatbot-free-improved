/**
 * AgentGroupsPage Component
 *
 * Premium Metronic v9 styled page for managing Agent Groups (Teams).
 * Features a card-based grid view of teams with create/edit/delete functionality.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
    Users,
    Plus,
    Search,
    Edit3,
    Trash2,
    MoreVertical,
    Zap,
    ArrowRight,
    Layers,
    RefreshCw,
    Filter,
    LayoutGrid,
    List,
    AlertTriangle,
    CheckCircle2,
    XCircle,
} from 'lucide-react';
import Loading from '../common/Loading';
import AgentGroupEditor from './AgentGroupEditor';

// Orchestration mode configurations
const ORCHESTRATION_MODES = {
    router: { label: __('Auto-Router', 'smart-woo-chatbot'), icon: Zap, color: 'bg-blue-100 text-blue-600' },
    sequential: { label: __('Sequential', 'smart-woo-chatbot'), icon: ArrowRight, color: 'bg-green-100 text-green-600' },
    parallel: { label: __('Parallel', 'smart-woo-chatbot'), icon: Layers, color: 'bg-purple-100 text-purple-600' },
    handoff: { label: __('Handoff', 'smart-woo-chatbot'), icon: RefreshCw, color: 'bg-orange-100 text-orange-600' },
};

export default function AgentGroupsPage() {
    const [groups, setGroups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [viewMode, setViewMode] = useState('grid'); // 'grid' or 'list'
    const [isEditing, setIsEditing] = useState(false);
    const [selectedGroup, setSelectedGroup] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    // Fetch groups
    const fetchGroups = useCallback(async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: '/smart-ai-chatbot/v1/agent-groups',
            });
            setGroups(response.groups || response.data || []);
            setError(null);
        } catch (err) {
            console.error('Failed to fetch groups:', err);
            setError(__('Failed to load agent groups', 'smart-woo-chatbot'));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchGroups();
    }, [fetchGroups]);

    // Filter groups by search query
    const filteredGroups = groups.filter((group) => {
        if (!searchQuery.trim()) return true;
        const query = searchQuery.toLowerCase();
        return (
            group.name?.toLowerCase().includes(query) ||
            group.group_id?.toLowerCase().includes(query) ||
            group.description?.toLowerCase().includes(query)
        );
    });

    // Handlers
    const handleCreate = () => {
        setSelectedGroup(null);
        setIsEditing(true);
    };

    const handleEdit = (group) => {
        setSelectedGroup(group);
        setIsEditing(true);
    };

    const handleDelete = async (groupId) => {
        try {
            await apiFetch({
                path: `/smart-ai-chatbot/v1/agent-groups/${groupId}`,
                method: 'DELETE',
            });
            setGroups((prev) => prev.filter((g) => g.id !== groupId));
            setDeleteConfirm(null);
        } catch (err) {
            console.error('Failed to delete group:', err);
        }
    };

    const handleSave = async (data) => {
        try {
            if (selectedGroup) {
                // Update existing
                const response = await apiFetch({
                    path: `/smart-ai-chatbot/v1/agent-groups/${selectedGroup.id}`,
                    method: 'PUT',
                    data,
                });
                setGroups((prev) =>
                    prev.map((g) => (g.id === selectedGroup.id ? response.group || response : g))
                );
            } else {
                // Create new
                const response = await apiFetch({
                    path: '/smart-ai-chatbot/v1/agent-groups',
                    method: 'POST',
                    data,
                });
                setGroups((prev) => [...prev, response.group || response]);
            }
            setIsEditing(false);
            setSelectedGroup(null);
        } catch (err) {
            console.error('Failed to save group:', err);
            throw err;
        }
    };

    const handleCancel = () => {
        setIsEditing(false);
        setSelectedGroup(null);
    };

    // Loading state
    if (loading && groups.length === 0) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-50 flex items-center justify-center">
                <Loading message={__('Loading agent groups...', 'smart-woo-chatbot')} fullPage />
            </div>
        );
    }

    // Show editor if editing
    if (isEditing) {
        return (
            <AgentGroupEditor
                group={selectedGroup}
                isNew={!selectedGroup}
                onSave={handleSave}
                onCancel={handleCancel}
            />
        );
    }

    return (
        <div>
            {/* Toolbar */}
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-6">
                    {/* Search */}
                    <div className="relative w-80">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder={__('Search teams...', 'smart-woo-chatbot')}
                            className="w-full h-10 pl-10 pr-4 text-sm rounded-xl border border-gray-200 bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all"
                        />
                    </div>

                    {/* Stats */}
                    <div className="flex items-center gap-4 text-sm">
                        <span className="flex items-center gap-1.5 text-gray-500">
                            <CheckCircle2 className="w-4 h-4 text-green-500" />
                            <span className="font-semibold text-gray-900">{groups.filter((g) => g.is_active !== false).length}</span> {__('Active', 'smart-woo-chatbot')}
                        </span>
                        <span className="text-gray-300">|</span>
                        <span className="flex items-center gap-1.5 text-gray-500">
                            <Users className="w-4 h-4 text-indigo-500" />
                            <span className="font-semibold text-gray-900">{groups.length}</span> {__('Total', 'smart-woo-chatbot')}
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    {/* View Toggle */}
                    <div className="flex items-center gap-2 p-1 bg-gray-100 rounded-lg">
                        <button
                            onClick={() => setViewMode('grid')}
                            className={`flex items-center justify-center w-9 h-9 rounded-md transition-all ${viewMode === 'grid'
                                ? 'bg-white text-indigo-600 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                                }`}
                        >
                            <LayoutGrid className="w-4 h-4" />
                        </button>
                        <button
                            onClick={() => setViewMode('list')}
                            className={`flex items-center justify-center w-9 h-9 rounded-md transition-all ${viewMode === 'list'
                                ? 'bg-white text-indigo-600 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                                }`}
                        >
                            <List className="w-4 h-4" />
                        </button>
                    </div>

                    <button
                        onClick={handleCreate}
                        className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-medium shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30 hover:-translate-y-0.5 transition-all duration-200"
                    >
                        <Plus className="w-5 h-5" />
                        {__('Create Team', 'smart-woo-chatbot')}
                    </button>
                </div>
            </div>

            {/* Content */}
            <div>
                {error && (
                    <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3">
                        <AlertTriangle className="w-5 h-5 text-red-500" />
                        <p className="text-red-700">{error}</p>
                    </div>
                )}

                {filteredGroups.length === 0 ? (
                    <div className="text-center py-20">
                        <div className="flex items-center justify-center w-20 h-20 mx-auto rounded-2xl bg-gray-100 mb-6">
                            <Users className="w-10 h-10 text-gray-400" />
                        </div>
                        <h3 className="text-xl font-semibold text-gray-700 mb-2">
                            {searchQuery
                                ? __('No teams found', 'smart-woo-chatbot')
                                : __('No agent teams yet', 'smart-woo-chatbot')
                            }
                        </h3>
                        <p className="text-gray-500 max-w-md mx-auto mb-6">
                            {searchQuery
                                ? __('Try adjusting your search query', 'smart-woo-chatbot')
                                : __('Create your first team to enable multi-agent orchestration', 'smart-woo-chatbot')
                            }
                        </p>
                        {!searchQuery && (
                            <button
                                onClick={handleCreate}
                                className="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-medium hover:bg-indigo-700 transition-colors"
                            >
                                <Plus className="w-5 h-5" />
                                {__('Create Your First Team', 'smart-woo-chatbot')}
                            </button>
                        )}
                    </div>
                ) : viewMode === 'grid' ? (
                    /* Grid View */
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        {filteredGroups.map((group) => {
                            const mode = ORCHESTRATION_MODES[group.orchestration_mode] || ORCHESTRATION_MODES.router;
                            const ModeIcon = mode.icon;
                            const memberCount = group.members?.length || group.member_count || 0;

                            return (
                                <div
                                    key={group.id}
                                    className="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-lg hover:border-indigo-200 transition-all duration-300 overflow-hidden"
                                >
                                    {/* Card Header */}
                                    <div className="p-5 border-b border-gray-100">
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 text-2xl">
                                                    {group.avatar || ''}
                                                </div>
                                                <div className="min-w-0">
                                                    <h3 className="font-semibold text-gray-900 truncate">
                                                        {group.name}
                                                    </h3>
                                                    <p className="text-xs text-gray-400 font-mono">
                                                        {group.group_id}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Status Badge */}
                                            <span
                                                className={`inline-flex items-center px-2 py-1 text-xs font-medium rounded-full ${group.is_active !== false
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-gray-100 text-gray-500'
                                                    }`}
                                            >
                                                {group.is_active !== false ? __('Active', 'smart-woo-chatbot') : __('Inactive', 'smart-woo-chatbot')}
                                            </span>
                                        </div>

                                        {group.description && (
                                            <p className="mt-3 text-sm text-gray-500 line-clamp-2">
                                                {group.description}
                                            </p>
                                        )}
                                    </div>

                                    {/* Card Body */}
                                    <div className="p-5 space-y-3">
                                        {/* Orchestration Mode */}
                                        <div className="flex items-center gap-2">
                                            <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg ${mode.color}`}>
                                                <ModeIcon className="w-3 h-3" />
                                                {mode.label}
                                            </span>
                                        </div>

                                        {/* Stats */}
                                        <div className="flex items-center gap-4 text-sm">
                                            <div className="flex items-center gap-1.5 text-gray-500">
                                                <Users className="w-4 h-4" />
                                                <span>
                                                    {memberCount} {memberCount === 1 ? __('agent', 'smart-woo-chatbot') : __('agents', 'smart-woo-chatbot')}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Card Footer */}
                                    <div className="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                                        <button
                                            onClick={() => handleEdit(group)}
                                            className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors"
                                        >
                                            <Edit3 className="w-3.5 h-3.5" />
                                            {__('Edit', 'smart-woo-chatbot')}
                                        </button>
                                        <button
                                            onClick={() => setDeleteConfirm(group.id)}
                                            className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
                                            {__('Delete', 'smart-woo-chatbot')}
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    /* List View */
                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <table className="w-full">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        {__('Team', 'smart-woo-chatbot')}
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        {__('Strategy', 'smart-woo-chatbot')}
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        {__('Members', 'smart-woo-chatbot')}
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        {__('Status', 'smart-woo-chatbot')}
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        {__('Actions', 'smart-woo-chatbot')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {filteredGroups.map((group) => {
                                    const mode = ORCHESTRATION_MODES[group.orchestration_mode] || ORCHESTRATION_MODES.router;
                                    const ModeIcon = mode.icon;
                                    const memberCount = group.members?.length || group.member_count || 0;

                                    return (
                                        <tr key={group.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 text-xl">
                                                        {group.avatar || ''}
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-gray-900">{group.name}</p>
                                                        <p className="text-xs text-gray-400 font-mono">{group.group_id}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg ${mode.color}`}>
                                                    <ModeIcon className="w-3 h-3" />
                                                    {mode.label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="text-sm text-gray-600">
                                                    {memberCount} {memberCount === 1 ? __('agent', 'smart-woo-chatbot') : __('agents', 'smart-woo-chatbot')}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex items-center px-2 py-1 text-xs font-medium rounded-full ${group.is_active !== false
                                                        ? 'bg-green-100 text-green-700'
                                                        : 'bg-gray-100 text-gray-500'
                                                        }`}
                                                >
                                                    {group.is_active !== false ? __('Active', 'smart-woo-chatbot') : __('Inactive', 'smart-woo-chatbot')}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        onClick={() => handleEdit(group)}
                                                        className="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                                    >
                                                        <Edit3 className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => setDeleteConfirm(group.id)}
                                                        className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Delete Confirmation Modal */}
            {deleteConfirm && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
                        <div className="p-6">
                            <div className="flex items-center justify-center w-12 h-12 mx-auto rounded-full bg-red-100 mb-4">
                                <AlertTriangle className="w-6 h-6 text-red-600" />
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900 text-center mb-2">
                                {__('Delete Team?', 'smart-woo-chatbot')}
                            </h3>
                            <p className="text-sm text-gray-500 text-center">
                                {__('Are you sure you want to delete this team? All member agents will be unassigned. This action cannot be undone.', 'smart-woo-chatbot')}
                            </p>
                        </div>
                        <div className="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
                            <button
                                onClick={() => setDeleteConfirm(null)}
                                className="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors"
                            >
                                {__('Cancel', 'smart-woo-chatbot')}
                            </button>
                            <button
                                onClick={() => handleDelete(deleteConfirm)}
                                className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                            >
                                {__('Delete Team', 'smart-woo-chatbot')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
