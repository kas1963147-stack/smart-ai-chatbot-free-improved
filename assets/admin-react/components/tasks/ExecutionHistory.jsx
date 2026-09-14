/**
 * Execution History Component
 *
 * Displays task execution history with status and details.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Button, Modal } from '../ui';

const API_BASE = window.swcChatbot?.apiUrl || '/wp-json/smart-ai-chatbot/v1';

const STATUS_CONFIG = {
	pending: { icon: '', color: 'var(--swc-color-muted)', label: 'Pending' },
	running: {
		icon: '',
		color: 'var(--swc-color-primary)',
		label: 'Running',
	},
	completed: {
		icon: '',
		color: 'var(--swc-color-success)',
		label: 'Completed',
	},
	failed: { icon: '', color: 'var(--swc-color-error)', label: 'Failed' },
	timeout: {
		icon: '',
		color: 'var(--swc-color-warning)',
		label: 'Timeout',
	},
	cancelled: {
		icon: '',
		color: 'var(--swc-color-muted)',
		label: 'Cancelled',
	},
};

export default function ExecutionHistory({ task }) {
	const [executions, setExecutions] = useState([]);
	const [loading, setLoading] = useState(true);
	const [selectedExecution, setSelectedExecution] = useState(null);

	const fetchExecutions = useCallback(async () => {
		try {
			setLoading(true);
			const resp = await fetch(
				`${API_BASE}/tasks/${task.id}/executions?limit=50`,
				{
					headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
				}
			);
			const data = await resp.json();
			setExecutions(data.executions || []);
		} catch (err) {
			console.error('Failed to fetch executions:', err);
		} finally {
			setLoading(false);
		}
	}, [task.id]);

	useEffect(() => {
		fetchExecutions();
	}, [fetchExecutions]);

	const formatDate = (dateStr) => {
		if (!dateStr) {
			return '—';
		}
		return new Date(dateStr).toLocaleString();
	};

	const formatDuration = (durationHuman) => {
		return durationHuman || '—';
	};

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-12">
				<div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
				<p className="text-sm text-gray-500">
					{__('Loading execution history…', 'agentflow-ai')}
				</p>
			</div>
		);
	}

	if (executions.length === 0) {
		return (
			<div className="swc-empty-state">
				<div className="swc-empty-state__icon"></div>
				<h3 className="swc-empty-state__title">
					{__('No Execution History', 'agentflow-ai')}
				</h3>
				<p className="swc-empty-state__description">
					{__(
						'This task has not been executed yet.',
						'agentflow-ai'
					)}
				</p>
			</div>
		);
	}

	return (
		<div className="swc-execution-history">
			<div className="swc-execution-timeline">
				{executions.map((exec) => {
					const statusConfig =
						STATUS_CONFIG[exec.status] || STATUS_CONFIG.pending;
					return (
						<div
							key={exec.id}
							className={`swc-execution-item swc-execution-item--${exec.status}`}
							onClick={() => setSelectedExecution(exec)}
						>
							<div
								className="swc-execution-item__icon"
								style={{ color: statusConfig.color }}
							>
								{statusConfig.icon}
							</div>
							<div className="swc-execution-item__content">
								<div className="swc-execution-item__header">
									<span
										className="swc-execution-item__status"
										style={{ color: statusConfig.color }}
									>
										{statusConfig.label}
									</span>
									<span className="swc-execution-item__date">
										{formatDate(exec.started_at)}
									</span>
								</div>
								<div className="swc-execution-item__details">
									<span className="swc-execution-item__duration">
										{' '}
										{formatDuration(
											exec.duration_human
										)}
									</span>
									{exec.tokens_used && (
										<span className="swc-execution-item__tokens">
											{' '}
											{exec.tokens_used.toLocaleString()}{' '}
											tokens
										</span>
									)}
								</div>
								{exec.error_message && (
									<div className="swc-execution-item__error">
										{' '}
										{exec.error_message.substring(
											0,
											100
										)}
										{exec.error_message.length > 100 &&
											'...'}
									</div>
								)}
							</div>
						</div>
					);
				})}
			</div>

			{ /* Execution Detail Modal */}
			{selectedExecution && (
				<Modal
					title={__('Execution Details', 'agentflow-ai')}
					isOpen={!!selectedExecution}
					onClose={() => setSelectedExecution(null)}
					className="swc-modal swc-execution-modal"
					footer={
						<Button
							variant="secondary"
							onClick={() => setSelectedExecution(null)}
						>
							{__('Close', 'agentflow-ai')}
						</Button>
					}
				>
					<div className="swc-modal__content">
						<div className="swc-detail-grid">
							<div className="swc-detail-row">
								<strong>
									{__(
										'Execution ID:',
										'agentflow-ai'
									)}
								</strong>
								<code className="swc-code">
									{selectedExecution.execution_id}
								</code>
							</div>
							<div className="swc-detail-row">
								<strong>
									{__('Status:', 'agentflow-ai')}
								</strong>
								<span
									style={{
										color: STATUS_CONFIG[
											selectedExecution.status
										]?.color,
									}}
								>
									{
										STATUS_CONFIG[
											selectedExecution.status
										]?.icon
									}{' '}
									{
										STATUS_CONFIG[
											selectedExecution.status
										]?.label
									}
								</span>
							</div>
							<div className="swc-detail-row">
								<strong>
									{__('Started:', 'agentflow-ai')}
								</strong>
								<span>
									{formatDate(
										selectedExecution.started_at
									)}
								</span>
							</div>
							<div className="swc-detail-row">
								<strong>
									{__('Completed:', 'agentflow-ai')}
								</strong>
								<span>
									{formatDate(
										selectedExecution.completed_at
									)}
								</span>
							</div>
							<div className="swc-detail-row">
								<strong>
									{__('Duration:', 'agentflow-ai')}
								</strong>
								<span>
									{formatDuration(
										selectedExecution.duration_human
									)}
								</span>
							</div>
							{selectedExecution.tokens_used && (
								<div className="swc-detail-row">
									<strong>
										{__(
											'Tokens Used:',
											'agentflow-ai'
										)}
									</strong>
									<span>
										{selectedExecution.tokens_used.toLocaleString()}
									</span>
								</div>
							)}
						</div>

						{selectedExecution.error_message && (
							<div className="swc-detail-section">
								<strong>
									{__('Error:', 'agentflow-ai')}
								</strong>
								<pre className="swc-code-block swc-code-block--error">
									{selectedExecution.error_message}
								</pre>
							</div>
						)}

						{selectedExecution.result && (
							<div className="swc-detail-section">
								<strong>
									{__('Result:', 'agentflow-ai')}
								</strong>
								<pre className="swc-code-block">
									{JSON.stringify(
										selectedExecution.result,
										null,
										2
									)}
								</pre>
							</div>
						)}
					</div>
				</Modal>
			)}
		</div>
	);
}

ExecutionHistory.propTypes = {
	task: PropTypes.shape({
		id: PropTypes.oneOfType([PropTypes.string, PropTypes.number])
			.isRequired,
		name: PropTypes.string,
	}).isRequired,
};
