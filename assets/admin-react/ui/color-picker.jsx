import PropTypes from 'prop-types';
import { cn } from './utils';

export default function ColorPicker({ label, value, onChange, className = '', ...props }) {
	return (
		<label className={cn('flex items-center justify-between gap-3 text-sm text-slate-700 dark:text-slate-200', className)}>
			{label && <span className="font-medium whitespace-nowrap">{label}</span>}
			<input
				type="color"
				value={value}
				onChange={(event) => onChange?.(event.target.value)}
				className="h-10 w-14 shrink-0 cursor-pointer rounded-md border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-950"
				{...props}
			/>
		</label>
	);
}

ColorPicker.propTypes = {
	label: PropTypes.node,
	value: PropTypes.string,
	onChange: PropTypes.func,
	className: PropTypes.string,
};
