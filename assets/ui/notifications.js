import { notifications } from '@mantine/notifications';

const normalizeOptions = ( options = {} ) => ( {
	autoClose: 4000,
	withCloseButton: true,
	...options,
} );

export const notify = {
	success: ( message, options ) =>
		notifications.show( {
			message,
			color: 'success',
			...normalizeOptions( options ),
		} ),
	error: ( message, options ) =>
		notifications.show( {
			message,
			color: 'error',
			...normalizeOptions( options ),
		} ),
	warning: ( message, options ) =>
		notifications.show( {
			message,
			color: 'warning',
			...normalizeOptions( options ),
		} ),
	info: ( message, options ) =>
		notifications.show( {
			message,
			color: 'info',
			...normalizeOptions( options ),
		} ),
};
