/**
 * AgentDocumentAssigner Component - Metronic v9 Style
 *
 * Document section selection for agents with premium Tailwind styling.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { FileText, FolderOpen, Check, Loader2 } from 'lucide-react';

export default function AgentDocumentAssigner({
	enabledSections = [],
	onEnabledChange,
}) {
	const [sections, setSections] = useState([]);
	const [loading, setLoading] = useState(true);
	const [searchQuery, setSearchQuery] = useState('');

	useEffect(() => {
		fetchSections();
	}, []);

	const fetchSections = async () => {
		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/documents/sections',
			});

			if (response.success) {
				setSections(response.data || []);
			}
		} catch (err) {
			console.error('Failed to fetch document sections:', err);
		} finally {
			setLoading(false);
		}
	};

	const filteredSections = useMemo(() => {
		if (!searchQuery.trim()) {
			return sections;
		}

		const query = searchQuery.toLowerCase();
		return sections.filter(
			(s) =>
				s.name.toLowerCase().includes(query) ||
				s.description?.toLowerCase().includes(query)
		);
	}, [sections, searchQuery]);

	const isSectionEnabled = (sectionId) => {
		return enabledSections.includes(sectionId);
	};

	const toggleSection = (sectionId) => {
		if (enabledSections.includes(sectionId)) {
			onEnabledChange(
				enabledSections.filter((id) => id !== sectionId)
			);
		} else {
			onEnabledChange([...enabledSections, sectionId]);
		}
	};

	const selectAll = () => {
		const allIds = sections.map((s) => s.id);
		onEnabledChange(allIds);
	};

	const deselectAll = () => {
		onEnabledChange([]);
	};

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-16">
				<div className="relative">
					<div className="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin" />
					<FileText className="w-5 h-5 text-primary absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
				</div>
				<p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
					{__('Loading document sections...', 'smart-woo-chatbot')}
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
						<div className="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30">
							<FileText className="w-5 h-5 text-blue-600 dark:text-blue-400" />
						</div>
						<div>
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
								{__('Document Sections', 'smart-woo-chatbot')}
							</h3>
							<p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
								{__('Select document sections to make available to this agent. The AI can search and read these documents.', 'smart-woo-chatbot')}
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
								placeholder={__('Search sections...', 'smart-woo-chatbot')}
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

				{/* Sections Grid */}
				<div className="p-6">
					{sections.length === 0 ? (
						<div className="text-center py-12">
							<div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
								<FolderOpen className="w-8 h-8 text-gray-400" />
							</div>
							<h4 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
								{__('No document sections available', 'smart-woo-chatbot')}
							</h4>
							<p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
								{__('Create sections in the Documents settings to organize your knowledge base.', 'smart-woo-chatbot')}
							</p>
						</div>
					) : (
						<div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
							{filteredSections.map((section) => {
								const enabled = isSectionEnabled(section.id);

								return (
									<div
										key={section.id}
										onClick={() => toggleSection(section.id)}
										className={`
											group relative flex items-start gap-4 p-4 rounded-xl border-2
											cursor-pointer transition-all duration-200
											${enabled
												? 'border-primary bg-primary/5 dark:bg-primary/10 shadow-sm'
												: 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-primary/50 hover:shadow-sm'
											}
										`}
									>
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

										{/* Content */}
										<div className="flex-1 min-w-0">
											<div className="flex items-center gap-2">
												<span className={`
													font-semibold text-sm
													${enabled ? 'text-primary' : 'text-gray-900 dark:text-white'}
												`}>
													{section.name}
												</span>
												<span className="
													inline-flex items-center px-2 py-0.5 rounded-full
													text-xs font-medium bg-gray-100 dark:bg-gray-700
													text-gray-600 dark:text-gray-300
												">
													{section.document_count || 0} {__('docs', 'smart-woo-chatbot')}
												</span>
											</div>
											{section.description && (
												<p className="mt-1.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
													{section.description}
												</p>
											)}
										</div>
									</div>
								);
							})}
						</div>
					)}

					{filteredSections.length === 0 && sections.length > 0 && (
						<div className="text-center py-12">
							<Search className="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
							<p className="text-sm text-gray-500 dark:text-gray-400">
								{__('No sections match your search.', 'smart-woo-chatbot')}
							</p>
						</div>
					)}
				</div>

				{/* Summary Footer */}
				<div className="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
					<div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
						<div className="flex items-center justify-center w-6 h-6 rounded-full bg-primary/10">
							<FileText className="w-3.5 h-3.5 text-primary" />
						</div>
						<span>
							<strong className="text-gray-900 dark:text-white">{enabledSections.length}</strong>
							{' / '}
							{sections.length}
							{' '}
							{__('sections enabled for this agent', 'smart-woo-chatbot')}
						</span>
					</div>
				</div>
			</div>
		</div>
	);
}

AgentDocumentAssigner.propTypes = {
	enabledSections: PropTypes.array,
	onEnabledChange: PropTypes.func.isRequired,
};
