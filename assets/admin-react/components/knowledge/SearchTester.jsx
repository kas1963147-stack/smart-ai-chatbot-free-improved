/**
 * SearchTester Component - Metronic v9 Style
 *
 * Test knowledge base search functionality with modern UI.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function SearchTester() {
	const [query, setQuery] = useState('');
	const [results, setResults] = useState(null);
	const [searching, setSearching] = useState(false);
	const [error, setError] = useState(null);

	const handleSearch = async () => {
		if (!query.trim()) return;

		setSearching(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/knowledge/search',
				method: 'POST',
				data: { query, limit: 5 },
			});

			if (response.success) {
				setResults(response.data?.results || []);
			} else {
				setError(response.error || 'Search failed');
			}
		} catch (err) {
			setError(err.message);
		} finally {
			setSearching(false);
		}
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter') handleSearch();
	};

	return (
		<div className="space-y-6">
			{/* Header */}
			<div>
				<h3 className="text-lg font-semibold text-gray-900 mb-1">
					{__('Test Knowledge Search', 'agentflow-ai')}
				</h3>
				<p className="text-sm text-gray-500">
					{__('Test how the AI will search your knowledge base.', 'agentflow-ai')}
				</p>
			</div>

			{/* Search Form */}
			<div className="flex gap-3">
				<div className="flex-1">
					<label className="block text-sm font-medium text-gray-700 mb-1.5">
						{__('Search Query', 'agentflow-ai')}
					</label>
					<input
						type="text"
						placeholder={__('e.g., What is your return policy?', 'agentflow-ai')}
						value={query}
						onChange={(e) => setQuery(e.target.value)}
						onKeyDown={handleKeyDown}
						className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
					/>
				</div>
				<div className="flex items-end">
					<button
						onClick={handleSearch}
						disabled={searching || !query.trim()}
						className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
					>
						{searching ? (
							<>
								<span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
								{__('Searchingâ€¦', 'agentflow-ai')}
							</>
						) : (
							<> {__('Search', 'agentflow-ai')}</>
						)}
					</button>
				</div>
			</div>

			{/* Error */}
			{error && (
				<div className="px-4 py-3 rounded-lg bg-red-50 text-red-800 border border-red-200 text-sm">
					{error}
				</div>
			)}

			{/* Results */}
			{results !== null && (
				<div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
					<div className="px-5 py-3 bg-gray-50 border-b border-gray-200">
						<h4 className="text-sm font-semibold text-gray-700">
							{__('Results', 'agentflow-ai')} ({results.length})
						</h4>
					</div>

					{results.length === 0 ? (
						<div className="px-5 py-8 text-center text-gray-500">
							<div className="text-3xl mb-2"></div>
							<p>{__('No matching documents found.', 'agentflow-ai')}</p>
						</div>
					) : (
						<ul className="divide-y divide-gray-100">
							{results.map((result, idx) => (
								<li key={idx} className="p-5 hover:bg-gray-50 transition-colors">
									<div className="flex items-center justify-between mb-2">
										<strong className="text-base font-semibold text-gray-900">
											{result.title}
										</strong>
										<span className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">
											{Math.round(result.score * 100)}% match
										</span>
									</div>
									<div className="text-xs text-gray-500 mb-2">
										{result.content_type}
									</div>
									<p className="text-sm text-gray-600 line-clamp-3">
										{result.snippet}
									</p>
								</li>
							))}
						</ul>
					)}
				</div>
			)}
		</div>
	);
}
