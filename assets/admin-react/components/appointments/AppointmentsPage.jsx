/**
 * Appointments Page — Metronic v9 Premium Style
 *
 * Admin page for viewing and managing appointments booked through the AI chatbot.
 * Features stat cards, filterable table, detail view, and manual booking modal.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import AppointmentDetail from './AppointmentDetail';
import AppointmentSettingsPanel from './AppointmentSettingsPanel';

// ── Icon components ──────────────────────────────────────────
const CalendarIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
	</svg>
);
const TodayIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
	</svg>
);
const UpcomingIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
	</svg>
);
const CancelIcon = () => (
	<svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
	</svg>
);
const ArrowLeftIcon = () => (
	<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
	</svg>
);
const RefreshIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
	</svg>
);
const PlusIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
	</svg>
);
const SettingsIcon = () => (
	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
		<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
	</svg>
);

// Status badge component
const StatusBadge = ({ status }) => {
	const styles = {
		booked: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
		completed: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
		canceled: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700',
		no_show: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700',
		pending: 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-700/30 dark:text-slate-300 dark:border-slate-600',
	};
	const labels = { booked: 'Booked', completed: 'Completed', canceled: 'Canceled', no_show: 'No Show', pending: 'Pending' };
	return (
		<span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[status] || styles.pending}`}>
			{labels[status] || status}
		</span>
	);
};

// Type badge
const TypeBadge = ({ type }) => {
	return (
		<span className="inline-flex items-center text-sm">
			<span className="capitalize">{type?.replace(/_/g, ' ') || 'General'}</span>
		</span>
	);
};

// ── Add Appointment Modal ─────────────────────────────────────
const AddAppointmentModal = ({ isOpen, onClose, onSave, saving }) => {
	const [form, setForm] = useState({
		customer_name: '', customer_email: '', customer_phone: '',
		appointment_type: 'consultation', date: '', time: '10:00',
		duration: 60, notes: '',
	});
	const nameRef = useRef(null);

	useEffect(() => {
		if (isOpen && nameRef.current) nameRef.current.focus();
		if (isOpen) setForm(f => ({ ...f, date: '', time: '10:00', customer_name: '', customer_email: '', customer_phone: '', appointment_type: 'consultation', duration: 60, notes: '' }));
	}, [isOpen]);

	if (!isOpen) return null;

	const handleChange = (key, val) => setForm(f => ({ ...f, [key]: val }));
	const handleSubmit = (e) => { e.preventDefault(); onSave(form); };

	const inputClass = "w-full h-10 px-3 text-sm rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all";
	const labelClass = "block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5";

	return (
		<div className="fixed inset-0 z-50 flex items-center justify-center p-4">
			{/* Backdrop */}
			<div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />

			{/* Modal */}
			<div className="relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
				{/* Header */}
				<div className="relative bg-gradient-to-r from-primary/10 via-blue-500/5 to-transparent px-6 py-5">
					<div className="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full" />
					<div className="relative flex items-center justify-between">
						<div className="flex items-center gap-3">
							<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-primary text-white">
								<PlusIcon />
							</div>
							<div>
								<h3 className="text-lg font-bold text-slate-900 dark:text-white">{__('New Appointment', 'smart-woo-chatbot')}</h3>
								<p className="text-xs text-slate-500 dark:text-slate-400">{__('Manually book an appointment', 'smart-woo-chatbot')}</p>
							</div>
						</div>
						<button onClick={onClose} className="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
							<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
						</button>
					</div>
				</div>

				{/* Form */}
				<form id="add-appointment-form" onSubmit={handleSubmit} className="px-6 py-5 space-y-4 max-h-[65vh] overflow-y-auto">
					<div className="grid grid-cols-2 gap-4">
						<div className="col-span-2 sm:col-span-1">
							<label className={labelClass}>{__('Customer Name', 'smart-woo-chatbot')}</label>
							<input ref={nameRef} type="text" value={form.customer_name} onChange={e => handleChange('customer_name', e.target.value)} className={inputClass} placeholder="John Doe" />
						</div>
						<div className="col-span-2 sm:col-span-1">
							<label className={labelClass}>{__('Email', 'smart-woo-chatbot')}</label>
							<input type="email" value={form.customer_email} onChange={e => handleChange('customer_email', e.target.value)} className={inputClass} placeholder="john@example.com" />
						</div>
					</div>

					<div className="grid grid-cols-2 gap-4">
						<div>
							<label className={labelClass}>{__('Phone', 'smart-woo-chatbot')}</label>
							<input type="tel" value={form.customer_phone} onChange={e => handleChange('customer_phone', e.target.value)} className={inputClass} placeholder="+1 234 567 890" />
						</div>
						<div>
							<label className={labelClass}>{__('Type', 'smart-woo-chatbot')}</label>
							<select value={form.appointment_type} onChange={e => handleChange('appointment_type', e.target.value)} className={inputClass + ' cursor-pointer'}>
								<option value="consultation">Consultation</option>
								<option value="demo">Demo</option>
								<option value="meeting">Meeting</option>
								<option value="followup">Follow-up</option>
								<option value="interview">Interview</option>
								<option value="support">Support</option>
							</select>
						</div>
					</div>

					<div className="grid grid-cols-3 gap-4">
						<div>
							<label className={labelClass}>{__('Date', 'smart-woo-chatbot')}</label>
							<input type="date" value={form.date} onChange={e => handleChange('date', e.target.value)} className={inputClass} />
						</div>
						<div>
							<label className={labelClass}>{__('Time', 'smart-woo-chatbot')}</label>
							<input type="time" value={form.time} onChange={e => handleChange('time', e.target.value)} className={inputClass} />
						</div>
						<div>
							<label className={labelClass}>{__('Duration', 'smart-woo-chatbot')}</label>
							<select value={form.duration} onChange={e => handleChange('duration', parseInt(e.target.value))} className={inputClass + ' cursor-pointer'}>
								<option value={15}>15 min</option>
								<option value={30}>30 min</option>
								<option value={45}>45 min</option>
								<option value={60}>60 min</option>
								<option value={90}>90 min</option>
								<option value={120}>2 hours</option>
							</select>
						</div>
					</div>

					<div>
						<label className={labelClass}>{__('Notes', 'smart-woo-chatbot')}</label>
						<textarea value={form.notes} onChange={e => handleChange('notes', e.target.value)} rows={3} className="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none" placeholder="Optional notes..." />
					</div>
				</form>

				{/* Footer */}
				<div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
					<button type="button" onClick={onClose} className="h-10 px-5 text-sm font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700 transition">
						{__('Cancel', 'smart-woo-chatbot')}
					</button>
					<button type="submit" form="add-appointment-form" disabled={saving}
						className="h-10 px-5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition inline-flex items-center gap-2">
						{saving && <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />}
						{saving ? __('Booking…', 'smart-woo-chatbot') : __('Book Appointment', 'smart-woo-chatbot')}
					</button>
				</div>
			</div>
		</div>
	);
};

export default function AppointmentsPage() {
	const [view, setView] = useState('list'); // 'list' | 'detail' | 'settings'
	const [appointments, setAppointments] = useState([]);
	const [selectedAppointment, setSelectedAppointment] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [stats, setStats] = useState(null);
	const [notification, setNotification] = useState(null);

	// Modal
	const [showAddModal, setShowAddModal] = useState(false);
	const [saving, setSaving] = useState(false);

	// Pagination
	const [page, setPage] = useState(1);
	const [totalPages, setTotalPages] = useState(1);
	const [total, setTotal] = useState(0);

	// Filters
	const [searchQuery, setSearchQuery] = useState('');
	const [statusFilter, setStatusFilter] = useState('');
	const [typeFilter, setTypeFilter] = useState('');
	const [dateFrom, setDateFrom] = useState('');
	const [dateTo, setDateTo] = useState('');

	const apiBase = '/smart-ai-chatbot/v1';

	// ── Fetch ──────────────────────────────
	const fetchAppointments = useCallback(async () => {
		setLoading(true);
		setError(null);
		try {
			let path = `${apiBase}/appointments?page=${page}&per_page=20`;
			if (searchQuery) path += `&search=${encodeURIComponent(searchQuery)}`;
			if (statusFilter) path += `&status=${statusFilter}`;
			if (typeFilter) path += `&appointment_type=${typeFilter}`;
			if (dateFrom) path += `&date_from=${dateFrom}`;
			if (dateTo) path += `&date_to=${dateTo}`;
			const response = await apiFetch({ path });
			if (response.success) {
				setAppointments(response.data.appointments || []);
				setTotalPages(response.data.pagination?.total_pages || 1);
				setTotal(response.data.pagination?.total || 0);
			} else {
				setError(response.error || 'Failed to load appointments');
			}
		} catch (err) {
			setError(err.message || 'Failed to load appointments');
		} finally {
			setLoading(false);
		}
	}, [apiBase, page, searchQuery, statusFilter, typeFilter, dateFrom, dateTo]);

	const fetchStats = useCallback(async () => {
		try {
			const response = await apiFetch({ path: `${apiBase}/appointments/stats` });
			if (response.success) setStats(response.data);
		} catch (err) {
			console.error('Failed to load appointment stats:', err);
		}
	}, [apiBase]);

	useEffect(() => { fetchAppointments(); }, [fetchAppointments]);
	useEffect(() => { fetchStats(); }, [fetchStats]);

	// ── Actions ────────────────────────────
	const showNotif = (message, status = 'success') => {
		setNotification({ message, status });
		setTimeout(() => setNotification(null), 4000);
	};

	const handleViewDetail = (apt) => { setSelectedAppointment(apt); setView('detail'); };
	const handleBack = () => { setView('list'); setSelectedAppointment(null); };

	const handleUpdateStatus = async (id, newStatus) => {
		try {
			const response = await apiFetch({ path: `${apiBase}/appointments/${id}`, method: 'PATCH', data: { status: newStatus } });
			if (response.success) {
				showNotif(`Appointment ${newStatus} successfully`);
				fetchAppointments();
				fetchStats();
				if (selectedAppointment?.id === id) setSelectedAppointment(response.data);
			}
		} catch (err) {
			showNotif(err.message || 'Failed to update appointment', 'error');
		}
	};

	const handleDelete = async (id) => {
		if (!confirm(__('Are you sure you want to permanently delete this appointment?', 'smart-woo-chatbot'))) return;
		try {
			await apiFetch({ path: `${apiBase}/appointments/${id}`, method: 'DELETE' });
			showNotif('Appointment deleted');
			fetchAppointments(); fetchStats();
			if (selectedAppointment?.id === id) handleBack();
		} catch (err) { showNotif(err.message || 'Failed to delete', 'error'); }
	};

	const handleAddAppointment = async (formData) => {
		setSaving(true);
		try {
			const response = await apiFetch({
				path: `${apiBase}/appointments`,
				method: 'POST',
				data: formData,
			});
			if (response.success) {
				showNotif('Appointment booked successfully!');
				setShowAddModal(false);
				fetchAppointments();
				fetchStats();
			} else {
				showNotif(response.error || 'Failed to create appointment', 'error');
			}
		} catch (err) {
			showNotif(err.message || err.error || 'Failed to create appointment', 'error');
		} finally {
			setSaving(false);
		}
	};

	const handleResetFilters = () => {
		setSearchQuery(''); setStatusFilter(''); setTypeFilter(''); setDateFrom(''); setDateTo(''); setPage(1);
	};
	const hasActiveFilters = searchQuery || statusFilter || typeFilter || dateFrom || dateTo;

	// ── Render ──────────────────────────────
	return (
		<div className="min-h-screen bg-slate-50 dark:bg-slate-900">
			{/* Notification Toast */}
			{notification && (
				<div className={`fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 ${notification.status === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`}>
					{notification.message}
				</div>
			)}

			{/* Add Appointment Modal */}
			<AddAppointmentModal isOpen={showAddModal} onClose={() => setShowAddModal(false)} onSave={handleAddAppointment} saving={saving} />

			{/* Action Bar */}
			<div className="flex items-center justify-between px-8 py-3">
				<div className="flex items-center gap-3">
					{view === 'detail' && (
						<button onClick={handleBack} className="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all duration-200">
							<ArrowLeftIcon />
						</button>
					)}
				</div>
				{view === 'list' && (
					<div className="flex items-center gap-3">
						<button onClick={() => { fetchAppointments(); fetchStats(); }} className="inline-flex items-center gap-2 h-10 px-4 text-sm rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-all">
							<RefreshIcon />
							{__('Refresh', 'smart-woo-chatbot')}
						</button>
						<button onClick={() => setView('settings')} className="inline-flex items-center gap-2 h-10 px-4 text-sm rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-all">
							<SettingsIcon />
							{__('Settings', 'smart-woo-chatbot')}
						</button>
						<button onClick={() => setShowAddModal(true)} className="inline-flex items-center gap-2 h-10 px-5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 transition-all">
							<PlusIcon />
							{__('Add Appointment', 'smart-woo-chatbot')}
						</button>
					</div>
				)}
			</div>

			{/* Error Alert */}
			{error && (
				<div className="mx-8 mt-2 px-5 py-4 rounded-2xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 shadow-sm">
					<div className="flex items-center gap-3">
						<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500 text-white">
							<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
						</div>
						<div>
							<p className="font-medium text-red-800 dark:text-red-300">{__('Error', 'smart-woo-chatbot')}</p>
							<p className="text-sm text-red-600 dark:text-red-400">{error}</p>
						</div>
					</div>
				</div>
			)}

			{/* Content */}
			<main className="p-8">
				{/* ── Stat Cards ────────────────── */}
				{view === 'list' && stats && (
					<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
						{[
							{ label: __('Total Appointments', 'smart-woo-chatbot'), value: stats.total, color: 'primary', icon: <CalendarIcon />, sub: __('All time bookings', 'smart-woo-chatbot'), textColor: 'text-primary' },
							{ label: __("Today's", 'smart-woo-chatbot'), value: stats.today, color: 'primary', icon: <TodayIcon />, sub: __('Appointments today', 'smart-woo-chatbot'), textColor: 'text-primary' },
							{ label: __('Upcoming', 'smart-woo-chatbot'), value: stats.upcoming, color: 'primary', icon: <UpcomingIcon />, sub: __('Next 7 days', 'smart-woo-chatbot'), textColor: 'text-primary' },
							{ label: __('Canceled', 'smart-woo-chatbot'), value: stats.canceled, color: 'primary', icon: <CancelIcon />, sub: __('Total canceled', 'smart-woo-chatbot'), textColor: 'text-primary' },
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
								<div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
									<span className="text-xs text-slate-400 dark:text-slate-500">{card.sub}</span>
								</div>
							</div>
						))}
					</div>
				)}

				{/* ── Appointment List ────────────── */}
				{view === 'list' && (
					<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
						{/* Filters Bar — Redesigned */}
						<div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
							<div className="flex flex-wrap items-center gap-3">
								{/* Search — Premium design */}
								<div className="relative flex-1 min-w-[220px] max-w-[340px]">
									<input
										type="text"
										placeholder={__('Search by name, email…', 'smart-woo-chatbot')}
										value={searchQuery}
										onChange={(e) => { setSearchQuery(e.target.value); setPage(1); }}
										className="w-full h-10 px-4 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-slate-700 transition-all shadow-sm"
									/>
									{searchQuery && (
										<button onClick={() => { setSearchQuery(''); setPage(1); }} className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 transition">
											<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
										</button>
									)}
								</div>

								{/* Divider */}
								<div className="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-600" />

								{/* Status filter */}
								<select
									value={statusFilter}
									onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
									className="h-10 px-3 pr-8 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition cursor-pointer shadow-sm"
								>
									<option value="">{__('All Statuses', 'smart-woo-chatbot')}</option>
									<option value="booked">{__('Booked', 'smart-woo-chatbot')}</option>
									<option value="completed">{__('Completed', 'smart-woo-chatbot')}</option>
									<option value="canceled">{__('Canceled', 'smart-woo-chatbot')}</option>
									<option value="no_show">{__('No Show', 'smart-woo-chatbot')}</option>
								</select>

								{/* Type filter */}
								<select
									value={typeFilter}
									onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }}
									className="h-10 px-3 pr-8 text-sm rounded-xl border border-slate-200 bg-slate-50/80 text-slate-700 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition cursor-pointer shadow-sm"
								>
									<option value="">{__('All Types', 'smart-woo-chatbot')}</option>
									<option value="consultation">{__('Consultation', 'smart-woo-chatbot')}</option>
									<option value="demo">{__('Demo', 'smart-woo-chatbot')}</option>
									<option value="meeting">{__('Meeting', 'smart-woo-chatbot')}</option>
									<option value="followup">{__('Follow-up', 'smart-woo-chatbot')}</option>
									<option value="interview">{__('Interview', 'smart-woo-chatbot')}</option>
									<option value="support">{__('Support', 'smart-woo-chatbot')}</option>
								</select>

								{/* Date range */}
								<div className="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-600" />
								<div className="flex items-center gap-2">
									<input type="date" value={dateFrom} onChange={(e) => { setDateFrom(e.target.value); setPage(1); }}
										className="h-10 px-3 text-sm rounded-xl border border-slate-200 bg-slate-50/80 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 transition shadow-sm" />
									<span className="text-slate-300 dark:text-slate-500 text-lg">→</span>
									<input type="date" value={dateTo} onChange={(e) => { setDateTo(e.target.value); setPage(1); }}
										className="h-10 px-3 text-sm rounded-xl border border-slate-200 bg-slate-50/80 dark:border-slate-600 dark:bg-slate-700/80 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 transition shadow-sm" />
								</div>

								{/* Clear */}
								{hasActiveFilters && (
									<button onClick={handleResetFilters} className="h-10 px-4 text-sm font-medium rounded-xl text-red-500 border border-red-200 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20 transition shadow-sm">
										<svg className="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
										{__('Clear', 'smart-woo-chatbot')}
									</button>
								)}
							</div>
						</div>

						{/* Table */}
						{loading ? (
							<div className="flex items-center justify-center py-20">
								<div className="w-8 h-8 border-3 border-primary border-t-transparent rounded-full animate-spin" />
							</div>
						) : appointments.length === 0 ? (
							<div className="text-center py-20">
								<div className="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 mx-auto mb-4">
									<CalendarIcon />
								</div>
								<h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-1">
									{hasActiveFilters ? __('No appointments match your filters', 'smart-woo-chatbot') : __('No appointments yet', 'smart-woo-chatbot')}
								</h3>
								<p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
									{hasActiveFilters ? __('Try adjusting your filters.', 'smart-woo-chatbot') : __('Appointments booked through the chatbot will appear here.', 'smart-woo-chatbot')}
								</p>
								{!hasActiveFilters && (
									<button onClick={() => setShowAddModal(true)} className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-primary text-white shadow-sm hover:bg-primary/90 transition">
										<PlusIcon />
										{__('Book Your First Appointment', 'smart-woo-chatbot')}
									</button>
								)}
							</div>
						) : (
							<>
								<div className="overflow-x-auto">
									<table className="w-full text-sm">
										<thead>
											<tr className="bg-slate-50 dark:bg-slate-700/50">
												<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">{__('Date & Time', 'smart-woo-chatbot')}</th>
												<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">{__('Customer', 'smart-woo-chatbot')}</th>
												<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">{__('Type', 'smart-woo-chatbot')}</th>
												<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">{__('Duration', 'smart-woo-chatbot')}</th>
												<th className="px-6 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">{__('Status', 'smart-woo-chatbot')}</th>
												<th className="px-6 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">{__('Actions', 'smart-woo-chatbot')}</th>
											</tr>
										</thead>
										<tbody className="divide-y divide-slate-100 dark:divide-slate-700">
											{appointments.map((apt) => {
												const dateObj = new Date(apt.start_datetime);
												const isPast = dateObj < new Date();
												return (
													<tr key={apt.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
														<td className="px-6 py-4">
															<div className="font-medium text-slate-800 dark:text-slate-100">
																{dateObj.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
															</div>
															<div className="text-xs text-slate-500 dark:text-slate-400">
																{apt.time} – {apt.end_time}
																{apt.google_calendar_synced && <span className="ml-1 text-blue-500" title="Synced to Google Calendar">📅</span>}
															</div>
														</td>
														<td className="px-6 py-4">
															<div className="font-medium text-slate-800 dark:text-slate-100">{apt.customer_name}</div>
															<div className="text-xs text-slate-500 dark:text-slate-400">{apt.customer_email}</div>
														</td>
														<td className="px-6 py-4"><TypeBadge type={apt.appointment_type} /></td>
														<td className="px-6 py-4 text-slate-600 dark:text-slate-300">{apt.duration_minutes} min</td>
														<td className="px-6 py-4"><StatusBadge status={apt.status} /></td>
														<td className="px-6 py-4">
															<div className="flex items-center justify-end gap-1">
																<button onClick={() => handleViewDetail(apt)} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title={__('View Details', 'smart-woo-chatbot')}>
																	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
																</button>
																{apt.status === 'booked' && (
																	<>
																		<button onClick={() => handleUpdateStatus(apt.id, 'completed')} className="p-2 rounded-lg text-green-500 hover:bg-green-50 dark:hover:bg-green-900/30 transition" title={__('Complete', 'smart-woo-chatbot')}>
																			<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
																		</button>
																		<button onClick={() => handleUpdateStatus(apt.id, 'canceled')} className="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition" title={__('Cancel', 'smart-woo-chatbot')}>
																			<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
																		</button>
																	</>
																)}
																{isPast && apt.status === 'booked' && (
																	<button onClick={() => handleUpdateStatus(apt.id, 'no_show')} className="p-2 rounded-lg text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition" title={__('No Show', 'smart-woo-chatbot')}>
																		<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
																	</button>
																)}
																<button onClick={() => handleDelete(apt.id)} className="p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition" title={__('Delete', 'smart-woo-chatbot')}>
																	<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
																</button>
															</div>
														</td>
													</tr>
												);
											})}
										</tbody>
									</table>
								</div>

								{/* Pagination */}
								{totalPages > 1 && (
									<div className="flex items-center justify-between px-6 py-4 border-t border-slate-100 dark:border-slate-700">
										<p className="text-sm text-slate-500 dark:text-slate-400">
											{__('Showing', 'smart-woo-chatbot')} {((page - 1) * 20) + 1}–{Math.min(page * 20, total)} {__('of', 'smart-woo-chatbot')} {total}
										</p>
										<div className="flex items-center gap-1">
											<button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
												className="h-9 px-3 text-sm rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
												{__('Previous', 'smart-woo-chatbot')}
											</button>
											<span className="px-3 text-sm text-slate-600 dark:text-slate-300">{page} / {totalPages}</span>
											<button onClick={() => setPage(Math.min(totalPages, page + 1))} disabled={page === totalPages}
												className="h-9 px-3 text-sm rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
												{__('Next', 'smart-woo-chatbot')}
											</button>
										</div>
									</div>
								)}
							</>
						)}
					</div>
				)}

				{/* ── Detail View ────────── */}
				{view === 'detail' && selectedAppointment && (
					<AppointmentDetail appointment={selectedAppointment} onUpdateStatus={handleUpdateStatus} onDelete={handleDelete} onBack={handleBack} />
				)}

				{/* ── Settings View ────────── */}
				{view === 'settings' && (
					<AppointmentSettingsPanel onClose={() => setView('list')} />
				)}
			</main>
		</div>
	);
}
