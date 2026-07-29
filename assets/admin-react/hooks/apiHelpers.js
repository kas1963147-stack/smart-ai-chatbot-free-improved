/**
 * API Response Helpers
 *
 * Utilities to normalize API responses across different backend formats.
 */

/**
 * Unwrap API response that may be wrapped in a success envelope
 * @param {Object} response - API response
 * @param {string} key      - Expected data key (e.g., 'agents', 'settings')
 * @return {*} Unwrapped data
 */
export function unwrapResponse( response, key ) {
	// Handle wrapped responses: { success: true, data: { [key]: [...] } }
	if ( response?.success && response?.data ) {
		if ( key && response.data[ key ] !== undefined ) {
			return response.data[ key ];
		}
		return response.data;
	}

	// Handle direct key access: { [key]: [...] }
	if ( key && response?.[ key ] !== undefined ) {
		return response[ key ];
	}

	// Return as-is for arrays or direct data
	return response;
}

/**
 * Normalize agent object to consistent format
 * @param {Object} agent - Agent data from API
 * @return {Object} Normalized agent
 */
export function normalizeAgent( agent ) {
	if ( ! agent ) {
		return null;
	}

	return {
		...agent,
		// Avatar: support multiple formats (empty string falls back to letter-based icon)
		avatar: agent.avatar_emoji || agent.avatar || '',
		// Welcome message: support both cases
		welcomeMessage: agent.welcome_message || agent.welcomeMessage || '',
		// Skills: normalize to ID array
		skills:
			agent.skills?.map( ( s ) =>
				typeof s === 'object' ? s.id : s
			) || [],
		// Quick actions: ensure proper format
		quickActions: ( agent.quick_actions || agent.quickActions || [] ).map(
			( action ) => ( {
				id: action.id || action.action,
				label: action.label || action.name,
				action: action.action || action.id,
			} )
		),
	};
}

/**
 * Normalize session response
 * @param {Object} response - Session API response
 * @return {Object} Normalized session data
 */
export function normalizeSession( response ) {
	return {
		sessionId: response.session?.id || response.session_id || response.id,
		messages: response.messages || response.session?.messages || [],
		created: response.created_at || response.session?.created_at,
	};
}

/**
 * Normalize streaming chunk data
 * @param {Object} data - SSE data chunk
 * @return {Object} Normalized chunk
 */
export function normalizeStreamChunk( data ) {
	return {
		text: data.text || data.content || data.delta?.content || '',
		done: data.done || data.finished || false,
		error: data.error || null,
		message: data.message || data.error_message || null,
	};
}

/**
 * Normalize product data for display
 * @param {Object} product - Product from API
 * @return {Object} Normalized product
 */
export function normalizeProduct( product ) {
	return {
		id: product.id,
		name: product.name || product.title || '',
		price: product.price_html || product.price || '',
		image:
			product.image ||
			product.thumbnail ||
			product.images?.[ 0 ]?.src ||
			'',
		url: product.permalink || product.url || product.link || '',
		inStock: product.in_stock ?? product.is_in_stock ?? true,
		rating: product.average_rating || product.rating || 0,
	};
}

/**
 * Safe localStorage operations with quota handling
 */
export const safeStorage = {
	get( key, defaultValue = null ) {
		try {
			const item = localStorage.getItem( key );
			return item ? JSON.parse( item ) : defaultValue;
		} catch ( e ) {
			console.warn( `Failed to get ${ key } from localStorage:`, e );
			return defaultValue;
		}
	},

	set( key, value ) {
		try {
			localStorage.setItem( key, JSON.stringify( value ) );
			return true;
		} catch ( e ) {
			console.warn( `Failed to set ${ key } in localStorage:`, e );
			// If quota exceeded, try to clear old data
			if ( e.name === 'QuotaExceededError' ) {
				this.clearOldData();
				try {
					localStorage.setItem( key, JSON.stringify( value ) );
					return true;
				} catch ( retryError ) {
					return false;
				}
			}
			return false;
		}
	},

	remove( key ) {
		try {
			localStorage.removeItem( key );
		} catch ( e ) {
			console.warn( `Failed to remove ${ key } from localStorage:`, e );
		}
	},

	clearOldData() {
		// Clear any old starter_ prefixed keys
		const keysToRemove = [];
		for ( let i = 0; i < localStorage.length; i++ ) {
			const key = localStorage.key( i );
			if ( key?.startsWith( 'starter_' ) ) {
				keysToRemove.push( key );
			}
		}
		keysToRemove.forEach( ( key ) => localStorage.removeItem( key ) );
	},
};
