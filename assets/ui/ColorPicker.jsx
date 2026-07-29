import { ColorPicker as MantineColorPicker } from '@mantine/core';
import PropTypes from 'prop-types';

export default function ColorPicker( { value, onChange, ...props } ) {
	return (
		<MantineColorPicker value={ value } onChange={ onChange } { ...props } />
	);
}

ColorPicker.propTypes = {
	value: PropTypes.string,
	onChange: PropTypes.func,
};
