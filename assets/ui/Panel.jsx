import { useState } from '@wordpress/element';
import { Collapse } from '@mantine/core';
import { chevronDown } from '@wordpress/icons';
import PropTypes from 'prop-types';

import { Card, CardBody, CardHeader } from './Card';
import Icon from './Icon';

export function Panel( { children, className = '', ...props } ) {
	const classes = [ 'swc-flex', 'swc-flex-col', 'swc-gap-4', className ]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ classes } { ...props }>
			{ children }
		</div>
	);
}

export function PanelBody( {
	title,
	children,
	className = '',
	initialOpen,
	...props
} ) {
	const classes = [ className ].filter( Boolean ).join( ' ' );
	const defaultOpen = initialOpen !== undefined ? initialOpen : true;
	const [ opened, setOpened ] = useState( defaultOpen );

	const handleToggle = () => {
		setOpened( ( prev ) => ! prev );
	};

	return (
		<Card
			className={ classes }
			{ ...props }
		>
			{ title && (
				<CardHeader className="swc-panel__header">
					<button
						type="button"
						className="swc-panel__toggle"
						onClick={ handleToggle }
						aria-expanded={ opened }
					>
						<span className="swc-panel__title">{ title }</span>
						<span className="swc-panel__toggle-icon">
							<Icon icon={ chevronDown } size={ 16 } />
						</span>
					</button>
				</CardHeader>
			) }
			{ title ? (
				<Collapse in={ opened }>
					<CardBody>{ children }</CardBody>
				</Collapse>
			) : (
				<CardBody>{ children }</CardBody>
			) }
		</Card>
	);
}

export function PanelRow( { children, className = '', ...props } ) {
	const classes = [ 'swc-form-group', className ]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ classes } { ...props }>
			{ children }
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
