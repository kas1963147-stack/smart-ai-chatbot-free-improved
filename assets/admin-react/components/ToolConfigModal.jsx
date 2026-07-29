/**
 * ToolConfigModal Component
 *
 * Modal dialog for configuring tool-specific settings.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

import ToolConfigField from './ToolConfigField';
import { Button, Modal, Notice } from './ui';

export default function ToolConfigModal( {
	tool,
	schema,
	currentConfig,
	connections,
	onSave,
	onCancel,
} ) {
	const [ config, setConfig ] = useState( {} );
	const [ errors, setErrors ] = useState( [] );
	const [ saving, setSaving ] = useState( false );

	// Initialize config with current values
	useEffect( () => {
		if ( currentConfig ) {
			setConfig( currentConfig );
		} else {
			// Set defaults from schema
			const defaults = {};
			schema.forEach( ( field ) => {
				if ( field.default !== undefined ) {
					defaults[ field.id ] = field.default;
				}
			} );
			setConfig( defaults );
		}
	}, [ currentConfig, schema ] );

	// Handle field change
	const handleFieldChange = ( fieldId, value ) => {
		setConfig( ( prev ) => ( {
			...prev,
			[ fieldId ]: value,
		} ) );
		// Clear errors when field is edited
		setErrors( [] );
	};

	// Validate before save
	const validate = () => {
		const validationErrors = [];

		schema.forEach( ( field ) => {
			if ( field.required && ! config[ field.id ] ) {
				validationErrors.push( `${ field.label } is required` );
			}

			// URL validation
			if ( field.type === 'url' && config[ field.id ] ) {
				try {
					new URL( config[ field.id ] );
				} catch {
					validationErrors.push(
						`${ field.label } must be a valid URL`
					);
				}
			}
		} );

		return validationErrors;
	};

	// Handle save
	const handleSave = async () => {
		const validationErrors = validate();

		if ( validationErrors.length > 0 ) {
			setErrors( validationErrors );
			return;
		}

		setSaving( true );
		try {
			await onSave( tool.id, config );
		} catch ( err ) {
			setErrors( [ err.message || 'Failed to save configuration' ] );
		} finally {
			setSaving( false );
		}
	};

	// Check if any required fields are missing
	const hasRequiredMissing = schema.some(
		( field ) => field.required && ! config[ field.id ]
	);

	if ( ! tool || ! schema || schema.length === 0 ) {
		return null;
	}

	return (
		<Modal
			title={
				<>
					️ { __( 'Configure', 'smart-woo-chatbot' ) }:{ ' ' }
					{ tool.name || tool.id }
				</>
			}
			isOpen
			onClose={ onCancel }
			className="swc-config-modal"
			size="md"
		>
			<div className="swc-config-modal__content">
				{ /* Description */ }
				{ tool.description && (
					<p className="swc-config-modal__desc">
						{ tool.description }
					</p>
				) }

				{ /* Errors */ }
				{ errors.length > 0 && (
					<Notice status="error" isDismissible={ false }>
						<ul>
							{ errors.map( ( err, i ) => (
								<li key={ i }>{ err }</li>
							) ) }
						</ul>
					</Notice>
				) }

				{ /* Configuration Fields */ }
				<div className="swc-config-fields">
					{ schema.map( ( field ) => (
						<div key={ field.id } className="swc-config-field">
							<ToolConfigField
								field={ field }
								value={ config[ field.id ] }
								onChange={ handleFieldChange }
								connections={ connections }
							/>
							{ field.required && (
								<span className="swc-required-badge">
									{ __( 'Required', 'smart-woo-chatbot' ) }
								</span>
							) }
						</div>
					) ) }
				</div>

				{ /* Warning for missing required */ }
				{ hasRequiredMissing && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Some required fields are missing. The tool will not be available until configured.',
							'smart-woo-chatbot'
						) }
					</Notice>
				) }
			</div>

			{ /* Actions */ }
			<div className="swc-config-modal__actions">
				<Button
					variant="secondary"
					onClick={ onCancel }
					disabled={ saving }
				>
					{ __( 'Cancel', 'smart-woo-chatbot' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleSave }
					loading={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'smart-woo-chatbot' )
						: __( 'Save Configuration', 'smart-woo-chatbot' ) }
				</Button>
			</div>
		</Modal>
	);
}

ToolConfigModal.propTypes = {
	tool: PropTypes.shape( {
		id: PropTypes.string.isRequired,
		name: PropTypes.string,
		description: PropTypes.string,
	} ).isRequired,
	schema: PropTypes.array.isRequired,
	currentConfig: PropTypes.object,
	connections: PropTypes.array,
	onSave: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
};
