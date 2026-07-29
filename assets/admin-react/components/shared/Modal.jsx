/**
 * Premium Modal Component
 *
 * Reusable modal with glassmorphism styling for the admin interface.
 *
 * @version 1.0.0
 * @package
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Button, Modal as UiModal } from '../ui';

/**
 * Modal Component
 *
 * @param {Object}          props
 * @param {boolean}         props.isOpen          - Whether modal is visible
 * @param {Function}        props.onClose         - Close handler
 * @param {string}          props.title           - Modal title
 * @param {string}          props.subtitle        - Optional subtitle
 * @param {React.ReactNode} props.children        - Modal content
 * @param {React.ReactNode} props.footer          - Optional footer content
 * @param {string}          props.size            - Modal size: 'sm' | 'md' | 'lg' | 'xl' | 'full'
 * @param {boolean}         props.closeOnOverlay  - Close when clicking overlay
 * @param {boolean}         props.showCloseButton - Show X button
 */
export default function Modal( {
	isOpen,
	onClose,
	title,
	subtitle,
	children,
	footer,
	size = 'md',
	closeOnOverlay = true,
	showCloseButton = true,
} ) {
	const modalTitle =
		title || subtitle ? (
			<div className="swc-modal__header-content">
				{ title && <h2 className="swc-modal__title">{ title }</h2> }
				{ subtitle && (
					<p className="swc-modal__subtitle">{ subtitle }</p>
				) }
			</div>
		) : null;

	return (
		<UiModal
			isOpen={ isOpen }
			onClose={ onClose }
			title={ modalTitle }
			size={ size }
			closeOnClickOutside={ closeOnOverlay }
			withCloseButton={ showCloseButton }
			closeButtonProps={ {
				'aria-label': __('Close', 'smart-ai-chatbot'),
			} }
			overlayProps={ {
				className: 'swc-modal-overlay swc-modal-overlay--open',
			} }
			classNames={ {
				content: `swc-modal swc-modal--${ size }`,
				header: 'swc-modal__header',
				close: 'swc-modal__close',
			} }
			footer={ footer }
		>
			{ children }
		</UiModal>
	);
}

/**
 * Confirm Modal
 *
 * Quick confirmation dialog.
 * @param root0
 * @param root0.isOpen
 * @param root0.onClose
 * @param root0.onConfirm
 * @param root0.title
 * @param root0.message
 * @param root0.confirmText
 * @param root0.cancelText
 * @param root0.variant
 */
export function ConfirmModal( {
	isOpen,
	onClose,
	onConfirm,
	title = __('Confirm Action', 'smart-ai-chatbot'),
	message,
	confirmText = __('Confirm', 'smart-ai-chatbot'),
	cancelText = __('Cancel', 'smart-ai-chatbot'),
	variant = 'danger', // 'danger' | 'warning' | 'primary'
} ) {
	return (
		<Modal
			isOpen={ isOpen }
			onClose={ onClose }
			title={ title }
			size="sm"
			footer={
				<div className="swc-flex swc-justify-end swc-gap-3">
					<Button variant="secondary" onClick={ onClose }>
						{ cancelText }
					</Button>
					<Button
						variant={ variant }
						onClick={ () => {
							onConfirm();
							onClose();
						} }
					>
						{ confirmText }
					</Button>
				</div>
			}
		>
			<p className="swc-text-sm swc-text-gray-600 swc-m-0">{ message }</p>
		</Modal>
	);
}

Modal.propTypes = {
	isOpen: PropTypes.bool,
	onClose: PropTypes.func.isRequired,
	title: PropTypes.string,
	subtitle: PropTypes.string,
	children: PropTypes.node,
	footer: PropTypes.node,
	size: PropTypes.string,
	closeOnOverlay: PropTypes.bool,
	showCloseButton: PropTypes.bool,
};

ConfirmModal.propTypes = {
	isOpen: PropTypes.bool,
	onClose: PropTypes.func.isRequired,
	onConfirm: PropTypes.func.isRequired,
	title: PropTypes.string,
	message: PropTypes.string,
	confirmText: PropTypes.string,
	cancelText: PropTypes.string,
	variant: PropTypes.string,
};
