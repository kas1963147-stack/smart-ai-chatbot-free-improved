/**
 * SkillCard Component - Metronic v9 Style
 *
 * Premium card for displaying a skill with modern design.
 */
import { __ } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';
import PropTypes from 'prop-types';
import { ShoppingCart, FileText, MessageSquare, Zap, Search, Pencil, FileIcon, MoreVertical, Copy, Edit2, Trash2 } from 'lucide-react';

// Category config with Metronic-style colors
const CATEGORY_CONFIG = {
	woocommerce: { color: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300', IconComponent: ShoppingCart, label: 'WooCommerce' },
	wordpress: { color: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300', IconComponent: FileText, label: 'WordPress' },
	support: { color: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300', IconComponent: MessageSquare, label: 'Support' },
	general: { color: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300', IconComponent: Zap, label: 'General' },
	seo: { color: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300', IconComponent: Search, label: 'SEO' },
	content: { color: 'bg-pink-100 dark:bg-pink-900/40 text-pink-700 dark:text-pink-300', IconComponent: Pencil, label: 'Content' },
};

export default function SkillCard({ skill, onEdit, onDelete, onDuplicate }) {
	const [deleting, setDeleting] = useState(false);
	const [menuOpen, setMenuOpen] = useState(false);
	const menuRef = useRef(null);

	const category = CATEGORY_CONFIG[skill.category] || CATEGORY_CONFIG.general;

	// Close menu on outside click
	useEffect(() => {
		const handleClickOutside = (e) => {
			if (menuRef.current && !menuRef.current.contains(e.target)) {
				setMenuOpen(false);
			}
		};
		if (menuOpen) {
			document.addEventListener('mousedown', handleClickOutside);
		}
		return () => document.removeEventListener('mousedown', handleClickOutside);
	}, [menuOpen]);

	const handleDelete = async () => {
		if (!confirm(__('Are you sure you want to delete this skill?', 'smart-woo-chatbot'))) {
			return;
		}
		setDeleting(true);
		try {
			await onDelete(skill.id);
		} catch (err) {
			setDeleting(false);
		}
	};

	return (
		<article
			onClick={onEdit ? () => onEdit(skill) : undefined}
			className="group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-lg hover:border-primary/20 dark:hover:border-primary/40 transition-all duration-200 cursor-pointer"
		>
			{/* Card Content */}
			<div className="p-5">
				{/* Header Row: Name on Left, Badge + Actions on Right */}
				<div className="flex items-start gap-4">
					{/* Avatar */}
					<div className={`flex items-center justify-center w-12 h-12 rounded-xl text-lg font-semibold shrink-0 ${category.color}`}>
						<category.IconComponent className="w-5 h-5" />
					</div>

					{/* Info */}
					<div className="flex-1 min-w-0">
						<div className="flex items-center gap-2 mb-1">
							<h3 className="text-base font-semibold text-gray-900 dark:text-white truncate">
								{skill.display_name || skill.name}
							</h3>
							{skill.always_on && (
								<span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-50 dark:bg-green-900/40 text-green-700 dark:text-green-300 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/30">
									{__('Active', 'smart-woo-chatbot')}
								</span>
							)}
						</div>
						<p className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
							{skill.description || __('No description', 'smart-woo-chatbot')}
						</p>
					</div>

					{/* Badge + Kebab on Right */}
					<div className="flex items-center gap-2 shrink-0">
						{/* Category Badge */}
						<span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full ${category.color}`}>
							{category.label}
						</span>

						{/* Kebab Menu */}
						<div className="relative" ref={menuRef}>
							<button
								onClick={(e) => {
									e.stopPropagation();
									setMenuOpen(!menuOpen);
								}}
								className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
								title={__('Actions', 'smart-woo-chatbot')}
							>
								<MoreVertical className="w-4 h-4" />
							</button>

							{/* Dropdown Menu */}
							{menuOpen && (
								<div className="absolute right-0 top-full mt-1 w-40 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-20 py-1">
									<button
										onClick={(e) => { e.stopPropagation(); onDuplicate(skill); setMenuOpen(false); }}
										className="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
									>
										<Copy className="w-3.5 h-3.5" />
										{__('Duplicate', 'smart-woo-chatbot')}
									</button>
									<button
										onClick={(e) => { e.stopPropagation(); onEdit(skill); setMenuOpen(false); }}
										className="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
									>
										<Edit2 className="w-3.5 h-3.5" />
										{__('Edit', 'smart-woo-chatbot')}
									</button>
									<div className="border-t border-gray-100 dark:border-gray-700 my-1" />
									<button
										onClick={(e) => { e.stopPropagation(); handleDelete(); setMenuOpen(false); }}
										disabled={deleting}
										className="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors disabled:opacity-50"
									>
										<Trash2 className="w-3.5 h-3.5" />
										{deleting ? __('Deleting...', 'smart-woo-chatbot') : __('Delete', 'smart-woo-chatbot')}
									</button>
								</div>
							)}
						</div>
					</div>
				</div>

				{/* Tools badges */}
				{skill.tools_required && skill.tools_required.length > 0 && (
					<div className="mt-4 flex flex-wrap gap-1.5">
						{skill.tools_required.slice(0, 3).map((tool) => (
							<span
								key={tool}
								className="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600"
							>
								{tool}
							</span>
						))}
						{skill.tools_required.length > 3 && (
							<span className="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
								+{skill.tools_required.length - 3}
							</span>
						)}
					</div>
				)}

				{/* References indicator */}
				{skill.reference_count > 0 && (
					<div className="mt-3 flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
						<FileIcon className="w-3.5 h-3.5" />
						<span>{skill.reference_count} {__('reference document(s)', 'smart-woo-chatbot')}</span>
					</div>
				)}
			</div>
		</article>
	);
}

SkillCard.propTypes = {
	skill: PropTypes.shape({
		id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
		name: PropTypes.string,
		display_name: PropTypes.string,
		description: PropTypes.string,
		category: PropTypes.string,
		always_on: PropTypes.bool,
		tools_required: PropTypes.array,
		reference_count: PropTypes.number,
	}).isRequired,
	onEdit: PropTypes.func.isRequired,
	onDelete: PropTypes.func.isRequired,
	onDuplicate: PropTypes.func.isRequired,
};
