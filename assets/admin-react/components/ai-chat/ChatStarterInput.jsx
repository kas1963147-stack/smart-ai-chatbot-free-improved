/**
 * Chat Starter Input - Metronic v9 AI Style
 * The main input component for the AI chat interface
 * All buttons and input in a single row with file upload preview
 */
import * as React from 'react';
import { Paperclip, Mic, X, Send, Square, FileText, Image } from 'lucide-react';
import { Button } from '../../ui/button';
import { cn } from '../../ui/utils';

export function ChatStarterInput({
    message,
    onMessageChange,
    onSend,
    onStopGeneration,
    onAttachment,
    disabled,
    isStreaming = false,
    placeholder = 'Message...',
    compact = false,
}) {
    const textareaRef = React.useRef(null);
    const fileInputRef = React.useRef(null);
    const recognitionRef = React.useRef(null);
    const [attachedFiles, setAttachedFiles] = React.useState([]);
    const [isRecording, setIsRecording] = React.useState(false);

    // Initialize speech recognition
    React.useEffect(() => {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (SpeechRecognition) {
            try {
                recognitionRef.current = new SpeechRecognition();
                recognitionRef.current.continuous = false;
                recognitionRef.current.interimResults = true;
                recognitionRef.current.lang = 'en-US';

                recognitionRef.current.onresult = (event) => {
                    const transcript = Array.from(event.results)
                        .map((result) => result[0].transcript)
                        .join('');
                    onMessageChange?.(transcript);
                };

                recognitionRef.current.onend = () => {
                    setIsRecording(false);
                };

                recognitionRef.current.onerror = (event) => {
                    console.error('Speech recognition error:', event.error);
                    setIsRecording(false);
                    if (event.error === 'not-allowed') {
                        alert('Microphone access denied. Please allow microphone access in browser settings.');
                    }
                };
            } catch (err) {
                console.warn('Speech recognition not available:', err);
            }
        }

        return () => {
            if (recognitionRef.current) {
                try {
                    recognitionRef.current.abort();
                } catch (e) {
                    // ignore
                }
            }
        };
    }, [onMessageChange]);

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    const handleSend = () => {
        if (!message?.trim() && attachedFiles.length === 0) return;
        // Stop recording if active
        if (isRecording && recognitionRef.current) {
            try {
                recognitionRef.current.stop();
            } catch (e) { }
            setIsRecording(false);
        }
        onSend?.(message?.trim(), attachedFiles);
        setAttachedFiles([]);
    };

    const handleStop = () => {
        onStopGeneration?.();
    };

    // Auto-resize textarea
    const handleChange = (e) => {
        onMessageChange?.(e.target.value);
        const textarea = e.target;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
    };

    const handleFileSelect = () => {
        fileInputRef.current?.click();
    };

    const handleFileChange = (e) => {
        const files = Array.from(e.target.files || []);
        if (files.length > 0) {
            const newFiles = files.map(file => ({
                id: Date.now() + Math.random(),
                file,
                name: file.name,
                type: file.type,
                size: file.size,
                preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null
            }));
            setAttachedFiles(prev => [...prev, ...newFiles]);
            // Also trigger the parent handler if exists
            if (onAttachment) {
                files.forEach(file => onAttachment(file));
            }
        }
        // Reset input
        e.target.value = '';
    };

    const removeFile = (fileId) => {
        setAttachedFiles(prev => {
            const updated = prev.filter(f => f.id !== fileId);
            // Revoke object URLs to prevent memory leaks
            const removed = prev.find(f => f.id === fileId);
            if (removed?.preview) {
                URL.revokeObjectURL(removed.preview);
            }
            return updated;
        });
    };

    // Voice input toggle
    const toggleVoiceInput = () => {
        if (!recognitionRef.current) {
            alert('Voice input is not supported in this browser. Please use Chrome or Edge.');
            return;
        }

        if (isRecording) {
            try {
                recognitionRef.current.stop();
            } catch (e) {
                console.error('Error stopping recognition:', e);
            }
            setIsRecording(false);
        } else {
            try {
                recognitionRef.current.start();
                setIsRecording(true);
            } catch (err) {
                console.error('Error starting recognition:', err);
                setIsRecording(false);
                if (err.message?.includes('already started')) {
                    try {
                        recognitionRef.current.stop();
                    } catch (e) { }
                } else {
                    alert('Could not start voice input. Error: ' + err.message);
                }
            }
        }
    };

    const formatFileSize = (bytes) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    const getFileIcon = (type) => {
        if (type.startsWith('image/')) return <Image className="size-4" />;
        return <FileText className="size-4" />;
    };

    return (
        <div className={cn('relative', !compact && 'mb-8')}>
            {/* Attached Files Preview */}
            {attachedFiles.length > 0 && (
                <div className="flex flex-wrap gap-2 mb-3">
                    {attachedFiles.map((file) => (
                        <div
                            key={file.id}
                            className="flex items-center gap-2 px-3 py-2 bg-muted/50 rounded-lg border border-border group"
                        >
                            {file.preview ? (
                                <img
                                    src={file.preview}
                                    alt={file.name}
                                    className="w-8 h-8 rounded object-cover"
                                />
                            ) : (
                                <div className="w-8 h-8 rounded bg-muted flex items-center justify-center text-muted-foreground">
                                    {getFileIcon(file.type)}
                                </div>
                            )}
                            <div className="flex flex-col min-w-0">
                                <span className="text-xs font-medium text-foreground truncate max-w-[120px]">
                                    {file.name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {formatFileSize(file.size)}
                                </span>
                            </div>
                            <button
                                onClick={() => removeFile(file.id)}
                                className="ml-1 p-1 rounded-full hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Main Input Container - All in one row */}
            <div
                className={cn(
                    'relative flex items-end gap-2 transition-all rounded-2xl border shadow-lg px-3 py-2',
                    'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700'
                )}
            >
                {/* Attachment Button */}
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9 shrink-0 text-muted-foreground hover:text-foreground rounded-lg"
                    onClick={handleFileSelect}
                    disabled={disabled}
                    type="button"
                >
                    <Paperclip className="size-4" />
                </Button>

                {/* Hidden file input */}
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    className="hidden"
                    onChange={handleFileChange}
                    accept="image/*,.pdf,.doc,.docx,.txt,.csv,.xlsx,.xls"
                />

                {/* Text Input */}
                <textarea
                    ref={textareaRef}
                    value={message}
                    onChange={handleChange}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    rows={1}
                    className="flex-1 border-0 bg-transparent shadow-none focus-visible:ring-0 focus:outline-none text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 h-auto px-2 text-sm py-2 resize-none min-h-[36px] max-h-[150px]"
                    style={{ caretColor: 'inherit' }}
                />

                {/* Voice Button */}
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'size-9 shrink-0 rounded-lg transition-all',
                        isRecording
                            ? 'bg-red-500 text-white hover:bg-red-600 animate-pulse'
                            : 'text-muted-foreground hover:text-foreground'
                    )}
                    onClick={toggleVoiceInput}
                    disabled={disabled}
                    type="button"
                    title={isRecording ? 'Stop recording' : 'Voice input'}
                >
                    <Mic className="size-4" />
                </Button>

                {/* Send Button */}
                <Button
                    variant={isStreaming ? 'ghost' : (message?.trim() || attachedFiles.length > 0 ? 'primary' : 'secondary')}
                    size="icon"
                    className={cn(
                        'size-9 shrink-0 rounded-xl transition-all',
                        isStreaming
                            ? 'bg-red-500 text-white hover:bg-red-600 opacity-100 shadow-md'
                            : (message?.trim() || attachedFiles.length > 0) ? 'opacity-100' : 'opacity-50'
                    )}
                    onClick={isStreaming ? handleStop : handleSend}
                    disabled={isStreaming ? false : (disabled || (!message?.trim() && attachedFiles.length === 0))}
                    type="button"
                >
                    {isStreaming ? <Square className="size-4 fill-current" /> : <Send className="size-4" />}
                </Button>
            </div>
        </div>
    );
}
