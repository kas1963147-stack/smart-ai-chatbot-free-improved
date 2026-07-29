/**
 * useChatWidget Hook
 *
 * Core hook for managing chat widget state, messaging, and sessions.
 * Features:
 * - Session management with localStorage persistence
 * - Chat history with auto-expiry
 * - Streaming message support via SSE
 * - Multi-agent/team handling
 * - Performance optimizations (throttling, debouncing, abort controllers)
 */
import { useCallback, useEffect, useRef, useState } from 'react';

// Constants
const STORAGE_KEYS = {
    visitorId: 'swc_visitor_id',
    chatHistory: 'swc_chat_history', // Base key, will be appended with widget ID
    soundEnabled: 'swc_sound_enabled',
    proactiveShown: 'swc_proactive_shown',
    lastWidgetId: 'swc_last_widget_id', // Track last used widget
};

const MESSAGE_RETENTION_MS = 24 * 60 * 60 * 1000; // 24 hours
const MAX_MESSAGES = 50;

/**
 * Strip tool call JSON artifacts from displayed text.
 * 
 * When the AI calls tools (load_skill, lead_collector, etc.), the raw JSON
 * gets streamed as text. This function removes those internal artifacts
 * so the user only sees the natural language response.
 */
const stripToolCallArtifacts = (text) => {
    if (!text) return text;
    
    let cleaned = text;
    
    // Strategy: find [{"callId": markers and strip everything from there
    // to the matching closing bracket, using bracket depth tracking
    let safetyCounter = 0;
    while (cleaned.includes('{"callId"') && safetyCounter < 10) {
        safetyCounter++;
        const startIdx = cleaned.indexOf('[{"callId"');
        const altStartIdx = cleaned.indexOf('{"callId"');
        const actualStart = startIdx !== -1 ? startIdx : altStartIdx;
        
        if (actualStart === -1) break;
        
        // Track bracket depth to find the end of the JSON block
        let depth = 0;
        let endIdx = actualStart;
        let inString = false;
        let escaped = false;
        
        for (let i = actualStart; i < cleaned.length; i++) {
            const ch = cleaned[i];
            
            if (escaped) { escaped = false; continue; }
            if (ch === '\\') { escaped = true; continue; }
            
            if (ch === '"' && !escaped) { inString = !inString; continue; }
            if (inString) continue;
            
            if (ch === '[' || ch === '{') depth++;
            if (ch === ']' || ch === '}') {
                depth--;
                if (depth <= 0) {
                    endIdx = i + 1;
                    break;
                }
            }
        }
        
        if (endIdx > actualStart) {
            cleaned = cleaned.substring(0, actualStart) + cleaned.substring(endIdx);
        } else {
            // Couldn't find end — strip from start to end (partial/streaming)
            cleaned = cleaned.substring(0, actualStart);
        }
    }
    
    // Clean up any leftover fragments: "}]", "]", stray brackets at start
    cleaned = cleaned.replace(/^[\s"}\]]+/, '');
    
    // Clean up extra whitespace
    cleaned = cleaned.replace(/^\s+/, '').replace(/\s+$/, '');
    
    return cleaned;
};


/**
 * Extract interactive options from AI response text.
 * 
 * The AI embeds clickable options using [OPTIONS]...[/OPTIONS] markers.
 * This function extracts the JSON options array and returns the clean text
 * without the markers, plus the parsed options.
 * 
 * @param {string} text - The raw AI response text
 * @returns {{ text: string, options: string[] }} Clean text and extracted options
 */
const extractInteractiveOptions = (text) => {
    if (!text) return { text: text || '', options: [] };

    const optionsRegex = /\[OPTIONS\]\s*([\s\S]*?)\s*\[\/OPTIONS\]/gi;
    const match = optionsRegex.exec(text);

    if (!match) {
        return { text, options: [] };
    }

    // Remove the [OPTIONS]...[/OPTIONS] block from the displayed text
    const cleanText = text.replace(optionsRegex, '').replace(/\s+$/, '');

    // Parse the JSON array inside the block
    let options = [];
    try {
        const jsonStr = match[1].trim();
        const parsed = JSON.parse(jsonStr);
        if (Array.isArray(parsed)) {
            options = parsed.filter(opt => typeof opt === 'string' && opt.trim()).slice(0, 8);
        }
    } catch (e) {
        // If JSON parsing fails, try to extract options from a simpler format
        // e.g., line-by-line options or comma-separated
        const rawContent = match[1].trim();
        const lines = rawContent.split('\n').map(l => l.replace(/^[-•*]\s*/, '').trim()).filter(Boolean);
        if (lines.length > 0 && lines.length <= 8) {
            options = lines;
        }
    }

    return { text: cleanText, options };
};


/**
 * Strip internal monologue / chain-of-thought text from AI responses.
 * 
 * Some AI models leak their internal reasoning into the visible response.
 * This catches common patterns like:
 * - "Now we need to respond to..."
 * - "The system expects the assistant to..."
 * - "The conversation ended after..."
 * - "Let me think about..." 
 * - "The latest user message in the transcript was..."
 */
const stripInternalMonologue = (text) => {
    if (!text) return text;

    // Patterns that indicate internal monologue has started.
    // When detected at the START of a paragraph, everything from that paragraph
    // onwards is stripped — the AI has switched from user-facing to self-talk.
    const monologueStarters = [
        /\n\n(?:Now (?:we|I|the assistant) (?:need|should|must|will|have to|can) )/i,
        /\n\n(?:(?:The |This )(?:system|assistant|AI|bot|conversation|task|user|latest|above|previous) (?:expects?|needs?|shows?|ended|says?|wants?|asks?|requires?|message|is |was ))/i,
        /\n\n(?:(?:We|I) (?:need to|should|must|have to|will now) (?:respond|reply|answer|handle|process|generate|provide|create))/i,
        /\n\n(?:(?:Let me|Let's|I'll|I will|I need to|I should) (?:think|analyze|process|check|review|consider|look|see|figure|determine|formulate))/i,
        /\n\n(?:(?:Based on|Looking at|According to|Given|Considering) (?:the|this|that|my|our) (?:conversation|transcript|history|context|instructions?|prompt|system))/i,
        /\n\n(?:(?:The user|The visitor|The customer|The message|The query|The request) (?:is asking|wants|asked|said|wrote|sent|mentioned|requested|needs))/i,
        /\n\n(?:(?:My |The )?(?:response|reply|answer|output|task) (?:should|must|needs to|will|is to) )/i,
        /\n\n(?:(?:In this case|In this scenario|At this point|Here|So), (?:I|we|the) (?:should|need|must|will|can))/i,
        /\n\n(?:But (?:in |the |conversation|no |there ))/i,
        /\n\n(?:No further user message)/i,
    ];

    let cleaned = text;

    for (const pattern of monologueStarters) {
        const match = pattern.exec(cleaned);
        if (match) {
            // Cut everything from the monologue start onwards
            cleaned = cleaned.substring(0, match.index);
            break; // Only need to find the first occurrence
        }
    }

    // Clean up trailing whitespace
    cleaned = cleaned.replace(/\s+$/, '');

    return cleaned;
};

/**
 * Get widget-specific storage key for chat history
 */
const getWidgetHistoryKey = (widgetId) => {
    if (!widgetId) return STORAGE_KEYS.chatHistory;
    return `${STORAGE_KEYS.chatHistory}_${widgetId}`;
};

/**
 * Generate a unique visitor ID
 */
const generateVisitorId = () => {
    return `v_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`;
};

/**
 * Throttle function for performance
 */
const throttle = (fn, ms) => {
    let lastCall = 0;
    return (...args) => {
        const now = Date.now();
        if (now - lastCall >= ms) {
            lastCall = now;
            return fn(...args);
        }
    };
};

/**
 * Main hook
 */
export default function useChatWidget(config = {}) {
    const { apiUrl, nonce, settings = {}, widgetId: initialWidgetId, agentDbId: configAgentDbId } = config;
    const availableWidgets = config?.availableWidgets || [];

    // Current widget ID for per-widget history
    const [currentWidgetId, setCurrentWidgetId] = useState(initialWidgetId || null);

    // Track current agent DB ID — updates when user switches widgets
    const [currentAgentDbId, setCurrentAgentDbId] = useState(configAgentDbId || 0);

    // Core state
    const [isOpen, setIsOpen] = useState(false);
    const [isMinimized, setIsMinimized] = useState(false);
    const [messages, setMessages] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isStreaming, setIsStreaming] = useState(false);
    const [activeTool, setActiveTool] = useState(null);
    const [hasUsedTool, setHasUsedTool] = useState(false);
    const [error, setError] = useState(null);

    // Agent/Team state
    const [currentAgent, setCurrentAgent] = useState(null);
    const [availableAgents, setAvailableAgents] = useState([]);
    const [showAgentSelector, setShowAgentSelector] = useState(false);

    // Session state
    const [sessionId, setSessionId] = useState(null);
    const [visitorId, setVisitorId] = useState(null);
    const [pastSessions, setPastSessions] = useState([]);

    // Refs for cleanup
    const abortControllerRef = useRef(null);
    const sseEventSourceRef = useRef(null);
    const messagesEndRef = useRef(null);
    const isMounted = useRef(true);

    // Preferences
    const [soundEnabled, setSoundEnabled] = useState(true);

    /**
     * Initialize on mount
     */
    useEffect(() => {
        isMounted.current = true;
        initializeWidget();

        return () => {
            isMounted.current = false;
            cleanup();
        };
    }, []);

    /**
     * Initialize widget state from storage
     */
    const initializeWidget = useCallback(() => {
        // Get or create visitor ID
        let vid = localStorage.getItem(STORAGE_KEYS.visitorId);
        if (!vid) {
            vid = generateVisitorId();
            localStorage.setItem(STORAGE_KEYS.visitorId, vid);
        }
        setVisitorId(vid);

        // Load sound preference
        const savedSound = localStorage.getItem(STORAGE_KEYS.soundEnabled);
        if (savedSound !== null) {
            setSoundEnabled(savedSound === 'true');
        } else if (settings.enableSounds !== undefined) {
            setSoundEnabled(settings.enableSounds);
        }

        // Set initial widget ID from config or last used
        if (initialWidgetId) {
            setCurrentWidgetId(initialWidgetId);
            localStorage.setItem(STORAGE_KEYS.lastWidgetId, initialWidgetId);
        } else {
            const lastWidget = localStorage.getItem(STORAGE_KEYS.lastWidgetId);
            if (lastWidget) {
                setCurrentWidgetId(lastWidget);
            }
        }

        // Load chat history for current widget
        loadChatHistory(currentWidgetId);

        // Load past sessions
        const pastKey = `swc_past_sessions_${currentWidgetId || initialWidgetId || 'default'}`;
        const past = localStorage.getItem(pastKey);
        if (past) {
            try {
                setPastSessions(JSON.parse(past));
            } catch(e){}
        }
    }, [settings, initialWidgetId, currentWidgetId]);

    /**
     * Load chat history from localStorage for a specific widget
     */
    const loadChatHistory = useCallback((widgetId = null) => {
        try {
            const storageKey = getWidgetHistoryKey(widgetId || currentWidgetId);
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                const data = JSON.parse(saved);
                // Only restore if less than 24 hours old
                if (data.timestamp && (Date.now() - data.timestamp) < MESSAGE_RETENTION_MS) {
                    setMessages(data.messages || []);
                    if (data.sessionId) setSessionId(data.sessionId);
                    return data.messages || [];
                }
                // Clear stale history
                localStorage.removeItem(storageKey);
            }
            setMessages([]);
            return [];
        } catch (err) {
            console.warn('Failed to load chat history:', err);
            setMessages([]);
            return [];
        }
    }, [currentWidgetId]);

    /**
     * Save chat history to localStorage for current widget
     */
    const saveChatHistory = useCallback((msgs, widgetId = null, currentSessionId = null) => {
        try {
            const storageKey = getWidgetHistoryKey(widgetId || currentWidgetId);
            const data = {
                timestamp: Date.now(),
                widgetId: widgetId || currentWidgetId,
                sessionId: currentSessionId || sessionId,
                messages: msgs.slice(-MAX_MESSAGES), // Keep last N messages
            };
            localStorage.setItem(storageKey, JSON.stringify(data));
        } catch (err) {
            console.warn('Failed to save chat history:', err);
        }
    }, [currentWidgetId, sessionId]);

    /**
     * Add a message to the chat
     */
    const addMessage = useCallback((message) => {
        setMessages((prev) => {
            const newMessages = [
                ...prev,
                {
                    id: `msg_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`,
                    timestamp: new Date().toISOString(),
                    ...message,
                },
            ];
            saveChatHistory(newMessages);
            return newMessages;
        });
    }, [saveChatHistory]);

    /**
     * Update the last bot message (for streaming)
     */
    const updateLastBotMessage = useCallback((content, isComplete = false) => {
        setMessages((prev) => {
            const lastIdx = prev.length - 1;

            // Extract interactive options from the content
            const { text: cleanContent, options } = extractInteractiveOptions(content);

            if (lastIdx < 0 || prev[lastIdx].role !== 'assistant') {
                // If there's no assistant message yet, create it now (but only if we have text)
                // This prevents empty bubbles from showing before the stream actually sends text.
                if (!cleanContent && (!options || options.length === 0)) {
                    return prev;
                }

                const msgObj = {
                    id: `msg_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`,
                    timestamp: new Date().toISOString(),
                    role: 'assistant',
                    content: cleanContent,
                    isStreaming: !isComplete,
                    options: isComplete ? options : [],
                };
                const newMessages = [...prev, msgObj];
                if (isComplete) saveChatHistory(newMessages);
                return newMessages;
            }

            const updated = [...prev];
            updated[lastIdx] = {
                ...updated[lastIdx],
                content: cleanContent,
                isStreaming: !isComplete,
                // Only attach options when the message is complete (not while streaming)
                options: isComplete ? options : [],
            };
            if (isComplete) {
                saveChatHistory(updated);
            }
            return updated;
        });
    }, [saveChatHistory]);

    /**
     * Resolve agents for current page
     */
    const resolveAgents = useCallback(async () => {
        if (!apiUrl) return;

        try {
            const params = new URLSearchParams({
                url: window.location.href,
                page_id: settings.pageId || '',
                post_type: settings.postType || '',
                is_cart: settings.isCart || 'false',
                is_checkout: settings.isCheckout || 'false',
            });

            const response = await fetch(`${apiUrl}/resolve?${params.toString()}`, {
                headers: { 'X-WP-Nonce': nonce },
            });

            if (!response.ok) throw new Error('Failed to resolve agents');

            const data = await response.json();
            const agents = data.agents || [];
            setAvailableAgents(agents);

            // If multiple agents and requires selection, show selector
            if (data.requires_selection && agents.length > 1) {
                setShowAgentSelector(true);
            } else if (agents.length > 0) {
                selectAgent(agents[0]);
            }
        } catch (err) {
            console.warn('Agent resolution failed:', err);
        }
    }, [apiUrl, nonce, settings]);

    /**
     * Select an agent
     */
    const selectAgent = useCallback((agent) => {
        setCurrentAgent(agent);
        setShowAgentSelector(false);

        // Show welcome message ONLY ONCE per widget per browser session
        // Uses sessionStorage flag to completely avoid stale closure issues with messages.length
        if (agent.welcome_message) {
            const widgetKey = currentWidgetId || 'default';
            const welcomeSessionKey = `swc_welcome_shown_${widgetKey}`;
            const alreadyShownThisSession = sessionStorage.getItem(welcomeSessionKey);

            // Also check localStorage for existing chat history (returning visitor)
            const storageKey = getWidgetHistoryKey(currentWidgetId);
            const savedHistory = localStorage.getItem(storageKey);
            let hasExistingHistory = false;

            if (savedHistory) {
                try {
                    const data = JSON.parse(savedHistory);
                    if (data.timestamp && (Date.now() - data.timestamp) < MESSAGE_RETENTION_MS) {
                        hasExistingHistory = (data.messages || []).length > 0;
                    }
                } catch (e) {
                    // Ignore parse errors
                }
            }

            // Only show if: not shown this session AND no existing history
            if (!alreadyShownThisSession && !hasExistingHistory) {
                sessionStorage.setItem(welcomeSessionKey, 'true');
                addMessage({
                    role: 'assistant',
                    content: agent.welcome_message,
                    agent: agent.name,
                });
            }
        }

        // Initialize session
        initSession(agent);
    }, [addMessage, currentWidgetId]);

    /**
     * Initialize chat session
     */
    const initSession = useCallback(async (agent) => {
        if (!apiUrl || !agent) return;

        try {
            const response = await fetch(`${apiUrl}/sessions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({
                    agent_id: agent.id,
                    visitor_id: visitorId,
                    url: window.location.href,
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                }),
            });

            if (!response.ok) throw new Error('Failed to create session');

            const data = await response.json();
            const newSessionId = data.session?.id || data.session_id || data.id;
            setSessionId(newSessionId);
            // Save the session ID immediately to current chat
            setMessages(prev => {
                saveChatHistory(prev, currentWidgetId, newSessionId);
                return prev;
            });
        } catch (err) {
            console.warn('Session init failed:', err);
        }
    }, [apiUrl, nonce, visitorId]);

    /**
     * Send a message with streaming support via fetch POST
     */
    const sendMessage = useCallback(async (text) => {
        if (!text.trim()) return;

        // Don't allow sending while streaming — user must stop generation first
        if (isStreaming) return;

        const message = text.trim();

        // Add user message
        addMessage({
            role: 'user',
            content: message,
        });

        // Do NOT add a placeholder bot message yet!
        // This allows the ActiveToolIndicator "AI is thinking..." to show
        // while we wait for the first chunk to arrive.

        setIsLoading(true);
        setIsStreaming(false); // Not streaming until chunks arrive
        setActiveTool(null);
        setHasUsedTool(false);
        setError(null);

        try {
            // Cancel any existing request
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
            abortControllerRef.current = new AbortController();

            // Use fetch with POST for SSE streaming
            const response = await fetch(`${apiUrl}/chat/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce || '',
                    'Accept': 'text/event-stream',
                },
                body: JSON.stringify({
                    message,
                    session_id: sessionId || '',
                    agent_id: currentAgentDbId || currentAgent?.id || '',
                    visitor_id: visitorId || '',
                    url: window.location.href,
                    post_id: settings.pageId || 0,
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                }),
                signal: abortControllerRef.current.signal,
            });

            if (!response.ok) {
                // Try to parse the error response for a user-friendly message
                let errorMessage = '';
                try {
                    const errorData = await response.json();
                    errorMessage = errorData?.data?.message || errorData?.message || '';
                } catch (e) {
                    // Response wasn't JSON, use fallback messages
                }

                if (!errorMessage) {
                    // Fallback to friendly messages based on status code
                    switch (response.status) {
                        case 429:
                            errorMessage = "You're sending messages too quickly. Please wait a moment and try again.";
                            break;
                        case 403:
                            errorMessage = 'Access denied. Please refresh the page and try again.';
                            break;
                        case 500:
                            errorMessage = 'Something went wrong on our end. Please try again shortly.';
                            break;
                        default:
                            errorMessage = 'An error occurred. Please try again.';
                    }
                }

                throw new Error(errorMessage);
            }

            // Handle SSE stream with ReadableStream
            const reader = response.body?.getReader();
            if (!reader) {
                throw new Error('No response body');
            }

            const decoder = new TextDecoder();
            let fullContent = '';
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    // Stream completed
                    if (fullContent) {
                        updateLastBotMessage(stripInternalMonologue(stripToolCallArtifacts(fullContent)), true);
                    }
                    break;
                }

                // Decode chunk and add to buffer
                buffer += decoder.decode(value, { stream: true });

                // Process complete SSE events from buffer
                const events = buffer.split('\n\n');
                buffer = events.pop() || ''; // Keep incomplete event in buffer

                for (const eventStr of events) {
                    if (!eventStr.trim()) continue;

                    // Parse SSE format: "event: type\ndata: {json}"
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

                        if (eventType === 'chunk' || data.text) {
                            // Streaming chunk
                            setIsStreaming(true); // Now we are officially streaming
                            const chunk = data.text || data.content || '';
                            fullContent += chunk;
                            // Strip tool call JSON artifacts from display
                            // Tool calls appear as [{"callId":"...","name":"...","inputs":...,"result":"..."}]
                            const displayContent = stripInternalMonologue(stripToolCallArtifacts(fullContent));
                            updateLastBotMessage(displayContent, false);
                        } else if (eventType === 'tool_start') {
                            setActiveTool(data.name || 'tool');
                            setHasUsedTool(true);
                        } else if (eventType === 'tool_result') {
                            setActiveTool(null);
                        } else if (eventType === 'final' || data.response) {
                            // Final response with full content
                            const finalText = data.response?.text || data.response?.content || fullContent;
                            updateLastBotMessage(stripInternalMonologue(stripToolCallArtifacts(finalText)), true);
                        } else if (eventType === 'error' || data.error) {
                            throw new Error(data.message || 'An error occurred');
                        } else if (eventType === 'done' || data.finished) {
                            // Stream complete
                            updateLastBotMessage(stripInternalMonologue(stripToolCallArtifacts(fullContent)), true);
                        }
                    } catch (parseErr) {
                        // Re-throw actual error events (not JSON parse issues)
                        // so they propagate to the outer catch and display in the UI
                        if (parseErr.message && parseErr.message !== 'Unexpected token' && eventType === 'error') {
                            throw parseErr;
                        }
                        console.warn('SSE parse error:', parseErr, eventData);
                    }
                }
            }

            cleanup();

        } catch (err) {
            if (err.name === 'AbortError') {
                // Request was aborted (user stopped generation)
                // Mark the last bot message as complete with whatever was received
                setMessages((prev) => {
                    const lastIdx = prev.length - 1;
                    if (lastIdx >= 0 && prev[lastIdx].role === 'assistant') {
                        const updated = [...prev];
                        updated[lastIdx] = { ...updated[lastIdx], isStreaming: false };
                        if (!updated[lastIdx].content) {
                            return updated.slice(0, -1);
                        }
                        saveChatHistory(updated);
                        return updated;
                    }
                    return prev;
                });
                cleanup();
                return;
            }

            console.error('Chat error:', err);

            // Show the error as a bot message so the user sees it clearly in the chat
            addMessage({
                role: 'assistant',
                content: `⚠️ ${err.message || 'Something went wrong. Please try again.'}`,
            });

            // Remove the empty bot message if it was created (before the error message)
            setMessages((prev) => {
                // Find and remove any empty assistant messages (but keep the error one we just added)
                return prev.filter((msg, idx) => {
                    if (msg.role === 'assistant' && !msg.content && idx < prev.length - 1) {
                        return false;
                    }
                    return true;
                });
            });
            cleanup();
        }
    }, [
        isStreaming,
        addMessage,
        updateLastBotMessage,
        apiUrl,
        nonce,
        sessionId,
        currentAgent,
        visitorId,
    ]);

    /**
     * Cleanup function
     */
    const cleanup = useCallback(() => {
        setIsLoading(false);
        setIsStreaming(false);
        setActiveTool(null);
        setHasUsedTool(false);

        if (sseEventSourceRef.current) {
            sseEventSourceRef.current.close();
            sseEventSourceRef.current = null;
        }

        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
            abortControllerRef.current = null;
        }
    }, []);

    /**
     * Stop the current generation (abort streaming response)
     */
    const stopGeneration = useCallback(() => {
        if (!isStreaming) return;

        // Abort the fetch request — this triggers the AbortError handler in sendMessage
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
            abortControllerRef.current = null;
        }

        if (sseEventSourceRef.current) {
            sseEventSourceRef.current.close();
            sseEventSourceRef.current = null;
        }

        // Mark streaming as done
        setIsLoading(false);
        setIsStreaming(false);
        setActiveTool(null);

        // Mark the last bot message as complete with whatever content was received
        setMessages((prev) => {
            const lastIdx = prev.length - 1;
            if (lastIdx >= 0 && prev[lastIdx].role === 'assistant') {
                const updated = [...prev];
                updated[lastIdx] = { ...updated[lastIdx], isStreaming: false };
                // If the bot message is empty, remove it
                if (!updated[lastIdx].content) {
                    return updated.slice(0, -1);
                }
                saveChatHistory(updated);
                return updated;
            }
            return prev;
        });
    }, [isStreaming, saveChatHistory]);

    /**
     * Clear chat history for current widget (Start New Chat)
     */
    const clearHistory = useCallback(() => {
        // Save current chat into pastSessions before clearing
        setPastSessions((prev) => {
            if (messages.length <= 1) return prev; // Don't save empty/welcome-only chats
            const userMsg = messages.find(m => m.role === 'user');
            if (!userMsg) return prev;
            
            const title = userMsg.content.slice(0, 40) + (userMsg.content.length > 40 ? '...' : '');
            const newSessions = [{
                id: sessionId || `local_${Date.now()}`,
                title: title,
                date: Date.now(),
                messages: [...messages]
            }, ...prev.filter(s => s.id !== sessionId)].slice(0, 30); // Keep top 30
            
            const pastKey = `swc_past_sessions_${currentWidgetId || 'default'}`;
            localStorage.setItem(pastKey, JSON.stringify(newSessions));
            return newSessions;
        });

        setMessages([]);
        setSessionId(null);
        const storageKey = getWidgetHistoryKey(currentWidgetId);
        localStorage.removeItem(storageKey);

        // Show welcome message again
        if (currentAgent?.welcome_message) {
            addMessage({
                role: 'assistant',
                content: currentAgent.welcome_message,
                agent: currentAgent.name,
            });
        }
    }, [messages, sessionId, currentAgent, addMessage, currentWidgetId]);

    /**
     * Load an old session from history
     */
    const loadSession = useCallback((id) => {
        setPastSessions(prev => {
            const session = prev.find(s => s.id === id);
            if (session) {
                setMessages(session.messages || []);
                setSessionId(session.id);
                saveChatHistory(session.messages || [], currentWidgetId, session.id);
            }
            return prev;
        });
    }, [currentWidgetId, saveChatHistory]);

    /**
     * Switch to a different widget and load its history
     */
    const switchWidget = useCallback((newWidgetId) => {
        if (!newWidgetId || newWidgetId === currentWidgetId) return;

        // Update current widget ID
        setCurrentWidgetId(newWidgetId);
        localStorage.setItem(STORAGE_KEYS.lastWidgetId, newWidgetId);

        // Update agent DB ID from the selected widget's config
        const widgetData = availableWidgets.find(w => w.id === newWidgetId);
        if (widgetData?.agentDbId) {
            setCurrentAgentDbId(widgetData.agentDbId);
            console.log('[SWC] Switched to widget', newWidgetId, 'agent_id:', widgetData.agentDbId);
        }

        // Reset session for new widget
        setSessionId(null);
        setCurrentAgent(null);

        // Load chat history for the new widget
        loadChatHistory(newWidgetId);
        
        // Load past sessions for new widget
        const pastKey = `swc_past_sessions_${newWidgetId || 'default'}`;
        const past = localStorage.getItem(pastKey);
        if (past) {
            try {
                setPastSessions(JSON.parse(past));
            } catch(e){}
        } else {
            setPastSessions([]);
        }
    }, [currentWidgetId, loadChatHistory, availableWidgets]);

    /**
     * Toggle widget open/closed
     */
    const toggle = useCallback(() => {
        setIsOpen((prev) => {
            if (!prev) {
                // Opening - resolve agents if not done
                if (availableAgents.length === 0) {
                    resolveAgents();
                }
                setIsMinimized(false);
            }
            return !prev;
        });
    }, [availableAgents.length, resolveAgents]);

    /**
     * Open widget
     */
    const open = useCallback(() => {
        setIsOpen(true);
        setIsMinimized(false);
        if (availableAgents.length === 0) {
            resolveAgents();
        }
    }, [availableAgents.length, resolveAgents]);

    /**
     * Close widget
     */
    const close = useCallback(() => {
        setIsOpen(false);
    }, []);

    /**
     * Minimize widget
     */
    const minimize = useCallback(() => {
        setIsMinimized(true);
    }, []);

    /**
     * Toggle sound
     */
    const toggleSound = useCallback(() => {
        setSoundEnabled((prev) => {
            const newVal = !prev;
            localStorage.setItem(STORAGE_KEYS.soundEnabled, String(newVal));
            return newVal;
        });
    }, []);

    /**
 * Scroll to bottom (throttled)
 */
    const scrollToBottom = useCallback(
        throttle((immediate = false) => {
            if (messagesEndRef.current) {
                messagesEndRef.current.scrollIntoView({
                    behavior: immediate ? 'auto' : 'smooth',
                    block: 'end'
                });
            }
        }, 100),
        []
    );

    // Auto-scroll when messages change
    useEffect(() => {
        scrollToBottom(false);
    }, [messages, scrollToBottom]);

    // Auto-scroll when chat opens (immediate, to show latest message)
    useEffect(() => {
        if (isOpen) {
            // Small delay to ensure DOM is ready
            setTimeout(() => {
                scrollToBottom(true);
            }, 50);
        }
    }, [isOpen, scrollToBottom]);

    return {
        // State
        isOpen,
        isMinimized,
        messages,
        isLoading,
        isStreaming,
        activeTool,
        hasUsedTool,
        error,

        // Agent/Team
        currentAgent,
        availableAgents,
        showAgentSelector,

        // Session
        sessionId,
        visitorId,
        currentWidgetId,

        // Preferences
        soundEnabled,

        // Refs
        messagesEndRef,

        // Sessions
        pastSessions,
        loadSession,

        // Actions
        toggle,
        open,
        close,
        minimize,
        sendMessage,
        stopGeneration,
        clearHistory,
        selectAgent,
        switchWidget,
        toggleSound,
        setError,
    };
}
