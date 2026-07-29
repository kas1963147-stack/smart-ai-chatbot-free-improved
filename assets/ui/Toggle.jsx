import { Switch } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Toggle( {
	label,
	help,
	checked = false,
	onChange,
	className = '',
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
		<Switch
			label={ label }
			description={ help }
			checked={ !! checked }
			onChange={ handleChange }
			className={ className }
			{ ...props }
		/>
	);
}

Toggle.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	checked: PropTypes.bool,
	onChange: PropTypes.func,
	className: PropTypes.string,
};
