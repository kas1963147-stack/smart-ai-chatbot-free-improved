/**
 * Chat Starter - Metronic v9 AI Style
 * Main chat starter component combining header, input and actions
 */
import * as React from 'react';
import { cn } from '../../ui/utils';
import { ChatStarterHeader } from './ChatStarterHeader';
import { ChatStarterInput } from './ChatStarterInput';
import { ChatStarterActions } from './ChatStarterActions';

export function ChatStarter({
    onSend,
    onAttachment,
    onPersonaSelect,
    onStopGeneration,
    className,
    compact = false,
    disabled = false,
    isStreaming = false,
    placeholder,
    selectedAgent, // Added prop for dynamic actions
}) {
    const [message, setMessage] = React.useState('');

    const handleSend = () => {
        if (!message.trim()) return;
        onSend?.(message.trim());
        setMessage('');
    };

    return (
        <div
            className={cn(
                compact
                    ? 'flex flex-col w-full'
                    : 'flex flex-col items-center justify-center flex-1 p-6',
                className
            )}
        >
            {!compact && <ChatStarterHeader selectedAgent={selectedAgent} />}
            <div className={cn('w-full', !compact && 'max-w-3xl')}>
                <ChatStarterInput
                    message={message}
                    onMessageChange={setMessage}
                    onSend={handleSend}
                    onStopGeneration={onStopGeneration}
                    onAttachment={onAttachment}
                    disabled={disabled}
                    isStreaming={isStreaming}
                    placeholder={placeholder}
                    compact={compact}
                />

                {!compact && <ChatStarterActions onSelect={onPersonaSelect} selectedAgent={selectedAgent} />}
            </div>
        </div>
    );
}
