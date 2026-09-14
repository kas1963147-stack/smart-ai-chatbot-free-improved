/**
 * Workspace Page - Pure Tailwind CSS
 *
 * Admin AI workspace with:
 * - Full Tailwind CSS styling (no external CSS)
 * - Dark mode with dark: prefix
 * - Black sidebar, dark gray chat area in dark mode
 * - Conversation management
 * - Toast notifications
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Bot, Plus, Menu, Sun, Moon, ChevronDown } from 'lucide-react';

// Metronic AI Chat Components
import { ChatMessages, ChatStarter } from '../ai-chat';
import { cn } from '../../ui/utils';

import ConversationList from './ConversationList';
import { ToastProvider, useToast } from '../shared/Toast';

/**
 * WorkspaceContent Component (wrapped by ToastProvider)
 */
function WorkspaceContent() {
	const [conversations, setConversations] = useState([]);
	const [activeConversation, setActiveConversation] = useState(null);
	const [messages, setMessages] = useState([]);
	const [pendingMessages, setPendingMessages] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [isSending, setIsSending] = useState(false);
	const [sidebarOpen, setSidebarOpen] = useState(true);
	const [error, setError] = useState(null);
	const [darkMode, setDarkMode] = useState(false);
	const [activeTool, setActiveTool] = useState(null);
	const [hasUsedTool, setHasUsedTool] = useState(false);

	// Conversation management state (persisted to localStorage)
	const [pinnedConvos, setPinnedConvos] = useState(() => {
		try { return JSON.parse(localStorage.getItem('swc-pinned-convos') || '[]'); } catch { return []; }
	});
	const [convTags, setConvTags] = useState(() => {
		try { return JSON.parse(localStorage.getItem('swc-conv-tags') || '{}'); } catch { return {}; }
	});

	// Agent/Workflow selection state
	const [availableAgents, setAvailableAgents] = useState([]);
	const [availableWorkflows, setAvailableWorkflows] = useState([]);
	const [availableTeams, setAvailableTeams] = useState([]);
	const [selectedAgent, setSelectedAgent] = useState(() => {
		return localStorage.getItem('swc-workspace-agent') || '';
	});
	const [selectedType, setSelectedType] = useState('agent'); // 'agent', 'workflow', or 'team'
	const [showAgentDropdown, setShowAgentDropdown] = useState(false);
	const dropdownRef = useRef(null);

	const toast = useToast();
	const chatContainerRef = useRef(null);
	const abortControllerRef = useRef(null);

	// Refresh conversation list (used after stream completes)
	const refreshConversations = useCallback(async () => {
		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/workspace/conversations',
			});
			if (response.success) {
				setConversations(response.data || []);
			}
		} catch (e) {
			// Non-critical
		}
	}, []);

	// Load conversations on mount
	useEffect(() => {
		let mounted = true;

		const loadConversations = async () => {
			setIsLoading(true);
			try {
				const response = await apiFetch({
					path: '/quark-agentflow-ai/v1/workspace/conversations',
				});
				if (mounted && response.success) {
					setConversations(response.data || []);
				}
			} catch (err) {
				console.error('Failed to load conversations:', err);
			} finally {
				if (mounted) {
					setIsLoading(false);
				}
			}
		};

		loadConversations();
		return () => {
			mounted = false;
		};
	}, []);

	// Load available agents and workflows on mount
	useEffect(() => {
		let mounted = true;

		const loadAgentsAndWorkflows = async () => {
			try {
				// Load agents
				const agentResponse = await apiFetch({
					path: '/quark-agentflow-ai/v1/workspace/available-agents',
				});
				if (mounted && agentResponse.success) {
					const agents = agentResponse.data || [];
					setAvailableAgents(agents);

					// Priority: 1) Bulk selection, 2) Single selection from AI Agents, 3) Persisted workspace agent, 4) First agent
					const savedAgents = localStorage.getItem('swc-selected-agents');
					const savedAgent = localStorage.getItem('swc-selected-agent');
					const persistedAgent = localStorage.getItem('swc-workspace-agent');

					if (savedAgents) {
						try {
							const agentIds = JSON.parse(savedAgents);
							if (agentIds.length > 0 && agents.find(a => a.id === agentIds[0] || a.agent_id === agentIds[0])) {
								setSelectedAgent(agentIds[0]);
								setSelectedType('agent');
							}
							localStorage.removeItem('swc-selected-agents');
						} catch (e) {
							console.error('Failed to parse selected agents:', e);
						}
					} else if (savedAgent && agents.find(a => a.id === savedAgent || a.agent_id === savedAgent)) {
						setSelectedAgent(savedAgent);
						setSelectedType('agent');
						localStorage.removeItem('swc-selected-agent');
					} else if (persistedAgent && agents.find(a => a.id === persistedAgent || a.agent_id === persistedAgent)) {
						setSelectedAgent(persistedAgent);
						setSelectedType('agent');
					} else if (agents.length > 0 && !selectedAgent) {
						setSelectedAgent(agents[0].id);
						setSelectedType('agent');
					}
				}

				/* Workflows hidden â€” backend preserved for future use
				const workflowResponse = await apiFetch({
					path: '/quark-agentflow-ai/v1/workflows',
				});
				if (mounted && workflowResponse.success !== false) {
					const workflows = workflowResponse.workflows || [];
					setAvailableWorkflows(workflows.filter(w => w.is_active));
				}
				*/

				// Load teams
				const teamResponse = await apiFetch({
					path: '/quark-agentflow-ai/v1/agent-groups',
				});
				if (mounted && teamResponse.groups) {
					const teams = teamResponse.groups || [];
					setAvailableTeams(teams.filter(t => t.is_active));
				}
			} catch (err) {
				console.error('Failed to load agents/workflows:', err);
			}
		};

		loadAgentsAndWorkflows();
		return () => {
			mounted = false;
		};
	}, []);

	// Close dropdown when clicking outside
	useEffect(() => {
		const handleClickOutside = (event) => {
			if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
				setShowAgentDropdown(false);
			}
		};
		document.addEventListener('mousedown', handleClickOutside);
		return () => document.removeEventListener('mousedown', handleClickOutside);
	}, []);

	// Persist selected agent to localStorage
	useEffect(() => {
		if (selectedAgent && selectedType === 'agent') {
			localStorage.setItem('swc-workspace-agent', selectedAgent);
		}
	}, [selectedAgent, selectedType]);

	// Load conversation messages when active conversation changes
	useEffect(() => {
		let mounted = true;

		const loadMessages = async () => {
			if (!activeConversation) {
				setMessages([]);
				return;
			}

			// Don't reload messages while we're streaming â€” the session event
			// during streaming triggers this effect, and the backend hasn't
			// saved the assistant response yet. This would wipe streamed content.
			if (isSendingRef.current) {
				return;
			}

			try {
				const response = await apiFetch({
					path: `/quark-agentflow-ai/v1/workspace/conversations/${activeConversation}`,
				});
				if (mounted && response.success && response.data) {
					setMessages(response.data.messages || []);
				}
			} catch (err) {
				console.error('Failed to load messages:', err);
			}
		};

		loadMessages();
		return () => {
			mounted = false;
		};
	}, [activeConversation]);

	// Load dark mode preference
	useEffect(() => {
		const saved = localStorage.getItem('swc-dark-mode');
		if (saved === 'true') {
			setDarkMode(true);
		}
	}, []);

	// Apply dark mode class
	useEffect(() => {
		const workspace = document.querySelector('.swc-workspace');
		if (workspace) {
			workspace.classList.toggle('dark', darkMode);
		}
		localStorage.setItem('swc-dark-mode', darkMode);
	}, [darkMode]);

	// Send message state ref to prevent race conditions
	const isSendingRef = useRef(false);
	const pendingMessagesRef = useRef([]);

	// Handle file attachment
	const handleAttachment = useCallback(async () => {
		// Create file input and trigger
		const input = document.createElement('input');
		input.type = 'file';
		input.multiple = true;
		input.onchange = async (e) => {
			const files = Array.from(e.target.files);
			for (const file of files) {
				try {
					const formData = new FormData();
					formData.append('file', file);

					const result = await apiFetch({
						path: '/quark-agentflow-ai/v1/workspace/upload',
						method: 'POST',
						body: formData,
					});

					if (result.success) {
						toast.success(__('File uploaded successfully', 'agentflow-ai'));
					} else {
						toast.error(result.error || __('Failed to upload file', 'agentflow-ai'));
					}
				} catch (err) {
					toast.error(__('Failed to upload file', 'agentflow-ai'));
				}
			}
		};
		input.click();
	}, [toast]);

	const processNextPendingMessage = useCallback(() => {
		if (isSendingRef.current) {
			return;
		}

		const next = pendingMessagesRef.current.shift();
		if (!next) {
			return;
		}

		setPendingMessages((prev) =>
			prev.filter((msg) => msg.id !== next.userMessageId)
		);

		void handleSendMessage(next.message);
	}, []);

	// Send message with SSE streaming
	const handleSendMessage = useCallback(
		async (message, options = {}) => {
			const trimmedMessage = message.trim();
			if (!trimmedMessage) {
				return;
			}

			const existingUserMessageId = options.existingUserMessageId || null;
			const timestamp = new Date().toLocaleTimeString([], {
				hour: '2-digit',
				minute: '2-digit',
			});

			if (isSendingRef.current) {
				const queuedUserMessageId = `queued-user-${Date.now()}`;
				setPendingMessages((prev) => [
					...prev,
					{
						id: queuedUserMessageId,
						content: trimmedMessage,
						timestamp,
					},
				]);

				pendingMessagesRef.current.push({
					message: trimmedMessage,
					userMessageId: queuedUserMessageId,
				});
				return;
			}

			isSendingRef.current = true;
			setIsSending(true);
			setError(null);
			setActiveTool(null);
			setHasUsedTool(false);

			if (!existingUserMessageId) {
				// Optimistically add user message
				const userMessage = {
					id: `user-${Date.now()}`,
					role: 'user',
					content: trimmedMessage,
					timestamp,
				};
				setMessages((prev) => [...prev, userMessage]);
			}

			// Add placeholder for streaming assistant message
			const assistantMsgId = `assistant-${Date.now()}`;
			setMessages((prev) => [
				...prev,
				{
					id: assistantMsgId,
					role: 'assistant',
					content: '',
					isStreaming: true,
					timestamp: new Date().toLocaleTimeString([], {
						hour: '2-digit',
						minute: '2-digit',
					}),
				},
			]);

			try {
				const restUrl = window.swcChatbot?.apiUrl || window.wpApiSettings?.root || '/wp-json/quark-agentflow-ai/v1';
				const nonce = window.swcChatbot?.nonce || window.wpApiSettings?.nonce || '';
				abortControllerRef.current = new AbortController();

				// Build the stream URL â€” swcChatbot.apiUrl already includes the namespace
				const streamUrl = restUrl.replace(/\/$/, '') + '/workspace/stream';

				const response = await fetch(streamUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
						'Accept': 'text/event-stream',
					},
					body: JSON.stringify({
						message: trimmedMessage,
						conversation_id: activeConversation || '',
						agent_id: selectedAgent || '',
					}),
					signal: abortControllerRef.current.signal,
				});

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				// Handle SSE stream with ReadableStream
				const reader = response.body?.getReader();
				if (!reader) {
					throw new Error('No response body');
				}

				const decoder = new TextDecoder();
				let fullContent = '';
				let buffer = '';
				let receivedConversationId = activeConversation;

				while (true) {
					const { done, value } = await reader.read();

					if (done) {
						// Stream completed â€” finalize the message
						setMessages((prev) => {
							const updated = [...prev];
							const lastIdx = updated.length - 1;
							if (lastIdx >= 0 && updated[lastIdx].id === assistantMsgId) {
								if (fullContent) {
									updated[lastIdx] = {
										...updated[lastIdx],
										content: fullContent,
										isStreaming: false,
									};
								} else {
									// No content received â€” remove empty assistant bubble
									updated.splice(lastIdx, 1);
								}
							}
							return updated;
						});
						break;
					}

					// Decode chunk and add to buffer
					buffer += decoder.decode(value, { stream: true });

					// Normalize line endings (Windows PHP may send \r\n)
					buffer = buffer.replace(/\r\n/g, '\n');

					// Process complete SSE events from buffer
					const events = buffer.split('\n\n');
					buffer = events.pop() || ''; // Keep incomplete event in buffer

					for (const eventStr of events) {
						if (!eventStr.trim()) continue;

						// Parse SSE format
						const lines = eventStr.split('\n');
						let eventType = '';
						let eventData = '';

						for (const line of lines) {
							if (line.startsWith('event:')) {
								eventType = line.slice(6).trim();
							} else if (line.startsWith('data:')) {
								eventData = line.slice(5).trim();
							}
						}

						if (!eventData) continue;

						try {
							const data = JSON.parse(eventData);

							if (eventType === 'session') {
								// Received conversation_id from backend
								if (data.conversation_id && !activeConversation) {
									receivedConversationId = data.conversation_id;
									setActiveConversation(data.conversation_id);
									// Reload conversation list
									try {
										const convResponse = await apiFetch({
											path: '/quark-agentflow-ai/v1/workspace/conversations',
										});
										if (convResponse.success) {
											setConversations(convResponse.data || []);
										}
									} catch (e) {
										// Non-critical
									}
								}
							} else if (eventType === 'chunk' || data.text) {
								// Streaming text chunk
								fullContent += data.text || data.content || '';
								console.log('[STREAM ' + new Date().toISOString().slice(11,23) + '] ts=' + (data.ts || '?') + ' text=' + JSON.stringify((data.text||'').slice(0,40)));
								setMessages((prev) => {
									const updated = [...prev];
									const lastIdx = updated.length - 1;
									if (lastIdx >= 0 && updated[lastIdx].id === assistantMsgId) {
										updated[lastIdx] = {
											...updated[lastIdx],
											content: fullContent,
											isStreaming: true,
										};
									}
									return updated;
								});
							} else if (eventType === 'tool_start') {
								setActiveTool(data.name || 'tool');
								setHasUsedTool(true);
								// Tool execution started â€” add to message tool_calls
								const toolName = data.name || 'tool';
								setMessages((prev) => {
									const updated = [...prev];
									const lastIdx = updated.length - 1;
									if (lastIdx >= 0 && updated[lastIdx].id === assistantMsgId) {
										const existingTools = updated[lastIdx].tool_calls || [];
										updated[lastIdx] = {
											...updated[lastIdx],
											tool_calls: [...existingTools, {
												name: toolName,
												arguments: data.arguments || '{}',
												status: 'running',
											}],
										};
									}
									return updated;
								});
							} else if (eventType === 'tool_result') {
								setActiveTool(null);
								// Tool completed â€” update status
								const toolName = data.name || 'tool';
								setMessages((prev) => {
									const updated = [...prev];
									const lastIdx = updated.length - 1;
									if (lastIdx >= 0 && updated[lastIdx].id === assistantMsgId) {
										const tools = [...(updated[lastIdx].tool_calls || [])];
										// Find the last running tool with this name
										for (let i = tools.length - 1; i >= 0; i--) {
											if (tools[i].name === toolName && tools[i].status === 'running') {
												tools[i] = { ...tools[i], status: 'completed' };
												break;
											}
										}
										updated[lastIdx] = {
											...updated[lastIdx],
											tool_calls: tools,
										};
									}
									return updated;
								});
							} else if (eventType === 'final') {
								setActiveTool(null);
								// Final response with full content
								const finalText = data.message || fullContent;
								fullContent = finalText;
								setMessages((prev) => {
									const updated = [...prev];
									const lastIdx = updated.length - 1;
									if (lastIdx >= 0 && updated[lastIdx].id === assistantMsgId) {
										updated[lastIdx] = {
											...updated[lastIdx],
											content: finalText,
											isStreaming: false,
											// Preserve tool_calls accumulated during streaming
								tool_calls: (data.tool_calls && data.tool_calls.length > 0)
									? data.tool_calls
									: (updated[lastIdx].tool_calls || []).map(t => ({ ...t, status: 'completed' })),
										};
									}
									return updated;
								});
								if (data.conversation_id && !activeConversation) {
									setActiveConversation(data.conversation_id);
								}
							} else if (eventType === 'error' || data.error) {
								setActiveTool(null);
								throw new Error(data.message || 'An error occurred');
							} else if (eventType === 'done' || data.finished) {
								setActiveTool(null);
								// Stream complete
								setMessages((prev) => {
									const updated = [...prev];
									const lastIdx = updated.length - 1;
									if (lastIdx >= 0 && updated[lastIdx].id === assistantMsgId) {
										updated[lastIdx] = {
											...updated[lastIdx],
											content: fullContent,
											isStreaming: false,
										};
									}
									return updated;
								});
							}
						} catch (parseErr) {
							if (parseErr.message && parseErr.message !== 'An error occurred') {
								setError(parseErr.message);
							}
							console.warn('SSE parse error:', parseErr, eventData);
						}
					}
				}

			} catch (err) {
				if (err.name === 'AbortError') {
					return;
				}

				console.error('Workspace stream error:', err);
				const errorMessage =
					err?.message ||
					'Network error - check console for details';
				setError(errorMessage);

				// Remove the empty assistant message on error
				setMessages((prev) => {
					const lastMsg = prev[prev.length - 1];
					if (lastMsg?.role === 'assistant' && !lastMsg.content) {
						return prev.slice(0, -1);
					}
					return prev;
				});
			} finally {
				abortControllerRef.current = null;
				isSendingRef.current = false;
				setIsSending(false);
				// Refresh conversation list to pick up auto-generated titles
				refreshConversations();
				if (pendingMessagesRef.current.length > 0) {
					setTimeout(() => {
						processNextPendingMessage();
					}, 0);
				}
			}
		},
		[activeConversation, processNextPendingMessage, selectedAgent, refreshConversations]
	);

	// Handle quick action selection â€” accepts string ID or object with .id
	const handleQuickAction = useCallback((persona) => {
		const prompts = {
			create: 'Help me create a new page or post',
			search: 'Search my site for ',
			settings: 'Help me update my site settings',
			analyze: 'Show me my recent orders and analytics',
		};
		const actionId = typeof persona === 'string' ? persona : persona?.id;
		const actionName = typeof persona === 'string' ? persona : persona?.name;
		const prompt = prompts[actionId] || `Help me with ${actionName || actionId}`;
		handleSendMessage(prompt);
	}, [handleSendMessage]);

	const handleStopGeneration = useCallback(() => {
		if (!isSendingRef.current) {
			return;
		}

		if (abortControllerRef.current) {
			abortControllerRef.current.abort();
			abortControllerRef.current = null;
		}

		setActiveTool(null);
		setIsSending(false);
		isSendingRef.current = false;

		setMessages((prev) => {
			const updated = [...prev];
			const lastIdx = updated.length - 1;

			if (lastIdx >= 0 && updated[lastIdx].role === 'assistant') {
				updated[lastIdx] = {
					...updated[lastIdx],
					isStreaming: false,
				};

				if (!updated[lastIdx].content) {
					return updated.slice(0, -1);
				}
			}

			return updated;
		});
	}, []);

	// Create new conversation
	const handleNewConversation = useCallback(() => {
		setActiveConversation(null);
		setMessages([]);
		setPendingMessages([]);
		pendingMessagesRef.current = [];
		setError(null);
	}, []);

	// Select conversation
	const handleSelectConversation = useCallback((convId) => {
		setActiveConversation(convId);
		setPendingMessages([]);
		pendingMessagesRef.current = [];
		setError(null);
	}, []);

	// Delete conversation
	const handleDeleteConversation = useCallback(
		async (convId) => {
			try {
				await apiFetch({
					path: `/quark-agentflow-ai/v1/workspace/conversations/${convId}`,
					method: 'DELETE',
				});
				setConversations((prev) => prev.filter((c) => c.id !== convId));
				if (activeConversation === convId) {
					setActiveConversation(null);
					setMessages([]);
					setPendingMessages([]);
					pendingMessagesRef.current = [];
				}
				toast.success(__('Conversation deleted', 'agentflow-ai'));
			} catch (err) {
				console.error('Failed to delete conversation:', err);
				toast.error(__('Failed to delete conversation', 'agentflow-ai'));
			}
		},
		[activeConversation, toast]
	);

	// Pin/unpin conversation (local state + localStorage)
	const handlePinConversation = useCallback((convId) => {
		setPinnedConvos((prev) => {
			const updated = prev.includes(convId)
				? prev.filter((id) => id !== convId)
				: [...prev, convId];
			localStorage.setItem('swc-pinned-convos', JSON.stringify(updated));
			return updated;
		});
	}, []);

	// Rename conversation (backend API + local state)
	const handleRenameConversation = useCallback(async (convId, newTitle) => {
		try {
			await apiFetch({
				path: `/quark-agentflow-ai/v1/workspace/conversations/${convId}`,
				method: 'PATCH',
				data: { title: newTitle },
			});
			setConversations((prev) =>
				prev.map((c) => c.id === convId ? { ...c, title: newTitle } : c)
			);
			toast.success(__('Conversation renamed', 'agentflow-ai'));
		} catch (err) {
			console.error('Failed to rename conversation:', err);
			toast.error(__('Failed to rename conversation', 'agentflow-ai'));
		}
	}, [toast]);

	// Export conversation (download as file)
	const handleExportConversation = useCallback(async (convId, format) => {
		try {
			const response = await apiFetch({
				path: `/quark-agentflow-ai/v1/workspace/conversations/${convId}`,
			});
			if (!response.success || !response.data) return;

			const conv = response.data;
			const msgs = conv.messages || [];
			let content, filename, mimeType;

			if (format === 'json') {
				content = JSON.stringify(conv, null, 2);
				filename = `${(conv.title || 'conversation').replace(/[^a-z0-9]/gi, '_')}.json`;
				mimeType = 'application/json';
			} else {
				content = `# ${conv.title || 'Conversation'}\n\n`;
				msgs.forEach((msg) => {
					const role = msg.role === 'user' ? '**You**' : '**AI**';
					content += `${role} (${msg.timestamp || ''}):\n\n${msg.content || ''}\n\n---\n\n`;
				});
				filename = `${(conv.title || 'conversation').replace(/[^a-z0-9]/gi, '_')}.md`;
				mimeType = 'text/markdown';
			}

			const blob = new Blob([content], { type: mimeType });
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = filename;
			a.click();
			URL.revokeObjectURL(url);
			toast.success(__('Conversation exported', 'agentflow-ai'));
		} catch (err) {
			console.error('Failed to export conversation:', err);
			toast.error(__('Failed to export conversation', 'agentflow-ai'));
		}
	}, [toast]);

	// Tag conversation (local state + localStorage)
	const handleTagChange = useCallback((convId, tagId) => {
		setConvTags((prev) => {
			const updated = { ...prev };
			if (tagId) {
				updated[convId] = tagId;
			} else {
				delete updated[convId];
			}
			localStorage.setItem('swc-conv-tags', JSON.stringify(updated));
			return updated;
		});
	}, []);



	// Enrich conversations with local pin/tag state
	const enrichedConversations = conversations.map((conv) => ({
		...conv,
		isPinned: pinnedConvos.includes(conv.id),
		tag: convTags[conv.id] || null,
	}));

	// Handle copy feedback from ChatMessage
	const handleCopy = useCallback((message, isError) => {
		if (isError) {
			toast.error(message);
		} else {
			toast.success(message);
		}
	}, [toast]);

	// Handle feedback from ChatMessage
	const handleFeedback = useCallback((type) => {
		if (type === 'regenerate') {
			toast.info(__('Regenerating response...', 'agentflow-ai'));
		} else {
			toast.success(__('Feedback submitted', 'agentflow-ai'));
		}
	}, [toast]);

	// Toggle dark mode
	const toggleDarkMode = useCallback(() => {
		setDarkMode((prev) => !prev);
	}, []);

	return (
		<div className={cn(
			'flex h-[calc(100vh-130px)] rounded-2xl overflow-hidden shadow-lg font-sans',
			'bg-white border border-gray-200',
			'dark:bg-slate-900 dark:border-slate-700',
			darkMode && 'dark'
		)}>
			{/* Sidebar - Light in light mode, Dark in dark mode */}
			<aside
				className={cn(
					'flex flex-col transition-all duration-300 border-r',
					'w-[280px] bg-gray-50 border-gray-200',
					'dark:bg-black dark:border-slate-800',
					!sidebarOpen && 'w-0 opacity-0 overflow-hidden'
				)}
			>
				{/* Header with App Name */}
				<div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-black">
					<div className="flex items-center gap-2">
						<Bot className="w-5 h-5 text-indigo-500 dark:text-indigo-400" />
						<span className="font-semibold text-gray-900 dark:text-white">QuarksolAI</span>
					</div>
				</div>

				{/* New Chat Button */}
				<div className="p-3 bg-gray-50 dark:bg-black">
					<button
						className="w-full h-10 rounded-lg bg-primary text-primary-foreground flex items-center justify-center gap-2 text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm"
						onClick={handleNewConversation}
					>
						<Plus className="w-4 h-4" />
						{__('New Chat', 'agentflow-ai')}
					</button>
				</div>

				{/* Conversations Section */}
				<div className="flex items-center justify-between px-4 py-2 border-t border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-black">
					<h3 className="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
						{__('Recent', 'agentflow-ai')}
					</h3>
				</div>
				<div className="flex-1 overflow-y-auto bg-gray-50 dark:bg-black">
					<ConversationList
						conversations={enrichedConversations}
						activeId={activeConversation}
						onSelect={handleSelectConversation}
						onDelete={handleDeleteConversation}
						onPin={handlePinConversation}
						onRename={handleRenameConversation}
						onExport={handleExportConversation}
						onTagChange={handleTagChange}
						isLoading={isLoading}
						darkMode={darkMode}
					/>
				</div>

			</aside>

			{/* Main Chat Area - Dark Gray in dark mode */}
			<main className="flex-1 flex flex-col min-h-0 overflow-hidden bg-gray-50 dark:bg-slate-900">
				<header className="flex items-center gap-3 px-5 py-3 border-b border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900">
					<button
						className="w-9 h-9 border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-700 dark:hover:text-white flex items-center justify-center transition-colors"
						onClick={() => setSidebarOpen(!sidebarOpen)}
					>
						<Menu className="w-4 h-4" />
					</button>
					<h2 className="text-lg font-semibold text-gray-900 dark:text-white flex-1">
						{activeConversation
							? conversations.find((c) => c.id === activeConversation)?.title ||
							__('Chat', 'agentflow-ai')
							: __('New Chat', 'agentflow-ai')}
					</h2>

					{/* Agent/Workflow Dropdown Selector */}
					<div className="relative hidden sm:block" ref={dropdownRef}>
						<button
							onClick={() => setShowAgentDropdown(!showAgentDropdown)}
							className="flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 rounded-full text-sm text-gray-600 dark:text-slate-300 transition-colors"
						>
							<Bot className="w-4 h-4" />
							<span className="font-medium max-w-[150px] truncate">
								{availableAgents.find(a => a.id === selectedAgent)?.name
									|| 'Select Agent'}
							</span>
							<ChevronDown className={cn("w-4 h-4 transition-transform", showAgentDropdown && "rotate-180")} />
						</button>

						{/* Dropdown Menu */}
						{showAgentDropdown && (
							<div className="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 py-2 z-50 max-h-80 overflow-y-auto">
								{/* Agents Section */}
								{availableAgents.length > 0 && (
									<>
										<div className="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
											{__('AI Agents', 'agentflow-ai')}
										</div>
										{availableAgents.map((agent) => (
											<button
												key={agent.id}
												onClick={() => {
													setSelectedAgent(agent.id);
													setSelectedType('agent');
													setShowAgentDropdown(false);
												}}
												className={cn(
													"w-full flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors",
													selectedAgent === agent.id && selectedType === 'agent' && "bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400"
												)}
											>
												<Bot className="w-4 h-4 flex-shrink-0" />
												<div className="flex-1 text-left">
													<div className="font-medium text-gray-900 dark:text-white truncate">{agent.name}</div>
													{agent.description && (
														<div className="text-xs text-gray-500 dark:text-slate-400 truncate">{agent.description}</div>
													)}
												</div>
											</button>
										))}
									</>
								)}

								{/* Workflows Section hidden â€” backend preserved for future use
								{availableWorkflows.length > 0 && (
									<>
										<div className="border-t border-gray-200 dark:border-slate-700 mt-2 pt-2">
											<div className="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
												{__('Workflows', 'agentflow-ai')}
											</div>
										</div>
										{availableWorkflows.map((workflow) => (
											<button
												key={workflow.id}
												onClick={() => {
													setSelectedAgent(workflow.slug || workflow.id);
													setSelectedType('workflow');
													setShowAgentDropdown(false);
												}}
												className={cn(
													"w-full flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors",
													(selectedAgent === workflow.id || selectedAgent === workflow.slug) && selectedType === 'workflow' && "bg-purple-50 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400"
												)}
											>
												<span className="w-4 h-4 flex-shrink-0 text-center"></span>
												<div className="flex-1 text-left">
													<div className="font-medium text-gray-900 dark:text-white truncate">{workflow.name}</div>
													{workflow.description && (
														<div className="text-xs text-gray-500 dark:text-slate-400 truncate">{workflow.description}</div>
													)}
												</div>
											</button>
										))}
									</>
								)}
								*/}

								{/* Teams Section hidden â€” backend preserved for future use
								{availableTeams.length > 0 && (
									<>
										<div className="border-t border-gray-200 dark:border-slate-700 mt-2 pt-2">
											<div className="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
												{__('Teams', 'agentflow-ai')}
											</div>
										</div>
										{availableTeams.map((team) => (
											<button
												key={team.id}
												onClick={() => {
													setSelectedAgent(team.group_id || team.id);
													setSelectedType('team');
													setShowAgentDropdown(false);
												}}
												className={cn(
													"w-full flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors",
													(selectedAgent === team.id || selectedAgent === team.group_id) && selectedType === 'team' && "bg-blue-50 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400"
												)}
											>
												<span className="w-4 h-4 flex-shrink-0 flex items-center justify-center text-lg">{team.avatar || 'ðŸ‘¥'}</span>
												<div className="flex-1 text-left">
													<div className="font-medium text-gray-900 dark:text-white truncate">{team.name}</div>
													{team.description && (
														<div className="text-xs text-gray-500 dark:text-slate-400 truncate">{team.description}</div>
													)}
												</div>
											</button>
										))}
									</>
								)}
								*/}

								{/* Empty State */}
								{availableAgents.length === 0 && (
									<div className="px-3 py-4 text-sm text-gray-500 dark:text-slate-400 text-center">
										{__('No agents available', 'agentflow-ai')}
									</div>
								)}
							</div>
						)}
					</div>

					{/* Dark Mode Toggle */}
					<button
						className="w-9 h-9 border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 flex items-center justify-center transition-colors"
						onClick={toggleDarkMode}
						title={darkMode ? __('Light mode', 'agentflow-ai') : __('Dark mode', 'agentflow-ai')}
					>
						{darkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
					</button>

				</header>

				{error && (
					<div className="flex items-center justify-between px-5 py-3 bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm">
						{error}
						<button onClick={() => setError(null)} className="text-lg hover:text-red-800 dark:hover:text-red-300">Ã—</button>
					</div>
				)}

				{/* Chat Content */}
				<div className="flex-1 flex flex-col overflow-hidden bg-white dark:bg-slate-900" ref={chatContainerRef}>
					{messages.length === 0 ? (
						<ChatStarter
							onSend={handleSendMessage}
							onAttachment={handleAttachment}
							onPersonaSelect={handleQuickAction}
							disabled={false}
							isStreaming={isSending}
							onStopGeneration={handleStopGeneration}
							placeholder={__('Ask me to create pages, edit content, manage productsâ€¦', 'agentflow-ai')}
							selectedAgent={availableAgents.find(a => a.id === selectedAgent || a.agent_id === selectedAgent)}
							darkMode={darkMode}
						/>
					) : (
						<>
							<ChatMessages
								messages={messages}
								className="flex-1 overflow-y-auto px-6 py-4"
								onCopy={handleCopy}
								onFeedback={handleFeedback}
								onOptionSelect={handleSendMessage}
								darkMode={darkMode}
							>
								{pendingMessages.map((message) => (
									<div key={message.id} className="flex items-start gap-3 py-4 flex-row-reverse">
										<div className="size-9 rounded-full bg-primary text-primary-foreground text-sm flex items-center justify-center shrink-0">
											U
										</div>
										<div className="flex flex-col gap-1 flex-1 items-end">
											<div className="rounded-2xl rounded-br-sm px-5 py-3.5 text-sm shadow-sm relative group bg-primary/85 text-primary-foreground max-w-[85%] border border-primary/30">
												<p className="my-1 leading-relaxed">{message.content}</p>
												<div className="mt-2 text-[11px] font-medium uppercase tracking-[0.08em] text-primary-foreground/75">
													Pending
												</div>
											</div>
											<span className="text-xs text-muted-foreground px-1">
												{message.timestamp}
											</span>
										</div>
									</div>
								))}
							</ChatMessages>
							<div className="p-4 pb-6 bg-white dark:bg-slate-900">
								<div className="max-w-3xl mx-auto w-full">
									<ChatStarter
										onSend={handleSendMessage}
										onAttachment={handleAttachment}
										disabled={false}
										isStreaming={isSending}
										onStopGeneration={handleStopGeneration}
										placeholder={__('Continue the conversationâ€¦', 'agentflow-ai')}
										compact={true}
										darkMode={darkMode}
									/>
								</div>
							</div>
						</>
					)}
				</div>
			</main>
		</div>
	);
}

/**
 * WorkspacePage Component - Wrapped with ToastProvider
 */
export default function WorkspacePage() {
	return (
		<ToastProvider>
			<WorkspaceContent />
		</ToastProvider>
	);
}

WorkspacePage.propTypes = {};
