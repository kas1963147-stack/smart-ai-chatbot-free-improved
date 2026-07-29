import { TextInput, Textarea } from '@mantine/core';
import PropTypes from 'prop-types';

const coerceValue = ( value ) => ( value === null || value === undefined ? '' : value );

export default function TextField( {
	label,
	help,
	error,
	value,
	defaultValue,
	onChange,
	multiline = false,
	rows,
	...props
} ) {
	const Component = multiline || rows ? Textarea : TextInput;

	const handleChange = ( eventOrValue ) => {
		if ( ! onChange ) {
			return;
		}
		if ( typeof eventOrValue === 'string' ) {
			onChange( eventOrValue );
			return;
		}
		const nextValue = eventOrValue?.currentTarget?.value;
		onChange( nextValue !== undefined ? nextValue : eventOrValue );
	};

	return (
		<Component
			label={ label }
			description={ help }
			error={ error }
			value={ value !== undefined ? coerceValue( value ) : undefined }
			defaultValue={ defaultValue }
			onChange={ handleChange }
			rows={ rows }
			{ ...props }
		/>
	);
}

TextField.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	error: PropTypes.node,
	value: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	defaultValue: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	onChange: PropTypes.func,
	multiline: PropTypes.bool,
	rows: PropTypes.number,
};
