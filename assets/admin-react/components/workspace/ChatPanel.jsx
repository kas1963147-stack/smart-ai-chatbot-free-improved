/**
 * Chat Panel - Pure Tailwind CSS
 *
 * Premium chat interface with:
 * - Beautiful message bubbles with role-based styling
 * - Message toolbar with copy, feedback, share, regenerate
 * - Collapsible long messages
 * - Smooth animations
 * - Dark mode support
 */
import { useState, useEffect, useRef, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import DOMPurify from 'dompurify';
import { Bot, Copy, ThumbsUp, ThumbsDown, Share2, RotateCcw, Mic, FileEdit, Search, Settings, BarChart3, User, ChevronDown } from 'lucide-react';
import { cn } from '../../ui/utils';

import ToolExecutionCard from './ToolExecutionCard';
import { useToast } from '../shared/Toast';

// Message length threshold for collapsible
const COLLAPSE_THRESHOLD = 500;

// Quick action suggestions
const QUICK_ACTIONS = [
	{ id: 'create', IconComponent: FileEdit, label: 'Create content', description: 'Pages, posts & products' },
	{ id: 'search', IconComponent: Search, label: 'Search site', description: 'Find content & data' },
	{ id: 'settings', IconComponent: Settings, label: 'Update settings', description: 'Configure your site' },
	{ id: 'analyze', IconComponent: BarChart3, label: 'Analyze data', description: 'Orders & analytics' },
];

/**
 * Enhanced markdown-to-HTML converter
 */
function renderMarkdown(text) {
	if (!text) return '';

	let html = text;
	
		// Because Regex often misses edge cases with HTML entities or nested markdown, we use robust index-based matching
		if (html && (html.indexOf('OPTIONS') !== -1 || html.indexOf('OPTIONS&#93;') !== -1)) {
			try {
				let startStr = '[OPTIONS]';
				let endStr = '[/OPTIONS]';
				let startIndex = html.indexOf(startStr);
				let endIndex = html.indexOf(endStr);
				
				// Fallback to HTML entity versions
				if (startIndex === -1) startIndex = html.indexOf('&#91;OPTIONS&#93;');
				if (endIndex === -1) endIndex = html.indexOf('&#91;/OPTIONS&#93;');

				if (startIndex !== -1 && endIndex !== -1 && endIndex > startIndex) {
					let innerText = html.substring(startIndex + startStr.length, endIndex);
					let cleanJson = innerText.replace(/```json/gi, '').replace(/```/g, '').replace(/<[^>]*>?/gm, '').trim();
					let options = [];
					// Replace HTML entity quotes and smart/typographic quotes with standard quotes
					let unescapedJson = cleanJson
						.replace(/&quot;/g, '"')
						.replace(/&apos;/g, "'")
						.replace(/[“”]/g, '"')
						.replace(/[‘’]/g, "'");
						
					try {
						options = JSON.parse(unescapedJson);
					} catch (e) {
						// Fallback: match anything between literally any type of quotes
						let matches = unescapedJson.match(/["']([^"']+)["']/g);
						if (matches) options = matches.map(m => m.replace(/["']/g, ''));
					}

					if (Array.isArray(options) && options.length > 0) {
						const buttonsHtml = options.map(opt => 
							`<span class="inline-block mt-2 mr-2 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 rounded-full text-sm font-medium shadow-sm transition-colors cursor-default">${opt}</span>`
						).join('');
						let finalReplacement = `<div class="mt-3 flex flex-wrap pt-1 border-t border-gray-100 dark:border-slate-800">${buttonsHtml}</div>`;
						
						html = html.substring(0, startIndex) + finalReplacement + html.substring(endIndex + endStr.length);
						
						// Clean up any stray asterisks that surrounded it
						html = html.replace(/\*\*<div class="mt-3/g, '<div class="mt-3').replace(/<\/div>\*\*/g, '</div>');
					}
				}
			} catch (e) {
				console.error("Failed to parse options block", e);
			}
		}

	html = html
		// Code blocks with language
		.replace(
			/```(\w+)?\n([\s\S]*?)```/g,
			(match, lang, code) => {
				const langClass = lang ? `language-${lang}` : 'language-plaintext';
				const escapedCode = code
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
				return `<pre class="bg-slate-900 dark:bg-black rounded-lg p-4 overflow-x-auto my-3 text-sm"><code class="${langClass} text-gray-100">${escapedCode}</code></pre>`;
			}
		)
		// Detect file paths and convert to download links
		.replace(
			/(?:File location:|File:|Created file:|Saved to:)?\s*`?(wp-content\/uploads\/[^\s`<>]+)`?/gi,
			(match, filePath) => {
				const fileName = filePath.split('/').pop();
				const siteUrl = window.swcChatbot?.siteUrl || '';
				const fullUrl = `${siteUrl}/${filePath}`;
				return `<a href="${fullUrl}" download="${fileName}" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 rounded-md text-sm hover:bg-indigo-200 dark:hover:bg-indigo-500/30 transition-colors" target="_blank" rel="noopener">
					<span></span>
					<span>${fileName}</span>
				</a>`;
			}
		)
		// Inline code
		.replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-slate-800 text-pink-600 dark:text-pink-400 rounded text-sm font-mono">$1</code>')
		// Bold
		.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
		// Italic
		.replace(/\*([^*]+)\*/g, '<em>$1</em>')
		// Links
		.replace(
			/\[([^\]]+)\]\(([^)]+)\)/g,
			'<a href="$2" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">$1</a>'
		)
		// Lists
		.replace(/^\s*[-*]\s+(.+)$/gm, '<li>$1</li>')
		// Line breaks
		.replace(/\n/g, '<br />');

	// Wrap consecutive <li> in <ul>
	html = html.replace(/(<li>.*?<\/li>)(\s*<br \/>)*(<li>)/g, '$1$3');
	html = html.replace(/(<li>.*?<\/li>)+/g, '<ul class="list-disc list-inside space-y-1 my-2">$&</ul>');

	return DOMPurify.sanitize(html, {
		ADD_ATTR: ['download', 'target', 'rel'],
		ADD_TAGS: ['a']
	});
}

/**
 * Check if two messages should be grouped
 */
function shouldGroupMessages(prev, curr) {
	if (!prev || !curr) return false;
	if (prev.role !== curr.role) return false;

	const prevTime = new Date(prev.timestamp).getTime();
	const currTime = new Date(curr.timestamp).getTime();
	return Math.abs(currTime - prevTime) < 120000;
}

/**
 * Single Message Component - Tailwind Style
 */
function Message({ msg, isGrouped, darkMode }) {
	const [isCollapsed, setIsCollapsed] = useState(true);
	const [showToolbar, setShowToolbar] = useState(false);
	const toast = useToast();

	const isLong = msg.content && msg.content.length > COLLAPSE_THRESHOLD;
	const displayContent = isLong && isCollapsed
		? msg.content.slice(0, COLLAPSE_THRESHOLD) + '...'
		: msg.content;

	const handleCopy = useCallback(async () => {
		try {
			await navigator.clipboard.writeText(msg.content || '');
			toast.success(__('Copied to clipboard', 'smart-woo-chatbot'));
		} catch (err) {
			toast.error(__('Failed to copy', 'smart-woo-chatbot'));
		}
	}, [msg.content, toast]);

	const handleThumbsUp = useCallback(() => {
		toast.success(__('Feedback submitted', 'smart-woo-chatbot'));
	}, [toast]);

	const handleThumbsDown = useCallback(() => {
		toast.success(__('Feedback submitted', 'smart-woo-chatbot'));
	}, [toast]);

	const handleShare = useCallback(async () => {
		try {
			if (navigator.share) {
				await navigator.share({
					title: 'AI Response',
					text: msg.content,
				});
			} else {
				await navigator.clipboard.writeText(msg.content);
				toast.success(__('Message copied to clipboard', 'smart-woo-chatbot'));
			}
		} catch (err) {
			// User cancelled
		}
	}, [msg.content, toast]);

	const handleRegenerate = useCallback(() => {
		toast.info(__('Regenerating response...', 'smart-woo-chatbot'));
	}, [toast]);

	const isUser = msg.role === 'user';

	return (
		<div
			className={cn(
				'flex gap-3 px-4 py-3 group',
				isUser ? 'flex-row-reverse' : 'flex-row',
				isGrouped && 'pt-1'
			)}
			onMouseEnter={() => setShowToolbar(true)}
			onMouseLeave={() => setShowToolbar(false)}
		>
			{/* Avatar */}
			{!isGrouped && (
				<div className={cn(
					'w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0',
					isUser
						? 'bg-indigo-500 text-white'
						: 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300'
				)}>
					{isUser ? <User className="w-4 h-4" /> : <Bot className="w-4 h-4" />}
				</div>
			)}

			{/* Message Content */}
			<div className={cn('flex flex-col max-w-[75%]', isGrouped && (isUser ? 'mr-11' : 'ml-11'))}>
				{/* Tool Calls */}
				{msg.role === 'assistant' && msg.tool_calls && msg.tool_calls.length > 0 && (
					<div className="space-y-2 mb-2">
						{msg.tool_calls.map((tool, toolIndex) => (
							<ToolExecutionCard
								key={toolIndex}
								toolName={tool.name || tool.function?.name || 'Tool'}
								params={tool.arguments || tool.function?.arguments || {}}
								result={tool.result}
								status={tool.status || 'completed'}
							/>
						))}
					</div>
				)}

				{/* Message Bubble */}
				<div className={cn(
					'relative rounded-2xl px-4 py-3 text-sm leading-relaxed',
					isUser
						? 'bg-indigo-500 text-white rounded-br-sm'
						: 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white rounded-bl-sm'
				)}>
					<div
						className="prose prose-sm dark:prose-invert max-w-none [&_a]:text-indigo-600 dark:[&_a]:text-indigo-400"
						dangerouslySetInnerHTML={{ __html: renderMarkdown(displayContent) }}
					/>

					{/* Show More/Less Button */}
					{isLong && (
						<button
							className="mt-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
							onClick={() => setIsCollapsed(!isCollapsed)}
						>
							{isCollapsed
								? __('Show more', 'smart-woo-chatbot')
								: __('Show less', 'smart-woo-chatbot')}
						</button>
					)}

					{/* Message Toolbar - AI messages only */}
					{msg.role === 'assistant' && showToolbar && (
						<div className="absolute -bottom-8 left-0 flex items-center gap-1 p-1 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 opacity-0 group-hover:opacity-100 transition-opacity">
							<button
								className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 transition-colors"
								onClick={handleCopy}
								title={__('Copy', 'smart-woo-chatbot')}
							>
								<Copy className="w-3.5 h-3.5" />
							</button>
							<button
								className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 transition-colors"
								onClick={handleThumbsUp}
								title={__('Thumbs up', 'smart-woo-chatbot')}
							>
								<ThumbsUp className="w-3.5 h-3.5" />
							</button>
							<button
								className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 transition-colors"
								onClick={handleThumbsDown}
								title={__('Thumbs down', 'smart-woo-chatbot')}
							>
								<ThumbsDown className="w-3.5 h-3.5" />
							</button>
							<button
								className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 transition-colors"
								onClick={handleShare}
								title={__('Share', 'smart-woo-chatbot')}
							>
								<Share2 className="w-3.5 h-3.5" />
							</button>
							<button
								className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 transition-colors"
								onClick={handleRegenerate}
								title={__('Regenerate', 'smart-woo-chatbot')}
							>
								<RotateCcw className="w-3.5 h-3.5" />
							</button>
						</div>
					)}
				</div>

				{/* Timestamp */}
				<span className="text-[10px] text-gray-400 dark:text-slate-500 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
					{msg.timestamp && new Date(msg.timestamp).toLocaleTimeString()}
				</span>
			</div>
		</div>
	);
}

Message.propTypes = {
	msg: PropTypes.object.isRequired,
	isGrouped: PropTypes.bool,
	darkMode: PropTypes.bool,
};

/**
 * ChatPanel Component - Pure Tailwind
 */
export default function ChatPanel({ messages, isLoading, onQuickAction, darkMode }) {
	const containerRef = useRef(null);
	const [showScrollBtn, setShowScrollBtn] = useState(false);

	// Handle scroll position
	const handleScroll = useCallback(() => {
		if (!containerRef.current) return;
		const { scrollTop, scrollHeight, clientHeight } = containerRef.current;
		setShowScrollBtn(scrollHeight - scrollTop - clientHeight > 200);
	}, []);

	// Auto-scroll to bottom on new messages
	useEffect(() => {
		if (containerRef.current) {
			containerRef.current.scrollTop = containerRef.current.scrollHeight;
		}
	}, [messages, isLoading]);

	const scrollToBottom = useCallback(() => {
		if (containerRef.current) {
			containerRef.current.scrollTo({
				top: containerRef.current.scrollHeight,
				behavior: 'smooth'
			});
		}
	}, []);

	// Process messages for grouping
	const groupedMessages = useMemo(() => {
		return messages.map((msg, index) => ({
			...msg,
			isGrouped: shouldGroupMessages(messages[index - 1], msg)
		}));
	}, [messages]);

	// Empty state
	if (messages.length === 0 && !isLoading) {
		return (
			<div className="flex-1 flex flex-col items-center justify-center p-8">
				<div className="text-center max-w-md">
					<div className="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center">
						<Mic className="w-8 h-8 text-indigo-500" />
					</div>
					<h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
						{__('How can I help you today?', 'smart-woo-chatbot')}
					</h3>
					<p className="text-gray-500 dark:text-slate-400 mb-6">
						{__("I'm here to assist with your WordPress site.", 'smart-woo-chatbot')}
					</p>

					<div className="grid grid-cols-2 gap-3">
						{QUICK_ACTIONS.map((action) => (
							<button
								key={action.id}
								className="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-all text-left group"
								onClick={() => onQuickAction && onQuickAction(action.id)}
							>
								<span className="w-9 h-9 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-600 dark:text-slate-300 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-500/20 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
									<action.IconComponent className="w-4 h-4" />
								</span>
								<span className="flex flex-col">
									<strong className="text-sm font-medium text-gray-900 dark:text-white">{action.label}</strong>
									<span className="text-xs text-gray-500 dark:text-slate-400">{action.description}</span>
								</span>
							</button>
						))}
					</div>
				</div>
			</div>
		);
	}

	return (
		<div
			className="flex-1 overflow-y-auto relative"
			ref={containerRef}
			onScroll={handleScroll}
		>
			<div className="py-4">
				{groupedMessages.map((msg, index) => (
					<Message
						key={index}
						msg={msg}
						isGrouped={msg.isGrouped}
						darkMode={darkMode}
					/>
				))}

				{/* Loading Indicator */}
				{isLoading && (
					<div className="flex gap-3 px-4 py-3">
						<div className="w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-600 dark:text-slate-300">
							<Bot className="w-4 h-4" />
						</div>
						<div className="bg-gray-100 dark:bg-slate-800 rounded-2xl rounded-bl-sm px-4 py-3">
							<div className="flex gap-1">
								<span className="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style={{ animationDelay: '0s' }}></span>
								<span className="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style={{ animationDelay: '0.2s' }}></span>
								<span className="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style={{ animationDelay: '0.4s' }}></span>
							</div>
						</div>
					</div>
				)}
			</div>

			{/* Scroll to bottom button */}
			{showScrollBtn && (
				<button
					className="fixed bottom-24 right-8 w-10 h-10 rounded-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-lg flex items-center justify-center text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
					onClick={scrollToBottom}
					title={__('Scroll to bottom', 'smart-woo-chatbot')}
				>
					<ChevronDown className="w-5 h-5" />
				</button>
			)}
		</div>
	);
}

ChatPanel.propTypes = {
	messages: PropTypes.arrayOf(
		PropTypes.shape({
			role: PropTypes.oneOf(['user', 'assistant', 'system']).isRequired,
			content: PropTypes.string,
			tool_calls: PropTypes.array,
			timestamp: PropTypes.string,
		})
	).isRequired,
	isLoading: PropTypes.bool,
	onQuickAction: PropTypes.func,
	darkMode: PropTypes.bool,
};

ChatPanel.defaultProps = {
	isLoading: false,
	onQuickAction: null,
	darkMode: false,
};
