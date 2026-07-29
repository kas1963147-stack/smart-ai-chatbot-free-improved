/**
 * TemplateGallery Component
 *
 * Display and select from pre-built skill templates.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

// Template metadata
const TEMPLATES = [
	{
		id: 'refund-handling',
		name: 'Refund Handling',
		category: 'woocommerce',
		description:
			'Handle refund requests and returns with policy enforcement',
		tools: ['wc_get_order', 'wc_process_refund'],
	},
	{
		id: 'order-lookup',
		name: 'Order Lookup',
		category: 'woocommerce',
		description: 'Look up order status, tracking, and shipping information',
		tools: ['wc_get_order', 'wc_search_orders'],
	},
	{
		id: 'lead-generation',
		name: 'Lead Generation',
		category: 'support',
		description: 'Qualify leads and capture contact information',
		tools: [],
	},
	{
		id: 'appointment-booking',
		name: 'Appointment Booking',
		category: 'support',
		description: 'Schedule appointments and manage bookings',
		tools: [],
	},
	{
		id: 'faq-responder',
		name: 'FAQ Responder',
		category: 'general',
		description: 'Answer frequently asked questions accurately',
		tools: [],
	},
	{
		id: 'product-recommendations',
		name: 'Product Recommendations',
		category: 'woocommerce',
		description: 'Recommend products based on customer needs',
		tools: ['wc_search_products', 'wc_get_product'],
	},
];

const CATEGORIES = {
	all: { label: 'All Templates' },
	woocommerce: { label: 'WooCommerce' },
	support: { label: 'Support' },
	general: { label: 'General' },
};

export default function TemplateGallery({ onSelectTemplate, onClose }) {
	const [activeCategory, setActiveCategory] = useState('all');
	const [selectedTemplate, setSelectedTemplate] = useState(null);
	const [previewContent, setPreviewContent] = useState(null);
	const [loading, setLoading] = useState(false);

	// Filter templates
	const filteredTemplates =
		activeCategory === 'all'
			? TEMPLATES
			: TEMPLATES.filter((t) => t.category === activeCategory);

	// Load template preview
	const loadPreview = async (templateId) => {
		setLoading(true);
		try {
			const response = await apiFetch({
				path: `/smart-ai-chatbot/v1/skill-templates/${templateId}`,
			});

			if (response.success) {
				setPreviewContent(response.data);
			}
		} catch (err) {
			console.error('Failed to load template:', err);
		} finally {
			setLoading(false);
		}
	};

	// Select template for preview
	const handleSelect = (template) => {
		setSelectedTemplate(template);
		loadPreview(template.id);
	};

	// Use template
	const handleUseTemplate = () => {
		if (selectedTemplate && previewContent) {
			onSelectTemplate({
				...selectedTemplate,
				content: previewContent,
			});
		}
	};

	return (
		<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
			<div className="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
				<h2 className="text-xl font-semibold text-gray-900 dark:text-white m-0">{__('Skill Templates', 'smart-woo-chatbot')}</h2>
				<p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
					{__('Start with a pre-built template and customize it for your needs.', 'smart-woo-chatbot')}
				</p>
			</div>

			{ /* Category tabs */}
			<div className="border-b border-gray-200 dark:border-gray-700 px-6 bg-gray-50/50 dark:bg-gray-800/50">
				<nav className="-mb-px flex space-x-8" aria-label="Tabs">
					{Object.entries(CATEGORIES).map(([catId, cat]) => (
						<button
							key={catId}
							type="button"
							className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm ${activeCategory === catId
									? 'border-primary text-primary'
									: 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
								}`}
							onClick={() => setActiveCategory(catId)}
						>
							{cat.label}
						</button>
					))}
				</nav>
			</div>

			<div className="flex flex-col lg:flex-row min-h-[500px]">
				{ /* Template grid */}
				<div className="w-full lg:w-1/2 border-r border-gray-200 dark:border-gray-700 p-6 overflow-y-auto max-h-[600px]">
					<div className="grid grid-cols-1 gap-4">
						{filteredTemplates.map((template) => (
							<button
								key={template.id}
								type="button"
								className={`text-left w-full rounded-xl border p-4 transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${selectedTemplate?.id === template.id
										? 'border-primary bg-primary/5 dark:bg-primary/10 ring-1 ring-primary'
										: 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-primary/50 hover:bg-gray-50 dark:hover:bg-gray-700'
									}`}
								onClick={() => handleSelect(template)}
							>
								<h4 className="text-base font-semibold text-gray-900 dark:text-white m-0">{template.name}</h4>
								<p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{template.description}</p>

								{template.tools && template.tools.length > 0 && (
									<div className="flex flex-wrap gap-2 mt-3">
										{template.tools.slice(0, 2).map((tool) => (
											<span
												key={tool}
												className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600"
											>
												{tool}
											</span>
										))}
										{template.tools.length > 2 && (
											<span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
												+{template.tools.length - 2}
											</span>
										)}
									</div>
								)}
							</button>
						))}
					</div>
				</div>

				{ /* Preview panel */}
				<div className="w-full lg:w-1/2 bg-gray-50 dark:bg-gray-900/50 p-6 flex flex-col">
					{selectedTemplate ? (
						<div className="h-full flex flex-col bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
							<div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/50">
								<h3 className="text-sm font-semibold text-gray-900 dark:text-white m-0">
									{__('Preview:', 'smart-woo-chatbot')} {selectedTemplate.name}
								</h3>
							</div>

							<div className="flex-1 p-4 overflow-y-auto">
								{loading ? (
									<div className="flex items-center justify-center h-40 text-gray-500">
										<svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
											<circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
											<path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
										</svg>
										{__('Loading preview…', 'smart-woo-chatbot')}
									</div>
								) : previewContent ? (
									<pre className="text-xs text-gray-600 dark:text-gray-300 font-mono whitespace-pre-wrap bg-gray-50 dark:bg-gray-900 p-4 rounded border border-gray-200 dark:border-gray-700">
										{previewContent.body?.substring(0, 800)}
										{previewContent.body?.length > 800 ? '\n\n... (truncated for preview)' : ''}
									</pre>
								) : (
									<div className="flex items-center justify-center h-40 text-gray-500 italic">
										{__('Preview content not available.', 'smart-woo-chatbot')}
									</div>
								)}
							</div>

							<div className="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex items-center justify-end gap-3 space-x-0">
								<button
									type="button"
									className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
									onClick={onClose}
								>
									{__('Cancel', 'smart-woo-chatbot')}
								</button>
								<button
									type="button"
									className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
									onClick={handleUseTemplate}
									disabled={!previewContent}
								>
									{__('Use This Template', 'smart-woo-chatbot')}
								</button>
							</div>
						</div>
					) : (
						<div className="flex items-center justify-center h-full text-gray-500 dark:text-gray-400">
							<div className="text-center">
								<svg className="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
								</svg>
								<p>{__('Select a template to preview its contents', 'smart-woo-chatbot')}</p>
							</div>
						</div>
					)}
				</div>
			</div>
		</div>
	);
}
