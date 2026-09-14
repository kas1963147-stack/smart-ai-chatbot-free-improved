/**
 * SkillList Component
 *
 * Grid of skill cards with empty state.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import SkillCard from './SkillCard';
import { Button } from '../ui';

export default function SkillList({
	skills,
	onEdit,
	onDelete,
	onDuplicate,
	onCreate,
}) {
	// Empty state
	if (!skills || skills.length === 0) {
		return (
			<div className="flex flex-col items-center justify-center p-12 bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 text-center shadow-sm">
				<div className="w-16 h-16 bg-blue-50 dark:bg-blue-900/20 text-primary rounded-full flex items-center justify-center mb-6">
					<svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
					</svg>
				</div>
				<h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2 mt-0">
					{__('No skills yet', 'agentflow-ai')}
				</h3>
				<p className="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-8">
					{__(
						'Skills teach your AI agent how to handle specific tasks like refunds, appointments, or lead generation.',
						'agentflow-ai'
					)}
				</p>
				<button
					type="button"
					onClick={onCreate}
					className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
				>
					<svg className="w-5 h-5 mr-2 -ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
					{__('Create Your First Skill', 'agentflow-ai')}
				</button>
			</div>
		);
	}

	return (
		<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-fade-in-up">
			{skills.map((skill) => (
				<SkillCard
					key={skill.id}
					skill={skill}
					onEdit={onEdit}
					onDelete={onDelete}
					onDuplicate={onDuplicate}
				/>
			))}
		</div>
	);
}

SkillList.propTypes = {
	skills: PropTypes.array,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
	onDuplicate: PropTypes.func,
	onCreate: PropTypes.func,
};
