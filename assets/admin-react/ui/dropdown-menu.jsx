import { useEffect, useRef, useState } from '@wordpress/element';
import PropTypes from 'prop-types';
import { cn } from './utils';
import { buttonVariants } from './button';
import Icon from './icon';

const renderIcon = (icon, size = 16) => (icon ? <Icon icon={icon} size={size} /> : null);

export default function DropdownMenu({
	controls = [],
	icon,
	label,
	className = '',
	size = 'sm',
	variant = 'secondary',
	...props
}) {
	const [open, setOpen] = useState(false);
	const ref = useRef(null);
	const filteredControls = controls.filter(Boolean);
	const buttonLabel = label || 'Open menu';

	useEffect(() => {
		const handleClickOutside = (event) => {
			if (ref.current && !ref.current.contains(event.target)) {
				setOpen(false);
			}
		};
		document.addEventListener('mousedown', handleClickOutside);
		return () => document.removeEventListener('mousedown', handleClickOutside);
	}, []);

	return (
		<div className="relative inline-flex" ref={ref} {...props}>
			<button
				type="button"
				aria-label={buttonLabel}
				title={buttonLabel}
				className={cn(buttonVariants({ variant, size }), className)}
				onClick={() => setOpen((prev) => !prev)}
			>
				{renderIcon(icon, 18)}
			</button>
			{open && (
				<div className="absolute right-0 z-20 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-800 dark:bg-slate-900">
					{filteredControls.map((control, index) => (
						<button
							key={control.title || index}
							type="button"
							disabled={control.isDisabled}
							onClick={() => {
								control.onClick?.();
								setOpen(false);
							}}
							className={cn(
								'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition',
								control.isDestructive
									? 'text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40'
									: 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800',
								control.isDisabled ? 'cursor-not-allowed opacity-50' : ''
							)}
						>
							{renderIcon(control.icon, 14)}
							<span>{control.title}</span>
						</button>
					))}
				</div>
			)}
		</div>
	);
}

DropdownMenu.propTypes = {
	controls: PropTypes.arrayOf(
		PropTypes.shape({
			title: PropTypes.node,
			icon: PropTypes.any,
			onClick: PropTypes.func,
			isDestructive: PropTypes.bool,
			isDisabled: PropTypes.bool,
		})
	),
	icon: PropTypes.any,
	label: PropTypes.string,
	className: PropTypes.string,
	size: PropTypes.string,
	variant: PropTypes.string,
};
