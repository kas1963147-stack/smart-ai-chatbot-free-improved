/**
 * ToolConfigField Component
 *
 * Renders the appropriate input control based on field type.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Button, Select, TextField, Toggle } from './ui';

export default function ToolConfigField( {
	field,
	value,
	onChange,
	connections = [],
} ) {
	const [ showPassword, setShowPassword ] = useState( false );

	const handleChange = ( newValue ) => {
		onChange( field.id, newValue );
	};

	// Render based on field type
	switch ( field.type ) {
		case 'text':
			return (
				<TextField
					label={ field.label }
					help={ field.description }
					value={ value || field.default || '' }
					onChange={ handleChange }
					placeholder={ field.placeholder }
					required={ field.required }
				/>
			);

		case 'password':
			return (
				<div className="swc-password-field">
					<TextField
						label={ field.label }
						help={ field.description }
						type={ showPassword ? 'text' : 'password' }
						value={ value || '' }
						onChange={ handleChange }
						placeholder={ field.placeholder }
						required={ field.required }
					/>
					<Button
						variant="tertiary"
						onClick={ () => setShowPassword( ! showPassword ) }
						className="swc-password-toggle"
					>
						{ showPassword ? '' : '️' }
					</Button>
				</div>
			);

		case 'textarea':
			return (
				<TextField
					label={ field.label }
					help={ field.description }
					value={ value || field.default || '' }
					onChange={ handleChange }
					placeholder={ field.placeholder }
					rows={ field.rows || 4 }
					multiline
					required={ field.required }
				/>
			);

		case 'toggle':
			return (
				<Toggle
					label={ field.label }
					help={ field.description }
					checked={ value ?? field.default ?? false }
					onChange={ handleChange }
				/>
			);

		case 'select':
			const choices = Object.entries( field.choices || {} ).map(
				( [ val, label ] ) => ( {
					value: val,
					label,
				} )
			);

			return (
				<Select
					label={ field.label }
					help={ field.description }
					value={ value || field.default || '' }
					options={ [
						{
							value: '',
							label: __( 'Select…', 'agentflow-ai' ),
						},
						...choices,
					] }
					onChange={ handleChange }
					required={ field.required }
				/>
			);

		case 'number':
			return (
				<TextField
					type="number"
					label={ field.label }
					help={ field.description }
					value={ value ?? field.default ?? 0 }
					onChange={ ( val ) => handleChange( Number( val ) ) }
					min={ field.min }
					max={ field.max }
					step={ field.step || 1 }
					required={ field.required }
				/>
			);

		case 'url':
			return (
				<TextField
					type="url"
					label={ field.label }
					help={ field.description }
					value={ value || field.default || '' }
					onChange={ handleChange }
					placeholder={ field.placeholder || 'https://' }
					required={ field.required }
				/>
			);

		case 'connection':
			// Filter connections by integration if specified
			let filteredConnections = connections;
			if ( field.integration_filter ) {
				filteredConnections = connections.filter(
					( c ) => c.integration_id === field.integration_filter
				);
			}

			const connectionOptions = filteredConnections.map( ( conn ) => ( {
				value: String( conn.id ),
				label: `${ conn.connection_name } (${ conn.integration_id })`,
			} ) );

			return (
				<div className="swc-connection-field">
					<Select
						label={ field.label }
						help={ field.description }
						value={ value || '' }
						options={ [
							{
								value: '',
								label: __(
									'Select connection…',
									'agentflow-ai'
								),
							},
							...connectionOptions,
						] }
						onChange={ handleChange }
						required={ field.required }
					/>
					{ connectionOptions.length === 0 && (
						<p className="swc-help swc-help--warning">
							{ __(
								'No connections available. ',
								'agentflow-ai'
							) }
							<a
								href="#"
								onClick={ ( e ) => {
									e.preventDefault(); /* TODO: open connection modal */
								} }
							>
								{ __( 'Create one', 'agentflow-ai' ) }
							</a>
						</p>
					) }
				</div>
			);

		case 'oauth':
			const isConnected = !! value;
			return (
				<div className="swc-oauth-field">
					<label className="components-base-control__label">
						{ field.label }
					</label>
					{ field.description && (
						<p className="components-base-control__help">
							{ field.description }
						</p>
					) }
					<div className="swc-oauth-status">
						{ isConnected ? (
							<>
								<span className="swc-status swc-status--success">
									{ ' ' }
									{ __( 'Connected', 'agentflow-ai' ) }
								</span>
								<Button
									variant="secondary"
									isDestructive
									onClick={ () => handleChange( null ) }
								>
									{ __( 'Disconnect', 'agentflow-ai' ) }
								</Button>
							</>
						) : (
							<>
								<span className="swc-status swc-status--warning">
									️{ ' ' }
									{ __(
										'Not connected',
										'agentflow-ai'
									) }
								</span>
								<Button
									variant="primary"
									onClick={ () => {
										// TODO: Implement OAuth flow trigger
										alert(
											__(
												'OAuth flow not yet implemented for ' +
													field.provider,
												'agentflow-ai'
											)
										);
									} }
								>
									{ __( 'Connect', 'agentflow-ai' ) }{ ' ' }
									{ field.provider }
								</Button>
							</>
						) }
					</div>
				</div>
			);

		default:
			return (
				<TextField
					label={ field.label }
					help={ `Unknown field type: ${ field.type }` }
					value={ value || '' }
					onChange={ handleChange }
				/>
			);
	}
}

ToolConfigField.propTypes = {
	field: PropTypes.shape( {
		id: PropTypes.string.isRequired,
		type: PropTypes.string.isRequired,
		label: PropTypes.string,
		description: PropTypes.string,
		default: PropTypes.any,
		placeholder: PropTypes.string,
		required: PropTypes.bool,
		rows: PropTypes.number,
		min: PropTypes.number,
		max: PropTypes.number,
		step: PropTypes.number,
		choices: PropTypes.object,
		provider: PropTypes.string,
		integration_filter: PropTypes.string,
	} ).isRequired,
	value: PropTypes.any,
	onChange: PropTypes.func.isRequired,
	connections: PropTypes.array,
};
