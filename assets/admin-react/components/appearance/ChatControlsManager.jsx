/**
 * Chat Controls Manager Component
 *
 * Configure which chat control buttons are visible in the chatbot widget.
 * Covers: Header, Message, Footer, and Floating controls.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Panel, PanelBody, Toggle } from '../ui';

// Control Definitions
const HEADER_CONTROLS = [
	{
		id: 'close',
		label: 'Close Button',
		icon: 'X',
		default: true,
		description: 'Close the chat window',
	},
	{
		id: 'minimize',
		label: 'Minimize Button',
		icon: '-',
		default: false,
		description: 'Minimize to toggle only',
	},
	{
		id: 'reset',
		label: 'Reset Conversation',
		icon: 'R',
		default: false,
		description: 'Clear history and restart',
	},
	{
		id: 'sound',
		label: 'Sound Toggle',
		icon: 'S',
		default: false,
		description: 'Mute/unmute notifications',
	},
	{
		id: 'darkmode',
		label: 'Dark Mode Toggle',
		icon: 'D',
		default: false,
		description: 'Switch theme mode',
	},
];

const MESSAGE_CONTROLS = [
	{
		id: 'edit',
		label: 'Edit Message',
		icon: 'E',
		default: false,
		description: 'Edit sent user messages',
	},
	{
		id: 'copy',
		label: 'Copy Message',
		icon: 'C',
		default: false,
		description: 'Copy message to clipboard',
	},
	{
		id: 'delete',
		label: 'Delete Message',
		icon: 'Del',
		default: false,
		description: 'Remove single message',
	},
	{
		id: 'regenerate',
		label: 'Regenerate Response',
		icon: 'R',
		default: false,
		description: 'Get new AI response',
	},
	{
		id: 'feedback',
		label: 'Thumbs Up/Down',
		icon: '+/-',
		default: false,
		description: 'Rate AI responses',
	},
];

const FOOTER_CONTROLS = [
	{
		id: 'voice',
		label: 'Voice Input',
		icon: 'V',
		default: true,
		description: 'Speak message via microphone',
	},
	{
		id: 'attach',
		label: 'Attachment Button',
		icon: 'A',
		default: false,
		description: 'Upload files (future)',
	},
	{
		id: 'emoji',
		label: 'Emoji Picker',
		icon: 'Em',
		default: false,
		description: 'Insert emoji in message',
	},
	{
		id: 'clear',
		label: 'Clear Chat Button',
		icon: 'Clr',
		default: false,
		description: 'Clear all messages',
	},
	{
		id: 'export',
		label: 'Export Chat',
		icon: 'Ex',
		default: false,
		description: 'Download conversation',
	},
];

const FLOATING_CONTROLS = [
	{
		id: 'scroll_bottom',
		label: 'Scroll to Bottom',
		icon: 'Down',
		default: true,
		description: 'Jump to latest message',
	},
	{
		id: 'unread_badge',
		label: 'Unread Counter',
		icon: 'U',
		default: false,
		description: 'Show unread count',
	},
	{
		id: 'expand',
		label: 'Fullscreen Toggle',
		icon: 'Full',
		default: false,
		description: 'Expand to fullscreen',
	},
];

export default function ChatControlsManager({ settings, onChange }) {
	// Parse control arrays from settings
	const getControlState = (category, controlId) => {
		const key = `controls_${category}`;
		const controls = settings[key] || [];
		return controls.includes(controlId);
	};

	const toggleControl = (category, controlId, enabled) => {
		const key = `controls_${category}`;
		let controls = [...(settings[key] || [])];

		if (enabled && !controls.includes(controlId)) {
			controls.push(controlId);
		} else if (!enabled) {
			controls = controls.filter((c) => c !== controlId);
		}

		onChange(key, controls);
	};

	const ControlSection = ({ title, category, controls }) => (
		<PanelBody title={title} initialOpen={category === 'header'}>
			<div className="space-y-3">
				{controls.map((control) => (
					<Toggle
						key={control.id}
						label={
							<span className="flex items-center gap-2">
								<span className="inline-flex h-6 w-10 items-center justify-center rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-500">
									{control.icon}
								</span>
								{control.label}
							</span>
						}
						help={control.description}
						checked={getControlState(category, control.id)}
						onChange={(val) => toggleControl(category, control.id, val)}
					/>
				))}
			</div>
		</PanelBody>
	);

	const PreviewRow = ({ label, controls, categoryKey }) => (
		<div className="flex items-center gap-3">
			<span className="w-20 text-xs font-medium text-slate-500">{label}</span>
			<div className="flex flex-wrap gap-2">
				{controls
					.filter((c) => getControlState(categoryKey, c.id))
					.map((c) => (
						<span
							key={c.id}
							className="inline-flex h-7 items-center justify-center rounded-md border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-600"
							title={c.label}
						>
							{c.icon}
						</span>
					))}
				{!settings[categoryKey]?.length && (
					<span className="text-xs text-slate-400">None</span>
				)}
			</div>
		</div>
	);

	return (
		<div className="space-y-4">
			<div>
				<h3 className="text-lg font-semibold text-slate-900">
					{__('Chat Controls', 'agentflow-ai')}
				</h3>
				<p className="mt-1 text-sm text-slate-500">
					{__('Enable or disable control buttons in the chat widget.', 'agentflow-ai')}
				</p>
			</div>

			<Panel>
				<ControlSection
					title={__('Header Controls', 'agentflow-ai')}
					category="header"
					controls={HEADER_CONTROLS}
				/>
				<ControlSection
					title={__('Message Controls', 'agentflow-ai')}
					category="message"
					controls={MESSAGE_CONTROLS}
				/>
				<ControlSection
					title={__('Footer Controls', 'agentflow-ai')}
					category="footer"
					controls={FOOTER_CONTROLS}
				/>
				<ControlSection
					title={__('Floating Controls', 'agentflow-ai')}
					category="floating"
					controls={FLOATING_CONTROLS}
				/>
			</Panel>

			<div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
				<h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500">
					{__('Active Controls Preview', 'agentflow-ai')}
				</h4>
				<div className="mt-3 space-y-3">
					<PreviewRow label={__('Header', 'agentflow-ai')} controls={HEADER_CONTROLS} categoryKey="controls_header" />
					<PreviewRow label={__('Messages', 'agentflow-ai')} controls={MESSAGE_CONTROLS} categoryKey="controls_message" />
					<PreviewRow label={__('Footer', 'agentflow-ai')} controls={FOOTER_CONTROLS} categoryKey="controls_footer" />
				</div>
			</div>
		</div>
	);
}

ChatControlsManager.propTypes = {
	settings: PropTypes.object.isRequired,
	onChange: PropTypes.func.isRequired,
};
