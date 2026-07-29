import { NumberInput } from '@mantine/core';
import PropTypes from 'prop-types';

const coerceValue = ( value ) =>
	value === null || value === undefined ? '' : value;

export default function NumberField( {
	label,
	help,
	error,
	value,
	defaultValue,
	onChange,
	...props
} ) {
	const handleChange = ( nextValue ) => {
		if ( ! onChange ) {
			return;
		}
		onChange( nextValue );
	};

	return (
		<NumberInput
			label={ label }
			description={ help }
			error={ error }
			value={ value !== undefined ? coerceValue( value ) : undefined }
			defaultValue={ defaultValue }
			onChange={ handleChange }
			{ ...props }
		/>
	);
}

NumberField.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	error: PropTypes.node,
	value: PropTypes.oneOfType( [ PropTypes.number, PropTypes.string ] ),
	defaultValue: PropTypes.oneOfType( [ PropTypes.number, PropTypes.string ] ),
	onChange: PropTypes.func,
};
