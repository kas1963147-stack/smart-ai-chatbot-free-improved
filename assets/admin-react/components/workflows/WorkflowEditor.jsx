/**
 * WorkflowEditor Component
 *
 * Form to create/edit workflows and steps.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import WorkflowStepCard from './WorkflowStepCard';

export default function WorkflowEditor({ workflow, agents, onSave, onCancel }) {
	const [formData, setFormData] = useState({
		name: '',
		slug: '',
		description: '',
		trigger_type: 'manual',
		schedule_expression: '',
		steps: [],
		is_active: true,
	});
	const [errors, setErrors] = useState({});

	useEffect(() => {
		if (workflow) {
			setFormData({
				name: workflow.name || '',
				slug: workflow.slug || '',
				description: workflow.description || '',
				trigger_type: workflow.trigger_type || 'manual',
				schedule_expression: workflow.schedule_expression || '',
				steps: workflow.steps || [],
				is_active: workflow.is_active !== undefined ? !!workflow.is_active : true,
			});
		}
	}, [workflow]);

	const handleChange = (field, value) => {
		setFormData((prev) => ({ ...prev, [field]: value }));
		setErrors((prev) => ({ ...prev, [field]: null }));
	};

	const handleStepChange = (index, updatedStep) => {
		const nextSteps = [...formData.steps];
		nextSteps[index] = updatedStep;
		setFormData((prev) => ({ ...prev, steps: nextSteps }));
	};

	const addStep = () => {
		setFormData((prev) => ({
			...prev,
			steps: [
				...prev.steps,
				{ type: 'agent', name: '', agent_id: '', input: '' },
			],
		}));
	};

	const removeStep = (index) => {
		setFormData((prev) => ({
			...prev,
			steps: prev.steps.filter((_, idx) => idx !== index),
		}));
	};

	const validate = () => {
		const nextErrors = {};
		if (!formData.name.trim()) {
			nextErrors.name = __('Workflow name is required', 'smart-woo-chatbot');
		}
		if (formData.trigger_type === 'scheduled' && !formData.schedule_expression.trim()) {
			nextErrors.schedule_expression = __('Cron expression is required', 'smart-woo-chatbot');
		}
		setErrors(nextErrors);
		return Object.keys(nextErrors).length === 0;
	};

	const handleSubmit = (event) => {
		event.preventDefault();
		if (!validate()) return;
		onSave(formData);
	};

	return (
		<form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 shadow-sm">
			<div className="p-6 space-y-6">
				<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
					<div>
						<label className="block text-sm font-medium text-gray-900 mb-1.5">
							{__('Workflow Name', 'smart-woo-chatbot')} <span className="text-red-500">*</span>
						</label>
						<input
							type="text"
							value={formData.name}
							onChange={(e) => handleChange('name', e.target.value)}
							className={`w-full h-10 px-4 text-sm rounded-lg border bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all ${errors.name ? 'border-red-300' : 'border-gray-300'}`}
						/>
						{errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
					</div>

					<div>
						<label className="block text-sm font-medium text-gray-900 mb-1.5">
							{__('Slug', 'smart-woo-chatbot')}
						</label>
						<input
							type="text"
							value={formData.slug}
							onChange={(e) => handleChange('slug', e.target.value)}
							className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
							placeholder={__('Auto-generated if empty', 'smart-woo-chatbot')}
						/>
					</div>
				</div>

				<div>
					<label className="block text-sm font-medium text-gray-900 mb-1.5">
						{__('Description', 'smart-woo-chatbot')}
					</label>
					<textarea
						value={formData.description}
						onChange={(e) => handleChange('description', e.target.value)}
						rows={3}
						className="w-full px-4 py-3 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none"
					/>
				</div>

				<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
					<div>
						<label className="block text-sm font-medium text-gray-900 mb-1.5">
							{__('Trigger Type', 'smart-woo-chatbot')}
						</label>
						<select
							value={formData.trigger_type}
							onChange={(e) => handleChange('trigger_type', e.target.value)}
							className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white text-gray-900"
						>
							<option value="manual">{__('Manual', 'smart-woo-chatbot')}</option>
							<option value="scheduled">{__('Scheduled', 'smart-woo-chatbot')}</option>
							<option value="event">{__('Event', 'smart-woo-chatbot')}</option>
						</select>
					</div>

					{formData.trigger_type === 'scheduled' && (
						<div>
							<label className="block text-sm font-medium text-gray-900 mb-1.5">
								{__('Cron Expression', 'smart-woo-chatbot')}
							</label>
							<input
								type="text"
								value={formData.schedule_expression}
								onChange={(e) => handleChange('schedule_expression', e.target.value)}
								className={`w-full h-10 px-4 text-sm rounded-lg border bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all ${errors.schedule_expression ? 'border-red-300' : 'border-gray-300'}`}
								placeholder={__('0 9 * * 1', 'smart-woo-chatbot')}
							/>
							{errors.schedule_expression && <p className="text-sm text-red-600 mt-1">{errors.schedule_expression}</p>}
						</div>
					)}
				</div>

				<div className="flex items-center gap-3">
					<input
						id="workflow-active"
						type="checkbox"
						checked={formData.is_active}
						onChange={(e) => handleChange('is_active', e.target.checked)}
						className="h-4 w-4 text-primary border-gray-300 rounded"
					/>
					<label htmlFor="workflow-active" className="text-sm font-medium text-gray-700">
						{__('Workflow is active', 'smart-woo-chatbot')}
					</label>
				</div>

				<div className="space-y-4">
					<div className="flex items-center justify-between">
						<h3 className="text-base font-semibold text-gray-900">
							{__('Workflow Steps', 'smart-woo-chatbot')}
						</h3>
						<button
							type="button"
							onClick={addStep}
							className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-primary text-white hover:bg-primary/90"
						>
							+ {__('Add Step', 'smart-woo-chatbot')}
						</button>
					</div>

					{formData.steps.length === 0 ? (
						<div className="text-sm text-gray-500">
							{__('Add steps to define the workflow sequence.', 'smart-woo-chatbot')}
						</div>
					) : (
						<div className="space-y-4">
							{formData.steps.map((step, index) => (
								<WorkflowStepCard
									key={index}
									step={step}
									index={index}
									agents={agents}
									onChange={(updatedStep) => handleStepChange(index, updatedStep)}
									onRemove={() => removeStep(index)}
								/>
							))}
						</div>
					)}
				</div>
			</div>

			<div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
				<button
					type="button"
					onClick={onCancel}
					className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-colors"
				>
					{__('Cancel', 'smart-woo-chatbot')}
				</button>
				<button
					type="submit"
					className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary/90 shadow-sm transition-colors"
				>
					{workflow ? __('Update Workflow', 'smart-woo-chatbot') : __('Create Workflow', 'smart-woo-chatbot')}
				</button>
			</div>
		</form>
	);
}

WorkflowEditor.propTypes = {
	workflow: PropTypes.object,
	agents: PropTypes.array,
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
};
