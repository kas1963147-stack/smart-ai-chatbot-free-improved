import PropTypes from 'prop-types';
import { cn } from './utils';

const STATUS_STYLES = {
	success: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-100',
	error: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-100',
	warning: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-100',
	info: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-100',
};

export default function Notice({
	status = 'info',
	title,
	children,
	isDismissible = false,
	onRemove,
	className = '',
	...props
}) {
	const showClose = isDismissible || typeof onRemove === 'function';

	return (
		<div
			role="alert"
			className={cn(
				'flex w-full items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-sm',
				STATUS_STYLES[status] || STATUS_STYLES.info,
				className
			)}
			{...props}
		>
			<div className="flex-1">
				{title && <div className="mb-1 text-sm font-semibold">{title}</div>}
				<div className="text-sm leading-relaxed">{children}</div>
			</div>
			{showClose && (
				<button
					type="button"
					onClick={onRemove}
					className="rounded-md px-2 py-1 text-xs font-semibold uppercase tracking-wide text-current/70 transition hover:text-current"
					aria-label="Dismiss"
				>
					Close
				</button>
			)}
		</div>
	);
}

Notice.propTypes = {
	status: PropTypes.string,
	title: PropTypes.node,
	children: PropTypes.node,
	isDismissible: PropTypes.bool,
	onRemove: PropTypes.func,
	className: PropTypes.string,
};
