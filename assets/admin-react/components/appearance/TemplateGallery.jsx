/**
 * Template Gallery Component
 *
 * Visual template selector with preview cards for quick theme application.
 * Tailwind-only implementation.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Button, cn } from '../ui';
import { TEMPLATES } from './theme-templates';

export default function TemplateGallery({
	currentTemplate,
	onSelect,
	onCustomize,
}) {
	return (
		<div className="space-y-4">
			<div>
				<h3 className="text-lg font-semibold text-slate-900">
					{__('Choose a Template', 'smart-woo-chatbot')}
				</h3>
				<p className="mt-1 text-sm text-slate-500">
					{__('Pick a ready-made look and feel for your chatbot.', 'smart-woo-chatbot')}
				</p>
			</div>

			<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
				{TEMPLATES.map((template) => {
					const isActive = currentTemplate === template.id;
					const previewPrimary = template.variables['--swc-primary'] || '#6366f1';
					const previewBg = template.variables['--swc-bg-main'] || '#ffffff';
					const previewLight = template.variables['--swc-bg-light'] || '#f8fafc';
					const previewBot = template.variables['--swc-bg-message-bot'] || '#ffffff';

					return (
						<div
							key={template.id}
							role="button"
							tabIndex={0}
							onClick={() => onSelect(template)}
							onKeyDown={(event) => {
								if (event.key === 'Enter' || event.key === ' ') {
									event.preventDefault();
									onSelect(template);
								}
							}}
							className={cn(
								'group rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
								isActive ? 'border-primary ring-2 ring-primary/20' : 'border-slate-200'
							)}
						>
							<div
								className="flex h-28 items-center justify-center rounded-xl border border-slate-100"
								style={{ background: previewLight }}
							>
								<div
									className="flex h-20 w-11/12 flex-col overflow-hidden rounded-lg border"
									style={{
										background: previewBg,
										borderColor: template.variables['--swc-border-color'] || '#e2e8f0',
									}}
								>
									<div style={{ height: 10, background: previewPrimary }} />
									<div className="flex flex-1 flex-col gap-2 p-2" style={{ background: previewLight }}>
										<div className="h-2 w-3/4 rounded" style={{ background: previewBot }} />
										<div className="h-2 w-1/2 self-end rounded" style={{ background: previewPrimary, opacity: 0.8 }} />
									</div>
								</div>
							</div>

							<div className="mt-3 space-y-1">
								<div className="flex items-center justify-between">
									<h4 className="text-sm font-semibold text-slate-900">
										{template.name}
									</h4>
									{isActive && (
										<span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
											{__('Active', 'smart-woo-chatbot')}
										</span>
									)}
								</div>
								<p className="text-xs text-slate-500">
									{template.description}
								</p>
							</div>

							<div className="mt-4 flex flex-wrap gap-2">
								<Button
									size="xs"
									variant="primary"
									onClick={(event) => {
										event.stopPropagation();
										onSelect(template);
									}}
								>
									{__('Apply', 'smart-woo-chatbot')}
								</Button>
								{onCustomize && (
									<Button
										size="xs"
										variant="secondary"
										onClick={(event) => {
											event.stopPropagation();
											onCustomize(template);
										}}
									>
										{__('Edit', 'smart-woo-chatbot')}
									</Button>
								)}
							</div>
						</div>
					);
				})}
			</div>
		</div>
	);
}

TemplateGallery.propTypes = {
	currentTemplate: PropTypes.string,
	onSelect: PropTypes.func.isRequired,
	onCustomize: PropTypes.func,
};
