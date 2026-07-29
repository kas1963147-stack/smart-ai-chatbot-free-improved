/**
 * SkillsPage Component
 *
 * Main skills management page – redesigned to match the AgentSkillAssigner
 * layout (icon header, search bar, category tabs, grouped card grid).
 */
import { useState, useEffect, useMemo, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Loading from '../common/Loading';
import SkillEditor from './SkillEditor';
import TemplateGallery from './TemplateGallery';
import SkillPreview from './SkillPreview';
import SkillAnalyticsDashboard from './SkillAnalyticsDashboard';
import GroupManager from './GroupManager';
import useSkillsApi from '../../hooks/useSkillsApi';
import { TextField, Tabs, cn } from '../ui';
import {
	Sparkles,
	ShoppingCart,
	FileText,
	Settings,
	MessageSquare,
	FileEdit,
	Megaphone,
	Search,
	Zap,
	BookOpen,
	MoreVertical,
} from 'lucide-react';

// View modes
const VIEW_MODES = {
	LIST: 'list',
	CREATE: 'create',
	EDIT: 'edit',
	ANALYTICS: 'analytics',
	TEMPLATES: 'templates',
	GROUPS: 'groups',
};

// Category configuration – reused from AgentSkillAssigner
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
	wordpress: { Icon: FileText, bg: 'bg-primary/10', color: 'text-primary' },
	admin: { Icon: Settings, bg: 'bg-primary/10', color: 'text-primary' },
	support: { Icon: MessageSquare, bg: 'bg-primary/10', color: 'text-primary' },
	general: { Icon: Sparkles, bg: 'bg-primary/10', color: 'text-primary' },
	content: { Icon: FileEdit, bg: 'bg-primary/10', color: 'text-primary' },
	marketing: { Icon: Megaphone, bg: 'bg-primary/10', color: 'text-primary' },
	seo: { Icon: Search, bg: 'bg-primary/10', color: 'text-primary' },
};

export default function SkillsPage() {
	// View state
	const [view, setView] = useState(VIEW_MODES.LIST);
	const [selectedSkill, setSelectedSkill] = useState(null);
	const [notification, setNotification] = useState(null);
	const [showPreview, setShowPreview] = useState(false);
	const [isOpening, setIsOpening] = useState(false);

	// Filter state
	const [searchQuery, setSearchQuery] = useState('');
	const [activeCategory, setActiveCategory] = useState('all');

	// Track which card's action menu is open
	const [openMenuId, setOpenMenuId] = useState(null);
	const menuRef = useRef(null);

	// Close menu when clicking outside
	useEffect(() => {
		const handleClickOutside = (e) => {
			if (menuRef.current && !menuRef.current.contains(e.target)) {
				setOpenMenuId(null);
			}
		};
		document.addEventListener('mousedown', handleClickOutside);
		return () => document.removeEventListener('mousedown', handleClickOutside);
	}, []);

	// API
	const {
		skills,
		groups,
		categories,
		loading,
		error,
		fetchSkills,
		fetchGroups,
		createSkill,
		updateSkill,
		deleteSkill,
		getSkill,
	} = useSkillsApi();

	// Initial fetch
	useEffect(() => {
		fetchSkills();
		fetchGroups();
	}, [fetchGroups, fetchSkills]);

	// Available categories from actual skills
	const availableCategories = useMemo(() => {
		const cats = new Set(skills.map((s) => s.category || 'general'));
		return Array.from(cats);
	}, [skills]);

	// Category tabs – mirrors AgentSkillAssigner
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

	// Filtered skills
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

	// Group filtered skills by category
	const groupedSkills = useMemo(() => {
		const grouped = {};
		const order = ['woocommerce', 'wordpress', 'support', 'content', 'marketing', 'seo', 'admin', 'general'];

		filteredSkills.forEach((skill) => {
			const cat = skill.category || 'general';
			if (!grouped[cat]) {
				grouped[cat] = [];
			}
			grouped[cat].push(skill);
		});

		const ordered = {};
		order.forEach((catId) => {
			if (grouped[catId]) {
				ordered[catId] = grouped[catId];
			}
		});
		Object.keys(grouped).forEach((catId) => {
			if (!ordered[catId]) {
				ordered[catId] = grouped[catId];
			}
		});

		return ordered;
	}, [filteredSkills]);

	// Handlers
	const handleCreate = () => {
		setSelectedSkill(null);
		setView(VIEW_MODES.CREATE);
	};

	const handleEdit = async (skill) => {
		try {
			setIsOpening(true);
			const fullSkill = await getSkill(skill.id);
			setSelectedSkill(fullSkill);
			setView(VIEW_MODES.EDIT);
		} catch (err) {
			showNotification(err.message, 'error');
		} finally {
			setIsOpening(false);
		}
	};

	const handleSave = async (skillData) => {
		try {
			if (view === VIEW_MODES.CREATE) {
				await createSkill(skillData);
				showNotification(__('Skill created successfully!', 'smart-woo-chatbot'), 'success');
			} else {
				await updateSkill(selectedSkill.name, skillData);
				showNotification(__('Skill updated successfully!', 'smart-woo-chatbot'), 'success');
			}
			setView(VIEW_MODES.LIST);
			fetchSkills();
		} catch (err) {
			showNotification(err.message || __('Failed to save skill', 'smart-woo-chatbot'), 'error');
			throw err;
		}
	};

	const handleDelete = async (skillId) => {
		try {
			await deleteSkill(skillId);
			showNotification(__('Skill deleted successfully!', 'smart-woo-chatbot'), 'success');
			fetchSkills();
		} catch (err) {
			showNotification(err.message, 'error');
		}
	};

	const handleDuplicate = async (skill) => {
		try {
			const fullSkill = await getSkill(skill.id);
			const newSkill = {
				...fullSkill,
				name: `${skill.name}-copy`,
				display_name: `${fullSkill.display_name || skill.name} (Copy)`,
			};
			await createSkill(newSkill);
			showNotification(__('Skill duplicated successfully!', 'smart-woo-chatbot'), 'success');
			fetchSkills();
		} catch (err) {
			showNotification(err.message, 'error');
		}
	};

	const handleUseTemplate = (template) => {
		setSelectedSkill({
			name: template.id,
			display_name: template.name,
			category: template.category,
			description: template.description,
			instructions: [
				{
					title: 'Instructions',
					items: template.content?.body?.split('\n').filter(Boolean) || [],
				},
			],
			tools_required: template.tools || [],
		});
		setView(VIEW_MODES.CREATE);
	};

	const handleBack = () => {
		setView(VIEW_MODES.LIST);
		setSelectedSkill(null);
		setShowPreview(false);
	};

	const showNotification = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 5000);
	};

	const getPageTitle = () => {
		switch (view) {
			case VIEW_MODES.CREATE: return __('Create Skill', 'smart-woo-chatbot');
			case VIEW_MODES.EDIT: return __('Edit Skill', 'smart-woo-chatbot');
			case VIEW_MODES.ANALYTICS: return __('Skill Analytics', 'smart-woo-chatbot');
			case VIEW_MODES.TEMPLATES: return __('Skill Templates', 'smart-woo-chatbot');
			case VIEW_MODES.GROUPS: return __('Agent Teams', 'smart-woo-chatbot');
			default: return __('Skills', 'smart-woo-chatbot');
		}
	};

	// Loading state
	if (loading && skills.length === 0) {
		return <Loading message={__('Loading skills…', 'smart-woo-chatbot')} fullPage />;
	}

	if (isOpening) {
		return <Loading message={__('Opening skill editor…', 'smart-woo-chatbot')} fullPage />;
	}

	return (
		<div className="min-h-screen bg-gray-50/50 dark:bg-gray-900">
			{/* Page Header */}
			{/* Action Bar */}
			<div className="flex items-center justify-end px-6 py-3">
				<div className="flex items-center gap-3">
					{view === VIEW_MODES.LIST ? (
						<>
							<button
								onClick={() => setView(VIEW_MODES.TEMPLATES)}
								className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
							>
								{__('Templates', 'smart-woo-chatbot')}
							</button>
							<button
								onClick={handleCreate}
								className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
							>
								<span>+</span>
								{__('New Skill', 'smart-woo-chatbot')}
							</button>
						</>
					) : (
						<button
							onClick={handleBack}
							className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
						>
							<span>←</span>
							{__('Back to Skills', 'smart-woo-chatbot')}
						</button>
					)}
				</div>
			</div>

			{/* Notifications */}
			{notification && (
				<div className={`mx-6 mt-4 px-4 py-3 rounded-lg flex items-center justify-between ${notification.status === 'success'
					? 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-700'
					: 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-700'
					}`}>
					<span className="text-sm">{notification.message}</span>
					<button
						onClick={() => setNotification(null)}
						className="text-lg opacity-70 hover:opacity-100"
					>
						×
					</button>
				</div>
			)}

			{/* Error */}
			{error && (
				<div className="mx-6 mt-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-700">
					<span className="text-sm">{error}</span>
				</div>
			)}

			{/* Content */}
			<main className="p-6">
				{/* ── List View ── */}
				{view === VIEW_MODES.LIST && (
					<div className="w-full">
						<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
							{/* Header with Icon – same as AgentSkillAssigner */}
							<div className="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
								<div className="flex items-center gap-3">
									<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
										<Sparkles className="w-5 h-5 text-primary" />
									</div>
									<div>
										<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
											{__('All Skills', 'smart-woo-chatbot')}
										</h3>
										<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
											{__('Manage and organize skills for your AI agents.', 'smart-woo-chatbot')}
										</p>
									</div>
								</div>
							</div>

							{/* Search bar */}
							<div className="flex flex-col gap-3 border-b border-slate-200/70 px-5 py-4 dark:border-slate-800/70 md:flex-row md:items-center">
								<div className="flex-1 min-w-0">
									<TextField
										value={searchQuery}
										onChange={setSearchQuery}
										placeholder={__('Search skills...', 'smart-woo-chatbot')}
										className="w-full"
									/>
								</div>
							</div>

							{/* Category Tabs */}
							<div className="px-5 py-3">
								<Tabs tabs={categoryTabs} activeId={activeCategory} onChange={setActiveCategory} />
							</div>

							{/* Skills Grid – grouped by category */}
							<div className="px-5 py-4">
								{Object.entries(groupedSkills).map(([category, categorySkills]) => (
									<div key={category} className="mb-6">
										{/* Category Header – same as AgentSkillAssigner */}
										<div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
											{CATEGORY_CONFIG[category]?.label || category}
											<span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-300">
												{categorySkills.length}
											</span>
										</div>

										{/* Card Grid – exact same card layout as AgentSkillAssigner */}
										<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
											{categorySkills.map((skill) => {
												const catIcon = CATEGORY_ICONS[skill.category] || CATEGORY_ICONS.general;
												const isMenuOpen = openMenuId === skill.id;

												return (
													<div
														key={skill.id}
														onClick={() => handleEdit(skill)}
														className={cn(
															'group relative cursor-pointer rounded-xl border p-4 transition',
															'border-slate-200 bg-white hover:border-primary/30 hover:shadow-sm dark:border-slate-800 dark:bg-slate-950'
														)}
													>
														{/* Single Row: Icon + Name on Left, Badge + Menu on Right */}
														<div className="flex items-start gap-3">
															{/* Category Icon */}
															<div
																className={cn(
																	'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
																	catIcon.bg,
																	catIcon.color
																)}
															>
																<catIcon.Icon size={20} />
															</div>

															{/* Name + Description */}
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

															{/* Badge + Three-dot menu on Right */}
															<div className="flex items-center gap-2 shrink-0">
																<span className={cn(
																	'inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full',
																	catIcon.bg,
																	catIcon.color
																)}>
																	{CATEGORY_CONFIG[skill.category]?.label || skill.category}
																</span>

																<div className="relative shrink-0" ref={isMenuOpen ? menuRef : null}>
																	<button
																		onClick={(e) => {
																			e.stopPropagation();
																			setOpenMenuId(isMenuOpen ? null : skill.id);
																		}}
																		className={cn(
																			'flex h-7 w-7 items-center justify-center rounded-lg transition-colors',
																			isMenuOpen
																				? 'text-slate-600 bg-slate-100 dark:text-slate-300 dark:bg-slate-800'
																				: 'text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:text-slate-300 dark:hover:bg-slate-800'
																		)}
																	>
																		<MoreVertical size={16} />
																	</button>

																	{isMenuOpen && (
																		<div className="absolute right-0 top-full mt-1 w-36 rounded-lg border border-slate-200 bg-white py-1 shadow-lg z-20 dark:border-slate-700 dark:bg-slate-900">
																			<button
																				onClick={(e) => {
																					e.stopPropagation();
																					setOpenMenuId(null);
																					handleEdit(skill);
																				}}
																				className="flex w-full items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
																			>
																				{__('Edit', 'smart-woo-chatbot')}
																			</button>
																			<button
																				onClick={(e) => {
																					e.stopPropagation();
																					setOpenMenuId(null);
																					handleDuplicate(skill);
																				}}
																				className="flex w-full items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
																			>
																				{__('Duplicate', 'smart-woo-chatbot')}
																			</button>
																			<button
																				onClick={(e) => {
																					e.stopPropagation();
																					setOpenMenuId(null);
																					if (confirm(__('Are you sure you want to delete this skill?', 'smart-woo-chatbot'))) {
																						handleDelete(skill.id);
																					}
																				}}
																				className="flex w-full items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30"
																			>
																				{__('Delete', 'smart-woo-chatbot')}
																			</button>
																		</div>
																	)}
																</div>
															</div>
														</div>
													</div>
												);
											})}
										</div>
									</div>
								))}

								{filteredSkills.length === 0 && (
									<div className="py-16 text-center">
										<BookOpen className="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
										<h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
											{skills.length === 0
												? __('No skills yet', 'smart-woo-chatbot')
												: __('No skills match your search', 'smart-woo-chatbot')}
										</h3>
										<p className="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
											{skills.length === 0
												? __('Create your first skill to teach your AI agent new behaviors.', 'smart-woo-chatbot')
												: __('Try adjusting your search or category filter.', 'smart-woo-chatbot')}
										</p>
										{skills.length === 0 && (
											<button
												onClick={handleCreate}
												className="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
											>
												<span>+</span>
												{__('Create Skill', 'smart-woo-chatbot')}
											</button>
										)}
									</div>
								)}
							</div>

							{/* Summary Footer */}
							<div className="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
								<div className="flex items-center justify-between">
									<div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
										<div className="flex items-center justify-center w-6 h-6 rounded-full bg-primary/10">
											<Sparkles className="w-3.5 h-3.5 text-primary" />
										</div>
										<span>
											<strong className="text-gray-900 dark:text-white">{skills.length}</strong>
											{' '}
											{__('total skills', 'smart-woo-chatbot')}
											<span className="mx-2">•</span>
											<strong className="text-gray-900 dark:text-white">{skills.filter(s => s.always_on).length}</strong>
											{' '}
											{__('active', 'smart-woo-chatbot')}
										</span>
									</div>
									<button
										onClick={handleCreate}
										className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium rounded-lg text-primary hover:bg-primary/5 transition-colors"
									>
										<span>+</span>
										{__('Add Skill', 'smart-woo-chatbot')}
									</button>
								</div>
							</div>
						</div>
					</div>
				)}

				{/* Analytics View */}
				{view === VIEW_MODES.ANALYTICS && <SkillAnalyticsDashboard />}

				{/* Groups View */}
				{view === VIEW_MODES.GROUPS && <GroupManager />}

				{/* Templates View */}
				{view === VIEW_MODES.TEMPLATES && (
					<TemplateGallery onSelectTemplate={handleUseTemplate} onClose={handleBack} />
				)}

				{/* Editor View */}
				{(view === VIEW_MODES.CREATE || view === VIEW_MODES.EDIT) && (
					<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
						<SkillEditor
							skill={selectedSkill}
							onSave={handleSave}
							onCancel={handleBack}
							isNew={view === VIEW_MODES.CREATE}
							onPreviewToggle={() => setShowPreview(!showPreview)}
							groups={groups}
						/>
					</div>
				)}
			</main>
		</div>
	);
}
