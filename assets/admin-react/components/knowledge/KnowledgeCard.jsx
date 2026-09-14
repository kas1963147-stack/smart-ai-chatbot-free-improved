/**
 * KnowledgeCard Component - GroupCard Style
 *
 * Display card for a knowledge item, matching the GroupCard (Agent Team) design.
 * Structure: Header (avatar + name + menu + description) | Body (badges) | Footer (status + edit)
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import {
	FileText,
	Edit3,
	Trash2,
	MoreVertical,
	BookOpen,
	ShieldCheck,
	Package,
	Building2,
} from 'lucide-react';

// Category configurations
const CATEGORY_CONFIG = {
	policies: {
		label: __('Policies', 'agentflow-ai'),
		icon: ShieldCheck,
		bgColor: 'bg-red-100 dark:bg-red-900/30',
		textColor: 'text-red-600 dark:text-red-400',
		avatarGradient: 'from-red-100 to-pink-100 dark:from-red-900/50 dark:to-pink-900/50',
	},
	products: {
		label: __('Products', 'agentflow-ai'),
		icon: Package,
		bgColor: 'bg-blue-100 dark:bg-blue-900/30',
		textColor: 'text-blue-600 dark:text-blue-400',
		avatarGradient: 'from-blue-100 to-indigo-100 dark:from-blue-900/50 dark:to-indigo-900/50',
	},
	company_info: {
		label: __('Company Info', 'agentflow-ai'),
		icon: Building2,
		bgColor: 'bg-emerald-100 dark:bg-emerald-900/30',
		textColor: 'text-emerald-600 dark:text-emerald-400',
		avatarGradient: 'from-emerald-100 to-teal-100 dark:from-emerald-900/50 dark:to-teal-900/50',
	},
	general: {
		label: __('General', 'agentflow-ai'),
		icon: BookOpen,
		bgColor: 'bg-purple-100 dark:bg-purple-900/30',
		textColor: 'text-purple-600 dark:text-purple-400',
		avatarGradient: 'from-indigo-100 to-purple-100 dark:from-indigo-900/50 dark:to-purple-900/50',
	},
};

export default function KnowledgeCard({ item, onEdit, onDelete }) {
	const [menuOpen, setMenuOpen] = useState(false);

	const catConfig = CATEGORY_CONFIG[item.category] || CATEGORY_CONFIG.general;
	const CatIcon = catConfig.icon;
	const initial = (item.title || item.name || 'K')?.charAt(0)?.toUpperCase();

	return (
		<article 
			onClick={() => onEdit(item)}
			className="group bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all duration-300 relative cursor-pointer"
		>
			<div className="p-4 flex items-center gap-4">
				{/* Avatar / Icon */}
				<div className={`flex shrink-0 items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br ${catConfig.avatarGradient} shadow-sm`}>
					<CatIcon className={`w-5 h-5 ${catConfig.textColor}`} />
				</div>

				{/* Info Column */}
				<div className="flex-1 min-w-0 flex flex-col justify-center">
					<h3 className="font-semibold text-sm text-gray-900 dark:text-white truncate mb-1.5">
						{item.title || item.name}
					</h3>
					<div className="flex items-center gap-2 text-xs text-gray-500 dark:text-slate-400 font-medium">
						<span className="uppercase bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded text-[10px]">
							{catConfig.label || 'GENERAL'}
						</span>
						<span>
							{(item.content_length > 0 || item.content) 
						? (item.content 
							? `${item.content.split(/\s+/).length} ${__('words', 'agentflow-ai')}` 
							: `${item.content_length} ${__('chars', 'agentflow-ai')}`)
						: __('No content', 'agentflow-ai')}
						</span>
						<span className="flex items-center gap-1.5">
							<span className={`w-1.5 h-1.5 rounded-full ${item.is_active ? 'bg-primary' : 'bg-gray-400'}`} />
							{item.is_active ? __('Active', 'agentflow-ai') : __('Inactive', 'agentflow-ai')}
						</span>
					</div>
				</div>

				{/* Actions */}
				<div className="flex shrink-0 items-center gap-1 pl-2">
					<button
						onClick={(e) => {
							e.stopPropagation();
							onEdit(item);
						}}
						className="flex items-center justify-center w-8 h-8 rounded-lg text-primary hover:bg-primary/10 transition-colors"
						title={__('Edit', 'agentflow-ai')}
					>
						<Edit3 className="w-4 h-4" />
					</button>

					<div className="relative">
						<button
							onClick={(e) => {
								e.stopPropagation();
								setMenuOpen(!menuOpen);
							}}
							className="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"
						>
							<MoreVertical className="w-4 h-4" />
						</button>

						{/* Dropdown Menu */}
						{menuOpen && (
							<>
								<div
									className="fixed inset-0 z-10"
									onClick={(e) => {
										e.stopPropagation();
										setMenuOpen(false);
									}}
								/>
								<div className="absolute right-0 top-full mt-1 w-36 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 overflow-hidden z-20">
									<button
										onClick={(e) => {
											e.stopPropagation();
											setMenuOpen(false);
											onEdit(item);
										}}
										className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
									>
										<Edit3 className="w-4 h-4" />
										{__('Edit', 'agentflow-ai')}
									</button>
									<button
										onClick={(e) => {
											e.stopPropagation();
											setMenuOpen(false);
											onDelete(item.id);
										}}
										className="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
									>
										<Trash2 className="w-4 h-4" />
										{__('Delete', 'agentflow-ai')}
									</button>
								</div>
							</>
						)}
					</div>
				</div>
			</div>
		</article>
	);
}

KnowledgeCard.propTypes = {
	item: PropTypes.object.isRequired,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
};
