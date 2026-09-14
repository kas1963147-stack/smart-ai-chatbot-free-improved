/**
 * AgentCard Component - Slim Horizontal Style
 *
 * Compact card: Avatar | Name + Description | Three-dot menu
 * All actions (Edit, Test, Duplicate, Delete) in dropdown.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import {
	MoreVertical,
	Edit3,
	Trash2,
	Copy,
	MessageSquare,
} from 'lucide-react';
import { EntityIcon } from './shared/entity-icons';

/**
 * Agent Avatar with contextual SVG icon
 */
const AgentAvatar = ({ name, isActive }) => (
	<div className={`
		flex items-center justify-center 
		w-11 h-11 rounded-xl
		text-white
		${isActive ? 'bg-primary' : 'bg-gray-400'}
		shadow-sm
	`}>
		<EntityIcon name={name} size="w-5 h-5" />
	</div>
);

export default function AgentCard({
	agent,
	onEdit,
	onDelete,
	onDuplicate,
	onTest,
}) {
	const [menuOpen, setMenuOpen] = useState(false);

	return (
		<article
			className="group relative bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm transition-all duration-200 hover:shadow-md hover:border-primary/20 cursor-pointer"
			onClick={() => onEdit?.()}
		>
			{/* Slim Card Content */}
			<div className="flex items-center gap-3 p-3.5">
				{/* Avatar */}
				<AgentAvatar name={agent.name} isActive={agent.is_active} />

				{/* Info */}
				<div className="flex-1 min-w-0">
					<h3 className="text-sm font-semibold text-gray-900 dark:text-white truncate">
						{agent.name}
					</h3>
					{agent.description && (
						<p className="text-xs text-gray-500 dark:text-slate-400 line-clamp-2 mt-0.5">
							{agent.description}
						</p>
					)}
				</div>

				{/* Three-dot Menu */}
				<div className="relative shrink-0" onClick={(e) => e.stopPropagation()}>
					<button
						onClick={() => setMenuOpen(!menuOpen)}
						className="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
					>
						<MoreVertical className="w-4 h-4" />
					</button>

					{/* Dropdown */}
					{menuOpen && (
						<>
							<div
								className="fixed inset-0 z-10"
								onClick={() => setMenuOpen(false)}
							/>
							<div className="absolute right-0 top-full mt-1 w-40 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 overflow-hidden z-20">
								<button
									onClick={() => { setMenuOpen(false); onEdit(); }}
									className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
								>
									<Edit3 className="w-4 h-4" />
									{__('Edit', 'agentflow-ai')}
								</button>
								{onTest && (
									<button
										onClick={() => { setMenuOpen(false); onTest(agent); }}
										className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
									>
										<MessageSquare className="w-4 h-4" />
										{__('Test', 'agentflow-ai')}
									</button>
								)}
								{onDuplicate && (
									<button
										onClick={() => { setMenuOpen(false); onDuplicate(); }}
										className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
									>
										<Copy className="w-4 h-4" />
										{__('Duplicate', 'agentflow-ai')}
									</button>
								)}
								{onDelete && (
									<button
										onClick={() => { setMenuOpen(false); onDelete(); }}
										className="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
									>
										<Trash2 className="w-4 h-4" />
										{__('Delete', 'agentflow-ai')}
									</button>
								)}
							</div>
						</>
					)}
				</div>
			</div>
		</article>
	);
}

AgentCard.propTypes = {
	agent: PropTypes.shape({
		id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
		agent_id: PropTypes.string,
		name: PropTypes.string,
		description: PropTypes.string,
		is_active: PropTypes.oneOfType([PropTypes.bool, PropTypes.number]),
		is_default: PropTypes.oneOfType([PropTypes.bool, PropTypes.number]),
		avatar: PropTypes.string,
		config: PropTypes.shape({
			enabled_toolkits: PropTypes.arrayOf(PropTypes.string),
		}),
	}).isRequired,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
	onDuplicate: PropTypes.func,
	onTest: PropTypes.func,
};
