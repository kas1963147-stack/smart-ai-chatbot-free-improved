/**
 * Conversation List - Pure Tailwind CSS with Light/Dark Mode
 *
 * Lists past conversations with:
 * - Search/filter conversations
 * - Pin/unpin conversations
 * - Inline rename (click-to-edit)
 * - Export conversation
 * - Color-coded tags/labels
 */
import { useState, useCallback, useMemo, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Pin, Pencil, Tag, FileText, Copy, Trash2, MoreVertical, MessageSquare, X, Search } from 'lucide-react';
import { cn } from '../../ui/utils';

// Available tag colors
const TAG_COLORS = [
	{ id: 'red', color: '#ef4444', label: 'Red' },
	{ id: 'orange', color: '#f97316', label: 'Orange' },
	{ id: 'yellow', color: '#eab308', label: 'Yellow' },
	{ id: 'green', color: '#22c55e', label: 'Green' },
	{ id: 'blue', color: '#3b82f6', label: 'Blue' },
	{ id: 'purple', color: '#a855f7', label: 'Purple' },
];

/**
 * Conversation Item Component
 */
function ConversationItem({
	conv,
	isActive,
	onSelect,
	onDelete,
	onPin,
	onRename,
	onExport,
	onTagChange,
	darkMode,
}) {
	const [isEditing, setIsEditing] = useState(false);
	const [editValue, setEditValue] = useState(conv.title || '');
	const [showMenu, setShowMenu] = useState(false);
	const [showTagPicker, setShowTagPicker] = useState(false);
	const inputRef = useRef(null);
	const menuRef = useRef(null);

	// Focus input when editing starts
	useEffect(() => {
		if (isEditing && inputRef.current) {
			inputRef.current.focus();
			inputRef.current.select();
		}
	}, [isEditing]);

	// Close menu on outside click
	useEffect(() => {
		const handleClickOutside = (e) => {
			if (menuRef.current && !menuRef.current.contains(e.target)) {
				setShowMenu(false);
				setShowTagPicker(false);
			}
		};
		if (showMenu || showTagPicker) {
			document.addEventListener('mousedown', handleClickOutside);
			return () => document.removeEventListener('mousedown', handleClickOutside);
		}
	}, [showMenu, showTagPicker]);

	const handleStartEdit = useCallback((e) => {
		e.stopPropagation();
		setEditValue(conv.title || '');
		setIsEditing(true);
		setShowMenu(false);
	}, [conv.title]);

	const handleSaveEdit = useCallback(() => {
		if (editValue.trim() && editValue !== conv.title) {
			onRename(conv.id, editValue.trim());
		}
		setIsEditing(false);
	}, [editValue, conv.id, conv.title, onRename]);

	const handleKeyDown = useCallback((e) => {
		if (e.key === 'Enter') {
			handleSaveEdit();
		} else if (e.key === 'Escape') {
			setIsEditing(false);
		}
	}, [handleSaveEdit]);

	const handleExport = useCallback((format) => {
		onExport(conv.id, format);
		setShowMenu(false);
	}, [conv.id, onExport]);

	const handleTagSelect = useCallback((tagId) => {
		onTagChange(conv.id, tagId === conv.tag ? null : tagId);
		setShowTagPicker(false);
		setShowMenu(false);
	}, [conv.id, conv.tag, onTagChange]);

	const currentTag = TAG_COLORS.find((t) => t.id === conv.tag);

	return (
		<li
			className={cn(
				'relative flex items-center mb-1 rounded-lg transition-all duration-150 group',
				isActive
					? 'bg-indigo-50 dark:bg-slate-800 border-l-[3px] border-indigo-500'
					: 'hover:bg-gray-100 dark:hover:bg-slate-900/50'
			)}
		>
			{/* Tag indicator */}
			{currentTag && (
				<span
					className="absolute left-0 top-0 bottom-0 w-1 rounded-l-lg"
					style={{ backgroundColor: currentTag.color }}
				/>
			)}

			{isEditing ? (
				<input
					ref={inputRef}
					type="text"
					className="flex-1 px-3 py-2 mx-1 text-sm bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
					value={editValue}
					onChange={(e) => setEditValue(e.target.value)}
					onBlur={handleSaveEdit}
					onKeyDown={handleKeyDown}
					onClick={(e) => e.stopPropagation()}
				/>
			) : (
				<button
					className="flex-1 flex flex-col items-start py-3 px-3 text-left bg-transparent border-none cursor-pointer min-w-0"
					onClick={() => onSelect(conv.id)}
					onDoubleClick={handleStartEdit}
				>
					<span className="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[180px] flex items-center gap-1">
						{conv.isPinned && <Pin className="w-3 h-3 inline-block text-indigo-500 dark:text-indigo-400" />}
						{conv.title || __('Untitled', 'smart-woo-chatbot')}
					</span>
					<span className="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
						{conv.message_count || 0} {__('messages', 'smart-woo-chatbot')}
					</span>
				</button>
			)}

			{/* Context Menu Button */}
			<button
				className="w-7 h-7 mr-2 rounded-md flex items-center justify-center text-gray-400 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-slate-600 opacity-0 group-hover:opacity-100 transition-all duration-150"
				onClick={(e) => {
					e.stopPropagation();
					setShowMenu(!showMenu);
				}}
			>
				<MoreVertical className="w-4 h-4" />
			</button>

			{/* Context Menu */}
			{showMenu && (
				<div
					className="absolute top-full right-2 z-50 min-w-[180px] p-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-xl"
					ref={menuRef}
				>
					<button
						className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white rounded transition-colors"
						onClick={() => { onPin(conv.id); setShowMenu(false); }}
					>
						<Pin className="w-3.5 h-3.5" />
						{conv.isPinned ? __('Unpin', 'smart-woo-chatbot') : __('Pin', 'smart-woo-chatbot')}
					</button>
					<button
						className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white rounded transition-colors"
						onClick={handleStartEdit}
					>
						<Pencil className="w-3.5 h-3.5" />
						{__('Rename', 'smart-woo-chatbot')}
					</button>
					<button
						className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white rounded transition-colors"
						onClick={() => setShowTagPicker(!showTagPicker)}
					>
						<Tag className="w-3.5 h-3.5" />
						{__('Tag', 'smart-woo-chatbot')} →
					</button>
					<div className="my-1 border-t border-gray-200 dark:border-slate-700" />
					<button
						className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white rounded transition-colors"
						onClick={() => handleExport('markdown')}
					>
						<FileText className="w-3.5 h-3.5" />
						{__('Export Markdown', 'smart-woo-chatbot')}
					</button>
					<button
						className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white rounded transition-colors"
						onClick={() => handleExport('json')}
					>
						<Copy className="w-3.5 h-3.5" />
						{__('Export JSON', 'smart-woo-chatbot')}
					</button>
					<div className="my-1 border-t border-gray-200 dark:border-slate-700" />
					<button
						className="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/20 hover:text-red-700 dark:hover:text-red-300 rounded transition-colors"
						onClick={(e) => {
							e.stopPropagation();
							if (confirm(__('Delete this conversation?', 'smart-woo-chatbot'))) {
								onDelete(conv.id);
							}
							setShowMenu(false);
						}}
					>
						<Trash2 className="w-3.5 h-3.5" />
						{__('Delete', 'smart-woo-chatbot')}
					</button>

					{/* Tag Picker Submenu */}
					{showTagPicker && (
						<div className="mt-2 pt-2 border-t border-gray-200 dark:border-slate-700">
							<div className="grid grid-cols-3 gap-1">
								{TAG_COLORS.map((tag) => (
									<button
										key={tag.id}
										className={cn(
											'flex items-center gap-1 px-2 py-1.5 text-xs rounded transition-colors',
											conv.tag === tag.id
												? 'bg-gray-200 dark:bg-slate-600 text-gray-900 dark:text-white'
												: 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white'
										)}
										onClick={() => handleTagSelect(tag.id)}
									>
										<span
											className="w-2.5 h-2.5 rounded-full"
											style={{ backgroundColor: tag.color }}
										/>
										{tag.label}
									</button>
								))}
							</div>
							{conv.tag && (
								<button
									className="w-full flex items-center gap-1 px-2 py-1.5 mt-1 text-xs text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-700 dark:hover:text-white rounded transition-colors"
									onClick={() => handleTagSelect(null)}
								>
									<X className="w-3 h-3" />
									{__('Remove tag', 'smart-woo-chatbot')}
								</button>
							)}
						</div>
					)}
				</div>
			)}
		</li>
	);
}

ConversationItem.propTypes = {
	conv: PropTypes.object.isRequired,
	isActive: PropTypes.bool,
	onSelect: PropTypes.func.isRequired,
	onDelete: PropTypes.func.isRequired,
	onPin: PropTypes.func.isRequired,
	onRename: PropTypes.func.isRequired,
	onExport: PropTypes.func.isRequired,
	onTagChange: PropTypes.func.isRequired,
	darkMode: PropTypes.bool,
};

/**
 * ConversationList Component
 */
export default function ConversationList({
	conversations,
	activeId,
	onSelect,
	onDelete,
	onPin,
	onRename,
	onExport,
	onTagChange,
	isLoading,
	darkMode,
}) {
	const [searchQuery, setSearchQuery] = useState('');

	// Filter and sort conversations
	const { pinned, regular } = useMemo(() => {
		const filtered = conversations.filter((conv) =>
			!searchQuery ||
			(conv.title || '').toLowerCase().includes(searchQuery.toLowerCase())
		);

		return {
			pinned: filtered.filter((c) => c.isPinned),
			regular: filtered.filter((c) => !c.isPinned),
		};
	}, [conversations, searchQuery]);

	if (isLoading) {
		return (
			<div className="flex flex-col items-center justify-center py-8 px-4 text-gray-400 dark:text-slate-400">
				<MessageSquare className="w-8 h-8 mb-2 animate-pulse" />
				<span className="text-sm">{__('Loading...', 'smart-woo-chatbot')}</span>
			</div>
		);
	}

	return (
		<div className="flex flex-col h-full">
			{/* Search */}
			<div className="p-3 border-b border-gray-200 dark:border-slate-800">
				<input
					type="text"
					value={searchQuery}
					onChange={(e) => setSearchQuery(e.target.value)}
					placeholder={__('Search conversations...', 'smart-woo-chatbot')}
					className="w-full h-9 px-3 text-sm bg-white dark:bg-black/30 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500"
				/>
			</div>

			{/* Conversations */}
			<div className="flex-1 overflow-y-auto px-2 py-2">
				{/* Pinned Section */}
				{pinned.length > 0 && (
					<div className="mb-3">
						<div className="flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-gray-500 dark:text-slate-500 uppercase tracking-wider">
							<Pin className="w-3 h-3" />
							{__('Pinned', 'smart-woo-chatbot')}
						</div>
						<ul className="space-y-0.5">
							{pinned.map((conv) => (
								<ConversationItem
									key={conv.id}
									conv={conv}
									isActive={conv.id === activeId}
									onSelect={onSelect}
									onDelete={onDelete}
									onPin={onPin}
									onRename={onRename}
									onExport={onExport}
									onTagChange={onTagChange}
									darkMode={darkMode}
								/>
							))}
						</ul>
					</div>
				)}

				{/* Regular Conversations */}
				{regular.length > 0 && (
					<div>
						<div className="flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-gray-500 dark:text-slate-500 uppercase tracking-wider">
							<MessageSquare className="w-3 h-3" />
							{__('Conversations', 'smart-woo-chatbot')}
						</div>
						<ul className="space-y-0.5">
							{regular.map((conv) => (
								<ConversationItem
									key={conv.id}
									conv={conv}
									isActive={conv.id === activeId}
									onSelect={onSelect}
									onDelete={onDelete}
									onPin={onPin}
									onRename={onRename}
									onExport={onExport}
									onTagChange={onTagChange}
									darkMode={darkMode}
								/>
							))}
						</ul>
					</div>
				)}

				{/* Empty State */}
				{pinned.length === 0 && regular.length === 0 && (
					<div className="flex flex-col items-center justify-center py-8 px-4 text-center">
						<MessageSquare className="w-10 h-10 mb-3 text-gray-300 dark:text-slate-600" />
						<p className="text-sm text-gray-500 dark:text-slate-400">
							{searchQuery
								? __('No conversations match your search.', 'smart-woo-chatbot')
								: __('No conversations yet. Start a new chat!', 'smart-woo-chatbot')}
						</p>
					</div>
				)}
			</div>
		</div>
	);
}

ConversationList.propTypes = {
	conversations: PropTypes.arrayOf(
		PropTypes.shape({
			id: PropTypes.string.isRequired,
			title: PropTypes.string,
			message_count: PropTypes.number,
			updated_at: PropTypes.string,
			isPinned: PropTypes.bool,
			tag: PropTypes.string,
		})
	).isRequired,
	activeId: PropTypes.string,
	onSelect: PropTypes.func.isRequired,
	onDelete: PropTypes.func.isRequired,
	onPin: PropTypes.func,
	onRename: PropTypes.func,
	onExport: PropTypes.func,
	onTagChange: PropTypes.func,
	isLoading: PropTypes.bool,
	darkMode: PropTypes.bool,
};

ConversationList.defaultProps = {
	activeId: null,
	isLoading: false,
	darkMode: false,
	onPin: () => { },
	onRename: () => { },
	onExport: () => { },
	onTagChange: () => { },
};
