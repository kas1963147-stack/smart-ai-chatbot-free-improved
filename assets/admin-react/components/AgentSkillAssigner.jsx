/**
 * AgentSkillAssigner Component
 *
 * Tailwind-only skill selection for agents.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { Button, Checkbox, Tabs, TextField, cn } from './ui';
import { getCached, setCache } from '../hooks/useApiCache';
import Loading from './common/Loading';
import {
	ShoppingCart,
	FileText,
	Settings,
	MessageSquare,
	Sparkles,
	FileEdit,
	Megaphone,
	Search,
} from 'lucide-react';

const CATEGORY_CONFIG = {
	woocommerce: { label: 'WooCommerce' },
	wordpress: { label: 'WordPress' },
	admin: { label: 'Admin' },
	support: { label: 'Support' },
	general: { label: 'General' },
	content: { label: 'Content' },
	marketing: { label: 'Marketing' },
	seo: { label: 'SEO' },
};

const CATEGORY_ICONS = {
	woocommerce: { Icon: ShoppingCart, bg: 'bg-primary/10', color: 'text-primary' },
	wordpress: { Icon: FileText, bg: 'bg-blue-50', color: 'text-blue-600' },
	admin: { Icon: Settings, bg: 'bg-orange-50', color: 'text-orange-600' },
	support: { Icon: MessageSquare, bg: 'bg-green-50', color: 'text-green-600' },
	general: { Icon: Sparkles, bg: 'bg-purple-50', color: 'text-purple-600' },
	content: { Icon: FileEdit, bg: 'bg-cyan-50', color: 'text-cyan-600' },
	marketing: { Icon: Megaphone, bg: 'bg-pink-50', color: 'text-pink-600' },
	seo: { Icon: Search, bg: 'bg-indigo-50', color: 'text-indigo-600' },
};

export default function AgentSkillAssigner({
	enabledSkills = [],
	onEnabledChange,
}) {
	const [skills, setSkills] = useState([]);
	const [loading, setLoading] = useState(true);
	const [searchQuery, setSearchQuery] = useState('');
	const [activeCategory, setActiveCategory] = useState('all');

	useEffect(() => {
		const cached = getCached('skills');
		if (cached?.skills || cached?.data?.skills) {
			const list = cached.skills || cached.data.skills;
			setSkills(list || []);
			setLoading(false);
		}

		const fetchSkills = async () => {
			try {
				const response = await apiFetch({
					path: '/smart-ai-chatbot/v1/skills',
				});

				if (response.success) {
					setSkills(response.data.skills || []);
					setCache('skills', response.data);
				}
			} catch (err) {
				console.error('Failed to fetch skills:', err);
			} finally {
				setLoading(false);
			}
		};

		fetchSkills();
	}, []);

	const filteredSkills = useMemo(() => {
		let result = [...skills];

		if (activeCategory !== 'all') {
			result = result.filter((s) => s.category === activeCategory);
		}

		if (searchQuery.trim()) {
			const query = searchQuery.toLowerCase();
			result = result.filter(
				(s) =>
					s.name.toLowerCase().includes(query) ||
					s.display_name?.toLowerCase().includes(query) ||
					s.description?.toLowerCase().includes(query)
			);
		}

		return result;
	}, [skills, activeCategory, searchQuery]);

	const groupedSkills = useMemo(() => {
		const groups = {};

		filteredSkills.forEach((skill) => {
			const cat = skill.category || 'general';
			if (!groups[cat]) {
				groups[cat] = [];
			}
			groups[cat].push(skill);
		});

		return groups;
	}, [filteredSkills]);

	const availableCategories = useMemo(() => {
		const cats = new Set(skills.map((s) => s.category || 'general'));
		return Array.from(cats);
	}, [skills]);

	const categoryTabs = useMemo(
		() => [
			{
				id: 'all',
				label: `${__('All', 'smart-woo-chatbot')} (${skills.length})`,
			},
			...availableCategories.map((catId) => ({
				id: catId,
				label: CATEGORY_CONFIG[catId]?.label || catId,
			})),
		],
		[availableCategories, skills.length]
	);

	const isSkillEnabled = (skillId) => enabledSkills.includes(skillId);

	const toggleSkill = (skillId) => {
		if (enabledSkills.includes(skillId)) {
			onEnabledChange(enabledSkills.filter((id) => id !== skillId));
		} else {
			onEnabledChange([...enabledSkills, skillId]);
		}
	};

	const selectAllInView = () => {
		const viewedIds = filteredSkills.map((s) => s.id);
		const newEnabled = [...new Set([...enabledSkills, ...viewedIds])];
		onEnabledChange(newEnabled);
	};

	const deselectAllInView = () => {
		const viewedIds = filteredSkills.map((s) => s.id);
		onEnabledChange(enabledSkills.filter((id) => !viewedIds.includes(id)));
	};

	if (loading) {
		return <Loading message={__('Loading skills…', 'smart-woo-chatbot')} fullPage />;
	}

	return (
		<div className="w-full">
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
				{/* Header with Icon */}
				<div className="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
					<div className="flex items-center gap-3">
						<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30">
							<Sparkles className="w-5 h-5 text-purple-600 dark:text-purple-400" />
						</div>
						<div>
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
								{__('Agent Skills', 'smart-woo-chatbot')}
							</h3>
							<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
								{__('Select skills to enable for this agent. Only checked skills will be available.', 'smart-woo-chatbot')}
							</p>
						</div>
					</div>
				</div>

				<div className="flex flex-col gap-3 border-b border-slate-200/70 px-5 py-4 dark:border-slate-800/70 md:flex-row md:items-center">
					<div className="flex-1 min-w-0">
						<TextField
							value={searchQuery}
							onChange={setSearchQuery}
							placeholder={__('Search skills...', 'smart-woo-chatbot')}
							className="w-full"
						/>
					</div>
					<div className="flex flex-wrap items-center gap-2">
						<Button variant="primary" size="sm" onClick={selectAllInView}>
							{__('Enable All', 'smart-woo-chatbot')}
						</Button>
						<Button variant="secondary" size="sm" onClick={deselectAllInView}>
							{__('Disable All', 'smart-woo-chatbot')}
						</Button>
					</div>
				</div>

				<div className="px-5 py-3">
					<Tabs tabs={categoryTabs} activeId={activeCategory} onChange={setActiveCategory} />
				</div>

				<div className="px-5 py-4">
					{Object.entries(groupedSkills).map(([category, categorySkills]) => (
						<div key={category} className="mb-6">
							<div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
								{CATEGORY_CONFIG[category]?.label || category}
								<span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-300">
									{categorySkills.length}
								</span>
							</div>

							<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
								{categorySkills.map((skill) => {
									const enabled = isSkillEnabled(skill.id);
									const catIcon = CATEGORY_ICONS[skill.category] || CATEGORY_ICONS.general;

									return (
										<div
											key={skill.id}
											onClick={() => toggleSkill(skill.id)}
											className={cn(
												'cursor-pointer rounded-xl border p-4 transition',
												enabled
													? 'border-primary/60 bg-primary/5 shadow-sm'
													: 'border-slate-200 bg-white hover:border-primary/30 hover:shadow-sm dark:border-slate-800 dark:bg-slate-950'
											)}
										>
											<div className="flex items-start gap-3">
												<div
													className={cn(
														'flex h-10 w-10 items-center justify-center rounded-xl',
														catIcon.bg,
														catIcon.color
													)}
												>
													<catIcon.Icon size={20} />
												</div>
												<div className="min-w-0 flex-1">
													<div className="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
														{skill.display_name || skill.name}
														{skill.always_on && (
															<span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
																{__('Core', 'smart-woo-chatbot')}
															</span>
														)}
													</div>
													<p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
														{skill.description?.substring(0, 100)}
														{skill.description?.length > 100 ? '...' : ''}
													</p>
												</div>
												<Checkbox
													checked={enabled}
													onChange={() => toggleSkill(skill.id)}
													onClick={(event) => event.stopPropagation()}
												/>
											</div>
										</div>
									);
								})}
							</div>
						</div>
					))}

					{filteredSkills.length === 0 && (
						<div className="py-10 text-center text-sm text-slate-500">
							{skills.length === 0
								? __('No skills available.', 'smart-woo-chatbot')
								: __('No skills match your search.', 'smart-woo-chatbot')}
						</div>
					)}
				</div>

				{/* Summary Footer */}
				<div className="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
					<div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
						<div className="flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900/30">
							<Sparkles className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
						</div>
						<span>
							<strong className="text-gray-900 dark:text-white">{enabledSkills.length}</strong>
							{' / '}
							{skills.length}
							{' '}
							{__('skills enabled for this agent', 'smart-woo-chatbot')}
						</span>
					</div>
				</div>
			</div>
		</div>
	);
}

AgentSkillAssigner.propTypes = {
	enabledSkills: PropTypes.array,
	onEnabledChange: PropTypes.func.isRequired,
};
