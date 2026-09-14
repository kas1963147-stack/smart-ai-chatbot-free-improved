import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Loading from '../common/Loading';
import apiFetch from '@wordpress/api-fetch';

export default function LiveChatPage() {
	const [sessions, setSessions] = useState([]);
	const [loading, setLoading] = useState(true);
	const [selectedSessionId, setSelectedSessionId] = useState(null);
	const [replyText, setReplyText] = useState('');
	const [sending, setSending] = useState(false);
	const [error, setError] = useState(null);

	const messagesEndRef = useRef(null);

	// Poll sessions every 5 seconds
	useEffect(() => {
		fetchSessions();
		const interval = setInterval(fetchSessions, 5000);
		return () => clearInterval(interval);
	}, []);

	const fetchSessions = async () => {
		try {
			// Removed API_BASE buggy variable and explicitly used the relative path
			const res = await apiFetch({ path: `/quark-agentflow-ai/v1/inbox/sessions` });
			if (res.success) {
				setSessions(res.sessions || []);
			}
		} catch (err) {
			setError(err.message || 'Failed to load sessions');
		} finally {
			setLoading(false);
		}
	};

	const handleSendReply = async () => {
		if (!replyText.trim() || !selectedSessionId) return;

		setSending(true);
		try {
			const res = await apiFetch({
				path: `/quark-agentflow-ai/v1/inbox/sessions/${selectedSessionId}/reply`,
				method: 'POST',
				data: { message: replyText.trim() },
			});
			if (res.success) {
				setReplyText('');
				
				// Update session locally
				setSessions(prev => 
					prev.map(s => s.session_id === selectedSessionId ? res.session : s)
				);
			} else {
				throw new Error(res.error || 'Failed to send reply');
			}
		} catch (err) {
			alert(err.message || 'Failed to send reply');
		} finally {
			setSending(false);
		}
	};

	const selectedSession = sessions.find(s => s.session_id === selectedSessionId);
	const messageCount = selectedSession?.messages?.length || 0;

	useEffect(() => {
		if (selectedSession && messagesEndRef.current) {
			messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
		}
	}, [selectedSessionId, messageCount]);

	// Regex to strip raw system tool JSON from the assistant's output to keep the UI clean
	const cleanMessageText = (text) => {
		if (!text) return '';
		return text.replace(/\[\s*\{.*"callId".*\}\s*\]/g, '').trim();
	};

	if (loading && sessions.length === 0) {
		return <Loading message={__('Loading Live Inboxâ€¦', 'agentflow-ai')} fullPage />;
	}

	return (
		<div className="flex h-[calc(100vh-6rem)] min-h-[600px] w-full overflow-hidden rounded-[1.5rem] border border-slate-200/60 bg-white/90 shadow-xl shadow-primary/5 dark:border-slate-700/50 dark:bg-slate-900/85">
			
			{/* Sidebar - Sessions List */}
			<div className="flex w-[320px] shrink-0 flex-col border-r border-slate-200/60 bg-slate-50/60 dark:border-slate-700/50 dark:bg-slate-800/20 xl:w-[340px]">
				{/* Sidebar Header */}
				<div className="border-b border-slate-200/60 px-5 py-4 dark:border-slate-700/50">
					<div className="flex items-center justify-between mb-2">
						<h2 className="bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-xl font-bold text-transparent dark:from-white dark:to-slate-400">Live Inbox</h2>
						<span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary ring-1 ring-inset ring-primary/20">
							{sessions.length}
						</span>
					</div>
					<p className="text-xs font-medium tracking-wide text-slate-500">Awaiting your response</p>
				</div>

				{/* Session Items */}
				<div className="flex-1 space-y-2 overflow-y-auto px-3 py-3">
					{sessions.length === 0 ? (
						<div className="flex flex-col items-center justify-center h-full text-center p-8 opacity-60">
							<div className="w-16 h-16 mb-4 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center">
								<svg className="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 13l4 4L19 7" />
								</svg>
							</div>
							<p className="text-sm font-semibold text-slate-600 dark:text-slate-300">All caught up!</p>
							<p className="text-xs mt-1 text-slate-500">No active handoff requests.</p>
						</div>
					) : (
						sessions.map(session => {
							const isSelected = selectedSessionId === session.session_id;
							const lastMsg = session.messages[session.messages.length - 1];
							const previewText = cleanMessageText(lastMsg?.content) || 'Connected...';
							const timeStr = new Date(session.last_message_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
							
							return (
								<button
									key={session.id}
									onClick={() => setSelectedSessionId(session.session_id)}
									className={`group relative w-full rounded-xl border p-3.5 text-left transition-all duration-200 ${
										isSelected 
											? 'bg-white dark:bg-slate-800 border-primary shadow-md shadow-primary/10 ring-1 ring-primary/20' 
											: 'bg-transparent border-transparent hover:bg-white/60 dark:hover:bg-slate-800/40 hover:border-slate-200 dark:hover:border-slate-700'
									}`}
								>
									<div className="mb-1.5 flex items-start justify-between gap-2">
										<p className={`truncate text-sm font-semibold tracking-tight transition-colors ${isSelected ? 'text-primary dark:text-primary-light' : 'text-slate-900 dark:text-white group-hover:text-primary'}`}>
											{(session.whatsapp_number && session.whatsapp_number !== 'web') ? `+${session.whatsapp_number.replace(/\D/g, '')}` : (session.user_name || 'Web User')}
										</p>
										<span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-medium ${isSelected ? 'bg-primary/10 text-primary' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'}`}>
											{timeStr}
										</span>
									</div>
									<p className="line-clamp-1 text-xs leading-5 text-slate-500 opacity-80 dark:text-slate-400">
										{previewText}
									</p>
									{/* Unread dot indicator (simulated for realism) */}
									{!isSelected && (
										<span className="absolute top-4 -left-1 hidden group-hover:block w-2 h-2 rounded-full bg-primary animate-pulse"></span>
									)}
								</button>
							);
						})
					)}
				</div>
			</div>

			{/* Main Chat Area */}
			<div className="relative z-10 flex min-h-0 flex-1 flex-col bg-slate-50/30 dark:bg-slate-900/30">
				{selectedSession ? (
					<>
						{/* Chat Top Bar */}
						<div className="z-20 flex items-center justify-between border-b border-slate-200/60 bg-white/70 px-6 py-3 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-900/60">
							<div className="flex items-center gap-4">
								<div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-tr from-primary to-primary-hover text-base font-bold text-white shadow-lg shadow-primary/20">
									{(selectedSession.whatsapp_number && selectedSession.whatsapp_number !== 'web') ? selectedSession.whatsapp_number.slice(-2) : 'W'}
								</div>
								<div>
									<h3 className="flex items-center gap-3 text-base font-bold text-slate-900 dark:text-white">
										{(selectedSession.whatsapp_number && selectedSession.whatsapp_number !== 'web') ? `WhatsApp User` : (selectedSession.user_name || 'Live Web User')}
										{(selectedSession.whatsapp_number && selectedSession.whatsapp_number !== 'web') && (
											<span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-[#25D366]/10 text-[#25D366] text-xs font-bold uppercase tracking-wider ring-1 ring-inset ring-[#25D366]/20">
												<svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
												Connected
											</span>
										)}
									</h3>
									<p className="text-xs font-mono text-slate-400 mt-0.5">{(selectedSession.whatsapp_number && selectedSession.whatsapp_number !== 'web') ? selectedSession.whatsapp_number : selectedSession.session_id}</p>
								</div>
							</div>
						</div>

						{/* Transcript Scroll Area */}
						<div className="flex-1 space-y-4 overflow-y-auto px-6 py-4">
							{selectedSession.messages.map((msg, idx) => {
								const isUser = msg.role === 'user';
								const isAgent = msg.role === 'assistant';
								const isBot = isAgent && !msg.agent_reply;
								const cleanText = cleanMessageText(msg.content);

								if (!cleanText) return null;

								return (
									<div key={idx} className={`flex w-full ${isAgent ? 'justify-start' : 'justify-end'}`}>
										<div className={`flex max-w-[82%] flex-col ${isAgent ? 'items-start' : 'items-end'}`}>
											{/* Author Tag */}
											<div className="mb-1 flex items-center gap-2 px-1">
												{isAgent ? (
													<>
														<span className={`text-[10px] font-bold uppercase tracking-wider ${isBot ? 'text-primary' : 'text-primary'}`}>
															{isBot ? 'AI Bot' : 'Human Support'}
														</span>
														<span className="text-[10px] text-slate-400 font-medium">
															{new Date(msg.timestamp || Date.now()).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}
														</span>
													</>
												) : (
													<>
														<span className="text-[10px] text-slate-400 font-medium">
															{new Date(msg.timestamp || Date.now()).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}
														</span>
														<span className="text-[10px] font-bold uppercase tracking-wider text-primary">{selectedSession.user_name ? selectedSession.user_name.substring(0, 15) : 'User'}</span>
													</>
												)}
											</div>

											{/* Bubble rendering */}
											<div className={`rounded-2xl px-4 py-2.5 text-sm leading-6 shadow-sm ring-1 ${
												isAgent 
													? isBot 
														? 'bg-white text-slate-700 ring-slate-200/60 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700/50 rounded-tl-sm shadow-slate-200/50' 
														: 'bg-primary/10 text-primary-text ring-primary/20 dark:bg-primary/20 dark:text-white dark:ring-primary/30 rounded-tl-sm shadow-primary/10'
													: 'bg-primary text-white ring-primary/30 rounded-tr-sm shadow-primary/20'
											}`}>
												<div className="whitespace-pre-wrap">{cleanText}</div>
											</div>
										</div>
									</div>
								);
							})}
							<div ref={messagesEndRef} className="h-2" />
						</div>

						{/* Composer Input Area */}
						<div className="border-t border-slate-200/60 bg-white/85 px-6 py-3 backdrop-blur-md dark:border-slate-700/50 dark:bg-slate-900/80">
							<div className="relative group">
								<textarea
									value={replyText}
									onChange={(e) => setReplyText(e.target.value)}
									placeholder={(selectedSession.whatsapp_number && selectedSession.whatsapp_number !== 'web') ? "Type your official WhatsApp reply here..." : "Type response to web user..."}
									className="block h-12 min-h-[3rem] max-h-32 w-full resize-none rounded-xl border border-slate-300/80 bg-white py-3 pl-4 pr-24 text-sm leading-6 text-slate-900 shadow-inner transition-all placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
									onKeyDown={(e) => {
										if (e.key === 'Enter' && !e.shiftKey) {
											e.preventDefault();
											handleSendReply();
										}
									}}
								/>
								
								<button
									onClick={handleSendReply}
									disabled={sending || !replyText.trim()}
									className="absolute bottom-2 right-2 flex items-center justify-center gap-1.5 rounded-lg bg-primary px-4 py-1.5 text-sm font-bold text-white shadow-md shadow-primary/30 transition-all ring-primary/50 hover:bg-primary-hover hover:-translate-y-0.5 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 group-focus-within:ring-2"
								>
									{sending ? (
										<svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
											<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
											<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
										</svg>
									) : (
										<>
											<span>Send</span>
											<svg className="w-4 h-4 ml-1 -mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
												<path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5" />
											</svg>
										</>
									)}
								</button>
							</div>
							
							<div className="mt-2 flex items-center justify-between text-[11px] font-medium text-slate-500 dark:text-slate-400">
								<p>Shift + Enter to add a new line. Enter to send.</p>
								{(selectedSession.whatsapp_number && selectedSession.whatsapp_number !== 'web') && (
									<p className="flex items-center gap-1">
										<svg className="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
										Protected by Meta 24h Window
									</p>
								)}
							</div>
						</div>
					</>
				) : (
					<div className="relative flex flex-1 flex-col items-center justify-center overflow-hidden text-slate-500 dark:text-slate-400">
						{/* Background decorative blob */}
						<div className="absolute w-[500px] h-[500px] bg-primary/5 rounded-full blur-[100px] top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none"></div>
						
						<div className="relative z-10 mb-6 flex h-24 w-24 rotate-3 items-center justify-center rounded-[1.5rem] bg-gradient-to-br from-white to-slate-50 shadow-2xl shadow-slate-200/50 ring-1 ring-slate-200 dark:from-slate-800 dark:to-slate-900 dark:shadow-black/20 dark:ring-slate-700">
							<svg className="w-12 h-12 text-primary -rotate-3 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
								<path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
							</svg>
						</div>
						<p className="z-10 bg-gradient-to-r from-slate-900 to-slate-500 bg-clip-text text-xl font-bold text-transparent dark:from-white dark:to-slate-400">Live Chat Inbox</p>
						<p className="z-10 mt-3 max-w-sm text-center text-sm leading-relaxed text-slate-500 dark:text-slate-400">
							Select a conversation from the sidebar to review the AI's transcript and take over the conversation instantly.
						</p>
					</div>
				)}
			</div>
		</div>
	);
}

