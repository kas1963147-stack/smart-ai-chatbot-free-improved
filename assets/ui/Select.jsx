import { Select as MantineSelect, MultiSelect } from '@mantine/core';
import PropTypes from 'prop-types';

const normalizeOptions = ( options ) => {
	if ( ! Array.isArray( options ) ) {
		return [];
	}
	return options.map( ( option ) => ( {
		value: String( option.value ?? option.id ?? option.label ?? '' ),
		label: option.label ?? option.name ?? option.value ?? option.id ?? '',
		...option,
	} ) );
};

export default function Select( {
	label,
	help,
	error,
	options,
	data,
	value,
	onChange,
	multiselect = false,
	...props
} ) {
	const Component = multiselect ? MultiSelect : MantineSelect;
	const resolvedData = data || normalizeOptions( options );

	return (
		<Component
			label={ label }
			description={ help }
			error={ error }
			data={ resolvedData }
			value={ value }
			onChange={ onChange }
			{ ...props }
		/>
	);
}

Select.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	error: PropTypes.node,
	options: PropTypes.array,
	data: PropTypes.array,
	value: PropTypes.oneOfType( [ PropTypes.string, PropTypes.array ] ),
	onChange: PropTypes.func,
	multiselect: PropTypes.bool,
};
