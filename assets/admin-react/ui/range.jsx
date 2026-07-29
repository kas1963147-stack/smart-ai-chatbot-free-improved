import PropTypes from 'prop-types';
import { cn } from './utils';

export default function Range({
	label,
	help,
	error,
	value,
	onChange,
	className = '',
	...props
}) {
	const handleChange = (eventOrValue) => {
		if (!onChange) {
			return;
		}
		if (typeof eventOrValue === 'number') {
			onChange(eventOrValue);
			return;
		}
		const nextValue = eventOrValue?.currentTarget?.value;
		onChange(nextValue !== undefined ? Number(nextValue) : eventOrValue);
	};

	return (
		<label className={cn('block w-full space-y-2', className)}>
			{label && <span className="text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>}
			<input
				type="range"
				value={value ?? 0}
				onChange={handleChange}
				className={cn('w-full accent-primary', error ? 'accent-rose-500' : '')}
				{...props}
			/>
			{help && <span className="text-xs text-slate-500 dark:text-slate-400">{help}</span>}
			{error && <span className="text-xs text-rose-600">{error}</span>}
		</label>
	);
}

Range.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	error: PropTypes.node,
	value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
	onChange: PropTypes.func,
	className: PropTypes.string,
};
