/**
 * Tool Execution Card
 *
 * Displays AI tool calls with parameters and results.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

/**
 * ToolExecutionCard Component
 * @param root0
 * @param root0.toolName
 * @param root0.params
 * @param root0.result
 * @param root0.status
 */
export default function ToolExecutionCard( {
	toolName,
	params,
	result,
	status,
} ) {
	const [ expanded, setExpanded ] = useState( false );

	const statusIcon =
		{
			pending: '',
			running: '',
			completed: '',
			error: '',
		}[ status ] || '';

	const statusClass =
		{
			pending: 'swc-tool-card--pending',
			running: 'swc-tool-card--running',
			completed: 'swc-tool-card--completed',
			error: 'swc-tool-card--error',
		}[ status ] || '';

	// Format tool name for display
	const displayName = toolName
		.replace( /_/g, ' ' )
		.replace( /\b\w/g, ( c ) => c.toUpperCase() );

	return (
		<div className={ `swc-tool-card ${ statusClass }` }>
			<div
				className="swc-tool-card__header"
				onClick={ () => setExpanded( ! expanded ) }
				role="button"
				tabIndex={ 0 }
				onKeyDown={ ( e ) =>
					e.key === 'Enter' && setExpanded( ! expanded )
				}
			>
				<span className="swc-tool-card__icon">{ statusIcon }</span>
				<span className="swc-tool-card__name">{ displayName }</span>
				<span className="swc-tool-card__expand">
					{ expanded ? '' : '' }
				</span>
			</div>

			{ expanded && (
				<div className="swc-tool-card__body">
					{ params && Object.keys( params ).length > 0 && (
						<div className="swc-tool-card__section">
							<h5>{ __( 'Parameters', 'agentflow-ai' ) }</h5>
							<pre className="swc-tool-card__code">
								{ typeof params === 'string'
									? params
									: JSON.stringify( params, null, 2 ) }
							</pre>
						</div>
					) }

					{ result && (
						<div className="swc-tool-card__section">
							<h5>{ __( 'Result', 'agentflow-ai' ) }</h5>
							<pre className="swc-tool-card__code swc-tool-card__code--result">
								{ typeof result === 'string'
									? result
									: JSON.stringify( result, null, 2 ) }
							</pre>
						</div>
					) }
				</div>
			) }
		</div>
	);
}

ToolExecutionCard.propTypes = {
	toolName: PropTypes.string.isRequired,
	params: PropTypes.oneOfType( [ PropTypes.object, PropTypes.string ] ),
	result: PropTypes.oneOfType( [
		PropTypes.object,
		PropTypes.string,
		PropTypes.any,
	] ),
	status: PropTypes.oneOf( [ 'pending', 'running', 'completed', 'error' ] ),
};

ToolExecutionCard.defaultProps = {
	params: {},
	result: null,
	status: 'completed',
};
