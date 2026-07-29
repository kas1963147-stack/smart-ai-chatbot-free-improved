/**
 * Chat Messages Container - Metronic v9 AI Style
 * With auto-scroll to bottom on new messages
 */
import * as React from 'react';
import { ChatMessage } from './ChatMessage';
import { cn } from '../../ui/utils';

export function ChatMessages({ messages = [], className, children, onCopy, onFeedback, onOptionSelect }) {
    const containerRef = React.useRef(null);
    const bottomRef = React.useRef(null);
    const [userScrolled, setUserScrolled] = React.useState(false);

    // Auto-scroll to bottom when new messages arrive
    React.useEffect(() => {
        if (!userScrolled && bottomRef.current) {
            bottomRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [messages, userScrolled]);

    // Also scroll on initial load
    React.useEffect(() => {
        if (bottomRef.current) {
            bottomRef.current.scrollIntoView({ behavior: 'auto' });
        }
    }, []);

    // Detect if user has manually scrolled up
    const handleScroll = React.useCallback(() => {
        if (!containerRef.current) return;

        const { scrollTop, scrollHeight, clientHeight } = containerRef.current;
        const isNearBottom = scrollHeight - scrollTop - clientHeight < 100;

        if (isNearBottom) {
            setUserScrolled(false);
        } else {
            setUserScrolled(true);
        }
    }, []);

    // Reset userScrolled when messages change (new message arrives)
    React.useEffect(() => {
        setUserScrolled(false);
    }, [messages.length]);

    return (
        <div
            ref={containerRef}
            onScroll={handleScroll}
            className={cn(
                'flex flex-col h-full overflow-y-auto space-y-3.5',
                className
            )}
        >
            {messages.map((message) => (
                <ChatMessage
                    key={message.id}
                    role={message.role}
                    content={message.content}
                    timestamp={message.timestamp}
                    isStreaming={message.isStreaming}
                    toolCalls={message.tool_calls}
                    onCopy={onCopy}
                    onFeedback={onFeedback}
                    onOptionSelect={onOptionSelect}
                />
            ))}
            {children}
            {/* Invisible element to scroll to */}
            <div ref={bottomRef} />
        </div>
    );
}
