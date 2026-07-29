/**
 * Onboarding Wizard Component
 *
 * First-run experience for new users.
 * Guides through API key setup and first agent creation.
 *
 * @version 1.0.0
 * @package
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import apiFetch from '@wordpress/api-fetch';
import Modal from './Modal';
import { Button, TextField } from '../ui';

const WIZARD_STEPS = [
	{ id: 'welcome', title: __('Welcome', 'smart-ai-chatbot') },
	{ id: 'provider', title: __('AI Provider', 'smart-ai-chatbot') },
	{ id: 'api-key', title: __('API Key', 'smart-ai-chatbot') },
	{ id: 'first-agent', title: __('First Agent', 'smart-ai-chatbot') },
	{ id: 'complete', title: __('Complete', 'smart-ai-chatbot') },
];

const AI_PROVIDERS = [
	{ id: 'openai', name: 'OpenAI', color: '#10a37f', popular: true },
	{ id: 'anthropic', name: 'Claude', color: '#cc785c', popular: true },
	{ id: 'azure', name: 'Azure OpenAI', color: '#0078d4', popular: true },
	{ id: 'gemini', name: 'Google Gemini', color: '#8e44ad', popular: false },
	{ id: 'groq', name: 'Groq', color: '#f7931e', popular: false },
	{ id: 'openrouter', name: 'OpenRouter', color: '#6366f1', popular: false },
];

/**
 * OnboardingWizard Component
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen
 * @param {Function} props.onComplete
 * @param {Function} props.onSkip
 */
export default function OnboardingWizard( { isOpen, onComplete, onSkip } ) {
	const [ currentStep, setCurrentStep ] = useState( 0 );
	const [ formData, setFormData ] = useState( {
		provider: 'openai',
		apiKey: '',
		agentName: 'Shopping Assistant',
	} );
	const [ testing, setTesting ] = useState( false );
	const [ testResult, setTestResult ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	const step = WIZARD_STEPS[ currentStep ];

	const canProceed = () => {
		switch ( step.id ) {
			case 'provider':
				return !! formData.provider;
			case 'api-key':
				return formData.apiKey.length > 10;
			case 'first-agent':
				return formData.agentName.trim().length > 0;
			default:
				return true;
		}
	};

	const handleNext = async () => {
		if ( step.id === 'api-key' && formData.apiKey ) {
			// Test API key before proceeding
			setTesting( true );
			try {
				const response = await apiFetch( {
					path: '/smart-ai-chatbot/v1/settings/test-connection',
					method: 'POST',
					data: {
						provider: formData.provider,
						api_key: formData.apiKey,
					},
				} );
				setTestResult( response.success ? 'success' : 'error' );
				if ( ! response.success ) {
					return; // Don't proceed on failure
				}
			} catch ( err ) {
				setTestResult( 'error' );
				return;
			} finally {
				setTesting( false );
			}
		}

		if ( step.id === 'first-agent' ) {
			// Save everything and create agent
			setSaving( true );
			try {
				// Save settings
				await apiFetch( {
					path: '/smart-ai-chatbot/v1/settings',
					method: 'POST',
					data: {
						settings: {
							ai_provider: formData.provider,
							api_key: formData.apiKey,
						},
					},
				} );

				// Mark onboarding complete
				await apiFetch( {
					path: '/smart-ai-chatbot/v1/settings/onboarding',
					method: 'POST',
					data: { completed: true },
				} );
			} catch ( err ) {
				console.error( 'Onboarding save error:', err );
			} finally {
				setSaving( false );
			}
		}

		if ( currentStep < WIZARD_STEPS.length - 1 ) {
			setCurrentStep( currentStep + 1 );
		} else {
			onComplete();
		}
	};

	const handleBack = () => {
		if ( currentStep > 0 ) {
			setCurrentStep( currentStep - 1 );
			setTestResult( null );
		}
	};

	const renderStepContent = () => {
		switch ( step.id ) {
			case 'welcome':
				return (
					<div className="swc-onboarding__welcome">
						<h2
							className="swc-text-2xl swc-font-bold swc-mb-3"
							style={ {
								background:
									'linear-gradient(135deg, #1e293b 0%, #6366f1 50%, #8b5cf6 100%)',
								WebkitBackgroundClip: 'text',
								WebkitTextFillColor: 'transparent',
								backgroundClip: 'text',
							} }
						>
							{ __('Welcome to Smart Chatbot', 'smart-ai-chatbot') }
						</h2>
						<p
							className="swc-text-gray-500 swc-text-sm"
							style={ { maxWidth: '380px', lineHeight: '1.6' } }
						>
							{ __("Set up your AI shopping assistant in under 2 minutes. We'll connect your AI provider and create your first intelligent agent.", 'smart-ai-chatbot') }
						</p>
						<div className="swc-onboarding__features">
							<div className="swc-onboarding__feature">
								{ __('Smart Conversations', 'smart-ai-chatbot') }
							</div>
							<div className="swc-onboarding__feature">
								{ __('Product Discovery', 'smart-ai-chatbot') }
							</div>
							<div className="swc-onboarding__feature">
								{ __('Order Tracking', 'smart-ai-chatbot') }
							</div>
						</div>
					</div>
				);

			case 'provider':
				return (
					<div className="swc-onboarding__provider">
						<h3 className="swc-text-lg swc-font-bold swc-mb-2">
							{ __('Choose Your AI Provider', 'smart-ai-chatbot') }
						</h3>
						<p className="swc-text-sm swc-text-gray-600 swc-mb-4">
							{ __('Select the AI service you want to power your chatbot.', 'smart-ai-chatbot') }
						</p>

						<div className="swc-onboarding__provider-grid">
							{ AI_PROVIDERS.filter( ( p ) => p.popular ).map(
								( provider ) => (
									<Button
										key={ provider.id }
										className={ `swc-onboarding__provider-card ${
											formData.provider === provider.id
												? 'swc-onboarding__provider-card--selected'
												: ''
										}` }
										useLegacyClasses={ false }
										unstyled
										mantineVariant="subtle"
										onClick={ () =>
											setFormData( {
												...formData,
												provider: provider.id,
											} )
										}
									>
										<span className="swc-onboarding__provider-name">
											{ provider.name }
										</span>
										{ provider.popular && (
											<span className="swc-badge swc-badge--success swc-badge--sm">
												Popular
											</span>
										) }
									</Button>
								)
							) }
						</div>

						<details className="swc-onboarding__more-providers swc-mt-4">
							<summary className="swc-text-sm swc-text-primary swc-cursor-pointer swc-outline-none">
								{ __('Show more providers', 'smart-ai-chatbot') }
							</summary>
							<div className="swc-onboarding__provider-grid swc-mt-3">
								{ AI_PROVIDERS.filter(
									( p ) => ! p.popular
								).map( ( provider ) => (
									<Button
										key={ provider.id }
										className={ `swc-onboarding__provider-card ${
											formData.provider === provider.id
												? 'swc-onboarding__provider-card--selected'
												: ''
										}` }
										useLegacyClasses={ false }
										unstyled
										mantineVariant="subtle"
										onClick={ () =>
											setFormData( {
												...formData,
												provider: provider.id,
											} )
										}
									>
										<span className="swc-onboarding__provider-name">
											{ provider.name }
										</span>
									</Button>
								) ) }
							</div>
						</details>
					</div>
				);

			case 'api-key':
				return (
					<div className="swc-onboarding__api-key swc-w-full">
						<h3 className="swc-text-lg swc-font-bold swc-mb-2">
							{ __('Enter Your API Key', 'smart-ai-chatbot') }
						</h3>
						<p className="swc-text-sm swc-text-gray-600 swc-mb-4">
							{ __('Paste your API key from', 'smart-ai-chatbot') }{ ' ' }
							<strong>{ formData.provider }</strong>.
						</p>

						<div className="swc-form-group">
							<TextField
								label={ __('API Key', 'smart-ai-chatbot') }
								type="password"
								placeholder="sk-..."
								value={ formData.apiKey }
								onChange={ ( value ) => {
									setFormData( {
										...formData,
										apiKey: value,
									} );
									setTestResult( null );
								} }
							/>
							{ testResult === 'error' && (
								<p className="swc-help swc-text-error">
									{ __('Invalid API key. Please check and try again.', 'smart-ai-chatbot') }
								</p>
							) }
							{ testResult === 'success' && (
								<p className="swc-help swc-text-success">
									{ ' ' }
									{ __('Connection successful!', 'smart-ai-chatbot') }
								</p>
							) }
						</div>
					</div>
				);

			case 'first-agent':
				return (
					<div className="swc-onboarding__first-agent swc-w-full">
						<h3 className="swc-text-lg swc-font-bold swc-mb-2">
							{ __('Name Your First Agent', 'smart-ai-chatbot') }
						</h3>
						<p className="swc-text-sm swc-text-gray-600 swc-mb-4">
							{ __('Give your AI assistant a friendly name that customers will see.', 'smart-ai-chatbot') }
						</p>

						<div className="swc-form-group">
							<TextField
								label={ __('Agent Name', 'smart-ai-chatbot') }
								placeholder={ __('Shopping Assistant', 'smart-ai-chatbot') }
								value={ formData.agentName }
								onChange={ ( value ) =>
									setFormData( {
										...formData,
										agentName: value,
									} )
								}
							/>
						</div>

						<div className="swc-card swc-p-4 swc-mt-4 swc-bg-gray-50">
							<div className="swc-bg-white swc-p-3 swc-rounded-lg swc-border swc-border-gray-200 swc-text-sm swc-text-gray-700">
								{ __('Hello! How can I help you today?', 'smart-ai-chatbot') }
							</div>
						</div>
					</div>
				);

			case 'complete':
				return (
					<div className="swc-onboarding__complete">
						<h2 className="swc-text-2xl swc-font-bold swc-mb-3">
							{ __("You're All Set!", 'smart-ai-chatbot') }
						</h2>
						<p className="swc-text-gray-600 swc-mb-6">
							{ __('Your Smart Chatbot is ready to help customers. You can now customize your agent, add more toolkits, and configure advanced settings.', 'smart-ai-chatbot') }
						</p>
						<div className="swc-onboarding__next-steps swc-grid swc-grid--3 swc-gap-4 swc-w-full">
							<div className="swc-card swc-p-4 swc-text-center">
								<span className="swc-text-sm swc-font-medium text-gray-700">
									{ __('Add Knowledge Base sources', 'smart-ai-chatbot') }
								</span>
							</div>
							<div className="swc-card swc-p-4 swc-text-center">
								<span className="swc-text-sm swc-font-medium text-gray-700">
									{ __('Configure chat appearance', 'smart-ai-chatbot') }
								</span>
							</div>
							<div className="swc-card swc-p-4 swc-text-center">
								<span className="swc-text-sm swc-font-medium text-gray-700">
									{ __('Customize widget styling', 'smart-ai-chatbot') }
								</span>
							</div>
						</div>
					</div>
				);

			default:
				return null;
		}
	};

	return (
		<Modal
			isOpen={ isOpen }
			onClose={ onSkip }
			title=""
			size="lg"
			showCloseButton={ false }
		>
			<div className="swc-onboarding">
				{ /* Progress Steps */ }
				<div className="swc-onboarding__progress">
					{ WIZARD_STEPS.map( ( s, idx ) => (
						<div
							key={ s.id }
							className={ `swc-onboarding__step ${
								idx === currentStep
									? 'swc-onboarding__step--active'
									: ''
							} ${
								idx < currentStep
									? 'swc-onboarding__step--completed'
									: ''
							}` }
						>
							<span className="swc-onboarding__step-title">
								{ s.title }
							</span>
						</div>
					) ) }
				</div>

				{ /* Step Content */ }
				<div className="swc-onboarding__content">
					{ renderStepContent() }
				</div>

				{ /* Footer */ }
				<div className="swc-onboarding__footer">
					{ currentStep > 0 &&
						currentStep < WIZARD_STEPS.length - 1 && (
							<Button variant="secondary" onClick={ handleBack }>
								{ __('Back', 'smart-ai-chatbot') }
							</Button>
						) }

					<div className="swc-onboarding__footer-right">
						{ currentStep === 0 && (
							<Button variant="ghost" onClick={ onSkip }>
								{ __('Skip for now', 'smart-ai-chatbot') }
							</Button>
						) }
						<Button
							variant="primary"
							onClick={ handleNext }
							disabled={ ! canProceed() || testing || saving }
							isBusy={ testing || saving }
						>
							{ step.id === 'complete'
								? __('Get Started', 'smart-ai-chatbot')
								: step.id === 'first-agent'
								? __('Finish Setup', 'smart-ai-chatbot')
								: __('Continue', 'smart-ai-chatbot') }
						</Button>
					</div>
				</div>
			</div>
		</Modal>
	);
}

OnboardingWizard.propTypes = {
	isOpen: PropTypes.bool,
	onComplete: PropTypes.func.isRequired,
	onSkip: PropTypes.func.isRequired,
};
