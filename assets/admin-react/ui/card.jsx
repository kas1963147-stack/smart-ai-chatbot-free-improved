import PropTypes from 'prop-types';
import { cn } from './utils';

export function Card({ children, className = '', ...props }) {
	return (
		<div
			className={cn(
				'rounded-2xl border border-slate-200/70 bg-white shadow-sm dark:border-slate-800/70 dark:bg-slate-900',
				className
			)}
			{...props}
		>
			{children}
		</div>
	);
}

export function CardHeader({ children, className = '', ...props }) {
	return (
		<div className={cn('border-b border-slate-200/70 px-5 py-4 dark:border-slate-800/70', className)} {...props}>
			{children}
		</div>
	);
}

export function CardTitle({ children, className = '', as: Tag = 'h3', ...props }) {
	return (
		<Tag className={cn('text-base font-semibold text-slate-900 dark:text-slate-100', className)} {...props}>
			{children}
		</Tag>
	);
}

export function CardSubtitle({ children, className = '', as: Tag = 'p', ...props }) {
	return (
		<Tag className={cn('text-sm text-slate-500 dark:text-slate-400', className)} {...props}>
			{children}
		</Tag>
	);
}

export function CardBody({ children, className = '', ...props }) {
	return (
		<div className={cn('px-5 py-4', className)} {...props}>
			{children}
		</div>
	);
}

export function CardFooter({ children, className = '', ...props }) {
	return (
		<div className={cn('border-t border-slate-200/70 px-5 py-4 dark:border-slate-800/70', className)} {...props}>
			{children}
		</div>
	);
}

Card.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

CardHeader.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

CardTitle.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
	as: PropTypes.elementType,
};

CardSubtitle.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
	as: PropTypes.elementType,
};

CardBody.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

CardFooter.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};
