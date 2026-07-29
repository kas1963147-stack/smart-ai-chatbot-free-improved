import { Drawer as MantineDrawer } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Drawer( {
	isOpen,
	onClose,
	title,
	position = 'right',
	size = 'md',
	children,
	className = '',
	...props
} ) {
	return (
		<MantineDrawer
			opened={ isOpen }
			onClose={ onClose }
			title={ title }
			position={ position }
			size={ size }
			className={ className }
			{ ...props }
		>
			{ children }
		</MantineDrawer>
	);
}

Drawer.propTypes = {
	isOpen: PropTypes.bool,
	onClose: PropTypes.func.isRequired,
	title: PropTypes.node,
	position: PropTypes.string,
	size: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	children: PropTypes.node,
	className: PropTypes.string,
};
