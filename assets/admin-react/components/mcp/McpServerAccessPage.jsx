import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Server, Copy, RefreshCw, ExternalLink } from 'lucide-react';
import Loading from '../common/Loading';
import McpToolSelector from '../McpToolSelector';

export default function McpServerAccessPage() {
	const [mcpServer, setMcpServer] = useState(null);
	const [mcpLoading, setMcpLoading] = useState(true);
	const [generatingToken, setGeneratingToken] = useState(false);
	const [notification, setNotification] = useState(null);

	useEffect(() => {
		loadMcpServer();
	}, []);

	const loadMcpServer = async () => {
		setMcpLoading(true);
		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/mcp-server/status',
				method: 'GET',
			});
			if (response.success) {
				setMcpServer(response.data);
			}
		} catch (err) {
			setNotification({ type: 'error', message: 'Failed to load external AI access status' });
		} finally {
			setMcpLoading(false);
		}
	};

	const toggleMcpServer = async (enabled) => {
		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/mcp-server/settings',
				method: 'POST',
				data: { enabled },
			});
			if (response.success) {
				setMcpServer(response.data);
				setNotification({
					type: 'success',
					message: enabled ? 'External AI access enabled' : 'External AI access disabled',
				});
			}
		} catch (err) {
			setNotification({ type: 'error', message: err.message || 'Failed to update external AI access' });
		}
	};

	const generateMcpToken = async () => {
		setGeneratingToken(true);
		try {
			const response = await apiFetch({
				path: '/quark-agentflow-ai/v1/mcp-server/token',
				method: 'POST',
			});
			if (response.success) {
				setMcpServer((prev) => ({
					...prev,
					...response.data,
					has_token: true,
				}));
				setNotification({ type: 'success', message: 'New token generated!' });
			}
		} catch (err) {
			setNotification({ type: 'error', message: err.message || 'Failed to generate token' });
		} finally {
			setGeneratingToken(false);
		}
	};

	const copyToClipboard = async (text, label = 'Content') => {
		try {
			if (navigator.clipboard && window.isSecureContext) {
				await navigator.clipboard.writeText(text);
			} else {
				const textarea = document.createElement('textarea');
				textarea.value = text;
				textarea.style.position = 'fixed';
				textarea.style.left = '-9999px';
				textarea.style.top = '-9999px';
				document.body.appendChild(textarea);
				textarea.focus();
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);
			}
			setNotification({ type: 'success', message: `${label} copied to clipboard!` });
		} catch (err) {
			setNotification({ type: 'error', message: 'Failed to copy to clipboard' });
		}
	};

	if (mcpLoading) {
		return <Loading message={__('Loading external AI access...', 'agentflow-ai')} fullPage />;
	}

	return (
		<div className="space-y-6">
			<div className="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
				<div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
					<div className="max-w-3xl">
						<div className="flex items-center gap-3">
							<div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
								<Server className="h-5 w-5" />
							</div>
							<div>
								<h1 className="text-xl font-semibold text-slate-900 dark:text-slate-100">
									{__('Connect AI Apps', 'agentflow-ai')}
								</h1>
								<p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
									{__('Turn this WordPress site into an MCP server so external AI apps like Claude Desktop, Cursor, and other MCP clients can use your site tools.', 'agentflow-ai')}
								</p>
							</div>
						</div>
					</div>
					<div className="inline-flex self-start rounded-full bg-slate-50 px-3 py-1.5 text-sm text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">
						<span className={`mr-2 inline-block h-2.5 w-2.5 rounded-full ${mcpServer?.enabled ? 'bg-emerald-500' : 'bg-slate-400'}`} />
						{mcpServer?.enabled ? __('Enabled', 'agentflow-ai') : __('Disabled', 'agentflow-ai')}
					</div>
				</div>
			</div>

			{notification && (
				<div className={`rounded-xl px-4 py-3 text-sm ${notification.type === 'success'
					? 'border border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
					: 'border border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300'
				}`}>
					{notification.message}
				</div>
			)}

			{mcpServer ? (
				<>
					<div className="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
						<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
							<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
								{__('Server Status', 'agentflow-ai')}
							</h3>
						</div>
						<div className="p-6">
							<div className="flex items-center justify-between gap-4">
								<div>
									<div className="text-sm font-medium text-gray-900 dark:text-white">
										{__('Enable External AI Access', 'agentflow-ai')}
									</div>
									<div className="text-sm text-gray-500 dark:text-slate-400">
										{__('Allow external AI apps to connect to this site through MCP.', 'agentflow-ai')}
									</div>
								</div>
								<label className="relative inline-flex items-center cursor-pointer">
									<input
										type="checkbox"
										checked={!!mcpServer.enabled}
										onChange={(e) => toggleMcpServer(e.target.checked)}
										className="sr-only peer"
									/>
									<div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
								</label>
							</div>

							{mcpServer.enabled && mcpServer.tools && (
								<div className="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
									<div className="flex items-center gap-4 text-sm">
										<span className="px-3 py-1 bg-green-100 text-green-700 rounded-full font-medium">
											{mcpServer.tools.enabled} {__('tools enabled', 'agentflow-ai')}
										</span>
										<span className="text-gray-500 dark:text-slate-400">
											{__('of', 'agentflow-ai')} {mcpServer.tools.total} {__('total', 'agentflow-ai')}
										</span>
									</div>
								</div>
							)}
						</div>
					</div>

					{mcpServer.enabled && (
						<div className="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
							<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
								<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
									{__('Authentication Token', 'agentflow-ai')}
								</h3>
							</div>
							<div className="p-6 space-y-4">
								{mcpServer.has_token ? (
									<>
										<div>
											<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
												{__('Bearer Token', 'agentflow-ai')}
											</label>
											<div className="flex gap-2">
												<input
													type="text"
													value={mcpServer.token || 'â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢'}
													readOnly
													className="flex-1 h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-white font-mono"
												/>
												<button
													onClick={() => copyToClipboard(mcpServer.token, 'Token')}
													className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
												>
													<Copy className="w-4 h-4" />
													{__('Copy', 'agentflow-ai')}
												</button>
											</div>
										</div>
										<button
											onClick={generateMcpToken}
											disabled={generatingToken}
											className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 transition-colors disabled:opacity-50"
										>
											<RefreshCw className={`w-4 h-4 ${generatingToken ? 'animate-spin' : ''}`} />
											{generatingToken ? __('Regenerating...', 'agentflow-ai') : __('Regenerate Token', 'agentflow-ai')}
										</button>
									</>
								) : (
									<div className="text-center py-4">
										<p className="text-gray-500 dark:text-slate-400 mb-4">
											{__('No token generated yet. Generate a token to allow external AI apps to connect.', 'agentflow-ai')}
										</p>
										<button
											onClick={generateMcpToken}
											disabled={generatingToken}
											className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 transition-colors disabled:opacity-50"
										>
											<RefreshCw className={`w-4 h-4 ${generatingToken ? 'animate-spin' : ''}`} />
											{generatingToken ? __('Generating...', 'agentflow-ai') : __('Generate Token', 'agentflow-ai')}
										</button>
									</div>
								)}
							</div>
						</div>
					)}

					{mcpServer.enabled && mcpServer.has_token && mcpServer.endpoints && (
						<div className="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
							<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
								<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
									{__('Connection URLs', 'agentflow-ai')}
								</h3>
							</div>
							<div className="p-6 space-y-4">
								<div>
									<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
										{__('SSE Endpoint', 'agentflow-ai')}
									</label>
									<div className="flex gap-2">
										<input
											type="text"
											value={mcpServer.endpoints.sse || ''}
											readOnly
											className="flex-1 h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-slate-300 font-mono truncate"
										/>
										<button
											onClick={() => copyToClipboard(mcpServer.endpoints.sse, 'SSE URL')}
											className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
										>
											<Copy className="w-4 h-4" />
										</button>
									</div>
								</div>
								<div>
									<label className="block text-sm font-medium text-gray-900 dark:text-white mb-1.5">
										{__('Direct URL With Token', 'agentflow-ai')}
									</label>
									<div className="flex gap-2">
										<input
											type="text"
											value={mcpServer.endpoints.no_auth || ''}
											readOnly
											className="flex-1 h-10 px-4 text-sm rounded-lg border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-slate-300 font-mono truncate"
										/>
										<button
											onClick={() => copyToClipboard(mcpServer.endpoints.no_auth, 'Direct URL')}
											className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
										>
											<Copy className="w-4 h-4" />
										</button>
									</div>
								</div>
							</div>
						</div>
					)}

					{mcpServer.enabled && mcpServer.has_token && mcpServer.claude_config && (
						<div className="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
							<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between gap-3">
								<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
									{__('Claude Desktop Setup', 'agentflow-ai')}
								</h3>
								<a
									href="https://modelcontextprotocol.io/quickstart/user"
									target="_blank"
									rel="noopener noreferrer"
									className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
								>
									{__('Setup Guide', 'agentflow-ai')} <ExternalLink className="w-3 h-3" />
								</a>
							</div>
							<div className="p-6">
								<p className="text-sm text-gray-500 dark:text-slate-400 mb-3">
									{__('Add this to your Claude Desktop configuration file.', 'agentflow-ai')}
								</p>
								<div className="relative">
									<pre className="p-4 bg-gray-900 text-gray-100 rounded-lg text-sm overflow-x-auto font-mono">
										{JSON.stringify(mcpServer.claude_config, null, 2)}
									</pre>
									<button
										onClick={() => copyToClipboard(JSON.stringify(mcpServer.claude_config, null, 2), 'Claude config')}
										className="absolute top-2 right-2 inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded bg-gray-700 text-gray-200 hover:bg-gray-600 transition-colors"
									>
										<Copy className="w-3 h-3" />
										{__('Copy', 'agentflow-ai')}
									</button>
								</div>
							</div>
						</div>
					)}

					{mcpServer.enabled && (
						<div className="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
							<div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
								<h3 className="text-lg font-semibold text-gray-900 dark:text-white">
									{__('Shared Tools For External Apps', 'agentflow-ai')}
								</h3>
								<p className="text-sm text-gray-500 dark:text-slate-400 mt-1">
									{__('Choose which plugin tools external MCP clients are allowed to use.', 'agentflow-ai')}
								</p>
							</div>
							<div className="p-6">
								<McpToolSelector
									onStatsChange={(stats) => setMcpServer((prev) => ({
										...prev,
										tools: stats,
									}))}
								/>
							</div>
						</div>
					)}
				</>
			) : (
				<div className="text-center py-12 text-gray-500 dark:text-slate-400">
					{__('Unable to load external AI access status.', 'agentflow-ai')}
					<button onClick={loadMcpServer} className="ml-2 text-primary hover:underline">
						{__('Retry', 'agentflow-ai')}
					</button>
				</div>
			)}
		</div>
	);
}
