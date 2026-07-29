/**
 * Session Detail - Metronic v9 Premium Style
 *
 * Displays a single session's messages with embedded tool call details.
 * Features chat bubble design, gradient headers, and premium card styling.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ActionTimeline from './ActionTimeline';

// Icon components
const RobotIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
	</svg>
);

const UserIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
	</svg>
);

const SystemIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
	</svg>
);

const TrashIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
	</svg>
);

const ClockIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
	</svg>
);

const ChatIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
	</svg>
);

const LocationIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
	</svg>
);

const ChatBubbleEmptyIcon = () => (
	<svg className="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
	</svg>
);

export default function SessionDetail({ session, onDelete }) {
	if (!session) {
		return null;
	}

	// Ref for scrolling to bottom
	const messagesEndRef = useRef(null);

	// Auto-scroll to bottom when session loads
	useEffect(() => {
		if (messagesEndRef.current) {
			setTimeout(() => {
				messagesEndRef.current.scrollIntoView({ behavior: 'auto', block: 'end' });
			}, 100);
		}
	}, [session]);

	// Format date
	const formatDate = (dateStr) => {
		if (!dateStr) {
			return '-';
		}
		const date = new Date(dateStr);
		return (
			date.toLocaleDateString() +
			' ' +
			date.toLocaleTimeString([], {
				hour: '2-digit',
				minute: '2-digit',
			})
		);
	};

	// Get role configuration
	const getRoleConfig = (role) => {
		switch (role) {
			case 'user':
				return {
					label: __('User', 'smart-woo-chatbot'),
					icon: <UserIcon />,
					gradient: 'bg-primary',
					bgColor: 'bg-blue-50 dark:bg-blue-900/40',
					borderColor: 'border-blue-200 dark:border-blue-700',
					textColor: 'text-blue-900 dark:text-blue-100',
					align: 'justify-end',
					bubbleAlign: 'ml-12',
				};
			case 'assistant':
				return {
					label: __('Agent', 'smart-woo-chatbot'),
					icon: <RobotIcon />,
					gradient: 'bg-slate-700',
					bgColor: 'bg-slate-50 dark:bg-slate-700',
					borderColor: 'border-slate-200 dark:border-slate-500',
					textColor: 'text-slate-900 dark:text-slate-100',
					align: 'justify-start',
					bubbleAlign: 'mr-12',
				};
			case 'system':
				return {
					label: __('System', 'smart-woo-chatbot'),
					icon: <SystemIcon />,
					gradient: 'bg-amber-500',
					bgColor: 'bg-amber-50 dark:bg-amber-900/40',
					borderColor: 'border-amber-200 dark:border-amber-700',
					textColor: 'text-amber-900 dark:text-amber-100',
					align: 'justify-center',
					bubbleAlign: 'mx-8',
				};
			default:
				return {
					label: role,
					icon: <ChatIcon />,
					gradient: 'bg-gray-500',
					bgColor: 'bg-gray-50 dark:bg-slate-700',
					borderColor: 'border-gray-200 dark:border-slate-600',
					textColor: 'text-gray-900 dark:text-gray-100',
					align: 'justify-start',
					bubbleAlign: '',
				};
		}
	};

	return (
		<div className="space-y-6">
			{/* Session Header Card */}
			<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
				{/* Gradient Header */}
				<div className="bg-primary px-6 py-4">
					<div className="flex items-center justify-between">
						<div className="flex items-center gap-4">
							<div className="flex items-center justify-center w-12 h-12 rounded-2xl bg-white/20 text-white">
								<ChatIcon />
							</div>
							<div>
								<h2 className="text-lg font-semibold text-white">
									{__('Conversation with', 'smart-woo-chatbot')} {session.agent_name}
								</h2>
								<p className="text-sm text-slate-400">
									{__('Session ID:', 'smart-woo-chatbot')} {session.session_id.substring(0, 8)}...
								</p>
							</div>
						</div>
						<div className="flex items-center gap-3">
							{session.appointment && (
								<a
									href={`admin.php?page=smart-ai-chatbot&tab=appointments&id=${session.appointment.id}`}
									className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl bg-blue-500/20 text-blue-100 hover:bg-blue-500/30 hover:text-white border border-blue-500/30 transition-all"
								>
									<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
									{__('View Booking', 'smart-woo-chatbot')}
								</a>
							)}
							<button
								onClick={onDelete}
								className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl bg-red-500/20 text-red-300 hover:bg-red-500/30 hover:text-red-200 border border-red-500/30 transition-all"
							>
								<TrashIcon />
								{__('Delete Session', 'smart-woo-chatbot')}
							</button>
						</div>
					</div>
				</div>

				{/* Meta Info */}
				<div className="px-6 py-4 bg-slate-50 dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
					<div className="flex flex-wrap items-center gap-6">
						<div className="flex items-center gap-2">
							<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
								<RobotIcon />
							</div>
							<div>
								<p className="text-xs text-slate-400 dark:text-slate-500">{__('Agent', 'smart-woo-chatbot')}</p>
								<p className="text-sm font-medium text-slate-900 dark:text-white">{session.agent_name}</p>
							</div>
						</div>
						<div className="w-px h-8 bg-slate-200 dark:bg-slate-700"></div>
						<div className="flex items-center gap-2">
							<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">
								<ClockIcon />
							</div>
							<div>
								<p className="text-xs text-slate-400 dark:text-slate-500">{__('Started', 'smart-woo-chatbot')}</p>
								<p className="text-sm font-medium text-slate-900 dark:text-white">{formatDate(session.started_at)}</p>
							</div>
						</div>
						<div className="w-px h-8 bg-slate-200 dark:bg-slate-700"></div>
						<div className="flex items-center gap-2">
							<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">
								<ClockIcon />
							</div>
							<div>
								<p className="text-xs text-slate-400 dark:text-slate-500">{__('Last Activity', 'smart-woo-chatbot')}</p>
								<p className="text-sm font-medium text-slate-900 dark:text-white">{formatDate(session.last_message_at)}</p>
							</div>
						</div>
						<div className="w-px h-8 bg-slate-200 dark:bg-slate-700"></div>
						<div className="flex items-center gap-2">
							<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
								<ChatIcon />
							</div>
							<div>
								<p className="text-xs text-slate-400 dark:text-slate-500">{__('Messages', 'smart-woo-chatbot')}</p>
								<p className="text-sm font-medium text-slate-900 dark:text-white">{session.messages?.length || 0}</p>
							</div>
						</div>
						
						{(session.metadata?.location || session.metadata?.timezone) && (
							<>
								<div className="w-px h-8 bg-slate-200 dark:bg-slate-700"></div>
								<div className="flex items-center gap-2">
									<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400">
										<LocationIcon />
									</div>
									<div className="max-w-[120px]">
										<p className="text-xs text-slate-400 dark:text-slate-500">{__('Location', 'smart-woo-chatbot')}</p>
										<p className="text-sm font-medium text-slate-900 dark:text-white truncate" title={session.metadata.location || session.metadata.timezone}>
											{session.metadata.location || session.metadata.timezone}
										</p>
									</div>
								</div>
							</>
						)}
					</div>
				</div>
			</div>

			{/* Messages */}
			<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
				<div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
					<h3 className="text-lg font-semibold text-slate-900 dark:text-white">{__('Conversation', 'smart-woo-chatbot')}</h3>
				</div>

				<div className="p-6 space-y-6 bg-slate-50/50 dark:bg-slate-800/50 max-h-[600px] overflow-y-auto">
					{session.messages?.map((message, idx) => {
						const config = getRoleConfig(message.role);
						const isUser = message.role === 'user';
						return (
							<div key={message.id || idx} className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
								<div className={`flex items-start gap-3 max-w-[85%] ${isUser ? 'flex-row-reverse' : 'flex-row'}`}>
									{/* Avatar with Label Below */}
									<div className="flex flex-col items-center flex-shrink-0">
										<div className={`flex items-center justify-center w-10 h-10 rounded-xl ${config.gradient} text-white shadow-md`}>
											{config.icon}
										</div>
										<span className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mt-1">
											{config.label}
										</span>
									</div>

									{/* Message Content */}
									<div className="flex flex-col">
										{/* Message Bubble */}
										<div
											className={`${config.bgColor} ${config.borderColor} border rounded-2xl px-4 py-3 shadow-sm ${isUser
												? 'rounded-tr-md'
												: message.role === 'assistant'
													? 'rounded-tl-md'
													: ''
												}`}
										>
											<p className={`text-sm ${config.textColor} whitespace-pre-wrap leading-relaxed`}>
												{message.content}
											</p>
										</div>

										{/* Timestamp */}
										<span className={`text-xs text-slate-400 dark:text-slate-500 mt-1.5 ${isUser ? 'text-right' : 'text-left'}`}>
											{formatDate(message.timestamp)}
										</span>

										{/* Tool Calls for this message */}
										{message.tool_calls && message.tool_calls.length > 0 && (
											<div className="mt-3">
												<ActionTimeline actions={message.tool_calls} />
											</div>
										)}
									</div>
								</div>
							</div>
						);
					})}

					{(!session.messages || session.messages.length === 0) && (
						<div className="flex flex-col items-center justify-center py-16">
							<div className="flex items-center justify-center w-20 h-20 rounded-3xl bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 mb-4">
								<ChatBubbleEmptyIcon />
							</div>
							<p className="text-slate-500 dark:text-slate-400">
								{__('No messages in this session', 'smart-woo-chatbot')}
							</p>
						</div>
					)}

					{/* Scroll anchor for auto-scroll to bottom */}
					<div ref={messagesEndRef} />
				</div>
			</div>

			{/* Unlinked Tool Calls */}
			{session.unlinked_tool_calls?.length > 0 && (
				<div className="bg-white dark:bg-slate-800 rounded-2xl border border-amber-200 dark:border-amber-700 shadow-sm overflow-hidden">
					<div className="px-6 py-4 border-b border-amber-100 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30">
						<div className="flex items-center gap-3">
							<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-500 text-white">
								<SystemIcon />
							</div>
							<div>
								<h3 className="text-lg font-semibold text-amber-900 dark:text-amber-300">{__('Unlinked Tool Calls', 'smart-woo-chatbot')}</h3>
								<p className="text-sm text-amber-700 dark:text-amber-400">
									{__('These tool calls could not be matched to a specific message.', 'smart-woo-chatbot')}
								</p>
							</div>
						</div>
					</div>
					<div className="p-6">
						<ActionTimeline actions={session.unlinked_tool_calls} />
					</div>
				</div>
			)}
		</div>
	);
}
