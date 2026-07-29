import { useState } from '@wordpress/element';
import PropTypes from 'prop-types';
import { cn } from './utils';
import { Card, CardBody, CardHeader } from './card';
import Icon from './icon';
import { chevronDown } from '@wordpress/icons';

export function Panel({ children, className = '', ...props }) {
	return (
		<div className={cn('flex flex-col gap-4', className)} {...props}>
			{children}
		</div>
	);
}

export function PanelBody({ title, children, className = '', initialOpen, ...props }) {
	const defaultOpen = initialOpen !== undefined ? initialOpen : true;
	const [opened, setOpened] = useState(defaultOpen);

	const handleToggle = () => {
		setOpened((prev) => !prev);
	};

	return (
		<Card className={className} {...props}>
			{title && (
				<CardHeader>
					<button
						type="button"
						className="flex w-full items-center justify-between text-left text-sm font-semibold text-slate-700 dark:text-slate-200"
						onClick={handleToggle}
						aria-expanded={opened}
					>
						<span>{title}</span>
						<span className={cn('transition', opened ? 'rotate-180' : '')}>
							<Icon icon={chevronDown} size={16} />
						</span>
					</button>
				</CardHeader>
			)}
			{!title || opened ? <CardBody>{children}</CardBody> : null}
		</Card>
	);
}

export function PanelRow({ children, className = '', ...props }) {
	return (
		<div className={cn('space-y-1', className)} {...props}>
			{children}
		</div>
	);
}

Panel.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};

PanelBody.propTypes = {
	title: PropTypes.node,
	children: PropTypes.node,
	className: PropTypes.string,
	initialOpen: PropTypes.bool,
};

PanelRow.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};
