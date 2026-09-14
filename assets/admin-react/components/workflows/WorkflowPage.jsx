/**
 * WorkflowPage Component
 *
 * Main page for workflow builder management.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import WorkflowList from './WorkflowList';
import WorkflowEditor from './WorkflowEditor';
import ExecutionHistory from './ExecutionHistory';
import { getCached, setCache, invalidateCache } from '../../hooks/useApiCache';

const API_BASE = window.swcChatbot?.apiUrl || '/wp-json/smart-ai-chatbot/v1';

export default function WorkflowPage() {
	const [view, setView] = useState('list');
	const [workflows, setWorkflows] = useState([]);
	const [agents, setAgents] = useState([]);
	const [pendingApprovals, setPendingApprovals] = useState([]);
	const [selectedWorkflow, setSelectedWorkflow] = useState(null);
	const [executions, setExecutions] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [notification, setNotification] = useState(null);

	useEffect(() => {
		fetchWorkflows();
		fetchPendingApprovals();
		fetchOptions();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	const showNotification = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 5000);
	};

	const fetchWorkflows = async () => {
		const cached = getCached('workflows');
		if (cached) {
			setWorkflows(cached);
			setLoading(false);
		}

		try {
			setLoading(true);
			const resp = await fetch(`${API_BASE}/workflows`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			const list = data?.workflows || [];
			setWorkflows(list);
			setCache('workflows', list);
		} catch (err) {
			setError(err.message || __('Failed to load workflows', 'agentflow-ai'));
		} finally {
			setLoading(false);
		}
	};

	const fetchPendingApprovals = async () => {
		try {
			const resp = await fetch(`${API_BASE}/workflow-executions/pending`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			setPendingApprovals(data?.executions || []);
		} catch (err) {
			// non-critical
		}
	};

	const fetchOptions = async () => {
		try {
			const resp = await fetch(`${API_BASE}/workflows/options`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			setAgents(data?.agents || []);
		} catch (err) {
			// ignore
		}
	};

	const handleCreate = () => {
		setSelectedWorkflow(null);
		setView('create');
	};

	const handleEdit = (workflow) => {
		setSelectedWorkflow(workflow);
		setView('edit');
	};

	const handleBack = () => {
		setView('list');
		setSelectedWorkflow(null);
		setExecutions([]);
		invalidateCache('workflows');
		fetchWorkflows();
		fetchPendingApprovals();
	};

	const handleSave = async (payload) => {
		try {
			const isCreate = view === 'create';
			const url = isCreate ? `${API_BASE}/workflows` : `${API_BASE}/workflows/${selectedWorkflow.id}`;
			const method = isCreate ? 'POST' : 'PUT';

			const resp = await fetch(url, {
				method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.swcChatbot?.nonce,
				},
				body: JSON.stringify(payload),
			});
			const data = await resp.json();
			if (!data?.success) {
				throw new Error(data?.message || __('Failed to save workflow', 'agentflow-ai'));
			}
			showNotification(isCreate ? __('Workflow created!', 'agentflow-ai') : __('Workflow updated!', 'agentflow-ai'));
			handleBack();
		} catch (err) {
			showNotification(err.message || __('Failed to save workflow', 'agentflow-ai'), 'error');
		}
	};

	const handleDelete = async (id) => {
		if (!confirm(__('Delete this workflow?', 'agentflow-ai'))) return;
		try {
			const resp = await fetch(`${API_BASE}/workflows/${id}`, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			if (!data?.success) {
				throw new Error(data?.message || __('Failed to delete workflow', 'agentflow-ai'));
			}
			showNotification(__('Workflow deleted', 'agentflow-ai'));
			invalidateCache('workflows');
			fetchWorkflows();
		} catch (err) {
			showNotification(err.message || __('Failed to delete workflow', 'agentflow-ai'), 'error');
		}
	};

	const handleExecute = async (workflow) => {
		try {
			const resp = await fetch(`${API_BASE}/workflows/${workflow.id}/execute`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			if (!data?.success) {
				throw new Error(data?.message || __('Execution failed', 'agentflow-ai'));
			}
			showNotification(__('Workflow started', 'agentflow-ai'));
			fetchPendingApprovals();
		} catch (err) {
			showNotification(err.message || __('Execution failed', 'agentflow-ai'), 'error');
		}
	};

	const handleHistory = async (workflow) => {
		setSelectedWorkflow(workflow);
		setView('history');
		try {
			const resp = await fetch(`${API_BASE}/workflows/${workflow.id}/executions`, {
				headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
			});
			const data = await resp.json();
			setExecutions(data?.executions || []);
		} catch (err) {
			setExecutions([]);
		}
	};

	const handleApproval = async (executionId, approved) => {
		try {
			const resp = await fetch(`${API_BASE}/workflow-executions/${executionId}/resume`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.swcChatbot?.nonce,
				},
				body: JSON.stringify({ feedback: { approved } }),
			});
			const data = await resp.json();
			if (!data?.success) {
				throw new Error(data?.message || __('Failed to resume execution', 'agentflow-ai'));
			}
			showNotification(approved ? __('Approved', 'agentflow-ai') : __('Rejected', 'agentflow-ai'));
			fetchPendingApprovals();
		} catch (err) {
			showNotification(err.message || __('Failed to resume execution', 'agentflow-ai'), 'error');
		}
	};

	const totalWorkflows = workflows.length;
	const activeWorkflows = workflows.filter((wf) => wf.is_active).length;

	return (
		<div className="space-y-6">
			<div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200/70 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 shadow-sm">
				<div>
					<h1 className="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
						{__('Workflow Builder', 'agentflow-ai')}
					</h1>
					<p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
						{__('Create automated multi-step workflows with agents and approvals.', 'agentflow-ai')}
					</p>
				</div>
				{view === 'list' && (
					<button
						onClick={handleCreate}
						className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
					>
						+ {__('New Workflow', 'agentflow-ai')}
					</button>
				)}
			</div>

			{notification && (
				<div className={`px-4 py-3 rounded-lg border ${notification.status === 'error'
					? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800'
					: 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-200 dark:border-green-800'}`}>
					{notification.message}
				</div>
			)}

			{error && (
				<div className="px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800">
					{error}
				</div>
			)}

			{view === 'list' && (
				<>
					<div className="grid grid-cols-1 md:grid-cols-3 gap-4">
						<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
							<p className="text-sm text-gray-500 dark:text-gray-400">{__('Active Workflows', 'agentflow-ai')}</p>
							<p className="text-2xl font-semibold text-gray-900 dark:text-white mt-2">{activeWorkflows}</p>
						</div>
						<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
							<p className="text-sm text-gray-500 dark:text-gray-400">{__('Total Workflows', 'agentflow-ai')}</p>
							<p className="text-2xl font-semibold text-gray-900 dark:text-white mt-2">{totalWorkflows}</p>
						</div>
						<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
							<p className="text-sm text-gray-500 dark:text-gray-400">{__('Pending Approvals', 'agentflow-ai')}</p>
							<p className="text-2xl font-semibold text-gray-900 dark:text-white mt-2">{pendingApprovals.length}</p>
						</div>
					</div>

					{pendingApprovals.length > 0 && (
						<div className="border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/30 rounded-xl p-4">
							<div className="flex items-center justify-between">
								<div>
									<h3 className="text-sm font-semibold text-yellow-800 dark:text-yellow-400">
										{__('Pending Approvals', 'agentflow-ai')}
									</h3>
									<p className="text-xs text-yellow-700 dark:text-yellow-500">
										{__('These workflows are waiting for human approval.', 'agentflow-ai')}
									</p>
								</div>
							</div>
							<div className="mt-3 space-y-2">
								{pendingApprovals.map((execution) => (
									<div key={execution.id} className="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-800 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
										<div>
											<p className="text-sm font-medium text-gray-900 dark:text-white">
												{execution.workflow_name || __('Workflow Execution', 'agentflow-ai')}
											</p>
											<p className="text-xs text-gray-500 dark:text-gray-400">#{execution.id}</p>
										</div>
										<div className="flex items-center gap-2">
											<button
												onClick={() => handleApproval(execution.id, true)}
												className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700"
											>
												{__('Approve', 'agentflow-ai')}
											</button>
											<button
												onClick={() => handleApproval(execution.id, false)}
												className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700"
											>
												{__('Reject', 'agentflow-ai')}
											</button>
										</div>
									</div>
								))}
							</div>
						</div>
					)}

					<WorkflowList
						workflows={workflows}
						onEdit={handleEdit}
						onDelete={handleDelete}
						onExecute={handleExecute}
						onHistory={handleHistory}
					/>
				</>
			)}

			{view !== 'list' && view !== 'history' && (
				<WorkflowEditor
					workflow={selectedWorkflow}
					agents={agents}
					onSave={handleSave}
					onCancel={handleBack}
				/>
			)}

			{view === 'history' && (
				<ExecutionHistory executions={executions} onBack={handleBack} />
			)}

			{loading && view === 'list' && workflows.length === 0 && (
				<div className="text-center text-sm text-gray-500 dark:text-gray-400">{__('Loading workflows…', 'agentflow-ai')}</div>
			)}
		</div>
	);
}
