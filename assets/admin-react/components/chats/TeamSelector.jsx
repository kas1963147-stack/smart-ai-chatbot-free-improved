/**
 * TeamSelector Component
 *
 * Allows selection of an individual agent for a chat widget.
 * Team/workflow orchestration hidden — backend preserved for future use.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

export default function TeamSelector({ value, onChange, agents = [] }) {
	const [selectedId, setSelectedId] = useState('');

	// Parse initial value
	useEffect(() => {
		if (value) {
			if (value.startsWith('agent:')) {
				setSelectedId(value.replace('agent:', ''));
			} else if (value.startsWith('team:')) {
				// Legacy team value — treat as empty so user re-selects an agent
				setSelectedId('');
			} else {
				// Legacy format - assume agent
				setSelectedId(value);
			}
		}
	}, [value]);

	// Notify parent of changes
	const handleSelection = useCallback((selId) => {
		setSelectedId(selId);
		if (selId) {
			onChange(`agent:${selId}`);
		} else {
			onChange('');
		}
	}, [onChange]);

	return (
		<div className="space-y-4">
			{/* Agent Selector */}
			<div>
				<label className="block text-sm font-medium text-gray-700 mb-1.5">
					{__('Select Agent', 'smart-woo-chatbot')}
				</label>
				<select
					value={selectedId}
					onChange={(e) => handleSelection(e.target.value)}
					className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
				>
					<option value="">{__('Select an agent…', 'smart-woo-chatbot')}</option>
					{agents.map((agent) => (
						<option key={agent.agent_id || agent.id} value={agent.agent_id || agent.id}>
							{agent.name || agent.agent_id}
						</option>
					))}
				</select>
				<p className="text-xs text-gray-500 mt-1">
					{__('This agent will handle all conversations for this widget.', 'smart-woo-chatbot')}
				</p>
			</div>
		</div>
	);
}

TeamSelector.propTypes = {
	value: PropTypes.string,
	onChange: PropTypes.func.isRequired,
	agents: PropTypes.array,
};
