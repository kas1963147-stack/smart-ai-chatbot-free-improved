import { Loader as MantineLoader } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Loader( {
	size = 'sm',
	color = 'swc',
	className = '',
	...props
} ) {
	const classes = [ 'swc-spinner', className ].filter( Boolean ).join( ' ' );

	return (
		<MantineLoader
			size={ size }
			color={ color }
			className={ classes }
			{ ...props }
		/>
	);
}

Loader.propTypes = {
	size: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	color: PropTypes.string,
	className: PropTypes.string,
};
