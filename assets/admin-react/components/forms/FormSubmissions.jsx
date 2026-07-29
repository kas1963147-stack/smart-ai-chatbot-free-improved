/**
 * Form Submissions — List and manage submissions for a specific form
 *
 * Follows the same pattern as LeadDetail.jsx — table with filters and actions.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const EyeIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>);
const TrashIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>);
const CheckIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>);
const RefreshIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>);

const SubmissionStatusBadge = ({ status }) => {
	const styles = {
		pending: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700',
		complete: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
		reviewed: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
	};
	const labels = { pending: 'Pending', complete: 'Complete', reviewed: 'Reviewed' };
	return <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[status] || styles.pending}`}>{labels[status] || status}</span>;
};

export default function FormSubmissions({ form, onViewDetail, onBack }) {
	const [submissions, setSubmissions] = useState([]);
	const [formFields, setFormFields] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [notification, setNotification] = useState(null);
	const [page, setPage] = useState(1);
	const [totalPages, setTotalPages] = useState(1);
	const [total, setTotal] = useState(0);
	const [statusFilter, setStatusFilter] = useState('');
	const [searchQuery, setSearchQuery] = useState('');

	const apiBase = '/smart-ai-chatbot/v1';

	const fetchSubmissions = useCallback(async () => {
		setLoading(true);
		setError(null);
		try {
			let path = `${apiBase}/forms/${form.id}/submissions?page=${page}&per_page=20`;
			if (statusFilter) path += `&status=${statusFilter}`;
			if (searchQuery) path += `&search=${encodeURIComponent(searchQuery)}`;
			const response = await apiFetch({ path });
			if (response.success) {
				setSubmissions(response.data.submissions || []);
				setFormFields(response.data.form?.fields || form.fields || []);
				setTotalPages(response.data.pagination?.total_pages || 1);
				setTotal(response.data.pagination?.total || 0);
			} else {
				setError(response.error || 'Failed to load submissions');
			}
		} catch (err) {
			setError(err.message || 'Failed to load submissions');
		} finally {
			setLoading(false);
		}
	}, [apiBase, form.id, page, statusFilter, searchQuery]);

	useEffect(() => { fetchSubmissions(); }, [fetchSubmissions]);

	const showNotif = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 4000);
	};

	const handleMarkReviewed = async (id) => {
		try {
			await apiFetch({ path: `${apiBase}/form-submissions/${id}`, method: 'PATCH', data: { status: 'reviewed' } });
			showNotif('Marked as reviewed');
			fetchSubmissions();
		} catch (err) { showNotif(err.message || 'Failed to update', 'error'); }
	};

	const handleDelete = async (id) => {
		if (!confirm('Are you sure you want to delete this submission?')) return;
		try {
			await apiFetch({ path: `${apiBase}/form-submissions/${id}`, method: 'DELETE' });
			showNotif('Submission deleted');
			fetchSubmissions();
		} catch (err) { showNotif(err.message || 'Failed to delete', 'error'); }
	};

	// Get a preview of submission data (first 2-3 field values)
	const getSubmissionPreview = (submission) => {
		const data = submission.submission_data || {};
		const previews = [];
		for (const field of formFields) {
			const val = data[field.field_id];
			if (val) {
				previews.push(`${field.label}: ${val}`);
				if (previews.length >= 2) break;
			}
		}
		return previews.join(' · ') || 'No data';
	};

	return (
		<div>
			{notification && <div className={`fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 ${notification.status === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`}>{notification.message}</div>}

			<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
				{/* Filters */}
				<div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
					<div className="flex flex-wrap items-center gap-3">
						<div className="relative flex-1 min-w-[220px] max-w-[340px]">
							<input type="text" placeholder="Search submissions…" value={searchQuery} onChange={(e) => { setSearchQuery(e.target.value); setPage(1); }} className="w-full h-10 px-4 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 focus:bg-white transition-all shadow-sm" />
						</div>
						<select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="h-10 px-3 pr-8 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition cursor-pointer shadow-sm">
							<option value="">All Status</option>
							<option value="pending">Pending</option>
							<option value="complete">Complete</option>
							<option value="reviewed">Reviewed</option>
						</select>
						<button onClick={fetchSubmissions} className="h-10 px-4 text-sm rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition inline-flex items-center gap-2 shadow-sm"><RefreshIcon /> Refresh</button>
					</div>
				</div>

				{/* Table */}
				{loading ? (
					<div className="flex items-center justify-center py-20"><div className="w-8 h-8 border-3 border-indigo-500 border-t-transparent rounded-full animate-spin" /></div>
				) : submissions.length === 0 ? (
					<div className="text-center py-20">
						<div className="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 mx-auto mb-4">
							<svg className="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
						</div>
						<h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-1">No submissions yet</h3>
						<p className="text-sm text-slate-500 dark:text-slate-400">Submissions will appear here when users fill out this form via the AI chatbot.</p>
					</div>
				) : (
					<>
						<div className="overflow-x-auto">
							<table className="w-full text-sm">
								<thead><tr className="bg-slate-50 dark:bg-slate-700/50">
									<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Submitted By</th>
									<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Preview</th>
									<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Source</th>
									<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Status</th>
									<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Date</th>
									<th className="px-6 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Actions</th>
								</tr></thead>
								<tbody className="divide-y divide-slate-100 dark:divide-slate-700">
									{submissions.map((sub) => (
										<tr key={sub.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
											<td className="px-6 py-4">
												<div className="font-medium text-slate-800 dark:text-slate-100">{sub.submitted_by_name || 'Anonymous'}</div>
												<div className="text-xs text-slate-500 dark:text-slate-400">{sub.submitted_by_email || '—'}</div>
											</td>
											<td className="px-6 py-4">
												<div className="text-slate-700 dark:text-slate-200 max-w-[250px] truncate text-xs">{getSubmissionPreview(sub)}</div>
											</td>
											<td className="px-6 py-4">
												<span className="capitalize text-xs px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{sub.source || 'chatbot'}</span>
											</td>
											<td className="px-6 py-4"><SubmissionStatusBadge status={sub.status} /></td>
											<td className="px-6 py-4 text-slate-500 text-xs">{new Date(sub.created_at).toLocaleDateString()}</td>
											<td className="px-6 py-4">
												<div className="flex items-center justify-end gap-1">
													<button onClick={() => onViewDetail(sub)} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="View Details"><EyeIcon /></button>
													{sub.status !== 'reviewed' && (
														<button onClick={() => handleMarkReviewed(sub.id)} className="p-2 rounded-lg text-green-500 hover:bg-green-50 dark:hover:bg-green-900/30 transition" title="Mark Reviewed"><CheckIcon /></button>
													)}
													<button onClick={() => handleDelete(sub.id)} className="p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition" title="Delete"><TrashIcon /></button>
												</div>
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>
						{totalPages > 1 && (
							<div className="flex items-center justify-between px-6 py-4 border-t border-slate-100 dark:border-slate-700">
								<p className="text-sm text-slate-500">Showing {((page - 1) * 20) + 1}–{Math.min(page * 20, total)} of {total}</p>
								<div className="flex items-center gap-1">
									<button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1} className="h-9 px-3 text-sm rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition">Previous</button>
									<span className="px-3 text-sm text-slate-600">{page} / {totalPages}</span>
									<button onClick={() => setPage(Math.min(totalPages, page + 1))} disabled={page === totalPages} className="h-9 px-3 text-sm rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition">Next</button>
								</div>
							</div>
						)}
					</>
				)}
			</div>
		</div>
	);
}
