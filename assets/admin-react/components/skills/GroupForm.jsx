/**
 * GroupForm Component
 *
 * Form for creating and editing skill groups.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

const PRESET_COLORS = [
	'#6366f1', // Indigo
	'#ef4444', // Red
	'#22c55e', // Green
	'#eab308', // Yellow
	'#3b82f6', // Blue
	'#ec4899', // Pink
	'#8b5cf6', // Violet
	'#64748b', // Slate
];

const PRESET_ICONS = [
	'F', // Folder
	'C', // Commerce
	'M', // Message
	'S', // Settings
	'D', // Document
	'B', // Brain
	'L', // Lab
	'T', // Tool
	'P', // Package
	'R', // Rocket
];

export default function GroupForm({ group, onSave, onCancel }) {
	const isNew = !group;

	// Initial state
	const [formData, setFormData] = useState({
		name: '',
		id: '',
		description: '',
		icon: 'F',
		color: '#6366f1',
		order: 0,
		...group,
	});

	const [errors, setErrors] = useState({});
	const [saving, setSaving] = useState(false);

	// Auto-generate ID from name for new groups
	useEffect(() => {
		if (isNew && formData.name && !group?.id) {
			const id = formData.name
				.toLowerCase()
				.replace(/[^a-z0-9\s-]/g, '')
				.replace(/[\s_]+/g, '-')
				.trim();
			setFormData((prev) => ({ ...prev, id }));
		}
	}, [formData.name, isNew, group]);

	const updateField = (field, value) => {
		setFormData((prev) => ({ ...prev, [field]: value }));
		if (errors[field]) {
			setErrors((prev) => ({ ...prev, [field]: null }));
		}
	};

	const validate = () => {
		const newErrors = {};
		if (!formData.name.trim()) {
			newErrors.name = __(
				'Group name is required',
				'agentflow-ai'
			);
		}
		if (!formData.id.trim()) {
			newErrors.id = __('Group ID is required', 'agentflow-ai');
		}

		setErrors(newErrors);
		return Object.keys(newErrors).length === 0;
	};

	const handleSubmit = async (e) => {
		e.preventDefault();

		if (!validate()) {
			return;
		}

		setSaving(true);
		try {
			await onSave(formData);
		} catch (err) {
			setErrors({ submit: err.message });
		} finally {
			setSaving(false);
		}
	};

	return (
		<form className="flex flex-col gap-6 bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm" onSubmit={handleSubmit}>
			<div className="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
				<h3 className="text-xl font-bold text-gray-900 dark:text-white m-0">
					{isNew
						? __('Create Group', 'agentflow-ai')
						: __('Edit Group', 'agentflow-ai')}
				</h3>
			</div>

			{errors.submit && (
				<div className="p-4 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/30 dark:text-red-300 dark:border-red-800 flex items-start gap-2">
					<svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					{errors.submit}
				</div>
			)}

			<div>
				<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5 flex items-center gap-1">
					{__('Group Name', 'agentflow-ai')}
					<span className="text-red-500" title="Required">*</span>
				</label>
				<input
					type="text"
					className={`w-full px-4 py-2.5 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary text-sm dark:bg-gray-800 dark:text-white transition-colors ${errors.name ? 'border-red-500 focus:ring-red-500/50' : 'border-gray-300 dark:border-gray-600'
						}`}
					value={formData.name}
					onChange={(e) => updateField('name', e.target.value)}
					placeholder={__('e.g., Sales Skills', 'agentflow-ai')}
				/>
				{errors.name && (
					<p className="mt-1.5 text-xs font-medium text-red-500">{errors.name}</p>
				)}
			</div>

			<div>
				<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5 flex items-center gap-1">
					{__('Group ID', 'agentflow-ai')}
					<span className="text-red-500" title="Required">*</span>
				</label>
				<input
					type="text"
					className={`w-full px-4 py-2.5 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary text-sm dark:bg-gray-800 dark:text-white transition-colors disabled:opacity-60 disabled:bg-gray-50 dark:disabled:bg-gray-900 disabled:cursor-not-allowed ${errors.id ? 'border-red-500 focus:ring-red-500/50' : 'border-gray-300 dark:border-gray-600'
						}`}
					value={formData.id}
					onChange={(e) => updateField('id', e.target.value)}
					disabled={!isNew}
					placeholder="sales-skills"
				/>
				<p className="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
					{isNew
						? __('Auto-generated unique identifier', 'agentflow-ai')
						: __('Cannot be changed', 'agentflow-ai')}
				</p>
			</div>

			<div>
				<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
					{__('Description', 'agentflow-ai')}
				</label>
				<textarea
					className="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary text-sm dark:bg-gray-800 dark:text-white transition-colors resize-y"
					value={formData.description}
					onChange={(e) => updateField('description', e.target.value)}
					rows={3}
					placeholder={__('What kind of skills does this group contain?', 'agentflow-ai')}
				/>
			</div>

			<div className="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50/50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
				<div>
					<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
						{__('Icon', 'agentflow-ai')}
					</label>
					<div className="flex flex-wrap gap-2">
						{PRESET_ICONS.map((icon) => (
							<button
								key={icon}
								type="button"
								className={`w-10 h-10 flex items-center justify-center rounded-lg text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary border ${formData.icon === icon
										? 'bg-primary text-white border-primary shadow-md scale-105'
										: 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-gray-300'
									}`}
								onClick={() => updateField('icon', icon)}
							>
								{icon}
							</button>
						))}
					</div>
				</div>

				<div>
					<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
						{__('Color', 'agentflow-ai')}
					</label>
					<div className="flex flex-wrap gap-2">
						{PRESET_COLORS.map((color) => (
							<button
								key={color}
								type="button"
								className={`w-10 h-10 rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary ${formData.color === color
										? 'ring-2 ring-offset-2 ring-primary shadow-md scale-110'
										: 'opacity-80 hover:opacity-100 hover:scale-105'
									}`}
								style={{ backgroundColor: color }}
								onClick={() => updateField('color', color)}
								aria-label={color}
								title={color}
							/>
						))}
					</div>
				</div>
			</div>

			<div>
				<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
					{__('Order', 'agentflow-ai')}
				</label>
				<input
					type="number"
					className="w-full max-w-[150px] px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary text-sm dark:bg-gray-800 dark:text-white transition-colors"
					value={formData.order}
					onChange={(e) => updateField('order', parseInt(e.target.value) || 0)}
					step={1}
				/>
				<p className="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
					{__('Lower numbers appear first', 'agentflow-ai')}
				</p>
			</div>

			<div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 mt-2">
				<button
					type="button"
					className="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
					onClick={onCancel}
					disabled={saving}
				>
					{__('Cancel', 'agentflow-ai')}
				</button>
				<button
					type="submit"
					className="inline-flex items-center px-6 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
					disabled={saving}
				>
					{saving && (
						<svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
							<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
							<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
						</svg>
					)}
					{saving
						? __('Saving…', 'agentflow-ai')
						: isNew
							? __('Create Group', 'agentflow-ai')
							: __('Update Group', 'agentflow-ai')}
				</button>
			</div>
		</form>
	);
}

GroupForm.propTypes = {
	group: PropTypes.object,
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
};
