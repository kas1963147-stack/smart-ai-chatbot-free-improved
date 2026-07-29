import { ActionIcon } from '@mantine/core';
import PropTypes from 'prop-types';

const VARIANT_MAP = {
	primary: { variant: 'filled', color: 'swc' },
	secondary: { variant: 'light', color: 'gray' },
	tertiary: { variant: 'subtle', color: 'gray' },
	ghost: { variant: 'subtle', color: 'gray' },
	danger: { variant: 'filled', color: 'error' },
	warning: { variant: 'filled', color: 'warning' },
};

export default function IconButton( {
	children,
	icon,
	variant = 'ghost',
	size = 'sm',
	className = '',
	color,
	isDestructive = false,
	...props
} ) {
	const mapped = VARIANT_MAP[ variant ] || { variant: 'subtle', color: 'gray' };
	const resolvedColor = color || ( isDestructive ? 'error' : mapped.color );
	const content = icon || children;

	return (
		<ActionIcon
			variant={ mapped.variant }
			color={ resolvedColor }
			size={ size }
			className={ className }
			{ ...props }
		>
			{ content }
		</ActionIcon>
	);
}

IconButton.propTypes = {
	children: PropTypes.node,
	icon: PropTypes.node,
	variant: PropTypes.string,
	size: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	className: PropTypes.string,
	color: PropTypes.string,
	isDestructive: PropTypes.bool,
};
