import { Tabs as MantineTabs } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Tabs( {
	tabs,
	activeId,
	onChange,
	fullWidth = false,
	className = '',
} ) {
	const classes = [ 'swc-tabs', className ].filter( Boolean ).join( ' ' );

	return (
		<MantineTabs value={ activeId } onChange={ onChange } className={ classes }>
			<MantineTabs.List grow={ fullWidth }>
				{ tabs.map( ( tab ) => (
					<MantineTabs.Tab
						key={ tab.id }
						value={ tab.id }
						leftSection={ tab.icon }
						disabled={ tab.disabled }
					>
						{ tab.label }
					</MantineTabs.Tab>
				) ) }
			</MantineTabs.List>
		</MantineTabs>
	);
}

Tabs.propTypes = {
	tabs: PropTypes.arrayOf(
		PropTypes.shape( {
			id: PropTypes.string.isRequired,
			label: PropTypes.node.isRequired,
			icon: PropTypes.node,
			disabled: PropTypes.bool,
		} )
	).isRequired,
	activeId: PropTypes.string,
	onChange: PropTypes.func,
	fullWidth: PropTypes.bool,
	className: PropTypes.string,
};
