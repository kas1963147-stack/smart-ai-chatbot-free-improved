/**
 * SkillPreview Component
 *
 * Live preview of skill SKILL.md with tool requirements and token estimates.
 */
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import DOMPurify from 'dompurify';
import { Button, Tabs } from '../ui';

// Estimate tokens (rough: ~4 chars per token)
const estimateTokens = (text) => Math.ceil((text || '').length / 4);

// Category badge colors
const CATEGORY_COLORS = {
	woocommerce: { bg: '#f3e8ff', color: '#7c3aed' },
	wordpress: { bg: '#dbeafe', color: '#2563eb' },
	support: { bg: '#cffafe', color: '#0891b2' },
	general: { bg: '#f5f3ff', color: '#8b5cf6' },
};

export default function SkillPreview({
	skillData,
	onTest,
	showTestButton = true,
}) {
	const [activeTab, setActiveTab] = useState('preview');

	// Generate SKILL.md content from form data
	const generatedMarkdown = useMemo(() => {
		if (!skillData) {
			return '';
		}

		// Build frontmatter
		const frontmatter = [
			'---',
			`name: ${skillData.name || 'untitled'}`,
			`description: ${skillData.description || ''}`,
			`category: ${skillData.category || 'general'}`,
			skillData.displayName
				? `display_name: ${skillData.displayName}`
				: null,
			skillData.toolsRequired?.length > 0
				? `tools_required: [${skillData.toolsRequired.join(', ')}]`
				: null,
			`always_on: ${skillData.alwaysOn ? 'true' : 'false'}`,
			skillData.parentSkill ? `parent: ${skillData.parentSkill}` : null,
			skillData.group ? `group: ${skillData.group}` : null,
			skillData.requires?.length > 0
				? `requires: [${skillData.requires.join(', ')}]`
				: null,
			skillData.suggests?.length > 0
				? `suggests: [${skillData.suggests.join(', ')}]`
				: null,
			skillData.conflicts?.length > 0
				? `conflicts: [${skillData.conflicts.join(', ')}]`
				: null,
			'---',
		]
			.filter(Boolean)
			.join('\n');

		// Build body from instructions
		let body = '';
		if (skillData.instructions && skillData.instructions.length > 0) {
			body = skillData.instructions
				.map((section) => {
					let sectionMd = `\n## ${section.title || 'Instructions'
						}\n\n`;

					if (section.items && section.items.length > 0) {
						section.items.forEach((item, idx) => {
							if (section.format === 'numbered') {
								sectionMd += `${idx + 1}. ${item}\n`;
							} else if (section.format === 'bullets') {
								sectionMd += `- ${item}\n`;
							} else {
								sectionMd += `${item}\n`;
							}
						});
					}

					return sectionMd;
				})
				.join('\n');
		}

		return frontmatter + '\n' + body;
	}, [skillData]);

	// Token estimates
	const tokenStats = useMemo(() => {
		const total = estimateTokens(generatedMarkdown);
		const frontmatterEnd = generatedMarkdown.indexOf('---', 4);
		const frontmatter = estimateTokens(
			generatedMarkdown.substring(0, frontmatterEnd + 3)
		);
		const body = total - frontmatter;

		// Estimate with references
		const refTokens = (skillData?.references || []).reduce(
			(sum, ref) => {
				return sum + estimateTokens(ref.content);
			},
			0
		);

		return {
			total,
			frontmatter,
			body,
			references: refTokens,
			withRefs: total + refTokens,
		};
	}, [generatedMarkdown, skillData?.references]);

	const categoryStyle =
		CATEGORY_COLORS[skillData?.category] || CATEGORY_COLORS.general;
	const tabItems = [
		{ id: 'preview', label: __('Preview', 'agentflow-ai') },
		{
			id: 'tools',
			label: (
				<>
					{__('Tools', 'agentflow-ai')} (
					{skillData?.toolsRequired?.length || 0})
				</>
			),
		},
		{
			id: 'dependencies',
			label: __('Dependencies', 'agentflow-ai'),
		},
		{ id: 'raw', label: __('Raw', 'agentflow-ai') },
	];

	return (
		<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden flex flex-col h-full">
			{ /* Header */}
			<div className="px-6 py-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex items-start justify-between">
				<div>
					<span
						className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold mb-2"
						style={{
							backgroundColor: categoryStyle.bg,
							color: categoryStyle.color,
						}}
					>
						{skillData?.category || 'general'}
					</span>
					<h3 className="text-xl font-bold text-gray-900 dark:text-white m-0">
						{skillData?.displayName ||
							skillData?.name ||
							__('Untitled Skill', 'agentflow-ai')}
					</h3>
				</div>

				{showTestButton && (
					<button
						type="button"
						className="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
						onClick={onTest}
					>
						{__('Test Skill', 'agentflow-ai')}
					</button>
				)}
			</div>

			{ /* Stats bar */}
			<div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-px bg-gray-200 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700">
				<div className="bg-white dark:bg-gray-800 p-4">
					<span className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">
						{__('Tokens', 'agentflow-ai')}
					</span>
					<span className="block text-lg font-semibold text-gray-900 dark:text-white">
						~{tokenStats.total}
					</span>
				</div>
				<div className="bg-white dark:bg-gray-800 p-4">
					<span className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">
						{__('With Refs', 'agentflow-ai')}
					</span>
					<span className="block text-lg font-semibold text-gray-900 dark:text-white">
						~{tokenStats.withRefs}
					</span>
				</div>
				<div className="bg-white dark:bg-gray-800 p-4">
					<span className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">
						{__('Tools', 'agentflow-ai')}
					</span>
					<span className="block text-lg font-semibold text-gray-900 dark:text-white">
						{skillData?.toolsRequired?.length || 0}
					</span>
				</div>
				<div className="bg-white dark:bg-gray-800 p-4">
					<span className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">
						{__('References', 'agentflow-ai')}
					</span>
					<span className="block text-lg font-semibold text-gray-900 dark:text-white">
						{skillData?.references?.length || 0}
					</span>
				</div>
				<div className="bg-white dark:bg-gray-800 p-4 flex items-center gap-2">
					{skillData?.alwaysOn ? (
						<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
							{__('Always On', 'agentflow-ai')}
						</span>
					) : (
						<span className="text-gray-400 dark:text-gray-500 text-sm italic">
							{__('On Demand', 'agentflow-ai')}
						</span>
					)}
				</div>
			</div>

			{ /* Tabs */}
			<div className="border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 px-6">
				<nav className="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
					{tabItems.map((tab) => (
						<button
							key={tab.id}
							type="button"
							className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm focus:outline-none ${activeTab === tab.id
									? 'border-primary text-primary'
									: 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
								}`}
							onClick={() => setActiveTab(tab.id)}
						>
							{tab.label}
						</button>
					))}
				</nav>
			</div>

			{ /* Tab content */}
			<div className="p-6 flex-1 overflow-y-auto">
				{ /* Preview tab */}
				{activeTab === 'preview' && (
					<div className="prose prose-sm max-w-none dark:prose-invert">
						<MarkdownRenderer content={generatedMarkdown} />
					</div>
				)}

				{ /* Tools tab */}
				{activeTab === 'tools' && (
					<div>
						{skillData?.toolsRequired?.length > 0 ? (
							<ul className="space-y-3 m-0 p-0 list-none">
								{skillData.toolsRequired.map((tool) => (
									<li key={tool} className="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg border border-gray-100 dark:border-gray-700">
										<span className="flex-shrink-0 w-6 h-6 rounded flex items-center justify-center bg-gray-200 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
											<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
										</span>
										<span className="font-mono text-sm text-gray-800 dark:text-gray-200">
											{tool}
										</span>
									</li>
								))}
							</ul>
						) : (
							<div className="text-center py-8 text-gray-500 dark:text-gray-400">
								<p className="m-0">
									{__(
										'No tools required for this skill.',
										'agentflow-ai'
									)}
								</p>
							</div>
						)}
					</div>
				)}

				{ /* Dependencies tab */}
				{activeTab === 'dependencies' && (
					<div>
						<DependencyDisplay
							requires={skillData?.requires || []}
							suggests={skillData?.suggests || []}
							conflicts={skillData?.conflicts || []}
						/>
					</div>
				)}

				{ /* Raw tab */}
				{activeTab === 'raw' && (
					<div className="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4 overflow-x-auto">
						<pre className="text-xs font-mono text-gray-700 dark:text-gray-300 whitespace-pre-wrap word-break-words m-0">
							{generatedMarkdown}
						</pre>
					</div>
				)}
			</div>
		</div>
	);
}

/**
 * Simple Markdown Renderer (basic formatting)
 * @param root0
 * @param root0.content
 */
function MarkdownRenderer({ content }) {
	const html = useMemo(() => {
		if (!content) {
			return '';
		}

		const result = content
			// Remove frontmatter for display
			.replace(/^---[\s\S]*?---\n*/m, '')
			// Headers
			.replace(/^### (.+)$/gm, '<h4>$1</h4>')
			.replace(/^## (.+)$/gm, '<h3>$1</h3>')
			.replace(/^# (.+)$/gm, '<h2>$1</h2>')
			// Bold
			.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
			// Italic
			.replace(/\*(.+?)\*/g, '<em>$1</em>')
			// Code
			.replace(/`(.+?)`/g, '<code>$1</code>')
			// Bullet lists
			.replace(/^- (.+)$/gm, '<li>$1</li>')
			// Numbered lists
			.replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
			// Paragraphs
			.replace(/\n\n/g, '</p><p>')
			// Line breaks
			.replace(/\n/g, '<br/>');

		return `<div class="content-wrapper"><p>${result}</p></div>`;
	}, [content]);

	return (
		<div
			className="prose prose-sm max-w-none dark:prose-invert prose-p:my-2 prose-h2:mt-6 prose-h2:mb-4 prose-h3:mt-5 prose-h3:mb-3 prose-h4:mt-4 prose-h4:mb-2 prose-li:my-1 prose-ul:my-3 prose-ol:my-3 text-gray-700 dark:text-gray-300"
			dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(html) }}
		/>
	);
}

/**
 * Dependency Display
 * @param root0
 * @param root0.requires
 * @param root0.suggests
 * @param root0.conflicts
 */
function DependencyDisplay({ requires, suggests, conflicts }) {
	const hasAny =
		requires.length > 0 || suggests.length > 0 || conflicts.length > 0;

	if (!hasAny) {
		return (
			<div className="text-center py-8 text-gray-500 dark:text-gray-400">
				<p className="m-0">{__('No dependencies defined.', 'agentflow-ai')}</p>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			{requires.length > 0 && (
				<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden text-sm">
					<div className="px-4 py-3 bg-blue-50/50 dark:bg-blue-900/10 border-b border-gray-200 dark:border-gray-700">
						<h4 className="font-medium text-gray-900 dark:text-white m-0 flex items-center gap-2">
							<span className="w-2 h-2 rounded-full bg-blue-500"></span>
							{__('Requires', 'agentflow-ai')}
						</h4>
						<p className="text-xs text-gray-500 dark:text-gray-400 mt-1 m-0 block">
							{__('Must be loaded before this skill', 'agentflow-ai')}
						</p>
					</div>
					<div className="p-4 flex flex-wrap gap-2">
						{requires.map((id) => (
							<span
								key={id}
								className="inline-flex items-center px-2.5 py-1 rounded text-xs font-mono font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800"
							>
								{id}
							</span>
						))}
					</div>
				</div>
			)}
			{suggests.length > 0 && (
				<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden text-sm">
					<div className="px-4 py-3 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
						<h4 className="font-medium text-gray-900 dark:text-white m-0 flex items-center gap-2">
							<span className="w-2 h-2 rounded-full bg-gray-400"></span>
							{__('Suggests', 'agentflow-ai')}
						</h4>
						<p className="text-xs text-gray-500 dark:text-gray-400 mt-1 m-0 block">
							{__('Recommended to load after this skill', 'agentflow-ai')}
						</p>
					</div>
					<div className="p-4 flex flex-wrap gap-2">
						{suggests.map((id) => (
							<span
								key={id}
								className="inline-flex items-center px-2.5 py-1 rounded text-xs font-mono font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600"
							>
								{id}
							</span>
						))}
					</div>
				</div>
			)}
			{conflicts.length > 0 && (
				<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden text-sm">
					<div className="px-4 py-3 bg-red-50/50 dark:bg-red-900/10 border-b border-gray-200 dark:border-gray-700">
						<h4 className="font-medium text-gray-900 dark:text-white m-0 flex items-center gap-2">
							<span className="w-2 h-2 rounded-full bg-red-500"></span>
							{__('Conflicts', 'agentflow-ai')}
						</h4>
						<p className="text-xs text-gray-500 dark:text-gray-400 mt-1 m-0 block">
							{__('Cannot be used together', 'agentflow-ai')}
						</p>
					</div>
					<div className="p-4 flex flex-wrap gap-2">
						{conflicts.map((id) => (
							<span
								key={id}
								className="inline-flex items-center px-2.5 py-1 rounded text-xs font-mono font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border border-red-200 dark:border-red-800"
							>
								{id}
							</span>
						))}
					</div>
				</div>
			)}
		</div>
	);
}

DependencyDisplay.propTypes = {
	requires: PropTypes.array,
	suggests: PropTypes.array,
	conflicts: PropTypes.array,
};
