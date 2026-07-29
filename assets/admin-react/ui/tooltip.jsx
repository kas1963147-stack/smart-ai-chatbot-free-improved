import PropTypes from 'prop-types';

export default function Tooltip({ label, children }) {
	if (!label) {
		return children;
	}
	return (
		<span title={label} className="cursor-help">
			{children}
		</span>
	);
}

Tooltip.propTypes = {
	label: PropTypes.node,
	children: PropTypes.node,
};
