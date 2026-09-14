/**
 * SourceEditor Component
 *
 * Modal for adding/editing knowledge sources.
 * Metronic v9-inspired design with Tailwind CSS.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import { Modal } from '../ui';

const SOURCE_TYPES = [
	{ value: 'raw_text', label: __('Raw Text', 'agentflow-ai') },
	{ value: 'wordpress_pages', label: __('WordPress Pages', 'agentflow-ai') },
	{ value: 'wordpress_posts', label: __('WordPress Posts', 'agentflow-ai') },
	{ value: 'woocommerce_products', label: __('WooCommerce Products', 'agentflow-ai') },
	{ value: 'folder', label: __('Folder', 'agentflow-ai') },
];

export default function SourceEditor({ source, onSave, onCancel }) {
	const isNew = !source;

	const [formData, setFormData] = useState({
		id: source?.id || null,
		name: source?.name || '',
		source_type: source?.source_type || 'raw_text',
		config: source?.config || {},
		status: source?.status || 'active',
	});

	const [saving, setSaving] = useState(false);
	const [error, setError] = useState(null);

	// WordPress content for page/post dropdown
	const [wpContent, setWpContent] = useState([]);
	const [wpContentLoading, setWpContentLoading] = useState(false);

	// Fetch WP pages/posts when source type changes
	useEffect(() => {
		if (formData.source_type === 'wordpress_pages' || formData.source_type === 'wordpress_posts') {
			const postType = formData.source_type === 'wordpress_pages' ? 'page' : 'post';
			setWpContentLoading(true);
			apiFetch({
				path: `/smart-ai-chatbot/v1/knowledge/wp-content?type=${postType}`,
			})
				.then((res) => {
					if (res.success) {
						setWpContent(res.data || []);
					}
				})
				.catch(() => setWpContent([]))
				.finally(() => setWpContentLoading(false));
		}
	}, [formData.source_type]);

	const handleChange = (field, value) => {
		setFormData({ ...formData, [field]: value });
	};

	const handleConfigChange = (key, value) => {
		setFormData({
			...formData,
			config: { ...formData.config, [key]: value },
		});
	};

	// Toggle a post/page ID in the selected list
	const togglePostId = (id) => {
		const current = formData.config.post_ids || [];
		const idNum = Number(id);
		if (current.includes(idNum)) {
			handleConfigChange('post_ids', current.filter((pid) => pid !== idNum));
		} else {
			handleConfigChange('post_ids', [...current, idNum]);
		}
	};

	const selectAllPosts = () => {
		handleConfigChange('post_ids', wpContent.map((p) => p.id));
	};

	const deselectAllPosts = () => {
		handleConfigChange('post_ids', []);
	};

	const handleSubmit = async () => {
		if (!formData.name.trim()) {
			setError(__('Name is required', 'agentflow-ai'));
			return;
		}
		setSaving(true);
		setError(null);
		try {
			await onSave(formData);
		} catch (err) {
			setError(err.message);
			setSaving(false);
		}
	};

	const inputBase =
		'w-full px-3.5 py-2.5 text-[13px] rounded-[10px] border bg-[#F5F8FA] text-[#071437] placeholder-[#99A1B7] transition-all duration-150 focus:outline-none focus:bg-white focus:border-[#1B84FF] focus:ring-[3px] focus:ring-[#1B84FF]/15';
	const inputNormal = 'border-[#E1E3EA] hover:border-[#C4CBD3] hover:bg-white';

	const renderConfigFields = () => {
		switch (formData.source_type) {
			case 'raw_text':
				return (
					<div>
						<label className="block text-[13px] font-medium text-[#3F4254] mb-1.5">
							{__('Knowledge Text', 'agentflow-ai')}
						</label>
						<textarea
							value={formData.config.text || ''}
							onChange={(e) => handleConfigChange('text', e.target.value)}
							rows={6}
							className={`${inputBase} ${inputNormal} resize-none`}
							placeholder={__('Enter or paste the specific knowledge here...', 'agentflow-ai')}
						/>
						<p className="mt-1 text-[11px] text-[#99A1B7]">
							{__('Paste any text — FAQ answers, policies, product info, etc.', 'agentflow-ai')}
						</p>
					</div>
				);
			case 'folder':
				return (
					<div>
						<label className="block text-[13px] font-medium text-[#3F4254] mb-1.5">
							{__('Folder Path', 'agentflow-ai')}
						</label>
						<input
							type="text"
							value={formData.config.path || ''}
							onChange={(e) => handleConfigChange('path', e.target.value)}
							className={`${inputBase} ${inputNormal}`}
							placeholder="swc-knowledge/policies"
						/>
						<p className="mt-1 text-[11px] text-[#99A1B7]">
							{__('Relative to wp-content', 'agentflow-ai')}
						</p>
					</div>
				);
			case 'wordpress_pages':
			case 'wordpress_posts': {
				const selectedIds = formData.config.post_ids || [];
				const label = formData.source_type === 'wordpress_pages'
					? __('Select Pages', 'agentflow-ai')
					: __('Select Posts', 'agentflow-ai');

				if (wpContentLoading) {
					return (
						<div className="flex items-center justify-center py-8">
							<div className="w-5 h-5 border-2 border-[#1B84FF]/30 border-t-[#1B84FF] rounded-full animate-spin" />
							<span className="ml-2 text-[13px] text-[#99A1B7]">{__('Loading...', 'agentflow-ai')}</span>
						</div>
					);
				}

				return (
					<div>
						<div className="flex items-center justify-between mb-1.5">
							<label className="text-[13px] font-medium text-[#3F4254]">
								{label}
							</label>
							<div className="flex gap-2">
								<button
									type="button"
									onClick={selectAllPosts}
									className="text-[11px] text-[#1B84FF] hover:underline"
								>
									{__('Select All', 'agentflow-ai')}
								</button>
								<button
									type="button"
									onClick={deselectAllPosts}
									className="text-[11px] text-[#99A1B7] hover:underline"
								>
									{__('Clear', 'agentflow-ai')}
								</button>
							</div>
						</div>
						{wpContent.length === 0 ? (
							<div className="text-center py-6 rounded-lg bg-[#F5F8FA] text-[13px] text-[#99A1B7]">
								{formData.source_type === 'wordpress_pages'
									? __('No published pages found', 'agentflow-ai')
									: __('No published posts found', 'agentflow-ai')}
							</div>
						) : (
							<div className="max-h-[220px] overflow-y-auto rounded-[10px] border border-[#E1E3EA] bg-[#F5F8FA]">
								{wpContent.map((item) => {
									const isChecked = selectedIds.includes(item.id);
									return (
										<label
											key={item.id}
											className={`flex items-center gap-3 px-3.5 py-2.5 cursor-pointer transition-colors border-b border-[#E1E3EA] last:border-b-0 hover:bg-white ${isChecked ? 'bg-[#1B84FF]/5' : ''}`}
										>
											<input
												type="checkbox"
												checked={isChecked}
												onChange={() => togglePostId(item.id)}
												className="w-4 h-4 rounded border-[#C4CBD3] text-[#1B84FF] focus:ring-[#1B84FF]/30"
											/>
											<span className={`text-[13px] ${isChecked ? 'text-[#1B84FF] font-medium' : 'text-[#3F4254]'}`}>
												{item.title}
											</span>
										</label>
									);
								})}
							</div>
						)}
						<p className="mt-1.5 text-[11px] text-[#99A1B7]">
							{selectedIds.length > 0
								? `${selectedIds.length} ${__('selected', 'agentflow-ai')}`
								: __('Leave empty to include all', 'agentflow-ai')}
						</p>
					</div>
				);
			}
			case 'woocommerce_products':
				return (
					<div className="rounded-[10px] bg-[#F5F8FA] px-4 py-3 text-[13px] text-[#4B5675] border border-[#E1E3EA]">
						{__('All published WooCommerce products will be indexed automatically.', 'agentflow-ai')}
					</div>
				);
			default:
				return null;
		}
	};

	const footerContent = (
		<>
			<button
				type="button"
				onClick={onCancel}
				disabled={saving}
				className="inline-flex items-center px-4 py-2 text-[13px] font-medium rounded-lg border border-[#E1E3EA] text-[#4B5675] bg-white hover:bg-[#F5F8FA] hover:border-[#C4CBD3] disabled:opacity-50 transition-all duration-150"
			>
				{__('Cancel', 'agentflow-ai')}
			</button>
			<button
				type="button"
				onClick={handleSubmit}
				disabled={saving}
				className="inline-flex items-center gap-2 px-5 py-2 text-[13px] font-medium rounded-lg bg-[#1B84FF] text-white hover:bg-[#1870DB] active:bg-[#1558B0] shadow-[0_4px_12px_0_rgba(27,132,255,0.20)] disabled:opacity-50 transition-all duration-150"
			>
				{saving ? (
					<>
						<svg className="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
							<circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" strokeDasharray="31.4" strokeDashoffset="10" strokeLinecap="round" />
						</svg>
						{__('Saving…', 'agentflow-ai')}
					</>
				) : (
					<>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
							<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
							<polyline points="17 21 17 13 7 13 7 21" />
							<polyline points="7 3 7 8 15 8" />
						</svg>
						{__('Save', 'agentflow-ai')}
					</>
				)}
			</button>
		</>
	);

	return (
		<Modal
			title={isNew ? __('Add Knowledge Source', 'agentflow-ai') : __('Edit Knowledge Source', 'agentflow-ai')}
			subtitle={__('Connect a data source for your AI agents', 'agentflow-ai')}
			isOpen
			onClose={onCancel}
			footer={footerContent}
			size="sm"
		>
			<div className="space-y-5">
				{/* Error */}
				{error && (
					<div className="flex items-center gap-2 px-4 py-3 rounded-lg bg-[#FFF5F5] border border-[#FFE0E0] text-[13px] text-[#F8285A]">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
							<circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
						</svg>
						{error}
					</div>
				)}

				{/* Name */}
				<div>
					<label className="block text-[13px] font-medium text-[#3F4254] mb-1.5">
						{__('Name', 'agentflow-ai')} <span className="text-[#F8285A]">*</span>
					</label>
					<input
						type="text"
						value={formData.name}
						onChange={(e) => handleChange('name', e.target.value)}
						className={`${inputBase} ${inputNormal}`}
						placeholder={__('e.g. Store Policies', 'agentflow-ai')}
					/>
				</div>

				{/* Source Type */}
				<div>
					<label className="block text-[13px] font-medium text-[#3F4254] mb-1.5">
						{__('Source Type', 'agentflow-ai')}
					</label>
					<div className="relative">
						<select
							value={formData.source_type}
							onChange={(e) => {
								handleChange('source_type', e.target.value);
								// Reset config when type changes
								if (isNew) {
									setFormData(prev => ({ ...prev, source_type: e.target.value, config: {} }));
								}
							}}
							disabled={!isNew}
							className={`${inputBase} ${inputNormal} appearance-none pr-10 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed`}
						>
							{SOURCE_TYPES.map((type) => (
								<option key={type.value} value={type.value}>{type.label}</option>
							))}
						</select>
						<div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-[#99A1B7]">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
								<path d="m6 9 6 6 6-6" />
							</svg>
						</div>
					</div>
				</div>

				{/* Config Fields */}
				{renderConfigFields()}
			</div>
		</Modal>
	);
}

SourceEditor.propTypes = {
	source: PropTypes.object,
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
};
