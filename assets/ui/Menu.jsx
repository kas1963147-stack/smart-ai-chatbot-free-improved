import { Menu as MantineMenu } from '@mantine/core';
import PropTypes from 'prop-types';

export function Menu( { children, ...props } ) {
	return <MantineMenu { ...props }>{ children }</MantineMenu>;
}

Menu.propTypes = {
	children: PropTypes.node,
};

export const MenuTarget = MantineMenu.Target;
export const MenuDropdown = MantineMenu.Dropdown;
export const MenuItem = MantineMenu.Item;
export const MenuDivider = MantineMenu.Divider;

export default Menu;
