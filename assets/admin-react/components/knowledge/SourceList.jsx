/**
 * SourceList Component - Metronic v9 Style
 *
 * Displays knowledge sources as modern cards.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

export default function SourceList({ sources, onEdit, onDelete, onSync }) {
	// Empty state
	if (!sources || sources.length === 0) {
		return (
			<div className="text-center py-16 border-2 border-dashed border-gray-200 dark:border-slate-600 rounded-xl bg-gray-50/50 dark:bg-slate-800/50">
				<div className="text-5xl mb-4"></div>
				<h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
					{__('No knowledge sources yet', 'agentflow-ai')}
				</h3>
				<p className="text-gray-500 dark:text-slate-400 max-w-md mx-auto">
					{__('Add your first source to start building your knowledge base.', 'agentflow-ai')}
				</p>
			</div>
		);
	}

	const getStatusBadge = (status) => {
		const configs = {
			active: { label: __('Active', 'agentflow-ai'), class: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' },
			indexing: { label: __('Syncing...', 'agentflow-ai'), class: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400' },
			error: { label: __('Error', 'agentflow-ai'), class: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' },
			disabled: { label: __('Disabled', 'agentflow-ai'), class: 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400' },
		};
		return configs[status] || configs.disabled;
	};

	const getSourceIcon = (type) => {
		const icons = {
			folder: '',
			wordpress_pages: '',
			wordpress_posts: '',
			woocommerce_products: '',
			uploaded: '',
			external_url: '',
		};
		return icons[type] || '';
	};

	const getSourceTypeLabel = (type) => {
		const labels = {
			folder: __('Folder', 'agentflow-ai'),
			wordpress_pages: __('WordPress Pages', 'agentflow-ai'),
			wordpress_posts: __('WordPress Posts', 'agentflow-ai'),
			woocommerce_products: __('WooCommerce Products', 'agentflow-ai'),
			uploaded: __('Uploaded Files', 'agentflow-ai'),
			external_url: __('External URL', 'agentflow-ai'),
		};
		return labels[type] || type;
	};

	const formatDate = (dateStr) => {
		if (!dateStr) return __('Never', 'agentflow-ai');
		const date = new Date(dateStr);
		return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	};

	return (
		<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
			{sources.map((source) => {
				const statusBadge = getStatusBadge(source.status);

				return (
					<article
						key={source.id}
						className={`bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm hover:shadow-lg hover:border-primary/20 dark:hover:border-primary/40 transition-all duration-200 ${source.status === 'disabled' ? 'opacity-60' : ''
							}`}
					>
						{/* Card Header */}
						<div className="p-5">
							<div className="flex items-start gap-4">
								{/* Icon */}
								<div className="flex items-center justify-center w-12 h-12 rounded-xl bg-gray-100 dark:bg-slate-700 text-2xl">
									{getSourceIcon(source.source_type)}
								</div>

								{/* Info */}
								<div className="flex-1 min-w-0">
									<div className="flex items-center gap-2 mb-1">
										<h3 className="text-base font-semibold text-gray-900 dark:text-white truncate" title={source.name}>
											{source.name}
										</h3>
									</div>
									<div className="flex items-center gap-2">
										<span className={`inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full ${statusBadge.class}`}>
											{statusBadge.label}
										</span>
										<span className="text-xs text-gray-500 dark:text-slate-400">
											{source.doc_count} {__('docs', 'agentflow-ai')}
										</span>
									</div>
								</div>
							</div>

							{/* Source Type Badge */}
							<div className="mt-4 flex items-center gap-2">
								<span className="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-600">
									{getSourceTypeLabel(source.source_type)}
								</span>
							</div>

							{/* Last Indexed */}
							<div className="mt-3 flex items-center gap-1.5 text-xs text-gray-400 dark:text-slate-500">
								<span></span>
								<span>{__('Last sync:', 'agentflow-ai')} {formatDate(source.last_indexed)}</span>
							</div>

							{/* Error Message */}
							{source.error_message && (
								<div className="mt-3 px-3 py-2 text-xs text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-800">
									{source.error_message}
								</div>
							)}
						</div>

						{/* Card Footer */}
						<div className="flex items-center justify-end gap-1 px-5 py-3 border-t border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-900/50 rounded-b-xl">
							<button
								onClick={() => onEdit(source)}
								className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-600 border border-gray-300 dark:border-slate-600 shadow-sm transition-colors"
							>
								{__('Edit', 'agentflow-ai')}
							</button>
							<button
								onClick={() => onDelete(source.id)}
								className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
							>
								{__('Delete', 'agentflow-ai')}
							</button>
						</div>
					</article>
				);
			})}
		</div>
	);
}

SourceList.propTypes = {
	sources: PropTypes.array,
	onEdit: PropTypes.func.isRequired,
	onDelete: PropTypes.func.isRequired,
	onSync: PropTypes.func.isRequired,
};
