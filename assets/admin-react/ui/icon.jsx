import { Icon as WpIcon } from '@wordpress/icons';
import PropTypes from 'prop-types';

export default function Icon({ icon, size, ...props }) {
	return <WpIcon icon={icon} size={size} {...props} />;
}

Icon.propTypes = {
	icon: PropTypes.any,
	size: PropTypes.number,
};
