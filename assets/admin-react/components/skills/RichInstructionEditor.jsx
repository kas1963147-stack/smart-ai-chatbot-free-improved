/**
 * RichInstructionEditor Component
 *
 * TipTap-based rich text editor for skill instructions.
 * Exports to markdown for SKILL.md generation.
 */
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { IconButton } from '../ui';

export default function RichInstructionEditor({
	content = '',
	onChange,
	placeholder = 'Write your instructions here...',
	minHeight = '200px',
}) {
	const editor = useEditor({
		extensions: [
			StarterKit.configure({
				heading: {
					levels: [2, 3, 4],
				},
			}),
			Link.configure({
				openOnClick: false,
			}),
			Placeholder.configure({
				placeholder,
			}),
		],
		content: markdownToHTML(content),
		onUpdate: ({ editor: updatedEditor }) => {
			const markdown = htmlToMarkdown(updatedEditor.getHTML());
			onChange?.(markdown);
		},
	});

	// Update content when prop changes externally
	useEffect(() => {
		if (editor && content !== htmlToMarkdown(editor.getHTML())) {
			editor.commands.setContent(markdownToHTML(content));
		}
	}, [content, editor]);

	if (!editor) {
		return (
			<div className="flex items-center justify-center p-8 text-gray-500 dark:text-gray-400 bg-gray-50/50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl">
				<svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
					<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
					<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
				</svg>
				{__('Loading editor…', 'smart-woo-chatbot')}
			</div>
		);
	}

	return (
		<div className="border border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden bg-white dark:bg-gray-800 shadow-sm transition-colors focus-within:border-primary focus-within:ring-1 focus-within:ring-primary flex flex-col">
			{ /* Toolbar */}
			<div className="flex flex-wrap items-center gap-1.5 p-2 bg-gray-50/80 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
				<div className="flex items-center gap-1">
					<ToolbarButton
						onClick={() =>
							editor.chain().focus().toggleBold().run()
						}
						isActive={editor.isActive('bold')}
						title="Bold"
					>
						<strong>B</strong>
					</ToolbarButton>
					<ToolbarButton
						onClick={() =>
							editor.chain().focus().toggleItalic().run()
						}
						isActive={editor.isActive('italic')}
						title="Italic"
					>
						<em>I</em>
					</ToolbarButton>
					<ToolbarButton
						onClick={() =>
							editor.chain().focus().toggleCode().run()
						}
						isActive={editor.isActive('code')}
						title="Code"
					>
						<span className="font-mono text-[10px]">&lt;/&gt;</span>
					</ToolbarButton>
				</div>

				<div className="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1" />

				<div className="flex items-center gap-1">
					<ToolbarButton
						onClick={() =>
							editor
								.chain()
								.focus()
								.toggleHeading({ level: 2 })
								.run()
						}
						isActive={editor.isActive('heading', { level: 2 })}
						title="Heading 2"
					>
						H2
					</ToolbarButton>
					<ToolbarButton
						onClick={() =>
							editor
								.chain()
								.focus()
								.toggleHeading({ level: 3 })
								.run()
						}
						isActive={editor.isActive('heading', { level: 3 })}
						title="Heading 3"
					>
						H3
					</ToolbarButton>
				</div>

				<div className="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1" />

				<div className="flex items-center gap-1">
					<ToolbarButton
						onClick={() =>
							editor.chain().focus().toggleBulletList().run()
						}
						isActive={editor.isActive('bulletList')}
						title="Bullet List"
					>
						•
					</ToolbarButton>
					<ToolbarButton
						onClick={() =>
							editor.chain().focus().toggleOrderedList().run()
						}
						isActive={editor.isActive('orderedList')}
						title="Numbered List"
					>
						1.
					</ToolbarButton>
				</div>

				<div className="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1" />

				<div className="flex items-center gap-1">
					<ToolbarButton
						onClick={() =>
							editor.chain().focus().toggleBlockquote().run()
						}
						isActive={editor.isActive('blockquote')}
						title="Quote"
					>
						"
					</ToolbarButton>
					<ToolbarButton
						onClick={() =>
							editor.chain().focus().toggleCodeBlock().run()
						}
						isActive={editor.isActive('codeBlock')}
						title="Code Block"
					>
						<span className="font-mono text-[10px]">{'{ }'}</span>
					</ToolbarButton>
				</div>

				<div className="flex-1" />

				<div className="flex items-center gap-1">
					<ToolbarButton
						onClick={() => editor.chain().focus().undo().run()}
						disabled={!editor.can().undo()}
						title="Undo"
					>
						↩
					</ToolbarButton>
					<ToolbarButton
						onClick={() => editor.chain().focus().redo().run()}
						disabled={!editor.can().redo()}
						title="Redo"
					>
						↪
					</ToolbarButton>
				</div>
			</div>

			{ /* Editor content */}
			<div className="p-4 prose prose-sm max-w-none dark:prose-invert focus:outline-none flex-1 overflow-y-auto" style={{ minHeight }}>
				<EditorContent editor={editor} className="min-h-full outline-none" />
			</div>

			{ /* Character count */}
			<div className="flex items-center gap-4 px-4 py-2.5 bg-gray-50/80 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 font-medium">
				<span>
					{editor.storage.characterCount?.characters?.() ||
						content.length}{' '}
					{__('characters', 'smart-woo-chatbot')}
				</span>
				<span>
					~{Math.ceil(content.length / 4)}{' '}
					{__('tokens', 'smart-woo-chatbot')}
				</span>
			</div>
		</div>
	);
}

RichInstructionEditor.propTypes = {
	content: PropTypes.string,
	onChange: PropTypes.func.isRequired,
	placeholder: PropTypes.string,
	minHeight: PropTypes.string,
};

/**
 * Toolbar button component
 * @param root0
 * @param root0.children
 * @param root0.onClick
 * @param root0.isActive
 * @param root0.disabled
 * @param root0.title
 */
function ToolbarButton({ children, onClick, isActive, disabled, title }) {
	return (
		<button
			type="button"
			onClick={onClick}
			disabled={disabled}
			title={title}
			aria-label={title}
			className={`flex items-center justify-center w-8 h-8 rounded-md text-sm transition-colors focus:outline-none ${isActive
					? 'bg-primary/10 text-primary border border-primary/20 font-medium'
					: 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 border border-transparent'
				} ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
		>
			{children}
		</button>
	);
}

ToolbarButton.propTypes = {
	children: PropTypes.node,
	onClick: PropTypes.func.isRequired,
	isActive: PropTypes.bool,
	disabled: PropTypes.bool,
	title: PropTypes.string,
};

/**
 * Convert markdown to HTML (simple)
 * @param md
 */
function markdownToHTML(md) {
	if (!md) {
		return '';
	}

	return (
		md
			// Headers
			.replace(/^#### (.+)$/gm, '<h4>$1</h4>')
			.replace(/^### (.+)$/gm, '<h3>$1</h3>')
			.replace(/^## (.+)$/gm, '<h2>$1</h2>')
			// Bold
			.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
			// Italic
			.replace(/\*(.+?)\*/g, '<em>$1</em>')
			// Code
			.replace(/`(.+?)`/g, '<code>$1</code>')
			// Blockquote
			.replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>')
			// Bullet lists
			.replace(/^- (.+)$/gm, '<li>$1</li>')
			.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>')
			// Numbered lists
			.replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
			// Paragraphs (wrap remaining text)
			.replace(/\n\n/g, '</p><p>')
			.replace(/\n/g, '<br />')
	);
}

/**
 * Convert HTML to markdown
 * @param html
 */
function htmlToMarkdown(html) {
	if (!html) {
		return '';
	}

	return (
		html
			// Headers
			.replace(/<h2>(.*?)<\/h2>/gi, '## $1\n')
			.replace(/<h3>(.*?)<\/h3>/gi, '### $1\n')
			.replace(/<h4>(.*?)<\/h4>/gi, '#### $1\n')
			// Bold
			.replace(/<strong>(.*?)<\/strong>/gi, '**$1**')
			.replace(/<b>(.*?)<\/b>/gi, '**$1**')
			// Italic
			.replace(/<em>(.*?)<\/em>/gi, '*$1*')
			.replace(/<i>(.*?)<\/i>/gi, '*$1*')
			// Code
			.replace(/<code>(.*?)<\/code>/gi, '`$1`')
			// Blockquote
			.replace(/<blockquote>(.*?)<\/blockquote>/gi, '> $1\n')
			// Lists
			.replace(/<ul>(.*?)<\/ul>/gis, (match, content) => {
				return content.replace(/<li>(.*?)<\/li>/gi, '- $1\n');
			})
			.replace(/<ol>(.*?)<\/ol>/gis, (match, content) => {
				let counter = 0;
				return content.replace(/<li>(.*?)<\/li>/gi, () => {
					counter++;
					return `${counter}. $1\n`;
				});
			})
			// Line breaks
			.replace(/<br\s*\/?>/gi, '\n')
			// Paragraphs
			.replace(/<p>(.*?)<\/p>/gi, '$1\n\n')
			// Strip remaining tags
			.replace(/<[^>]+>/g, '')
			// Clean up
			.replace(/\n{3,}/g, '\n\n')
			.trim()
	);
}
