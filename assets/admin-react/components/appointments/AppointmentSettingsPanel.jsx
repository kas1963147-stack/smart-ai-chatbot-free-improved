/**
 * Appointment Settings Panel
 * 
 * Admin UI for configuring appointment settings:
 * - Max concurrent slots (capacity)
 * - Appointment types with per-type duration & capacity
 * - Buffer/break time before & after
 * - Business hours per day
 * - Minimum advance booking
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Settings, Clock, Users, Calendar, Plus, Trash2, ArrowLeft, Save } from 'lucide-react';

const DURATION_OPTIONS = [
	{ value: 15, label: '15 min' },
	{ value: 30, label: '30 min' },
	{ value: 45, label: '45 min' },
	{ value: 60, label: '1 hour' },
	{ value: 90, label: '1.5 hours' },
	{ value: 120, label: '2 hours' },
	{ value: 180, label: '3 hours' },
	{ value: 240, label: '4 hours' },
];

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const DAY_LABELS = { monday: 'Monday', tuesday: 'Tuesday', wednesday: 'Wednesday', thursday: 'Thursday', friday: 'Friday', saturday: 'Saturday', sunday: 'Sunday' };

export default function AppointmentSettingsPanel({ onClose }) {
	const [settings, setSettings] = useState(null);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [notification, setNotification] = useState(null);
	const [dirty, setDirty] = useState(false);

	// Load settings on mount
	useEffect(() => {
		loadSettings();
	}, []);

	const loadSettings = async () => {
		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/appointments/settings',
				method: 'GET',
			});
			if (response.success) {
				setSettings(response.settings);
			}
		} catch (err) {
			setNotification({ type: 'error', message: 'Failed to load appointment settings' });
		} finally {
			setLoading(false);
		}
	};

	const saveSettings = async () => {
		setSaving(true);
		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/appointments/settings',
				method: 'POST',
				data: settings,
			});
			if (response.success) {
				setSettings(response.settings);
				setDirty(false);
				setNotification({ type: 'success', message: 'Settings saved successfully!' });
			} else {
				setNotification({ type: 'error', message: response.message || 'Failed to save' });
			}
		} catch (err) {
			setNotification({ type: 'error', message: err.message || 'Failed to save settings' });
		} finally {
			setSaving(false);
		}
	};

	const updateField = useCallback((key, value) => {
		setSettings(prev => ({ ...prev, [key]: value }));
		setDirty(true);
	}, []);

	const updateBusinessHours = useCallback((day, field, value) => {
		setSettings(prev => ({
			...prev,
			business_hours: {
				...prev.business_hours,
				[day]: { ...prev.business_hours[day], [field]: value }
			}
		}));
		setDirty(true);
	}, []);

	const updateAppointmentType = useCallback((index, field, value) => {
		setSettings(prev => {
			const types = [...prev.appointment_types];
			types[index] = { ...types[index], [field]: value };
			return { ...prev, appointment_types: types };
		});
		setDirty(true);
	}, []);

	const addAppointmentType = useCallback(() => {
		const id = 'custom_' + Date.now();
		setSettings(prev => ({
			...prev,
			appointment_types: [
				...prev.appointment_types,
				{ id, name: '', duration: 60, max_concurrent: null, enabled: true }
			]
		}));
		setDirty(true);
	}, []);

	const removeAppointmentType = useCallback((index) => {
		setSettings(prev => ({
			...prev,
			appointment_types: prev.appointment_types.filter((_, i) => i !== index)
		}));
		setDirty(true);
	}, []);

	if (loading) {
		return (
			<div className="flex flex-col items-center justify-center py-20">
				<div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
				<p className="text-gray-500 dark:text-slate-400">Loading appointment settings...</p>
			</div>
		);
	}

	if (!settings) {
		return (
			<div className="text-center py-12 text-gray-500">
				Failed to load settings.
				<button onClick={loadSettings} className="ml-2 text-primary hover:underline">Retry</button>
			</div>
		);
	}

	const bufferTotal = (settings.buffer_before || 0) + (settings.default_duration || 60) + (settings.buffer_after || 0);

	return (
		<div className="space-y-6">
			{/* Header */}
			<div className="flex items-center justify-between">
				<div className="flex items-center gap-3">
					<button
						onClick={onClose}
						className="p-2 rounded-lg text-gray-500 hover:text-gray-900 dark:text-slate-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
					>
						<ArrowLeft className="w-5 h-5" />
					</button>
					<div>
						<h2 className="text-xl font-semibold text-gray-900 dark:text-white">Appointment Settings</h2>
						<p className="text-sm text-gray-500 dark:text-slate-400">Configure scheduling rules, capacity, and availability</p>
					</div>
				</div>
				<button
					onClick={saveSettings}
					disabled={saving || !dirty}
					className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors disabled:opacity-50"
				>
					<Save className="w-4 h-4" />
					{saving ? 'Saving...' : 'Save Settings'}
				</button>
			</div>

			{/* Notification */}
			{notification && (
				<div className={`px-4 py-3 rounded-lg flex items-center justify-between ${notification.type === 'success'
					? 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800'
					: 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800'
					}`}>
					<span className="text-sm">{notification.message}</span>
					<button onClick={() => setNotification(null)} className="text-lg opacity-70 hover:opacity-100">×</button>
				</div>
			)}

			{/* 1. Capacity Settings */}
			<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
				<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2">
					<Users className="w-5 h-5 text-primary" />
					<h3 className="text-lg font-semibold text-gray-900 dark:text-white">Capacity</h3>
				</div>
				<div className="p-6 space-y-4">
					<div>
						<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
							Max Concurrent Bookings
						</label>
						<div className="flex items-center gap-4">
							<input
								type="number"
								min={1}
								max={100}
								value={settings.max_concurrent_slots || 1}
								onChange={e => updateField('max_concurrent_slots', parseInt(e.target.value) || 1)}
								className="w-24 h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
							/>
							<span className="text-sm text-gray-500 dark:text-slate-400">
								How many appointments can overlap at the same time
							</span>
						</div>
						<p className="mt-2 text-xs text-gray-400 dark:text-slate-500">
							💡 Doctor/Therapist: <strong>1</strong> • Massage Center with 5 tables: <strong>5</strong> • Salon with 3 stylists: <strong>3</strong>
						</p>
					</div>
				</div>
			</div>

			{/* 2. Appointment Types */}
			<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
				<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
					<div className="flex items-center gap-2">
						<Calendar className="w-5 h-5 text-primary" />
						<h3 className="text-lg font-semibold text-gray-900 dark:text-white">Appointment Types</h3>
					</div>
					<button
						onClick={addAppointmentType}
						className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg text-primary bg-primary/10 hover:bg-primary/20 transition-colors"
					>
						<Plus className="w-4 h-4" />
						Add Type
					</button>
				</div>
				<div className="p-6">
					<div className="overflow-x-auto">
						<table className="w-full text-sm">
							<thead>
								<tr className="text-left text-gray-500 dark:text-slate-400 border-b border-gray-100 dark:border-slate-700">
									<th className="pb-3 font-medium">Name</th>
									<th className="pb-3 font-medium">Duration</th>
									<th className="pb-3 font-medium">Max Concurrent</th>
									<th className="pb-3 font-medium text-center">Enabled</th>
									<th className="pb-3 font-medium text-center w-12"></th>
								</tr>
							</thead>
							<tbody className="divide-y divide-gray-50 dark:divide-slate-700">
								{(settings.appointment_types || []).map((type, i) => (
									<tr key={type.id || i} className="group">
										<td className="py-3 pr-3">
											<input
												type="text"
												value={type.name}
												onChange={e => updateAppointmentType(i, 'name', e.target.value)}
												placeholder="Type name..."
												className="w-full h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
											/>
										</td>
										<td className="py-3 pr-3">
											<select
												value={type.duration || 60}
												onChange={e => updateAppointmentType(i, 'duration', parseInt(e.target.value))}
												className="h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
											>
												{DURATION_OPTIONS.map(opt => (
													<option key={opt.value} value={opt.value}>{opt.label}</option>
												))}
											</select>
										</td>
										<td className="py-3 pr-3">
											<input
												type="number"
												min={0}
												max={100}
												value={type.max_concurrent !== null && type.max_concurrent !== undefined ? type.max_concurrent : ''}
												onChange={e => updateAppointmentType(i, 'max_concurrent', e.target.value === '' ? null : parseInt(e.target.value))}
												placeholder="Global"
												className="w-24 h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
											/>
										</td>
										<td className="py-3 text-center">
											<label className="relative inline-flex items-center cursor-pointer">
												<input
													type="checkbox"
													checked={!!type.enabled}
													onChange={e => updateAppointmentType(i, 'enabled', e.target.checked)}
													className="sr-only peer"
												/>
												<div className="w-9 h-5 bg-gray-200 dark:bg-slate-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-4 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
											</label>
										</td>
										<td className="py-3 text-center">
											<button
												onClick={() => removeAppointmentType(i)}
												className="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 opacity-0 group-hover:opacity-100 transition-all"
												title="Remove type"
											>
												<Trash2 className="w-4 h-4" />
											</button>
										</td>
									</tr>
								))}
							</tbody>
						</table>
					</div>
					<p className="mt-3 text-xs text-gray-400 dark:text-slate-500">
						Leave "Max Concurrent" empty to use the global capacity setting above. Each type can override it independently.
					</p>
				</div>
			</div>

			{/* 3. Buffer / Break Time */}
			<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
				<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2">
					<Clock className="w-5 h-5 text-primary" />
					<h3 className="text-lg font-semibold text-gray-900 dark:text-white">Buffer / Break Time</h3>
				</div>
				<div className="p-6 space-y-5">
					<div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
						<div>
							<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
								Buffer Before (minutes)
							</label>
							<input
								type="number"
								min={0}
								max={120}
								value={settings.buffer_before || 0}
								onChange={e => updateField('buffer_before', parseInt(e.target.value) || 0)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
							/>
							<p className="mt-1 text-xs text-gray-400 dark:text-slate-500">Preparation time before each appointment</p>
						</div>
						<div>
							<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
								Buffer After (minutes)
							</label>
							<input
								type="number"
								min={0}
								max={120}
								value={settings.buffer_after || 0}
								onChange={e => updateField('buffer_after', parseInt(e.target.value) || 0)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
							/>
							<p className="mt-1 text-xs text-gray-400 dark:text-slate-500">Break time after each appointment (cleanup, notes, rest)</p>
						</div>
					</div>

					{/* Visual preview */}
					<div className="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4">
						<p className="text-xs font-medium text-gray-500 dark:text-slate-400 mb-2">Preview: Total blocked time per appointment</p>
						<div className="flex items-center gap-1 text-xs">
							{(settings.buffer_before || 0) > 0 && (
								<div className="px-3 py-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 font-medium border border-amber-200 dark:border-amber-800">
									{settings.buffer_before}min buffer
								</div>
							)}
							<div className="px-4 py-2 rounded-lg bg-primary/10 text-primary font-semibold border border-primary/20">
								{settings.default_duration || 60}min appointment
							</div>
							{(settings.buffer_after || 0) > 0 && (
								<div className="px-3 py-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 font-medium border border-amber-200 dark:border-amber-800">
									{settings.buffer_after}min buffer
								</div>
							)}
							<span className="ml-2 text-gray-400">=</span>
							<span className="ml-1 font-semibold text-gray-700 dark:text-slate-300">{bufferTotal}min total</span>
						</div>
					</div>

					{/* Default duration */}
					<div>
						<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
							Default Duration
						</label>
						<select
							value={settings.default_duration || 60}
							onChange={e => updateField('default_duration', parseInt(e.target.value))}
							className="h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
						>
							{DURATION_OPTIONS.map(opt => (
								<option key={opt.value} value={opt.value}>{opt.label}</option>
							))}
						</select>
						<p className="mt-1 text-xs text-gray-400 dark:text-slate-500">Used when appointment type doesn't specify a duration</p>
					</div>
				</div>
			</div>

			{/* 4. Business Hours */}
			<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
				<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2">
					<Settings className="w-5 h-5 text-primary" />
					<h3 className="text-lg font-semibold text-gray-900 dark:text-white">Business Hours</h3>
				</div>
				<div className="p-6">
					<div className="space-y-3">
						{DAYS.map(day => {
							const dh = settings.business_hours?.[day] || { start: '09:00', end: '17:00', enabled: false };
							return (
								<div key={day} className="flex items-center gap-4">
									<div className="w-28 flex items-center gap-2">
										<label className="relative inline-flex items-center cursor-pointer">
											<input
												type="checkbox"
												checked={!!dh.enabled}
												onChange={e => updateBusinessHours(day, 'enabled', e.target.checked)}
												className="sr-only peer"
											/>
											<div className="w-9 h-5 bg-gray-200 dark:bg-slate-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-4 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
										</label>
										<span className={`text-sm font-medium ${dh.enabled ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-slate-500'}`}>
											{DAY_LABELS[day]}
										</span>
									</div>
									{dh.enabled ? (
										<div className="flex items-center gap-2">
											<input
												type="time"
												value={dh.start}
												onChange={e => updateBusinessHours(day, 'start', e.target.value)}
												className="h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
											/>
											<span className="text-gray-400">to</span>
											<input
												type="time"
												value={dh.end}
												onChange={e => updateBusinessHours(day, 'end', e.target.value)}
												className="h-9 px-3 text-sm rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30"
											/>
										</div>
									) : (
										<span className="text-sm text-gray-400 dark:text-slate-500 italic">Closed</span>
									)}
								</div>
							);
						})}
					</div>
				</div>
			</div>

			{/* 5. Advance Booking */}
			<div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
				<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2">
					<Clock className="w-5 h-5 text-primary" />
					<h3 className="text-lg font-semibold text-gray-900 dark:text-white">Advance Booking</h3>
				</div>
				<div className="p-6">
					<div>
						<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
							Minimum Advance Booking (hours)
						</label>
						<div className="flex items-center gap-4">
							<input
								type="number"
								min={0}
								max={168}
								value={settings.min_advance_hours || 0}
								onChange={e => updateField('min_advance_hours', parseInt(e.target.value) || 0)}
								className="w-24 h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
							/>
							<span className="text-sm text-gray-500 dark:text-slate-400">
								Appointments must be booked at least this many hours in advance
							</span>
						</div>
						<p className="mt-2 text-xs text-gray-400 dark:text-slate-500">
							Set to 0 to allow same-day bookings at any time. Set to 24 to require 1-day advance notice.
						</p>
					</div>
				</div>
			</div>
		</div>
	);
}
