/**
 * WorkflowStepCard Component
 *
 * Editor for an individual workflow step.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

export default function WorkflowStepCard({ step, index, agents, onChange, onRemove }) {
	const handleFieldChange = (field, value) => {
		onChange({ ...step, [field]: value });
	};

	const handleConditionChange = (field, value) => {
		onChange({
			...step,
			condition: { ...step.condition, [field]: value },
		});
	};

	return (
		<div className="border border-gray-200 rounded-xl p-4 bg-white shadow-sm">
			<div className="flex items-center justify-between">
				<div>
					<p className="text-sm font-semibold text-gray-900">
						{__('Step', 'smart-woo-chatbot')} {index + 1}
					</p>
					<p className="text-xs text-gray-500 capitalize">{step.type || 'agent'}</p>
				</div>
				<button
					type="button"
					onClick={onRemove}
					className="text-xs text-red-600 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded"
				>
					{__('Remove', 'smart-woo-chatbot')}
				</button>
			</div>

			<div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
				<div>
					<label className="block text-sm font-medium text-gray-700 mb-1.5">
						{__('Step Type', 'smart-woo-chatbot')}
					</label>
					<select
						value={step.type}
						onChange={(e) => handleFieldChange('type', e.target.value)}
						className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white"
					>
						<option value="agent">{__('Agent Step', 'smart-woo-chatbot')}</option>
						<option value="approval">{__('Human Approval', 'smart-woo-chatbot')}</option>
						<option value="condition">{__('Condition', 'smart-woo-chatbot')}</option>
					</select>
				</div>

				<div>
					<label className="block text-sm font-medium text-gray-700 mb-1.5">
						{__('Step Name', 'smart-woo-chatbot')}
					</label>
					<input
						type="text"
						value={step.name || ''}
						onChange={(e) => handleFieldChange('name', e.target.value)}
						className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white"
						placeholder={__('Optional label', 'smart-woo-chatbot')}
					/>
				</div>
			</div>

			{step.type === 'agent' && (
				<div className="mt-4 space-y-4">
					<div>
						<label className="block text-sm font-medium text-gray-700 mb-1.5">
							{__('Agent', 'smart-woo-chatbot')}
						</label>
						<select
							value={step.agent_id || ''}
							onChange={(e) => handleFieldChange('agent_id', e.target.value)}
							className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white"
						>
							<option value="">{__('Select agent…', 'smart-woo-chatbot')}</option>
							{agents.map((agent) => (
								<option key={agent.agent_id || agent.id} value={agent.agent_id || agent.id}>
									{agent.name || agent.agent_id}
								</option>
							))}
						</select>
					</div>
					<div>
						<label className="block text-sm font-medium text-gray-700 mb-1.5">
							{__('Agent Input', 'smart-woo-chatbot')}
						</label>
						<textarea
							value={step.input || ''}
							onChange={(e) => handleFieldChange('input', e.target.value)}
							rows={3}
							className="w-full px-4 py-3 text-sm rounded-lg border border-gray-300 bg-white"
							placeholder={__('Describe what this step should do', 'smart-woo-chatbot')}
						/>
					</div>
				</div>
			)}

			{step.type === 'approval' && (
				<div className="mt-4">
					<label className="block text-sm font-medium text-gray-700 mb-1.5">
						{__('Approval Prompt', 'smart-woo-chatbot')}
					</label>
					<textarea
						value={step.prompt || ''}
						onChange={(e) => handleFieldChange('prompt', e.target.value)}
						rows={3}
						className="w-full px-4 py-3 text-sm rounded-lg border border-gray-300 bg-white"
						placeholder={__('Ask for approval or feedback', 'smart-woo-chatbot')}
					/>
				</div>
			)}

			{step.type === 'condition' && (
				<div className="mt-4 space-y-4">
					<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div>
							<label className="block text-sm font-medium text-gray-700 mb-1.5">
								{__('Operator', 'smart-woo-chatbot')}
							</label>
							<select
								value={step.condition?.operator || 'contains'}
								onChange={(e) => handleConditionChange('operator', e.target.value)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white"
							>
								<option value="contains">{__('Contains', 'smart-woo-chatbot')}</option>
								<option value="equals">{__('Equals', 'smart-woo-chatbot')}</option>
								<option value="not_contains">{__('Does not contain', 'smart-woo-chatbot')}</option>
							</select>
						</div>
						<div>
							<label className="block text-sm font-medium text-gray-700 mb-1.5">
								{__('Value', 'smart-woo-chatbot')}
							</label>
							<input
								type="text"
								value={step.condition?.value || ''}
								onChange={(e) => handleConditionChange('value', e.target.value)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white"
								placeholder={__('Text to match', 'smart-woo-chatbot')}
							/>
						</div>
					</div>
					<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div>
							<label className="block text-sm font-medium text-gray-700 mb-1.5">
								{__('If True, Jump to Step', 'smart-woo-chatbot')}
							</label>
							<input
								type="number"
								min="1"
								value={step.condition?.if_true_step || ''}
								onChange={(e) => handleConditionChange('if_true_step', e.target.value)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white"
								placeholder={__('Optional', 'smart-woo-chatbot')}
							/>
						</div>
						<div>
							<label className="block text-sm font-medium text-gray-700 mb-1.5">
								{__('If False, Jump to Step', 'smart-woo-chatbot')}
							</label>
							<input
								type="number"
								min="1"
								value={step.condition?.if_false_step || ''}
								onChange={(e) => handleConditionChange('if_false_step', e.target.value)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white"
								placeholder={__('Optional', 'smart-woo-chatbot')}
							/>
						</div>
					</div>
				</div>
			)}
		</div>
	);
}

WorkflowStepCard.propTypes = {
	step: PropTypes.object.isRequired,
	index: PropTypes.number.isRequired,
	agents: PropTypes.array,
	onChange: PropTypes.func.isRequired,
	onRemove: PropTypes.func.isRequired,
};
