import { Alert } from '@mantine/core';
import PropTypes from 'prop-types';

const STATUS_COLOR = {
	success: 'success',
	error: 'error',
	warning: 'warning',
	info: 'info',
};

const STATUS_CLASS = {
	success: 'swc-notice--success',
	error: 'swc-notice--error',
	warning: 'swc-notice--warning',
	info: 'swc-notice--info',
};

export default function Notice( {
	status = 'info',
	title,
	children,
	isDismissible = false,
	onRemove,
	className = '',
	...props
} ) {
	const resolvedStatus = status || 'info';
	const classes = [
		'swc-notice',
		STATUS_CLASS[ resolvedStatus ],
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	const withCloseButton = isDismissible || typeof onRemove === 'function';

	return (
		<Alert
			color={ STATUS_COLOR[ resolvedStatus ] || 'info' }
			title={ title }
			withCloseButton={ withCloseButton }
			onClose={ onRemove }
			className={ classes }
			classNames={ {
				icon: 'swc-notice__icon',
				body: 'swc-notice__content',
				title: 'swc-notice__title',
				closeButton: 'swc-notice__dismiss',
			} }
			{ ...props }
		>
			{ children }
		</Alert>
	);
}

Notice.propTypes = {
	status: PropTypes.string,
	title: PropTypes.node,
	children: PropTypes.node,
	isDismissible: PropTypes.bool,
	onRemove: PropTypes.func,
	className: PropTypes.string,
};
