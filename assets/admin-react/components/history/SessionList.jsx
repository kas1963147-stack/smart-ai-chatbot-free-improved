/**
 * Session List - Metronic v9 Premium Style
 *
 * Displays a list of chat sessions with search and pagination.
 * Features modern card design, hover effects, and premium icons.
 */
import { __ } from '@wordpress/i18n';
import Loading from '../common/Loading';

// Icon components

const EyeIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
	</svg>
);

const TrashIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
	</svg>
);

const UserIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
	</svg>
);

const ChatBubbleIcon = () => (
	<svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
	</svg>
);

const ChevronLeftIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
	</svg>
);

const ChevronRightIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
	</svg>
);

export default function SessionList({
	sessions,
	loading,
	searchQuery,
	onSearchChange,
	onViewSession,
	onDeleteSession,
	page,
	totalPages,
	onPageChange,
}) {
	const formatDate = (dateStr) => {
		if (!dateStr) return '-';
		const date = new Date(dateStr);
		return (
			date.toLocaleDateString() +
			' ' +
			date.toLocaleTimeString([], {
				hour: '2-digit',
				minute: '2-digit',
			})
		);
	};

	const formatRelativeTime = (dateStr) => {
		if (!dateStr) return '-';
		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = now - date;
		const diffMins = Math.floor(diffMs / 60000);
		const diffHours = Math.floor(diffMs / 3600000);
		const diffDays = Math.floor(diffMs / 86400000);

		if (diffMins < 1) return __('Just now', 'agentflow-ai');
		if (diffMins < 60) return `${diffMins}m ago`;
		if (diffHours < 24) return `${diffHours}h ago`;
		if (diffDays < 7) return `${diffDays}d ago`;
		return formatDate(dateStr);
	};

	return (
		<div className="overflow-hidden">
			{/* Toolbar */}
			<div className="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
				<div className="flex items-center gap-4">
					<div className="flex-1">
						<input
							type="text"
							placeholder={__('Search conversations by content...', 'agentflow-ai')}
							value={searchQuery}
							onChange={(e) => onSearchChange(e.target.value)}
							className="w-full h-11 px-4 text-sm rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-sm transition-all"
						/>
					</div>
					<div className="text-sm text-slate-500 dark:text-slate-400">
						{sessions.length > 0 && (
							<span className="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 rounded-lg font-medium">
								{sessions.length} {__('sessions', 'agentflow-ai')}
							</span>
						)}
					</div>
				</div>
			</div>

			{/* Loading */}
			{loading && (
				<Loading message={__('Loading conversations...', 'agentflow-ai')} fullPage />
			)}

			{/* Empty State */}
			{!loading && sessions.length === 0 && (
				<div className="flex flex-col items-center justify-center py-20">
					<div className="flex items-center justify-center w-20 h-20 rounded-3xl bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 mb-6">
						<ChatBubbleIcon />
					</div>
					<h3 className="text-xl font-semibold text-slate-900 dark:text-white mb-2">
						{__('No conversations yet', 'agentflow-ai')}
					</h3>
					<p className="text-slate-500 dark:text-slate-400 text-center max-w-md">
						{__('Chat history will appear here once users start conversations with your AI agents.', 'agentflow-ai')}
					</p>
				</div>
			)}

			{/* Sessions Grid */}
			{!loading && sessions.length > 0 && (
				<>
					<div className="divide-y divide-slate-100 dark:divide-slate-700">
						{sessions.map((session) => (
							<div
								key={session.session_id}
								className="group relative px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all duration-200 cursor-pointer"
								onClick={() => onViewSession(session)}
							>
								<div className="flex items-start gap-4">
									{/* Avatar */}
									<div className="flex-shrink-0">
										<div className="flex items-center justify-center w-12 h-12 rounded-2xl bg-primary text-white">
											<UserIcon />
										</div>
									</div>

									{/* Content */}
									<div className="flex-1 min-w-0">
										<div className="flex items-start justify-between gap-4">
											<div className="flex-1 min-w-0">
												{/* Preview */}
												<p className="text-sm font-medium text-slate-900 dark:text-white truncate group-hover:text-primary transition-colors">
													{session.preview || __('Empty conversation', 'agentflow-ai')}
												</p>
												{/* Meta */}
												<div className="flex items-center gap-3 mt-1.5">
													<span className="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-lg bg-primary/10 dark:bg-primary/20 text-primary border border-primary/20 dark:border-primary/30">
														{session.agent_name}
													</span>
													<span className="text-xs text-slate-400 dark:text-slate-500">
														{formatRelativeTime(session.last_message_at)}
													</span>
												</div>
											</div>

											{/* Stats */}
											<div className="flex items-center gap-4 text-sm">
												<div className="flex flex-col items-end">
													<span className="text-xs text-slate-400 dark:text-slate-500">{__('Messages', 'agentflow-ai')}</span>
													<span className="font-semibold text-slate-700 dark:text-slate-300">{session.message_count}</span>
												</div>
												{session.action_count > 0 && (
													<div className="flex flex-col items-end">
														<span className="text-xs text-slate-400 dark:text-slate-500">{__('Tools', 'agentflow-ai')}</span>
														<span className="font-semibold text-primary">{session.action_count}</span>
													</div>
												)}
											</div>
										</div>

										{/* Timestamps */}
										<div className="flex items-center gap-4 mt-2 text-xs text-slate-400 dark:text-slate-500">
											<span>{__('Started:', 'agentflow-ai')} {formatDate(session.started_at)}</span>
										</div>
									</div>

									{/* Actions */}
									<div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
										<button
											onClick={(e) => {
												e.stopPropagation();
												onViewSession(session);
											}}
											className="inline-flex items-center justify-center w-9 h-9 rounded-xl text-slate-400 dark:text-slate-500 hover:text-primary hover:bg-primary/10 transition-all"
											title={__('View', 'agentflow-ai')}
										>
											<EyeIcon />
										</button>
										<button
											onClick={(e) => {
												e.stopPropagation();
												onDeleteSession(session.session_id);
											}}
											className="inline-flex items-center justify-center w-9 h-9 rounded-xl text-slate-400 dark:text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-all"
											title={__('Delete', 'agentflow-ai')}
										>
											<TrashIcon />
										</button>
									</div>
								</div>
							</div>
						))}
					</div>

					{/* Pagination */}
					{totalPages > 1 && (
						<div className="flex items-center justify-between px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
							<button
								disabled={page <= 1}
								onClick={() => onPageChange(page - 1)}
								className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 hover:border-slate-300 dark:hover:border-slate-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all"
							>
								<ChevronLeftIcon />
								{__('Previous', 'agentflow-ai')}
							</button>

							<div className="flex items-center gap-2">
								{Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
									let pageNum;
									if (totalPages <= 5) {
										pageNum = i + 1;
									} else if (page <= 3) {
										pageNum = i + 1;
									} else if (page >= totalPages - 2) {
										pageNum = totalPages - 4 + i;
									} else {
										pageNum = page - 2 + i;
									}
									return (
										<button
											key={pageNum}
											onClick={() => onPageChange(pageNum)}
											className={`w-10 h-10 text-sm font-medium rounded-xl transition-all ${page === pageNum
												? 'bg-primary text-white shadow-md'
												: 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'
												}`}
										>
											{pageNum}
										</button>
									);
								})}
							</div>

							<button
								disabled={page >= totalPages}
								onClick={() => onPageChange(page + 1)}
								className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 hover:border-slate-300 dark:hover:border-slate-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all"
							>
								{__('Next', 'agentflow-ai')}
								<ChevronRightIcon />
							</button>
						</div>
					)}
				</>
			)}
		</div>
	);
}
