/**
 * AgentKnowledgeAssigner Component - Metronic v9 Style
 *
 * Knowledge source selection for agents with premium Tailwind styling.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { BookOpen, Database, Check, Globe, FileText, Package } from 'lucide-react';

// Source type icons mapping
const SOURCE_ICONS = {
	website: { Icon: Globe, bg: 'bg-blue-100 dark:bg-blue-900/30', color: 'text-blue-600 dark:text-blue-400' },
	document: { Icon: FileText, bg: 'bg-green-100 dark:bg-green-900/30', color: 'text-green-600 dark:text-green-400' },
	api: { Icon: Package, bg: 'bg-purple-100 dark:bg-purple-900/30', color: 'text-purple-600 dark:text-purple-400' },
	default: { Icon: Database, bg: 'bg-gray-100 dark:bg-gray-700', color: 'text-gray-600 dark:text-gray-400' },
};

export default function AgentKnowledgeAssigner({
	enabledSources = [],
	onEnabledChange,
}) {
	const [sources, setSources] = useState([]);
	const [loading, setLoading] = useState(true);
	const [searchQuery, setSearchQuery] = useState('');

	useEffect(() => {
		fetchSources();
	}, []);

	const fetchSources = async () => {
		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/knowledge/documents',
			});

			if (response.success) {
				setSources(response.data || []);
			}
		} catch (err) {
			console.error('Failed to fetch knowledge sources:', err);
		} finally {
			setLoading(false);
		}
	};

	const filteredSources = useMemo(() => {
		if (!searchQuery.trim()) {
			return sources;
		}

		const query = searchQuery.toLowerCase();
		return sources.filter(
			(s) =>
				s.title?.toLowerCase().includes(query) ||
				s.category?.toLowerCase().includes(query)
		);
	}, [sources, searchQuery]);

	const toggleSource = (sourceId) => {
		if (enabledSources.includes(sourceId)) {
			onEnabledChange(
				enabledSources.filter((id) => id !== sourceId)
			);
		} else {
			onEnabledChange([...enabledSources, sourceId]);
		}
	};

	const selectAll = () => {
		const allIds = sources.map((s) => s.id);
		onEnabledChange(allIds);
	};

	const deselectAll = () => {
		onEnabledChange([]);
	};

	const getSourceIcon = (sourceType) => {
		return SOURCE_ICONS[sourceType] || SOURCE_ICONS.default;
	};

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-16">
				<div className="relative">
					<div className="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin" />
					<BookOpen className="w-5 h-5 text-primary absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
				</div>
				<p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
					{__('Loading knowledge sources...', 'smart-woo-chatbot')}
				</p>
			</div>
		);
	}

	return (
		<div className="w-full">
			{/* Main Card */}
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
				{/* Header */}
				<div className="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
					<div className="flex items-center gap-3">
						<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30">
							<BookOpen className="w-5 h-5 text-purple-600 dark:text-purple-400" />
						</div>
						<div>
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
								{__('Knowledge Sources', 'smart-woo-chatbot')}
							</h3>
							<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
								{__('Select knowledge sources to make available to this agent. Only checked sources will be searchable.', 'smart-woo-chatbot')}
							</p>
						</div>
					</div>
				</div>

				{/* Search and Actions Bar */}
				<div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
					<div className="flex flex-col sm:flex-row gap-3">
						{/* Search Input */}
						<div className="flex-1">
							<input
								type="text"
								value={searchQuery}
								onChange={(e) => setSearchQuery(e.target.value)}
								placeholder={__('Search sources...', 'smart-woo-chatbot')}
								className="
									w-full h-10 px-4 text-sm rounded-lg
									border border-gray-200 dark:border-gray-600
									bg-white dark:bg-gray-900
									text-gray-900 dark:text-white
									placeholder-gray-400 dark:placeholder-gray-500
									focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
									transition-all duration-200
								"
							/>
						</div>

						{/* Action Buttons */}
						<div className="flex gap-2">
							<button
								onClick={selectAll}
								className="
									inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg
									bg-primary text-white
									hover:bg-primary/90
									transition-all duration-200
								"
							>
								<Check className="w-4 h-4" />
								{__('Enable All', 'smart-woo-chatbot')}
							</button>
							<button
								onClick={deselectAll}
								className="
									inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg
									bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200
									border border-gray-300 dark:border-gray-600
									hover:bg-gray-50 dark:hover:bg-gray-600
									transition-all duration-200
								"
							>
								{__('Disable All', 'smart-woo-chatbot')}
							</button>
						</div>
					</div>
				</div>

				{/* Sources Grid */}
				<div className="p-6">
					{filteredSources.length === 0 ? (
						<div className="text-center py-12">
							<div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
								<Database className="w-8 h-8 text-gray-400" />
							</div>
							<h4 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
								{sources.length === 0
									? __('No knowledge sources found', 'smart-woo-chatbot')
									: __('No sources match your search', 'smart-woo-chatbot')
								}
							</h4>
							<p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
								{sources.length === 0
									? __('Create knowledge sources in the Knowledge settings to start building your AI knowledge base.', 'smart-woo-chatbot')
									: __('Try adjusting your search terms.', 'smart-woo-chatbot')
								}
							</p>
						</div>
					) : (
						<div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
							{filteredSources.map((source) => {
								const enabled = enabledSources.includes(source.id);
								const iconConfig = getSourceIcon(source.type || 'document');
								const IconComponent = iconConfig.Icon;

								return (
									<div
										key={source.id}
										onClick={() => toggleSource(source.id)}
										className={`
											group relative flex items-start gap-4 p-4 rounded-xl border-2
											cursor-pointer transition-all duration-200
											${enabled
												? 'border-primary bg-primary/5 dark:bg-primary/10 shadow-sm'
												: 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-primary/50 hover:shadow-sm'
											}
										`}
									>
										{/* Icon */}
										<div className={`
											flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center
											${iconConfig.bg}
										`}>
											<IconComponent className={`w-5 h-5 ${iconConfig.color}`} />
										</div>

										{/* Content */}
										<div className="flex-1 min-w-0">
											<div className="flex items-center gap-2">
												<span className={`
													font-semibold text-sm line-clamp-1
													${enabled ? 'text-primary' : 'text-gray-900 dark:text-white'}
												`}>
													{source.title}
												</span>
											</div>
											<div className="flex items-center gap-2 mt-1">
												<span className="
													inline-flex items-center px-2 py-0.5 rounded-full
													text-xs font-medium bg-gray-100 dark:bg-gray-700
													text-gray-600 dark:text-gray-300 uppercase leading-none
												">
													{source.category || 'general'}
												</span>
												<span className="text-xs text-gray-500 dark:text-gray-400 capitalize">
													{source.type || 'document'}
												</span>
											</div>
											{source.is_active !== undefined && (
												<div className="mt-2 flex items-center gap-1">
													<span className={`
														w-2 h-2 rounded-full
														${source.is_active ? 'bg-green-500' : 'bg-gray-400'}
													`} />
													<span className="text-xs text-gray-500 dark:text-gray-400">
														{source.is_active ? __('Active', 'smart-woo-chatbot') : __('Inactive', 'smart-woo-chatbot')}
													</span>
												</div>
											)}
										</div>

										{/* Checkbox */}
										<div className={`
											flex-shrink-0 w-5 h-5 rounded-md border-2 flex items-center justify-center
											transition-all duration-200
											${enabled
												? 'bg-primary border-primary'
												: 'border-gray-300 dark:border-gray-600 group-hover:border-primary/50'
											}
										`}>
											{enabled && <Check className="w-3 h-3 text-white" />}
										</div>
									</div>
								);
							})}
						</div>
					)}
				</div>

				{/* Summary Footer */}
				<div className="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
					<div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
						<div className="flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900/30">
							<BookOpen className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
						</div>
						<span>
							<strong className="text-gray-900 dark:text-white">{enabledSources.length}</strong>
							{' / '}
							{sources.length}
							{' '}
							{__('sources enabled for this agent', 'smart-woo-chatbot')}
						</span>
					</div>
				</div>
			</div>
		</div>
	);
}

AgentKnowledgeAssigner.propTypes = {
	enabledSources: PropTypes.array,
	onEnabledChange: PropTypes.func.isRequired,
};
