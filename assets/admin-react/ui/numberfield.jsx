import PropTypes from 'prop-types';
import { cn } from './utils';

const coerceValue = (value) => (value === null || value === undefined ? '' : value);

export default function NumberField({
	label,
	help,
	error,
	value,
	defaultValue,
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
		onChange(nextValue !== undefined ? nextValue : eventOrValue);
	};

	return (
		<label className={cn('block w-full space-y-1', className)}>
			{label && <span className="text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>}
			<input
				type="number"
				value={value !== undefined ? coerceValue(value) : undefined}
				defaultValue={defaultValue}
				onChange={handleChange}
				className={cn(
					'block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100',
					error ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200' : ''
				)}
				{...props}
			/>
			{help && <span className="text-xs text-slate-500 dark:text-slate-400">{help}</span>}
			{error && <span className="text-xs text-rose-600">{error}</span>}
		</label>
	);
}

NumberField.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	error: PropTypes.node,
	value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
	defaultValue: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
	onChange: PropTypes.func,
	className: PropTypes.string,
};
