/**
 * Appointment Detail View
 *
 * Shows full details of a single appointment with action buttons.
 * Matches SessionDetail styling pattern.
 */
import { __ } from '@wordpress/i18n';

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
		<span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold border ${styles[status] || styles.pending}`}>
			{labels[status] || status}
		</span>
	);
};

const InfoRow = ({ icon, label, value, muted }) => (
	<div className="flex items-start gap-4 py-3">
		<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex-shrink-0">
			{icon}
		</div>
		<div className="min-w-0 flex-1">
			<p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-0.5">{label}</p>
			<p className={`text-sm font-medium ${muted ? 'text-slate-400 dark:text-slate-500 italic' : 'text-slate-800 dark:text-slate-100'}`}>{value}</p>
		</div>
	</div>
);

export default function AppointmentDetail({ appointment, onUpdateStatus, onDelete, onBack }) {
	if (!appointment) return null;

	const hasDateTime = appointment.start_datetime && appointment.start_datetime !== '0000-00-00 00:00:00';
	const dateObj = hasDateTime ? new Date(appointment.start_datetime) : null;
	const isValidDate = dateObj && !isNaN(dateObj.getTime());
	const isPast = isValidDate ? dateObj < new Date() : false;
	const typeIcons = { consultation: '', demo: '', meeting: '', followup: '', interview: '', support: '' };

	const dateTimeDisplay = isValidDate
		? `${dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })} · ${appointment.time || '—'} – ${appointment.end_time || '—'}`
		: 'Not yet scheduled';

	return (
		<div className="space-y-6">
			{/* Header Card */}
			<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
				{/* Gradient header */}
				<div className="relative bg-gradient-to-r from-primary/10 via-blue-500/5 to-transparent px-8 py-6">
					<div className="absolute top-0 right-0 w-48 h-48 bg-primary/5 rounded-bl-full" />
					<div className="relative flex items-start justify-between">
						<div>
							<div className="flex items-center gap-3 mb-2">
								<span className="text-3xl">{typeIcons[appointment.appointment_type] || '📅'}</span>
								<div>
									<h2 className="text-xl font-bold text-slate-900 dark:text-white capitalize">
										{appointment.appointment_type?.replace(/_/g, ' ') || 'Appointment'}
									</h2>
									<p className="text-sm text-slate-500 dark:text-slate-400">
										#{appointment.id} · {__('Created', 'smart-woo-chatbot')} {new Date(appointment.created_at).toLocaleDateString()}
									</p>
								</div>
							</div>
						</div>
						<StatusBadge status={appointment.status} />
					</div>
				</div>

				{/* Info Grid */}
				<div className="px-8 py-6">
					<div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 divide-y md:divide-y-0 divide-slate-100 dark:divide-slate-700">
						{/* Left Column */}
						<div className="space-y-1">
							<InfoRow
								icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>}
								label={__('Date & Time', 'smart-woo-chatbot')}
								value={dateTimeDisplay}
								muted={!isValidDate}
							/>
							<InfoRow
								icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
								label={__('Duration', 'smart-woo-chatbot')}
								value={appointment.duration_minutes ? `${appointment.duration_minutes} minutes` : 'Not set'}
								muted={!appointment.duration_minutes}
							/>
							<InfoRow
								icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
								label={__('Google Calendar', 'smart-woo-chatbot')}
								value={appointment.google_calendar_synced ? '✅ Synced' : '— Not synced'}
								muted={!appointment.google_calendar_synced}
							/>
						</div>

						{/* Right Column */}
						<div className="space-y-1">
							<InfoRow
								icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>}
								label={__('Customer', 'smart-woo-chatbot')}
								value={appointment.customer_name}
							/>
							<InfoRow
								icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>}
								label={__('Email', 'smart-woo-chatbot')}
								value={appointment.customer_email}
							/>
							<InfoRow
								icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>}
								label={__('Phone', 'smart-woo-chatbot')}
								value={appointment.customer_phone || 'Not provided'}
								muted={!appointment.customer_phone}
							/>
							{appointment.conversation_id && (
								<InfoRow
									icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>}
									label={__('Chat History', 'smart-woo-chatbot')}
									value={
										<a 
											href={`admin.php?page=smart-ai-chatbot&tab=history&session=${appointment.conversation_id}`} 
											className="inline-flex items-center gap-1 text-primary hover:text-primary/80 transition-colors"
										>
											{__('View Conversation', 'smart-woo-chatbot')}
											<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
										</a>
									}
								/>
							)}
						</div>
					</div>

					{/* Notes */}
					{appointment.notes && (
						<div className="mt-6 pt-6 border-t border-slate-100 dark:border-slate-700">
							<p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">{__('Notes', 'smart-woo-chatbot')}</p>
							<div className="bg-slate-50 dark:bg-slate-700/50 rounded-xl p-4 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">
								{appointment.notes}
							</div>
						</div>
					)}
				</div>
			</div>

			{/* Action Buttons */}
			<div className="flex flex-wrap items-center gap-3">
				{appointment.status === 'booked' && (
					<>
						<button
							onClick={() => onUpdateStatus(appointment.id, 'completed')}
							className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-green-500 text-white text-sm font-semibold shadow-sm hover:bg-green-600 transition"
						>
							<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
							{__('Mark Complete', 'smart-woo-chatbot')}
						</button>
						<button
							onClick={() => onUpdateStatus(appointment.id, 'canceled')}
							className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-red-200 text-red-600 text-sm font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 transition"
						>
							<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
							{__('Cancel Appointment', 'smart-woo-chatbot')}
						</button>
						{isPast && (
							<button
								onClick={() => onUpdateStatus(appointment.id, 'no_show')}
								className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-amber-200 text-amber-600 text-sm font-semibold hover:bg-amber-50 dark:hover:bg-amber-900/20 transition"
							>
								<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
								{__('No Show', 'smart-woo-chatbot')}
							</button>
						)}
					</>
				)}
				<button
					onClick={() => onDelete(appointment.id)}
					className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-red-50 hover:text-red-600 hover:border-red-200 dark:hover:bg-red-900/20 transition"
				>
					<svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
					{__('Delete', 'smart-woo-chatbot')}
				</button>
			</div>
		</div>
	);
}
