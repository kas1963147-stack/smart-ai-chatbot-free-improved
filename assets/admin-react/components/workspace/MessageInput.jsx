/**
 * Message Input - Pure Tailwind CSS
 *
 * Multi-line input with:
 * - File/image attachments with preview
 * - Voice input (Web Speech API)
 * - Keyboard shortcuts (Cmd+Enter, Cmd+K)
 * - Slash command suggestions
 * - Dark mode support
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Paperclip, Mic, Send, X, Loader2, Check, AlertCircle } from 'lucide-react';
import { cn } from '../../ui/utils';

// Slash commands for autocomplete
const SLASH_COMMANDS = [
	{ command: '/create', label: 'Create page or post', icon: '' },
	{ command: '/edit', label: 'Edit existing content', icon: '' },
	{ command: '/search', label: 'Search site content', icon: '' },
	{ command: '/product', label: 'Manage products', icon: '' },
	{ command: '/order', label: 'Lookup orders', icon: '' },
	{ command: '/settings', label: 'Update settings', icon: '' },
	{ command: '/help', label: 'Show available commands', icon: '' },
];

/**
 * MessageInput Component - Pure Tailwind
 */
export default function MessageInput({ onSend, disabled, placeholder, onAttachment, darkMode }) {
	const [value, setValue] = useState('');
	const [attachments, setAttachments] = useState([]);
	const [isRecording, setIsRecording] = useState(false);
	const [showCommands, setShowCommands] = useState(false);
	const [filteredCommands, setFilteredCommands] = useState([]);
	const [selectedCommandIndex, setSelectedCommandIndex] = useState(0);
	const [showShortcuts, setShowShortcuts] = useState(false);
	const [isDragOver, setIsDragOver] = useState(false);
	const [voiceSupported, setVoiceSupported] = useState(false);

	const textareaRef = useRef(null);
	const fileInputRef = useRef(null);
	const recognitionRef = useRef(null);

	// Initialize speech recognition
	useEffect(() => {
		// Check if we're on HTTPS (required for Speech Recognition, except localhost)
		const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
		console.log('Protocol:', window.location.protocol, 'Is secure context:', isSecure);

		const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
		console.log('Speech Recognition API available:', !!SpeechRecognition);

		if (!isSecure) {
			console.warn('Voice input requires HTTPS. Current protocol:', window.location.protocol);
			setVoiceSupported(false);
			return;
		}

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
					console.log('Voice transcript:', transcript);
					setValue(transcript);
				};

				recognitionRef.current.onend = () => {
					console.log('Voice recognition ended');
					setIsRecording(false);
				};

				recognitionRef.current.onerror = (event) => {
					console.error('Speech recognition error:', event.error);
					setIsRecording(false);
					// Show user friendly error
					if (event.error === 'not-allowed') {
						alert(__('Microphone access denied. Please allow microphone access in browser settings.', 'smart-woo-chatbot'));
					} else if (event.error === 'no-speech') {
						// User didn't speak - this is normal, don't show error
					} else if (event.error === 'network') {
						alert(__('Network error. Please check your internet connection.', 'smart-woo-chatbot'));
					} else if (event.error === 'service-not-allowed') {
						alert(__('Voice input requires HTTPS. Please access this site over HTTPS.', 'smart-woo-chatbot'));
					}
				};

				recognitionRef.current.onstart = () => {
					console.log('Voice recognition started');
				};

				setVoiceSupported(true);
				console.log('Voice recognition initialized successfully');
			} catch (err) {
				console.warn('Speech recognition not available:', err);
				setVoiceSupported(false);
			}
		} else {
			console.log('Speech Recognition API not available in this browser');
			setVoiceSupported(false);
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
	}, []);

	// Handle slash command filtering
	useEffect(() => {
		if (value.startsWith('/')) {
			const query = value.toLowerCase();
			const filtered = SLASH_COMMANDS.filter((cmd) =>
				cmd.command.toLowerCase().startsWith(query)
			);
			setFilteredCommands(filtered);
			setShowCommands(filtered.length > 0);
			setSelectedCommandIndex(0);
		} else {
			setShowCommands(false);
		}
	}, [value]);

	// Check if any attachments are still uploading
	const hasUploadingAttachments = attachments.some((att) => att.uploading);

	const handleSubmit = useCallback(() => {
		if (hasUploadingAttachments) {
			return;
		}
		const message = value.trim();
		if ((message || attachments.length > 0) && !disabled) {
			// Stop voice input if active
			if (isRecording && recognitionRef.current) {
				try {
					recognitionRef.current.stop();
				} catch (err) {
					// ignore
				}
				setIsRecording(false);
			}

			// Clear input first
			setValue('');
			setAttachments([]);

			if (textareaRef.current) {
				textareaRef.current.style.height = 'auto';
				textareaRef.current.focus();
			}

			// Then send the message
			onSend(message, attachments);
		}
	}, [value, attachments, disabled, onSend, hasUploadingAttachments, isRecording]);

	const handleKeyDown = useCallback(
		(e) => {
			// Command palette (Cmd+K)
			if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
				e.preventDefault();
				setValue('/');
				setShowCommands(true);
				return;
			}

			// Show shortcuts help (Cmd+/)
			if ((e.metaKey || e.ctrlKey) && e.key === '/') {
				e.preventDefault();
				setShowShortcuts((prev) => !prev);
				return;
			}

			// Handle command selection
			if (showCommands) {
				if (e.key === 'ArrowDown') {
					e.preventDefault();
					setSelectedCommandIndex((prev) =>
						Math.min(prev + 1, filteredCommands.length - 1)
					);
					return;
				}
				if (e.key === 'ArrowUp') {
					e.preventDefault();
					setSelectedCommandIndex((prev) => Math.max(prev - 1, 0));
					return;
				}
				if (e.key === 'Tab' || (e.key === 'Enter' && filteredCommands.length > 0)) {
					e.preventDefault();
					const selected = filteredCommands[selectedCommandIndex];
					if (selected) {
						setValue(selected.command + ' ');
						setShowCommands(false);
					}
					return;
				}
				if (e.key === 'Escape') {
					setShowCommands(false);
					return;
				}
			}

			// Enter to send (Shift+Enter for new line)
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				handleSubmit();
			}
		},
		[handleSubmit, showCommands, filteredCommands, selectedCommandIndex]
	);

	// Auto-resize textarea
	const handleChange = useCallback((e) => {
		setValue(e.target.value);
		const textarea = e.target;
		textarea.style.height = 'auto';
		textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
	}, []);

	// File handling
	const handleFileSelect = useCallback(async (e) => {
		const files = Array.from(e.target.files);

		for (const file of files) {
			const attachment = {
				file,
				name: file.name,
				type: file.type,
				size: file.size,
				preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
				uploading: true,
				uploaded: false,
			};

			setAttachments((prev) => [...prev, attachment]);

			if (onAttachment) {
				try {
					const uploadResult = await onAttachment(attachment);
					setAttachments((prev) =>
						prev.map((att) =>
							att.name === file.name && att.uploading
								? {
									...att,
									uploading: false,
									uploaded: true,
									...uploadResult,
								}
								: att
						)
					);
				} catch (err) {
					setAttachments((prev) =>
						prev.map((att) =>
							att.name === file.name && att.uploading
								? { ...att, uploading: false, uploaded: false, error: true }
								: att
						)
					);
				}
			}
		}

		e.target.value = '';
	}, [onAttachment]);

	const removeAttachment = useCallback((index) => {
		setAttachments((prev) => {
			const updated = [...prev];
			if (updated[index].preview) {
				URL.revokeObjectURL(updated[index].preview);
			}
			updated.splice(index, 1);
			return updated;
		});
	}, []);

	// Voice input
	const toggleVoiceInput = useCallback(async () => {
		console.log('Voice button clicked, voiceSupported:', voiceSupported, 'recognitionRef:', !!recognitionRef.current);

		if (!recognitionRef.current) {
			alert(__('Voice input is not supported in this browser. Please use Chrome or Edge.', 'smart-woo-chatbot'));
			return;
		}

		if (isRecording) {
			console.log('Stopping recording...');
			try {
				recognitionRef.current.stop();
			} catch (e) {
				console.error('Error stopping recognition:', e);
			}
			setIsRecording(false);
		} else {
			console.log('Starting recording...');
			try {
				// Start recognition directly - browser will prompt for permission if needed
				recognitionRef.current.start();
				setIsRecording(true);
				console.log('Recording state set to true');
			} catch (err) {
				console.error('Error starting recognition:', err);
				setIsRecording(false);

				if (err.message?.includes('already started')) {
					// Already recording, try to stop
					try {
						recognitionRef.current.stop();
					} catch (e) {
						// ignore
					}
				} else {
					alert(__('Could not start voice input. Error: ', 'smart-woo-chatbot') + err.message);
				}
			}
		}
	}, [isRecording, voiceSupported]);


	// Drag and drop
	const handleDragOver = useCallback((e) => {
		e.preventDefault();
		setIsDragOver(true);
	}, []);

	const handleDragLeave = useCallback((e) => {
		setIsDragOver(false);
	}, []);

	const handleDrop = useCallback((e) => {
		e.preventDefault();
		setIsDragOver(false);
		const files = Array.from(e.dataTransfer.files);
		const newAttachments = files.map((file) => ({
			file,
			name: file.name,
			type: file.type,
			size: file.size,
			preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
		}));
		setAttachments((prev) => [...prev, ...newAttachments]);
	}, []);

	const selectCommand = useCallback((cmd) => {
		setValue(cmd.command + ' ');
		setShowCommands(false);
		textareaRef.current?.focus();
	}, []);

	return (
		<div className="relative">
			{/* Attachment Previews */}
			{attachments.length > 0 && (
				<div className="flex flex-wrap gap-2 mb-3">
					{attachments.map((att, index) => (
						<div
							key={index}
							className={cn(
								'relative flex items-center gap-2 px-3 py-2 rounded-lg text-sm',
								'border transition-colors',
								att.error
									? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
									: att.uploaded
										? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
										: 'bg-gray-50 dark:bg-slate-800 border-gray-200 dark:border-slate-700'
							)}
						>
							{att.preview ? (
								<img src={att.preview} alt={att.name} className="w-8 h-8 rounded object-cover" />
							) : (
								<span className="text-lg"></span>
							)}
							<span className="text-gray-700 dark:text-slate-300 max-w-[120px] truncate">{att.name}</span>

							{/* Status indicator */}
							{att.uploading && <Loader2 className="w-4 h-4 text-indigo-500 animate-spin" />}
							{att.uploaded && <Check className="w-4 h-4 text-green-500" />}
							{att.error && <AlertCircle className="w-4 h-4 text-red-500" />}

							<button
								className="w-5 h-5 rounded-full bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 flex items-center justify-center text-gray-500 dark:text-slate-400 transition-colors"
								onClick={() => removeAttachment(index)}
							>
								<X className="w-3 h-3" />
							</button>
						</div>
					))}
				</div>
			)}

			{/* Command Suggestions */}
			{showCommands && (
				<div className="absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-xl overflow-hidden z-10">
					{filteredCommands.map((cmd, index) => (
						<button
							key={cmd.command}
							className={cn(
								'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors',
								index === selectedCommandIndex
									? 'bg-indigo-50 dark:bg-indigo-500/20'
									: 'hover:bg-gray-50 dark:hover:bg-slate-700'
							)}
							onClick={() => selectCommand(cmd)}
						>
							<span className="text-xl">{cmd.icon}</span>
							<span className="flex flex-col">
								<strong className="text-sm font-medium text-gray-900 dark:text-white">{cmd.command}</strong>
								<small className="text-xs text-gray-500 dark:text-slate-400">{cmd.label}</small>
							</span>
						</button>
					))}
				</div>
			)}

			{/* Keyboard Shortcuts Help */}
			{showShortcuts && (
				<div className="absolute bottom-full left-0 right-0 mb-2 p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-xl z-10">
					<div className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
						{__('Keyboard Shortcuts', 'smart-woo-chatbot')}
					</div>
					<div className="space-y-2 text-sm text-gray-600 dark:text-slate-400">
						<div className="flex items-center gap-2">
							<kbd className="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs">Enter</kbd>
							{__('Send message', 'smart-woo-chatbot')}
						</div>
						<div className="flex items-center gap-2">
							<kbd className="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs">Shift</kbd>+
							<kbd className="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs">Enter</kbd>
							{__('New line', 'smart-woo-chatbot')}
						</div>
						<div className="flex items-center gap-2">
							<kbd className="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs"></kbd>+
							<kbd className="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs">K</kbd>
							{__('Command palette', 'smart-woo-chatbot')}
						</div>
						<div className="flex items-center gap-2">
							<kbd className="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs"></kbd>+
							<kbd className="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs">/</kbd>
							{__('Toggle shortcuts', 'smart-woo-chatbot')}
						</div>
					</div>
				</div>
			)}

			{/* Input Box */}
			<div
				className={cn(
					'flex items-end gap-2 p-3 rounded-2xl border transition-all',
					'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700',
					'shadow-lg focus-within:ring-2 focus-within:ring-indigo-500/30 focus-within:border-indigo-500',
					isDragOver && 'ring-2 ring-indigo-500 border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10'
				)}
				onDragOver={handleDragOver}
				onDragLeave={handleDragLeave}
				onDrop={handleDrop}
			>
				{/* Attachment Button */}
				<button
					className="w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors disabled:opacity-50"
					onClick={() => fileInputRef.current?.click()}
					title={__('Attach file', 'smart-woo-chatbot')}
					disabled={disabled}
				>
					<Paperclip className="w-5 h-5" />
				</button>
				<input
					ref={fileInputRef}
					type="file"
					multiple
					onChange={handleFileSelect}
					style={{ display: 'none' }}
				/>

				{/* Textarea */}
				<textarea
					ref={textareaRef}
					className="flex-1 min-h-[24px] max-h-[200px] py-2 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 text-sm resize-none focus:outline-none"
					style={{ caretColor: darkMode ? '#fff' : '#000' }}
					value={value}
					onChange={handleChange}
					onKeyDown={handleKeyDown}
					placeholder={placeholder}
					disabled={disabled}
					rows={1}
				/>

				{/* Voice Input Button */}
				<button
					className={cn(
						'w-9 h-9 flex items-center justify-center rounded-lg transition-colors disabled:opacity-50',
						isRecording
							? 'bg-red-500 text-white animate-pulse'
							: 'text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700'
					)}
					onClick={toggleVoiceInput}
					title={isRecording ? __('Stop recording', 'smart-woo-chatbot') : __('Voice input', 'smart-woo-chatbot')}
					disabled={disabled}
					type="button"
				>
					<Mic className="w-5 h-5" />
				</button>

				{/* Send Button */}
				<button
					className={cn(
						'w-9 h-9 flex items-center justify-center rounded-xl transition-all',
						disabled || hasUploadingAttachments || (!value.trim() && attachments.length === 0)
							? 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500 cursor-not-allowed'
							: 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm'
					)}
					onClick={handleSubmit}
					disabled={disabled || hasUploadingAttachments || (!value.trim() && attachments.length === 0)}
					title={hasUploadingAttachments ? __('Waiting for upload...', 'smart-woo-chatbot') : __('Send message', 'smart-woo-chatbot')}
				>
					{disabled || hasUploadingAttachments ? (
						<Loader2 className="w-4 h-4 animate-spin" />
					) : (
						<Send className="w-4 h-4" />
					)}
				</button>
			</div>

			{/* Helper text */}
			<div className="flex items-center justify-center gap-3 mt-2 text-xs text-gray-400 dark:text-slate-500">
				<span className="flex items-center gap-1">
					{__('Press', 'smart-woo-chatbot')}
					<kbd className="px-1.5 py-0.5 bg-gray-100 dark:bg-slate-800 rounded text-[10px]">/</kbd>
					{__('for commands', 'smart-woo-chatbot')}
				</span>
				<span className="flex items-center gap-1">
					{__('or', 'smart-woo-chatbot')}
					<kbd className="px-1.5 py-0.5 bg-gray-100 dark:bg-slate-800 rounded text-[10px]">K</kbd>
				</span>
			</div>
		</div>
	);
}

MessageInput.propTypes = {
	onSend: PropTypes.func.isRequired,
	onAttachment: PropTypes.func,
	disabled: PropTypes.bool,
	placeholder: PropTypes.string,
	darkMode: PropTypes.bool,
};

MessageInput.defaultProps = {
	disabled: false,
	placeholder: 'Type your message...',
	onAttachment: null,
	darkMode: false,
};
