/**
 * useLocalStorage Hook
 *
 * Safe localStorage operations with quota handling and migration support.
 */
import { useState, useCallback, useEffect } from '@wordpress/element';

/**
 * Hook for safe localStorage state persistence
 * @param {string} key          - Storage key
 * @param {*}      initialValue - Default value if key doesn't exist
 * @param {Object} options      - Configuration options
 * @return {[*, Function, Function]} [value, setValue, removeValue]
 */
export default function useLocalStorage( key, initialValue, options = {} ) {
	const {
		legacyKey = null, // Old key to migrate from
		serialize = JSON.stringify,
		deserialize = JSON.parse,
	} = options;

	// Initialize state with value from localStorage
	const [ storedValue, setStoredValue ] = useState( () => {
		try {
			// Try current key first
			let item = window.localStorage.getItem( key );

			// If not found and legacy key provided, try migration
			if ( item === null && legacyKey ) {
				item = window.localStorage.getItem( legacyKey );
				if ( item !== null ) {
					// Migrate to new key
					window.localStorage.setItem( key, item );
					window.localStorage.removeItem( legacyKey );
				}
			}

			return item !== null ? deserialize( item ) : initialValue;
		} catch ( error ) {
			console.warn( `Error reading localStorage key "${ key }":`, error );
			return initialValue;
		}
	} );

	// Update localStorage when value changes
	const setValue = useCallback(
		( value ) => {
			try {
				// Allow value to be a function for functional updates
				const valueToStore =
					value instanceof Function ? value( storedValue ) : value;

				setStoredValue( valueToStore );

				if ( valueToStore === undefined || valueToStore === null ) {
					window.localStorage.removeItem( key );
				} else {
					window.localStorage.setItem(
						key,
						serialize( valueToStore )
					);
				}
			} catch ( error ) {
				console.warn(
					`Error setting localStorage key "${ key }":`,
					error
				);

				// Handle quota exceeded
				if ( error.name === 'QuotaExceededError' ) {
					clearLegacyData();
					try {
						window.localStorage.setItem( key, serialize( value ) );
					} catch ( retryError ) {
						console.error(
							'Still unable to save to localStorage after cleanup'
						);
					}
				}
			}
		},
		[ key, storedValue, serialize ]
	);

	// Remove value from localStorage
	const removeValue = useCallback( () => {
		try {
			setStoredValue( initialValue );
			window.localStorage.removeItem( key );
		} catch ( error ) {
			console.warn(
				`Error removing localStorage key "${ key }":`,
				error
			);
		}
	}, [ key, initialValue ] );

	return [ storedValue, setValue, removeValue ];
}

/**
 * Clear legacy localStorage data to free up space
 */
function clearLegacyData() {
	const legacyPrefixes = [ 'starter_', 'swc_' ];
	const keysToRemove = [];

	for ( let i = 0; i < window.localStorage.length; i++ ) {
		const key = window.localStorage.key( i );
		if ( legacyPrefixes.some( ( prefix ) => key?.startsWith( prefix ) ) ) {
			keysToRemove.push( key );
		}
	}

	keysToRemove.forEach( ( key ) => {
		try {
			window.localStorage.removeItem( key );
		} catch ( e ) {
			// Ignore removal errors
		}
	} );
}

/**
 * Hook for tracking dirty (unsaved) state
 * @param {*} initialValue - The initial/saved value
 * @param {*} currentValue - The current edited value
 * @return {Object} { isDirty, markClean, confirmNavigation }
 */
export function useDirtyState( initialValue, currentValue ) {
	const [ cleanValue, setCleanValue ] = useState( initialValue );

	const isDirty =
		JSON.stringify( cleanValue ) !== JSON.stringify( currentValue );

	const markClean = useCallback( () => {
		setCleanValue( currentValue );
	}, [ currentValue ] );

	// Warn before navigation if dirty
	useEffect( () => {
		const handleBeforeUnload = ( e ) => {
			if ( isDirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};

		window.addEventListener( 'beforeunload', handleBeforeUnload );
		return () =>
			window.removeEventListener( 'beforeunload', handleBeforeUnload );
	}, [ isDirty ] );

	const confirmNavigation = useCallback(
		( callback ) => {
			if ( isDirty ) {
				const confirmed = window.confirm(
					'You have unsaved changes. Are you sure you want to leave?'
				);
				if ( confirmed ) {
					callback();
				}
			} else {
				callback();
			}
		},
		[ isDirty ]
	);

	return { isDirty, markClean, confirmNavigation };
}
