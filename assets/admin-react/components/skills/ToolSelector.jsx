/**
 * ToolSelector Component
 *
 * Tool checkbox grid for selecting required tools.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function ToolSelector({ selected, onChange }) {
	const [toolkits, setToolkits] = useState([]);
	const [loading, setLoading] = useState(true);
	const [expanded, setExpanded] = useState({});

	// Fetch toolkits on mount
	useEffect(() => {
		fetchToolkits();
	}, []);

	const fetchToolkits = async () => {
		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/toolkits',
			});

			const toolkitsData =
				response?.data || response?.toolkits || response;
			if (toolkitsData && typeof toolkitsData === 'object') {
				setToolkits(Object.values(toolkitsData));
			}
		} catch (err) {
			console.error('Failed to fetch toolkits:', err);
		} finally {
			setLoading(false);
		}
	};

	const toggleTool = (toolId) => {
		if (selected.includes(toolId)) {
			onChange(selected.filter((id) => id !== toolId));
		} else {
			onChange([...selected, toolId]);
		}
	};

	const toggleExpanded = (toolkitId) => {
		setExpanded((prev) => ({
			...prev,
			[toolkitId]: !prev[toolkitId],
		}));
	};

	if (loading) {
		return (
			<div className="flex items-center justify-center p-8 text-gray-500 dark:text-gray-400">
				<svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
					<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
					<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
				</svg>
				{__('Loading tools…', 'agentflow-ai')}
			</div>
		);
	}

	if (toolkits.length === 0) {
		return (
			<div className="border border-dashed border-gray-300 dark:border-gray-700 rounded-xl p-8 text-center bg-gray-50 dark:bg-gray-800/50">
				<p className="text-gray-500 dark:text-gray-400">
					{__('No tools available.', 'agentflow-ai')}
				</p>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			{selected.length > 0 && (
				<div className="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
					<div className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
						{__('Selected:', 'agentflow-ai')}
					</div>
					<div className="flex flex-wrap gap-2">
						{selected.map((toolId) => (
							<span
								key={toolId}
								className="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"
							>
								{toolId}
								<button
									type="button"
									className="flex-shrink-0 ml-1.5 h-4 w-4 rounded-sm inline-flex items-center justify-center text-blue-400 hover:bg-blue-200 hover:text-blue-500 dark:hover:bg-blue-800 focus:outline-none focus:bg-blue-500 focus:text-white"
									onClick={() => toggleTool(toolId)}
								>
									<span className="sr-only">Remove large option</span>
									<svg className="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8">
										<path strokeLinecap="round" strokeWidth="1.5" d="M1 1l6 6m0-6L1 7" />
									</svg>
								</button>
							</span>
						))}
					</div>
				</div>
			)}

			<div className="space-y-4">
				{toolkits.map((toolkit) => (
					<div key={toolkit.id} className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
						<button
							type="button"
							className="w-full px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
							onClick={() => toggleExpanded(toolkit.id)}
						>
							<div className="flex items-center gap-3">
								<span className="flex-shrink-0 w-8 h-8 flex items-center justify-center bg-white dark:bg-gray-700 rounded-lg shadow-sm text-gray-600 dark:text-gray-300">
									{toolkit.icon}
								</span>
								<span className="font-medium text-gray-900 dark:text-white">
									{toolkit.name}
								</span>
								<span className="text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-600">
									{toolkit.tool_count}{' '}{__('tools', 'agentflow-ai')}
								</span>
							</div>
							<span className="text-gray-400 dark:text-gray-500">
								{expanded[toolkit.id] ? (
									<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" /></svg>
								) : (
									<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
								)}
							</span>
						</button>

						{expanded[toolkit.id] && toolkit.categories && (
							<div className="p-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
								<div className="space-y-6">
									{Object.entries(toolkit.categories).map(
										([catId, category]) => (
											<div key={catId} className="space-y-3">
												<div className="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">
													<span className="text-gray-500 dark:text-gray-400">{category.icon}</span>
													{category.name}
												</div>
												<div className="grid grid-cols-1 md:grid-cols-2 gap-3 pl-6">
													{Object.entries(category.tools || {}).map(
														([toolId, description]) => (
															<label
																key={toolId}
																className="relative flex items-start p-3 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
															>
																<div className="flex items-center h-5">
																	<input
																		type="checkbox"
																		className="focus:ring-primary h-4 w-4 text-primary border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
																		checked={selected.includes(toolId)}
																		onChange={() => toggleTool(toolId)}
																	/>
																</div>
																<div className="ml-3 text-sm">
																	<span className="font-mono font-medium text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-900 px-1.5 py-0.5 rounded text-xs block mb-1">
																		{toolId}
																	</span>
																	<span className="text-gray-500 dark:text-gray-400 block leading-tight">
																		{description}
																	</span>
																</div>
															</label>
														)
													)}
												</div>
											</div>
										)
									)}
								</div>
							</div>
						)}
					</div>
				))}
			</div>
		</div>
	);
}
