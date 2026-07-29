import PropTypes from 'prop-types';
import { cn } from './utils';

export default function Tabs({ tabs, activeId, onChange, fullWidth = false, className = '' }) {
	return (
		<div className={cn('flex flex-wrap gap-2', className)} role="tablist">
			{tabs.map((tab) => {
				const isActive = activeId === tab.id;
				return (
					<button
						key={tab.id}
						type="button"
						role="tab"
						aria-selected={isActive}
						disabled={tab.disabled}
						className={cn(
							'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
							fullWidth ? 'flex-1 justify-center' : '',
							isActive
								? 'bg-primary text-primary-foreground shadow-sm'
								: 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
							tab.disabled ? 'cursor-not-allowed opacity-50' : ''
						)}
						onClick={() => !tab.disabled && onChange?.(tab.id)}
					>
						{tab.icon && <span className="text-base">{tab.icon}</span>}
						<span>{tab.label}</span>
					</button>
				);
			})}
		</div>
	);
}

Tabs.propTypes = {
	tabs: PropTypes.arrayOf(
		PropTypes.shape({
			id: PropTypes.string.isRequired,
			label: PropTypes.node.isRequired,
			icon: PropTypes.node,
			disabled: PropTypes.bool,
		})
	).isRequired,
	activeId: PropTypes.string,
	onChange: PropTypes.func,
	fullWidth: PropTypes.bool,
	className: PropTypes.string,
};
