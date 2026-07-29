/**
 * TemplateWizard Component (Repurposed as Template List / Existing Agents)
 *
 * Displays a list of templates/existing pre-configured agents.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import {
	AlertCircle,
	Bot,
	ChevronLeft,
	Edit,
} from 'lucide-react';
import Loading from './common/Loading';

export default function TemplateWizard({ agents = [], onSelectTemplate, onCancel }) {
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	// Loading State
	if (loading) {
		return (
			<div className="min-h-[400px] flex flex-col items-center justify-center">
				<Loading message={__('Loading agents...', 'smart-woo-chatbot')} fullPage />
			</div>
		);
	}

	return (
		<div className="max-w-5xl mx-auto pb-10">
			{/* Header */}
			<div className="text-center mb-10">
				<div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-primary/80 shadow-lg shadow-primary/20 mb-4">
					<Bot className="w-8 h-8 text-white" />
				</div>
				<h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">
					{__('Existing Agents', 'smart-woo-chatbot')}
				</h1>
				<p className="text-gray-500 dark:text-gray-400 max-w-xl mx-auto">
					{__('Choose an existing pre-configured agent to customize and use as your own.', 'smart-woo-chatbot')}
				</p>
			</div>

			{/* Error Notice */}
			{error && (
				<div className="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-start gap-3">
					<AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
					<div>
						<p className="text-sm font-medium text-red-800 dark:text-red-200">{__('Error', 'smart-woo-chatbot')}</p>
						<p className="text-sm text-red-600 dark:text-red-300">{error}</p>
					</div>
				</div>
			)}

			<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
				{agents.map((template) => {
					const agentInitial = template.name?.charAt(0)?.toUpperCase() || 'A';
					return (
						<div
							key={template.id}
							className="
							group relative flex flex-col bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700
							p-5 shadow-sm transition-all duration-200
							hover:border-primary/40 hover:shadow-md
						"
						>
							{/* Icon & Title Row */}
							<div className="flex items-start gap-4 mb-3">
								<div className="
								w-12 h-12 shrink-0 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 text-indigo-700 font-bold
								dark:from-slate-700 dark:to-slate-800 dark:text-indigo-400
								flex items-center justify-center text-xl
								border border-blue-100 dark:border-slate-600/50
							">
									{agentInitial}
								</div>

								<div className="flex-1 min-w-0 pt-1">
									<h3 className="text-base font-semibold text-gray-900 dark:text-white group-hover:text-primary transition-colors truncate">
										{template.name}
									</h3>
									<div className="flex items-center gap-2 mt-0.5">
										<span className="inline-flex items-center px-2 py-0.5 text-[10px] uppercase tracking-wider font-semibold rounded bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300">
											Agent
										</span>
									</div>
								</div>
							</div>

							{/* Description */}
							<p className="text-sm text-gray-500 dark:text-slate-400 line-clamp-3 mb-5 flex-1">
								{template.description || __('No description provided.', 'smart-woo-chatbot')}
							</p>

							{/* Edit Button */}
							<div className="mt-auto pt-4 border-t border-gray-100 dark:border-slate-700/50 flex justify-end">
								<button
									onClick={() => onSelectTemplate(template)}
									className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm shadow-primary/20 transition-all"
								>
									<Edit className="w-4 h-4" />
									{__('Edit Agent', 'smart-woo-chatbot')}
								</button>
							</div>
						</div>
					)
				})}
			</div>

			{/* Action Buttons */}
			<div className="flex items-center justify-start mt-8 pt-6 border-t border-gray-200 dark:border-slate-700 max-w-5xl mx-auto w-full">
				<button
					type="button"
					onClick={onCancel}
					className="
						inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg
						bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200
						border border-gray-300 dark:border-slate-600
						hover:bg-gray-50 dark:hover:bg-slate-700
						transition-all duration-200
					"
				>
					<ChevronLeft className="w-4 h-4" />
					{__('Cancel', 'smart-woo-chatbot')}
				</button>
			</div>
		</div>
	);
}

TemplateWizard.propTypes = {
	agents: PropTypes.array,
	onSelectTemplate: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
};
