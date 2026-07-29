import PropTypes from 'prop-types';
import { cn } from './utils';

const normalizeOptions = (options) => {
	if (!Array.isArray(options)) {
		return [];
	}
	return options.map((option) => ({
		value: String(option.value ?? option.id ?? option.label ?? ''),
		label: option.label ?? option.name ?? option.value ?? option.id ?? '',
		...option,
	}));
};

export default function Select({
	label,
	help,
	error,
	options,
	data,
	value,
	onChange,
	multiselect = false,
	className = '',
	...props
}) {
	const resolvedData = data || normalizeOptions(options);

	const handleChange = (event) => {
		if (!onChange) {
			return;
		}
		if (multiselect) {
			const selected = Array.from(event.target.selectedOptions).map((option) => option.value);
			onChange(selected);
			return;
		}
		onChange(event.target.value);
	};

	return (
		<label className={cn('block w-full space-y-1', className)}>
			{label && <span className="text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>}
			<select
				value={value ?? (multiselect ? [] : '')}
				onChange={handleChange}
				multiple={multiselect}
				className={cn(
					'block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100',
					error ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200' : ''
				)}
				{...props}
			>
				{resolvedData.map((option) => (
					<option key={option.value} value={option.value} disabled={option.disabled}>
						{option.label}
					</option>
				))}
			</select>
			{help && <span className="text-xs text-slate-500 dark:text-slate-400">{help}</span>}
			{error && <span className="text-xs text-rose-600">{error}</span>}
		</label>
	);
}

Select.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	error: PropTypes.node,
	options: PropTypes.array,
	data: PropTypes.array,
	value: PropTypes.oneOfType([PropTypes.string, PropTypes.array]),
	onChange: PropTypes.func,
	multiselect: PropTypes.bool,
	className: PropTypes.string,
};
