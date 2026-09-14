/**
 * Action Timeline - Metronic v9 Premium Style
 *
 * Displays tool executions in a visual timeline format.
 * Shows tool name, inputs, outputs, duration, and success status.
 * Features expandable cards with syntax-highlighted JSON.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Icon components
const ToolIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
	</svg>
);

const CheckIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
	</svg>
);

const XIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
	</svg>
);

const ChevronDownIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
	</svg>
);

const ClockIcon = () => (
	<svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
	</svg>
);

export default function ActionTimeline({ actions }) {
	if (!actions || actions.length === 0) {
		return null;
	}

	return (
		<div className="bg-slate-50 rounded-xl border border-slate-200 p-4">
			{/* Header */}
			<div className="flex items-center gap-3 mb-4">
				<div className="flex items-center justify-center w-8 h-8 rounded-lg bg-primary text-white">
					<ToolIcon />
				</div>
				<div>
					<h4 className="text-sm font-semibold text-slate-900">
						{__('Tool Executions', 'agentflow-ai')}
					</h4>
					<p className="text-xs text-slate-500">
						{actions.length} {actions.length === 1 ? __('action', 'agentflow-ai') : __('actions', 'agentflow-ai')} {__('executed', 'agentflow-ai')}
					</p>
				</div>
			</div>

			{/* Timeline */}
			<div className="space-y-3">
				{actions.map((action, idx) => (
					<ActionCard key={action.id || idx} action={action} isLast={idx === actions.length - 1} />
				))}
			</div>
		</div>
	);
}

/**
 * Individual Action Card
 */
function ActionCard({ action, isLast }) {
	const [expanded, setExpanded] = useState(false);

	// Format duration
	const formatDuration = (ms) => {
		if (!ms && ms !== 0) {
			return '-';
		}
		if (ms < 1000) {
			return `${ms}ms`;
		}
		return `${(ms / 1000).toFixed(2)}s`;
	};

	// Format JSON for display
	const formatJson = (data) => {
		if (!data) {
			return 'null';
		}
		if (typeof data === 'string') {
			return data;
		}
		try {
			return JSON.stringify(data, null, 2);
		} catch {
			return String(data);
		}
	};

	const isSuccess = action.success !== false;
	const hasError = action.error || action.error_message;

	return (
		<div className={`relative ${!isLast ? 'pb-3' : ''}`}>
			{/* Timeline connector line */}
			{!isLast && (
				<div className="absolute left-4 top-10 bottom-0 w-0.5 bg-slate-200"></div>
			)}

			<div
				className={`bg-white rounded-xl border shadow-sm overflow-hidden transition-all duration-200 ${hasError
					? 'border-red-200 hover:border-red-300'
					: 'border-slate-200 hover:border-slate-300 hover:shadow-md'
					}`}
			>
				{/* Card Header */}
				<button
					className="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-50 transition-colors"
					onClick={() => setExpanded(!expanded)}
				>
					<div className="flex items-center gap-3">
						{/* Status Indicator */}
						<div
							className={`flex items-center justify-center w-8 h-8 rounded-lg ${isSuccess
								? 'bg-green-500 text-white'
								: 'bg-red-500 text-white'
								}`}
						>
							{isSuccess ? <CheckIcon /> : <XIcon />}
						</div>

						{/* Tool Name */}
						<div className="text-left">
							<p className="text-sm font-semibold text-slate-900 font-mono">
								{action.name || action.tool_name}
							</p>
							{action.tool_description && (
								<p className="text-xs text-slate-500 truncate max-w-xs">
									{action.tool_description}
								</p>
							)}
						</div>
					</div>

					{/* Meta */}
					<div className="flex items-center gap-3">
						{/* Duration Badge */}
						<div className="flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 rounded-lg">
							<ClockIcon />
							<span className="text-xs font-medium text-slate-600">
								{formatDuration(action.duration_ms)}
							</span>
						</div>

						{/* Status Badge */}
						<span
							className={`px-2.5 py-1 text-xs font-semibold rounded-lg ${isSuccess
								? 'bg-green-100 text-green-700'
								: 'bg-red-100 text-red-700'
								}`}
						>
							{isSuccess ? __('Success', 'agentflow-ai') : __('Failed', 'agentflow-ai')}
						</span>

						{/* Expand Icon */}
						<div
							className={`flex items-center justify-center w-6 h-6 rounded-md bg-slate-100 text-slate-500 transition-transform duration-200 ${expanded ? 'rotate-180' : ''
								}`}
						>
							<ChevronDownIcon />
						</div>
					</div>
				</button>

				{/* Expanded Content */}
				{expanded && (
					<div className="border-t border-slate-100 bg-slate-50">
						{/* Error Message */}
						{hasError && (
							<div className="px-4 py-3 bg-red-50 border-b border-red-200">
								<div className="flex items-start gap-3">
									<div className="flex items-center justify-center w-6 h-6 rounded-md bg-red-500 text-white flex-shrink-0 mt-0.5">
										<XIcon />
									</div>
									<div>
										<p className="text-xs font-semibold text-red-800 uppercase tracking-wide mb-1">
											{__('Error', 'agentflow-ai')}
										</p>
										<pre className="text-sm text-red-700 whitespace-pre-wrap font-mono bg-red-100 rounded-lg p-3 border border-red-200">
											{action.error || action.error_message}
										</pre>
									</div>
								</div>
							</div>
						)}

						{/* Inputs */}
						<div className="px-4 py-3 border-b border-slate-100">
							<div className="flex items-center gap-2 mb-2">
								<div className="flex items-center justify-center w-5 h-5 rounded bg-blue-100 text-blue-600">
									<svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 16l-4-4m0 0l4-4m-4 4h14" />
									</svg>
								</div>
								<p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
									{__('Inputs', 'agentflow-ai')}
								</p>
							</div>
							<pre className="text-xs text-slate-700 whitespace-pre-wrap font-mono bg-white rounded-lg p-3 border border-slate-200 max-h-40 overflow-auto">
								{formatJson(action.inputs)}
							</pre>
						</div>

						{/* Output */}
						<div className="px-4 py-3">
							<div className="flex items-center gap-2 mb-2">
								<div className="flex items-center justify-center w-5 h-5 rounded bg-green-100 text-green-600">
									<svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l4 4m0 0l-4 4m4-4H3" />
									</svg>
								</div>
								<p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
									{__('Output', 'agentflow-ai')}
								</p>
							</div>
							<pre className="text-xs text-slate-700 whitespace-pre-wrap font-mono bg-white rounded-lg p-3 border border-slate-200 max-h-40 overflow-auto">
								{formatJson(action.outputs || action.output)}
							</pre>
						</div>
					</div>
				)}
			</div>
		</div>
	);
}
