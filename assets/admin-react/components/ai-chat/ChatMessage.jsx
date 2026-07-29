/**
 * Chat Message Component - Metronic v9 AI Style
 * Ported from Metronic TypeScript to JSX
 */
import * as React from 'react';
import { Bot, Copy, ThumbsUp, ThumbsDown, Share2, RotateCcw, MoreHorizontal, Wrench, Check, Loader2 } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '../../ui/avatar';
import { Button } from '../../ui/button';
import { cn } from '../../ui/utils';

export function ChatMessage({ role, content, timestamp, isStreaming, toolCalls, onCopy, onFeedback, onOptionSelect }) {
    if (!content && (!toolCalls || toolCalls.length === 0) && !isStreaming) {
        return null; // Don't render empty bubbles at all unless streaming
    }

    let displayContent = (content || '').toString();
    let options = [];
    if (displayContent) {
        const optionsRegex = /\[OPTIONS\]\s*([\s\S]*?)\s*\[\/OPTIONS\]/gi;
        const match = optionsRegex.exec(displayContent);
        if (match) {
            displayContent = displayContent.replace(optionsRegex, '').trim();
            try {
                let jsonStr = match[1].trim();
                let unescapedJson = jsonStr
                    .replace(/&quot;/g, '"')
                    .replace(/&apos;/g, "'")
                    .replace(/[“”]/g, '"')
                    .replace(/[‘’]/g, "'");
                
                const parsed = JSON.parse(unescapedJson);
                if (Array.isArray(parsed)) {
                    options = parsed.filter(opt => typeof opt === 'string' && opt.trim()).slice(0, 8);
                }
            } catch (e) {
                const rawContent = match[1].trim();
                const matches = rawContent.match(/["']([^"']+)["']/g);
                if (matches) {
                    options = matches.map(m => m.replace(/["']/g, ''));
                } else {
                    const lines = rawContent.split('\n').map(l => l.replace(/^[-•*\d.]\s*/, '').trim()).filter(Boolean);
                    if (lines.length > 0 && lines.length <= 8) {
                        options = lines;
                    }
                }
            }
        }
    }

    const isUser = role === 'user';

    const getFriendlyName = (name) => {
        if (!name) return "Processing...";
        const lowerName = name.toLowerCase();
        if (lowerName.includes('search') || lowerName.includes('google')) return "Searching the web...";
        if (lowerName.includes('knowledge') || lowerName.includes('rag')) return "Reading knowledge base...";
        if (lowerName.includes('lead') || lowerName.includes('form')) return "Processing information...";
        if (lowerName.includes('appointment') || lowerName.includes('calendar')) return "Checking schedule...";
        if (lowerName.includes('skill') || lowerName.includes('tool')) return "Processing request...";
        const formattedName = name.replace(/_/g, ' ');
        return `Using ${formattedName}...`;
    };

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(displayContent);
            onCopy?.('Message copied to clipboard');
        } catch {
            onCopy?.('Failed to copy message', true);
        }
    };

    const handleThumbsUp = () => {
        onFeedback?.('positive');
    };

    const handleThumbsDown = () => {
        onFeedback?.('negative');
    };

    const handleShare = async () => {
        try {
            if (navigator.share) {
                await navigator.share({
                    title: 'AI Response',
                    text: displayContent,
                });
            } else {
                await navigator.clipboard.writeText(displayContent);
                onCopy?.('Message copied to clipboard');
            }
        } catch {
            // User cancelled
        }
    };

    const handleRegenerate = () => {
        onFeedback?.('regenerate');
    };

    // Parse content into structured HTML elements
    const renderContent = () => {
        const lines = displayContent.split('\n');
        const elements = [];
        let currentList = null;

        const flushList = () => {
            if (currentList) {
                if (currentList.type === 'ul') {
                    elements.push(
                        <ul key={`ul-${elements.length}`} className="my-3 space-y-1.5">
                            {currentList.items.map((item, i) => (
                                <li key={i} className="flex items-start gap-2 pl-1">
                                    <span className="mt-2 size-1 rounded-full bg-current shrink-0 opacity-70" />
                                    <span className="flex-1">{parseInline(item)}</span>
                                </li>
                            ))}
                        </ul>
                    );
                } else {
                    elements.push(
                        <ol key={`ol-${elements.length}`} className="my-3 space-y-1.5">
                            {currentList.items.map((item, i) => (
                                <li key={i} className="flex items-start gap-2 pl-1">
                                    <span className="font-medium text-muted-foreground text-sm shrink-0">{i + 1}.</span>
                                    <span className="flex-1">{parseInline(item)}</span>
                                </li>
                            ))}
                        </ol>
                    );
                }
                currentList = null;
            }
        };

        const parseInline = (text) => {
            const parts = text.split(/(\*\*.*?\*\*)/g);
            return parts.map((part, i) => {
                if (part.startsWith('**') && part.endsWith('**')) {
                    return <strong key={i} className="font-semibold text-muted-foreground">{part.slice(2, -2)}</strong>;
                }
                return part;
            });
        };

        lines.forEach((line, index) => {
            const trimmed = line.trim();

            if (!trimmed) {
                flushList();
                if (elements.length > 0) {
                    elements.push(<div key={`space-${index}`} className="h-3" />);
                }
                return;
            }

            if (trimmed.match(/^[•\-*\.]\s/)) {
                const content = trimmed.replace(/^[•\-*\.]\s*/, '');
                if (!currentList || currentList.type !== 'ul') {
                    flushList();
                    currentList = { type: 'ul', items: [] };
                }
                currentList.items.push(content);
                return;
            }

            const numberMatch = trimmed.match(/^(\d+)\.\s+(.+)$/);
            if (numberMatch) {
                const content = numberMatch[2];
                if (!currentList || currentList.type !== 'ol') {
                    flushList();
                    currentList = { type: 'ol', items: [] };
                }
                currentList.items.push(content);
                return;
            }

            if ((trimmed.startsWith('**') && trimmed.endsWith('**')) || trimmed.startsWith('###')) {
                flushList();
                const headerText = trimmed.replace(/^###\s*/, '').replace(/^\*\*|\*\*$/g, '');
                elements.push(
                    <h3 key={index} className="font-bold text-[15px] mt-5 mb-2.5 first:mt-0 text-muted-foreground">
                        {headerText}
                    </h3>
                );
                return;
            }

            flushList();
            elements.push(
                <p key={index} className="my-1 leading-relaxed">
                    {parseInline(line)}
                </p>
            );
        });

        flushList();
        return elements;
    };

    return (
        <div
            className={cn(
                'flex items-start gap-3 py-4',
                isUser && 'flex-row-reverse'
            )}
        >
            {isUser ? (
                <Avatar className="size-9">
                    <AvatarFallback className="bg-primary text-primary-foreground text-sm">U</AvatarFallback>
                </Avatar>
            ) : (
                <Avatar className="size-8 shrink-0">
                    <AvatarFallback className="bg-primary/10">
                        <Bot className="size-4 text-primary" />
                    </AvatarFallback>
                </Avatar>
            )}

            <div
                className={cn(
                    'flex flex-col gap-1 flex-1',
                    isUser && 'items-end'
                )}
            >
                <div
                    className={cn(
                        'rounded-2xl px-5 py-3.5 text-sm shadow-sm relative group',
                        isUser
                            ? 'bg-primary text-primary-foreground max-w-[85%] rounded-br-sm'
                            : 'bg-muted/50 text-foreground max-w-[90%] rounded-bl-sm'
                    )}
                >
                    {/* Tool Execution Cards */}
                    {!isUser && toolCalls && toolCalls.length > 0 && (
                        <div className="space-y-1.5 mb-2">
                            {toolCalls.map((tool, idx) => (
                                <div
                                    key={idx}
                                    className={cn(
                                        'flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs border',
                                        tool.status === 'running'
                                            ? 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20 text-amber-700 dark:text-amber-300'
                                            : 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-300'
                                    )}
                                >
                                    {tool.status === 'running' ? (
                                        <Loader2 className="size-3 animate-spin" />
                                    ) : (
                                        <Check className="size-3" />
                                    )}
                                    <Wrench className="size-3 opacity-60" />
                                    <span className="font-medium">{getFriendlyName(tool.name)}</span>
                                    {tool.status === 'running' && (
                                        <span className="text-[10px] opacity-60 ml-auto">running…</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="text-sm">
                        {renderContent()}
                    </div>
                    {options.length > 0 && !isStreaming && !isUser && (
                        <div className="flex flex-wrap gap-2 mt-3 pt-2">
                            {options.map((option, idx) => (
                                <button
                                    key={idx}
                                    onClick={() => onOptionSelect?.(option)}
                                    className="px-3 py-1.5 text-[13px] font-medium bg-background border border-border rounded-full hover:bg-primary/10 hover:text-primary hover:border-primary/30 transition-colors shadow-sm"
                                >
                                    {option}
                                </button>
                            ))}
                        </div>
                    )}
                    {isStreaming && !isUser && (!toolCalls || toolCalls.length === 0 || displayContent.trim().length > 0) && (
                        <>
                            {(!displayContent || displayContent.trim().length === 0) ? (
                                <div className="flex items-center gap-2.5 py-1 animate-fade-in">
                                    <span className="text-sm font-medium text-muted-foreground">
                                        AI is thinking...
                                    </span>
                                    <span className="flex gap-0.5 ml-1">
                                        <span className="w-1.5 h-1.5 bg-primary/60 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                                        <span className="w-1.5 h-1.5 bg-primary/60 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                                        <span className="w-1.5 h-1.5 bg-primary/60 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                                    </span>
                                </div>
                            ) : (
                                <span className="inline-flex items-center ml-1 gap-1 h-4">
                                    <span
                                        className="inline-block w-0.5 h-4 rounded-full"
                                        style={{
                                            background: 'linear-gradient(180deg, var(--primary, #6366f1) 0%, var(--primary, #6366f1) 50%, transparent 100%)',
                                            animation: 'streamCursorBlink 0.8s ease-in-out infinite',
                                        }}
                                    />
                                    <style>{`
                                        @keyframes streamCursorBlink {
                                            0%, 100% { opacity: 1; transform: scaleY(1); }
                                            50% { opacity: 0.3; transform: scaleY(0.8); }
                                        }
                                    `}</style>
                                </span>
                            )}
                        </>
                    )}

                    {/* Toolbar for AI messages */}
                    {!isUser && !isStreaming && (
                        <div className="flex items-center gap-1 mt-3 pt-3 border-t border-border">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 h-7 text-muted-foreground hover:text-foreground hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                onClick={handleCopy}
                                title="Copy"
                            >
                                <Copy className="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 h-7 text-muted-foreground hover:text-foreground hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                onClick={handleThumbsUp}
                                title="Thumbs up"
                            >
                                <ThumbsUp className="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 h-7 text-muted-foreground hover:text-foreground hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                onClick={handleThumbsDown}
                                title="Thumbs down"
                            >
                                <ThumbsDown className="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 h-7 text-muted-foreground hover:text-foreground hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                onClick={handleShare}
                                title="Share"
                            >
                                <Share2 className="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 h-7 text-muted-foreground hover:text-foreground hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                onClick={handleRegenerate}
                                title="Regenerate"
                            >
                                <RotateCcw className="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 h-7 text-muted-foreground hover:text-foreground hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                title="More options"
                            >
                                <MoreHorizontal className="size-3.5" />
                            </Button>
                        </div>
                    )}
                </div>
                {timestamp && (
                    <span className="text-xs text-muted-foreground px-1">
                        {timestamp}
                    </span>
                )}
            </div>
        </div>
    );
}
