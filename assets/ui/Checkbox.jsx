import { Checkbox as MantineCheckbox } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Checkbox( {
	label,
	help,
	checked = false,
	onChange,
	className = '',
	classNames,
	...props
} ) {
	const handleChange = ( eventOrValue ) => {
		if ( ! onChange ) {
			return;
		}
		if ( typeof eventOrValue === 'boolean' ) {
			onChange( eventOrValue );
			return;
		}
		const nextValue = eventOrValue?.currentTarget?.checked;
		onChange( typeof nextValue === 'boolean' ? nextValue : !! eventOrValue );
	};

	return (
		<MantineCheckbox
			label={ label }
			description={ help }
			checked={ !! checked }
			onChange={ handleChange }
			className={ className }
			classNames={ {
				input: 'swc-checkbox',
				label: 'swc-checkbox-label',
				...( classNames || {} ),
			} }
			{ ...props }
		/>
	);
}

Checkbox.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	checked: PropTypes.bool,
	onChange: PropTypes.func,
	className: PropTypes.string,
	classNames: PropTypes.object,
};
