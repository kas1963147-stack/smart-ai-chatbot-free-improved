import PropTypes from 'prop-types';
import { cn } from './utils';

const VARIANT_STYLES = {
	primary: 'bg-primary text-primary-foreground hover:bg-primary/90',
	secondary: 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700',
	tertiary: 'bg-transparent text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
	ghost: 'bg-transparent text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
	danger: 'bg-rose-600 text-white hover:bg-rose-700',
	warning: 'bg-amber-500 text-white hover:bg-amber-600',
};

const SIZE_STYLES = {
	xs: 'h-7 w-7 text-xs',
	sm: 'h-8 w-8 text-sm',
	md: 'h-9 w-9 text-base',
	lg: 'h-10 w-10 text-base',
};

export default function IconButton({
	children,
	icon,
	variant = 'ghost',
	size = 'sm',
	className = '',
	isDestructive = false,
	...props
}) {
	const resolvedVariant = isDestructive ? 'danger' : variant;
	const content = icon || children;

	return (
		<button
			type="button"
			className={cn(
				'inline-flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
				VARIANT_STYLES[resolvedVariant] || VARIANT_STYLES.ghost,
				SIZE_STYLES[size] || SIZE_STYLES.sm,
				className
			)}
			{...props}
		>
			{content}
		</button>
	);
}

IconButton.propTypes = {
	children: PropTypes.node,
	icon: PropTypes.node,
	variant: PropTypes.string,
	size: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
	className: PropTypes.string,
	isDestructive: PropTypes.bool,
};
