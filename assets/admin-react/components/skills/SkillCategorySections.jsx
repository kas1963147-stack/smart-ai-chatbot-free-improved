/**
 * SkillCategorySections Component - Metronic v9 Style
 *
 * Displays skills organized into category sections with modern card grid.
 */
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { ShoppingCart, FileText, MessageSquare, Zap, Pencil, Megaphone, Search, Settings, BookOpen, Folder } from 'lucide-react';
import SkillCard from './SkillCard';

// Category configuration with icons as components
const CATEGORY_CONFIG = {
	woocommerce: { id: 'woocommerce', label: 'WooCommerce', IconComponent: ShoppingCart },
	wordpress: { id: 'wordpress', label: 'WordPress', IconComponent: FileText },
	support: { id: 'support', label: 'Support', IconComponent: MessageSquare },
	general: { id: 'general', label: 'General', IconComponent: Zap },
	content: { id: 'content', label: 'Content', IconComponent: Pencil },
	marketing: { id: 'marketing', label: 'Marketing', IconComponent: Megaphone },
	seo: { id: 'seo', label: 'SEO', IconComponent: Search },
	admin: { id: 'admin', label: 'Admin', IconComponent: Settings },
};

export default function SkillCategorySections({
	skills,
	onEdit,
	onDelete,
	onDuplicate,
	onCreate,
}) {
	// Group skills by category
	const skillsByCategory = useMemo(() => {
		const grouped = {};
		skills.forEach((skill) => {
			const category = skill.category || 'general';
			if (!grouped[category]) {
				grouped[category] = [];
			}
			grouped[category].push(skill);
		});
		return grouped;
	}, [skills]);

	// Get categories that have skills (sorted by order)
	const categoriesWithSkills = useMemo(() => {
		const order = ['woocommerce', 'wordpress', 'support', 'content', 'marketing', 'seo', 'admin', 'general'];
		return order.filter((catId) => skillsByCategory[catId]?.length > 0);
	}, [skillsByCategory]);

	// Empty state
	if (skills.length === 0) {
		return (
			<div className="text-center py-20 bg-white dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700">
				<BookOpen className="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
				<h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
					{__('No skills yet', 'agentflow-ai')}
				</h3>
				<p className="text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
					{__('Create your first skill to teach your AI agent new behaviors.', 'agentflow-ai')}
				</p>
				<button
					onClick={onCreate}
					className="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
				>
					<span>+</span>
					{__('Create Skill', 'agentflow-ai')}
				</button>
			</div>
		);
	}

	return (
		<div className="space-y-8">
			{categoriesWithSkills.map((categoryId) => {
				const category = CATEGORY_CONFIG[categoryId] || { label: categoryId, IconComponent: Folder };
				const categorySkills = skillsByCategory[categoryId] || [];

				return (
					<section key={categoryId}>
						{/* Category Header */}
						<div className="flex items-center gap-3 mb-4">
							{category.IconComponent && <category.IconComponent className="w-6 h-6 text-gray-600 dark:text-gray-400" />}
							<h2 className="text-lg font-semibold text-gray-900 dark:text-white">
								{category.label}
							</h2>
							<span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
								{categorySkills.length}
							</span>
						</div>

						{/* Skills Grid */}
						<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
							{categorySkills.map((skill) => (
								<SkillCard
									key={skill.id}
									skill={skill}
									onEdit={onEdit}
									onDelete={onDelete}
									onDuplicate={onDuplicate}
								/>
							))}
						</div>
					</section>
				);
			})}

			{/* Summary Footer */}
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-4 flex items-center justify-between">
				<div className="text-sm text-gray-600 dark:text-gray-400">
					<strong className="text-gray-900 dark:text-white">{skills.length}</strong> {__('total skills', 'agentflow-ai')}
					<span className="mx-2">•</span>
					<strong className="text-gray-900 dark:text-white">{categoriesWithSkills.length}</strong> {__('categories', 'agentflow-ai')}
				</div>
				<button
					onClick={onCreate}
					className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium rounded-lg text-primary hover:bg-primary/5 transition-colors"
				>
					<span>+</span>
					{__('Add Skill', 'agentflow-ai')}
				</button>
			</div>
		</div>
	);
}

SkillCategorySections.propTypes = {
	skills: PropTypes.array.isRequired,
	onEdit: PropTypes.func.isRequired,
	onDelete: PropTypes.func.isRequired,
	onDuplicate: PropTypes.func.isRequired,
	onCreate: PropTypes.func.isRequired,
};
