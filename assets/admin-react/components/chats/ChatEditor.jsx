/**
 * ChatEditor Component - Metronic v9 Premium Design
 *
 * Form to configure a chat widget with rich appearance settings.
 * Supports both individual agents and teams (agent groups) for multi-agent orchestration.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import WidgetAssignmentManager from './WidgetAssignmentManager';
import DisplayRules from './DisplayRules';
import EngagementSettings from './EngagementSettings';
import PresetSelector from './PresetSelector';
import TeamSelector from './TeamSelector';
import { applyPreset } from './widget-presets';

// Rich appearance components
import {
	TemplateGallery,
	ThemeBuilder,
	ChatControlsManager,
	ChatPreview,
} from '../appearance';
import { TEMPLATES } from '../appearance/theme-templates';

const POSITION_OPTIONS = [
	{ value: 'left', label: __('Bottom Left', 'smart-woo-chatbot'), icon: <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6" /></svg> },
	{ value: 'right', label: __('Bottom Right', 'smart-woo-chatbot'), icon: <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6" /></svg> },
];

// Tab configuration with icons
const SECTION_CONFIG = [
	{ id: 'basic', label: __('Basic', 'smart-woo-chatbot'), icon: 'M11 5H6a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 013 3L12 15.5l-4 1 1-4L18.5 2.5z' },
	{ id: 'appearance', label: __('Appearance', 'smart-woo-chatbot'), icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828L15.314 13.5' },
	{ id: 'behavior', label: __('Behavior', 'smart-woo-chatbot'), icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
	{ id: 'display', label: __('Display', 'smart-woo-chatbot'), icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
	{ id: 'engagement', label: __('Engagement', 'smart-woo-chatbot'), icon: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z' },
	{ id: 'bubble', label: __('Bubble', 'smart-woo-chatbot'), icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
	{ id: 'controls', label: __('Controls', 'smart-woo-chatbot'), icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
];

// Default appearance state
const DEFAULT_APPEARANCE = {
	template: 'default',
	position: 'right',
	color_primary: '#6366f1',
	color_primary_hover: '#4f46e5',
	color_bg_main: '#ffffff',
	color_bg_light: '#f8fafc',
	color_text_primary: '#1e293b',
	color_text_secondary: '#64748b',
	color_border: '#e2e8f0',
	font_family: 'system',
	font_size_base: 14,
	line_height: 1.5,
	window_width: 380,
	window_height: 550,
	border_radius: 16,
	bubble_radius: 18,
	toggle_size: 60,
	// Presentation Mode
	presentation_mode: 'floating',
	embedded_target: '',
	sidebar_width: 400,
	// Greeting Bubble
	greeting_bubble_enabled: false,
	greeting_bubble_text: 'Need help? Chat with us!',
	greeting_bubble_delay: 5,
	greeting_bubble_dismissible: true,
	// Notification Badge
	notification_badge_enabled: true,
	notification_badge_color: '#ef4444',
	notification_sound_url: '',
	desktop_notification_enabled: false,
	// Entrance Animation
	entrance_animation: 'slide-up',
	// Mobile Overrides
	mobile_toggle_size: 52,
	mobile_fullscreen: false,
	// Toggle Button Customization
	toggle_shape: 'circle',
	toggle_label: '',
	toggle_label_position: 'right',
	toggle_icon: 'chat',
	toggle_custom_icon: '',
	toggle_shadow: 'medium',
	toggle_glow: false,
	toggle_pulse: false,
	toggle_border: false,
	toggle_border_color: '#ffffff',
};

// Icon component
const TabIcon = ({ path }) => (
	<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
		<path strokeLinecap="round" strokeLinejoin="round" d={path} />
	</svg>
);

// Form field components with Metronic styling
const FormField = ({ label, required, error, hint, children }) => (
	<div className="space-y-1.5">
		<label className="flex items-center gap-1 text-sm font-semibold text-slate-800">
			{label}
			{required && <span className="text-red-500">*</span>}
		</label>
		{children}
		{hint && !error && <p className="text-xs text-slate-500">{hint}</p>}
		{error && <p className="text-xs text-red-600 flex items-center gap-1">
			<svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
				<path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
			</svg>
			{error}
		</p>}
	</div>
);

const Input = ({ error, ...props }) => (
	<input
		{...props}
		className={`w-full h-11 px-4 text-sm rounded-xl border bg-white text-slate-900 
			placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 
			focus:border-primary transition-all duration-200
			${error ? 'border-red-300 focus:border-red-400 focus:ring-red-100' : 'border-slate-200 hover:border-slate-300'}`}
	/>
);

const Textarea = ({ error, ...props }) => (
	<textarea
		{...props}
		className={`w-full px-4 py-3 text-sm rounded-xl border bg-white text-slate-900 
			placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 
			focus:border-primary transition-all duration-200 resize-none
			${error ? 'border-red-300 focus:border-red-400 focus:ring-red-100' : 'border-slate-200 hover:border-slate-300'}`}
	/>
);

// Toggle Switch - Metronic style with better visibility
const Toggle = ({ checked, onChange, label, description }) => (
	<label className="flex items-start gap-3 cursor-pointer group">
		<div className="relative mt-0.5 flex-shrink-0">
			<input
				type="checkbox"
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
				className="sr-only peer"
			/>
			<div
				className={`w-12 h-7 rounded-full transition-all duration-200 border-2 ${checked
					? 'bg-primary border-primary'
					: 'bg-slate-300 border-slate-300'
					}`}
			>
				<div
					className={`absolute top-[3px] left-[3px] w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-200 ${checked ? 'translate-x-5' : 'translate-x-0'
						}`}
				/>
			</div>
		</div>
		{(label || description) && (
			<div className="flex-1 min-w-0">
				{label && <span className="text-sm font-medium text-slate-800 group-hover:text-slate-900">{label}</span>}
				{description && <p className="text-xs text-slate-500 mt-0.5">{description}</p>}
			</div>
		)}
	</label>
);

// Section Card
const SectionCard = ({ title, description, icon, children }) => (
	<div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
		{(title || description) && (
			<div className="flex items-start gap-3 px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
				{icon && (
					<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
						<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
							<path strokeLinecap="round" strokeLinejoin="round" d={icon} />
						</svg>
					</div>
				)}
				<div>
					{title && <h3 className="font-semibold text-slate-900">{title}</h3>}
					{description && <p className="text-sm text-slate-500 mt-0.5">{description}</p>}
				</div>
			</div>
		)}
		<div className="p-6">{children}</div>
	</div>
);

export default function ChatEditor({ widget, agents, onSave, onCancel }) {
	const [showPresetSelector, setShowPresetSelector] = useState(!widget?.id);
	const [activeTab, setActiveTab] = useState('basic');
	const [formData, setFormData] = useState({
		name: '',
		description: '',
		agent_id: '',
		is_active: true,
		appearance: { ...DEFAULT_APPEARANCE },
		behavior: {
			greeting_message: '',
			placeholder_text: '',
			returning_visitor_greeting: '',
			auto_open_enabled: false,
			auto_open_delay: 10,
			exit_intent_enabled: false,
			exit_intent_message: '',
			scroll_depth_enabled: false,
			scroll_depth_percent: 50,
			time_on_page_enabled: false,
			time_on_page_seconds: 30,
			cart_abandonment_enabled: false,
			cart_abandonment_message: '',
		},
		display: {
			include_urls: [],
			exclude_urls: [],
			logged_in_only: false,
			guest_only: false,
			devices: ['desktop', 'tablet', 'mobile'],
			schedule_enabled: false,
			schedule_start: '09:00',
			schedule_end: '17:00',
			schedule_days: [1, 2, 3, 4, 5],
		},
		engagement: {
			quick_replies_enabled: true,
			quick_replies: [],
			sound_enabled: false,
			tab_flash_enabled: false,
			browser_notifications_enabled: false,
			typing_indicator: true,
			auto_send_welcome: false,
			pass_user_context: false,
			session_persistence: true,
			rate_limiting_enabled: false,
			rate_limit_per_minute: 10,
			powered_by: true,
			custom_css: '',
			post_chat_rating: false,
			email_transcript: false,
		},
	});
	const [errors, setErrors] = useState({});

	useEffect(() => {
		if (widget) {
			setFormData({
				name: widget.display_name || widget.name || '',
				description: widget.description || '',
				agent_id: widget.agent_id || '',
				is_active: widget.is_active !== undefined ? !!widget.is_active : true,
				appearance: {
					...DEFAULT_APPEARANCE,
					...(widget.appearance || {}),
					...(widget.appearance?.colors ? {
						color_primary: widget.appearance.colors.primary || DEFAULT_APPEARANCE.color_primary,
						color_bg_main: widget.appearance.colors.background || DEFAULT_APPEARANCE.color_bg_main,
						color_text_primary: widget.appearance.colors.text || DEFAULT_APPEARANCE.color_text_primary,
					} : {}),
				},
				behavior: {
					...formData.behavior,
					...(widget.behavior || {}),
					// Migrate legacy triggers data into behavior
					...(widget.triggers || {}),
				},
				display: {
					...formData.display,
					...(widget.display || {}),
				},
				engagement: {
					...formData.engagement,
					...(widget.engagement || {}),
				},
			});
		}
	}, [widget]);

	const handleChange = (field, value) => {
		setFormData((prev) => ({ ...prev, [field]: value }));
		setErrors((prev) => ({ ...prev, [field]: null }));
	};

	const handleAppearanceChange = (field, value) => {
		setFormData((prev) => ({
			...prev,
			appearance: { ...prev.appearance, [field]: value },
		}));
	};

	const applyTemplate = (template) => {
		if (!template || !template.variables) return;
		const vars = template.variables;

		// Parse font size from template variable (e.g. '15px' -> 15)
		const parseFontSize = (val) => {
			if (!val) return null;
			const num = parseInt(val, 10);
			return isNaN(num) ? null : num;
		};
		// Parse border radius from template variable
		const parseRadius = (val) => {
			if (!val) return null;
			const num = parseInt(val, 10);
			return isNaN(num) ? null : num;
		};

		setFormData((prev) => ({
			...prev,
			appearance: {
				...prev.appearance,
				template: template.id,
				// Colors
				color_primary: vars['--swc-primary'] || prev.appearance.color_primary,
				color_primary_hover: vars['--swc-primary-hover'] || prev.appearance.color_primary_hover,
				color_bg_main: vars['--swc-bg-main'] || prev.appearance.color_bg_main,
				color_bg_light: vars['--swc-bg-light'] || prev.appearance.color_bg_light,
				color_text_primary: vars['--swc-text-primary'] || prev.appearance.color_text_primary,
				color_text_secondary: vars['--swc-text-secondary'] || prev.appearance.color_text_secondary,
				color_border: vars['--swc-border-color'] || prev.appearance.color_border,
				// Typography
				font_family: vars['--swc-font-family'] || prev.appearance.font_family,
				font_size_base: parseFontSize(vars['--swc-font-size-base']) ?? prev.appearance.font_size_base,
				// Spacing
				border_radius: parseRadius(vars['--swc-radius-lg']) ?? prev.appearance.border_radius,
				bubble_radius: parseRadius(vars['--swc-radius-bubble']) ?? prev.appearance.bubble_radius,
			},
		}));
	};

	const handleBehaviorChange = (field, value) => {
		setFormData((prev) => ({
			...prev,
			behavior: { ...prev.behavior, [field]: value },
		}));
	};

	const handleDisplayChange = (display) => {
		setFormData((prev) => ({ ...prev, display }));
	};

	const handleEngagementChange = (engagement) => {
		setFormData((prev) => ({ ...prev, engagement }));
	};

	const handlePresetSelect = (preset) => {
		// First, apply the preset config
		let newFormData;
		setFormData((prev) => {
			newFormData = applyPreset(preset, prev);
			// Then optionally apply the specific template colors if found
			if (preset.config?.appearance?.template) {
				const template = TEMPLATES.find(t => t.id === preset.config.appearance.template);
				if (template && template.variables) {
					const vars = template.variables;
					const parseFontSize = (val) => {
						if (!val) return null;
						const num = parseInt(val, 10);
						return isNaN(num) ? null : num;
					};
					const parseRadius = (val) => {
						if (!val) return null;
						const num = parseInt(val, 10);
						return isNaN(num) ? null : num;
					};

					newFormData = {
						...newFormData,
						appearance: {
							...newFormData.appearance,
							color_primary: vars['--swc-primary'] || newFormData.appearance.color_primary,
							color_primary_hover: vars['--swc-primary-hover'] || newFormData.appearance.color_primary_hover,
							color_bg_main: vars['--swc-bg-main'] || newFormData.appearance.color_bg_main,
							color_bg_light: vars['--swc-bg-light'] || newFormData.appearance.color_bg_light,
							color_text_primary: vars['--swc-text-primary'] || newFormData.appearance.color_text_primary,
							color_text_secondary: vars['--swc-text-secondary'] || newFormData.appearance.color_text_secondary,
							color_border: vars['--swc-border-color'] || newFormData.appearance.color_border,
							font_family: vars['--swc-font-family'] || newFormData.appearance.font_family,
							font_size_base: parseFontSize(vars['--swc-font-size-base']) ?? newFormData.appearance.font_size_base,
							border_radius: parseRadius(vars['--swc-radius-lg']) ?? newFormData.appearance.border_radius,
							bubble_radius: parseRadius(vars['--swc-radius-bubble']) ?? newFormData.appearance.bubble_radius,
						}
					};
				}
			}
			return newFormData;
		});
		setShowPresetSelector(false);
	};

	const handleSkipPresets = () => {
		setShowPresetSelector(false);
	};

	const validate = () => {
		const nextErrors = {};
		if (!formData.name.trim()) {
			nextErrors.name = __('Name is required', 'smart-woo-chatbot');
		}
		setErrors(nextErrors);

		if (Object.keys(nextErrors).length > 0) {
			setActiveTab('basic');
		}

		return Object.keys(nextErrors).length === 0;
	};

	const handleSubmit = (event) => {
		event.preventDefault();
		if (!validate()) return;
		// Auto-sync display_name from name
		const payload = { ...formData, display_name: formData.name };
		onSave(payload);
	};

	return (
		<div className="bg-white rounded-2xl border border-slate-200 shadow-sm">
			{/* Preset Selector Modal */}
			{showPresetSelector ? (
				<div className="p-8">
					<PresetSelector
						onSelect={handlePresetSelect}
						onSkip={handleSkipPresets}
					/>
				</div>
			) : (
				<form onSubmit={handleSubmit}>
					{/* Header */}
					<div className="px-6 py-5 border-b border-slate-100 bg-white rounded-t-2xl">
						<div className="flex items-center justify-between">
							<div className="flex items-center gap-4">
								<div className="flex items-center justify-center w-12 h-12 rounded-2xl bg-primary text-primary-foreground text-xl shadow-lg shadow-primary/25">
									<svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
								</div>
								<div>
									<h2 className="text-lg font-semibold text-slate-900">
										{widget ? __('Edit Widget', 'smart-woo-chatbot') : __('Create New Widget', 'smart-woo-chatbot')}
									</h2>
									<p className="text-sm text-slate-500">
										{widget?.display_name || widget?.name || __('Configure your chat widget settings', 'smart-woo-chatbot')}
									</p>
								</div>
							</div>
							<div className="flex items-center gap-3">
								<button
									type="button"
									onClick={() => setShowPresetSelector(true)}
									className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
								>
									<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
										<path strokeLinecap="round" strokeLinejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
									</svg>
									{__('Browse Presets', 'smart-woo-chatbot')}
								</button>
								<div className="h-8 w-px bg-slate-200 mx-1"></div>
								<Toggle
									checked={formData.is_active}
									onChange={(val) => handleChange('is_active', val)}
									label={formData.is_active ? __('Active', 'smart-woo-chatbot') : __('Inactive', 'smart-woo-chatbot')}
								/>
							</div>
						</div>
					</div>

					{/* Tabs Navigation */}
					<div className="px-6 py-3 border-b border-slate-100 bg-slate-50/50 overflow-x-auto">
						<div className="flex gap-1 min-w-max">
							{SECTION_CONFIG.map((section) => (
								<button
									key={section.id}
									type="button"
									onClick={() => setActiveTab(section.id)}
									className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 ${activeTab === section.id
										? 'bg-white text-primary shadow-sm border border-slate-200'
										: 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
										}`}
								>
									<TabIcon path={section.icon} />
									{section.label}
								</button>
							))}
						</div>
					</div>

					{/* Tab Content */}
					<div className="p-6">
						{/* Basic Info Tab */}
						{activeTab === 'basic' && (
							<div className="space-y-6">
								<FormField
									label={__('Name', 'smart-woo-chatbot')}
									required
									error={errors.name}
									hint={__('Used as the widget identifier and shown in the chat header', 'smart-woo-chatbot')}
								>
									<Input
										type="text"
										value={formData.name}
										onChange={(e) => handleChange('name', e.target.value)}
										error={errors.name}
										placeholder="e.g. Sales Assistant"
									/>
								</FormField>

								<FormField
									label={__('Description', 'smart-woo-chatbot')}
									hint={__('Internal notes about this widget', 'smart-woo-chatbot')}
								>
									<Textarea
										value={formData.description}
										onChange={(e) => handleChange('description', e.target.value)}
										rows={3}
										placeholder={__('Describe the purpose of this widget...', 'smart-woo-chatbot')}
									/>
								</FormField>

								<SectionCard
									title={__('Agent Configuration', 'smart-woo-chatbot')}
									description={__('Choose which agent handles conversations', 'smart-woo-chatbot')}
									icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
								>
									<TeamSelector
										value={formData.agent_id}
										onChange={(val) => handleChange('agent_id', val)}
										agents={agents}
									/>
								</SectionCard>
							</div>
						)}

						{/* Appearance Tab */}
						{activeTab === 'appearance' && (
							<div className="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
								<div className="space-y-6">
									<TemplateGallery
										currentTemplate={formData.appearance.template}
										onSelect={(template) => {
											handleAppearanceChange('template', template.id);
											applyTemplate(template);
										}}
										onCustomize={applyTemplate}
									/>

									<ThemeBuilder
										settings={formData.appearance}
										onChange={handleAppearanceChange}
									/>

									{/* Position Selector */}
									<SectionCard
										title={__('Widget Position', 'smart-woo-chatbot')}
										icon="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
									>
										<div className="flex gap-3">
											{POSITION_OPTIONS.map((option) => (
												<button
													key={option.value}
													type="button"
													onClick={() => handleAppearanceChange('position', option.value)}
													className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium rounded-xl border-2 transition-all duration-200 ${formData.appearance.position === option.value
														? 'bg-primary/5 text-primary border-primary shadow-sm'
														: 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
														}`}
												>
													<span>{option.icon}</span>
													{option.label}
												</button>
											))}
										</div>
									</SectionCard>

									{/* Avatar Input */}
									<SectionCard
										title={__('Widget Avatar', 'smart-woo-chatbot')}
										icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
									>
										<div className="flex gap-4 items-center">
											<div
												className="flex items-center justify-center w-14 h-14 rounded-2xl text-2xl border-2 border-dashed border-slate-200"
												style={{
													background: formData.appearance.avatar ? 'transparent' : 'linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%)'
												}}
											>
												{formData.appearance.avatar || <svg className="w-7 h-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19 14.5M14.25 3.104c.251.023.501.05.75.082M19 14.5l-2.47 4.235a2.25 2.25 0 01-1.944 1.126H9.414a2.25 2.25 0 01-1.944-1.126L5 14.5m14 0H5" /></svg>}
											</div>
											<div className="flex-1">
												<Input
													type="text"
													value={formData.appearance.avatar || ''}
													onChange={(e) => handleAppearanceChange('avatar', e.target.value)}
													placeholder={__('Emoji or image URL', 'smart-woo-chatbot')}
												/>
												<p className="text-xs text-slate-500 mt-1.5">
													{__('Use an emoji or paste an image URL', 'smart-woo-chatbot')}
												</p>
											</div>
										</div>
									</SectionCard>

									{/* Toggle Button Customization */}
									<SectionCard
										title={__('Toggle Button', 'smart-woo-chatbot')}
										description={__('Customize the floating button appearance', 'smart-woo-chatbot')}
										icon="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
									>
										<div className="space-y-5">
											{/* Shape Selection */}
											<FormField label={__('Button Shape', 'smart-woo-chatbot')}>
												<div className="grid grid-cols-4 gap-2">
													{[
														{ value: 'circle', label: __('Circle', 'smart-woo-chatbot'), shape: (<svg width="28" height="28" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="12" stroke="currentColor" strokeWidth="2" fill="currentColor" fillOpacity="0.1" /></svg>) },
														{ value: 'pill', label: __('Pill', 'smart-woo-chatbot'), shape: (<svg width="40" height="24" viewBox="0 0 40 24" fill="none"><rect x="1" y="1" width="38" height="22" rx="11" stroke="currentColor" strokeWidth="2" fill="currentColor" fillOpacity="0.1" /></svg>) },
														{ value: 'rounded_square', label: __('Square', 'smart-woo-chatbot'), shape: (<svg width="28" height="28" viewBox="0 0 28 28" fill="none"><rect x="2" y="2" width="24" height="24" rx="6" stroke="currentColor" strokeWidth="2" fill="currentColor" fillOpacity="0.1" /></svg>) },
														{ value: 'card', label: __('Card', 'smart-woo-chatbot'), shape: (<svg width="36" height="26" viewBox="0 0 36 26" fill="none"><rect x="1" y="1" width="34" height="24" rx="8" stroke="currentColor" strokeWidth="2" fill="currentColor" fillOpacity="0.1" /><circle cx="12" cy="13" r="3" fill="currentColor" fillOpacity="0.3" /><line x1="18" y1="11" x2="28" y2="11" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" opacity="0.4" /><line x1="18" y1="15" x2="25" y2="15" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" opacity="0.3" /></svg>) },
													].map((shape) => (
														<button
															key={shape.value}
															type="button"
															onClick={() => handleAppearanceChange('toggle_shape', shape.value)}
															className={`flex flex-col items-center gap-1 py-3 px-2 text-xs font-medium rounded-xl border-2 transition-all duration-200 ${formData.appearance.toggle_shape === shape.value
																? 'bg-primary/5 text-primary border-primary shadow-sm'
																: 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
																}`}
														>
															{shape.shape}
															<span>{shape.label}</span>
														</button>
													))}
												</div>
											</FormField>

											{/* Icon Selection */}
											<FormField label={__('Button Icon', 'smart-woo-chatbot')}>
												<div className="grid grid-cols-3 sm:grid-cols-6 gap-2">
													{[
														{ value: 'chat', label: __('Chat', 'smart-woo-chatbot'), icon: <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg> },
														{ value: 'message', label: __('Message', 'smart-woo-chatbot'), icon: <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg> },
														{ value: 'support', label: __('Support', 'smart-woo-chatbot'), icon: <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg> },
														{ value: 'headset', label: __('Headset', 'smart-woo-chatbot'), icon: <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M3 18v-6a9 9 0 0118 0v6" /><path d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z" /></svg> },
														{ value: 'custom', label: __('Custom', 'smart-woo-chatbot'), icon: <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg> },
														{ value: 'none', label: __('None', 'smart-woo-chatbot'), icon: <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> },
													].map((icon) => (
														<button
															key={icon.value}
															type="button"
															onClick={() => handleAppearanceChange('toggle_icon', icon.value)}
															className={`flex flex-col items-center gap-1 py-2 px-2 text-xs font-medium rounded-lg border-2 transition-all duration-200 ${formData.appearance.toggle_icon === icon.value
																? 'bg-primary/5 text-primary border-primary shadow-sm'
																: 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'
																}`}
														>
															<span className="text-base">{icon.icon}</span>
															<span className="truncate">{icon.label}</span>
														</button>
													))}
												</div>
											</FormField>

											{/* Custom Icon Input */}
											{formData.appearance.toggle_icon === 'custom' && (
												<FormField
													label={__('Custom Icon', 'smart-woo-chatbot')}
													hint={__('Enter text or emoji for the icon', 'smart-woo-chatbot')}
												>
													<Input
														type="text"
														value={formData.appearance.toggle_custom_icon || ''}
														onChange={(e) => handleAppearanceChange('toggle_custom_icon', e.target.value)}
														placeholder="Type here..."
													/>
												</FormField>
											)}

											{/* Label for Pill/Card shapes */}
											{(formData.appearance.toggle_shape === 'pill' || formData.appearance.toggle_shape === 'card') && (
												<FormField
													label={__('Button Label', 'smart-woo-chatbot')}
													hint={__('Text shown next to the icon', 'smart-woo-chatbot')}
												>
													<Input
														type="text"
														value={formData.appearance.toggle_label || ''}
														onChange={(e) => handleAppearanceChange('toggle_label', e.target.value)}
														placeholder={__('Chat with us', 'smart-woo-chatbot')}
													/>
												</FormField>
											)}

											{/* Shadow Selection */}
											<FormField label={__('Shadow Intensity', 'smart-woo-chatbot')}>
												<div className="flex gap-2">
													{[
														{ value: 'none', label: __('None', 'smart-woo-chatbot') },
														{ value: 'small', label: __('Small', 'smart-woo-chatbot') },
														{ value: 'medium', label: __('Medium', 'smart-woo-chatbot') },
														{ value: 'large', label: __('Large', 'smart-woo-chatbot') },
													].map((shadow) => (
														<button
															key={shadow.value}
															type="button"
															onClick={() => handleAppearanceChange('toggle_shadow', shadow.value)}
															className={`flex-1 py-2 px-3 text-sm font-medium rounded-lg border-2 transition-all duration-200 ${formData.appearance.toggle_shadow === shadow.value
																? 'bg-primary/5 text-primary border-primary'
																: 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'
																}`}
														>
															{shadow.label}
														</button>
													))}
												</div>
											</FormField>

											{/* Visual Effects Toggles */}
											<div className="grid grid-cols-2 gap-4 pt-2">
												<Toggle
													checked={formData.appearance.toggle_glow || false}
													onChange={(val) => handleAppearanceChange('toggle_glow', val)}
													label={__('Glow Effect', 'smart-woo-chatbot')}
													description={__('Subtle glowing animation', 'smart-woo-chatbot')}
												/>
												<Toggle
													checked={formData.appearance.toggle_pulse || false}
													onChange={(val) => handleAppearanceChange('toggle_pulse', val)}
													label={__('Pulse Animation', 'smart-woo-chatbot')}
													description={__('Attention-grabbing pulse', 'smart-woo-chatbot')}
												/>
											</div>

											{/* Border Toggle */}
											<Toggle
												checked={formData.appearance.toggle_border || false}
												onChange={(val) => handleAppearanceChange('toggle_border', val)}
												label={__('Show Border', 'smart-woo-chatbot')}
												description={__('Add a border around the toggle button', 'smart-woo-chatbot')}
											/>

											{formData.appearance.toggle_border && (
												<FormField label={__('Border Color', 'smart-woo-chatbot')}>
													<div className="flex items-center gap-3">
														<input
															type="color"
															value={formData.appearance.toggle_border_color || '#ffffff'}
															onChange={(e) => handleAppearanceChange('toggle_border_color', e.target.value)}
															className="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer"
														/>
														<Input
															type="text"
															value={formData.appearance.toggle_border_color || '#ffffff'}
															onChange={(e) => handleAppearanceChange('toggle_border_color', e.target.value)}
															placeholder="#ffffff"
														/>
													</div>
												</FormField>
											)}
										</div>
									</SectionCard>
								</div>

								{/* Live Preview */}
								<div className="xl:sticky xl:top-[160px]">
									<ChatPreview
										settings={formData.appearance}
										displayName={formData.name}
										behavior={formData.behavior}
										engagement={formData.engagement}
									/>
								</div>
							</div>
						)}

						{/* Behavior Tab — Messages & Proactive Triggers */}
						{activeTab === 'behavior' && (
							<div className="space-y-6 max-w-2xl">
								{/* Section 1: Welcome Messages */}
								<SectionCard
									title={__('Welcome Messages', 'smart-woo-chatbot')}
									description={__('Configure how your widget greets visitors', 'smart-woo-chatbot')}
									icon="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
								>
									<div className="space-y-5">
										<FormField
											label={__('Greeting Message', 'smart-woo-chatbot')}
											hint={__('First message shown when chat opens', 'smart-woo-chatbot')}
										>
											<Textarea
												value={formData.behavior.greeting_message}
												onChange={(e) => handleBehaviorChange('greeting_message', e.target.value)}
												rows={3}
												placeholder={__('Hi! How can I help you today?', 'smart-woo-chatbot')}
											/>
										</FormField>

										<FormField
											label={__('Input Placeholder', 'smart-woo-chatbot')}
											hint={__('Placeholder text in the message input', 'smart-woo-chatbot')}
										>
											<Input
												type="text"
												value={formData.behavior.placeholder_text}
												onChange={(e) => handleBehaviorChange('placeholder_text', e.target.value)}
												placeholder={__('Type your message…', 'smart-woo-chatbot')}
											/>
										</FormField>

										<FormField
											label={__('Returning Visitor Greeting', 'smart-woo-chatbot')}
											hint={__('Special greeting for visitors who have been to your site before (leave empty to use default)', 'smart-woo-chatbot')}
										>
											<Textarea
												value={formData.behavior.returning_visitor_greeting || ''}
												onChange={(e) => handleBehaviorChange('returning_visitor_greeting', e.target.value)}
												rows={2}
												placeholder={__('Welcome back! How can I assist you today?', 'smart-woo-chatbot')}
											/>
										</FormField>
									</div>
								</SectionCard>

								{/* Section 2: Proactive Triggers */}
								<SectionCard
									title={__('Proactive Triggers', 'smart-woo-chatbot')}
									description={__('Automatically engage visitors at the right moment', 'smart-woo-chatbot')}
									icon="M13 10V3L4 14h7v7l9-11h-7z"
								>
									<div className="space-y-4">
										{/* Auto-Open Trigger */}
										<div className="rounded-xl border border-slate-200 overflow-hidden">
											<div className="flex items-center justify-between px-4 py-3 bg-slate-50/80">
												<div className="flex items-center gap-3">
													<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-500">
														<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
															<path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
														</svg>
													</div>
													<div>
														<span className="text-sm font-semibold text-slate-800">{__('Auto-Open After Delay', 'smart-woo-chatbot')}</span>
														<p className="text-xs text-slate-500">{__('Open chat automatically after a set time', 'smart-woo-chatbot')}</p>
													</div>
												</div>
												<Toggle
													checked={formData.behavior.auto_open_enabled || false}
													onChange={(val) => handleBehaviorChange('auto_open_enabled', val)}
												/>
											</div>
											{formData.behavior.auto_open_enabled && (
												<div className="px-4 pb-4 pt-2 border-t border-slate-100">
													<FormField label={__('Delay (seconds)', 'smart-woo-chatbot')}>
														<div className="flex items-center gap-4">
															<input
																type="range"
																min={0}
																max={60}
																step={1}
																value={formData.behavior.auto_open_delay || 10}
																onChange={(e) => handleBehaviorChange('auto_open_delay', parseInt(e.target.value))}
																className="flex-1 h-2 bg-slate-200 rounded-full appearance-none cursor-pointer accent-indigo-500"
															/>
															<span className="text-sm font-semibold text-indigo-600 min-w-[40px] text-right">
																{formData.behavior.auto_open_delay || 10}s
															</span>
														</div>
													</FormField>
												</div>
											)}
										</div>

										{/* Exit Intent Trigger */}
										<div className="rounded-xl border border-slate-200 overflow-hidden">
											<div className="flex items-center justify-between px-4 py-3 bg-slate-50/80">
												<div className="flex items-center gap-3">
													<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-500">
														<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
															<path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
														</svg>
													</div>
													<div>
														<span className="text-sm font-semibold text-slate-800">{__('Exit Intent', 'smart-woo-chatbot')}</span>
														<p className="text-xs text-slate-500">{__('Show chat when visitor tries to leave', 'smart-woo-chatbot')}</p>
													</div>
												</div>
												<Toggle
													checked={formData.behavior.exit_intent_enabled || false}
													onChange={(val) => handleBehaviorChange('exit_intent_enabled', val)}
												/>
											</div>
											{formData.behavior.exit_intent_enabled && (
												<div className="px-4 pb-4 pt-2 border-t border-slate-100">
													<FormField label={__('Exit Message', 'smart-woo-chatbot')}>
														<Textarea
															value={formData.behavior.exit_intent_message || ''}
															onChange={(e) => handleBehaviorChange('exit_intent_message', e.target.value)}
															rows={2}
															placeholder={__('Wait! Before you go, can I help you find what you\'re looking for?', 'smart-woo-chatbot')}
														/>
													</FormField>
												</div>
											)}
										</div>

										{/* Scroll Depth Trigger */}
										<div className="rounded-xl border border-slate-200 overflow-hidden">
											<div className="flex items-center justify-between px-4 py-3 bg-slate-50/80">
												<div className="flex items-center gap-3">
													<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-500">
														<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
															<path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
														</svg>
													</div>
													<div>
														<span className="text-sm font-semibold text-slate-800">{__('Scroll Depth', 'smart-woo-chatbot')}</span>
														<p className="text-xs text-slate-500">{__('Open chat when visitor scrolls past a point', 'smart-woo-chatbot')}</p>
													</div>
												</div>
												<Toggle
													checked={formData.behavior.scroll_depth_enabled || false}
													onChange={(val) => handleBehaviorChange('scroll_depth_enabled', val)}
												/>
											</div>
											{formData.behavior.scroll_depth_enabled && (
												<div className="px-4 pb-4 pt-2 border-t border-slate-100">
													<FormField label={__('Scroll Percentage', 'smart-woo-chatbot')}>
														<div className="flex items-center gap-4">
															<input
																type="range"
																min={10}
																max={100}
																step={10}
																value={formData.behavior.scroll_depth_percent || 50}
																onChange={(e) => handleBehaviorChange('scroll_depth_percent', parseInt(e.target.value))}
																className="flex-1 h-2 bg-slate-200 rounded-full appearance-none cursor-pointer accent-indigo-500"
															/>
															<span className="text-sm font-semibold text-indigo-600 min-w-[40px] text-right">
																{formData.behavior.scroll_depth_percent || 50}%
															</span>
														</div>
													</FormField>
												</div>
											)}
										</div>

										{/* Time on Page Trigger */}
										<div className="rounded-xl border border-slate-200 overflow-hidden">
											<div className="flex items-center justify-between px-4 py-3 bg-slate-50/80">
												<div className="flex items-center gap-3">
													<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-50 text-purple-500">
														<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
															<path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
														</svg>
													</div>
													<div>
														<span className="text-sm font-semibold text-slate-800">{__('Time on Page', 'smart-woo-chatbot')}</span>
														<p className="text-xs text-slate-500">{__('Trigger after visitor spends time on page', 'smart-woo-chatbot')}</p>
													</div>
												</div>
												<Toggle
													checked={formData.behavior.time_on_page_enabled || false}
													onChange={(val) => handleBehaviorChange('time_on_page_enabled', val)}
												/>
											</div>
											{formData.behavior.time_on_page_enabled && (
												<div className="px-4 pb-4 pt-2 border-t border-slate-100">
													<FormField label={__('Time (seconds)', 'smart-woo-chatbot')}>
														<div className="flex items-center gap-4">
															<input
																type="range"
																min={5}
																max={300}
																step={5}
																value={formData.behavior.time_on_page_seconds || 30}
																onChange={(e) => handleBehaviorChange('time_on_page_seconds', parseInt(e.target.value))}
																className="flex-1 h-2 bg-slate-200 rounded-full appearance-none cursor-pointer accent-indigo-500"
															/>
															<span className="text-sm font-semibold text-indigo-600 min-w-[40px] text-right">
																{formData.behavior.time_on_page_seconds || 30}s
															</span>
														</div>
													</FormField>
												</div>
											)}
										</div>

										{/* Cart Abandonment Trigger */}
										<div className="rounded-xl border border-slate-200 overflow-hidden">
											<div className="flex items-center justify-between px-4 py-3 bg-slate-50/80">
												<div className="flex items-center gap-3">
													<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-rose-50 text-rose-500">
														<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
															<path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
														</svg>
													</div>
													<div>
														<span className="text-sm font-semibold text-slate-800">{__('Cart Abandonment', 'smart-woo-chatbot')}</span>
														<p className="text-xs text-slate-500">{__('Engage customers about to leave with cart items', 'smart-woo-chatbot')}</p>
													</div>
												</div>
												<Toggle
													checked={formData.behavior.cart_abandonment_enabled || false}
													onChange={(val) => handleBehaviorChange('cart_abandonment_enabled', val)}
												/>
											</div>
											{formData.behavior.cart_abandonment_enabled && (
												<div className="px-4 pb-4 pt-2 border-t border-slate-100">
													<FormField label={__('Abandonment Message', 'smart-woo-chatbot')}>
														<Textarea
															value={formData.behavior.cart_abandonment_message || ''}
															onChange={(e) => handleBehaviorChange('cart_abandonment_message', e.target.value)}
															rows={2}
															placeholder={__('Don\'t forget your items! Need help completing your order?', 'smart-woo-chatbot')}
														/>
													</FormField>
												</div>
											)}
										</div>
									</div>
								</SectionCard>
							</div>
						)}

						{/* Display Tab */}
						{activeTab === 'display' && (
							<DisplayRules
								display={formData.display}
								onChange={handleDisplayChange}
							/>
						)}

						{/* Engagement Tab */}
						{activeTab === 'engagement' && (
							<EngagementSettings
								engagement={formData.engagement}
								onChange={handleEngagementChange}
							/>
						)}

						{/* Bubble Tab — Presentation & Notification Controls */}
						{activeTab === 'bubble' && (
							<div className="space-y-6 max-w-2xl">
								{/* Presentation Mode */}
								<SectionCard
									title={__('Presentation Mode', 'smart-woo-chatbot')}
									description={__('How the widget appears on the page', 'smart-woo-chatbot')}
									icon="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"
								>
									<div className="space-y-4">
										<div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
											{[
												{ value: 'floating', label: __('Floating', 'smart-woo-chatbot'), icon: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>, desc: 'Classic bubble button' },
												{ value: 'centered', label: __('Centered', 'smart-woo-chatbot'), icon: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>, desc: 'ChatGPT-style input' },
												{ value: 'embedded', label: __('Embedded', 'smart-woo-chatbot'), icon: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>, desc: 'Inside a page element' },
												{ value: 'sidebar', label: __('Sidebar', 'smart-woo-chatbot'), icon: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18v16H3V4zm6 0v16" /></svg>, desc: 'Fixed side panel' },
											].map((mode) => (
												<button
													key={mode.value}
													type="button"
													onClick={() => handleAppearanceChange('presentation_mode', mode.value)}
													className={`flex flex-col items-center gap-2 p-4 text-xs font-medium rounded-xl border-2 transition-all duration-200 ${formData.appearance.presentation_mode === mode.value
														? 'bg-primary/5 text-primary border-primary shadow-sm'
														: 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
														}`}
												>
													<span>{mode.icon}</span>
													<span className="font-semibold">{mode.label}</span>
													<span className="text-[10px] text-slate-500">{mode.desc}</span>
												</button>
											))}
										</div>

										{formData.appearance.presentation_mode === 'embedded' && (
											<FormField
												label={__('CSS Selector', 'smart-woo-chatbot')}
												hint={__('e.g. #chat-container or .chat-widget-area', 'smart-woo-chatbot')}
											>
												<Input
													type="text"
													value={formData.appearance.embedded_target || ''}
													onChange={(e) => handleAppearanceChange('embedded_target', e.target.value)}
													placeholder="#chat-container"
												/>
											</FormField>
										)}

										{formData.appearance.presentation_mode === 'sidebar' && (
											<FormField
												label={__('Sidebar Width (px)', 'smart-woo-chatbot')}
												hint={__('Width of the side panel', 'smart-woo-chatbot')}
											>
												<input
													type="range"
													min="300"
													max="600"
													step="10"
													value={formData.appearance.sidebar_width || 400}
													onChange={(e) => handleAppearanceChange('sidebar_width', parseInt(e.target.value))}
													className="w-full accent-primary"
												/>
												<div className="text-xs text-slate-500 text-right mt-1">{formData.appearance.sidebar_width || 400}px</div>
											</FormField>
										)}
									</div>
								</SectionCard>

								{/* Greeting Bubble */}
								<SectionCard
									title={__('Greeting Bubble', 'smart-woo-chatbot')}
									description={__('A teaser tooltip shown near the toggle button before the chat opens', 'smart-woo-chatbot')}
									icon="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
								>
									<div className="space-y-4">
										<Toggle
											checked={formData.appearance.greeting_bubble_enabled || false}
											onChange={(val) => handleAppearanceChange('greeting_bubble_enabled', val)}
											label={__('Enable Greeting Bubble', 'smart-woo-chatbot')}
											description={__('Show a small speech bubble to attract attention', 'smart-woo-chatbot')}
										/>

										{formData.appearance.greeting_bubble_enabled && (
											<>
												<FormField label={__('Greeting Text', 'smart-woo-chatbot')}>
													<Input
														type="text"
														value={formData.appearance.greeting_bubble_text || ''}
														onChange={(e) => handleAppearanceChange('greeting_bubble_text', e.target.value)}
														placeholder="Need help? Chat with us!"
													/>
												</FormField>

												<div className="grid grid-cols-2 gap-4">
													<FormField
														label={__('Show After (seconds)', 'smart-woo-chatbot')}
														hint={__('Delay before the greeting appears', 'smart-woo-chatbot')}
													>
														<Input
															type="number"
															min="0"
															max="60"
															value={formData.appearance.greeting_bubble_delay || 5}
															onChange={(e) => handleAppearanceChange('greeting_bubble_delay', parseInt(e.target.value))}
														/>
													</FormField>
													<div className="flex items-end pb-2">
														<Toggle
															checked={formData.appearance.greeting_bubble_dismissible !== false}
															onChange={(val) => handleAppearanceChange('greeting_bubble_dismissible', val)}
															label={__('Dismissible', 'smart-woo-chatbot')}
														/>
													</div>
												</div>

												{/* Preview */}
												<div className="relative bg-slate-50 rounded-xl p-6 flex items-end justify-end gap-3">
													<div className="bg-white rounded-2xl rounded-br-sm px-4 py-2 shadow-lg border border-slate-200 text-sm text-slate-700 max-w-[200px]">
														{formData.appearance.greeting_bubble_text || 'Need help? Chat with us!'}
														{formData.appearance.greeting_bubble_dismissible && (
															<span className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-slate-300 text-white text-xs rounded-full flex items-center justify-center cursor-pointer"></span>
														)}
													</div>
													<div
														className="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg shadow-lg shrink-0"
														style={{ background: `linear-gradient(135deg, ${formData.appearance.color_primary}, ${formData.appearance.color_primary_hover})` }}
													>
														<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
													</div>
												</div>
											</>
										)}
									</div>
								</SectionCard>

								{/* Notification Badge */}
								<SectionCard
									title={__('Notification Badge', 'smart-woo-chatbot')}
									description={__('Configure the attention-grabbing notification dot', 'smart-woo-chatbot')}
									icon="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
								>
									<div className="space-y-4">
										<Toggle
											checked={formData.appearance.notification_badge_enabled !== false}
											onChange={(val) => handleAppearanceChange('notification_badge_enabled', val)}
											label={__('Show Notification Badge', 'smart-woo-chatbot')}
											description={__('Red dot indicator when there are unread messages', 'smart-woo-chatbot')}
										/>

										{formData.appearance.notification_badge_enabled !== false && (
											<FormField label={__('Badge Color', 'smart-woo-chatbot')}>
												<div className="flex items-center gap-3">
													<input
														type="color"
														value={formData.appearance.notification_badge_color || '#ef4444'}
														onChange={(e) => handleAppearanceChange('notification_badge_color', e.target.value)}
														className="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer"
													/>
													<Input
														type="text"
														value={formData.appearance.notification_badge_color || '#ef4444'}
														onChange={(e) => handleAppearanceChange('notification_badge_color', e.target.value)}
														placeholder="#ef4444"
													/>
												</div>
											</FormField>
										)}

										<FormField
											label={__('Notification Sound URL', 'smart-woo-chatbot')}
											hint={__('Optional MP3/WAV URL for incoming message sound', 'smart-woo-chatbot')}
										>
											<Input
												type="text"
												value={formData.appearance.notification_sound_url || ''}
												onChange={(e) => handleAppearanceChange('notification_sound_url', e.target.value)}
												placeholder="https://example.com/notification.mp3"
											/>
										</FormField>

										<Toggle
											checked={formData.appearance.desktop_notification_enabled || false}
											onChange={(val) => handleAppearanceChange('desktop_notification_enabled', val)}
											label={__('Desktop Notifications', 'smart-woo-chatbot')}
											description={__('Browser push notifications for new messages (requires user permission)', 'smart-woo-chatbot')}
										/>
									</div>
								</SectionCard>

								{/* Entrance Animation */}
								<SectionCard
									title={__('Entrance Animation', 'smart-woo-chatbot')}
									description={__('How the chat window appears when opened', 'smart-woo-chatbot')}
									icon="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
								>
									<div className="grid grid-cols-5 gap-2">
										{[
											{ value: 'none', label: __('None', 'smart-woo-chatbot'), icon: <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> },
											{ value: 'slide-up', label: __('Slide Up', 'smart-woo-chatbot'), icon: <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg> },
											{ value: 'fade', label: __('Fade', 'smart-woo-chatbot'), icon: <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg> },
											{ value: 'bounce', label: __('Bounce', 'smart-woo-chatbot'), icon: <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg> },
											{ value: 'scale', label: __('Scale', 'smart-woo-chatbot'), icon: <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg> },
										].map((anim) => (
											<button
												key={anim.value}
												type="button"
												onClick={() => handleAppearanceChange('entrance_animation', anim.value)}
												className={`flex flex-col items-center gap-1 py-3 px-2 text-xs font-medium rounded-xl border-2 transition-all duration-200 ${formData.appearance.entrance_animation === anim.value
													? 'bg-primary/5 text-primary border-primary shadow-sm'
													: 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
													}`}
											>
												<span>{anim.icon}</span>
												<span>{anim.label}</span>
											</button>
										))}
									</div>
								</SectionCard>

								{/* Mobile Overrides */}
								<SectionCard
									title={__('Mobile Settings', 'smart-woo-chatbot')}
									description={__('Fine-tune behavior on mobile devices', 'smart-woo-chatbot')}
									icon="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
								>
									<div className="space-y-4">
										<FormField
											label={__('Mobile Toggle Size', 'smart-woo-chatbot')}
											hint={__('Button size on mobile (default: 52px)', 'smart-woo-chatbot')}
										>
											<div className="flex items-center gap-4">
												<input
													type="range"
													min="36"
													max="72"
													step="2"
													value={formData.appearance.mobile_toggle_size || 52}
													onChange={(e) => handleAppearanceChange('mobile_toggle_size', parseInt(e.target.value))}
													className="flex-1 accent-primary"
												/>
												<span className="text-sm font-mono text-slate-600 w-10 text-right">{formData.appearance.mobile_toggle_size || 52}px</span>
											</div>
										</FormField>

										<Toggle
											checked={formData.appearance.mobile_fullscreen || false}
											onChange={(val) => handleAppearanceChange('mobile_fullscreen', val)}
											label={__('Fullscreen on Mobile', 'smart-woo-chatbot')}
											description={__('Chat window takes the full screen on small devices', 'smart-woo-chatbot')}
										/>
									</div>
								</SectionCard>
							</div>
						)}

						{/* Controls Tab */}
						{activeTab === 'controls' && (
							<ChatControlsManager
								settings={formData.appearance}
								onChange={handleAppearanceChange}
							/>
						)}
					</div>

					{/* Footer Actions */}
					<div className="flex items-center justify-between px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
						<button
							type="button"
							onClick={onCancel}
							className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-xl border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-400 transition-all duration-200"
						>
							<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
								<path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
							</svg>
							{__('Cancel', 'smart-woo-chatbot')}
						</button>
						<button
							type="submit"
							className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold rounded-xl text-white bg-primary hover:bg-primary/90 shadow-lg shadow-primary/25 transition-all duration-200 hover:shadow-xl hover:shadow-primary/30 hover:-translate-y-0.5"
						>
							<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
								<path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
							</svg>
							{widget ? __('Update Widget', 'smart-woo-chatbot') : __('Create Widget', 'smart-woo-chatbot')}
						</button>
					</div>
				</form>
			)}
		</div>
	);
}

ChatEditor.propTypes = {
	widget: PropTypes.object,
	agents: PropTypes.array,
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
};
