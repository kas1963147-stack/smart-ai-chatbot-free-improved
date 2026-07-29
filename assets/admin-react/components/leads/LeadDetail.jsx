/**
 * Lead Detail View
 *
 * Shows full details of a single lead with action buttons.
 */
import { __ } from '@wordpress/i18n';
import { HelpCircle, DollarSign, Wrench, MessageSquare, ClipboardList } from 'lucide-react';

const StatusBadge = ({ status }) => {
	const styles = {
		new: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
		contacted: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700',
		qualified: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700',
		converted: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
		lost: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700',
	};
	const labels = { new: 'New', contacted: 'Contacted', qualified: 'Qualified', converted: 'Converted', lost: 'Lost' };
	return (
		<span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold border ${styles[status] || styles.new}`}>
			{labels[status] || status}
		</span>
	);
};

const PriorityBadge = ({ priority }) => {
	const styles = {
		low: 'bg-slate-50 text-slate-600 border-slate-200',
		medium: 'bg-blue-50 text-blue-600 border-blue-200',
		high: 'bg-orange-50 text-orange-600 border-orange-200',
		urgent: 'bg-red-50 text-red-600 border-red-200',
	};
	return (
		<span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border capitalize ${styles[priority] || styles.medium}`}>
			{priority}
		</span>
	);
};

const InfoRow = ({ icon, label, value, muted }) => (
	<div className="flex items-start gap-4 py-3">
		<div className="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex-shrink-0">{icon}</div>
		<div className="min-w-0 flex-1">
			<p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-0.5">{label}</p>
			<p className={`text-sm font-medium ${muted ? 'text-slate-400 italic' : 'text-slate-800 dark:text-slate-100'}`}>{value}</p>
		</div>
	</div>
);

export default function LeadDetail({ lead, onUpdateStatus, onDelete }) {
	if (!lead) return null;
	const typeIcons = { 
		inquiry: <HelpCircle className="w-7 h-7 text-blue-500" />, 
		quote_request: <DollarSign className="w-7 h-7 text-emerald-500" />, 
		support: <Wrench className="w-7 h-7 text-amber-500" />, 
		feedback: <MessageSquare className="w-7 h-7 text-purple-500" />, 
		general: <ClipboardList className="w-7 h-7 text-slate-500" /> 
	};

	return (
		<div className="space-y-6">
			<div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
				<div className="relative bg-gradient-to-r from-emerald-500/10 via-teal-500/5 to-transparent px-8 py-6">
					<div className="absolute top-0 right-0 w-48 h-48 bg-emerald-500/5 rounded-bl-full" />
					<div className="relative flex items-center justify-between">
						<div className="flex items-center gap-4 mb-2">
							<div className="flex items-center justify-center p-2.5 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700">
								{typeIcons[lead.lead_type] || <ClipboardList className="w-7 h-7 text-slate-500" />}
							</div>
							<div>
								<h2 className="text-xl font-bold text-slate-900 dark:text-white capitalize">{lead.lead_type?.replace(/_/g, ' ') || 'Lead'}</h2>
								<p className="text-sm text-slate-500">#{lead.id} · Created {new Date(lead.created_at).toLocaleDateString()}</p>
							</div>
						</div>
						<div className="flex items-center gap-2">
							<PriorityBadge priority={lead.priority} />
							<StatusBadge status={lead.status} />
						</div>
					</div>
				</div>

				<div className="px-8 py-6">
					<div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1">
						<div className="space-y-1">
							<InfoRow icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>} label="Customer" value={lead.customer_name || 'Not provided'} muted={!lead.customer_name} />
							<InfoRow icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>} label="Email" value={lead.customer_email || 'Not provided'} muted={!lead.customer_email} />
							<InfoRow icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>} label="Phone" value={lead.customer_phone || 'Not provided'} muted={!lead.customer_phone} />
						</div>
						<div className="space-y-1">
							<InfoRow icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>} label="Company" value={lead.company || 'Not provided'} muted={!lead.company} />
							<InfoRow icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>} label="Source" value={lead.source === 'chatbot' ? 'AI Chatbot' : 'Manual Entry'} />
							{lead.contacted_at && <InfoRow icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>} label="Contacted At" value={new Date(lead.contacted_at).toLocaleString()} />}
						</div>
					</div>

					<div className="mt-6 pt-6 border-t border-slate-100 dark:border-slate-700">
						<p className="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Request / Interest</p>
						<div className="bg-slate-50 dark:bg-slate-700/50 rounded-xl p-4 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{lead.request_summary}</div>
					</div>

					{lead.notes && (
						<div className="mt-4">
							<p className="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Admin Notes</p>
							<div className="bg-amber-50/50 dark:bg-amber-900/10 rounded-xl p-4 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap border border-amber-100 dark:border-amber-800/30">{lead.notes}</div>
						</div>
					)}
				</div>
			</div>

			<div className="flex flex-wrap items-center gap-3">
				{lead.status === 'new' && <button onClick={() => onUpdateStatus(lead.id, 'contacted')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-semibold shadow-sm hover:bg-amber-600 transition"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>Mark Contacted</button>}
				{(lead.status === 'new' || lead.status === 'contacted') && <button onClick={() => onUpdateStatus(lead.id, 'qualified')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-500 text-white text-sm font-semibold shadow-sm hover:bg-purple-600 transition"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>Mark Qualified</button>}
				{lead.status !== 'converted' && lead.status !== 'lost' && <button onClick={() => onUpdateStatus(lead.id, 'converted')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-green-500 text-white text-sm font-semibold shadow-sm hover:bg-green-600 transition"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>Mark Converted</button>}
				{lead.status !== 'lost' && lead.status !== 'converted' && <button onClick={() => onUpdateStatus(lead.id, 'lost')} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-red-200 text-red-600 text-sm font-semibold hover:bg-red-50 transition"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>Mark Lost</button>}
				<button onClick={() => onDelete(lead.id)} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition"><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>Delete</button>
			</div>
		</div>
	);
}
