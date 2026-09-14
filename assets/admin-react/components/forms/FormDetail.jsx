/**
 * Form Detail â€” View a single submission's field values
 *
 * Shows all field labels and their submitted values in a clean card layout.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const SubmissionStatusBadge = ({ status }) => {
	const styles = {
		pending: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700',
		complete: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
		reviewed: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
	};
	const labels = { pending: 'Pending', complete: 'Complete', reviewed: 'Reviewed' };
	return <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[status] || styles.pending}`}>{labels[status] || status}</span>;
};

export default function FormDetail({ submission, form, onBack }) {
	const [notification, setNotification] = useState(null);
	const [currentStatus, setCurrentStatus] = useState(submission.status);

	const apiBase = '/quark-agentflow-ai/v1';
	const submissionData = submission.submission_data || {};
	const fieldMap = submission.field_map || {};
	const fields = form.fields || [];

	const showNotif = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 4000);
	};

	const handleUpdateStatus = async (newStatus) => {
		try {
			await apiFetch({ path: `${apiBase}/form-submissions/${submission.id}`, method: 'PATCH', data: { status: newStatus } });
			setCurrentStatus(newStatus);
			showNotif(`Status updated to ${newStatus}`);
		} catch (err) {
			showNotif(err.message || 'Failed to update', 'error');
		}
	};

	const handleDelete = async () => {
		if (!confirm('Are you sure you want to delete this submission?')) return;
		try {
			await apiFetch({ path: `${apiBase}/form-submissions/${submission.id}`, method: 'DELETE' });
			showNotif('Submission deleted');
			setTimeout(onBack, 500);
		} catch (err) {
			showNotif(err.message || 'Failed to delete', 'error');
		}
	};

	return (
		<div>
			{notification && <div className={`fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 ${notification.status === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`}>{notification.message}</div>}

			<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
				{/* Header */}
				<div className="relative bg-gradient-to-r from-indigo-500/10 via-purple-500/5 to-transparent px-6 py-5">
					<div className="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 rounded-bl-full" />
					<div className="relative flex items-center justify-between">
						<div>
							<h3 className="text-lg font-bold text-slate-900 dark:text-white">Submission #{submission.id}</h3>
							<div className="flex items-center gap-3 mt-1">
								<SubmissionStatusBadge status={currentStatus} />
								<span className="text-xs text-slate-500">Submitted {new Date(submission.created_at).toLocaleString()}</span>
								<span className="text-xs text-slate-400">via {submission.source || 'chatbot'}</span>
							</div>
						</div>
						<div className="flex items-center gap-2">
							{currentStatus !== 'reviewed' && (
								<button onClick={() => handleUpdateStatus('reviewed')} className="h-9 px-4 text-sm font-medium rounded-xl bg-green-500 text-white hover:bg-green-600 transition inline-flex items-center gap-2">
									<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
									Mark Reviewed
								</button>
							)}
							<button onClick={handleDelete} className="h-9 px-4 text-sm font-medium rounded-xl border border-red-200 text-red-500 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20 transition">Delete</button>
						</div>
					</div>
				</div>

				{/* Submitter info */}
				{(submission.submitted_by_name || submission.submitted_by_email) && (
					<div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-4">
						<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600">
							<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
						</div>
						<div>
							<p className="font-medium text-slate-800 dark:text-slate-100">{submission.submitted_by_name || 'Anonymous'}</p>
							{submission.submitted_by_email && <p className="text-xs text-slate-500">{submission.submitted_by_email}</p>}
						</div>
					</div>
				)}

				{/* Field values */}
				<div className="px-6 py-5">
					<h4 className="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Submitted Data</h4>
					<div className="space-y-4">
						{fields.map((field) => {
							const value = submissionData[field.field_id];
							return (
								<div key={field.field_id} className="bg-slate-50 dark:bg-slate-700/50 rounded-xl p-4 border border-slate-200 dark:border-slate-600">
									<div className="flex items-center gap-2 mb-1">
										<span className="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide">{field.label}</span>
										<span className="text-xs text-slate-300 dark:text-slate-600">({field.type})</span>
										{field.required && <span className="text-xs text-red-400">*</span>}
									</div>
									<p className={`text-sm ${value ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400 italic'}`}>
										{value || 'Not provided'}
									</p>
								</div>
							);
						})}

						{/* Show any extra fields not in the form definition */}
						{Object.keys(submissionData).filter(k => !fields.find(f => f.field_id === k)).map((key) => (
							<div key={key} className="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 border border-yellow-200 dark:border-yellow-700">
								<div className="flex items-center gap-2 mb-1">
									<span className="text-xs font-semibold text-yellow-600 dark:text-yellow-400 uppercase tracking-wide">{key}</span>
									<span className="text-xs text-yellow-400">(extra field)</span>
								</div>
								<p className="text-sm text-slate-800 dark:text-slate-100">{submissionData[key]}</p>
							</div>
						))}
					</div>
				</div>

				{/* Conversation link */}
				{submission.conversation_id && (
					<div className="px-6 py-4 border-t border-slate-100 dark:border-slate-700">
						<span className="text-xs text-slate-400">Conversation ID: <code className="text-xs text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">{submission.conversation_id}</code></span>
					</div>
				)}
			</div>
		</div>
	);
}
