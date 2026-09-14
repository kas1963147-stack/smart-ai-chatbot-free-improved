/**
 * ErrorBoundary Component
 *
 * Catches React errors and displays recovery UI to prevent entire admin panel crash.
 */
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '../ui';

export default class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = {
			hasError: false,
			error: null,
			errorInfo: null,
		};
	}

	static getDerivedStateFromError( error ) {
		return { hasError: true, error };
	}

	componentDidCatch( error, errorInfo ) {
		this.setState( {
			error,
			errorInfo,
		} );

		// Log to console for debugging
		console.error( 'ErrorBoundary caught an error:', error, errorInfo );

		// Optionally send to error tracking service
		if ( window.swcChatbot?.errorReportUrl ) {
			this.reportError( error, errorInfo );
		}

		// LOG TO A FILE FOR AGENT
		try {
			fetch('/log-error.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ error: String(error), stack: errorInfo?.componentStack })
			});
		} catch (e) {}
	}

	reportError( error, errorInfo ) {
		try {
			fetch( window.swcChatbot.errorReportUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.swcChatbot.nonce,
				},
				body: JSON.stringify( {
					error: error.toString(),
					stack: error.stack,
					componentStack: errorInfo?.componentStack,
					url: window.location.href,
					userAgent: navigator.userAgent,
				} ),
			} );
		} catch ( e ) {
			// Silently fail error reporting
		}
	}

	handleReload = () => {
		window.location.reload();
	};

	handleReset = () => {
		this.setState( { hasError: false, error: null, errorInfo: null } );
	};

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="swc-error-boundary">
					<div className="swc-error-boundary__content">
						<div className="swc-error-boundary__icon">ï¸</div>
						<h2 className="swc-error-boundary__title">
							{ __(
								'Something went wrong',
								'agentflow-ai'
							) }
						</h2>
						<p className="swc-error-boundary__message">
							{ __(
								'An error occurred in the admin panel. You can try reloading the page or reset this section.',
								'agentflow-ai'
							) }
						</p>

						{ this.state.error && (
								<details className="swc-error-boundary__details">
									<summary>
										{ __(
											'Error Details',
											'agentflow-ai'
										) }
									</summary>
									<pre className="swc-code-block swc-code-block--error">
										{ this.state.error.toString() }
										{ this.state.errorInfo?.componentStack }
									</pre>
								</details>
							) }

						<div className="swc-error-boundary__actions">
							<Button
								variant="primary"
								onClick={ this.handleReload }
							>
								{ __( 'Reload Page', 'agentflow-ai' ) }
							</Button>
							<Button
								variant="secondary"
								onClick={ this.handleReset }
							>
								{ __( 'Try Again', 'agentflow-ai' ) }
							</Button>
						</div>
					</div>
				</div>
			);
		}

		return this.props.children;
	}
}
