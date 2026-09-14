/**
 * SkillFilters Component - Metronic v9 Style
 *
 * Modern filtering UI for skills with clean design.
 */
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

const CATEGORIES = [
	{ id: 'all', label: 'All Categories' },
	{ id: 'woocommerce', label: 'WooCommerce' },
	{ id: 'wordpress', label: 'WordPress' },
	{ id: 'support', label: 'Support' },
	{ id: 'general', label: 'General' },
	{ id: 'content', label: 'Content' },
	{ id: 'marketing', label: 'Marketing' },
	{ id: 'seo', label: 'SEO' },
];

const SORT_OPTIONS = [
	{ id: 'name_asc', label: 'Name (A-Z)' },
	{ id: 'name_desc', label: 'Name (Z-A)' },
	{ id: 'category', label: 'Category' },
];

export default function SkillFilters({
	filters,
	onFilterChange,
	categories = null,
}) {
	const updateFilter = (key, value) => {
		onFilterChange({ ...filters, [key]: value });
	};

	const categoryOptions = useMemo(() => {
		if (categories) {
			const opts = [
				{ id: 'all', label: __('All Categories', 'agentflow-ai') },
			];

			if (Array.isArray(categories) && categories.length > 0) {
				categories.forEach((c) => {
					opts.push({
						id: c.id || c.slug,
						label: c.name || c.label || c.slug,
					});
				});
				return opts;
			} else if (typeof categories === 'object' && Object.keys(categories).length > 0) {
				Object.entries(categories).forEach(([id, value]) => {
					const label = typeof value === 'object' ? value.name || value.label || id : value;
					opts.push({ id, label });
				});
				return opts;
			}
		}
		return CATEGORIES;
	}, [categories]);

	const hasActiveFilters = () => {
		return (
			(filters.category && filters.category !== 'all') ||
			(filters.group && filters.group !== 'all') ||
			(filters.search && filters.search.trim() !== '') ||
			(filters.status && filters.status !== 'all') ||
			(filters.tool && filters.tool !== 'all')
		);
	};

	const clearFilters = () => {
		onFilterChange({
			search: '',
			category: 'all',
			group: 'all',
			status: 'all',
			hasReferences: 'all',
			tool: 'all',
			sort: 'name_asc',
		});
	};

	return (
		<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-6">
			<div className="flex flex-wrap items-center gap-3">
				{/* Search */}
				<div className="flex-1 min-w-[200px]">
					<input
						type="text"
						value={filters.search || ''}
						onChange={(e) => updateFilter('search', e.target.value)}
						placeholder={__('Search skills…', 'agentflow-ai')}
						className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
					/>
				</div>

				{/* Category dropdown */}
				<select
					value={filters.category || 'all'}
					onChange={(e) => updateFilter('category', e.target.value)}
					className="h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer"
				>
					{categoryOptions.map((opt) => (
						<option key={opt.id} value={opt.id}>
							{opt.label}
						</option>
					))}
				</select>

				{/* Sort dropdown */}
				<select
					value={filters.sort || 'name_asc'}
					onChange={(e) => updateFilter('sort', e.target.value)}
					className="h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer"
				>
					{SORT_OPTIONS.map((opt) => (
						<option key={opt.id} value={opt.id}>
							{opt.label}
						</option>
					))}
				</select>

				{/* Clear filters */}
				{hasActiveFilters() && (
					<button
						onClick={clearFilters}
						className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
					>
						{__('Clear', 'agentflow-ai')}
					</button>
				)}
			</div>
		</div>
	);
}

SkillFilters.propTypes = {
	filters: PropTypes.object.isRequired,
	onFilterChange: PropTypes.func.isRequired,
	categories: PropTypes.oneOfType([PropTypes.array, PropTypes.object]),
};
