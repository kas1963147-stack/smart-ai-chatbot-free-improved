import { Input, Slider } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Range( {
	label,
	help,
	value,
	onChange,
	min,
	max,
	step,
	className = '',
	...props
} ) {
	return (
		<Input.Wrapper label={ label } description={ help } className={ className }>
			<Slider
				value={ value }
				onChange={ onChange }
				min={ min }
				max={ max }
				step={ step }
				{ ...props }
			/>
		</Input.Wrapper>
	);
}

Range.propTypes = {
	label: PropTypes.node,
	help: PropTypes.node,
	value: PropTypes.number,
	onChange: PropTypes.func,
	min: PropTypes.number,
	max: PropTypes.number,
	step: PropTypes.number,
	className: PropTypes.string,
};
