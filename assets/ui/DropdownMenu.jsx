import PropTypes from 'prop-types';

import { Menu } from '@mantine/core';

import Button from './Button';
import Icon from './Icon';

const renderIcon = ( icon, size = 16 ) =>
	icon ? <Icon icon={ icon } size={ size } /> : null;

export default function DropdownMenu( {
	controls = [],
	icon,
	label,
	className = '',
	size = 'sm',
	variant = 'tertiary',
	...props
} ) {
	const filteredControls = controls.filter( Boolean );
	const buttonLabel = label || 'Open menu';

	return (
		<Menu position="bottom-end" withinPortal={ false } { ...props }>
			<Menu.Target>
				<Button
					variant={ variant }
					size={ size }
					icon={ renderIcon( icon, 18 ) }
					aria-label={ buttonLabel }
					title={ buttonLabel }
					className={ className }
				/>
			</Menu.Target>
			<Menu.Dropdown>
				{ filteredControls.map( ( control, index ) => (
					<Menu.Item
						key={ control.title || index }
						leftSection={ renderIcon( control.icon, 14 ) }
						color={ control.isDestructive ? 'red' : undefined }
						disabled={ control.isDisabled }
						onClick={ control.onClick }
					>
						{ control.title }
					</Menu.Item>
				) ) }
			</Menu.Dropdown>
		</Menu>
	);
}

DropdownMenu.propTypes = {
	controls: PropTypes.arrayOf(
		PropTypes.shape( {
			title: PropTypes.node,
			icon: PropTypes.any,
			onClick: PropTypes.func,
			isDestructive: PropTypes.bool,
			isDisabled: PropTypes.bool,
		} )
	),
	icon: PropTypes.any,
	label: PropTypes.string,
	className: PropTypes.string,
	size: PropTypes.string,
	variant: PropTypes.string,
};
