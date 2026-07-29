/**
 * WorkflowList Component
 *
 * Displays workflows with status badges and actions.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

const STATUS_LABELS = {
	active: { label: __('Active', 'smart-woo-chatbot'), class: 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400' },
	inactive: { label: __('Inactive', 'smart-woo-chatbot'), class: 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400' },
};

export default function WorkflowList({ workflows, onEdit, onDelete, onExecute, onHistory }) {
	if (!workflows || workflows.length === 0) {
		return (
			<div className="text-center py-16 border-2 border-dashed border-gray-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800">
				<div className="flex items-center justify-center mb-4">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-gray-500">
						<polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
					</svg>
				</div>
				<h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
					{__('No Workflows Yet', 'smart-woo-chatbot')}
				</h3>
				<p className="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
					{__('Create a workflow to automate multi-step agent operations.', 'smart-woo-chatbot')}
				</p>
			</div>
		);
	}

	return (
		<div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
			{workflows.map((workflow) => {
				const status = workflow.is_active ? STATUS_LABELS.active : STATUS_LABELS.inactive;
				return (
					<article
						key={workflow.id}
						className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg hover:border-primary/20 dark:hover:border-primary/40 transition-all duration-200"
					>
						<div className="p-5">
							<div className="flex items-start gap-4">
								<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-gray-100 dark:bg-slate-700 text-2xl">
									
								</div>
								<div className="flex-1 min-w-0">
									<h3 className="text-base font-semibold text-gray-900 dark:text-white truncate" title={workflow.name}>
										{workflow.name}
									</h3>
									<p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
										{workflow.description || __('No description', 'smart-woo-chatbot')}
									</p>
								</div>
								<span className={`inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full ${status.class}`}>
									{status.label}
								</span>
							</div>

							<div className="mt-4 grid grid-cols-2 gap-4">
								<div>
									<div className="text-xs text-gray-400 dark:text-gray-500 mb-0.5">{__('Trigger', 'smart-woo-chatbot')}</div>
									<div className="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">
										{workflow.trigger_type || 'manual'}
									</div>
								</div>
								<div>
									<div className="text-xs text-gray-400 dark:text-gray-500 mb-0.5">{__('Steps', 'smart-woo-chatbot')}</div>
									<div className="text-sm font-medium text-gray-700 dark:text-gray-300">
										{(workflow.steps || []).length}
									</div>
								</div>
							</div>
						</div>

						<div className="flex items-center justify-between px-5 py-3 border-t border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-800/50 rounded-b-xl">
							<div className="flex items-center gap-2">
								<button
									onClick={() => onExecute(workflow)}
									className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg bg-primary text-white hover:bg-primary/90 transition-colors"
								>
									{__('Run', 'smart-woo-chatbot')}
								</button>
								<button
									onClick={() => onHistory(workflow)}
									className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors"
								>
									{__('History', 'smart-woo-chatbot')}
								</button>
							</div>
							<div className="flex items-center gap-1">
								<button
									onClick={() => onEdit(workflow)}
									className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600 border border-gray-300 dark:border-slate-600 shadow-sm transition-colors"
								>
									{__('Edit', 'smart-woo-chatbot')}
								</button>
								<button
									onClick={() => onDelete(workflow.id)}
									className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
								>
									{__('Delete', 'smart-woo-chatbot')}
								</button>
							</div>
						</div>
					</article>
				);
			})}
		</div>
	);
}

WorkflowList.propTypes = {
	workflows: PropTypes.array.isRequired,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
	onExecute: PropTypes.func,
	onHistory: PropTypes.func,
};
