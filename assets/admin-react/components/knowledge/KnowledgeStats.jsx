/**
 * KnowledgeStats Component - Metronic v9 Style
 *
 * Display knowledge base statistics as modern stat cards.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

export default function KnowledgeStats({ stats }) {
	if (!stats) {
		return null;
	}

	const statItems = [
		{
			label: __('Sources', 'smart-woo-chatbot'),
			value: stats.active_sources || 0,
			icon: '',
			color: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
		},
		{
			label: __('Documents', 'smart-woo-chatbot'),
			value: stats.total_docs || 0,
			icon: '',
			color: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
		},
		{
			label: __('Total Words', 'smart-woo-chatbot'),
			value: stats.total_words ? stats.total_words.toLocaleString() : '0',
			icon: '',
			color: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
		},
		{
			label: __('Last Updated', 'smart-woo-chatbot'),
			value: stats.last_indexed ? new Date(stats.last_indexed).toLocaleDateString() : __('Never', 'smart-woo-chatbot'),
			icon: '',
			color: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
		},
	];

	return (
		<div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
			{statItems.map((item, idx) => (
				<div
					key={idx}
					className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex items-center gap-4"
				>
					<div className={`flex items-center justify-center w-12 h-12 rounded-xl text-xl ${item.color}`}>
						{item.icon}
					</div>
					<div>
						<div className="text-2xl font-bold text-gray-900 dark:text-white">{item.value}</div>
						<div className="text-sm text-gray-500 dark:text-slate-400">{item.label}</div>
					</div>
				</div>
			))}
		</div>
	);
}

KnowledgeStats.propTypes = {
	stats: PropTypes.shape({
		active_sources: PropTypes.number,
		total_docs: PropTypes.number,
		total_words: PropTypes.number,
		last_indexed: PropTypes.string,
	}),
};
