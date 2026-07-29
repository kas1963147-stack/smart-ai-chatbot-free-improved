import { Card as MantineCard } from '@mantine/core';
import PropTypes from 'prop-types';

export function Card( { children, className = '', ...props } ) {
	const classes = [ 'swc-card', className ].filter( Boolean ).join( ' ' );
	return (
		<MantineCard className={ classes } padding={ 0 } withBorder={ false } { ...props }>
			{ children }
		</MantineCard>
	);
}

export function CardHeader( { children, className = '', ...props } ) {
	const classes = [ 'swc-card__header', className ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<div className={ classes } { ...props }>
			{ children }
		</div>
	);
}

export function CardTitle( {
	children,
	className = '',
	as: Tag = 'h3',
	...props
} ) {
	const classes = [ 'swc-card__title', className ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<Tag className={ classes } { ...props }>
			{ children }
		</Tag>
	);
}

export function CardSubtitle( {
	children,
	className = '',
	as: Tag = 'p',
	...props
} ) {
	const classes = [ 'swc-card__subtitle', className ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<Tag className={ classes } { ...props }>
			{ children }
		</Tag>
	);
}

export function CardBody( { children, className = '', ...props } ) {
	const classes = [ 'swc-card__body', className ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<div className={ classes } { ...props }>
			{ children }
		</div>
	);
}

export function CardFooter( { children, className = '', ...props } ) {
	const classes = [ 'swc-card__footer', className ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<div className={ classes } { ...props }>
			{ children }
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
