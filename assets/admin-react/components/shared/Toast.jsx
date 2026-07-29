/**
 * Toast Component
 *
 * Premium animated toast notification system for the admin panel.
 */
import {
	useState,
	useEffect,
	useCallback,
	createContext,
	useContext,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

// Toast Context for global access
const ToastContext = createContext( null );

// Toast types and their configurations
const TOAST_CONFIG = {
	success: { icon: '', className: 'swc-toast--success' },
	error: { icon: '', className: 'swc-toast--error' },
	warning: { icon: '', className: 'swc-toast--warning' },
	info: { icon: 'ℹ', className: 'swc-toast--info' },
};

/**
 * Individual Toast Component
 * @param root0
 * @param root0.id
 * @param root0.type
 * @param root0.title
 * @param root0.message
 * @param root0.duration
 * @param root0.onDismiss
 */
function Toast( {
	id,
	type = 'info',
	title,
	message,
	duration = 5000,
	onDismiss,
} ) {
	const [ isExiting, setIsExiting ] = useState( false );
	const config = TOAST_CONFIG[ type ] || TOAST_CONFIG.info;

	const handleDismiss = useCallback( () => {
		setIsExiting( true );
		setTimeout( () => {
			onDismiss( id );
		}, 300 ); // Match animation duration
	}, [ id, onDismiss ] );

	useEffect( () => {
		if ( duration > 0 ) {
			const timer = setTimeout( () => {
				handleDismiss();
			}, duration );
			return () => clearTimeout( timer );
		}
	}, [ duration, handleDismiss ] );

	return (
		<div
			className={ `swc-toast ${ config.className } ${
				isExiting ? 'swc-toast--exiting' : ''
			}` }
		>
			<div className="swc-toast__icon">{ config.icon }</div>
			<div className="swc-toast__content">
				{ title && <div className="swc-toast__title">{ title }</div> }
				{ message && (
					<div className="swc-toast__message">{ message }</div>
				) }
			</div>
			<button
				className="swc-toast__dismiss"
				onClick={ handleDismiss }
				aria-label={ __( 'Dismiss', 'smart-woo-chatbot' ) }
			>
				
			</button>
			{ duration > 0 && (
				<div
					className="swc-toast__progress"
					style={ {
						animationDuration: `${ duration }ms`,
					} }
				/>
			) }
		</div>
	);
}

/**
 * Toast Container Component
 * @param root0
 * @param root0.toasts
 * @param root0.onDismiss
 */
export function ToastContainer( { toasts, onDismiss } ) {
	if ( ! toasts || toasts.length === 0 ) {
		return null;
	}

	return (
		<div className="swc-toast-container">
			{ toasts.map( ( toast, index ) => (
				<Toast
					key={ toast.id }
					{ ...toast }
					onDismiss={ onDismiss }
					style={ { '--toast-index': index } }
				/>
			) ) }
		</div>
	);
}

/**
 * Toast Provider Component
 * @param root0
 * @param root0.children
 */
export function ToastProvider( { children } ) {
	const [ toasts, setToasts ] = useState( [] );

	const addToast = ( toast ) => {
		const id = Date.now() + Math.random();
		setToasts( ( prev ) => [ ...prev, { id, ...toast } ] );
		return id;
	};

	const removeToast = ( id ) => {
		setToasts( ( prev ) => prev.filter( ( t ) => t.id !== id ) );
	};

	const toast = {
		success: ( message, title ) =>
			addToast( { type: 'success', message, title } ),
		error: ( message, title ) =>
			addToast( { type: 'error', message, title } ),
		warning: ( message, title ) =>
			addToast( { type: 'warning', message, title } ),
		info: ( message, title ) =>
			addToast( { type: 'info', message, title } ),
		custom: ( options ) => addToast( options ),
		dismiss: removeToast,
		dismissAll: () => setToasts( [] ),
	};

	return (
		<ToastContext.Provider value={ toast }>
			{ children }
			<ToastContainer toasts={ toasts } onDismiss={ removeToast } />
		</ToastContext.Provider>
	);
}

/**
 * Hook to use toast notifications
 */
export function useToast() {
	const context = useContext( ToastContext );
	if ( ! context ) {
		throw new Error( 'useToast must be used within a ToastProvider' );
	}
	return context;
}

Toast.propTypes = {
	id: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	type: PropTypes.string,
	title: PropTypes.string,
	message: PropTypes.string,
	duration: PropTypes.number,
	onDismiss: PropTypes.func,
};

ToastContainer.propTypes = {
	toasts: PropTypes.array,
	onDismiss: PropTypes.func,
};

ToastProvider.propTypes = {
	children: PropTypes.node,
};

export default Toast;
