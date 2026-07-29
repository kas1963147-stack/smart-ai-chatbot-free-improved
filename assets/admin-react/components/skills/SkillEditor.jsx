/**
 * SkillEditor Component
 *
 * Full skill create/edit form with all sections.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

import InstructionEditor from './InstructionEditor';
import ReferenceManager from './ReferenceManager';
import ToolSelector from './ToolSelector';

// Category options
const CATEGORIES = [
	{ id: 'woocommerce', label: 'WooCommerce' },
	{ id: 'wordpress', label: 'WordPress' },
	{ id: 'support', label: 'Support' },
	{ id: 'general', label: 'General' },
];

// Generate slug from display name
const generateSlug = (name) => {
	return name
		.toLowerCase()
		.replace(/[^a-z0-9\s-]/g, '')
		.replace(/[\s_]+/g, '-')
		.replace(/-+/g, '-')
		.trim();
};

// Default empty form state
const getInitialState = (skill) => {
	if (skill) {
		return {
			name: skill.name || '',
			display_name: skill.display_name || '',
			description: skill.description || '',
			category: skill.category || 'general',
			tools_required: skill.tools_required || [],
			always_on: skill.always_on || false,
			instructions:
				skill.instructions?.length > 0
					? skill.instructions
					: [
						{
							id: 'section-1',
							heading: '',
							type: 'bullets',
							items: [''],
							content: '',
						},
					],
			references: skill.references || [],
		};
	}

	return {
		name: '',
		display_name: '',
		description: '',
		category: 'general',
		tools_required: [],
		always_on: false,
		instructions: [
			{
				id: 'section-1',
				heading: '',
				type: 'bullets',
				items: [''],
				content: '',
			},
		],
		references: [],
	};
};

export default function SkillEditor({
	skill,
	onSave,
	onCancel,
	isNew,
	groups = [],
}) {
	const [formData, setFormData] = useState(() =>
		getInitialState(skill)
	);
	const [errors, setErrors] = useState({});
	const [saving, setSaving] = useState(false);
	const [activeTab, setActiveTab] = useState('basic');

	// Reset form when skill changes
	useEffect(() => {
		setFormData({
			...getInitialState(skill),
			group: skill?.group || 'ungrouped', // Ensure group is preserved or defaulted
		});
		setErrors({});
	}, [skill]);

	// Auto-generate slug from display name (only for new skills)
	useEffect(() => {
		if (isNew && formData.display_name) {
			const slug = generateSlug(formData.display_name);
			setFormData((prev) => ({ ...prev, name: slug }));
		}
	}, [formData.display_name, isNew]);

	// Update field
	const updateField = (field, value) => {
		setFormData((prev) => ({ ...prev, [field]: value }));
		// Clear error when field is edited
		if (errors[field]) {
			setErrors((prev) => ({ ...prev, [field]: null }));
		}
	};

	// Validate form
	const validate = () => {
		const newErrors = {};

		if (!formData.name.trim()) {
			newErrors.name = __(
				'Skill name is required',
				'smart-woo-chatbot'
			);
		} else if (! /^[a-z0-9][a-z0-9-]*[a-z0-9]?$/.test(formData.name)) {
			newErrors.name = __(
				'Name must be lowercase with hyphens only',
				'smart-woo-chatbot'
			);
		}

		if (!formData.description.trim()) {
			newErrors.description = __(
				'Description is required',
				'smart-woo-chatbot'
			);
		}

		// Check if instructions have content
		const hasInstructionContent = formData.instructions.some((s) => {
			if (s.type === 'paragraph') {
				return s.content?.trim();
			}
			return s.items?.some((item) => item?.trim());
		});

		if (!hasInstructionContent) {
			newErrors.instructions = __(
				'At least one instruction section with content is required',
				'smart-woo-chatbot'
			);
		}

		setErrors(newErrors);
		return Object.keys(newErrors).length === 0;
	};

	// Handle save
	const handleSave = async () => {
		if (!validate()) {
			// Switch to tab with errors
			if (errors.name || errors.description) {
				setActiveTab('basic');
			} else if (errors.instructions) {
				setActiveTab('instructions');
			}
			return;
		}

		setSaving(true);
		try {
			await onSave(formData);
		} catch (err) {
			// Handle validation errors from server
			if (err.details) {
				setErrors(err.details);
			}
		} finally {
			setSaving(false);
		}
	};

	// Tabs config
	const tabs = [
		{
			id: 'basic',
			label: __('Basic Info', 'smart-woo-chatbot'),
		},
		{
			id: 'instructions',
			label: __('Instructions', 'smart-woo-chatbot'),
		},
		{ id: 'tools', label: __('Tools', 'smart-woo-chatbot') },
		{
			id: 'references',
			label: __('References', 'smart-woo-chatbot'),
		},
	];

	return (
		<div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
			{ /* Tabs */}
			<div className="border-b border-gray-200 dark:border-gray-700 px-6">
				<nav className="-mb-px flex space-x-8" aria-label="Tabs">
					{tabs.map((tab) => (
						<button
							key={tab.id}
							type="button"
							className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm ${activeTab === tab.id
									? 'border-primary text-primary'
									: 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
								}`}
							onClick={() => setActiveTab(tab.id)}
						>
							{tab.label}
						</button>
					))}
				</nav>
			</div>

			{ /* Form */}
			<form
				className="p-6 space-y-6"
				onSubmit={(e) => {
					e.preventDefault();
					handleSave();
				}}
			>
				{ /* Basic Info Tab */}
				{activeTab === 'basic' && (
					<div className="space-y-6">
						<div>
							<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
								{__('Display Name', 'smart-woo-chatbot')} <span className="text-red-500">*</span>
							</label>
							<input
								type="text"
								className={`block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary focus:ring-primary sm:text-sm ${errors.display_name ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500' : ''
									}`}
								value={formData.display_name}
								onChange={(e) => updateField('display_name', e.target.value)}
								placeholder={__('e.g., Refund Handling', 'smart-woo-chatbot')}
							/>
							<p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
								{__('A friendly name shown in the admin panel', 'smart-woo-chatbot')}
							</p>
						</div>

						<div>
							<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
								{__('Skill ID', 'smart-woo-chatbot')} <span className="text-red-500">*</span>
							</label>
							<input
								type="text"
								className={`block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 shadow-sm sm:text-sm ${errors.name ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' : ''
									}`}
								value={formData.name}
								onChange={(e) => updateField('name', e.target.value)}
								placeholder={__('refund-handling', 'smart-woo-chatbot')}
								disabled={!isNew}
							/>
							{errors.name && (
								<p className="mt-2 text-sm text-red-600">{errors.name}</p>
							)}
							<p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
								{isNew
									? __('Auto-generated from display name. Cannot be changed later.', 'smart-woo-chatbot')
									: __('Skill ID cannot be changed after creation.', 'smart-woo-chatbot')}
							</p>
						</div>

						<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
									{__('Category', 'smart-woo-chatbot')} <span className="text-red-500">*</span>
								</label>
								<select
									className="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
									value={formData.category}
									onChange={(e) => updateField('category', e.target.value)}
								>
									{CATEGORIES.map((cat) => (
										<option key={cat.id} value={cat.id}>{cat.label}</option>
									))}
								</select>
							</div>

							<div>
								<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
									{__('Group', 'smart-woo-chatbot')}
								</label>
								<select
									className="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
									value={formData.group || 'ungrouped'}
									onChange={(e) => updateField('group', e.target.value)}
								>
									<option value="ungrouped">{__('Ungrouped', 'smart-woo-chatbot')}</option>
									{groups.map((grp) => (
										<option key={grp.id} value={grp.id}>{grp.name}</option>
									))}
								</select>
								<p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
									{__('Organize related skills together in the tree view.', 'smart-woo-chatbot')}
								</p>
							</div>
						</div>

						<div>
							<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
								{__('Description', 'smart-woo-chatbot')} <span className="text-red-500">*</span>
							</label>
							<textarea
								className={`block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary focus:ring-primary sm:text-sm ${errors.description ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500' : ''
									}`}
								value={formData.description}
								onChange={(e) => updateField('description', e.target.value)}
								placeholder={__('When should the AI agent use this skill? Be concise.', 'smart-woo-chatbot')}
								rows={3}
								maxLength={1024}
							/>
							{errors.description && (
								<p className="mt-2 text-sm text-red-600">{errors.description}</p>
							)}
							<p className="mt-2 text-sm text-gray-500 dark:text-gray-400 flex justify-between">
								<span>{__('This helps the AI know when to load this skill.', 'smart-woo-chatbot')}</span>
								<span>{formData.description.length}/1024</span>
							</p>
						</div>

						<div className="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700 relative flex items-start">
							<div className="flex items-center h-5">
								<input
									id="always_on"
									type="checkbox"
									className="focus:ring-primary h-4 w-4 text-primary border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
									checked={formData.always_on}
									onChange={(e) => updateField('always_on', e.target.checked)}
								/>
							</div>
							<div className="ml-3 text-sm">
								<label htmlFor="always_on" className="font-medium text-gray-700 dark:text-gray-300">
									{__('Always On', 'smart-woo-chatbot')}
								</label>
								<p className="text-gray-500 dark:text-gray-400">
									{__('Include this skill in every conversation (uses more tokens)', 'smart-woo-chatbot')}
								</p>
							</div>
						</div>
					</div>
				)}

				{ /* Instructions Tab */}
				{activeTab === 'instructions' && (
					<div className="space-y-6">
						<div className="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
							<p className="text-sm text-blue-800 dark:text-blue-300">
								{__('Add step-by-step instructions for the AI to follow when this skill is loaded.', 'smart-woo-chatbot')}
							</p>
						</div>

						{errors.instructions && (
							<div className="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
								<p className="text-sm text-red-800 dark:text-red-300">{errors.instructions}</p>
							</div>
						)}

						<InstructionEditor
							sections={formData.instructions}
							onChange={(sections) => updateField('instructions', sections)}
						/>
					</div>
				)}

				{ /* Tools Tab */}
				{activeTab === 'tools' && (
					<div className="space-y-6">
						<div className="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
							<p className="text-sm text-blue-800 dark:text-blue-300">
								{__('Select which tools this skill requires. This helps with documentation and validation.', 'smart-woo-chatbot')}
							</p>
						</div>

						<ToolSelector
							selected={formData.tools_required}
							onChange={(tools) => updateField('tools_required', tools)}
						/>
					</div>
				)}

				{ /* References Tab */}
				{activeTab === 'references' && (
					<div className="space-y-6">
						<div className="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
							<p className="text-sm text-blue-800 dark:text-blue-300">
								{__('Add reference documents with detailed policies or procedures that the AI can load when needed.', 'smart-woo-chatbot')}
							</p>
						</div>

						<ReferenceManager
							references={formData.references}
							onChange={(refs) => updateField('references', refs)}
							skillId={skill?.name}
						/>
					</div>
				)}

				{ /* Form Actions */}
				<div className="flex items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
					<button
						type="button"
						className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
						onClick={onCancel}
						disabled={saving}
					>
						{__('Cancel', 'smart-woo-chatbot')}
					</button>
					<button
						type="submit"
						className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
						disabled={saving}
					>
						{saving ? (
							<>
								<svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
									<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								{__('Saving…', 'smart-woo-chatbot')}
							</>
						) : isNew ? (
							__('Create Skill', 'smart-woo-chatbot')
						) : (
							__('Save Changes', 'smart-woo-chatbot')
						)}
					</button>
				</div>
			</form>
		</div>
	);
}

SkillEditor.propTypes = {
	skill: PropTypes.object,
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
	isNew: PropTypes.bool,
	groups: PropTypes.array,
};
