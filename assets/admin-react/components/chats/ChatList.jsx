/**
 * ChatList Component
 *
 * Displays a grid of chat widgets with modern card styling and actions.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { DropdownMenu } from '../ui';
import { moreVertical, trash, edit } from '@wordpress/icons';
import { EntityIcon } from '../shared/entity-icons';

// Widget Avatar with SVG icon - uses the widget's theme color
const WidgetAvatar = ({ name, isActive, themeColor }) => {
	const bgColor = themeColor || (isActive ? '#6366f1' : '#9ca3af');
	return (
		<div className="relative">
			<div
				className="flex items-center justify-center w-12 h-12 rounded-xl text-white shadow-sm"
				style={{ backgroundColor: bgColor }}
			>
				<EntityIcon name={name} size="w-6 h-6" />
			</div>
			{/* Status indicator */}
			<span className={`
				absolute -bottom-0.5 -right-0.5
				w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-800
				${isActive ? 'bg-green-500' : 'bg-gray-400'}
			`} />
		</div>
	);
};

export default function ChatList({ widgets, onEdit, onDelete }) {
	if (!widgets || widgets.length === 0) {
		return null; // Empty state handled in parent
	}

	return (
		<div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
			{widgets.map((widget) => {
				const widgetName = widget.display_name || widget.name || 'Widget';
				const themeColor = widget.appearance?.color_primary
					|| widget.appearance?.colors?.primary
					|| null;
				const templateName = widget.appearance?.template;

				return (
					<article
						key={widget.id}
						className="group relative bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm transition-all duration-200 hover:shadow-lg hover:border-primary/20"
					>
						{/* Theme color accent bar */}
						{themeColor && (
							<div
								className="h-1 w-full rounded-t-xl"
								style={{ background: `linear-gradient(90deg, ${themeColor}, ${themeColor}cc)` }}
							/>
						)}

						<div 
							className="p-4 cursor-pointer"
							onClick={() => onEdit(widget)}
							role="button"
							tabIndex={0}
							onKeyDown={(e) => {
								if (e.key === 'Enter' || e.key === ' ') {
									e.preventDefault();
									onEdit(widget);
								}
							}}
						>
							<div className="flex items-start gap-3">
								{/* Avatar */}
								<WidgetAvatar name={widgetName} isActive={widget.is_active} themeColor={themeColor} />

								{/* Info */}
								<div className="flex-1 min-w-0">
									<h3 className="text-base font-semibold text-gray-900 dark:text-white truncate pr-8">
										{widgetName}
									</h3>

									{widget.description && (
										<p className="text-xs text-gray-500 dark:text-slate-400 line-clamp-2 mt-0.5">
											{widget.description}
										</p>
									)}

									{/* Theme indicator label */}
									{templateName && (
										<div className="flex items-center gap-1.5 mt-1.5">
											<span
												className="w-2.5 h-2.5 rounded-full shrink-0 border border-white dark:border-slate-700 shadow-sm"
												style={{ backgroundColor: themeColor || '#6366f1' }}
											/>
											<span className="text-[10px] font-medium text-gray-400 dark:text-slate-500 capitalize">
												{templateName.replace(/-/g, ' ')}
											</span>
										</div>
									)}
								</div>
							</div>
						</div>

						{/* 3 Dots Menu - absolutely positioned to not trigger card click */}
						<div className="absolute top-4 right-4 shrink-0 text-gray-400" onClick={(e) => e.stopPropagation()}>
							<DropdownMenu
								icon={moreVertical}
								label={__('More actions', 'agentflow-ai')}
								controls={[
									{
										title: __('Edit', 'agentflow-ai'),
										icon: edit,
										onClick: () => onEdit(widget),
									},
									{
										title: __('Delete', 'agentflow-ai'),
										icon: trash,
										onClick: () => onDelete(widget.id),
										isDestructive: true,
									},
								]}
							/>
						</div>
					</article>
				);
			})}
		</div>
	);
}

ChatList.propTypes = {
	widgets: PropTypes.array.isRequired,
	onEdit: PropTypes.func,
	onDelete: PropTypes.func,
};
