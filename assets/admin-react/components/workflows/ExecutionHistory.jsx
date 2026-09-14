/**
 * ExecutionHistory Component
 *
 * Displays workflow execution history.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

const STATUS_STYLES = {
	pending: 'bg-gray-100 text-gray-700',
	running: 'bg-blue-100 text-blue-700',
	awaiting_approval: 'bg-yellow-100 text-yellow-700',
	completed: 'bg-green-100 text-green-700',
	failed: 'bg-red-100 text-red-700',
};

export default function ExecutionHistory({ executions = [], onBack }) {
	return (
		<div className="bg-white rounded-xl border border-gray-200 shadow-sm">
			<div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
				<div>
					<h2 className="text-lg font-semibold text-gray-900">
						{__('Execution History', 'agentflow-ai')}
					</h2>
					<p className="text-sm text-gray-500">
						{__('Past workflow runs and outputs.', 'agentflow-ai')}
					</p>
				</div>
				<button
					onClick={onBack}
					className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-colors"
				>
					{__('Back', 'agentflow-ai')}
				</button>
			</div>

			<div className="p-6">
				{executions.length === 0 ? (
					<div className="text-sm text-gray-500">{__('No executions yet.', 'agentflow-ai')}</div>
				) : (
					<div className="space-y-3">
						{executions.map((execution) => (
							<div key={execution.id} className="border border-gray-200 rounded-lg p-4">
								<div className="flex flex-wrap items-center justify-between gap-3">
									<div>
										<p className="text-sm font-medium text-gray-900">
											{__('Execution', 'agentflow-ai')} #{execution.id}
										</p>
										<p className="text-xs text-gray-500">
											{execution.started_at || __('Not started', 'agentflow-ai')}
										</p>
									</div>
									<span className={`inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full ${STATUS_STYLES[execution.status] || STATUS_STYLES.pending}`}>
										{execution.status}
									</span>
								</div>
								{execution.duration_ms && (
									<p className="text-xs text-gray-500 mt-2">
										{__('Duration', 'agentflow-ai')}: {Math.round(execution.duration_ms / 1000)}s
									</p>
								)}
								{execution.output && (
									<pre className="mt-3 text-xs bg-gray-50 border border-gray-100 rounded-lg p-3 overflow-x-auto">
										{typeof execution.output === 'string' ? execution.output : JSON.stringify(execution.output, null, 2)}
									</pre>
								)}
							</div>
						))}
					</div>
				)}
			</div>
		</div>
	);
}

ExecutionHistory.propTypes = {
	executions: PropTypes.array,
	onBack: PropTypes.func.isRequired,
};
