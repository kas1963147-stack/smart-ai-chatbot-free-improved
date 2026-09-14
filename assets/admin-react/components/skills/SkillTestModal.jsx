/**
 * SkillTestModal Component
 *
 * Test a skill with sample conversation to see AI response.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Modal } from '../shared';
import { Button, IconButton, TextField } from '../ui';

export default function SkillTestModal({ skill, isOpen, onClose }) {
	const [message, setMessage] = useState('');
	const [conversation, setConversation] = useState([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	// Reset on open
	const resetConversation = () => {
		setConversation([]);
		setMessage('');
		setError(null);
	};

	// Send test message
	const sendMessage = async () => {
		if (!message.trim() || loading) {
			return;
		}

		const userMessage = message.trim();
		setMessage('');
		setLoading(true);
		setError(null);

		// Add user message to conversation
		setConversation((prev) => [
			...prev,
			{ role: 'user', content: userMessage },
		]);

		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/skills/test',
				method: 'POST',
				data: {
					skill_id: skill?.name,
					message: userMessage,
					context: {
						skill_content: skill?.generatedMarkdown,
						tools: skill?.toolsRequired || [],
					},
				},
			});

			if (response.success) {
				setConversation((prev) => [
					...prev,
					{
						role: 'assistant',
						content: response.data.response,
						meta: {
							tokens: response.data.tokens_used,
							time_ms: response.data.response_time_ms,
						},
					},
				]);
			} else {
				setError(response.error?.message || 'Failed to get response');
			}
		} catch (err) {
			setError(err.message || 'Connection error');
			setConversation((prev) => [
				...prev,
				{
					role: 'assistant',
					content: __(
						'(Error: Could not connect to AI)',
						'agentflow-ai'
					),
					isError: true,
				},
			]);
		} finally {
			setLoading(false);
		}
	};

	// Handle Enter key
	const handleKeyDown = (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			sendMessage();
		}
	};

	return (
		<Modal
			isOpen={isOpen}
			onClose={onClose}
			title={__('Test Skill', 'agentflow-ai')}
			subtitle={skill?.displayName || skill?.name}
			size="md"
			footer={
				<div className="flex justify-end gap-3 w-full">
					<button type="button" className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700" onClick={resetConversation}>
						{__('Clear Chat', 'agentflow-ai')}
					</button>
					<button type="button" className="px-4 py-2 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:bg-gray-700 dark:hover:bg-gray-600" onClick={onClose}>
						{__('Close', 'agentflow-ai')}
					</button>
				</div>
			}
		>
			<div className="flex flex-col min-h-[500px] h-[60vh] max-h-[700px]">
				{ /* Skill info */}
				<div className="flex items-center gap-2 mb-4 px-2">
					<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
						{skill?.category || 'general'}
					</span>
					{skill?.toolsRequired?.length > 0 && (
						<span className="text-xs text-gray-500 dark:text-gray-400 font-medium">
							{skill.toolsRequired.length}{' '}{__('tools', 'agentflow-ai')}
						</span>
					)}
				</div>

				{ /* Conversation */}
				<div className="flex-1 overflow-y-auto mb-4 p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50/50 dark:bg-gray-900/50 shadow-inner flex flex-col">
					{conversation.length === 0 ? (
						<div className="h-full flex-1 flex flex-col items-center justify-center text-center text-gray-500 dark:text-gray-400">
							<div className="w-16 h-16 bg-blue-50 dark:bg-blue-900/20 text-blue-500 rounded-full flex items-center justify-center mb-4">
								<svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
								</svg>
							</div>
							<p className="mb-6 max-w-sm text-sm">
								{__(
									'Send a message to test how this skill responds.',
									'agentflow-ai'
								)}
							</p>
							<div className="space-y-3 w-full max-w-xs">
								<p className="font-semibold text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 text-left">
									{__(
										'Example messages:',
										'agentflow-ai'
									)}
								</p>
								<div className="flex flex-col gap-2">
									<button
										type="button"
										className="text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2.5 hover:border-primary hover:text-primary transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-left truncate"
										onClick={() => setMessage('What can you help me with?')}
									>
										"What can you help me with?"
									</button>
									<button
										type="button"
										className="text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2.5 hover:border-primary hover:text-primary transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-left truncate"
										onClick={() => setMessage('I need help with my order')}
									>
										"I need help with my order"
									</button>
								</div>
							</div>
						</div>
					) : (
						<div className="space-y-5">
							{conversation.map((msg, idx) => (
								<div
									key={idx}
									className={`flex gap-3 w-full ${msg.role === 'user' ? 'justify-end' : 'justify-start'
										} ${msg.isError ? 'opacity-80' : ''}`}
								>
									{msg.role !== 'user' && (
										<div className="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold bg-primary text-white shadow-sm mt-1">
											AI
										</div>
									)}

									<div className={`max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm ${msg.role === 'user'
											? 'bg-blue-600 text-white rounded-tr-none'
											: msg.isError
												? 'bg-red-50 text-red-800 border border-red-200 dark:bg-red-900/30 dark:text-red-200 dark:border-red-800 rounded-tl-none'
												: 'bg-white text-gray-800 border border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 rounded-tl-none'
										}`}>
										<div className="whitespace-pre-wrap break-words leading-relaxed">{msg.content}</div>
										{msg.meta && (
											<div className={`flex items-center gap-3 mt-2 text-[10px] uppercase font-medium tracking-wider pt-2 border-t ${msg.role === 'user'
													? 'border-white/20 text-blue-100'
													: 'border-gray-100 dark:border-gray-700 text-gray-400'
												}`}>
												{msg.meta.tokens && (
													<span className="flex items-center gap-1">
														<svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
														{msg.meta.tokens} tokens
													</span>
												)}
												{msg.meta.time_ms && (
													<span className="flex items-center gap-1">
														<svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
														{msg.meta.time_ms}ms
													</span>
												)}
											</div>
										)}
									</div>

									{msg.role === 'user' && (
										<div className="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm mt-1">
											U
										</div>
									)}
								</div>
							))}

							{loading && (
								<div className="flex gap-3 justify-start w-full">
									<div className="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold bg-primary text-white shadow-sm mt-1">
										AI
									</div>
									<div className="max-w-[80%] rounded-2xl rounded-tl-none px-5 py-3 text-sm shadow-sm bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex items-center min-h-[44px]">
										<div className="flex space-x-1.5 items-center justify-center h-full">
											<div className="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
											<div className="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
											<div className="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></div>
										</div>
									</div>
								</div>
							)}
						</div>
					)}
				</div>

				{ /* Error */}
				{error && (
					<div className="mb-4 p-3 bg-red-50 text-red-700 border border-red-200 rounded-lg text-sm dark:bg-red-900/30 dark:text-red-300 dark:border-red-800 flex items-start gap-2">
						<svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
						</svg>
						<span>{error}</span>
					</div>
				)}

				{ /* Input */}
				<div className="flex gap-2 bg-white dark:bg-gray-800 p-2 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-all">
					<div className="flex-1 relative">
						<textarea
							rows="1"
							className="w-full text-sm bg-transparent text-gray-900 dark:text-white border-0 outline-none p-2.5 resize-none min-h-[44px] max-h-[120px] overflow-y-auto disabled:opacity-50"
							value={message}
							onChange={(e) => {
								setMessage(e.target.value);
								// Auto-resize
								e.target.style.height = 'auto';
								e.target.style.height = (e.target.scrollHeight) + 'px';
							}}
							onKeyDown={handleKeyDown}
							placeholder={__('Type a message to testâ€¦', 'agentflow-ai')}
							disabled={loading}
							style={{ minHeight: '44px' }}
						/>
					</div>
					<div className="flex items-end pb-1 pr-1">
						<button
							type="button"
							className="flex items-center justify-center w-10 h-10 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 hover:scale-[1.05] active:scale-[0.95]"
							onClick={sendMessage}
							disabled={loading || !message.trim()}
							aria-label={__('Send', 'agentflow-ai')}
						>
							{loading ? (
								<svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
									<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
							) : (
								<svg className="w-5 h-5 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
								</svg>
							)}
						</button>
					</div>
				</div>
				<div className="mt-2 text-center">
					<span className="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider">
						{__('Press Enter to send, Shift+Enter for new line', 'agentflow-ai')}
					</span>
				</div>
			</div>
		</Modal>
	);
}
