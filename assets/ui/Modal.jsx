import { Modal as MantineModal } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Modal( {
	isOpen,
	onClose,
	title,
	children,
	footer,
	size = 'md',
	className = '',
	...props
} ) {
	return (
		<MantineModal
			opened={ isOpen }
			onClose={ onClose }
			title={ title }
			size={ size }
			className={ className }
			{ ...props }
		>
			<div className="swc-modal__body">{ children }</div>
			{ footer && <div className="swc-modal__footer">{ footer }</div> }
		</MantineModal>
	);
}

Modal.propTypes = {
	isOpen: PropTypes.bool,
	onClose: PropTypes.func.isRequired,
	title: PropTypes.node,
	children: PropTypes.node,
	footer: PropTypes.node,
	size: PropTypes.string,
	className: PropTypes.string,
};
