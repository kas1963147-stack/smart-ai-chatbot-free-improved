import PropTypes from 'prop-types';

export default function UiProvider({ children }) {
	return children;
}

UiProvider.propTypes = {
	children: PropTypes.node,
};
