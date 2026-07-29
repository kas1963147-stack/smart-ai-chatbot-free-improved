/**
 * Theme Builder Component
 *
 * Advanced customization interface with collapsible sections for:
 * - Color Palette
 * - Typography
 * - Spacing & Sizing
 * - Effects & Animation
 *
 * Tailwind-only implementation.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { ColorPicker, Panel, PanelBody, Range, Select, Toggle } from '../ui';

// Available Fonts
const FONT_OPTIONS = [
	{ value: 'system', label: 'System Default' },
	{ value: '"Inter", sans-serif', label: 'Inter' },
	{ value: '"Roboto", sans-serif', label: 'Roboto' },
	{ value: '"Poppins", sans-serif', label: 'Poppins' },
	{ value: '"Nunito", sans-serif', label: 'Nunito' },
	{ value: '"Open Sans", sans-serif', label: 'Open Sans' },
	{ value: '"Outfit", sans-serif', label: 'Outfit' },
	{ value: '"DM Sans", sans-serif', label: 'DM Sans' },
	{ value: '"IBM Plex Sans", sans-serif', label: 'IBM Plex Sans' },
	{ value: '"Quicksand", sans-serif', label: 'Quicksand' },
];

const SHADOW_OPTIONS = [
	{ value: 'none', label: 'None' },
	{ value: 'light', label: 'Light' },
	{ value: 'medium', label: 'Medium' },
	{ value: 'heavy', label: 'Heavy' },
];

const ANIMATION_SPEED_OPTIONS = [
	{ value: 'none', label: 'Disabled' },
	{ value: 'fast', label: 'Fast (0.15s)' },
	{ value: 'normal', label: 'Normal (0.3s)' },
	{ value: 'slow', label: 'Slow (0.5s)' },
];

export default function ThemeBuilder({ settings, onChange }) {
	const updateSetting = (key, value) => {
		onChange(key, value);
	};

	const RangeField = ({ label, settingKey, defaultValue, min, max, step = 1, unit = '' }) => {
		const rawValue = settings[settingKey];
		const value = Number(rawValue ?? defaultValue);

		return (
			<div className="space-y-2">
				<div className="flex items-center justify-between text-sm">
					<span className="font-medium text-slate-700 dark:text-slate-200">{label}</span>
					<span className="text-slate-500 dark:text-slate-400">{value}{unit}</span>
				</div>
				<Range
					value={value}
					min={min}
					max={max}
					step={step}
					onChange={(val) => updateSetting(settingKey, val)}
				/>
			</div>
		);
	};

	return (
		<div className="space-y-4">
			<Panel>
				<PanelBody
					title={__('Color Palette', 'smart-woo-chatbot')}
					initialOpen={true}
				>
					<div className="grid gap-4 sm:grid-cols-2">
						<ColorPicker
							label={__('Primary Color', 'smart-woo-chatbot')}
							value={settings.color_primary || '#6366f1'}
							onChange={(val) => updateSetting('color_primary', val)}
						/>
						<ColorPicker
							label={__('Primary Hover', 'smart-woo-chatbot')}
							value={settings.color_primary_hover || '#4f46e5'}
							onChange={(val) => updateSetting('color_primary_hover', val)}
						/>
						<ColorPicker
							label={__('Background', 'smart-woo-chatbot')}
							value={settings.color_bg_main || '#ffffff'}
							onChange={(val) => updateSetting('color_bg_main', val)}
						/>
						<ColorPicker
							label={__('Background Light', 'smart-woo-chatbot')}
							value={settings.color_bg_light || '#f8fafc'}
							onChange={(val) => updateSetting('color_bg_light', val)}
						/>
						<ColorPicker
							label={__('Text Primary', 'smart-woo-chatbot')}
							value={settings.color_text_primary || '#1e293b'}
							onChange={(val) => updateSetting('color_text_primary', val)}
						/>
						<ColorPicker
							label={__('Text Secondary', 'smart-woo-chatbot')}
							value={settings.color_text_secondary || '#64748b'}
							onChange={(val) => updateSetting('color_text_secondary', val)}
						/>
						<ColorPicker
							label={__('Border Color', 'smart-woo-chatbot')}
							value={settings.color_border || '#e2e8f0'}
							onChange={(val) => updateSetting('color_border', val)}
						/>
					</div>
				</PanelBody>

				<PanelBody
					title={__('Typography', 'smart-woo-chatbot')}
					initialOpen={false}
				>
					<div className="space-y-4">
						<Select
							label={__('Font Family', 'smart-woo-chatbot')}
							value={settings.font_family || 'system'}
							onChange={(val) => updateSetting('font_family', val)}
							options={FONT_OPTIONS}
							help={__('Google Fonts will be loaded automatically', 'smart-woo-chatbot')}
						/>
						<RangeField
							label={__('Base Font Size', 'smart-woo-chatbot')}
							settingKey="font_size_base"
							defaultValue={14}
							min={10}
							max={24}
							unit="px"
						/>
						<RangeField
							label={__('Line Height', 'smart-woo-chatbot')}
							settingKey="line_height"
							defaultValue={1.5}
							min={1.2}
							max={2}
							step={0.1}
						/>
					</div>
				</PanelBody>

				<PanelBody
					title={__('Spacing & Sizing', 'smart-woo-chatbot')}
					initialOpen={false}
				>
					<div className="space-y-4">
						<RangeField
							label={__('Window Width', 'smart-woo-chatbot')}
							settingKey="window_width"
							defaultValue={380}
							min={300}
							max={500}
							step={10}
							unit="px"
						/>
						<RangeField
							label={__('Window Height', 'smart-woo-chatbot')}
							settingKey="window_height"
							defaultValue={550}
							min={400}
							max={700}
							step={10}
							unit="px"
						/>
						<RangeField
							label={__('Border Radius', 'smart-woo-chatbot')}
							settingKey="border_radius"
							defaultValue={16}
							min={0}
							max={32}
							step={2}
							unit="px"
						/>
						<RangeField
							label={__('Message Bubble Radius', 'smart-woo-chatbot')}
							settingKey="bubble_radius"
							defaultValue={18}
							min={0}
							max={28}
							step={2}
							unit="px"
						/>
						<RangeField
							label={__('Toggle Button Size', 'smart-woo-chatbot')}
							settingKey="toggle_size"
							defaultValue={60}
							min={48}
							max={80}
							step={4}
							unit="px"
						/>
					</div>
				</PanelBody>

				<PanelBody
					title={__('Effects & Animation', 'smart-woo-chatbot')}
					initialOpen={false}
				>
					<div className="space-y-4">
						<Select
							label={__('Shadow Intensity', 'smart-woo-chatbot')}
							value={settings.shadow_intensity || 'medium'}
							onChange={(val) => updateSetting('shadow_intensity', val)}
							options={SHADOW_OPTIONS}
						/>
						<Select
							label={__('Animation Speed', 'smart-woo-chatbot')}
							value={settings.animation_speed || 'normal'}
							onChange={(val) => updateSetting('animation_speed', val)}
							options={ANIMATION_SPEED_OPTIONS}
						/>
						<Toggle
							label={__('Enable Hover Effects', 'smart-woo-chatbot')}
							help={__('Add subtle hover highlights in the widget.', 'smart-woo-chatbot')}
							checked={settings.enable_hover_effects !== false}
							onChange={(val) => updateSetting('enable_hover_effects', val)}
						/>
						<Toggle
							label={__('Glassmorphism Effect', 'smart-woo-chatbot')}
							help={__('Adds blur and transparency effects.', 'smart-woo-chatbot')}
							checked={!!settings.enable_glassmorphism}
							onChange={(val) => updateSetting('enable_glassmorphism', val)}
						/>
						<Toggle
							label={__('Sound Effects', 'smart-woo-chatbot')}
							help={__('Play sounds on message send/receive.', 'smart-woo-chatbot')}
							checked={!!settings.enable_sounds}
							onChange={(val) => updateSetting('enable_sounds', val)}
						/>
					</div>
				</PanelBody>
			</Panel>
		</div>
	);
}

ThemeBuilder.propTypes = {
	settings: PropTypes.object.isRequired,
	onChange: PropTypes.func.isRequired,
};
