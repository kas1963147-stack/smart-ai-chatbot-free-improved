/**
 * GroupList Component
 *
 * Premium Metronic v9 styled grid display of group cards.
 * Using Tailwind CSS design patterns with dark mode support.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Users } from 'lucide-react';
import GroupCard from './GroupCard';

export default function GroupList({ groups, onEdit, onDelete }) {
	// Empty state
	if (!groups || groups.length === 0) {
		return (
			<div className="text-center py-20 bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-700">
				<div className="flex items-center justify-center w-20 h-20 mx-auto rounded-2xl bg-gray-100 dark:bg-slate-800 mb-6">
					<Users className="w-10 h-10 text-gray-400 dark:text-slate-500" />
				</div>
				<h3 className="text-xl font-semibold text-gray-700 dark:text-slate-200 mb-2">
					{__('No Agent Teams', 'agentflow-ai')}
				</h3>
				<p className="text-gray-500 dark:text-slate-400 max-w-md mx-auto">
					{__(
						'Create a team to orchestrate multiple agents together. Teams enable powerful multi-agent workflows with auto-routing, sequential execution, or parallel processing.',
						'agentflow-ai'
					)}
				</p>
			</div>
		);
	}

	return (
		<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
			{groups.map((group) => (
				<GroupCard
					key={group.id}
					group={group}
					onEdit={() => onEdit(group)}
					onDelete={() => onDelete(group.id)}
				/>
			))}
		</div>
	);
}

GroupList.propTypes = {
	groups: PropTypes.array.isRequired,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
};
