import PropTypes from 'prop-types';
import { cn } from './utils';

export default function Table({ children, className = '', ...props }) {
	return (
		<div className="w-full overflow-x-auto">
			<table className={cn('w-full text-left text-sm', className)} {...props}>
				{children}
			</table>
		</div>
	);
}

export function TableThead({ children, className = '', ...props }) {
	return (
		<thead className={cn('bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-900', className)} {...props}>
			{children}
		</thead>
	);
}

export function TableTbody({ children, className = '', ...props }) {
	return <tbody className={cn('divide-y divide-slate-200 dark:divide-slate-800', className)} {...props}>{children}</tbody>;
}

export function TableTfoot({ children, className = '', ...props }) {
	return <tfoot className={cn('bg-slate-50 text-xs text-slate-500 dark:bg-slate-900', className)} {...props}>{children}</tfoot>;
}

export function TableTr({ children, className = '', ...props }) {
	return <tr className={cn('hover:bg-slate-50 dark:hover:bg-slate-900/60', className)} {...props}>{children}</tr>;
}

export function TableTh({ children, className = '', ...props }) {
	return <th className={cn('px-4 py-3 font-semibold', className)} {...props}>{children}</th>;
}

export function TableTd({ children, className = '', ...props }) {
	return <td className={cn('px-4 py-3 text-slate-700 dark:text-slate-200', className)} {...props}>{children}</td>;
}

Table.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

TableThead.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

TableTbody.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

TableTfoot.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

TableTr.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

TableTh.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

TableTd.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};
