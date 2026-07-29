import PropTypes from 'prop-types';
import { cn } from './utils';

const SIZE_MAP = {
	xs: 'max-w-xs',
	sm: 'max-w-sm',
	md: 'max-w-md',
	lg: 'max-w-lg',
	xl: 'max-w-xl',
};

export default function Drawer({
	isOpen,
	onClose,
	title,
	position = 'right',
	size = 'md',
	children,
	className = '',
	...props
}) {
	if (!isOpen) {
		return null;
	}

	const isLeft = position === 'left';

	return (
		<div className="fixed inset-0 z-50 flex">
			<button
				type="button"
				className="absolute inset-0 bg-slate-950/60"
				aria-label="Close drawer"
				onClick={onClose}
			/>
			<div
				className={cn(
					`relative z-10 h-full w-full ${isLeft ? 'mr-auto' : 'ml-auto'} bg-white shadow-xl dark:bg-slate-900`,
					SIZE_MAP[size] || SIZE_MAP.md,
					className
				)}
				{...props}
			>
				<div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
					<h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">{title}</h3>
					<button
						type="button"
						onClick={onClose}
						className="rounded-md px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
					>
						Close
					</button>
				</div>
				<div className="px-5 py-4">{children}</div>
			</div>
		</div>
	);
}

Drawer.propTypes = {
	isOpen: PropTypes.bool,
	onClose: PropTypes.func.isRequired,
	title: PropTypes.node,
	position: PropTypes.string,
	size: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
	children: PropTypes.node,
	className: PropTypes.string,
};
