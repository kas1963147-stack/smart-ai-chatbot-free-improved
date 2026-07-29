import PropTypes from 'prop-types';
import { cn } from './utils';

export function Menu({ children, className = '', ...props }) {
	return (
		<div className={cn('relative inline-flex', className)} {...props}>
			{children}
		</div>
	);
}

export function MenuTarget({ children }) {
	return <div>{children}</div>;
}

export function MenuDropdown({ children, className = '', ...props }) {
	return (
		<div className={cn('absolute right-0 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-800 dark:bg-slate-900', className)} {...props}>
			{children}
		</div>
	);
}

export function MenuItem({ children, onClick, className = '', ...props }) {
	return (
		<button
			type="button"
			onClick={onClick}
			className={cn('flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800', className)}
			{...props}
		>
			{children}
		</button>
	);
}

export function MenuDivider({ className = '', ...props }) {
	return <div className={cn('my-1 h-px bg-slate-200 dark:bg-slate-800', className)} {...props} />;
}

Menu.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

MenuTarget.propTypes = {
	children: PropTypes.node,
};

MenuDropdown.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

MenuItem.propTypes = {
	children: PropTypes.node,
	onClick: PropTypes.func,
	className: PropTypes.string,
};

MenuDivider.propTypes = {
	className: PropTypes.string,
};

export default Menu;
