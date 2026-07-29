/**
 * Inline Knowledge Search Component - Metronic v9 Style
 *
 * Quick search preview with modern debounced search.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import debounce from 'lodash/debounce';

export default function InlineSearchPreview() {
	const [query, setQuery] = useState('');
	const [results, setResults] = useState([]);
	const [loading, setLoading] = useState(false);
	const [searched, setSearched] = useState(false);

	// Debounced search function
	const performSearch = useMemo(
		() =>
			debounce(async (searchQuery) => {
				if (!searchQuery.trim()) {
					setResults([]);
					setSearched(false);
					return;
				}

				setLoading(true);
				try {
					const response = await apiFetch({
						path: '/smart-ai-chatbot/v1/knowledge/search',
						method: 'POST',
						data: { query: searchQuery, limit: 5 },
					});

					if (response.success) {
						setResults(response.data?.results || []);
					} else {
						setResults([]);
					}
				} catch (err) {
					console.error('Search failed:', err);
					setResults([]);
				} finally {
					setLoading(false);
					setSearched(true);
				}
			}, 300),
		[]
	);

	useEffect(() => {
		return () => {
			performSearch.cancel();
		};
	}, [performSearch]);

	const handleInputChange = (e) => {
		const value = e.target.value;
		setQuery(value);
		performSearch(value);
	};

	const clearSearch = () => {
		setQuery('');
		setResults([]);
		setSearched(false);
	};

	const getSourceIcon = (type) => {
		const icons = {
			page: '',
			product: '',
			post: '',
		};
		return icons[type] || '';
	};

	return (
		<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 mb-6">
			{/* Header */}
			<div className="flex items-center gap-2 mb-4">
				<span className="text-xl"></span>
				<h3 className="text-base font-semibold text-gray-900 dark:text-white">
					{__('Quick Search', 'smart-ai-chatbot')}
				</h3>
			</div>

			{/* Search Input */}
			<div className="relative mb-4">
				<input
					type="text"
					placeholder={__('Test your knowledge base…', 'smart-ai-chatbot')}
					value={query}
					onChange={handleInputChange}
					className="w-full h-10 px-4 pr-10 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
				/>
				{query && (
					<button
						onClick={clearSearch}
						className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition-colors"
					>

					</button>
				)}
			</div>

			{/* Results Area */}
			<div className="min-h-[60px]">
				{loading && (
					<div className="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 py-3">
						<span className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
						{__('Searching…', 'smart-ai-chatbot')}
					</div>
				)}

				{!loading && searched && results.length === 0 && (
					<div className="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 py-3">
						<span></span>
						{__('No results found', 'smart-ai-chatbot')}
					</div>
				)}

				{!loading && results.length > 0 && (
					<div className="space-y-2">
						{results.map((result, idx) => (
							<div
								key={idx}
								className="p-3 bg-gray-50 dark:bg-slate-900/50 rounded-lg border border-gray-100 dark:border-slate-700 hover:border-primary/20 dark:hover:border-primary/40 transition-colors"
							>
								<div className="flex items-center justify-between mb-1">
									<span className="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
										{getSourceIcon(result.source_type)}
										{result.source_name || result.title}
									</span>
									{result.score && (
										<span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
											{Math.round(result.score * 100)}%
										</span>
									)}
								</div>
								<p className="text-xs text-gray-500 dark:text-slate-400 line-clamp-2">
									{result.content?.substring(0, 150)}...
								</p>
							</div>
						))}
					</div>
				)}

				{!loading && !searched && (
					<div className="flex items-center gap-2 text-sm text-gray-400 dark:text-slate-500 py-3">
						<span></span>
						{__('Try searching to test your knowledge base', 'smart-ai-chatbot')}
					</div>
				)}
			</div>
		</div>
	);
}
