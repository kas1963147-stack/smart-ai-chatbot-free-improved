/**
 * Chat Preview Component
 *
 * Real-time preview of the chatbot reflecting the user's actual customizations.
 * Shows: display name, avatar, theme colors, toggle icon/shape, greeting message, etc.
 */
import { useState } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Icon, Input, cn } from '../ui';
import { desktop, mobile } from '@wordpress/icons';

// Toggle icon SVGs matching the ChatEditor icon options
const TOGGLE_ICONS = {
	chat: (
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
		</svg>
	),
	message: (
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
		</svg>
	),
	support: (
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
		</svg>
	),
	headset: (
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M3 18v-6a9 9 0 0118 0v6" />
			<path d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z" />
		</svg>
	),
};

// Header control SVGs
const HEADER_ICONS = {
	reset: (
		<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<polyline points="23 4 23 10 17 10" />
			<path d="M20.49 15a9 9 0 11-2.12-9.36L23 10" />
		</svg>
	),
	sound: (
		<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
			<path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07" />
		</svg>
	),
	minimize: (
		<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<line x1="5" y1="12" x2="19" y2="12" />
		</svg>
	),
	close: (
		<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<line x1="18" y1="6" x2="6" y2="18" />
			<line x1="6" y1="6" x2="18" y2="18" />
		</svg>
	),
};

export default function ChatPreview({ settings = {}, displayName = '', behavior = {}, engagement = {} }) {
	const [viewMode, setViewMode] = useState('desktop');
	const [isFullscreen, setIsFullscreen] = useState(false);

	// Generate CSS variables from settings
	const primaryColor = settings.color_primary || '#6366f1';
	const primaryHover = settings.color_primary_hover || '#4f46e5';

	// Compute font size directly — CSS variables can be overridden by admin stylesheets
	const baseFontSize = Number(settings.font_size_base) || 14;
	const baseLineHeight = Number(settings.line_height) || 1.5;
	const fontFamily =
		settings.font_family === 'system'
			? '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
			: settings.font_family || '-apple-system, sans-serif';

	const previewVars = {
		'--preview-primary': primaryColor,
		'--preview-primary-hover': primaryHover,
		'--preview-bg-main': settings.color_bg_main || '#ffffff',
		'--preview-bg-light': settings.color_bg_light || '#f8fafc',
		'--preview-text-primary': settings.color_text_primary || '#1e293b',
		'--preview-text-secondary': settings.color_text_secondary || '#64748b',
		'--preview-border': settings.color_border || '#e2e8f0',
		'--preview-radius': `${settings.border_radius || 16}px`,
		'--preview-bubble-radius': `${settings.bubble_radius || 18}px`,
		'--preview-window-width': `${settings.window_width || 380}px`,
		'--preview-window-height': `${settings.window_height || 550}px`,
	};

	// Header controls from settings
	const headerControls = settings.controls_header || ['close'];

	// Effects & Animation
	const shadowIntensity = settings.shadow_intensity || 'medium';
	const animationSpeedStr = settings.animation_speed || 'normal';
	const enableHover = settings.enable_hover_effects !== false;
	const enableGlass = !!settings.enable_glassmorphism;
	
	// Determine animation duration
	let animDuration = '0.3s';
	if (animationSpeedStr === 'fast') animDuration = '0.15s';
	else if (animationSpeedStr === 'slow') animDuration = '0.5s';
	else if (animationSpeedStr === 'none') animDuration = '0s';

	// Determine window shadow
	const windowShadows = {
		none: '0 0 0 1px rgba(0, 0, 0, 0.05)',
		light: '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.05)',
		medium: '0 20px 40px -10px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05)',
		heavy: '0 30px 60px -15px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05)',
	};
	const previewShadow = windowShadows[shadowIntensity] || windowShadows.medium;

	// glassmorphism background parsing (just simplifying to rgba for preview)
	const getGlassBg = (colorHex, alpha) => {
		if (!enableGlass) return colorHex;
		// very basic hex to rgba for typical 6-char hex
		if (colorHex?.length === 7) {
			const r = parseInt(colorHex.slice(1, 3), 16);
			const g = parseInt(colorHex.slice(3, 5), 16);
			const b = parseInt(colorHex.slice(5, 7), 16);
			return `rgba(${r}, ${g}, ${b}, ${alpha})`;
		}
		return colorHex;
	};

	const windowBg = getGlassBg(settings.color_bg_main || '#ffffff', 0.9);

	// Widget constraints based on view mode (desktop allows user settings, mobile is fixed)
	const widgetWidth = viewMode === 'mobile' ? 320 : (settings.window_width || 380);
	const widgetHeight = viewMode === 'mobile' ? 550 : (settings.window_height || 555);

	// Greeting message from behavior or fallback
	const greetingMsg = behavior?.greeting_message || 'Hi there! How can I help you today?';
	const placeholderText = behavior?.placeholder_text || 'Type a message...';

	// Quick replies from engagement
	const quickReplies = engagement?.quick_replies_enabled && engagement?.quick_replies?.length > 0
		? engagement.quick_replies.slice(0, 3)
		: [{ text: 'Track Order' }, { text: 'Products' }];

	// Sample conversation using real greeting
	const messages = [
		{ type: 'bot', text: greetingMsg },
		{ type: 'user', text: 'I am looking for running shoes.' },
		{ type: 'bot', text: 'Great choice! Here are some popular running shoes.' },
	];

	// Avatar rendering
	const avatarContent = settings.avatar || null;
	const renderAvatar = (size = 'sm') => {
		const sizeClasses = size === 'lg' ? 'h-10 w-10 text-lg' : 'h-8 w-8 text-sm';
		if (avatarContent) {
			// Check if it's a URL (starts with http or /)
			if (typeof avatarContent === 'string' && (avatarContent.startsWith('http') || avatarContent.startsWith('/'))) {
				return (
					<div className={`flex items-center justify-center rounded-full bg-white/20 overflow-hidden ${sizeClasses}`}>
						<img src={avatarContent} alt="" className="w-full h-full object-cover" />
					</div>
				);
			}
			// It's an emoji or text
			return (
				<div className={`flex items-center justify-center rounded-full bg-white/20 ${sizeClasses}`}>
					{avatarContent}
				</div>
			);
		}
		// Default AI text
		return (
			<div className={`flex items-center justify-center rounded-full bg-white/20 font-semibold ${sizeClasses}`}>
				AI
			</div>
		);
	};

	// Toggle button rendering based on shape and icon settings
	const toggleShape = settings.toggle_shape || 'circle';
	const toggleIcon = settings.toggle_icon || 'chat';
	const toggleLabel = settings.toggle_label || 'Chat with us';
	const toggleGlow = settings.toggle_glow || false;
	const togglePulse = settings.toggle_pulse || false;
	const toggleBorder = settings.toggle_border || false;
	const toggleBorderColor = settings.toggle_border_color || '#ffffff';
	const toggleShadow = settings.toggle_shadow || 'medium';
	const toggleSize = Number(settings.toggle_size) || 60;

	// Shadow styles
	const shadowStyles = {
		none: 'none',
		small: '0 2px 8px rgba(0,0,0,0.15)',
		medium: '0 4px 16px rgba(0,0,0,0.2)',
		large: '0 8px 32px rgba(0,0,0,0.3)',
	};

	// Shape styles
	const getToggleShapeStyle = () => {
		const base = {
			background: `linear-gradient(135deg, ${primaryColor}, ${primaryHover})`,
			boxShadow: shadowStyles[toggleShadow] || shadowStyles.medium,
			border: toggleBorder ? `2px solid ${toggleBorderColor}` : 'none',
			cursor: 'pointer',
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			color: 'white',
			transition: `all ${animDuration} ease`,
			transform: enableHover ? 'scale(1)' : 'none',
		};

		switch (toggleShape) {
			case 'pill':
				return { ...base, borderRadius: `${toggleSize / 2}px`, padding: toggleLabel ? '10px 20px' : '0 16px', height: `${toggleSize}px`, minWidth: toggleLabel ? 'auto' : `${toggleSize * 1.8}px`, gap: '8px' };
			case 'rounded_square':
				return { ...base, borderRadius: '16px', width: `${toggleSize}px`, height: `${toggleSize}px` };
			case 'card':
				return { ...base, borderRadius: '16px', padding: '10px 16px', height: 'auto', gap: '10px' };
			default: // circle
				return { ...base, borderRadius: '50%', width: `${toggleSize}px`, height: `${toggleSize}px` };
		}
	};

	// Render toggle icon content
	const renderToggleIcon = () => {
		if (toggleIcon === 'custom') {
			return <span className="text-lg">{settings.toggle_custom_icon || ''}</span>;
		}
		if (toggleIcon === 'none') {
			return null;
		}
		return TOGGLE_ICONS[toggleIcon] || TOGGLE_ICONS.chat;
	};

	// Render toggle button
	const renderToggleButton = () => {
		const style = getToggleShapeStyle();
		const showLabel = toggleShape === 'pill' || toggleShape === 'card';

		return (
			<div style={{ position: 'relative', display: 'inline-flex' }}>
				{/* Pulse animation ring */}
				{togglePulse && (
					<span
						className="animate-ping absolute inset-0 opacity-30"
						style={{
							borderRadius: style.borderRadius,
							background: primaryColor,
						}}
					/>
				)}
				{/* Glow effect */}
				{toggleGlow && (
					<span
						className="absolute inset-0"
						style={{
							borderRadius: style.borderRadius,
							boxShadow: `0 0 20px ${primaryColor}60, 0 0 40px ${primaryColor}30`,
						}}
					/>
				)}
				<div style={style} className="relative">
					{toggleShape === 'card' ? (
						<>
							{toggleIcon !== 'none' && (
								<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-white/20">
									{renderToggleIcon()}
								</div>
							)}
							<div className="text-left">
								<div className="text-xs font-semibold leading-tight">{toggleLabel}</div>
								<div className="text-[10px] text-white/70">Online</div>
							</div>
						</>
					) : (
						<>
							{renderToggleIcon()}
							{showLabel && (
								<span className="text-sm font-semibold whitespace-nowrap">{toggleLabel}</span>
							)}
						</>
					)}
				</div>
			</div>
		);
	};

	// Render Widget Window
	const renderWidget = (isExpanded = false) => {
		// When expanded, we overwrite some fixed styles to make it larger
		const containerStyle = {
			width: isExpanded ? '100%' : widgetWidth,
			background: 'var(--preview-bg-main)',
			borderRadius: 'var(--preview-radius)',
			fontFamily: fontFamily,
			fontSize: baseFontSize + 'px',
			lineHeight: baseLineHeight,
		};
		
		if (isExpanded) {
			containerStyle.maxWidth = '800px';
			containerStyle.height = '80vh';
			containerStyle.maxHeight = '800px';
		} else {
			containerStyle.maxHeight = '450px';
		}

		return (
			<div
				className="flex flex-col overflow-hidden shadow-xl w-full"
				style={containerStyle}
			>
				{/* Header */}
				<div
					className="flex items-center justify-between px-4 py-3 text-white"
					style={{
						background: `linear-gradient(135deg, ${primaryColor}, ${primaryHover})`,
					}}
				>
					<div className="flex items-center gap-3">
						{renderAvatar()}
						<div>
							<div className="font-semibold" style={{ fontSize: baseFontSize + 'px' }}>
								{displayName || 'Shopping Assistant'}
							</div>
							<div className="flex items-center gap-1.5 text-white/80" style={{ fontSize: Math.round(baseFontSize * 0.78) + 'px' }}>
								<span
									className="w-1.5 h-1.5 rounded-full"
									style={{ backgroundColor: '#34d399', boxShadow: '0 0 4px #34d399' }}
								/>
								Online
							</div>
						</div>
					</div>
					<div className="flex items-center gap-1">
						{headerControls.includes('reset') && (
							<span className="flex items-center justify-center w-7 h-7 rounded-lg hover:bg-white/15 transition-colors cursor-pointer">
								{HEADER_ICONS.reset}
							</span>
						)}
						{headerControls.includes('sound') && (
							<span className="flex items-center justify-center w-7 h-7 rounded-lg hover:bg-white/15 transition-colors cursor-pointer">
								{HEADER_ICONS.sound}
							</span>
						)}
						{headerControls.includes('minimize') && (
							<span
								className="flex items-center justify-center w-7 h-7 rounded-lg hover:bg-white/15 transition-colors cursor-pointer"
								onClick={isExpanded ? () => setIsFullscreen(false) : undefined}
							>
								{HEADER_ICONS.minimize}
							</span>
						)}
						{headerControls.includes('close') && (
							<span
								className="flex items-center justify-center w-7 h-7 rounded-lg hover:bg-white/15 transition-colors cursor-pointer"
								onClick={isExpanded ? () => setIsFullscreen(false) : undefined}
							>
								{HEADER_ICONS.close}
							</span>
						)}
					</div>
				</div>

				{/* Messages */}
				<div
					className="flex flex-1 flex-col gap-3 overflow-y-auto p-4"
					style={{ background: 'var(--preview-bg-light)' }}
				>
					{messages.map((msg, idx) => (
						<div
							key={idx}
							className={cn('flex', msg.type === 'user' ? 'justify-end' : 'justify-start')}
						>
							<div
								className="max-w-[85%] px-3 py-2 shadow-sm"
								style={
									msg.type === 'user'
										? {
											background: `linear-gradient(135deg, ${primaryColor}, ${primaryHover})`,
											color: 'white',
											borderRadius: 'var(--preview-bubble-radius)',
											borderBottomRightRadius: 4,
											fontSize: baseFontSize + 'px',
											lineHeight: baseLineHeight,
										}
										: {
											background: '#ffffff',
											color: 'var(--preview-text-primary)',
											borderRadius: 'var(--preview-bubble-radius)',
											borderBottomLeftRadius: 4,
											fontSize: baseFontSize + 'px',
											lineHeight: baseLineHeight,
										}
								}
							>
								{msg.text}
							</div>
						</div>
					))}
				</div>

				{/* Quick Questions */}
				<div className="flex items-center gap-2 border-t px-3 py-2" style={{ borderColor: 'var(--preview-border)' }}>
					{quickReplies.map((reply, idx) => (
						<span
							key={idx}
							className="rounded-full border px-2 py-1"
							style={{
								borderColor: 'var(--preview-border)',
								background: 'var(--preview-bg-light)',
								color: 'var(--preview-text-secondary)',
								fontSize: Math.round(baseFontSize * 0.78) + 'px',
							}}
						>
							{reply.text || reply}
						</span>
					))}
				</div>

				{/* Input */}
				<div className="flex items-center gap-2 border-t bg-white px-3 py-3" style={{ borderColor: 'var(--preview-border)' }}>
					<Input
						placeholder={placeholderText}
						readOnly
						className="h-9"
						style={{ fontSize: Math.round(baseFontSize * 0.85) + 'px' }}
					/>
					<button
						type="button"
						className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white"
						style={{
							background: `linear-gradient(135deg, ${primaryColor}, ${primaryHover})`,
						}}
					>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
							<line x1="22" y1="2" x2="11" y2="13" />
							<polygon points="22 2 15 22 11 13 2 9 22 2" />
						</svg>
					</button>
				</div>
			</div>
		);
	};

	return (
		<div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm relative">
			{/* Inject dynamic hover styles since inline-hover is annoying to do cleanly without styled-components */}
			<style>{`
				.swc-preview-hover-target {
					transition: all ${animDuration} ease !important;
				}
				${enableHover ? `
					.swc-preview-hover-target:hover {
						transform: scale(1.03) !important;
						filter: brightness(1.05) !important;
					}
					.swc-preview-btn-hover:hover {
						opacity: 0.85 !important;
					}
				` : ''}
			`}</style>

			<div className="flex items-center justify-between">
				<h3 className="text-sm font-semibold text-slate-900">
					{__('Live Preview', 'agentflow-ai')}
				</h3>
				<div className="inline-flex rounded-full bg-slate-100 p-1">
					<button
						type="button"
						onClick={() => setIsFullscreen(!isFullscreen)}
						className={cn(
							'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition',
							isFullscreen ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'
						)}
						title={isFullscreen ? 'Minimize Preview' : 'Expand Preview'}
					>
						{isFullscreen ? (
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
								<path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
							</svg>
						) : (
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
								<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
							</svg>
						)}
						{isFullscreen ? 'Minimize' : 'Expand'}
					</button>
					<button
						type="button"
						onClick={() => { setViewMode('desktop'); setIsFullscreen(false); }}
						className={cn(
							'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition ml-2',
							viewMode === 'desktop' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'
						)}
					>
						<Icon icon={desktop} size={14} />
						{__('Desktop', 'agentflow-ai')}
					</button>
					<button
						type="button"
						onClick={() => setViewMode('mobile')}
						className={cn(
							'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition',
							viewMode === 'mobile' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'
						)}
					>
						<Icon icon={mobile} size={14} />
						{__('Mobile', 'agentflow-ai')}
					</button>
				</div>
			</div>

			<div
				className={`mt-4 flex items-center justify-center rounded-2xl bg-slate-50 p-4 relative overflow-auto min-h-[500px]`}
				style={{ ...previewVars }}
			>
				{/* The nested widget box inline for normal view */}
				{!isFullscreen && renderWidget(false)}
			</div>

			{/* Fullscreen Popup Modal — rendered via portal to escape sticky header stacking context */}
			{isFullscreen && createPortal(
				<div 
					className="fixed inset-0 z-[99999] flex justify-center items-center overflow-y-auto bg-slate-900/60 backdrop-blur-sm p-8"
					style={{ ...previewVars }}
				>
					{/* Click-away backdrop */}
					<div className="fixed inset-0 z-0 cursor-pointer" onClick={() => setIsFullscreen(false)} />
					
					{/* Widget Wrapper */}
					<div className="relative z-10 my-auto w-full max-w-4xl flex items-center justify-center" onClick={(e) => e.stopPropagation()}>
						{renderWidget(true)}
					</div>
				</div>,
				document.body
			)}
			
			<div
				className="mt-3 flex items-center"
				style={{ justifyContent: settings.position === 'left' ? 'flex-start' : 'flex-end' }}
			>
				<div className={enableHover ? 'swc-preview-hover-target' : ''}>
					{renderToggleButton()}
				</div>
			</div>
		</div>
	);
}

ChatPreview.propTypes = {
	settings: PropTypes.object.isRequired,
	displayName: PropTypes.string,
	behavior: PropTypes.object,
	engagement: PropTypes.object,
};
