/**
 * GroupCard Component
 *
 * Premium Metronic v9 styled group card with modern aesthetics.
 * Using Tailwind CSS design patterns.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import {
	Users,
	Edit3,
	Trash2,
	MoreVertical,
	Zap,
	ArrowRight,
	Layers,
	RefreshCw,
	ClipboardList,
} from 'lucide-react';

// Orchestration mode configurations
const ORCHESTRATION_MODES = {
	router: {
		label: __('Auto-Router', 'smart-woo-chatbot'),
		icon: Zap,
		bgColor: 'bg-blue-100 dark:bg-blue-900/30',
		textColor: 'text-blue-600 dark:text-blue-400',
		iconGradient: 'from-blue-500 to-indigo-600',
	},
	sequential: {
		label: __('Sequential', 'smart-woo-chatbot'),
		icon: ArrowRight,
		bgColor: 'bg-green-100 dark:bg-green-900/30',
		textColor: 'text-green-600 dark:text-green-400',
		iconGradient: 'from-green-500 to-emerald-600',
	},
	parallel: {
		label: __('Parallel', 'smart-woo-chatbot'),
		icon: Layers,
		bgColor: 'bg-purple-100 dark:bg-purple-900/30',
		textColor: 'text-purple-600 dark:text-purple-400',
		iconGradient: 'from-purple-500 to-pink-600',
	},
	handoff: {
		label: __('Handoff', 'smart-woo-chatbot'),
		icon: RefreshCw,
		bgColor: 'bg-orange-100 dark:bg-orange-900/30',
		textColor: 'text-orange-600 dark:text-orange-400',
		iconGradient: 'from-orange-500 to-amber-600',
	},
};

export default function GroupCard({ group, onEdit, onDelete }) {
	const [menuOpen, setMenuOpen] = useState(false);

	const mode = ORCHESTRATION_MODES[group.orchestration_mode] || ORCHESTRATION_MODES.router;
	const ModeIcon = mode.icon;
	const memberCount = group.members?.length || group.member_count || 0;
	const workflowStepCount = group.routing_config?.workflow_steps?.length || 0;

	return (
		<article className="group bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">
			{/* Card Header */}
			<div className="p-5 border-b border-gray-100 dark:border-slate-800">
				<div className="flex items-start justify-between">
					<div className="flex items-center gap-3">
						{/* Avatar */}
						<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/50 dark:to-purple-900/50 shadow-sm">
							<Users className="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
						</div>
						<div className="min-w-0">
							<h3 className="font-semibold text-gray-900 dark:text-white truncate">
								{group.name}
							</h3>
							<p className="text-xs text-gray-400 dark:text-slate-500 font-mono">
								{group.group_id}
							</p>
						</div>
					</div>

					{/* Actions Menu */}
					<div className="relative">
						<button
							onClick={() => setMenuOpen(!menuOpen)}
							className="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"
						>
							<MoreVertical className="w-4 h-4" />
						</button>

						{/* Dropdown Menu */}
						{menuOpen && (
							<>
								<div
									className="fixed inset-0 z-10"
									onClick={() => setMenuOpen(false)}
								/>
								<div className="absolute right-0 top-full mt-1 w-36 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 overflow-hidden z-20">
									<button
										onClick={() => {
											setMenuOpen(false);
											onEdit();
										}}
										className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
									>
										<Edit3 className="w-4 h-4" />
										{__('Edit', 'smart-woo-chatbot')}
									</button>
									<button
										onClick={() => {
											setMenuOpen(false);
											onDelete();
										}}
										className="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
									>
										<Trash2 className="w-4 h-4" />
										{__('Delete', 'smart-woo-chatbot')}
									</button>
								</div>
							</>
						)}
					</div>
				</div>

				{/* Description + Meta Row */}
				<div className="mt-3 flex items-start justify-between gap-4">
					{group.description && (
						<p className="text-sm text-gray-500 dark:text-slate-400 line-clamp-2 min-w-0 flex-1">
							{group.description}
						</p>
					)}
					<div className="flex flex-col items-end gap-2 shrink-0">
						{/* Orchestration Mode Badge */}
						<span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg ${mode.bgColor} ${mode.textColor}`}>
							<ModeIcon className="w-3 h-3" />
							{mode.label}
						</span>
						{/* Stats */}
						<div className="flex items-center gap-3 text-xs text-gray-500 dark:text-slate-400">
							<span className="inline-flex items-center gap-1">
								<Users className="w-3.5 h-3.5" />
								{memberCount} {memberCount === 1 ? __('agent', 'smart-woo-chatbot') : __('agents', 'smart-woo-chatbot')}
							</span>
							{workflowStepCount > 0 && (
								<span className="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
									<ClipboardList className="w-3.5 h-3.5" />
									{workflowStepCount} {workflowStepCount === 1 ? __('step', 'smart-woo-chatbot') : __('steps', 'smart-woo-chatbot')}
								</span>
							)}
						</div>
					</div>
				</div>
			</div>


		</article>
	);
}

GroupCard.propTypes = {
	group: PropTypes.shape({
		id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
		group_id: PropTypes.string,
		name: PropTypes.string,
		description: PropTypes.string,
		is_active: PropTypes.oneOfType([PropTypes.bool, PropTypes.number]),
		avatar: PropTypes.string,
		orchestration_mode: PropTypes.string,
		members: PropTypes.array,
		member_count: PropTypes.number,
	}).isRequired,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
};
