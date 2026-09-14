/**
 * Task Editor Component - Metronic v9 Style
 *
 * Form for creating/editing scheduled tasks.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

export default function TaskEditor({
	task,
	scheduleOptions,
	agents = [],
	onSave,
	onCancel,
	isNew,
}) {
	const [formData, setFormData] = useState({
		name: '',
		description: '',
		agent_id: 'general_assistant',
		task_type: 'custom',
		schedule_type: 'once',
		schedule_config: {},
		task_config: {},
		max_retries: 3,
		timeout_seconds: 1800,
	});

	const [errors, setErrors] = useState({});
	const [activeSection, setActiveSection] = useState('basic');

	useEffect(() => {
		if (task) {
			setFormData({
				name: task.name || '',
				description: task.description || '',
				agent_id: task.agent_id || 'general_assistant',
				task_type: task.task_type || 'custom',
				schedule_type: task.schedule_type || 'once',
				schedule_config: task.schedule_config || {},
				task_config: task.task_config || {},
				max_retries: task.max_retries || 3,
				timeout_seconds: task.timeout_seconds || 1800,
			});
		}
	}, [task]);

	const handleChange = (field, value) => {
		setFormData((prev) => ({ ...prev, [field]: value }));
		setErrors((prev) => ({ ...prev, [field]: null }));
	};

	const handleScheduleConfigChange = (field, value) => {
		setFormData((prev) => ({
			...prev,
			schedule_config: { ...prev.schedule_config, [field]: value },
		}));
	};

	const handleTaskConfigChange = (field, value) => {
		setFormData((prev) => ({
			...prev,
			task_config: { ...prev.task_config, [field]: value },
		}));
	};

	const validate = () => {
		const newErrors = {};
		if (!formData.name.trim()) {
			newErrors.name = __('Name is required', 'agentflow-ai');
		}
		if (!formData.agent_id) {
			newErrors.agent_id = __('Agent is required', 'agentflow-ai');
		}
		if (formData.schedule_type === 'cron' && !formData.schedule_config.expression) {
			newErrors.cron = __('Cron expression is required', 'agentflow-ai');
		}
		setErrors(newErrors);
		return Object.keys(newErrors).length === 0;
	};

	const handleSubmit = (e) => {
		e.preventDefault();
		if (validate()) {
			onSave(formData);
		}
	};

	// Fallback defaults in case API doesn't return options
	const defaultTaskTypes = {
		content_generation: __('Content Generation', 'agentflow-ai'),
		product_management: __('Product Management', 'agentflow-ai'),
		research: __('Research & Analysis', 'agentflow-ai'),
		analytics: __('Analytics Report', 'agentflow-ai'),
		sync: __('Data Sync', 'agentflow-ai'),
		custom: __('Custom Task', 'agentflow-ai'),
	};

	const defaultScheduleTypes = {
		once: __('Run Once', 'agentflow-ai'),
		recurring: __('Recurring', 'agentflow-ai'),
		cron: __('Cron Expression', 'agentflow-ai'),
	};

	const defaultIntervals = {
		hourly: __('Hourly', 'agentflow-ai'),
		daily: __('Daily', 'agentflow-ai'),
		weekly: __('Weekly', 'agentflow-ai'),
		monthly: __('Monthly', 'agentflow-ai'),
	};

	const defaultWeekdays = {
		monday: __('Monday', 'agentflow-ai'),
		tuesday: __('Tuesday', 'agentflow-ai'),
		wednesday: __('Wednesday', 'agentflow-ai'),
		thursday: __('Thursday', 'agentflow-ai'),
		friday: __('Friday', 'agentflow-ai'),
		saturday: __('Saturday', 'agentflow-ai'),
		sunday: __('Sunday', 'agentflow-ai'),
	};

	const taskTypes = Object.keys(scheduleOptions?.task_types || {}).length > 0
		? scheduleOptions.task_types
		: defaultTaskTypes;
	const scheduleTypes = Object.keys(scheduleOptions?.schedule_types || {}).length > 0
		? scheduleOptions.schedule_types
		: defaultScheduleTypes;
	const intervals = Object.keys(scheduleOptions?.intervals || {}).length > 0
		? scheduleOptions.intervals
		: defaultIntervals;
	const weekdays = Object.keys(scheduleOptions?.weekdays || {}).length > 0
		? scheduleOptions.weekdays
		: defaultWeekdays;

	const sections = [
		{ id: 'basic', label: __('Basic Info', 'agentflow-ai'), icon: '' },
		{ id: 'schedule', label: __('Schedule', 'agentflow-ai'), icon: '' },
		{ id: 'config', label: __('Task Config', 'agentflow-ai'), icon: '' },
		{ id: 'advanced', label: __('Advanced', 'agentflow-ai'), icon: '' },
	];

	return (
		<form onSubmit={handleSubmit}>
			{/* Section Tabs */}
			<div className="flex border-b border-gray-100 dark:border-gray-700">
				{sections.map((section) => (
					<button
						key={section.id}
						type="button"
						onClick={() => setActiveSection(section.id)}
						className={`px-5 py-3 text-sm font-medium transition-colors ${activeSection === section.id
							? 'text-primary border-b-2 border-primary -mb-px'
							: 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
							}`}
					>
						<span className="mr-1.5">{section.icon}</span>
						{section.label}
					</button>
				))}
			</div>

			<div className="p-6">
				{/* Basic Information */}
				{activeSection === 'basic' && (
					<div className="space-y-6">
						<div>
							<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
								{__('Task Name', 'agentflow-ai')} <span className="text-red-500">*</span>
							</label>
							<input
								type="text"
								value={formData.name}
								onChange={(e) => handleChange('name', e.target.value)}
								className={`w-full h-10 px-4 text-sm rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all ${errors.name ? 'border-red-300' : 'border-gray-300 dark:border-gray-600'
									}`}
							/>
							{errors.name && <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.name}</p>}
						</div>

						<div>
							<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
								{__('Description', 'agentflow-ai')}
							</label>
							<textarea
								value={formData.description}
								onChange={(e) => handleChange('description', e.target.value)}
								rows={3}
								className="w-full px-4 py-3 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none"
							/>
						</div>

						<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
									{__('Task Type', 'agentflow-ai')}
								</label>
								<select
									value={formData.task_type}
									onChange={(e) => handleChange('task_type', e.target.value)}
									className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
								>
									{Object.entries(taskTypes).map(([value, label]) => (
										<option key={value} value={value}>{label}</option>
									))}
								</select>
							</div>

							<div>
								<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
									{__('Agent', 'agentflow-ai')} <span className="text-red-500">*</span>
								</label>
								<select
									value={formData.agent_id}
									onChange={(e) => handleChange('agent_id', e.target.value)}
									className={`w-full h-10 px-4 text-sm rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all ${errors.agent_id ? 'border-red-300' : 'border-gray-300 dark:border-gray-600'
										}`}
								>
									<option value="">{__('Select an agent...', 'agentflow-ai')}</option>
									{agents.map((agent) => (
										<option
											key={agent.agent_id || agent.id}
											value={agent.agent_id || agent.id?.toString()}
										>
											{agent.name || agent.agent_id || `Agent ${agent.id}`}
										</option>
									))}
								</select>
								<p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{__('The agent that will execute this task', 'agentflow-ai')}</p>
							</div>
						</div>
					</div>
				)}

				{/* Schedule */}
				{activeSection === 'schedule' && (
					<div className="space-y-6">
						<div>
							<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
								{__('Schedule Type', 'agentflow-ai')}
							</label>
							<select
								value={formData.schedule_type}
								onChange={(e) => handleChange('schedule_type', e.target.value)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
							>
								{Object.entries(scheduleTypes).map(([value, label]) => (
									<option key={value} value={value}>{label}</option>
								))}
							</select>
						</div>

						{formData.schedule_type === 'recurring' && (
							<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
								<div>
									<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
										{__('Interval', 'agentflow-ai')}
									</label>
									<select
										value={formData.schedule_config.interval || 'daily'}
										onChange={(e) => handleScheduleConfigChange('interval', e.target.value)}
										className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
									>
										{Object.entries(intervals).map(([value, label]) => (
											<option key={value} value={value}>{label}</option>
										))}
									</select>
								</div>

								<div>
									<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
										{__('Time (HH:MM)', 'agentflow-ai')}
									</label>
									<input
										type="time"
										value={formData.schedule_config.time || '09:00'}
										onChange={(e) => handleScheduleConfigChange('time', e.target.value)}
										className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
									/>
								</div>

								{formData.schedule_config.interval === 'weekly' && (
									<div>
										<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
											{__('Day of Week', 'agentflow-ai')}
										</label>
										<select
											value={formData.schedule_config.day || 'monday'}
											onChange={(e) => handleScheduleConfigChange('day', e.target.value)}
											className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
										>
											{Object.entries(weekdays).map(([value, label]) => (
												<option key={value} value={value}>{label}</option>
											))}
										</select>
									</div>
								)}

								{formData.schedule_config.interval === 'monthly' && (
									<div>
										<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
											{__('Day of Month', 'agentflow-ai')}
										</label>
										<input
											type="number"
											value={formData.schedule_config.day_of_month || 1}
											onChange={(e) => handleScheduleConfigChange('day_of_month', parseInt(e.target.value))}
											min={1}
											max={28}
											className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
										/>
									</div>
								)}
							</div>
						)}

						{formData.schedule_type === 'cron' && (
							<div>
								<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
									{__('Cron Expression', 'agentflow-ai')}
								</label>
								<input
									type="text"
									value={formData.schedule_config.expression || ''}
									onChange={(e) => handleScheduleConfigChange('expression', e.target.value)}
									placeholder="0 9 * * *"
									className={`w-full h-10 px-4 text-sm rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all ${errors.cron ? 'border-red-300' : 'border-gray-300 dark:border-gray-600'
										}`}
								/>
								<p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{__('Standard 5-field cron format: minute hour day month weekday', 'agentflow-ai')}</p>
								{errors.cron && <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.cron}</p>}
							</div>
						)}
					</div>
				)}

				{/* Task Configuration */}
				{activeSection === 'config' && (
					<div className="space-y-6">
						{formData.task_type === 'content_generation' && (
							<>
								<div>
									<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
										{__('Topic', 'agentflow-ai')}
									</label>
									<input
										type="text"
										value={formData.task_config.topic || ''}
										onChange={(e) => handleTaskConfigChange('topic', e.target.value)}
										placeholder={__('e.g., WordPress Tips', 'agentflow-ai')}
										className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
									/>
								</div>

								<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div>
										<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
											{__('Word Count', 'agentflow-ai')}
										</label>
										<input
											type="number"
											value={formData.task_config.word_count || 1000}
											onChange={(e) => handleTaskConfigChange('word_count', parseInt(e.target.value))}
											min={100}
											max={5000}
											className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
										/>
									</div>

									<div>
										<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
											{__('Publish Status', 'agentflow-ai')}
										</label>
										<select
											value={formData.task_config.publish_status || 'draft'}
											onChange={(e) => handleTaskConfigChange('publish_status', e.target.value)}
											className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
										>
											<option value="draft">{__('Draft', 'agentflow-ai')}</option>
											<option value="publish">{__('Publish', 'agentflow-ai')}</option>
											<option value="pending">{__('Pending Review', 'agentflow-ai')}</option>
										</select>
									</div>
								</div>
							</>
						)}

						{formData.task_type === 'research' && (
							<>
								<div>
									<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
										{__('Research Topic', 'agentflow-ai')}
									</label>
									<input
										type="text"
										value={formData.task_config.topic || ''}
										onChange={(e) => handleTaskConfigChange('topic', e.target.value)}
										className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
									/>
								</div>

								<div>
									<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
										{__('Research Depth', 'agentflow-ai')}
									</label>
									<select
										value={formData.task_config.depth || 'comprehensive'}
										onChange={(e) => handleTaskConfigChange('depth', e.target.value)}
										className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
									>
										<option value="quick">{__('Quick Overview', 'agentflow-ai')}</option>
										<option value="comprehensive">{__('Comprehensive', 'agentflow-ai')}</option>
										<option value="deep">{__('Deep Dive', 'agentflow-ai')}</option>
									</select>
								</div>
							</>
						)}

						
{formData.task_type === 'product_management' && (
<>
<div className="bg-blue-50 dark:bg-blue-900/30 rounded-xl p-4 mb-4">
<p className="text-sm text-blue-800 dark:text-blue-300">
<strong>Product Management:</strong>{' '}
{__('Fill in the product details below. The AI will use the woo_product_manage tool to add this product to your store.', 'agentflow-ai')}
</p>
</div>

<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Product Name', 'agentflow-ai')} <span className="text-red-500">*</span>
</label>
<input
type="text"
value={formData.task_config.product_name || ''}
onChange={(e) => handleTaskConfigChange('product_name', e.target.value)}
placeholder={__('e.g., Premium Wireless Headphones', 'agentflow-ai')}
className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
/>
</div>

<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Regular Price', 'agentflow-ai')}
</label>
<input
type="text"
value={formData.task_config.regular_price || ''}
onChange={(e) => handleTaskConfigChange('regular_price', e.target.value)}
placeholder="99.99"
className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
/>
</div>
<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Sale Price', 'agentflow-ai')}
</label>
<input
type="text"
value={formData.task_config.sale_price || ''}
onChange={(e) => handleTaskConfigChange('sale_price', e.target.value)}
placeholder="79.99"
className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
/>
</div>
</div>

<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Category', 'agentflow-ai')}
</label>
<input
type="text"
value={formData.task_config.category || ''}
onChange={(e) => handleTaskConfigChange('category', e.target.value)}
placeholder={__('e.g., Electronics', 'agentflow-ai')}
className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
/>
</div>
<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('SKU', 'agentflow-ai')}
</label>
<input
type="text"
value={formData.task_config.sku || ''}
onChange={(e) => handleTaskConfigChange('sku', e.target.value)}
placeholder="PRD-001"
className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
/>
</div>
</div>

<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Stock Quantity', 'agentflow-ai')}
</label>
<input
type="number"
value={formData.task_config.stock_quantity || ''}
onChange={(e) => handleTaskConfigChange('stock_quantity', e.target.value)}
placeholder="100"
min="0"
className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
/>
</div>
<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Product Status', 'agentflow-ai')}
</label>
<select
value={formData.task_config.product_status || 'publish'}
onChange={(e) => handleTaskConfigChange('product_status', e.target.value)}
className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
>
<option value="publish">{__('Publish', 'agentflow-ai')}</option>
<option value="draft">{__('Draft', 'agentflow-ai')}</option>
<option value="pending">{__('Pending Review', 'agentflow-ai')}</option>
<option value="private">{__('Private', 'agentflow-ai')}</option>
</select>
</div>
</div>

<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Product Description', 'agentflow-ai')}
</label>
<textarea
value={formData.task_config.description || ''}
onChange={(e) => handleTaskConfigChange('description', e.target.value)}
rows={3}
placeholder={__('Describe the product or leave blank for AI to generate...', 'agentflow-ai')}
className="w-full px-4 py-3 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none"
/>
</div>

<div>
<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
{__('Additional Instructions', 'agentflow-ai')}
</label>
<textarea
value={formData.task_config.additional_instructions || ''}
onChange={(e) => handleTaskConfigChange('additional_instructions', e.target.value)}
rows={2}
placeholder={__('Any additional instructions for the AI...', 'agentflow-ai')}
className="w-full px-4 py-3 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none"
/>
</div>
</>
)}
						{formData.task_type === 'custom' && (
							<div>
								<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
									{__('Custom Prompt', 'agentflow-ai')}
								</label>
								<textarea
									value={formData.task_config.prompt || ''}
									onChange={(e) => handleTaskConfigChange('prompt', e.target.value)}
									rows={6}
									placeholder={__('Describe what the agent should do...€¦', 'agentflow-ai')}
									className="w-full px-4 py-3 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none"
								/>
							</div>
						)}

						{!['content_generation', 'research', 'custom', 'product_management'].includes(formData.task_type) && (
							<div className="text-center py-12 text-gray-500 dark:text-gray-400">
								<div className="text-4xl mb-3">ï¸</div>
								<p>{__('No additional configuration required for this task type.', 'agentflow-ai')}</p>
							</div>
						)}
					</div>
				)}

				{/* Advanced Settings */}
				{activeSection === 'advanced' && (
					<div className="space-y-6">
						<div className="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
									{__('Max Retries', 'agentflow-ai')}
								</label>
								<input
									type="number"
									value={formData.max_retries}
									onChange={(e) => handleChange('max_retries', parseInt(e.target.value))}
									min={0}
									max={10}
									className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
								/>
							</div>

							<div>
								<label className="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
									{__('Timeout (seconds)', 'agentflow-ai')}
								</label>
								<input
									type="number"
									value={formData.timeout_seconds}
									onChange={(e) => handleChange('timeout_seconds', parseInt(e.target.value))}
									min={60}
									max={7200}
									className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
								/>
							</div>
						</div>

						<div className="bg-blue-50 dark:bg-blue-900/30 rounded-xl p-4">
							<p className="text-sm text-blue-800 dark:text-blue-300">
								<strong> Tip:</strong>{' '}
								{__('Increase timeout for complex tasks that may take longer to complete.', 'agentflow-ai')}
							</p>
						</div>
					</div>
				)}
			</div>

			{/* Form Actions */}
			<div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 rounded-b-xl">
				<button
					type="button"
					onClick={onCancel}
					className="px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
				>
					{__('Cancel', 'agentflow-ai')}
				</button>
				<button
					type="submit"
					className="px-5 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
				>
					{isNew ? __('Create Task', 'agentflow-ai') : __('Save Changes', 'agentflow-ai')}
				</button>
			</div>
		</form>
	);
}

TaskEditor.propTypes = {
	task: PropTypes.object,
	scheduleOptions: PropTypes.object,
	agents: PropTypes.array,
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
	isNew: PropTypes.bool,
};
