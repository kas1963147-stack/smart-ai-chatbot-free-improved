import PropTypes from 'prop-types';
import { cn } from './utils';

export default function Checkbox({
	label,
	help,
	checked = false,
	onChange,
	className = '',
	...props
}) {
	const handleChange = (eventOrValue) => {
		if (!onChange) {
			return;
		}
		if (typeof eventOrValue === 'boolean') {
			onChange(eventOrValue);
			return;
		}
		const nextValue = eventOrValue?.currentTarget?.checked;
		onChange(typeof nextValue === 'boolean' ? nextValue : !!eventOrValue);
	};

	return (
		<label className={cn('flex cursor-pointer items-start gap-3 text-sm group', className)}>
			<div className="relative flex-shrink-0 mt-0.5">
				<input
					type="checkbox"
					checked={!!checked}
					onChange={handleChange}
					className="sr-only peer"
					{...props}
				/>
				<div
					className={cn(
						'w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all duration-200',
						checked
							? 'bg-primary border-primary'
							: 'bg-white border-slate-300 group-hover:border-slate-400'
					)}
				>
					{checked && (
						<svg
							className="w-3.5 h-3.5 text-white"
							fill="none"
							viewBox="0 0 24 24"
							stroke="currentColor"
							strokeWidth={3}
						>
							<path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
						</svg>
					)}
				</div>
			</div>
			<span className="flex-1 space-y-1">
				{label && <span className="block font-medium text-slate-800">{label}</span>}
				{help && <span className="block text-xs text-slate-500 leading-relaxed">{help}</span>}
			</span>
		</label>
	);
}

Checkbox.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	checked: PropTypes.bool,
	onChange: PropTypes.func,
	className: PropTypes.string,
};
