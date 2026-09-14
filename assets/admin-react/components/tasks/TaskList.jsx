/**
 * Task List Component - Metronic v9 Style
 *
 * Displays scheduled tasks in modern card grid.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

// Task Type Icons
const TASK_TYPE_ICONS = {
	content_generation: '',
	research: '',
	analytics: '',
	sync: '',
	custom: '',
};

// Status Config
const STATUS_CONFIG = {
	active: { label: __('Active', 'agentflow-ai'), class: 'bg-green-100 text-green-700' },
	paused: { label: __('Paused', 'agentflow-ai'), class: 'bg-yellow-100 text-yellow-700' },
	completed: { label: __('Completed', 'agentflow-ai'), class: 'bg-blue-100 text-blue-700' },
	disabled: { label: __('Disabled', 'agentflow-ai'), class: 'bg-gray-100 text-gray-500' },
};

const SCHEDULE_TYPE_LABELS = {
	once: __('One-time', 'agentflow-ai'),
	recurring: __('Recurring', 'agentflow-ai'),
	cron: __('Cron', 'agentflow-ai'),
};

export default function TaskList({
	tasks,
	onEdit,
	onDelete,
	onViewHistory,
	onRunNow,
	onPause,
	onResume,
}) {
	// Empty state
	if (!tasks || tasks.length === 0) {
		return (
			<div className="text-center py-16 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800">
				<div className="mb-4 flex justify-center">
					<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-gray-500">
						<circle cx="12" cy="13" r="8" />
						<path d="M12 9v4l2 2" />
						<path d="M5 3 2 6" />
						<path d="m22 6-3-3" />
						<path d="M6.38 18.7 4 21" />
						<path d="M17.64 18.67 20 21" />
					</svg>
				</div>
				<h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
					{__('No Scheduled Tasks', 'agentflow-ai')}
				</h3>
				<p className="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
					{__('Create your first scheduled task to automate agent workflows.', 'agentflow-ai')}
				</p>
			</div>
		);
	}

	const formatNextRun = (dateStr, status) => {
		if (status !== 'active') return '—';
		if (!dateStr) return __('Not scheduled', 'agentflow-ai');

		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = date - now;

		if (diffMs < 0) return __('Due now', 'agentflow-ai');

		const diffMins = Math.floor(diffMs / 60000);
		if (diffMins < 60) return `${diffMins}m`;

		const diffHours = Math.floor(diffMins / 60);
		if (diffHours < 24) return `${diffHours}h`;

		const diffDays = Math.floor(diffHours / 24);
		return `${diffDays}d`;
	};

	const formatSchedule = (task) => {
		if (task.schedule_type === 'once') {
			return SCHEDULE_TYPE_LABELS.once;
		}
		if (task.schedule_type === 'recurring') {
			const interval = task.schedule_config?.interval || 'daily';
			const time = task.schedule_config?.time || '';
			return `${interval.charAt(0).toUpperCase() + interval.slice(1)}${time ? ` @ ${time}` : ''}`;
		}
		if (task.schedule_type === 'cron') {
			return task.schedule_config?.expression || 'Cron';
		}
		return '—';
	};

	return (
		<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
			{tasks.map((task) => {
				const statusConfig = STATUS_CONFIG[task.status] || STATUS_CONFIG.active;
				const taskIcon = TASK_TYPE_ICONS[task.task_type] || TASK_TYPE_ICONS.custom;

				return (
					<article
						key={task.id}
						className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-lg hover:border-primary/20 dark:hover:border-primary/40 transition-all duration-200"
					>
						{/* Card Header */}
						<div className="p-5">
							<div className="flex items-start gap-4">
								{/* Icon */}
								<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-gray-100 dark:bg-gray-700 text-2xl">
									{taskIcon}
								</div>

								{/* Info */}
								<div className="flex-1 min-w-0">
									<div className="flex items-center gap-2 mb-1">
										<h3 className="text-base font-semibold text-gray-900 dark:text-white truncate" title={task.name}>
											{task.name}
										</h3>
									</div>
									<p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-1">
										{task.description || task.task_type}
									</p>
								</div>

								{/* Status Badge */}
								<span className={`inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full ${statusConfig.class}`}>
									{statusConfig.label}
								</span>
							</div>

							{/* Schedule Info */}
							<div className="mt-4 grid grid-cols-2 gap-4">
								<div>
									<div className="text-xs text-gray-400 dark:text-gray-500 mb-0.5">{__('Schedule', 'agentflow-ai')}</div>
									<div className="text-sm font-medium text-gray-700 dark:text-gray-300">{formatSchedule(task)}</div>
								</div>
								<div>
									<div className="text-xs text-gray-400 dark:text-gray-500 mb-0.5">{__('Next Run', 'agentflow-ai')}</div>
									<div className={`text-sm font-medium ${task.status === 'active' ? 'text-primary' : 'text-gray-400'}`}>
										{formatNextRun(task.next_run_at, task.status)}
									</div>
								</div>
							</div>
						</div>

						{/* Card Footer */}
						<div className="flex items-center justify-between px-5 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 rounded-b-xl">
							{/* Left Actions */}
							<div className="flex items-center gap-1">
								{task.status === 'active' && (
									<>
										<button
											onClick={() => onRunNow(task.id)}
											className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary hover:bg-primary/5 dark:hover:bg-primary/10 transition-colors"
											title={__('Run Now', 'agentflow-ai')}
										>
											
										</button>
										<button
											onClick={() => onPause(task.id)}
											className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-500/10 transition-colors"
											title={__('Pause', 'agentflow-ai')}
										>
											
										</button>
									</>
								)}
								{task.status === 'paused' && (
									<button
										onClick={() => onResume(task.id)}
										className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-500/10 transition-colors"
										title={__('Resume', 'agentflow-ai')}
									>
										
									</button>
								)}
								<button
									onClick={() => onViewHistory(task)}
									className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary hover:bg-primary/5 dark:hover:bg-primary/10 transition-colors"
									title={__('History', 'agentflow-ai')}
								>

								</button>
							</div>

							{/* Right Actions */}
							<div className="flex items-center gap-1">
								<button
									onClick={() => onEdit(task)}
									className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600 shadow-sm transition-colors"
								>
									{__('Edit', 'agentflow-ai')}
								</button>
								<button
									onClick={() => onDelete(task.id)}
									className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
								>
									{__('Delete', 'agentflow-ai')}
								</button>
							</div>
						</div>
					</article>
				);
			})}
		</div>
	);
}

TaskList.propTypes = {
	tasks: PropTypes.array,
	onEdit: PropTypes.func.isRequired,
	onDelete: PropTypes.func.isRequired,
	onViewHistory: PropTypes.func.isRequired,
	onRunNow: PropTypes.func.isRequired,
	onPause: PropTypes.func.isRequired,
	onResume: PropTypes.func.isRequired,
};
