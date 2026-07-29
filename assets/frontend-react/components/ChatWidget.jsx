/**
 * ChatWidget - Main React Component
 *
 * Modern, performant chat widget built with React and Tailwind CSS.
 * Metronic v9 design aesthetic with glassmorphism and premium styling.
 */
import React, { useCallback, useEffect, useRef, useState, forwardRef, useImperativeHandle, useMemo } from 'react';
import ReactDOM from 'react-dom';
import useChatWidget from '../hooks/useChatWidget';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Configure marked for chat-friendly output
marked.setOptions({
    breaks: true,       // Convert \n to <br>
    gfm: true,          // GitHub Flavored Markdown
    headerIds: false,    // No IDs on headers (cleaner)
    mangle: false,       // Don't mangle email addresses
});

// Metronic v9 themed icons as SVG components
const Icons = {
    Close: () => (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    ),
    Send: () => (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
        </svg>
    ),
    Microphone: () => (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
        </svg>
    ),
    MicrophoneActive: () => (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 15.75a3.75 3.75 0 003.75-3.75V6a3.75 3.75 0 10-7.5 0v6a3.75 3.75 0 003.75 3.75z" />
            <path d="M19.5 10.5c0 4.148-3.352 7.5-7.5 7.5s-7.5-3.352-7.5-7.5H3c0 4.73 3.568 8.625 8.16 9.19V21h1.68v-1.31C17.432 19.125 21 15.23 21 10.5h-1.5z" />
        </svg>
    ),
    Sound: () => (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
        </svg>
    ),
    SoundOff: () => (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
        </svg>
    ),
    Reset: () => (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    ),
    Minimize: () => (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 12h-15" />
        </svg>
    ),
    Chat: () => (
        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
        </svg>
    ),
    Message: () => (
        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
    ),
    Support: () => (
        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
        </svg>
    ),
    Headset: () => (
        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 3a9 9 0 00-9 9v4a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H5a7 7 0 0114 0h-1a2 2 0 00-2 2v3a2 2 0 002 2h1a2 2 0 002-2v-4a9 9 0 00-9-9z" />
        </svg>
    ),
    Switch: () => (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
        </svg>
    ),
    ChevronDown: ({ className = "w-5 h-5", strokeWidth = 2.5 }) => (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={strokeWidth}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    ),
    Bot: () => (
        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
        </svg>
    ),
    HandWave: () => (
        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.05 4.575a1.575 1.575 0 10-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 013.15 0v1.5m-3.15 0l.075 5.925m3.075-5.925a1.575 1.575 0 20-3.15 0v3m3.15-3v1.5m0 6v-6a1.575 1.575 0 113.15 0v5.85l-2.925 8.925h-9.9l-1-7.2-2.1-.9a1.575 1.575 0 01.9-3l2.85 1.2 1.35 6" />
        </svg>
    ),
    Sparkles: () => (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a2.25 2.25 0 00-1.551-1.551L15 6.75l1.19-.414a2.25 2.25 0 001.551-1.551L18 3.75l.259 1.035a2.25 2.25 0 001.551 1.551L21 6.75l-1.19.414a2.25 2.25 0 00-1.551 1.551z" />
        </svg>
    ),
    Stop: () => (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <rect x="6" y="6" width="12" height="12" rx="2" />
        </svg>
    ),
    History: () => (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    ),
};

/**
 * Format message content with full markdown support
 * @param {string} content - The message content
 * @param {boolean} isUserBubble - Whether this is a user message (needs white link styling)
 */
function formatMessage(content, isUserBubble = false) {
    if (!content) return '';

    try {
        // Parse markdown to HTML
        const rawHtml = marked.parse(content);
        // Sanitize to prevent XSS — allow safe tags/attributes
        let cleanHtml = DOMPurify.sanitize(rawHtml, {
            ALLOWED_TAGS: [
                'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'blockquote',
                'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'del', 'span', 'div', 'sup', 'sub',
            ],
            ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'style'],
        });

        // For user bubbles, force links to white with inline styles
        // This ensures visibility against the colored bubble background
        if (isUserBubble) {
            cleanHtml = cleanHtml.replace(
                /<a\s/g,
                '<a style="color: white !important; text-decoration: underline; text-decoration-color: rgba(255,255,255,0.6);" '
            );
        }

        return cleanHtml;
    } catch (e) {
        // Fallback: basic escaping
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br/>');
    }
}

/**
 * Message Bubble Component - Metronic styled
 */
const MessageBubble = React.memo(({ message, isBot, isStreaming, onOptionSelect, isLastBot, activeTool }) => {
    const bubbleStyle = isBot ? {
        background: 'var(--swc-bg-message-bot, var(--swc-bg-main, linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%)))',
        borderRadius: 'var(--swc-bubble-radius, 20px) var(--swc-bubble-radius, 20px) var(--swc-bubble-radius, 20px) 6px',
        color: 'var(--swc-text-message-bot, var(--swc-text-primary, #1e293b))',
        boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)',
    } : {
        background: 'linear-gradient(135deg, var(--swc-primary, #6366f1) 0%, var(--swc-primary-hover, #4f46e5) 100%)',
        borderRadius: 'var(--swc-bubble-radius, 20px) var(--swc-bubble-radius, 20px) 6px var(--swc-bubble-radius, 20px)',
        color: 'white',
        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
    };

    // Show options only on the last bot message that isn't still streaming
    const showOptions = isBot && isLastBot && !isStreaming && message.options?.length > 0;

    return (
        <div className={`flex flex-col ${isBot ? 'items-start' : 'items-end'} mb-3 animate-fade-in`}>
            <div
                className={`max-w-[85%] px-4 py-3 leading-relaxed ${isBot ? '' : 'swc-user-bubble'}`}
                style={bubbleStyle}
            >
                <div
                    className="swc-markdown-content break-words"
                    dangerouslySetInnerHTML={{ __html: formatMessage(message.content, !isBot) }}
                />
                {isStreaming && !activeTool && (
                    <span className="inline-flex ml-2 gap-0.5">
                        <span className="w-1.5 h-1.5 rounded-full bg-current opacity-60 animate-bounce" style={{ animationDelay: '0ms' }} />
                        <span className="w-1.5 h-1.5 rounded-full bg-current opacity-60 animate-bounce" style={{ animationDelay: '150ms' }} />
                        <span className="w-1.5 h-1.5 rounded-full bg-current opacity-60 animate-bounce" style={{ animationDelay: '300ms' }} />
                    </span>
                )}
            </div>
            {showOptions && (
                <DynamicOptions options={message.options} onSelect={onOptionSelect} />
            )}
        </div>
    );
});

MessageBubble.displayName = 'MessageBubble';

/**
 * Typing Indicator - Premium animation
 */
const TypingIndicator = () => (
    <div className="flex justify-start mb-3">
        <div
            className="px-5 py-4 flex gap-1.5"
            style={{
                background: 'var(--swc-bg-main, linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%))',
                borderRadius: '20px 20px 20px 6px',
                boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)',
            }}
        >
            <span className="w-2.5 h-2.5 bg-slate-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
            <span className="w-2.5 h-2.5 bg-slate-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
            <span className="w-2.5 h-2.5 bg-slate-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
        </div>
    </div>
);

/**
 * Active Tool Indicator - Shows when AI is thinking/using an external tool
 */
const ActiveToolIndicator = ({ toolName, hasUsedTool }) => {
    // Map internal tool names to friendly text
    const getFriendlyName = (name) => {
        if (!name) return hasUsedTool ? "Generating response..." : "AI is thinking...";
        if (name.includes('search') || name.includes('google')) return "Searching the web...";
        if (name.includes('knowledge') || name.includes('rag')) return "Reading knowledge base...";
        if (name.includes('lead') || name.includes('form')) return "Processing information...";
        if (name.includes('appointment') || name.includes('calendar')) return "Checking schedule...";
        if (name.includes('skill') || name.includes('tool')) return "Processing request...";
        // For general tools, format like "Using tool..."
        const formattedName = name.replace(/_/g, ' ');
        return `Using ${formattedName}...`;
    };

    return (
        <div className="flex justify-start mb-3 animate-fade-in">
            <div
                className="px-4 py-3 flex items-center gap-2.5"
                style={{
                    background: 'var(--swc-bg-main, linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%))',
                    borderRadius: '20px 20px 20px 6px',
                    boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)',
                    border: '1px solid #e2e8f0'
                }}
            >
                <span className="text-sm font-medium text-slate-600">
                    {getFriendlyName(toolName)}
                </span>
                <span className="flex gap-0.5 ml-1">
                    <span className="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                    <span className="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                    <span className="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                </span>
            </div>
        </div>
    );
};

/**
 * Quick Questions - Pill buttons
 */
const QuickReplies = React.memo(({ replies, onSelect }) => {
    if (!replies?.length) return null;

    return (
        <div className="px-4 pb-3 flex flex-wrap gap-2">
            {replies.map((reply, idx) => (
                <button
                    key={idx}
                    onClick={() => onSelect(reply.text || reply)}
                    className="px-4 py-2 font-medium bg-white border-2 border-slate-200 rounded-full text-slate-700 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all duration-200 shadow-sm hover:shadow"
                >
                    {reply.text || reply}
                </button>
            ))}
        </div>
    );
});

QuickReplies.displayName = 'QuickReplies';

/**
 * Dynamic Options Component — AI-generated clickable option buttons
 * Rendered below bot messages when the AI includes [OPTIONS] blocks
 */
const DynamicOptions = React.memo(({ options, onSelect }) => {
    if (!options?.length) return null;

    return (
        <div className="flex flex-wrap gap-2 mt-2" style={{ maxWidth: '85%' }}>
            {options.map((option, idx) => (
                <button
                    key={idx}
                    onClick={() => onSelect(option)}
                    style={{
                        padding: '8px 16px',
                        fontSize: '13px',
                        fontWeight: '500',
                        background: 'rgba(255, 255, 255, 0.95)',
                        border: '1.5px solid var(--swc-border, #e2e8f0)',
                        borderRadius: '20px',
                        color: 'var(--swc-text-primary, #334155)',
                        cursor: 'pointer',
                        transition: 'all 0.2s ease',
                        backdropFilter: 'blur(8px)',
                        boxShadow: '0 1px 3px rgba(0, 0, 0, 0.06)',
                        lineHeight: '1.3',
                        whiteSpace: 'nowrap',
                    }}
                    onMouseEnter={(e) => {
                        e.target.style.borderColor = 'var(--swc-primary, #6366f1)';
                        e.target.style.color = 'var(--swc-primary, #6366f1)';
                        e.target.style.transform = 'translateY(-1px)';
                        e.target.style.boxShadow = '0 4px 12px rgba(99, 102, 241, 0.15)';
                    }}
                    onMouseLeave={(e) => {
                        e.target.style.borderColor = 'var(--swc-border, #e2e8f0)';
                        e.target.style.color = 'var(--swc-text-primary, #334155)';
                        e.target.style.transform = 'translateY(0)';
                        e.target.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.06)';
                    }}
                >
                    {option}
                </button>
            ))}
        </div>
    );
});

DynamicOptions.displayName = 'DynamicOptions';

/**
 * Header action button
 */
const HeaderButton = ({ onClick, title, children }) => (
    <button
        onClick={onClick}
        className="p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/15 transition-all duration-200"
        title={title}
    >
        {children}
    </button>
);

/**
 * Main ChatWidget Component - Metronic v9 Premium Design
 */
const ChatWidget = forwardRef(function ChatWidget({ config, onOpenChange }, ref) {
    // Extract available widgets from config
    const availableWidgets = config?.availableWidgets || [];
    const hasMultipleWidgets = config?.hasMultipleWidgets || false;

    // State for widget selection
    const [selectedWidget, setSelectedWidget] = useState(null);
    const [showWidgetSelector, setShowWidgetSelector] = useState(false);
    const [showWidgetDropdown, setShowWidgetDropdown] = useState(false);
    const dropdownRef = useRef(null);

    // Get the active widget config (selected or default)
    const activeConfig = selectedWidget || config || {};
    const {
        appearance = {},
        behavior = {},
        triggers = {},
        engagement = {},
    } = activeConfig;

    // Main hook for state/logic
    const originalSettings = config?.settings || {};
    // Create an override wrapper if we've selected a specific widget from the widget list
    const activeSettings = selectedWidget?.settings ? { ...originalSettings, ...selectedWidget.settings } : originalSettings;

    const chat = useChatWidget({
        ...config,
        settings: activeSettings,
        widgetId: selectedWidget?.id || config?.frontend_id || 'default'
    });

    // Compute combined quick replies (agent starter prompts + global quick replies)
    const allQuickReplies = useMemo(() => {
        const agentPrompts = chat.currentAgent?.starter_prompts || [];
        const globalPrompts = activeSettings.engagement?.quick_replies_enabled && activeSettings.engagement?.quick_replies
            ? activeSettings.engagement.quick_replies
            : [];
        return [...agentPrompts, ...globalPrompts];
    }, [chat.currentAgent, activeSettings.engagement]);

    const [inputValue, setInputValue] = useState('');
    const [showProactive, setShowProactive] = useState(false);
    const [isListening, setIsListening] = useState(false);
    const [showHistory, setShowHistory] = useState(false);
    const [showAddMenu, setShowAddMenu] = useState(false);
    const [voiceSupported, setVoiceSupported] = useState(false);
    const [greetingVisible, setGreetingVisible] = useState(false);
    const [greetingDismissed, setGreetingDismissed] = useState(false);
    const inputRef = useRef(null);
    const recognitionRef = useRef(null);
    const addMenuRef = useRef(null);

    // Greeting Bubble Timer
    useEffect(() => {
        if (!appearance.greeting_bubble_enabled || greetingDismissed || chat.isOpen) {
            setGreetingVisible(false);
            return;
        }
        const delay = (appearance.greeting_bubble_delay || 5) * 1000;
        const timer = setTimeout(() => setGreetingVisible(true), delay);
        return () => clearTimeout(timer);
    }, [appearance.greeting_bubble_enabled, appearance.greeting_bubble_delay, greetingDismissed, chat.isOpen]);

    // Check for voice input support on mount
    useEffect(() => {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        setVoiceSupported(!!SpeechRecognition);
    }, []);

    // Handle toggle click - show selector if multiple widgets available
    const handleToggleClick = useCallback(() => {
        if (chat.isOpen) {
            chat.close();
            return;
        }

        // If multiple widgets and none selected yet, show selector
        if (hasMultipleWidgets && !selectedWidget) {
            setShowWidgetSelector(true);
            return;
        }

        // Otherwise just toggle
        chat.toggle();
    }, [chat, hasMultipleWidgets, selectedWidget]);

    // Handle widget selection - switch widget and load its history
    const handleWidgetSelect = useCallback((widget) => {
        setSelectedWidget(widget);
        setShowWidgetSelector(false);
        // Switch to new widget - this loads widget-specific chat history
        chat.switchWidget(widget.id);
        // Open chat with new widget
        setTimeout(() => chat.open(), 100);
    }, [chat]);

    // Open widget selector from header (to switch widgets)
    const handleShowWidgetSelector = useCallback(() => {
        setShowWidgetSelector(true);
    }, []);

    // Toggle widget dropdown
    const toggleWidgetDropdown = useCallback(() => {
        setShowWidgetDropdown(prev => !prev);
    }, []);

    // Handle widget selection from dropdown
    const handleDropdownSelect = useCallback((widget) => {
        setSelectedWidget(widget);
        setShowWidgetDropdown(false);
        // Switch to new widget - this loads widget-specific chat history
        chat.switchWidget(widget.id);
    }, [chat]);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (e) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setShowWidgetDropdown(false);
            }
            if (addMenuRef.current && !addMenuRef.current.contains(e.target)) {
                setShowAddMenu(false);
            }
        };
        if (showWidgetDropdown || showAddMenu) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [showWidgetDropdown, showAddMenu]);

    // Expose methods via ref
    useImperativeHandle(ref, () => ({
        open: chat.open,
        close: chat.close,
        toggle: chat.toggle,
        sendMessage: chat.sendMessage,
        clearHistory: () => {
            chat.clearHistory();
            setShowHistory(false);
        },
        get isOpen() { return chat.isOpen; },
        get sessionId() { return chat.sessionId; },
        get visitorId() { return chat.visitorId; },
        get currentAgent() { return chat.currentAgent; },
        get messages() { return chat.messages; },
    }), [chat]);

    // Notify parent of open state changes
    useEffect(() => {
        onOpenChange?.(chat.isOpen);
    }, [chat.isOpen, onOpenChange]);

    // Keyboard escape to close
    useEffect(() => {
        const handleEscape = (e) => {
            if (e.key === 'Escape' && chat.isOpen) {
                chat.close();
            }
        };
        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [chat.isOpen, chat.close]);

    // Focus input when opening
    useEffect(() => {
        if (chat.isOpen && inputRef.current) {
            setTimeout(() => inputRef.current?.focus(), 350);
        }
    }, [chat.isOpen]);

    // Cleanup speech recognition on unmount
    useEffect(() => {
        return () => {
            if (recognitionRef.current) {
                recognitionRef.current.abort();
            }
        };
    }, []);

    const handleSubmit = useCallback((e) => {
        e?.preventDefault();
        const message = inputValue.trim();
        if (!message || chat.isStreaming) return;

        // Stop voice input if active
        if (isListening && recognitionRef.current) {
            try {
                recognitionRef.current.stop();
            } catch (err) {
                // ignore
            }
            setIsListening(false);
        }

        // Clear input first to prevent any race conditions
        setInputValue('');
        // Reset textarea height to single line
        if (inputRef.current) inputRef.current.style.height = '24px';

        // Then send the message
        chat.sendMessage(message);
    }, [inputValue, chat, isListening]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!chat.isStreaming) {
                handleSubmit();
            }
        }
    }, [handleSubmit, chat.isStreaming]);

    // Stop generation handler
    const handleStopGeneration = useCallback(() => {
        chat.stopGeneration();
    }, [chat]);

    const handleQuickReply = useCallback((text) => {
        // Send the quick question as a message.
        // If the agent/session isn't resolved yet, give it a moment
        // then send anyway - sendMessage handles absent session gracefully.
        if (!chat.currentAgent) {
            setTimeout(() => chat.sendMessage(text), 500);
            return;
        }
        chat.sendMessage(text);
    }, [chat]);

    // Voice input handler
    const toggleVoiceInput = useCallback(() => {
        console.log('Voice input toggled, isListening:', isListening);

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            console.warn('Speech recognition not supported in this browser');
            alert('Voice input is not supported in this browser. Please use Chrome, Edge, or Safari.');
            return;
        }

        if (isListening) {
            // Stop listening
            console.log('Stopping speech recognition');
            if (recognitionRef.current) {
                try {
                    recognitionRef.current.stop();
                } catch (e) {
                    console.warn('Error stopping recognition:', e);
                }
            }
            setIsListening(false);
            return;
        }

        // Start listening
        console.log('Starting speech recognition');
        try {
            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = () => {
                console.log('Speech recognition started');
                setIsListening(true);
            };

            recognition.onresult = (event) => {
                let transcript = '';
                for (let i = 0; i < event.results.length; i++) {
                    transcript += event.results[i][0].transcript;
                }
                console.log('Transcript:', transcript);
                setInputValue(transcript);
            };

            recognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                setIsListening(false);

                if (event.error === 'not-allowed') {
                    alert('Microphone access was denied. Please allow microphone access and try again.');
                } else if (event.error === 'no-speech') {
                    // User didn't speak, that's okay
                    console.log('No speech detected');
                } else if (event.error === 'network') {
                    alert('Network error. Voice recognition requires an internet connection.');
                }
            };

            recognition.onend = () => {
                console.log('Speech recognition ended');
                setIsListening(false);
            };

            recognitionRef.current = recognition;
            recognition.start();
            console.log('recognition.start() called');
        } catch (err) {
            console.error('Failed to start speech recognition:', err);
            setIsListening(false);
            alert('Could not start voice input. Error: ' + err.message);
        }
    }, [isListening]);

    // Theme colors with fallbacks
    const primaryColor = appearance.color_primary || '#6366f1';
    const primaryHover = appearance.color_primary_hover || '#4f46e5';
    const bgMain = appearance.color_bg_main || '#ffffff';
    const bgLight = appearance.color_bg_light || '#f8fafc';
    const textPrimary = appearance.color_text_primary || '#1e293b';
    const textSecondary = appearance.color_text_secondary || '#64748b';
    const borderColor = appearance.color_border || '#e2e8f0';
    const presentationMode = appearance.presentation_mode || 'floating';

    // Typography & sizing from appearance settings
    const fontSizeBase = Number(appearance.font_size_base) || 14;
    const lineHeight = Number(appearance.line_height) || 1.5;
    const fontFamily = appearance.font_family && appearance.font_family !== 'system'
        ? appearance.font_family
        : "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    const windowWidth = Number(appearance.window_width) || 380;
    const windowHeight = Number(appearance.window_height) || 550;
    const borderRadius = Number(appearance.border_radius) || 16;

    // Effects & Animation settings
    const shadowIntensity = appearance.shadow_intensity || 'medium';
    const animationSpeedStr = appearance.animation_speed || 'normal';
    const enableHover = appearance.enable_hover_effects !== false;
    const enableGlass = !!appearance.enable_glassmorphism;
    const bubbleRadius = Number(appearance.bubble_radius) || 18;
    const toggleSize = Number(appearance.toggle_size) || 60;

    // Animation Duration parsing
    let animDuration = '0.3s';
    if (animationSpeedStr === 'fast') animDuration = '0.15s';
    else if (animationSpeedStr === 'slow') animDuration = '0.5s';
    else if (animationSpeedStr === 'none') animDuration = '0s';

    // Shadow parsing
    const windowShadows = {
        none: '0 0 0 1px rgba(0, 0, 0, 0.05)',
        light: '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.05)',
        medium: '0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05)',
        heavy: '0 30px 60px -15px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(0, 0, 0, 0.05)',
    };
    const widgetShadow = windowShadows[shadowIntensity] || windowShadows.medium;

    // Glassmorphism background parser
    const getGlassBg = (colorHex, alpha) => {
        if (!enableGlass) return colorHex;
        if (colorHex?.length === 7) {
            const r = parseInt(colorHex.slice(1, 3), 16);
            const g = parseInt(colorHex.slice(3, 5), 16);
            const b = parseInt(colorHex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        return colorHex;
    };
    const windowBgColor = getGlassBg(bgMain, 0.98);

    // Dynamic style injection for Effects
    const dynamicEffectStyles = `
        .swc-hover-target {
            transition: all var(--swc-anim-duration, 0.3s) ease;
        }
        ${enableHover ? `
        .swc-hover-target:hover {
            transform: scale(1.03);
            filter: brightness(1.05);
        }
        .swc-btn-hover:hover {
            transform: scale(1.05);
        }
        .swc-btn-hover:active {
            transform: scale(0.95);
        }
        ` : ''}
        ${enableGlass ? `
        .swc-glass {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        ` : ''}
    `;

    // ==========================================
    // CENTERED MODE — ChatGPT-style full-page layout with open/close toggle
    // ==========================================
    if (presentationMode === 'centered') {
        return (
            <div
                id="smart-ai-chatbot-centered-wrapper"
                style={{
                    fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                    '--swc-primary': primaryColor,
                    '--swc-primary-hover': primaryHover,
                    '--swc-bg-main': bgMain,
                    '--swc-bg-light': bgLight,
                    '--swc-bg-message-bot': appearance.color_bg_message_bot || '#ffffff',
                    '--swc-text-primary': textPrimary,
                    '--swc-text-message-bot': appearance.color_text_message_bot || '#1e293b',
                    '--swc-border': borderColor,
                    '--swc-anim-duration': animDuration,
                    '--swc-bubble-radius': `${bubbleRadius}px`,
                }}
            >
                <style>{dynamicEffectStyles}</style>
                {/* Full-page chat overlay — only shown when open */}
                {chat.isOpen && (
                    <div
                        id="smart-ai-chatbot-centered"
                        style={{
                            position: 'fixed',
                            inset: 0,
                            zIndex: 2147483647,
                            display: 'flex',
                            flexDirection: 'column',
                            fontFamily: 'inherit',
                            background: 'linear-gradient(180deg, #f8fafc 0%, #eef2ff 50%, #f8fafc 100%)',
                            animation: 'swc-fade-in 0.3s ease-out',
                        }}
                    >
                        {/* History Panel Overlay */}
                        {showHistory && (
                            <div style={{
                                position: 'absolute',
                                inset: 0,
                                zIndex: 10,
                                background: 'white',
                                display: 'flex',
                                flexDirection: 'column',
                                animation: 'swc-slide-right 0.2s ease-out',
                            }}>
                                <div style={{
                                    padding: '16px 20px',
                                    borderBottom: '1px solid #e2e8f0',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between'
                                }}>
                                    <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 600 }}>Past Conversations</h3>
                                    <HeaderButton onClick={() => setShowHistory(false)} title="Close History">
                                        <Icons.Close />
                                    </HeaderButton>
                                </div>
                                <div style={{ flex: 1, overflowY: 'auto', padding: '16px' }}>
                                    {chat.pastSessions?.length === 0 ? (
                                        <p style={{ color: '#64748b', textAlign: 'center', marginTop: '20px' }}>No past conversations found.</p>
                                    ) : (
                                        chat.pastSessions?.map(session => (
                                            <button
                                                key={session.id}
                                                onClick={() => {
                                                    chat.loadSession(session.id);
                                                    setShowHistory(false);
                                                }}
                                                style={{
                                                    width: '100%',
                                                    padding: '12px 16px',
                                                    background: '#f8fafc',
                                                    border: '1px solid #e2e8f0',
                                                    borderRadius: '8px',
                                                    marginBottom: '10px',
                                                    textAlign: 'left',
                                                    cursor: 'pointer',
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    gap: '4px'
                                                }}
                                            >
                                                <span style={{ fontWeight: 500, fontSize: '14px', color: '#0f172a' }}>{session.title || 'Chat'}</span>
                                                <span style={{ fontSize: '12px', color: '#64748b' }}>{new Date(session.date).toLocaleDateString()}</span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Header Bar with controls */}
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: '12px',
                                padding: '12px 20px',
                                background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                                boxShadow: '0 2px 12px rgba(0, 0, 0, 0.1)',
                            }}
                        >
                            <div
                                style={{
                                    width: '36px',
                                    height: '36px',
                                    borderRadius: '10px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '20px',
                                    background: 'rgba(255, 255, 255, 0.2)',
                                    backdropFilter: 'blur(10px)',
                                }}
                            >
                                {chat.currentAgent?.avatar || appearance.avatar || '✨'}
                            </div>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <h3 style={{
                                    fontSize: '15px',
                                    fontWeight: '600',
                                    color: 'white',
                                    margin: 0,
                                    wordBreak: 'break-word',
                                }}>
                                    {config?.displayName || chat.currentAgent?.name || 'AI Assistant'}
                                </h3>
                                <p style={{
                                    fontSize: '11px',
                                    color: 'rgba(255, 255, 255, 0.7)',
                                    margin: 0,
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '5px',
                                }}>
                                    <span style={{
                                        width: '6px',
                                        height: '6px',
                                        borderRadius: '50%',
                                        backgroundColor: chat.isStreaming ? '#fbbf24' : '#34d399',
                                        boxShadow: chat.isStreaming ? '0 0 6px #fbbf24' : '0 0 6px #34d399',
                                    }} />
                                    {chat.isStreaming ? 'Typing...' : 'Online'}
                                </p>
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '2px' }}>
                                <HeaderButton onClick={() => { chat.clearHistory(); setShowHistory(false); }} title="New conversation">
                                    <Icons.Reset />
                                </HeaderButton>
                                <HeaderButton onClick={chat.toggleSound} title={chat.soundEnabled ? 'Mute' : 'Unmute'}>
                                    {chat.soundEnabled ? <Icons.Sound /> : <Icons.SoundOff />}
                                </HeaderButton>
                                <HeaderButton onClick={chat.close} title="Close">
                                    <Icons.Close />
                                </HeaderButton>
                            </div>
                        </div>

                        {/* Messages Area — grows to fill space */}
                        <div
                            className="flex-1 overflow-y-auto"
                            style={{ padding: '40px 16px 0' }}
                        >
                            <div style={{ maxWidth: '720px', margin: '0 auto', width: '100%' }}>
                                {/* Welcome state when no messages */}
                                {chat.messages.length === 0 && !chat.isLoading && (
                                    <div style={{ textAlign: 'center', paddingTop: '8vh' }}>
                                        <div
                                            style={{
                                                width: '80px',
                                                height: '80px',
                                                margin: '0 auto 24px',
                                                borderRadius: '24px',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                fontSize: '40px',
                                                background: 'linear-gradient(135deg, ' + primaryColor + '20 0%, ' + primaryColor + '10 100%)',
                                                boxShadow: '0 8px 32px ' + primaryColor + '15',
                                            }}
                                        >
                                            {appearance.avatar || '✨'}
                                        </div>
                                        <h2 style={{
                                            fontSize: '28px',
                                            fontWeight: '700',
                                            color: '#0f172a',
                                            marginBottom: '8px',
                                            letterSpacing: '-0.02em',
                                        }}>
                                            {config?.displayName || chat.currentAgent?.name || 'How can I help you?'}
                                        </h2>
                                        <p style={{
                                            fontSize: '16px',
                                            color: '#64748b',
                                            maxWidth: '480px',
                                            margin: '0 auto',
                                            lineHeight: '1.6',
                                        }}>
                                            {behavior.greeting_message || 'Ask me anything — I\'m here to help.'}
                                        </p>
                                        {/* Quick question suggestions */}
                                        {allQuickReplies.length > 0 && (
                                            <div style={{
                                                display: 'flex',
                                                flexWrap: 'wrap',
                                                gap: '8px',
                                                justifyContent: 'center',
                                                marginTop: '32px',
                                            }}>
                                                {allQuickReplies.map((reply, idx) => (
                                                    <button
                                                        key={idx}
                                                        onClick={() => handleQuickReply(reply.text || reply)}
                                                        style={{
                                                            padding: '10px 20px',
                                                            fontSize: '14px',
                                                            fontWeight: '500',
                                                            background: 'rgba(255, 255, 255, 0.9)',
                                                            border: '1px solid #e2e8f0',
                                                            borderRadius: '12px',
                                                            color: '#334155',
                                                            cursor: 'pointer',
                                                            transition: `all var(--swc-anim-duration)`,
                                                            backdropFilter: 'blur(10px)',
                                                            boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                                                        }}
                                                        {...(enableHoverEffects && {
                                                            onMouseEnter: (e) => {
                                                                e.target.style.borderColor = primaryColor;
                                                                e.target.style.color = primaryColor;
                                                                e.target.style.transform = 'translateY(-1px)';
                                                                e.target.style.boxShadow = '0 4px 12px ' + primaryColor + '20';
                                                            },
                                                            onMouseLeave: (e) => {
                                                                e.target.style.borderColor = '#e2e8f0';
                                                                e.target.style.color = '#334155';
                                                                e.target.style.transform = 'translateY(0)';
                                                                e.target.style.boxShadow = '0 1px 3px rgba(0,0,0,0.06)';
                                                            },
                                                        })}
                                                    >
                                                        {reply.text || reply}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Messages */}
                                {chat.messages.map((msg, idx) => {
                                    const isBot = msg.role === 'assistant';
                                    // Find if this is the last bot message in the array
                                    const isLastBot = isBot && !chat.messages.slice(idx + 1).some(m => m.role === 'assistant');
                                    return (
                                        <MessageBubble
                                            key={msg.id}
                                            message={msg}
                                            isBot={isBot}
                                            isStreaming={msg.isStreaming}
                                            isLastBot={isLastBot}
                                            activeTool={chat.activeTool}
                                            onOptionSelect={handleQuickReply}
                                        />
                                    );
                                })}

                                {/* Loading indicator */}
                                {chat.activeTool ? (
                                    <ActiveToolIndicator toolName={chat.activeTool} hasUsedTool={chat.hasUsedTool} />
                                ) : (
                                    chat.isLoading && (!chat.isStreaming || chat.messages.length === 0 || chat.messages[chat.messages.length - 1].role !== 'assistant') && <ActiveToolIndicator toolName={null} hasUsedTool={chat.hasUsedTool} />
                                )}

                                {/* Error */}
                                {chat.error && (
                                    <div style={{
                                        margin: '8px 0 16px',
                                        padding: '12px 16px',
                                        borderRadius: '12px',
                                        background: 'linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)',
                                        border: '1px solid #fecaca',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                    }}>
                                        <span style={{ fontSize: '14px', color: '#b91c1c' }}>{chat.error}</span>
                                        <button
                                            onClick={() => chat.setError(null)}
                                            style={{ fontSize: '13px', fontWeight: '600', color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', textDecoration: 'underline' }}
                                        >
                                            Dismiss
                                        </button>
                                    </div>
                                )}

                                <div ref={chat.messagesEndRef} />
                            </div>
                        </div>

                        {/* Bottom Input Bar — fixed at bottom center */}
                        <div style={{
                            padding: '16px 16px 24px',
                            background: 'linear-gradient(180deg, transparent 0%, #f8fafc 20%)',
                        }}>
                            <div style={{ maxWidth: '720px', margin: '0 auto', width: '100%' }}>
                                <form
                                    onSubmit={handleSubmit}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '12px',
                                        padding: '12px 16px 12px 24px',
                                        borderRadius: '20px',
                                        border: '1px solid #e2e8f0',
                                        background: 'rgba(255, 255, 255, 0.95)',
                                        backdropFilter: 'blur(20px)',
                                        boxShadow: '0 4px 24px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.03)',
                                        transition: `all var(--swc-anim-duration)`,
                                    }}
                                >
                                    <textarea
                                        ref={inputRef}
                                        value={inputValue}
                                        onChange={(e) => {
                                            setInputValue(e.target.value);
                                            // Auto-expand: reset height then set to scrollHeight, capped at 2 lines
                                            e.target.style.height = '24px';
                                            e.target.style.height = Math.min(e.target.scrollHeight, 48) + 'px';
                                        }}
                                        onKeyDown={handleKeyDown}
                                        placeholder={isListening ? 'Listening...' : (behavior.placeholder_text || 'Message...')}
                                        style={{
                                            flex: 1,
                                            resize: 'none',
                                            border: 'none',
                                            background: 'transparent',
                                            fontSize: 'inherit',
                                            color: '#1e293b',
                                            outline: 'none',
                                            minHeight: '24px',
                                            maxHeight: '48px',
                                            fontFamily: 'inherit',
                                            lineHeight: '24px',
                                            overflowY: 'auto',
                                        }}
                                        rows={1}
                                        disabled={isListening}
                                    />
                                    {/* Add Menu (New/History) */}
                                    <div style={{ position: 'relative', display: 'flex' }} ref={addMenuRef}>
                                        <button
                                            type="button"
                                            onClick={() => setShowAddMenu(!showAddMenu)}
                                            style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '40px', height: '40px', borderRadius: '12px', border: 'none', background: showAddMenu ? '#f1f5f9' : 'transparent', color: showAddMenu ? '#1e293b' : '#94a3b8', cursor: 'pointer', transition: `all var(--swc-anim-duration)`, flexShrink: 0 }}
                                            title="Add Options"
                                        >
                                            <Icons.Reset />
                                        </button>
                                        {showAddMenu && (
                                            <div style={{
                                                position: 'absolute',
                                                bottom: 'calc(100% + 8px)',
                                                left: '50%',
                                                transform: 'translateX(-50%)',
                                                background: 'white',
                                                borderRadius: '12px',
                                                boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0,0,0,0.05)',
                                                padding: '6px',
                                                zIndex: 1000,
                                                minWidth: '150px',
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: '2px',
                                                border: '1px solid #e2e8f0'
                                            }}>
                                                <button
                                                    type="button"
                                                    onClick={() => { chat.clearHistory(); setShowHistory(false); setShowAddMenu(false); }}
                                                    style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '14px', textAlign: 'left', fontWeight: '500', transition: `all var(--swc-anim-duration)` }}
                                                    {...(enableHoverEffects && {
                                                        onMouseEnter: e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; },
                                                        onMouseLeave: e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; },
                                                    })}
                                                >
                                                    <Icons.Reset /> New Chat
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => { setShowHistory(true); setShowAddMenu(false); }}
                                                    style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '14px', textAlign: 'left', fontWeight: '500', transition: `all var(--swc-anim-duration)` }}
                                                    {...(enableHoverEffects && {
                                                        onMouseEnter: e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; },
                                                        onMouseLeave: e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; },
                                                    })}
                                                >
                                                    <Icons.History /> History
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    {/* Voice input */}
                                    {voiceSupported && (
                                        <button
                                            type="button"
                                            onClick={toggleVoiceInput}
                                            disabled={chat.isStreaming}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                width: '40px',
                                                height: '40px',
                                                borderRadius: '12px',
                                                border: 'none',
                                                background: isListening ? '#fee2e2' : 'transparent',
                                                color: isListening ? '#ef4444' : '#94a3b8',
                                                cursor: 'pointer',
                                                transition: `all var(--swc-anim-duration)`,
                                            }}
                                            title={isListening ? 'Stop listening' : 'Voice input'}
                                        >
                                            {isListening ? <Icons.MicrophoneActive /> : <Icons.Microphone />}
                                        </button>
                                    )}
                                    {/* Stop / Send button */}
                                    {chat.isStreaming ? (
                                        <button
                                            type="button"
                                            onClick={handleStopGeneration}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                width: '44px',
                                                height: '44px',
                                                borderRadius: '14px',
                                                border: 'none',
                                                color: 'white',
                                                cursor: 'pointer',
                                                background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                                                boxShadow: '0 4px 12px rgba(239, 68, 68, 0.4)',
                                                transition: `all var(--swc-anim-duration)`,
                                            }}
                                            title="Stop generating"
                                        >
                                            <Icons.Stop />
                                        </button>
                                    ) : (
                                        <button
                                            type="submit"
                                            disabled={!inputValue.trim()}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                width: '44px',
                                                height: '44px',
                                                borderRadius: '14px',
                                                border: 'none',
                                                color: 'white',
                                                cursor: inputValue.trim() ? 'pointer' : 'not-allowed',
                                                opacity: inputValue.trim() ? 1 : 0.4,
                                                background: inputValue.trim()
                                                    ? 'linear-gradient(135deg, ' + primaryColor + ' 0%, ' + primaryHover + ' 100%)'
                                                    : '#cbd5e1',
                                                boxShadow: inputValue.trim() ? '0 4px 12px ' + primaryColor + '40' : 'none',
                                                transition: `all var(--swc-anim-duration)`,
                                            }}
                                        >
                                            <Icons.Send />
                                        </button>
                                    )}
                                </form>
                                {/* Powered by removed */}
                            </div>
                        </div>
                    </div>
                )}

                {/* Floating toggle button for centered mode */}
                <button
                    onClick={handleToggleClick}
                    style={{
                        position: 'fixed',
                        bottom: '24px',
                        right: '24px',
                        zIndex: 2147483647,
                        width: '60px',
                        height: '60px',
                        borderRadius: '50%',
                        border: 'none',
                        background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                        boxShadow: widgetShadow,
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transition: `all var(--swc-anim-duration)`,
                    }}
                    aria-label={chat.isOpen ? 'Close chat' : 'Open chat'}
                >
                    <span
                        style={{
                            color: 'white',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: `transform var(--swc-anim-duration)`,
                            transform: chat.isOpen ? 'rotate(90deg)' : 'rotate(0deg)',
                        }}
                    >
                        {chat.isOpen ? <Icons.Close /> : (appearance.avatar ? <span style={{ fontSize: '24px' }}>{appearance.avatar}</span> : <Icons.Chat />)}
                    </span>
                </button>

                {/* Keyframe animations + Markdown styles */}
                <style>{`
                    @keyframes swc-fade-in {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes swc-slide-right {
                        from { transform: translateX(100%); }
                        to { transform: translateX(0); }
                    }
                    .animate-fade-in {
                        animation: swc-fade-in var(--swc-anim-duration) ease-out;
                    }
                    ${enableGlassmorphism ? `
                        .swc-glass {
                            background-color: rgba(255, 255, 255, 0.9);
                            backdrop-filter: blur(20px);
                            -webkit-backdrop-filter: blur(20px);
                        }
                    ` : ''}
                    /* Markdown content styles for chat bubbles */
                    .swc-markdown-content { word-wrap: break-word; overflow-wrap: break-word; }
                    .swc-markdown-content > :first-child { margin-top: 0 !important; }
                    .swc-markdown-content > :last-child { margin-bottom: 0 !important; }
                    .swc-markdown-content p { margin: 0.35em 0; line-height: 1.5; }
                    .swc-markdown-content strong, .swc-markdown-content b { font-weight: 600; }
                    .swc-markdown-content em, .swc-markdown-content i { font-style: italic; }
                    .swc-markdown-content a { color: #6366f1; text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 2px; }
                    .swc-user-bubble .swc-markdown-content a { color: rgba(255, 255, 255, 0.95) !important; text-decoration: underline; text-decoration-color: rgba(255, 255, 255, 0.6); }
                    .swc-user-bubble .swc-markdown-content a:hover { color: #ffffff !important; text-decoration-color: rgba(255, 255, 255, 0.9); }
                    .swc-markdown-content h1, .swc-markdown-content h2, .swc-markdown-content h3,
                    .swc-markdown-content h4, .swc-markdown-content h5, .swc-markdown-content h6 {
                        font-weight: 600; line-height: 1.3; margin: 0.5em 0 0.25em;
                    }
                    .swc-markdown-content h1 { font-size: 1.25em; }
                    .swc-markdown-content h2 { font-size: 1.15em; }
                    .swc-markdown-content h3 { font-size: 1.08em; }
                    .swc-markdown-content ul, .swc-markdown-content ol { margin: 0.35em 0; padding-left: 1.4em; }
                    .swc-markdown-content li { margin: 0.15em 0; line-height: 1.45; }
                    .swc-markdown-content ul { list-style-type: disc; }
                    .swc-markdown-content ol { list-style-type: decimal; }
                    .swc-markdown-content code {
                        background: rgba(0, 0, 0, 0.06); padding: 0.15em 0.4em; border-radius: 4px;
                        font-size: 0.88em; font-family: 'SF Mono', Menlo, Consolas, monospace;
                    }
                    .swc-markdown-content pre {
                        background: #1e293b; color: #e2e8f0; padding: 0.75em 1em; border-radius: 8px;
                        overflow-x: auto; margin: 0.5em 0; font-size: 0.85em; line-height: 1.45;
                    }
                    .swc-markdown-content pre code { background: none; padding: 0; font-size: inherit; color: inherit; }
                    .swc-markdown-content blockquote {
                        border-left: 3px solid #6366f1; padding: 0.3em 0 0.3em 0.8em;
                        margin: 0.4em 0; color: #475569; font-style: italic;
                    }
                    .swc-markdown-content hr { border: none; border-top: 1px solid rgba(0, 0, 0, 0.1); margin: 0.5em 0; }
                    .swc-markdown-content table { width: 100%; border-collapse: collapse; margin: 0.4em 0; font-size: 0.9em; }
                    .swc-markdown-content th, .swc-markdown-content td { border: 1px solid rgba(0, 0, 0, 0.1); padding: 0.35em 0.6em; text-align: left; }
                    .swc-markdown-content th { background: rgba(0, 0, 0, 0.04); font-weight: 600; }
                `}</style>
            </div>
        );
    }

    // ==========================================
    // EMBEDDED MODE — Inside a page element
    // ==========================================
    if (presentationMode === 'embedded') {
        const targetSelector = appearance.embedded_target || '#smart-ai-chatbot-embed';
        const targetEl = document.querySelector(targetSelector);

        // Build the chat UI element
        const embeddedChatUI = (
            <div
                id="smart-ai-chatbot-embedded"
                style={{
                    width: '100%',
                    height: '100%',
                    minHeight: '400px',
                    display: 'flex',
                    flexDirection: 'column',
                    fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                    '--swc-primary': primaryColor,
                    '--swc-primary-hover': primaryHover,
                    '--swc-bg-main': bgMain,
                    '--swc-bg-light': bgLight,
                    '--swc-text-primary': textPrimary,
                    '--swc-text-secondary': textSecondary,
                    '--swc-border': borderColor,
                    '--swc-anim-duration': animDuration,
                    background: windowBgColor,
                    borderRadius: '16px',
                    boxShadow: widgetShadow,
                    overflow: 'hidden',
                }}
                className={enableGlass ? "swc-glass" : ""}
            >
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px',
                        padding: '14px 20px',
                        background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                    }}
                >
                    <div
                        style={{
                            width: '36px',
                            height: '36px',
                            borderRadius: '10px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: '18px',
                            background: 'rgba(255, 255, 255, 0.2)',
                        }}
                    >
                        {chat.currentAgent?.avatar || appearance.avatar || <Icons.Bot />}
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <h3 style={{ fontSize: '14px', fontWeight: '600', color: 'white', margin: 0 }}>
                            {config?.displayName || chat.currentAgent?.name || 'Chat Assistant'}
                        </h3>
                        <p style={{ fontSize: '11px', color: 'rgba(255, 255, 255, 0.7)', margin: 0, display: 'flex', alignItems: 'center', gap: '5px' }}>
                            <span style={{
                                width: '6px', height: '6px', borderRadius: '50%',
                                backgroundColor: chat.isStreaming ? '#fbbf24' : '#34d399',
                            }} />
                            {chat.isStreaming ? 'Typing...' : 'Online'}
                        </p>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '2px' }}>
                    </div>
                </div>

                {/* History Panel Overlay */}
                {showHistory && (
                    <div style={{
                        position: 'absolute',
                        inset: 0,
                        zIndex: 10,
                        background: 'white',
                        display: 'flex',
                        flexDirection: 'column',
                        borderRadius: borderRadius + 'px',
                        overflow: 'hidden',
                        animation: `swc-fade-in var(--swc-anim-duration) ease-out forwards`,
                    }}>
                        <div style={{
                            padding: '16px 20px',
                            borderBottom: '1px solid #e2e8f0',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            background: '#f8fafc'
                        }}>
                            <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 600 }}>Past Conversations</h3>
                            <button onClick={() => setShowHistory(false)} style={{ border: 'none', background: 'transparent', cursor: 'pointer', color: '#64748b' }}>
                                <Icons.Close />
                            </button>
                        </div>
                        <div style={{ flex: 1, overflowY: 'auto', padding: '16px' }}>
                            {chat.pastSessions?.length === 0 ? (
                                <p style={{ color: '#64748b', textAlign: 'center', marginTop: '20px', fontSize: '13px' }}>No past conversations found.</p>
                            ) : (
                                chat.pastSessions?.map(session => (
                                <button
                                    key={session.id}
                                    onClick={() => {
                                        chat.loadSession(session.id);
                                        setShowHistory(false);
                                    }}
                                    style={{
                                        width: '100%',
                                        padding: '12px',
                                        background: '#f8fafc',
                                        border: '1px solid #e2e8f0',
                                        borderRadius: '8px',
                                        marginBottom: '10px',
                                        textAlign: 'left',
                                        cursor: 'pointer',
                                        transition: `all var(--swc-anim-duration)`
                                    }}
                                    {...(enableHoverEffects && {
                                        onMouseEnter: (e) => { e.currentTarget.style.borderColor = primaryColor; e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)'; },
                                        onMouseLeave: (e) => { e.currentTarget.style.borderColor = '#e2e8f0'; e.currentTarget.style.boxShadow = 'none'; },
                                    })}
                                >
                                    <div style={{ fontWeight: 500, fontSize: '13px', color: '#0f172a', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{session.title || 'Chat'}</div>
                                    <div style={{ fontSize: '11px', color: '#64748b', marginTop: '4px' }}>{new Date(session.date).toLocaleDateString()}</div>
                                </button>
                            )))}
                        </div>
                    </div>
                )}

                {/* Messages */}
                <div
                    className="flex-1 overflow-y-auto"
                    style={{ padding: '16px', background: 'linear-gradient(180deg, #f8fafc 0%, #ffffff 50%)', flex: 1, minHeight: 0 }}
                >
                    {chat.messages.length === 0 && !chat.isLoading && (
                        <div style={{ textAlign: 'center', padding: '32px 16px' }}>
                            <div style={{
                                width: '48px', height: '48px', margin: '0 auto 12px', borderRadius: '14px',
                                display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '24px',
                                background: 'linear-gradient(135deg, ' + primaryColor + '20 0%, ' + primaryColor + '10 100%)',
                            }}>
                                {appearance.avatar || <Icons.HandWave />}
                            </div>
                            <h4 style={{ fontSize: '16px', fontWeight: '600', color: '#1e293b', marginBottom: '4px' }}>Hi there!</h4>
                            <p style={{ fontSize: '13px', color: '#64748b' }}>{behavior.placeholder_text || 'Type your message to get started...'}</p>
                        </div>
                    )}
                    {chat.messages.map((msg, idx) => {
                        const isBot = msg.role === 'assistant';
                        const isLastBot = isBot && !chat.messages.slice(idx + 1).some(m => m.role === 'assistant');
                        return (
                            <MessageBubble key={msg.id} message={msg} isBot={isBot} isStreaming={msg.isStreaming} isLastBot={isLastBot} activeTool={chat.activeTool} onOptionSelect={handleQuickReply} />
                        );
                    })}
                    {chat.activeTool ? (
                        <ActiveToolIndicator toolName={chat.activeTool} hasUsedTool={chat.hasUsedTool} />
                    ) : (
                        chat.isLoading && (!chat.isStreaming || chat.messages.length === 0 || chat.messages[chat.messages.length - 1].role !== 'assistant') && <ActiveToolIndicator toolName={null} hasUsedTool={chat.hasUsedTool} />
                    )}
                    {chat.error && (
                        <div style={{
                            margin: '8px 0', padding: '10px 14px', borderRadius: '10px',
                            background: '#fef2f2', border: '1px solid #fecaca',
                            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                        }}>
                            <span style={{ fontSize: '13px', color: '#b91c1c' }}>{chat.error}</span>
                            <button onClick={() => chat.setError(null)} style={{ fontSize: '12px', fontWeight: '600', color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', textDecoration: 'underline' }}>Dismiss</button>
                        </div>
                    )}
                    <div ref={chat.messagesEndRef} />
                </div>

                {/* Quick Questions - show until user sends their first message */}
                {engagement.quick_replies_enabled && !chat.messages.some(m => m.role === 'user') && (
                    <QuickReplies replies={engagement.quick_replies} onSelect={handleQuickReply} />
                )}

                {/* Input */}
                <div style={{ padding: '12px 16px', borderTop: '1px solid #f1f5f9', background: 'white' }}>
                    <form
                        onSubmit={handleSubmit}
                        style={{
                            display: 'flex', alignItems: 'center', gap: '8px',
                            padding: '8px 12px', borderRadius: '14px',
                            border: '1px solid #e2e8f0', background: '#fafafa',
                        }}
                    >
                        <textarea
                            ref={inputRef}
                            value={inputValue}
                            onChange={(e) => setInputValue(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder={behavior.placeholder_text || 'Type your message...'}
                            style={{ flex: 1, resize: 'none', border: 'none', background: 'transparent', fontSize: '14px', color: '#1e293b', outline: 'none', minHeight: '22px', maxHeight: '80px', fontFamily: 'inherit' }}
                            rows={1}
                            disabled={isListening}
                        />
                        <div style={{ position: 'relative', display: 'flex' }} ref={addMenuRef}>
                            <button
                                type="button"
                                onClick={() => setShowAddMenu(!showAddMenu)}
                                style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '34px', height: '34px', borderRadius: '10px', border: 'none', background: showAddMenu ? '#e2e8f0' : 'transparent', color: showAddMenu ? '#1e293b' : '#94a3b8', cursor: 'pointer', transition: `all var(--swc-anim-duration)`, flexShrink: 0 }}
                                title="Add Options"
                            >
                                <Icons.Reset />
                            </button>
                            {showAddMenu && (
                                <div style={{
                                    position: 'absolute',
                                    bottom: 'calc(100% + 8px)',
                                    left: '50%',
                                    transform: 'translateX(-50%)',
                                    background: 'white',
                                    borderRadius: '12px',
                                    boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0,0,0,0.05)',
                                    padding: '6px',
                                    zIndex: 1000,
                                    minWidth: '140px',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: '2px',
                                    border: '1px solid #e2e8f0'
                                }}>
                                    <button
                                        type="button"
                                        onClick={() => { chat.clearHistory(); setShowHistory(false); setShowAddMenu(false); }}
                                        style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '13px', textAlign: 'left', fontWeight: '500', transition: `all var(--swc-anim-duration)` }}
                                        {...(enableHoverEffects && {
                                            onMouseEnter: e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; },
                                            onMouseLeave: e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; },
                                        })}
                                    >
                                        <Icons.Reset /> New Chat
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setShowHistory(true); setShowAddMenu(false); }}
                                        style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '13px', textAlign: 'left', fontWeight: '500', transition: `all var(--swc-anim-duration)` }}
                                        {...(enableHoverEffects && {
                                            onMouseEnter: e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; },
                                            onMouseLeave: e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; },
                                        })}
                                    >
                                        <Icons.History /> History
                                    </button>
                                </div>
                            )}
                        </div>

                        {voiceSupported && (
                            <button type="button" onClick={toggleVoiceInput} disabled={chat.isStreaming}
                                style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '34px', height: '34px', borderRadius: '10px', border: 'none', background: isListening ? '#fee2e2' : 'transparent', color: isListening ? '#ef4444' : '#94a3b8', cursor: 'pointer', transition: `all var(--swc-anim-duration)` }}
                                title={isListening ? 'Stop listening' : 'Voice input'}
                            >
                                {isListening ? <Icons.MicrophoneActive /> : <Icons.Microphone />}
                            </button>
                        )}
                        {chat.isStreaming ? (
                            <button type="button" onClick={handleStopGeneration}
                                style={{
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    width: '36px', height: '36px', borderRadius: '10px', border: 'none',
                                    color: 'white', cursor: 'pointer',
                                    background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                                    boxShadow: '0 4px 12px rgba(239, 68, 68, 0.4)',
                                    transition: `all var(--swc-anim-duration)`,
                                }}
                                title="Stop generating"
                            >
                                <Icons.Stop />
                            </button>
                        ) : (
                            <button type="submit" disabled={!inputValue.trim()}
                                style={{
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    width: '36px', height: '36px', borderRadius: '10px', border: 'none',
                                    color: 'white', cursor: inputValue.trim() ? 'pointer' : 'not-allowed',
                                    opacity: inputValue.trim() ? 1 : 0.4,
                                    background: inputValue.trim() ? `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)` : '#cbd5e1',
                                    transition: `all var(--swc-anim-duration)`,
                                }}
                            >
                                <Icons.Send />
                            </button>
                        )}
                    </form>
                    {/* Powered by removed */}
                </div>

                <style>{`
                    @keyframes swc-fade-in { from { opacity: 0; } to { opacity: 1; } }
                    .animate-fade-in { animation: swc-fade-in var(--swc-anim-duration) ease-out; }
                    ${enableGlassmorphism ? `
                        .swc-glass {
                            background-color: rgba(255, 255, 255, 0.9);
                            backdrop-filter: blur(20px);
                            -webkit-backdrop-filter: blur(20px);
                        }
                    ` : ''}
                `}</style>
            </div>
        );

        // If a target element exists, use a React portal to render inside it
        if (targetEl) {
            return ReactDOM.createPortal(embeddedChatUI, targetEl);
        }
        // Fallback: render inline
        return embeddedChatUI;
    }

    // ==========================================
    // SIDEBAR MODE — Fixed side panel
    // ==========================================
    if (presentationMode === 'sidebar') {
        const sidebarWidth = appearance.sidebar_width || 400;
        const sidebarSide = appearance.position === 'left' ? 'left' : 'right';

        return (
            <div
                id="smart-ai-chatbot-sidebar-wrapper"
                style={{
                    fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                    '--swc-primary': primaryColor,
                    '--swc-primary-hover': primaryHover,
                    '--swc-bg-main': bgMain,
                    '--swc-bg-light': bgLight,
                    '--swc-text-primary': textPrimary,
                    '--swc-text-secondary': textSecondary,
                    '--swc-bg-message-bot': appearance.color_bg_message_bot || '#ffffff',
                    '--swc-text-message-bot': appearance.color_text_message_bot || '#1e293b',
                    '--swc-border': borderColor,
                    '--swc-anim-duration': animDuration,
                }}
            >
                <style>{dynamicEffectStyles}</style>

                {/* Sidebar panel — shown when open */}
                {chat.isOpen && (
                    <div
                        id="smart-ai-chatbot-sidebar"
                        style={{
                            position: 'fixed',
                            top: 0,
                            bottom: 0,
                            [sidebarSide]: 0,
                            width: `${sidebarWidth}px`,
                            maxWidth: '100vw',
                            zIndex: 2147483647,
                            display: 'flex',
                            flexDirection: 'column',
                            background: windowBgColor,
                            backdropFilter: 'blur(20px)',
                            boxShadow: widgetShadow,
                            animation: `swc-slide-in-${sidebarSide} var(--swc-anim-duration) cubic-bezier(0.16, 1, 0.3, 1)`,
                        }}
                        className={enableGlass ? "swc-glass" : ""}
                    >
                        {/* Header */}
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: '12px',
                                padding: '14px 20px',
                                background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                            }}
                        >
                            <div
                                style={{
                                    width: '36px', height: '36px', borderRadius: '10px',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    fontSize: '18px', background: 'rgba(255, 255, 255, 0.2)',
                                }}
                            >
                                {chat.currentAgent?.avatar || appearance.avatar || <Icons.Bot />}
                            </div>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <h3 style={{ fontSize: '14px', fontWeight: '600', color: 'white', margin: 0 }}>
                                    {config?.displayName || chat.currentAgent?.name || 'Chat Assistant'}
                                </h3>
                                <p style={{ fontSize: '11px', color: 'rgba(255, 255, 255, 0.7)', margin: 0, display: 'flex', alignItems: 'center', gap: '5px' }}>
                                    <span style={{
                                        width: '6px', height: '6px', borderRadius: '50%',
                                        backgroundColor: chat.isStreaming ? '#fbbf24' : '#34d399',
                                    }} />
                                    {chat.isStreaming ? 'Typing...' : 'Online'}
                                </p>
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '2px' }}>
                                <HeaderButton onClick={chat.clearHistory} title="New conversation">
                                    <Icons.Reset />
                                </HeaderButton>
                                <HeaderButton onClick={chat.toggleSound} title={chat.soundEnabled ? 'Mute' : 'Unmute'}>
                                    {chat.soundEnabled ? <Icons.Sound /> : <Icons.SoundOff />}
                                </HeaderButton>
                                <HeaderButton onClick={chat.close} title="Close">
                                    <Icons.Close />
                                </HeaderButton>
                            </div>
                        </div>

                        {/* History Panel Overlay */}
                        {showHistory && (
                            <div style={{
                                position: 'absolute',
                                inset: 0,
                                zIndex: 10,
                                background: 'white',
                                display: 'flex',
                                flexDirection: 'column',
                                borderRadius: borderRadius + 'px',
                                overflow: 'hidden',
                                animation: `swc-fade-in var(--swc-anim-duration) ease-out forwards`,
                            }}>
                                <div style={{
                                    padding: '16px 20px',
                                    borderBottom: '1px solid #e2e8f0',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    background: '#f8fafc'
                                }}>
                                    <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 600 }}>Past Conversations</h3>
                                    <button onClick={() => setShowHistory(false)} style={{ border: 'none', background: 'transparent', cursor: 'pointer', color: '#64748b' }}>
                                        <Icons.Close />
                                    </button>
                                </div>
                                <div style={{ flex: 1, overflowY: 'auto', padding: '16px' }}>
                                    {chat.pastSessions?.length === 0 ? (
                                        <p style={{ color: '#64748b', textAlign: 'center', marginTop: '20px', fontSize: '13px' }}>No past conversations found.</p>
                                    ) : (
                                        chat.pastSessions?.map(session => (
                                        <button
                                            key={session.id}
                                            onClick={() => {
                                                chat.loadSession(session.id);
                                                setShowHistory(false);
                                            }}
                                            style={{
                                                width: '100%',
                                                padding: '12px',
                                                background: '#f8fafc',
                                                border: '1px solid #e2e8f0',
                                                borderRadius: '8px',
                                                marginBottom: '10px',
                                                textAlign: 'left',
                                                cursor: 'pointer',
                                                transition: `all var(--swc-anim-duration)`
                                            }}
                                            {...(enableHoverEffects && {
                                                onMouseEnter: (e) => { e.currentTarget.style.borderColor = primaryColor; e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)'; },
                                                onMouseLeave: (e) => { e.currentTarget.style.borderColor = '#e2e8f0'; e.currentTarget.style.boxShadow = 'none'; },
                                            })}
                                        >
                                            <div style={{ fontWeight: 500, fontSize: '13px', color: '#0f172a', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{session.title || 'Chat'}</div>
                                            <div style={{ fontSize: '11px', color: '#64748b', marginTop: '4px' }}>{new Date(session.date).toLocaleDateString()}</div>
                                        </button>
                                    )))}
                                </div>
                            </div>
                        )}

                        {/* Messages */}
                        <div
                            className="flex-1 overflow-y-auto"
                            style={{ padding: '16px', background: 'linear-gradient(180deg, #f8fafc 0%, #ffffff 50%)', flex: 1, minHeight: 0 }}
                        >
                            {chat.messages.length === 0 && !chat.isLoading && (
                                <div style={{ textAlign: 'center', padding: '40px 16px' }}>
                                    <div style={{
                                        width: '56px', height: '56px', margin: '0 auto 16px', borderRadius: '16px',
                                        display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '28px',
                                        background: 'linear-gradient(135deg, ' + primaryColor + '20 0%, ' + primaryColor + '10 100%)',
                                    }}>
                                        {appearance.avatar || <Icons.HandWave />}
                                    </div>
                                    <h4 style={{ fontSize: '18px', fontWeight: '600', color: '#1e293b', marginBottom: '4px' }}>Hi there!</h4>
                                    <p style={{ fontSize: '14px', color: '#64748b' }}>{behavior.placeholder_text || 'Type your message to get started...'}</p>
                                </div>
                            )}
                            {chat.messages.map((msg, idx) => {
                                const isBot = msg.role === 'assistant';
                                const isLastBot = isBot && !chat.messages.slice(idx + 1).some(m => m.role === 'assistant');
                                return (
                                    <MessageBubble key={msg.id} message={msg} isBot={isBot} isStreaming={msg.isStreaming} isLastBot={isLastBot} activeTool={chat.activeTool} onOptionSelect={handleQuickReply} />
                                );
                            })}
                            {chat.activeTool ? (
                                <ActiveToolIndicator toolName={chat.activeTool} hasUsedTool={chat.hasUsedTool} />
                            ) : (
                                chat.isLoading && (!chat.isStreaming || chat.messages.length === 0 || chat.messages[chat.messages.length - 1].role !== 'assistant') && <ActiveToolIndicator toolName={null} hasUsedTool={chat.hasUsedTool} />
                            )}
                            {chat.error && (
                                <div style={{
                                    margin: '8px 0', padding: '10px 14px', borderRadius: '10px',
                                    background: '#fef2f2', border: '1px solid #fecaca',
                                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                                }}>
                                    <span style={{ fontSize: '13px', color: '#b91c1c' }}>{chat.error}</span>
                                    <button onClick={() => chat.setError(null)} style={{ fontSize: '12px', fontWeight: '600', color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', textDecoration: 'underline' }}>Dismiss</button>
                                </div>
                            )}
                            <div ref={chat.messagesEndRef} />
                        </div>

                        {/* Quick Questions - show until user sends their first message */}
                        {allQuickReplies.length > 0 && !chat.messages.some(m => m.role === 'user') && (
                            <QuickReplies replies={allQuickReplies} onSelect={handleQuickReply} />
                        )}

                        {/* Input */}
                        <div style={{ padding: '12px 16px', borderTop: '1px solid #f1f5f9', background: 'white' }}>
                            <form
                                onSubmit={handleSubmit}
                                style={{
                                    display: 'flex', alignItems: 'center', gap: '8px',
                                    padding: '8px 12px', borderRadius: '14px',
                                    border: '1px solid #e2e8f0', background: '#fafafa',
                                }}
                            >
                                <textarea
                                    ref={inputRef}
                                    value={inputValue}
                                    onChange={(e) => setInputValue(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    placeholder={behavior.placeholder_text || 'Type your message...'}
                                    style={{ flex: 1, resize: 'none', border: 'none', background: 'transparent', fontSize: '14px', color: '#1e293b', outline: 'none', minHeight: '22px', maxHeight: '80px', fontFamily: 'inherit' }}
                                    rows={1}
                                    disabled={isListening}
                                />
                                <div style={{ position: 'relative', display: 'flex' }} ref={addMenuRef}>
                                    <button
                                        type="button"
                                        onClick={() => setShowAddMenu(!showAddMenu)}
                                        style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '34px', height: '34px', borderRadius: '10px', border: 'none', background: showAddMenu ? '#e2e8f0' : 'transparent', color: showAddMenu ? '#1e293b' : '#94a3b8', cursor: 'pointer', transition: `all var(--swc-anim-duration)`, flexShrink: 0 }}
                                        title="Add Options"
                                    >
                                        <Icons.Reset />
                                    </button>
                                    {showAddMenu && (
                                        <div style={{
                                            position: 'absolute',
                                            bottom: 'calc(100% + 8px)',
                                            left: '50%',
                                            transform: 'translateX(-50%)',
                                            background: 'white',
                                            borderRadius: '12px',
                                            boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0,0,0,0.05)',
                                            padding: '6px',
                                            zIndex: 1000,
                                            minWidth: '140px',
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: '2px',
                                            border: '1px solid #e2e8f0'
                                        }}>
                                            <button
                                                type="button"
                                                onClick={() => { chat.clearHistory(); setShowHistory(false); setShowAddMenu(false); }}
                                                style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '13px', textAlign: 'left', fontWeight: '500', transition: `all var(--swc-anim-duration)` }}
                                                {...(enableHoverEffects && {
                                                    onMouseEnter: e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; },
                                                    onMouseLeave: e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; },
                                                })}
                                            >
                                                <Icons.Reset /> New Chat
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => { setShowHistory(true); setShowAddMenu(false); }}
                                                style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '13px', textAlign: 'left', fontWeight: '500', transition: `all var(--swc-anim-duration)` }}
                                                {...(enableHoverEffects && {
                                                    onMouseEnter: e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; },
                                                    onMouseLeave: e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; },
                                                })}
                                            >
                                                <Icons.History /> History
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {voiceSupported && (
                                    <button type="button" onClick={toggleVoiceInput} disabled={chat.isStreaming}
                                        style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '34px', height: '34px', borderRadius: '10px', border: 'none', background: isListening ? '#fee2e2' : 'transparent', color: isListening ? '#ef4444' : '#94a3b8', cursor: 'pointer', transition: `all var(--swc-anim-duration)` }}
                                        title={isListening ? 'Stop listening' : 'Voice input'}
                                    >
                                        {isListening ? <Icons.MicrophoneActive /> : <Icons.Microphone />}
                                    </button>
                                )}
                                {chat.isStreaming ? (
                                    <button type="button" onClick={handleStopGeneration}
                                        style={{
                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                            width: '36px', height: '36px', borderRadius: '10px', border: 'none',
                                            color: 'white', cursor: 'pointer',
                                            background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                                            boxShadow: '0 4px 12px rgba(239, 68, 68, 0.4)',
                                            transition: `all var(--swc-anim-duration)`,
                                        }}
                                        title="Stop generating"
                                    >
                                        <Icons.Stop />
                                    </button>
                                ) : (
                                    <button type="submit" disabled={!inputValue.trim()}
                                        style={{
                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                            width: '36px', height: '36px', borderRadius: '10px', border: 'none',
                                            color: 'white', cursor: inputValue.trim() ? 'pointer' : 'not-allowed',
                                            opacity: inputValue.trim() ? 1 : 0.4,
                                            background: inputValue.trim() ? `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)` : '#cbd5e1',
                                            transition: `all var(--swc-anim-duration)`,
                                        }}
                                    >
                                        <Icons.Send />
                                    </button>
                                )}
                            </form>
                            {/* Powered by removed */}
                        </div>
                    </div>
                )}

                {/* Toggle tab button on the side edge */}
                <button
                    onClick={handleToggleClick}
                    style={{
                        position: 'fixed',
                        top: '50%',
                        transform: 'translateY(-50%)',
                        [sidebarSide]: chat.isOpen ? `${sidebarWidth}px` : '0',
                        zIndex: 2147483647,
                        width: '32px',
                        height: '80px',
                        border: 'none',
                        background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                        color: 'white',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        boxShadow: sidebarSide === 'right'
                            ? '-2px 0 8px rgba(0, 0, 0, 0.15)'
                            : '2px 0 8px rgba(0, 0, 0, 0.15)',
                        borderRadius: sidebarSide === 'right' ? '8px 0 0 8px' : '0 8px 8px 0',
                        transition: `all var(--swc-anim-duration) cubic-bezier(0.16, 1, 0.3, 1)`,
                        writingMode: 'vertical-lr',
                        fontSize: '11px',
                        fontWeight: '600',
                        letterSpacing: '0.5px',
                    }}
                    aria-label={chat.isOpen ? 'Close chat' : 'Open chat'}
                >
                    {chat.isOpen ? '✕' : '💬 Chat'}
                </button>

                {/* Animations */}
                <style>{`
                    @keyframes swc-slide-in-right {
                        from { transform: translateX(100%); }
                        to { transform: translateX(0); }
                    }
                    @keyframes swc-slide-in-left {
                        from { transform: translateX(-100%); }
                        to { transform: translateX(0); }
                    }
                    @keyframes swc-fade-in {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    .animate-fade-in {
                        animation: swc-fade-in var(--swc-anim-duration) ease-out;
                    }
                    ${enableGlassmorphism ? `
                        .swc-glass {
                            background-color: rgba(255, 255, 255, 0.9);
                            backdrop-filter: blur(20px);
                            -webkit-backdrop-filter: blur(20px);
                        }
                    ` : ''}
                `}</style>
            </div>
        );
    }

    return (
        <div
            id="smart-ai-chatbot"
            className="fixed z-[2147483647]"
            style={{
                bottom: '24px',
                [appearance.position === 'left' ? 'left' : 'right']: '24px',
                fontFamily: fontFamily,
                fontSize: fontSizeBase + 'px',
                lineHeight: lineHeight,
                '--swc-primary': primaryColor,
                '--swc-primary-hover': primaryHover,
                '--swc-bg-message-bot': appearance.color_bg_message_bot || '#ffffff',
                '--swc-text-message-bot': appearance.color_text_message_bot || '#1e293b',
                '--swc-anim-duration': animDuration,
                '--swc-bubble-radius': `${bubbleRadius}px`,
            }}
        >
            {/* Chat Window */}
            {chat.isOpen && !chat.isMinimized && (
                <div
                    className={`absolute bottom-20 mb-2 overflow-hidden flex flex-col ${enableGlass ? "swc-glass" : ""}`}
                    style={{
                        width: windowWidth + 'px',
                        maxWidth: 'calc(100vw - 48px)',
                        height: windowHeight + 'px',
                        maxHeight: 'calc(100vh - 140px)',
                        background: windowBgColor,
                        backdropFilter: 'blur(20px)',
                        WebkitBackdropFilter: 'blur(20px)',
                        borderRadius: borderRadius + 'px',
                        boxShadow: widgetShadow,
                        [appearance.position === 'left' ? 'left' : 'right']: '0',
                        animation: `swc-slide-up var(--swc-anim-duration) cubic-bezier(0.16, 1, 0.3, 1)`,
                    }}
                >
                    {/* Header - Premium gradient */}
                    <div
                        className="flex items-center gap-3 px-5 py-4"
                        style={{
                            background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                            borderRadius: borderRadius + 'px ' + borderRadius + 'px 0 0',
                        }}
                    >
                        {/* Agent Avatar */}
                        <div
                            className="flex items-center justify-center w-11 h-11 rounded-xl text-xl"
                            style={{
                                background: 'rgba(255, 255, 255, 0.2)',
                                backdropFilter: 'blur(10px)',
                            }}
                        >
                            {chat.currentAgent?.avatar || appearance.avatar || <Icons.Bot />}
                        </div>

                        {/* Agent Info with Dropdown */}
                        <div className="flex-1 min-w-0 relative" ref={dropdownRef}>
                            {/* Clickable header for dropdown (only when multiple widgets) */}
                            {hasMultipleWidgets && availableWidgets.length > 1 ? (
                                <button
                                    onClick={toggleWidgetDropdown}
                                    className="w-full text-left hover:bg-white/10 rounded-lg px-2 py-1 -mx-2 -my-1 transition-colors"
                                >
                                    <div className="flex items-center gap-1.5">
                                        <h3 className="font-semibold text-white text-base overflow-visible whitespace-normal break-words">
                                            {selectedWidget?.displayName || selectedWidget?.name || config?.displayName || chat.currentAgent?.name || 'Shopping Assistant'}
                                        </h3>
                                        <Icons.ChevronDown className="w-4 h-4 text-white" strokeWidth={3} />
                                    </div>
                                    <p className="text-xs text-white/70 flex items-center gap-1.5">
                                        <span
                                            className="w-2 h-2 rounded-full"
                                            style={{
                                                backgroundColor: chat.isStreaming ? '#fbbf24' : '#34d399',
                                                boxShadow: chat.isStreaming ? '0 0 6px #fbbf24' : '0 0 6px #34d399',
                                            }}
                                        />
                                        {chat.isStreaming ? 'Typing...' : 'Online'}
                                    </p>
                                </button>
                            ) : (
                                <>
                                    <h3 className="font-semibold text-white text-base overflow-visible whitespace-normal break-words">
                                        {config?.displayName || chat.currentAgent?.name || 'Shopping Assistant'}
                                    </h3>
                                    <p className="text-xs text-white/70 flex items-center gap-1.5">
                                        <span
                                            className="w-2 h-2 rounded-full"
                                            style={{
                                                backgroundColor: chat.isStreaming ? '#fbbf24' : '#34d399',
                                                boxShadow: chat.isStreaming ? '0 0 6px #fbbf24' : '0 0 6px #34d399',
                                            }}
                                        />
                                        {chat.isStreaming ? 'Typing...' : 'Online'}
                                    </p>
                                </>
                            )}

                            {/* Widget Dropdown Menu */}
                            {showWidgetDropdown && (
                                <div
                                    className="absolute left-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden z-50"
                                    style={{
                                        animation: 'swc-slide-up 0.2s ease-out',
                                    }}
                                >
                                    <div className="p-2 space-y-1 max-h-60 overflow-y-auto">
                                        {availableWidgets.map((widget) => {
                                            const isActive = selectedWidget?.id === widget.id;
                                            return (
                                                <button
                                                    key={widget.id}
                                                    onClick={() => handleDropdownSelect(widget)}
                                                    className={`w-full flex items-center gap-3 p-2 rounded-lg transition-all duration-150 text-left ${isActive
                                                        ? 'bg-indigo-50 border border-indigo-200'
                                                        : 'hover:bg-slate-50 border border-transparent'
                                                        }`}
                                                >
                                                    <div
                                                        className="flex items-center justify-center w-8 h-8 rounded-lg text-sm"
                                                        style={{
                                                            background: widget.appearance?.color_primary
                                                                ? widget.appearance.color_primary
                                                                : '#6366f1',
                                                        }}
                                                    >
                                                        <span className="text-white">{widget.avatar || <Icons.Bot />}</span>
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <p className={`text-sm font-medium truncate ${isActive ? 'text-indigo-700' : 'text-slate-800'}`}>
                                                            {widget.displayName || widget.name}
                                                        </p>
                                                        {widget.description && (
                                                            <p className="text-xs text-slate-500 truncate">{widget.description}</p>
                                                        )}
                                                    </div>
                                                    {isActive && (
                                                        <svg className="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Header Actions */}
                        <div className="flex items-center gap-0.5">
                            <HeaderButton onClick={chat.minimize} title="Minimize">
                                <Icons.Minimize />
                            </HeaderButton>
                            <HeaderButton onClick={chat.close} title="Close">
                                <Icons.Close />
                            </HeaderButton>
                        </div>
                    </div>

                    {/* History Panel Overlay */}
                    {showHistory && (
                        <div style={{
                            position: 'absolute',
                            inset: 0,
                            zIndex: 10,
                            background: 'white',
                            display: 'flex',
                            flexDirection: 'column',
                            borderRadius: borderRadius + 'px',
                            overflow: 'hidden'
                        }}>
                            <div style={{
                                padding: '16px 20px',
                                borderBottom: '1px solid #e2e8f0',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                background: '#f8fafc'
                            }}>
                                <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 600 }}>Past Conversations</h3>
                                <HeaderButton onClick={() => setShowHistory(false)} title="Close History">
                                    <Icons.Close />
                                </HeaderButton>
                            </div>
                            <div style={{ flex: 1, overflowY: 'auto', padding: '16px' }}>
                                {chat.pastSessions?.length === 0 ? (
                                    <p style={{ color: '#64748b', textAlign: 'center', marginTop: '20px', fontSize: '13px' }}>No past conversations found.</p>
                                ) : (
                                    chat.pastSessions?.map(session => (
                                    <button
                                        key={session.id}
                                        onClick={() => {
                                            chat.loadSession(session.id);
                                            setShowHistory(false);
                                        }}
                                        style={{
                                            width: '100%',
                                            padding: '12px',
                                            background: '#f8fafc',
                                            border: '1px solid #e2e8f0',
                                            borderRadius: '8px',
                                            marginBottom: '10px',
                                            textAlign: 'left',
                                            cursor: 'pointer',
                                            transition: 'all 0.2s'
                                        }}
                                    >
                                        <div style={{ fontWeight: 500, fontSize: '13px', color: '#0f172a', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{session.title || 'Chat'}</div>
                                        <div style={{ fontSize: '11px', color: '#64748b', marginTop: '4px' }}>{new Date(session.date).toLocaleDateString()}</div>
                                    </button>
                                )))}
                            </div>
                        </div>
                    )}

                    {/* Messages Area */}
                    <div
                        className="flex-1 overflow-y-auto px-5 py-4"
                        style={{
                            background: 'linear-gradient(180deg, #f8fafc 0%, #ffffff 50%)',
                        }}
                    >
                        {/* Visual welcome only (no duplicate greeting text) */}
                        {chat.messages.length === 0 && !chat.isLoading && (
                            <div className="text-center py-8">
                                <div
                                    className="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center text-3xl"
                                    style={{
                                        background: `linear-gradient(135deg, ${primaryColor}20 0%, ${primaryColor}10 100%)`,
                                    }}
                                >
                                    {appearance.avatar || <Icons.HandWave />}
                                </div>
                                <p className="text-sm text-slate-500">
                                    {behavior.placeholder_text || 'Type your message to get started...'}
                                </p>
                            </div>
                        )}

                        {/* Messages */}
                        {chat.messages.map((msg, idx) => {
                            const isBot = msg.role === 'assistant';
                            const isLastBot = isBot && !chat.messages.slice(idx + 1).some(m => m.role === 'assistant');
                            return (
                                <MessageBubble
                                    key={msg.id}
                                    message={msg}
                                    isBot={isBot}
                                    isStreaming={msg.isStreaming}
                                    isLastBot={isLastBot}
                                    activeTool={chat.activeTool}
                                    onOptionSelect={handleQuickReply}
                                />
                            );
                        })}

                        {/* Loading indicator */}
                        {chat.activeTool ? (
                            <ActiveToolIndicator toolName={chat.activeTool} hasUsedTool={chat.hasUsedTool} />
                        ) : (
                            chat.isLoading && (!chat.isStreaming || chat.messages.length === 0 || chat.messages[chat.messages.length - 1].role !== 'assistant') && <ActiveToolIndicator toolName={null} hasUsedTool={chat.hasUsedTool} />
                        )}

                        {/* Error message */}
                        {chat.error && (
                            <div
                                className="mx-1 mb-3 px-4 py-3 rounded-xl flex items-center justify-between"
                                style={{
                                    background: 'linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)',
                                    border: '1px solid #fecaca',
                                }}
                            >
                                <span className="text-sm text-red-700">{chat.error}</span>
                                <button
                                    onClick={() => chat.setError(null)}
                                    className="text-sm font-medium text-red-600 hover:text-red-800 underline decoration-1 underline-offset-2"
                                >
                                    Dismiss
                                </button>
                            </div>
                        )}

                        {/* Scroll anchor */}
                        <div ref={chat.messagesEndRef} />
                    </div>

                    {/* Quick Questions - show until user sends their first message */}
                    {allQuickReplies.length > 0 && !chat.messages.some(m => m.role === 'user') && (
                        <QuickReplies
                            replies={allQuickReplies}
                            onSelect={handleQuickReply}
                        />
                    )}

                    {/* Input Area - Clean styling */}
                    <div className="px-4 pb-4 pt-2 bg-white border-t border-slate-100">
                        <form
                            onSubmit={handleSubmit}
                            className="flex items-center gap-2 px-4 py-2 rounded-2xl border border-slate-200 focus-within:border-slate-300"
                            style={{ background: isListening ? '#fef3c7' : '#fafafa', outline: 'none', transition: 'background 0.2s' }}
                        >
                            <textarea
                                ref={inputRef}
                                value={inputValue}
                                onChange={(e) => {
                                    setInputValue(e.target.value);
                                    // Auto-expand: reset height then set to scrollHeight, capped at 2 lines
                                    e.target.style.height = '24px';
                                    e.target.style.height = Math.min(e.target.scrollHeight, 48) + 'px';
                                }}
                                onKeyDown={handleKeyDown}
                                placeholder={isListening ? 'Listening...' : (behavior.placeholder_text || 'Type your message...')}
                                className="flex-1 resize-none border-0 bg-transparent text-slate-800 placeholder:text-slate-400"
                                style={{ minHeight: '24px', maxHeight: '48px', outline: 'none', boxShadow: 'none', overflowY: 'auto', lineHeight: '24px' }}
                                rows={1}
                                disabled={isListening}
                            />
                            {/* Add Menu */}
                            <div className="relative flex" ref={addMenuRef}>
                                <button
                                    type="button"
                                    onClick={() => setShowAddMenu(!showAddMenu)}
                                    className={`flex items-center justify-center w-10 h-10 rounded-xl transition-all duration-200 shrink-0 ${showAddMenu ? 'bg-slate-200 text-slate-800' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'}`}
                                    title="Add Options"
                                >
                                    <Icons.Reset />
                                </button>
                                {showAddMenu && (
                                    <div style={{
                                        position: 'absolute',
                                        bottom: 'calc(100% + 8px)',
                                        left: '50%',
                                        transform: 'translateX(-50%)',
                                        background: 'white',
                                        borderRadius: '12px',
                                        boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0,0,0,0.05)',
                                        padding: '6px',
                                        zIndex: 1000,
                                        minWidth: '150px',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: '2px',
                                        border: '1px solid #e2e8f0'
                                    }}>
                                        <button
                                            type="button"
                                            onClick={() => { chat.clearHistory(); setShowHistory(false); setShowAddMenu(false); }}
                                            style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '14px', textAlign: 'left', fontWeight: '500', transition: 'all 0.15s' }}
                                            onMouseEnter={e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; }}
                                            onMouseLeave={e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; }}
                                        >
                                            <Icons.Reset /> New Chat
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setShowHistory(true); setShowAddMenu(false); }}
                                            style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 12px', border: 'none', background: 'transparent', borderRadius: '8px', color: '#334155', cursor: 'pointer', fontSize: '14px', textAlign: 'left', fontWeight: '500', transition: 'all 0.15s' }}
                                            onMouseEnter={e => { e.currentTarget.style.backgroundColor = '#f1f5f9'; e.currentTarget.style.color = '#334155'; }}
                                            onMouseLeave={e => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = '#334155'; }}
                                        >
                                            <Icons.History /> History
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Voice Input Button */}
                            {voiceSupported && (
                                <button
                                    type="button"
                                    onClick={toggleVoiceInput}
                                    disabled={chat.isStreaming}
                                    className={`flex items-center justify-center w-10 h-10 rounded-xl transition-all duration-200 hover:scale-105 active:scale-95 ${isListening
                                        ? 'text-red-500 bg-red-100 animate-pulse'
                                        : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'
                                        }`}
                                    title={isListening ? 'Stop listening' : 'Voice input'}
                                >
                                    {isListening ? <Icons.MicrophoneActive /> : <Icons.Microphone />}
                                </button>
                            )}
                            {/* Stop Button (only during streaming) / Send Button (only when not streaming) */}
                            {chat.isStreaming ? (
                                <button
                                    type="button"
                                    onClick={handleStopGeneration}
                                    className="flex items-center justify-center w-10 h-10 rounded-xl text-white transition-all duration-200 hover:scale-105 active:scale-95"
                                    style={{
                                        background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                                        boxShadow: '0 4px 12px rgba(239, 68, 68, 0.4)',
                                    }}
                                    title="Stop generating"
                                >
                                    <Icons.Stop />
                                </button>
                            ) : (
                                <button
                                    type="submit"
                                    disabled={!inputValue.trim()}
                                    className="flex items-center justify-center w-10 h-10 rounded-xl text-white transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed hover:scale-105 active:scale-95"
                                    style={{
                                        background: inputValue.trim()
                                            ? `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`
                                            : '#cbd5e1',
                                        boxShadow: inputValue.trim() ? '0 4px 12px rgba(0, 0, 0, 0.15)' : 'none',
                                    }}
                                >
                                    <Icons.Send />
                                </button>
                            )}
                        </form>
                    </div>

                    {/* Powered by removed */}
                </div>
            )}

            {/* Widget Selector Popup */}
            {showWidgetSelector && (
                <div
                    className={`absolute bottom-20 mb-2 overflow-hidden ${enableGlass ? 'swc-glass' : ''}`}
                    style={{
                        width: '320px',
                        maxWidth: 'calc(100vw - 48px)',
                        background: windowBgColor,
                        borderRadius: '20px',
                        boxShadow: widgetShadow,
                        [appearance.position === 'left' ? 'left' : 'right']: '0',
                        animation: `swc-slide-up var(--swc-anim-duration, 0.3s) cubic-bezier(0.16, 1, 0.3, 1)`,
                    }}
                >
                    {/* Header */}
                    <div
                        className="px-5 py-4"
                        style={{
                            background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                            borderRadius: '20px 20px 0 0',
                        }}
                    >
                        <h3 className="font-semibold text-white text-base">Choose an Assistant</h3>
                        <p className="text-xs text-white/70 mt-1">Select who you'd like to chat with</p>
                    </div>

                    {/* Widget Options */}
                    <div className="p-3 space-y-2 max-h-80 overflow-y-auto">
                        {availableWidgets.map((widget) => {
                            const isCurrentWidget = selectedWidget?.id === widget.id;
                            return (
                                <button
                                    key={widget.id}
                                    onClick={() => handleWidgetSelect(widget)}
                                    className={`w-full flex items-center gap-3 p-3 rounded-xl border-2 transition-all ${enableHover ? 'hover:scale-[1.02]' : ''} text-left ${isCurrentWidget
                                        ? 'border-indigo-400 bg-indigo-50'
                                        : 'border-transparent hover:border-indigo-200 hover:bg-indigo-50/50'
                                        }`}
                                >
                                    <div
                                        className="flex items-center justify-center w-12 h-12 rounded-xl text-xl relative"
                                        style={{
                                            background: widget.appearance?.color_primary
                                                ? `linear-gradient(135deg, ${widget.appearance.color_primary} 0%, ${widget.appearance.color_primary_hover || widget.appearance.color_primary} 100%)`
                                                : 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
                                        }}
                                    >
                                        <span className="text-white">{widget.avatar || '🤖'}</span>
                                        {/* Active indicator badge */}
                                        {isCurrentWidget && (
                                            <span className="absolute -top-1 -right-1 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center shadow-sm">
                                                <svg className="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className={`font-semibold truncate ${isCurrentWidget ? 'text-indigo-700' : 'text-slate-800'}`}>
                                            {widget.displayName || widget.name}
                                        </p>
                                        {widget.description && (
                                            <p className="text-xs text-slate-500 truncate">{widget.description}</p>
                                        )}
                                        {isCurrentWidget && (
                                            <p className="text-xs text-green-600 font-medium mt-0.5">Currently chatting</p>
                                        )}
                                    </div>
                                    {isCurrentWidget ? (
                                        <svg className="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    ) : (
                                        <svg className="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {/* Close button */}
                    <div className="px-3 pb-3">
                        <button
                            onClick={() => setShowWidgetSelector(false)}
                            className="w-full py-2 text-sm text-slate-500 hover:text-slate-700 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}

            {/* Greeting Bubble — teaser tooltip near the toggle */}
            {greetingVisible && !chat.isOpen && !showWidgetSelector && (
                <div
                    className="animate-fade-in"
                    style={{
                        position: 'relative',
                        marginBottom: '12px',
                        display: 'flex',
                        alignItems: appearance.position === 'left' ? 'flex-start' : 'flex-end',
                        flexDirection: 'column',
                    }}
                >
                    <div
                        style={{
                            background: '#ffffff',
                            borderRadius: '16px',
                            padding: '10px 16px',
                            boxShadow: '0 8px 24px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.08)',
                            fontSize: '14px',
                            color: '#334155',
                            maxWidth: '220px',
                            lineHeight: '1.4',
                            position: 'relative',
                        }}
                    >
                        {appearance.greeting_bubble_text || 'Need help? Chat with us!'}

                        {appearance.greeting_bubble_dismissible !== false && (
                            <button
                                onClick={(e) => { e.stopPropagation(); setGreetingDismissed(true); setGreetingVisible(false); }}
                                style={{
                                    position: 'absolute',
                                    top: '-6px',
                                    right: '-6px',
                                    width: '20px',
                                    height: '20px',
                                    borderRadius: '50%',
                                    background: '#94a3b8',
                                    color: 'white',
                                    fontSize: '11px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    border: 'none',
                                    cursor: 'pointer',
                                    lineHeight: 1,
                                }}
                                aria-label="Dismiss"
                            >
                                ✕
                            </button>
                        )}

                        {/* Tail arrow - centered on toggle button */}
                        <div style={{
                            position: 'absolute',
                            bottom: '-6px',
                            [appearance.position === 'left' ? 'left' : 'right']: '28px',
                            width: '12px',
                            height: '12px',
                            background: '#ffffff',
                            transform: 'rotate(45deg)',
                            boxShadow: '4px 4px 8px rgba(0,0,0,0.06)',
                        }} />
                    </div>
                </div>
            )}

            {/* Toggle Button - Premium floating action button with customizable shapes */}
            {(() => {
                // Toggle shape styles
                const toggleShape = appearance.toggle_shape || 'circle';
                const toggleLabel = appearance.toggle_label || '';
                const toggleIcon = appearance.toggle_icon || 'chat';
                const toggleShadow = appearance.toggle_shadow || 'medium';
                const toggleGlow = appearance.toggle_glow || false;
                const togglePulse = appearance.toggle_pulse || false;
                const toggleBorder = appearance.toggle_border || false;
                const toggleBorderColor = appearance.toggle_border_color || '#ffffff';

                // Shadow presets
                const shadows = {
                    none: 'none',
                    small: '0 2px 8px rgba(0, 0, 0, 0.15)',
                    medium: '0 8px 24px rgba(0, 0, 0, 0.25)',
                    large: '0 12px 40px rgba(0, 0, 0, 0.35)',
                };

                // Shape-specific styles
                const shapeStyles = {
                    circle: {
                        width: `${toggleSize}px`,
                        height: `${toggleSize}px`,
                        borderRadius: '50%',
                        padding: '0',
                    },
                    pill: {
                        width: toggleLabel ? 'auto' : `${toggleSize * 1.8}px`,
                        height: `${toggleSize}px`,
                        borderRadius: `${toggleSize / 2}px`,
                        padding: toggleLabel ? '0 20px' : '0 16px',
                        gap: '8px',
                    },
                    rounded_square: {
                        width: `${toggleSize}px`,
                        height: `${toggleSize}px`,
                        borderRadius: '16px',
                        padding: '0',
                    },
                    card: {
                        width: 'auto',
                        height: 'auto',
                        borderRadius: '16px',
                        padding: '12px 20px',
                        gap: '10px',
                    },
                };

                const currentShape = shapeStyles[toggleShape] || shapeStyles.circle;

                // Get the appropriate icon component
                const getToggleIcon = () => {
                    if (chat.isOpen || showWidgetSelector) {
                        return <Icons.Close />;
                    }
                    if (appearance.avatar) {
                        return <span className="text-2xl">{appearance.avatar}</span>;
                    }
                    if (toggleIcon === 'none') {
                        return null;
                    }
                    if (toggleIcon === 'custom' && appearance.toggle_custom_icon) {
                        return <span className="text-2xl">{appearance.toggle_custom_icon}</span>;
                    }
                    switch (toggleIcon) {
                        case 'message': return <Icons.Message />;
                        case 'support': return <Icons.Support />;
                        case 'headset': return <Icons.Headset />;
                        default: return <Icons.Chat />;
                    }
                };

                // Build animation class
                const animationClass = togglePulse && !chat.isOpen ? 'swc-toggle-pulse' : '';
                const glowClass = toggleGlow && !chat.isOpen ? 'swc-toggle-glow' : '';

                return (
                    <button
                        onClick={handleToggleClick}
                        className={`relative flex items-center justify-center transition-all duration-300 ${enableHover ? 'swc-btn-hover' : ''} ${animationClass} ${glowClass}`}
                        style={{
                            ...currentShape,
                            background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryHover} 100%)`,
                            boxShadow: shadows[toggleShadow],
                            border: toggleBorder ? `2px solid ${toggleBorderColor}` : 'none',
                        }}
                        aria-label={chat.isOpen ? 'Close chat' : 'Open chat'}
                    >
                        <span
                            className="text-white transition-transform duration-300 flex items-center gap-2"
                            style={{
                                transform: chat.isOpen || showWidgetSelector ? 'rotate(90deg)' : 'rotate(0deg)',
                            }}
                        >
                            {toggleShape === 'card' && toggleIcon !== 'none' ? (
                                <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-white/20">
                                    {getToggleIcon()}
                                </div>
                            ) : toggleIcon !== 'none' ? (
                                getToggleIcon()
                            ) : null}
                            {toggleLabel && !chat.isOpen && (toggleShape === 'pill' || toggleShape === 'card') && (
                                <span className="text-sm font-medium whitespace-nowrap">{toggleLabel}</span>
                            )}
                        </span>

                        {/* Unread indicator */}
                        {!chat.isOpen && chat.messages.length > 0 && (
                            <span
                                className="absolute -top-1 -right-1 w-5 h-5 text-xs font-bold rounded-full flex items-center justify-center text-white"
                                style={{
                                    background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                                    boxShadow: '0 2px 8px rgba(239, 68, 68, 0.4)',
                                }}
                            >
                                !
                            </span>
                        )}
                    </button>
                );
            })()}

            {/* Animation styles + Markdown content styles */}
            <style>{`
                @keyframes swc-slide-up {
                    from { opacity: 0; transform: translateY(20px) scale(0.95); }
                    to { opacity: 1; transform: translateY(0) scale(1); }
                }
                @keyframes swc-fade-in {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes swc-bounce-in {
                    0% { opacity: 0; transform: scale(0.3); }
                    50% { opacity: 1; transform: scale(1.05); }
                    70% { transform: scale(0.95); }
                    100% { transform: scale(1); }
                }
                @keyframes swc-scale-in {
                    from { opacity: 0; transform: scale(0.5); }
                    to { opacity: 1; transform: scale(1); }
                }
                @keyframes swc-pulse-anim {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
                @keyframes swc-glow-anim {
                    0%, 100% { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25); }
                    50% { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25), 0 0 20px ${primaryColor}66; }
                }
                .swc-toggle-pulse {
                    animation: swc-pulse-anim 2s ease-in-out infinite;
                }
                .swc-toggle-glow {
                    animation: swc-glow-anim 2s ease-in-out infinite;
                }
                .animate-fade-in {
                    animation: swc-fade-in 0.3s ease-out;
                }
                /* Mobile toggle size override */
                @media (max-width: 640px) {
                    .swc-toggle-btn {
                        width: ${appearance.mobile_toggle_size || 52}px !important;
                        height: ${appearance.mobile_toggle_size || 52}px !important;
                    }
                }

                /* Markdown content styles for chat bubbles */
                .swc-markdown-content { word-wrap: break-word; overflow-wrap: break-word; }
                .swc-markdown-content > :first-child { margin-top: 0 !important; }
                .swc-markdown-content > :last-child { margin-bottom: 0 !important; }
                .swc-markdown-content p { margin: 0.35em 0; line-height: 1.5; }
                .swc-markdown-content strong, .swc-markdown-content b { font-weight: 600; }
                .swc-markdown-content em, .swc-markdown-content i { font-style: italic; }
                .swc-markdown-content a { color: #6366f1; text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 2px; }
                .swc-markdown-content a:hover { color: #4f46e5; }
                .swc-user-bubble .swc-markdown-content a { color: rgba(255, 255, 255, 0.95) !important; text-decoration: underline; text-decoration-color: rgba(255, 255, 255, 0.6); }
                .swc-user-bubble .swc-markdown-content a:hover { color: #ffffff !important; text-decoration-color: rgba(255, 255, 255, 0.9); }
                .swc-markdown-content h1, .swc-markdown-content h2, .swc-markdown-content h3,
                .swc-markdown-content h4, .swc-markdown-content h5, .swc-markdown-content h6 {
                    font-weight: 600; line-height: 1.3; margin: 0.5em 0 0.25em;
                }
                .swc-markdown-content h1 { font-size: 1.25em; }
                .swc-markdown-content h2 { font-size: 1.15em; }
                .swc-markdown-content h3 { font-size: 1.08em; }
                .swc-markdown-content h4, .swc-markdown-content h5, .swc-markdown-content h6 { font-size: 1em; }
                .swc-markdown-content ul, .swc-markdown-content ol { margin: 0.35em 0; padding-left: 1.4em; }
                .swc-markdown-content li { margin: 0.15em 0; line-height: 1.45; }
                .swc-markdown-content ul { list-style-type: disc; }
                .swc-markdown-content ol { list-style-type: decimal; }
                .swc-markdown-content li > ul, .swc-markdown-content li > ol { margin: 0.1em 0; }
                .swc-markdown-content code {
                    background: rgba(0, 0, 0, 0.06); padding: 0.15em 0.4em; border-radius: 4px;
                    font-size: 0.88em; font-family: 'SF Mono', Menlo, Consolas, monospace;
                }
                .swc-markdown-content pre {
                    background: #1e293b; color: #e2e8f0; padding: 0.75em 1em; border-radius: 8px;
                    overflow-x: auto; margin: 0.5em 0; font-size: 0.85em; line-height: 1.45;
                }
                .swc-markdown-content pre code { background: none; padding: 0; border-radius: 0; font-size: inherit; color: inherit; }
                .swc-markdown-content blockquote {
                    border-left: 3px solid #6366f1; padding: 0.3em 0 0.3em 0.8em;
                    margin: 0.4em 0; color: #475569; font-style: italic;
                }
                .swc-markdown-content hr { border: none; border-top: 1px solid rgba(0, 0, 0, 0.1); margin: 0.5em 0; }
                .swc-markdown-content table { width: 100%; border-collapse: collapse; margin: 0.4em 0; font-size: 0.9em; }
                .swc-markdown-content th, .swc-markdown-content td { border: 1px solid rgba(0, 0, 0, 0.1); padding: 0.35em 0.6em; text-align: left; }
                .swc-markdown-content th { background: rgba(0, 0, 0, 0.04); font-weight: 600; }
                .swc-markdown-content del { text-decoration: line-through; opacity: 0.7; }
            `}</style>
        </div>
    );
});

export default ChatWidget;
