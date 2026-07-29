import { Tooltip as MantineTooltip } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Tooltip( {
	label,
	children,
	withArrow = true,
	className = '',
	...props
} ) {
	return (
		<MantineTooltip
			label={ label }
			withArrow={ withArrow }
			className={ className }
			{ ...props }
		>
			{ children }
		</MantineTooltip>
	);
}

Tooltip.propTypes = {
	label: PropTypes.node,
	children: PropTypes.node,
	withArrow: PropTypes.bool,
	className: PropTypes.string,
};
