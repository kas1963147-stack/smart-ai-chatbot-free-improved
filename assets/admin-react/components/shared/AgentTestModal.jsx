/**
 * Agent Test Preview Component — Metronic v9 Styled
 *
 * Allows testing an agent with a polished live chat preview.
 * Uses Tailwind CSS classes for Metronic-aligned styling.
 *
 * @version 2.1.0
 * @package SWC
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import Modal from './Modal';
import { Button } from '../ui';

/**
 * Format a timestamp to a human-readable time string (e.g. "5:23 PM")
 */
function formatTime(date) {
	return date.toLocaleTimeString([], {
		hour: 'numeric',
		minute: '2-digit',
	});
}

/**
 * Agent Test Modal
 */
export default function AgentTestModal({ isOpen, onClose, agent }) {
	const [messages, setMessages] = useState([]);
	const [input, setInput] = useState('');
	const [loading, setLoading] = useState(false);
	const [sessionId] = useState(() => `test-${Date.now()}`);
	const messagesEndRef = useRef(null);
	const inputRef = useRef(null);

	const agentAvatar = agent?.avatar || agent?.config?.avatar || '';
	const agentName =
		agent?.name || __('AI Assistant', 'agentflow-ai');

	// Clear messages when agent changes
	useEffect(() => {
		if (agent) {
			const welcomeMessage =
				agent.config?.welcome_message ||
				agent.welcome_message ||
				__('Hello! How can I help you today?', 'agentflow-ai');
			setMessages([
				{
					role: 'bot',
					content: welcomeMessage,
					time: new Date(),
				},
			]);
		}
	}, [agent]);

	// Auto-scroll to bottom
	useEffect(() => {
		messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
	}, [messages]);

	const handleSend = async () => {
		if (!input.trim() || loading) {
			return;
		}

		const userMessage = input.trim();
		setInput('');

		setMessages((prev) => [
			...prev,
			{ role: 'user', content: userMessage, time: new Date() },
		]);
		setLoading(true);

		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/chat/message',
				method: 'POST',
				data: {
					message: userMessage,
					agent_id: agent?.agent_id || 'default',
					session_id: sessionId,
					test_mode: true,
					history: messages.map((m) => ({
						role: m.role === 'bot' ? 'assistant' : m.role,
						content: m.content,
					})),
				},
			});

			if (response.success) {
				setMessages((prev) => [
					...prev,
					{
						role: 'bot',
						content:
							response.data?.message ||
							response.message ||
							'Response received.',
						time: new Date(),
					},
				]);
			} else {
				setMessages((prev) => [
					...prev,
					{
						role: 'bot',
						content: ` ${response.message ||
							__('Failed to get response', 'agentflow-ai')
							}`,
						time: new Date(),
					},
				]);
			}
		} catch (err) {
			setMessages((prev) => [
				...prev,
				{
					role: 'bot',
					content: ` ${err.message ||
						__('An error occurred', 'agentflow-ai')
						}`,
					time: new Date(),
				},
			]);
		} finally {
			setLoading(false);
			// Refocus input
			inputRef.current?.focus();
		}
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			handleSend();
		}
	};

	const handleClear = () => {
		const welcomeMessage =
			agent?.config?.welcome_message ||
			agent?.welcome_message ||
			__('Hello! How can I help you today?', 'agentflow-ai');
		setMessages([
			{
				role: 'bot',
				content: welcomeMessage,
				time: new Date(),
			},
		]);
	};

	return (
		<Modal
			isOpen={isOpen}
			onClose={onClose}
			title={__('Test Agent', 'agentflow-ai')}
			subtitle={
				agent?.name ? `Testing: ${agent.name}` : undefined
			}
			size="md"
			footer={
				<div className="flex items-center gap-2">
					<Button
						variant="secondary"
						onClick={handleClear}
						icon={<span></span>}
					>
						{__('Clear Chat', 'agentflow-ai')}
					</Button>
					<Button variant="primary" onClick={onClose}>
						{__('Done', 'agentflow-ai')}
					</Button>
				</div>
			}
		>
			{ /* Chat Container */}
			<div className="flex flex-col overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700" style={{ height: '420px' }}>

				{ /* ── Chat Header ── */}
				<div className="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
					<div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-lg">
						{agentAvatar}
					</div>
					<div className="flex flex-col">
						<span className="text-sm font-semibold text-slate-900 dark:text-slate-100">
							{agentName}
						</span>
						<span className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
							<span className="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500" style={{ boxShadow: '0 0 4px rgba(16, 185, 129, 0.6)' }} />
							{__('Online', 'agentflow-ai')}
						</span>
					</div>
				</div>

				{ /* ── Messages Area ── */}
				<div className="flex flex-1 flex-col gap-3 overflow-y-auto bg-slate-50 p-4 dark:bg-slate-950">
					{messages.map((msg, idx) => (
						<div
							key={idx}
							className={`flex gap-2 ${msg.role === 'user' ? 'flex-row-reverse self-end' : 'self-start'}`}
							style={{ maxWidth: '85%', animation: 'swc-msg-slide-in 0.25s ease-out' }}
						>
							{ /* Bot avatar */}
							{msg.role === 'bot' && (
								<div className="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-800">
									{agentAvatar}
								</div>
							)}

							<div className="flex flex-col gap-0.5">
								{ /* Bubble */}
								<div
									className={`rounded-xl px-3.5 py-2.5 text-[13px] leading-relaxed ${msg.role === 'user'
										? 'rounded-br-sm bg-primary text-white'
										: 'rounded-bl-sm border border-slate-200 bg-white text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'
										}`}
								>
									{msg.content}
								</div>
								{ /* Timestamp */}
								{msg.time && (
									<span className={`px-1 text-[10px] text-slate-400 ${msg.role === 'user' ? 'text-right' : 'text-left'}`}>
										{formatTime(msg.time)}
									</span>
								)}
							</div>
						</div>
					))}

					{ /* Typing indicator */}
					{loading && (
						<div className="flex items-start gap-2 self-start" style={{ maxWidth: '85%' }}>
							<div className="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-800">
								{agentAvatar}
							</div>
							<div className="flex gap-1 rounded-xl rounded-bl-sm border border-slate-200 bg-white px-3.5 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
								<span className="inline-block h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style={{ animationDelay: '0ms' }} />
								<span className="inline-block h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style={{ animationDelay: '150ms' }} />
								<span className="inline-block h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style={{ animationDelay: '300ms' }} />
							</div>
						</div>
					)}

					<div ref={messagesEndRef} />
				</div>

				{ /* ── Input Area ── */}
				<div className="flex items-center gap-2.5 border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
					<input
						ref={inputRef}
						type="text"
						value={input}
						onChange={(e) => setInput(e.target.value)}
						onKeyDown={handleKeyDown}
						placeholder={__('Type a message…', 'agentflow-ai')}
						disabled={loading}
						className="flex-1 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:focus:bg-slate-900"
					/>
					<button
						type="button"
						onClick={handleSend}
						disabled={loading || !input.trim()}
						className="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-primary text-white shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
					>
						<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
							<path
								d="M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11"
								stroke="currentColor"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							/>
						</svg>
					</button>
				</div>
			</div>

			{ /* ── Agent Info Panel ── */}
			{agent && (
				<div className="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
					<div className="flex items-center gap-3">
						<div className="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xl dark:border-slate-700 dark:bg-slate-800">
							{agentAvatar}
						</div>
						<div>
							<h4 className="text-sm font-semibold text-slate-900 dark:text-slate-100">
								{agent.name}
							</h4>
							<p className="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">
								{agent.agent_id}
							</p>
						</div>
					</div>
					{agent.toolkits?.length > 0 && (
						<div className="mt-3 flex flex-wrap gap-1.5">
							{agent.toolkits.map((toolkit, idx) => (
								<span
									key={idx}
									className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
								>
									{toolkit}
								</span>
							))}
						</div>
					)}
				</div>
			)}
		</Modal>
	);
}

AgentTestModal.propTypes = {
	isOpen: PropTypes.bool,
	onClose: PropTypes.func.isRequired,
	agent: PropTypes.object,
};
