/**
 * Leads Page â€” Admin page for viewing and managing leads/requests
 *
 * Features stat cards, filterable table, detail view, and manual add modal.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import LeadDetail from './LeadDetail';

// Icons
const LeadIcon = () => (<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>);
const NewIcon = () => (<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>);
const QualifiedIcon = () => (<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>);
const ConvertedIcon = () => (<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>);
const ArrowLeftIcon = () => (<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>);
const RefreshIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>);
const PlusIcon = () => (<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>);

const StatusBadge = ({ status }) => {
	const styles = {
		new: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
		contacted: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700',
		qualified: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700',
		converted: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
		lost: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700',
	};
	const labels = { new: 'New', contacted: 'Contacted', qualified: 'Qualified', converted: 'Converted', lost: 'Lost' };
	return <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[status] || styles.new}`}>{labels[status] || status}</span>;
};

const PriorityBadge = ({ priority }) => {
	const colors = { low: 'text-slate-500', medium: 'text-blue-500', high: 'text-orange-500', urgent: 'text-red-500 font-bold' };
	return <span className={`text-xs capitalize ${colors[priority] || colors.medium}`}>{priority}</span>;
};

// Add Lead Modal
const AddLeadModal = ({ isOpen, onClose, onSave, saving }) => {
	const [form, setForm] = useState({ customer_name: '', customer_email: '', customer_phone: '', company: '', lead_type: 'general', priority: 'medium', request_summary: '', notes: '' });
	const nameRef = useRef(null);
	useEffect(() => { if (isOpen && nameRef.current) nameRef.current.focus(); if (isOpen) setForm({ customer_name: '', customer_email: '', customer_phone: '', company: '', lead_type: 'general', priority: 'medium', request_summary: '', notes: '' }); }, [isOpen]);
	if (!isOpen) return null;
	const handleChange = (key, val) => setForm(f => ({ ...f, [key]: val }));
	const handleSubmit = (e) => { e.preventDefault(); onSave(form); };
	const inputClass = "w-full h-10 px-3 text-sm rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all";
	const labelClass = "block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5";

	return (
		<div className="fixed inset-0 z-50 flex items-center justify-center p-4">
			<div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
			<div className="relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
				<div className="relative bg-gradient-to-r from-primary/10 via-primary/5 to-transparent px-6 py-5">
					<div className="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full" />
					<div className="relative flex items-center justify-between">
						<div className="flex items-center gap-3">
							<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-primary text-white"><PlusIcon /></div>
							<div><h3 className="text-lg font-bold text-slate-900 dark:text-white">New Lead</h3><p className="text-xs text-slate-500">Manually add a lead or request</p></div>
						</div>
						<button onClick={onClose} className="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition"><svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg></button>
					</div>
				</div>
				<form id="add-lead-form" onSubmit={handleSubmit} className="px-6 py-5 space-y-4 max-h-[65vh] overflow-y-auto">
					<div className="grid grid-cols-2 gap-4">
						<div className="col-span-2 sm:col-span-1"><label className={labelClass}>Name</label><input ref={nameRef} type="text" value={form.customer_name} onChange={e => handleChange('customer_name', e.target.value)} className={inputClass} placeholder="John Doe" /></div>
						<div className="col-span-2 sm:col-span-1"><label className={labelClass}>Email</label><input type="email" value={form.customer_email} onChange={e => handleChange('customer_email', e.target.value)} className={inputClass} placeholder="john@example.com" /></div>
					</div>
					<div className="grid grid-cols-2 gap-4">
						<div><label className={labelClass}>Phone</label><input type="tel" value={form.customer_phone} onChange={e => handleChange('customer_phone', e.target.value)} className={inputClass} placeholder="+1 234 567 890" /></div>
						<div><label className={labelClass}>Company</label><input type="text" value={form.company} onChange={e => handleChange('company', e.target.value)} className={inputClass} placeholder="Acme Inc." /></div>
					</div>
					<div className="grid grid-cols-2 gap-4">
						<div><label className={labelClass}>Type</label><select value={form.lead_type} onChange={e => handleChange('lead_type', e.target.value)} className={inputClass + ' cursor-pointer'}><option value="general">General</option><option value="inquiry">Inquiry</option><option value="quote_request">Quote Request</option><option value="support">Support</option><option value="feedback">Feedback</option></select></div>
						<div><label className={labelClass}>Priority</label><select value={form.priority} onChange={e => handleChange('priority', e.target.value)} className={inputClass + ' cursor-pointer'}><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
					</div>
					<div><label className={labelClass}>Request / Interest *</label><textarea value={form.request_summary} onChange={e => handleChange('request_summary', e.target.value)} rows={3} className="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none" placeholder="What is the customer interested in?" required /></div>
					<div><label className={labelClass}>Notes</label><textarea value={form.notes} onChange={e => handleChange('notes', e.target.value)} rows={2} className="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none" placeholder="Optional notes..." /></div>
				</form>
				<div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
					<button type="button" onClick={onClose} className="h-10 px-5 text-sm font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700 transition">Cancel</button>
					<button type="submit" form="add-lead-form" disabled={saving} className="h-10 px-5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition inline-flex items-center gap-2">
						{saving && <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />}
						{saving ? 'Savingâ€¦' : 'Add Lead'}
					</button>
				</div>
			</div>
		</div>
	);
};

export default function LeadsPage() {
	const [view, setView] = useState('list');
	const [leads, setLeads] = useState([]);
	const [selectedLead, setSelectedLead] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [stats, setStats] = useState(null);
	const [notification, setNotification] = useState(null);
	const [showAddModal, setShowAddModal] = useState(false);
	const [saving, setSaving] = useState(false);
	const [page, setPage] = useState(1);
	const [totalPages, setTotalPages] = useState(1);
	const [total, setTotal] = useState(0);
	const [searchQuery, setSearchQuery] = useState('');
	const [statusFilter, setStatusFilter] = useState('');
	const [typeFilter, setTypeFilter] = useState('');
	const [priorityFilter, setPriorityFilter] = useState('');
	const [dateFrom, setDateFrom] = useState('');
	const [dateTo, setDateTo] = useState('');

	const apiBase = '/quark-agentflow-ai/v1';

	const fetchLeads = useCallback(async () => {
		setLoading(true); setError(null);
		try {
			let path = `${apiBase}/leads?page=${page}&per_page=20`;
			if (searchQuery) path += `&search=${encodeURIComponent(searchQuery)}`;
			if (statusFilter) path += `&status=${statusFilter}`;
			if (typeFilter) path += `&lead_type=${typeFilter}`;
			if (priorityFilter) path += `&priority=${priorityFilter}`;
			if (dateFrom) path += `&date_from=${dateFrom}`;
			if (dateTo) path += `&date_to=${dateTo}`;
			const response = await apiFetch({ path });
			if (response.success) {
				setLeads(response.data.leads || []);
				setTotalPages(response.data.pagination?.total_pages || 1);
				setTotal(response.data.pagination?.total || 0);
			} else { setError(response.error || 'Failed to load leads'); }
		} catch (err) { setError(err.message || 'Failed to load leads'); }
		finally { setLoading(false); }
	}, [apiBase, page, searchQuery, statusFilter, typeFilter, priorityFilter, dateFrom, dateTo]);

	const fetchStats = useCallback(async () => {
		try {
			const response = await apiFetch({ path: `${apiBase}/leads/stats` });
			if (response.success) setStats(response.data);
		} catch (err) { console.error('Failed to load lead stats:', err); }
	}, [apiBase]);

	useEffect(() => { fetchLeads(); }, [fetchLeads]);
	useEffect(() => { fetchStats(); }, [fetchStats]);

	const showNotif = (message, status = 'success') => { setNotification({ message, status }); setTimeout(() => setNotification(null), 4000); };
	const handleViewDetail = (lead) => { setSelectedLead(lead); setView('detail'); };
	const handleBack = () => { setView('list'); setSelectedLead(null); };

	const handleUpdateStatus = async (id, newStatus) => {
		try {
			const response = await apiFetch({ path: `${apiBase}/leads/${id}`, method: 'PATCH', data: { status: newStatus } });
			if (response.success) { showNotif(`Lead marked as ${newStatus}`); fetchLeads(); fetchStats(); if (selectedLead?.id === id) setSelectedLead(response.data); }
		} catch (err) { showNotif(err.message || 'Failed to update lead', 'error'); }
	};

	const handleDelete = async (id) => {
		if (!confirm('Are you sure you want to permanently delete this lead?')) return;
		try { await apiFetch({ path: `${apiBase}/leads/${id}`, method: 'DELETE' }); showNotif('Lead deleted'); fetchLeads(); fetchStats(); if (selectedLead?.id === id) handleBack(); }
		catch (err) { showNotif(err.message || 'Failed to delete', 'error'); }
	};

	const handleAddLead = async (formData) => {
		setSaving(true);
		try {
			const response = await apiFetch({ path: `${apiBase}/leads`, method: 'POST', data: formData });
			if (response.success) { showNotif('Lead added successfully!'); setShowAddModal(false); fetchLeads(); fetchStats(); }
			else { showNotif(response.error || 'Failed to create lead', 'error'); }
		} catch (err) { showNotif(err.message || 'Failed to create lead', 'error'); }
		finally { setSaving(false); }
	};

	const handleResetFilters = () => { setSearchQuery(''); setStatusFilter(''); setTypeFilter(''); setPriorityFilter(''); setDateFrom(''); setDateTo(''); setPage(1); };
	const hasActiveFilters = searchQuery || statusFilter || typeFilter || priorityFilter || dateFrom || dateTo;

	return (
		<div className="min-h-screen bg-slate-50 dark:bg-slate-900">
			{notification && <div className={`fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 ${notification.status === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`}>{notification.message}</div>}
			<AddLeadModal isOpen={showAddModal} onClose={() => setShowAddModal(false)} onSave={handleAddLead} saving={saving} />

			<div className="flex items-center justify-between px-8 py-3">
				<div className="flex items-center gap-3">{view === 'detail' && <button onClick={handleBack} className="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all duration-200"><ArrowLeftIcon /></button>}</div>
				{view === 'list' && (
					<div className="flex items-center gap-3">
						<button onClick={() => { fetchLeads(); fetchStats(); }} className="inline-flex items-center gap-2 h-10 px-4 text-sm rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-all"><RefreshIcon />Refresh</button>
						<button onClick={() => setShowAddModal(true)} className="inline-flex items-center gap-2 h-10 px-5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 transition-all"><PlusIcon />Add Lead</button>
					</div>
				)}
			</div>

			{error && <div className="mx-8 mt-2 px-5 py-4 rounded-2xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 shadow-sm"><p className="font-medium text-red-800 dark:text-red-300">{error}</p></div>}

			<main className="p-8">
				{view === 'list' && stats && (
					<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
						{[
							{ label: 'Total Leads', value: stats.total, color: 'primary', icon: <LeadIcon />, sub: 'All time', textColor: 'text-primary' },
							{ label: 'New Today', value: stats.new_today, color: 'primary', icon: <NewIcon />, sub: 'Captured today', textColor: 'text-primary' },
							{ label: 'Qualified', value: stats.qualified, color: 'primary', icon: <QualifiedIcon />, sub: 'Ready for sales', textColor: 'text-primary' },
							{ label: 'Converted', value: stats.converted, color: 'primary', icon: <ConvertedIcon />, sub: 'Successfully converted', textColor: 'text-primary' },
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

				{view === 'list' && (
					<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
						<div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
							<div className="flex flex-wrap items-center gap-3">
								<div className="relative flex-1 min-w-[220px] max-w-[340px]">
									<input type="text" placeholder="Search by name, email, requestâ€¦" value={searchQuery} onChange={(e) => { setSearchQuery(e.target.value); setPage(1); }} className="w-full h-10 px-4 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white transition-all shadow-sm" />
									{searchQuery && <button onClick={() => { setSearchQuery(''); setPage(1); }} className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 transition"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg></button>}
								</div>
								<div className="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-600" />
								<select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="h-10 px-3 pr-8 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 transition cursor-pointer shadow-sm">
									<option value="">All Statuses</option><option value="new">New</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="converted">Converted</option><option value="lost">Lost</option>
								</select>
								<select value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} className="h-10 px-3 pr-8 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 transition cursor-pointer shadow-sm">
									<option value="">All Types</option><option value="general">General</option><option value="inquiry">Inquiry</option><option value="quote_request">Quote Request</option><option value="support">Support</option><option value="feedback">Feedback</option>
								</select>
								<select value={priorityFilter} onChange={(e) => { setPriorityFilter(e.target.value); setPage(1); }} className="h-10 px-3 pr-8 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 transition cursor-pointer shadow-sm">
									<option value="">All Priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option>
								</select>
								{hasActiveFilters && <button onClick={handleResetFilters} className="h-10 px-4 text-sm font-medium rounded-xl text-red-500 border border-red-200 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20 transition shadow-sm"><svg className="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>Clear</button>}
							</div>
						</div>

						{loading ? (
							<div className="flex items-center justify-center py-20"><div className="w-8 h-8 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin" /></div>
						) : leads.length === 0 ? (
							<div className="text-center py-20">
								<div className="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 mx-auto mb-4"><LeadIcon /></div>
								<h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-1">{hasActiveFilters ? 'No leads match your filters' : 'No leads yet'}</h3>
								<p className="text-sm text-slate-500 dark:text-slate-400 mb-4">{hasActiveFilters ? 'Try adjusting your filters.' : 'Leads captured by the AI chatbot will appear here.'}</p>
								{!hasActiveFilters && <button onClick={() => setShowAddModal(true)} className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 transition"><PlusIcon />Add Your First Lead</button>}
							</div>
						) : (
							<>
								<div className="overflow-x-auto">
									<table className="w-full text-sm">
										<thead><tr className="bg-slate-50 dark:bg-slate-700/50">
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Customer</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Request</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Type</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Priority</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Status</th>
											<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Date</th>
											<th className="px-6 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Actions</th>
										</tr></thead>
										<tbody className="divide-y divide-slate-100 dark:divide-slate-700">
											{leads.map((lead) => (
												<tr key={lead.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
													<td className="px-6 py-4">
														<div className="font-medium text-slate-800 dark:text-slate-100">{lead.customer_name || 'â€”'}</div>
														<div className="text-xs text-slate-500 dark:text-slate-400">{lead.customer_email || 'â€”'}</div>
													</td>
													<td className="px-6 py-4"><div className="text-slate-700 dark:text-slate-200 max-w-[200px] truncate">{lead.request_summary}</div></td>
													<td className="px-6 py-4"><span className="capitalize text-sm">{lead.lead_type?.replace(/_/g, ' ') || 'General'}</span></td>
													<td className="px-6 py-4"><PriorityBadge priority={lead.priority} /></td>
													<td className="px-6 py-4"><StatusBadge status={lead.status} /></td>
													<td className="px-6 py-4 text-slate-500 text-xs">{new Date(lead.created_at).toLocaleDateString()}</td>
													<td className="px-6 py-4">
														<div className="flex items-center justify-end gap-1">
															<button onClick={() => handleViewDetail(lead)} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="View Details"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></button>
															{lead.status === 'new' && <button onClick={() => handleUpdateStatus(lead.id, 'contacted')} className="p-2 rounded-lg text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition" title="Mark Contacted"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg></button>}
															{lead.status !== 'converted' && lead.status !== 'lost' && <button onClick={() => handleUpdateStatus(lead.id, 'converted')} className="p-2 rounded-lg text-green-500 hover:bg-green-50 dark:hover:bg-green-900/30 transition" title="Mark Converted"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg></button>}
															<button onClick={() => handleDelete(lead.id)} className="p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition" title="Delete"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
														</div>
													</td>
												</tr>
											))}
										</tbody>
									</table>
								</div>
								{totalPages > 1 && (
									<div className="flex items-center justify-between px-6 py-4 border-t border-slate-100 dark:border-slate-700">
										<p className="text-sm text-slate-500">Showing {((page - 1) * 20) + 1}â€“{Math.min(page * 20, total)} of {total}</p>
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

				{view === 'detail' && selectedLead && <LeadDetail lead={selectedLead} onUpdateStatus={handleUpdateStatus} onDelete={handleDelete} onBack={handleBack} />}
			</main>
		</div>
	);
}
