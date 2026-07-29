/**
 * McpPage Component - Metronic v9 Style
 *
 * Global management page for external Model Context Protocol servers.
 * Uses shared McpCard component for consistent UI.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getCached, setCache, invalidateCache } from '../../hooks/useApiCache';
import { McpCard, Modal } from '../ui';
import Loading from '../common/Loading';

// SVG Icons
const GlobeIcon = ({ className = 'w-5 h-5' }) => (
	<svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
		<circle cx="12" cy="12" r="10" />
		<path d="M2 12h20" />
		<path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
	</svg>
);

const TerminalIcon = ({ className = 'w-5 h-5' }) => (
	<svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
		<polyline points="4 17 10 11 4 5" />
		<line x1="12" y1="19" x2="20" y2="19" />
	</svg>
);

const LinkIcon = ({ className = 'w-4 h-4' }) => (
	<svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
		<path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
	</svg>
);

const KeyIcon = ({ className = 'w-4 h-4' }) => (
	<svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.78 7.78 5.5 5.5 0 017.78-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
	</svg>
);

const ShieldIcon = ({ className = 'w-4 h-4' }) => (
	<svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
	</svg>
);

const EditIcon = ({ className = 'w-3.5 h-3.5' }) => (
	<svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
		<path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
	</svg>
);

const TrashIcon = ({ className = 'w-3.5 h-3.5' }) => (
	<svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<polyline points="3 6 5 6 21 6" />
		<path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
	</svg>
);

export default function McpPage() {
	const [mcps, setMcps] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [modalOpen, setModalOpen] = useState(false);
	const [editingMcp, setEditingMcp] = useState(null);

	// Form state
	const [formData, setFormData] = useState({
		id: '',
		name: '',
		description: '',
		type: 'sse',
		default_config: { url: '', api_key: '', token: '' },
	});

	useEffect(() => {
		fetchMcps();
	}, []);

	const fetchMcps = async () => {
		// Check cache first for instant display
		const cached = getCached('mcps');
		if (cached) {
			setMcps(cached);
			setLoading(false);

			// Background refresh
			apiFetch({ path: '/smart-ai-chatbot/v1/mcps' })
				.then(response => {
					setMcps(response);
					setCache('mcps', response);
				})
				.catch(() => { });
			return;
		}

		try {
			setLoading(true);
			const response = await apiFetch({ path: '/smart-ai-chatbot/v1/mcps' });
			setMcps(response);
			setCache('mcps', response);
			setError(null);
		} catch (err) {
			setError(err.message || 'Failed to load external MCPs');
		} finally {
			setLoading(false);
		}
	};

	const handleEdit = (mcp) => {
		setEditingMcp(mcp);
		setFormData({
			id: mcp.id,
			name: mcp.name,
			description: mcp.description || '',
			type: mcp.type,
			default_config: mcp.default_config || {},
		});
		setModalOpen(true);
	};

	const handleCreate = () => {
		setEditingMcp(null);
		setFormData({
			id: 'mcp_' + Date.now(),
			name: '',
			description: '',
			type: 'sse',
			default_config: { url: '', api_key: '', token: '' },
		});
		setModalOpen(true);
	};

	const handleDelete = async (id) => {
		if (!confirm(__('Are you sure you want to delete this external MCP? It may break agents using it.', 'smart-woo-chatbot'))) {
			return;
		}

		try {
			await apiFetch({
				path: `/smart-ai-chatbot/v1/mcps/${id}`,
				method: 'DELETE',
			});
			invalidateCache('mcps');
			fetchMcps();
		} catch (err) {
			alert(err.message);
		}
	};

	const handleSave = async () => {
		try {
			await apiFetch({
				path: '/smart-ai-chatbot/v1/mcps',
				method: 'POST',
				data: formData,
			});
			invalidateCache('mcps');
			setModalOpen(false);
			fetchMcps();
		} catch (err) {
			alert(err.message);
		}
	};

	const updateConfig = (key, value) => {
		setFormData((prev) => ({
			...prev,
			default_config: {
				...prev.default_config,
				[key]: value,
			},
		}));
	};

	// Loading state
	if (loading) {
		return <Loading message={__('Loading External MCPs...', 'smart-woo-chatbot')} fullPage />;
	}

	// Modal footer
	const modalFooter = (
		<>
			<button
				onClick={() => setModalOpen(false)}
				className="inline-flex items-center justify-center h-9 px-4 text-[13px] font-medium rounded-md text-[#4B5675] bg-[#F5F8FA] hover:bg-[#EEF1F5] border border-gray-200 transition-colors dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-700"
			>
				{__('Cancel', 'smart-woo-chatbot')}
			</button>
			<button
				onClick={handleSave}
				className="inline-flex items-center justify-center h-9 px-5 text-[13px] font-medium rounded-md text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors"
			>
				{editingMcp ? __('Update External MCP', 'smart-woo-chatbot') : __('Create External MCP', 'smart-woo-chatbot')}
			</button>
		</>
	);

	return (
		<div className="min-h-screen bg-background">
			{/* Page Header with Gradient */}
			{/* Action Bar */}
			<div className="flex items-center justify-end px-6 py-3">
				<button
					onClick={handleCreate}
					className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl text-white bg-primary hover:bg-primary/90 shadow-lg shadow-primary/30 transition-all"
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
						<path d="M12 5v14" />
						<path d="M5 12h14" />
					</svg>
					{__('Add New External MCP', 'smart-woo-chatbot')}
				</button>
			</div>

			{/* Error */}
			{error && (
				<div className="mx-6 mt-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-700">
					<span className="text-sm">{error}</span>
				</div>
			)}

			{/* Content */}
			<main className="p-6">
				<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
					{mcps.map((mcp) => {
						const isCustom = mcp.category === 'custom';

						return (
							<div key={mcp.id} className="swc-mcp-card-wrapper">
								<McpCard
									mcp={mcp}
									enabled={false}
									onConfigure={handleEdit}
									showConfigButton={true}
									showUrl={true}
								/>

								{/* Footer (for custom MCPs) */}
								{isCustom && (
									<div className="flex items-center justify-end gap-1 px-5 py-3 border-t border-border bg-muted/50 rounded-b-xl -mt-3">
										<button
											onClick={() => handleEdit(mcp)}
											className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-muted-foreground hover:text-primary hover:bg-primary/5 transition-colors"
										>
											<EditIcon />
											{__('Edit', 'smart-woo-chatbot')}
										</button>
										<button
											onClick={() => handleDelete(mcp.id)}
											className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
										>
											<TrashIcon />
											{__('Delete', 'smart-woo-chatbot')}
										</button>
									</div>
								)}
							</div>
						);
					})}
				</div>
			</main>

			{/* Modal — Metronic v9 style via shared Modal component */}
			<Modal
				isOpen={modalOpen}
				onClose={() => setModalOpen(false)}
				title={editingMcp ? __('Edit External MCP Server', 'smart-woo-chatbot') : __('Add New External MCP Server', 'smart-woo-chatbot')}
				subtitle={editingMcp
					? __('Update the configuration for this external MCP connection.', 'smart-woo-chatbot')
					: __('Configure a new external Model Context Protocol server for your agents.', 'smart-woo-chatbot')
				}
				footer={modalFooter}
				size="xl"
			>
				<div className="space-y-4">
					{/* Row 1: Name + Description side by side */}
					<div className="grid grid-cols-2 gap-4">
						<div>
							<label className="block text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1">
								{__('Name', 'smart-woo-chatbot')}
							</label>
							<input
								type="text"
								value={formData.name}
								onChange={(e) => setFormData({ ...formData, name: e.target.value })}
								placeholder={__('e.g. GitHub MCP', 'smart-woo-chatbot')}
								className="w-full h-[36px] px-3 text-[13px] rounded-md border border-gray-200 bg-white text-[#071437] placeholder:text-[#99A1B7] focus:outline-none focus:ring-1 focus:ring-primary/40 focus:border-primary transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500"
							/>
						</div>
						<div>
							<label className="block text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1">
								{__('Description', 'smart-woo-chatbot')}
							</label>
							<input
								type="text"
								value={formData.description}
								onChange={(e) => setFormData({ ...formData, description: e.target.value })}
								placeholder={__('Brief description of this external MCP server...', 'smart-woo-chatbot')}
								className="w-full h-[36px] px-3 text-[13px] rounded-md border border-gray-200 bg-white text-[#071437] placeholder:text-[#99A1B7] focus:outline-none focus:ring-1 focus:ring-primary/40 focus:border-primary transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500"
							/>
						</div>
					</div>

					{/* Row 2: Connection Type - compact inline buttons */}
					<div>
						<label className="block text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1.5">
							{__('Connection Type', 'smart-woo-chatbot')}
						</label>
						<div className="flex gap-3">
							<button
								type="button"
								onClick={() => setFormData({ ...formData, type: 'sse' })}
								className={`flex items-center gap-2 px-4 py-2 rounded-lg border-2 transition-all ${formData.type === 'sse'
									? 'border-primary bg-primary/5 dark:bg-primary/10'
									: 'border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800'
									}`}
							>
								<div className={`flex items-center justify-center w-8 h-8 rounded-lg ${formData.type === 'sse'
									? 'bg-primary/10 text-primary'
									: 'bg-[#F5F8FA] dark:bg-slate-700 text-[#78829D] dark:text-slate-400'
									}`}>
									<GlobeIcon className="w-4 h-4" />
								</div>
								<div className="text-left">
									<div className={`text-[13px] font-semibold leading-tight ${formData.type === 'sse' ? 'text-primary' : 'text-[#071437] dark:text-slate-200'}`}>
										{__('Remote (SSE)', 'smart-woo-chatbot')}
									</div>
									<div className="text-[11px] text-[#99A1B7] dark:text-slate-500">
										{__('HTTP connection', 'smart-woo-chatbot')}
									</div>
								</div>
							</button>
							<button
								type="button"
								onClick={() => setFormData({ ...formData, type: 'stdio' })}
								className={`flex items-center gap-2 px-4 py-2 rounded-lg border-2 transition-all ${formData.type === 'stdio'
									? 'border-primary bg-primary/5 dark:bg-primary/10'
									: 'border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800'
									}`}
							>
								<div className={`flex items-center justify-center w-8 h-8 rounded-lg ${formData.type === 'stdio'
									? 'bg-primary/10 text-primary'
									: 'bg-[#F5F8FA] dark:bg-slate-700 text-[#78829D] dark:text-slate-400'
									}`}>
									<TerminalIcon className="w-4 h-4" />
								</div>
								<div className="text-left">
									<div className={`text-[13px] font-semibold leading-tight ${formData.type === 'stdio' ? 'text-primary' : 'text-[#071437] dark:text-slate-200'}`}>
										{__('Local (Stdio)', 'smart-woo-chatbot')}
									</div>
									<div className="text-[11px] text-[#99A1B7] dark:text-slate-500">
										{__('CLI process', 'smart-woo-chatbot')}
									</div>
								</div>
							</button>
						</div>
					</div>

					{/* Divider */}
					<div className="border-t border-gray-100 dark:border-slate-800" />

					{/* SSE Fields */}
					{formData.type === 'sse' ? (
						<div className="space-y-3">
							{/* Server URL - full width */}
							<div>
								<label className="flex items-center gap-1.5 text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1">
									<LinkIcon className="w-3.5 h-3.5 text-[#99A1B7]" />
									{__('Server URL', 'smart-woo-chatbot')}
								</label>
								<input
									type="text"
									value={formData.default_config?.url || ''}
									onChange={(e) => updateConfig('url', e.target.value)}
									placeholder="https://mcp.example.com/sse"
									className="w-full h-[36px] px-3 text-[13px] rounded-md border border-gray-200 bg-white text-[#071437] placeholder:text-[#99A1B7] focus:outline-none focus:ring-1 focus:ring-primary/40 focus:border-primary transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500"
								/>
							</div>
							{/* API Key + Auth Token side by side */}
							<div className="grid grid-cols-2 gap-4">
								<div>
									<label className="flex items-center gap-1.5 text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1">
										<KeyIcon className="w-3.5 h-3.5 text-[#99A1B7]" />
										{__('API Key', 'smart-woo-chatbot')}
									</label>
									<input
										type="password"
										value={formData.default_config?.api_key || ''}
										onChange={(e) => updateConfig('api_key', e.target.value)}
										placeholder="sk-..."
										className="w-full h-[36px] px-3 text-[13px] rounded-md border border-gray-200 bg-white text-[#071437] placeholder:text-[#99A1B7] focus:outline-none focus:ring-1 focus:ring-primary/40 focus:border-primary transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500"
									/>
									<p className="text-[11px] text-[#99A1B7] dark:text-slate-500 mt-0.5">{__('Required API key for this MCP service', 'smart-woo-chatbot')}</p>
								</div>
								<div>
									<label className="flex items-center gap-1.5 text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1">
										<ShieldIcon className="w-3.5 h-3.5 text-[#99A1B7]" />
										{__('Auth Token', 'smart-woo-chatbot')}
										<span className="text-[11px] text-[#99A1B7] font-normal">{__('(Optional)', 'smart-woo-chatbot')}</span>
									</label>
									<input
										type="password"
										value={formData.default_config?.token || ''}
										onChange={(e) => updateConfig('token', e.target.value)}
										placeholder={__('Bearer token...', 'smart-woo-chatbot')}
										className="w-full h-[36px] px-3 text-[13px] rounded-md border border-gray-200 bg-white text-[#071437] placeholder:text-[#99A1B7] focus:outline-none focus:ring-1 focus:ring-primary/40 focus:border-primary transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500"
									/>
								</div>
							</div>
						</div>
					) : (
						<div className="grid grid-cols-2 gap-4">
							<div>
								<label className="flex items-center gap-1.5 text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1">
									<TerminalIcon className="w-3.5 h-3.5 text-[#99A1B7]" />
									{__('Command', 'smart-woo-chatbot')}
								</label>
								<input
									type="text"
									value={formData.default_config?.command || ''}
									onChange={(e) => updateConfig('command', e.target.value)}
									placeholder="npx"
									className="w-full h-[36px] px-3 text-[13px] font-mono rounded-md border border-gray-200 bg-white text-[#071437] placeholder:text-[#99A1B7] focus:outline-none focus:ring-1 focus:ring-primary/40 focus:border-primary transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500"
								/>
							</div>
							<div>
								<label className="block text-[13px] font-medium text-[#071437] dark:text-slate-200 mb-1">
									{__('Arguments', 'smart-woo-chatbot')}
									<span className="text-[11px] text-[#99A1B7] font-normal ml-1">{__('(JSON Array)', 'smart-woo-chatbot')}</span>
								</label>
								<input
									type="text"
									value={JSON.stringify(formData.default_config?.args || [])}
									onChange={(e) => {
										try {
											updateConfig('args', JSON.parse(e.target.value));
										} catch (err) { }
									}}
									placeholder='["-y", "@modelcontextprotocol/server"]'
									className="w-full h-[36px] px-3 text-[13px] font-mono rounded-md border border-gray-200 bg-white text-[#071437] placeholder:text-[#99A1B7] focus:outline-none focus:ring-1 focus:ring-primary/40 focus:border-primary transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500"
								/>
							</div>
						</div>
					)}
				</div>
			</Modal>
		</div>
	);
}
