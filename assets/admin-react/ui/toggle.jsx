import PropTypes from 'prop-types';
import { cn } from './utils';

export default function Toggle({
	label,
	help,
	checked = false,
	onChange,
	className = '',
	...props
}) {
	const handleClick = () => {
		if (onChange) {
			onChange(!checked);
		}
	};

	return (
		<div
			className={cn('flex items-start justify-between gap-4 cursor-pointer group', className)}
			onClick={handleClick}
			role="switch"
			aria-checked={!!checked}
			tabIndex={0}
			onKeyDown={(e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					handleClick();
				}
			}}
		>
			<span className="flex-1 space-y-1 select-none">
				{label && <span className="block font-medium text-slate-800">{label}</span>}
				{help && <span className="block text-xs text-slate-500 leading-relaxed">{help}</span>}
			</span>
			<div className="relative flex-shrink-0 mt-0.5">
				<div
					className={cn(
						'w-12 h-7 rounded-full transition-colors duration-200 border-2',
						checked
							? 'bg-primary border-primary'
							: 'bg-slate-300 border-slate-300'
					)}
				>
					<div
						className={cn(
							'absolute top-[3px] left-[3px] w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-200',
							checked ? 'translate-x-5' : 'translate-x-0'
						)}
					/>
				</div>
			</div>
		</div>
	);
}

Toggle.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	checked: PropTypes.bool,
	onChange: PropTypes.func,
	className: PropTypes.string,
};
