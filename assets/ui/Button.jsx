import { Button as MantineButton } from '@mantine/core';
import PropTypes from 'prop-types';

const VARIANT_MAP = {
	primary: { variant: 'filled', color: 'swc' },
	secondary: { variant: 'light', color: 'gray' },
	tertiary: { variant: 'subtle', color: 'gray' },
	ghost: { variant: 'subtle', color: 'gray' },
	danger: { variant: 'filled', color: 'error' },
	warning: { variant: 'filled', color: 'warning' },
};

const SIZE_MAP = {
	xs: 'xs',
	sm: 'sm',
	md: 'md',
	lg: 'lg',
	xl: 'xl',
};

export default function Button( {
	children,
	variant = 'primary',
	size,
	icon,
	iconPosition = 'left',
	className = '',
	color,
	fullWidth = false,
	isDestructive = false,
	isBusy = false,
	loading,
	useLegacyClasses = true,
	...props
} ) {
	const mapped = VARIANT_MAP[ variant ] || { variant: 'filled', color: 'swc' };
	const resolvedVariant = props.mantineVariant || mapped.variant;
	const resolvedColor = color || ( isDestructive ? 'error' : mapped.color );
	const resolvedSize = size ? SIZE_MAP[ size ] || size : undefined;
	const resolvedLoading = loading ?? isBusy;

	const legacyClasses = useLegacyClasses
		? [
				'swc-btn',
				variant ? `swc-btn--${ variant }` : '',
				size ? `swc-btn--${ size }` : '',
				className,
		  ]
				.filter( Boolean )
				.join( ' ' )
		: className;

	return (
		<MantineButton
			className={ legacyClasses }
			variant={ resolvedVariant }
			color={ resolvedColor }
			size={ resolvedSize }
			fullWidth={ fullWidth }
			loading={ resolvedLoading }
			leftSection={ iconPosition === 'left' ? icon : undefined }
			rightSection={ iconPosition === 'right' ? icon : undefined }
			{ ...props }
		>
			{ children }
		</MantineButton>
	);
}

Button.propTypes = {
	children: PropTypes.node,
	variant: PropTypes.string,
	size: PropTypes.string,
	icon: PropTypes.node,
	iconPosition: PropTypes.oneOf( [ 'left', 'right' ] ),
	className: PropTypes.string,
	color: PropTypes.string,
	fullWidth: PropTypes.bool,
	isDestructive: PropTypes.bool,
	isBusy: PropTypes.bool,
	loading: PropTypes.bool,
	mantineVariant: PropTypes.string,
	useLegacyClasses: PropTypes.bool,
};
