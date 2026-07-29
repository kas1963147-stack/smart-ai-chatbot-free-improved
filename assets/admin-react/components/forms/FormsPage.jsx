/**
 * Forms Page — Admin page for managing custom forms and viewing submissions
 *
 * Features form builder, form list, submission viewer, and detail view.
 * Follows the same pattern as LeadsPage.jsx and AppointmentsPage.jsx.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import FormSubmissions from './FormSubmissions';
import FormDetail from './FormDetail';

// ─── Icons ────────────────────────────────────────────────────
const FormIcon = () => (<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>);
const PlusIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>);
const RefreshIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>);
const ArrowLeftIcon = () => (<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>);
const TrashIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>);
const EditIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>);
const EyeIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>);
const ChevronUpIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" /></svg>);
const ChevronDownIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>);

const StatusBadge = ({ status }) => {
	const styles = {
		active: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
		draft: 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-700/30 dark:text-slate-300 dark:border-slate-600',
		archived: 'bg-red-50 text-red-600 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700',
	};
	const labels = { active: 'Active', draft: 'Draft', archived: 'Archived' };
	return <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[status] || styles.draft}`}>{labels[status] || status}</span>;
};

// ─── Field Types ──────────────────────────────────────────────
const FIELD_TYPES = [
	{ value: 'text', label: 'Text' },
	{ value: 'email', label: 'Email' },
	{ value: 'phone', label: 'Phone' },
	{ value: 'number', label: 'Number' },
	{ value: 'textarea', label: 'Textarea' },
	{ value: 'select', label: 'Dropdown' },
	{ value: 'date', label: 'Date' },
	{ value: 'url', label: 'URL' },
	{ value: 'checkbox', label: 'Checkbox' },
];

// ─── Form Builder Modal ──────────────────────────────────────
const FormBuilderModal = ({ isOpen, onClose, onSave, saving, editForm = null }) => {
	const [formName, setFormName] = useState('');
	const [formDescription, setFormDescription] = useState('');
	const [formStatus, setFormStatus] = useState('active');
	const [fields, setFields] = useState([]);
	const nameRef = useRef(null);

	useEffect(() => {
		if (isOpen) {
			if (editForm) {
				setFormName(editForm.form_name || '');
				setFormDescription(editForm.form_description || '');
				setFormStatus(editForm.status || 'active');
				setFields(editForm.fields || []);
			} else {
				setFormName('');
				setFormDescription('');
				setFormStatus('active');
				setFields([]);
			}
			setTimeout(() => nameRef.current?.focus(), 100);
		}
	}, [isOpen, editForm]);

	if (!isOpen) return null;

	const addField = () => {
		const newId = 'f_' + (Date.now());
		setFields([...fields, {
			field_id: newId,
			type: 'text',
			label: '',
			placeholder: '',
			required: false,
			options: [],
		}]);
	};

	const updateField = (index, key, value) => {
		const updated = [...fields];
		updated[index] = { ...updated[index], [key]: value };
		setFields(updated);
	};

	const removeField = (index) => {
		setFields(fields.filter((_, i) => i !== index));
	};

	const moveField = (index, direction) => {
		const newFields = [...fields];
		const newIndex = index + direction;
		if (newIndex < 0 || newIndex >= newFields.length) return;
		[newFields[index], newFields[newIndex]] = [newFields[newIndex], newFields[index]];
		setFields(newFields);
	};

	const handleSubmit = (e) => {
		e.preventDefault();
		if (!formName.trim()) return;
		if (fields.length === 0) return;
		// Validate all fields have labels
		const validFields = fields.filter(f => f.label.trim());
		if (validFields.length === 0) return;

		onSave({
			form_name: formName,
			form_description: formDescription,
			status: formStatus,
			fields: validFields,
		});
	};

	const inputClass = "w-full h-10 px-3 text-sm rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all";
	const labelClass = "block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5";

	return (
		<div className="fixed inset-0 z-50 flex items-center justify-center p-4">
			<div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
			<div className="relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden max-h-[90vh] flex flex-col">
				{/* Header */}
				<div className="relative bg-gradient-to-r from-primary/10 via-primary/5 to-transparent px-6 py-5 shrink-0">
					<div className="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full" />
					<div className="relative flex items-center justify-between">
						<div className="flex items-center gap-3">
							<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-primary text-white"><FormIcon /></div>
							<div>
								<h3 className="text-lg font-bold text-slate-900 dark:text-white">{editForm ? 'Edit Form' : 'Create New Form'}</h3>
								<p className="text-xs text-slate-500">Define the fields the AI will collect from users</p>
							</div>
						</div>
						<button onClick={onClose} className="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
							<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
						</button>
					</div>
				</div>

				{/* Body */}
				<form id="form-builder-form" onSubmit={handleSubmit} className="px-6 py-5 space-y-5 overflow-y-auto flex-1">
					{/* Form metadata */}
					<div className="grid grid-cols-2 gap-4">
						<div className="col-span-2 sm:col-span-1">
							<label className={labelClass}>Form Name *</label>
							<input ref={nameRef} type="text" value={formName} onChange={e => setFormName(e.target.value)} className={inputClass} placeholder="e.g. Customer Support Form" required />
						</div>
						<div className="col-span-2 sm:col-span-1">
							<label className={labelClass}>Status</label>
							<select value={formStatus} onChange={e => setFormStatus(e.target.value)} className={inputClass + ' cursor-pointer'}>
								<option value="active">Active</option>
								<option value="draft">Draft</option>
							</select>
						</div>
					</div>
					<div>
						<label className={labelClass}>Description</label>
						<textarea value={formDescription} onChange={e => setFormDescription(e.target.value)} rows={2} className="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none" placeholder="Brief description of this form..." />
					</div>

					{/* Fields */}
					<div>
						<div className="flex items-center justify-between mb-3">
							<label className={labelClass + ' mb-0'}>Form Fields</label>
							<span className="text-xs text-slate-400">{fields.length} field{fields.length !== 1 ? 's' : ''}</span>
						</div>

						{fields.length === 0 ? (
							<div className="text-center py-8 border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-xl">
								<p className="text-sm text-slate-500 dark:text-slate-400 mb-3">No fields yet. Add your first field to get started.</p>
								<button type="button" onClick={addField} className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 transition">
									<PlusIcon /> Add Field
								</button>
							</div>
						) : (
							<div className="space-y-3">
								{fields.map((field, index) => (
									<div key={field.field_id} className="group relative bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-200 dark:border-slate-600 p-4 transition hover:border-primary/30 dark:hover:border-primary/50">
										<div className="flex items-start gap-3">
											{/* Reorder buttons */}
											<div className="flex flex-col gap-0.5 pt-1">
												<button type="button" onClick={() => moveField(index, -1)} disabled={index === 0} className="p-1 rounded text-slate-400 hover:text-primary disabled:opacity-30 disabled:cursor-not-allowed transition"><ChevronUpIcon /></button>
												<button type="button" onClick={() => moveField(index, 1)} disabled={index === fields.length - 1} className="p-1 rounded text-slate-400 hover:text-primary disabled:opacity-30 disabled:cursor-not-allowed transition"><ChevronDownIcon /></button>
											</div>

											{/* Field config */}
											<div className="flex-1 grid grid-cols-12 gap-3">
												<div className="col-span-4">
													<label className="text-xs text-slate-400 mb-1 block">Type</label>
													<select value={field.type} onChange={e => updateField(index, 'type', e.target.value)} className="w-full h-9 px-2 text-sm rounded-lg border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 transition cursor-pointer">
														{FIELD_TYPES.map(ft => <option key={ft.value} value={ft.value}>{ft.label}</option>)}
													</select>
												</div>
												<div className="col-span-4">
													<label className="text-xs text-slate-400 mb-1 block">Label *</label>
													<input type="text" value={field.label} onChange={e => updateField(index, 'label', e.target.value)} className="w-full h-9 px-2 text-sm rounded-lg border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 transition" placeholder="e.g. First Name" />
												</div>
												<div className="col-span-3">
													<label className="text-xs text-slate-400 mb-1 block">Placeholder</label>
													<input type="text" value={field.placeholder || ''} onChange={e => updateField(index, 'placeholder', e.target.value)} className="w-full h-9 px-2 text-sm rounded-lg border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 transition" placeholder="Optional" />
												</div>
												<div className="col-span-1 flex items-end justify-center pb-1">
													<label className="flex items-center gap-1 cursor-pointer" title="Required">
														<input type="checkbox" checked={field.required || false} onChange={e => updateField(index, 'required', e.target.checked)} className="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary" />
														<span className="text-xs text-slate-400">Req</span>
													</label>
												</div>
											</div>

											{/* Delete */}
											<button type="button" onClick={() => removeField(index)} className="p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition mt-4" title="Remove field"><TrashIcon /></button>
										</div>

										{/* Options for select type */}
										{(field.type === 'select' || field.type === 'checkbox') && (
											<div className="mt-3 ml-10">
												<label className="text-xs text-slate-400 mb-1 block">Options (one per line)</label>
												<textarea
													value={Array.isArray(field.options) ? field.options.join('\n') : ''}
													onChange={e => updateField(index, 'options', e.target.value.split('\n').filter(o => o.trim()))}
													rows={3}
													className="w-full px-2 py-1.5 text-sm rounded-lg border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 transition resize-none"
													placeholder="Option 1&#10;Option 2&#10;Option 3"
												/>
											</div>
										)}
									</div>
								))}

								<button type="button" onClick={addField} className="w-full py-3 border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-500 hover:border-primary/30 hover:text-primary dark:hover:border-primary/50 transition flex items-center justify-center gap-2">
									<PlusIcon /> Add Another Field
								</button>
							</div>
						)}
					</div>
				</form>

				{/* Footer */}
				<div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 shrink-0">
					<button type="button" onClick={onClose} className="h-10 px-5 text-sm font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700 transition">Cancel</button>
					<button type="submit" form="form-builder-form" disabled={saving || !formName.trim() || fields.length === 0} className="h-10 px-5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition inline-flex items-center gap-2">
						{saving && <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />}
						{saving ? 'Saving…' : (editForm ? 'Update Form' : 'Create Form')}
					</button>
				</div>
			</div>
		</div>
	);
};

// ─── Main FormsPage ──────────────────────────────────────────
export default function FormsPage() {
	const [view, setView] = useState('list'); // list | submissions | submission-detail
	const [forms, setForms] = useState([]);
	const [selectedForm, setSelectedForm] = useState(null);
	const [selectedSubmission, setSelectedSubmission] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [stats, setStats] = useState(null);
	const [notification, setNotification] = useState(null);
	const [showCreateModal, setShowCreateModal] = useState(false);
	const [editingForm, setEditingForm] = useState(null);
	const [saving, setSaving] = useState(false);
	const [page, setPage] = useState(1);
	const [totalPages, setTotalPages] = useState(1);
	const [total, setTotal] = useState(0);
	const [searchQuery, setSearchQuery] = useState('');
	const [statusFilter, setStatusFilter] = useState('');

	const apiBase = '/smart-ai-chatbot/v1';

	const fetchForms = useCallback(async () => {
		setLoading(true); setError(null);
		try {
			let path = `${apiBase}/forms?page=${page}&per_page=20`;
			if (searchQuery) path += `&search=${encodeURIComponent(searchQuery)}`;
			if (statusFilter) path += `&status=${statusFilter}`;
			const response = await apiFetch({ path });
			if (response.success) {
				setForms(response.data.forms || []);
				setTotalPages(response.data.pagination?.total_pages || 1);
				setTotal(response.data.pagination?.total || 0);
			} else { setError(response.error || 'Failed to load forms'); }
		} catch (err) { setError(err.message || 'Failed to load forms'); }
		finally { setLoading(false); }
	}, [apiBase, page, searchQuery, statusFilter]);

	const fetchStats = useCallback(async () => {
		try {
			const response = await apiFetch({ path: `${apiBase}/forms/stats` });
			if (response.success) setStats(response.data);
		} catch (err) { console.error('Failed to load form stats:', err); }
	}, [apiBase]);

	useEffect(() => { fetchForms(); }, [fetchForms]);
	useEffect(() => { fetchStats(); }, [fetchStats]);

	const showNotif = (message, status = 'success') => { setNotification({ message, status }); setTimeout(() => setNotification(null), 4000); };

	const handleCreateForm = async (formData) => {
		setSaving(true);
		try {
			if (editingForm) {
				const response = await apiFetch({ path: `${apiBase}/forms/${editingForm.id}`, method: 'PATCH', data: formData });
				if (response.success) { showNotif('Form updated successfully!'); setShowCreateModal(false); setEditingForm(null); fetchForms(); fetchStats(); }
				else { showNotif(response.error || 'Failed to update form', 'error'); }
			} else {
				const response = await apiFetch({ path: `${apiBase}/forms`, method: 'POST', data: formData });
				if (response.success) { showNotif('Form created successfully!'); setShowCreateModal(false); fetchForms(); fetchStats(); }
				else { showNotif(response.error || 'Failed to create form', 'error'); }
			}
		} catch (err) { showNotif(err.message || 'Failed to save form', 'error'); }
		finally { setSaving(false); }
	};

	const handleDeleteForm = async (id) => {
		if (!confirm('Are you sure you want to delete this form and ALL its submissions? This cannot be undone.')) return;
		try {
			await apiFetch({ path: `${apiBase}/forms/${id}`, method: 'DELETE' });
			showNotif('Form deleted');
			fetchForms(); fetchStats();
		} catch (err) { showNotif(err.message || 'Failed to delete', 'error'); }
	};

	const handleToggleStatus = async (form) => {
		const newStatus = form.status === 'active' ? 'draft' : 'active';
		try {
			await apiFetch({ path: `${apiBase}/forms/${form.id}`, method: 'PATCH', data: { status: newStatus } });
			showNotif(`Form ${newStatus === 'active' ? 'activated' : 'deactivated'}`);
			fetchForms(); fetchStats();
		} catch (err) { showNotif(err.message || 'Failed to update status', 'error'); }
	};

	const handleViewSubmissions = (form) => { setSelectedForm(form); setView('submissions'); };
	const handleEditForm = (form) => { setEditingForm(form); setShowCreateModal(true); };
	const handleBack = () => { setView('list'); setSelectedForm(null); setSelectedSubmission(null); };

	const handleViewSubmissionDetail = (submission) => { setSelectedSubmission(submission); setView('submission-detail'); };
	const handleBackToSubmissions = () => { setView('submissions'); setSelectedSubmission(null); };

	const handleResetFilters = () => { setSearchQuery(''); setStatusFilter(''); setPage(1); };
	const hasActiveFilters = searchQuery || statusFilter;

	return (
		<div className="min-h-screen bg-slate-50 dark:bg-slate-900">
			{notification && <div className={`fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 ${notification.status === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`}>{notification.message}</div>}

			<FormBuilderModal
				isOpen={showCreateModal}
				onClose={() => { setShowCreateModal(false); setEditingForm(null); }}
				onSave={handleCreateForm}
				saving={saving}
				editForm={editingForm}
			/>

			<div className="flex items-center justify-between px-8 py-3">
				<div className="flex items-center gap-3">
					{(view === 'submissions' || view === 'submission-detail') && (
						<button onClick={view === 'submission-detail' ? handleBackToSubmissions : handleBack} className="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all duration-200"><ArrowLeftIcon /></button>
					)}
					{view === 'submissions' && selectedForm && (
						<div>
							<h2 className="text-lg font-bold text-slate-900 dark:text-white">{selectedForm.form_name} — Submissions</h2>
							<p className="text-xs text-slate-500">{selectedForm.submission_count || 0} submissions</p>
						</div>
					)}
				</div>
				{view === 'list' && (
					<div className="flex items-center gap-3">
						<button onClick={() => { fetchForms(); fetchStats(); }} className="inline-flex items-center gap-2 h-10 px-4 text-sm rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-all"><RefreshIcon /> Refresh</button>
						<button onClick={() => { setEditingForm(null); setShowCreateModal(true); }} className="inline-flex items-center gap-2 h-10 px-5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 transition-all"><PlusIcon /> New Form</button>
					</div>
				)}
			</div>

			{error && <div className="mx-8 mt-2 px-5 py-4 rounded-2xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 shadow-sm"><p className="font-medium text-red-800 dark:text-red-300">{error}</p></div>}

			<main className="p-8">
				{/* ─── Stats Cards ─── */}
				{view === 'list' && stats && (
					<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
						{[
							{ label: 'Total Forms', value: stats.total_forms, color: 'primary', icon: <FormIcon />, sub: 'All forms', textColor: 'text-primary' },
							{ label: 'Active Forms', value: stats.active_forms, color: 'primary', icon: <FormIcon />, sub: 'Ready for AI collection', textColor: 'text-primary' },
							{ label: 'Total Submissions', value: stats.total_submissions, color: 'primary', icon: <FormIcon />, sub: 'All time', textColor: 'text-primary' },
							{ label: 'Pending Review', value: stats.complete_submissions, color: 'primary', icon: <FormIcon />, sub: 'Need attention', textColor: 'text-primary' },
						].map((card, i) => (
							<div key={i} className="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm hover:shadow-lg hover:border-primary/30 transition-all duration-300">
								<div className={`absolute top-0 right-0 w-32 h-32 bg-${card.color}/5 rounded-bl-full`} />
								<div className="relative flex items-start justify-between">
									<div>
										<p className="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">{card.label}</p>
										<p className={`text-3xl font-bold ${card.textColor || 'text-slate-900 dark:text-white'}`}>{card.value?.toLocaleString() || 0}</p>
									</div>
									<div className={`flex items-center justify-center w-12 h-12 rounded-2xl bg-${card.color} text-white`}>{card.icon}</div>
								</div>
								<div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700"><span className="text-xs text-slate-400 dark:text-slate-500">{card.sub}</span></div>
							</div>
						))}
					</div>
				)}

				{/* ─── Form List ─── */}
				{view === 'list' && (
					<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
						{/* Filters */}
						<div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
							<div className="flex flex-wrap items-center gap-3">
								<div className="relative flex-1 min-w-[220px] max-w-[340px]">
									<input type="text" placeholder="Search forms…" value={searchQuery} onChange={(e) => { setSearchQuery(e.target.value); setPage(1); }} className="w-full h-10 px-4 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition-all shadow-sm" />
									{searchQuery && <button onClick={() => { setSearchQuery(''); setPage(1); }} className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 transition"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg></button>}
								</div>
								<div className="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-600" />
								<select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="h-10 px-3 pr-8 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 transition cursor-pointer shadow-sm">
									<option value="">All Statuses</option>
									<option value="active">Active</option>
									<option value="draft">Draft</option>
									<option value="archived">Archived</option>
								</select>
								{hasActiveFilters && <button onClick={handleResetFilters} className="h-10 px-4 text-sm font-medium rounded-xl text-red-500 border border-red-200 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20 transition shadow-sm"><svg className="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>Clear</button>}
							</div>
						</div>

						{/* Table */}
						{loading ? (
							<div className="flex items-center justify-center py-20"><div className="w-8 h-8 border-3 border-primary border-t-transparent rounded-full animate-spin" /></div>
						) : forms.length === 0 ? (
							<div className="text-center py-20">
								<div className="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 mx-auto mb-4"><FormIcon /></div>
								<h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-1">{hasActiveFilters ? 'No forms match your filters' : 'No forms yet'}</h3>
								<p className="text-sm text-slate-500 dark:text-slate-400 mb-4">{hasActiveFilters ? 'Try adjusting your filters.' : 'Create your first form and let AI collect data from users.'}</p>
								{!hasActiveFilters && <button onClick={() => { setEditingForm(null); setShowCreateModal(true); }} className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 transition"><PlusIcon /> Create Your First Form</button>}
							</div>
						) : (
							<>
								<div className="overflow-x-auto">
									<table className="w-full text-sm">
										<thead><tr className="bg-slate-50 dark:bg-slate-700/50">
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Form</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Fields</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Submissions</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Status</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Created</th>
											<th className="px-6 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Actions</th>
										</tr></thead>
										<tbody className="divide-y divide-slate-100 dark:divide-slate-700">
											{forms.map((form) => (
												<tr key={form.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
													<td className="px-6 py-4">
														<div className="font-medium text-slate-800 dark:text-slate-100">{form.form_name}</div>
														{form.form_description && <div className="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[200px]">{form.form_description}</div>}
													</td>
													<td className="px-6 py-4">
														<span className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-sm font-semibold">{form.field_count || 0}</span>
													</td>
													<td className="px-6 py-4">
														<button onClick={() => handleViewSubmissions(form)} className="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
															{form.submission_count || 0} <EyeIcon />
														</button>
													</td>
													<td className="px-6 py-4"><StatusBadge status={form.status} /></td>
													<td className="px-6 py-4 text-slate-500 text-xs">{new Date(form.created_at).toLocaleDateString()}</td>
													<td className="px-6 py-4">
														<div className="flex items-center justify-end gap-1">
															<button onClick={() => handleViewSubmissions(form)} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="View Submissions"><EyeIcon /></button>
															<button onClick={() => handleEditForm(form)} className="p-2 rounded-lg text-slate-500 hover:bg-primary/10 dark:hover:bg-primary/20 hover:text-primary transition" title="Edit Form"><EditIcon /></button>
															<button onClick={() => handleToggleStatus(form)} className={`p-2 rounded-lg transition ${form.status === 'active' ? 'text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/30' : 'text-green-500 hover:bg-green-50 dark:hover:bg-green-900/30'}`} title={form.status === 'active' ? 'Deactivate' : 'Activate'}>
																<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={form.status === 'active' ? 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' : 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z'} /></svg>
															</button>
															<button onClick={() => handleDeleteForm(form.id)} className="p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition" title="Delete"><TrashIcon /></button>
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
				)}

				{/* ─── Submissions View ─── */}
				{view === 'submissions' && selectedForm && (
					<FormSubmissions
						form={selectedForm}
						onViewDetail={handleViewSubmissionDetail}
						onBack={handleBack}
					/>
				)}

				{/* ─── Submission Detail View ─── */}
				{view === 'submission-detail' && selectedSubmission && selectedForm && (
					<FormDetail
						submission={selectedSubmission}
						form={selectedForm}
						onBack={handleBackToSubmissions}
					/>
				)}
			</main>
		</div>
	);
}
