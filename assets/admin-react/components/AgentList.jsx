/**
 * AgentList Component
 *
 * Displays grid of agent cards with professional styling.
 * Click any card to edit the agent.
 */
import { __ } from '@wordpress/i18n';
import { settings, search as searchIcon } from '@wordpress/icons';
import PropTypes from 'prop-types';
import { useState, useMemo } from '@wordpress/element';
import AgentCard from './AgentCard';
import { Icon } from './ui';

export default function AgentList({
	agents,
	onEdit,
	onDelete,
	onDuplicate,
	onTest,
	searchQuery: externalSearchQuery,
}) {
	// Use external search query if provided, otherwise fall back to internal state
	const [internalSearchQuery, setInternalSearchQuery] = useState('');
	const searchQuery = externalSearchQuery !== undefined ? externalSearchQuery : internalSearchQuery;

	// Filter agents based on search
	const filteredAgents = useMemo(() => {
		if (!searchQuery.trim()) return agents;
		const query = searchQuery.toLowerCase();
		return agents.filter(
			(agent) =>
				agent.name?.toLowerCase().includes(query) ||
				agent.agent_id?.toLowerCase().includes(query) ||
				agent.description?.toLowerCase().includes(query)
		);
	}, [agents, searchQuery]);

	// Empty state
	if (!agents || agents.length === 0) {
		return (
			<div className="flex flex-col items-center justify-center py-20 px-6">
				<div className="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-6">
					<Icon icon={settings} size={32} className="text-gray-400 dark:text-slate-500" />
				</div>
				<h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
					{__('No Agents Yet', 'agentflow-ai')}
				</h3>
				<p className="text-gray-500 dark:text-slate-400 text-center max-w-md">
					{__(
						'Create your first AI agent to get started. Choose from templates or build a custom agent.',
						'agentflow-ai'
					)}
				</p>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			{/* Agent Cards Grid */}
			<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
				{filteredAgents.map((agent) => (
					<AgentCard
						key={agent.id}
						agent={agent}
						onEdit={() => onEdit(agent)}
						onDelete={() => onDelete(agent.id)}
						onDuplicate={() => onDuplicate(agent)}
						onTest={onTest}
					/>
				))}
			</div>

			{/* No Results */}
			{filteredAgents.length === 0 && searchQuery && (
				<div className="text-center py-12">
					<div className="w-12 h-12 mx-auto rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-4">
						<Icon icon={searchIcon} size={24} className="text-gray-400 dark:text-slate-500" />
					</div>
					<p className="text-gray-500 dark:text-slate-400">
						{__('No agents found matching', 'agentflow-ai')}{' '}
						<span className="font-medium text-gray-700 dark:text-slate-300">"{searchQuery}"</span>
					</p>
				</div>
			)}
		</div>
	);
}

AgentList.propTypes = {
	agents: PropTypes.array.isRequired,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
	onDuplicate: PropTypes.func,
	onTest: PropTypes.func,
	searchQuery: PropTypes.string,
};
