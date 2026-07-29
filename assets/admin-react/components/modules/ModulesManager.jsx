/**
 * Modules Manager Component
 *
 * Admin UI for viewing and toggling plugin modules.
 * Modules extend AI Agent capabilities (WordPress, WooCommerce, Forms, etc.)
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import PropTypes from 'prop-types';
import { Notice, Panel, PanelBody, Toggle } from '../ui';
import Loading from '../common/Loading';

/**
 * Module Card Component
 * @param root0
 * @param root0.module
 * @param root0.onToggle
 * @param root0.isLoading
 */
function ModuleCard({ module, onToggle, isLoading }) {
	const statusClass = module.available
		? module.enabled
			? 'swc-module--active'
			: 'swc-module--disabled'
		: 'swc-module--unavailable';

	return (
		<div className={`swc-module-card ${statusClass}`}>
			<div className="swc-module-card__header">
				<span className="swc-module-card__icon">{module.icon}</span>
				<div className="swc-module-card__info">
					<h4 className="swc-module-card__name">{module.name}</h4>
					{module.description && (
						<p className="swc-module-card__description">
							{module.description}
						</p>
					)}
				</div>
			</div>

			<div className="swc-module-card__meta">
				{module.tools_count > 0 && (
					<span className="swc-module-card__stat">
						{module.tools_count} tools
					</span>
				)}
				{module.skills_count > 0 && (
					<span className="swc-module-card__stat">
						{module.skills_count} skills
					</span>
				)}
			</div>

			<div className="swc-module-card__footer">
				{module.isCore ? (
					<span className="swc-module-card__badge swc-module-card__badge--core">
						{__('Core Module', 'smart-woo-chatbot')}
					</span>
				) : !module.available ? (
					<span className="swc-module-card__badge swc-module-card__badge--unavailable">
						{__('Plugin Required:', 'smart-woo-chatbot')}{' '}
						{module.missing}
					</span>
				) : (
					<Toggle
						label={
							module.enabled
								? __('Enabled', 'smart-woo-chatbot')
								: __('Disabled', 'smart-woo-chatbot')
						}
						checked={module.enabled}
						onChange={() =>
							onToggle(module.slug, !module.enabled)
						}
						disabled={isLoading}
					/>
				)}
			</div>
		</div>
	);
}

ModuleCard.propTypes = {
	module: PropTypes.shape({
		slug: PropTypes.string.isRequired,
		name: PropTypes.string.isRequired,
		description: PropTypes.string,
		icon: PropTypes.string,
		available: PropTypes.bool,
		enabled: PropTypes.bool,
		isCore: PropTypes.bool,
		tools_count: PropTypes.number,
		skills_count: PropTypes.number,
		missing: PropTypes.string,
	}).isRequired,
	onToggle: PropTypes.func.isRequired,
	isLoading: PropTypes.bool,
};

ModuleCard.defaultProps = {
	isLoading: false,
};

/**
 * Modules Manager Component
 */
export default function ModulesManager() {
	const [modules, setModules] = useState([]);
	const [loading, setLoading] = useState(true);
	const [toggling, setToggling] = useState(null);
	const [notification, setNotification] = useState(null);

	// Load modules on mount
	useEffect(() => {
		loadModules();
	}, []);

	const loadModules = async () => {
		try {
			const response = await apiFetch({
				path: '/smart-ai-chatbot/v1/modules',
				method: 'GET',
			});
			setModules(response.modules || []);
		} catch (err) {
			setNotification({
				type: 'error',
				message: err.message || 'Failed to load modules',
			});
		} finally {
			setLoading(false);
		}
	};

	const toggleModule = async (slug, enabled) => {
		setToggling(slug);
		try {
			const action = enabled ? 'enable' : 'disable';
			await apiFetch({
				path: `/smart-ai-chatbot/v1/modules/${slug}/${action}`,
				method: 'POST',
			});

			// Update local state
			setModules((prev) =>
				prev.map((m) => (m.slug === slug ? { ...m, enabled } : m))
			);

			setNotification({
				type: 'success',
				message: `${slug} ${enabled ? 'enabled' : 'disabled'
					} successfully`,
			});
		} catch (err) {
			setNotification({
				type: 'error',
				message: err.message || 'Failed to toggle module',
			});
		} finally {
			setToggling(null);
		}
	};

	if (loading) {
		return <Loading message={__('Loading modules…', 'smart-woo-chatbot')} fullPage />;
	}

	// Group modules by status
	const coreModules = modules.filter((m) => m.isCore);
	const optionalModules = modules.filter(
		(m) => !m.isCore && m.available
	);
	const unavailableModules = modules.filter(
		(m) => !m.isCore && !m.available
	);

	return (
		<div className="swc-modules-manager">
			{notification && (
				<Notice
					status={notification.type}
					onRemove={() => setNotification(null)}
					isDismissible
				>
					{notification.message}
				</Notice>
			)}

			<div className="swc-modules-intro">
				<p>
					{__(
						'Modules extend the AI Agent with specialized capabilities. Enable the modules you need.',
						'smart-woo-chatbot'
					)}
				</p>
			</div>

			<Panel>
				{ /* Core Modules */}
				<PanelBody
					title={`${__('Core Modules', 'smart-woo-chatbot')}`}
					initialOpen
				>
					<div className="swc-modules-grid">
						{coreModules.map((module) => (
							<ModuleCard
								key={module.slug}
								module={module}
								onToggle={toggleModule}
								isLoading={toggling === module.slug}
							/>
						))}
					</div>
				</PanelBody>

				{ /* Optional Modules */}
				{optionalModules.length > 0 && (
					<PanelBody
						title={`${__(
							'Optional Modules',
							'smart-woo-chatbot'
						)}`}
						initialOpen
					>
						<div className="swc-modules-grid">
							{optionalModules.map((module) => (
								<ModuleCard
									key={module.slug}
									module={module}
									onToggle={toggleModule}
									isLoading={toggling === module.slug}
								/>
							))}
						</div>
					</PanelBody>
				)}

				{ /* Unavailable Modules */}
				{unavailableModules.length > 0 && (
					<PanelBody
						title={`${__(
							'Unavailable Modules',
							'smart-woo-chatbot'
						)}`}
						initialOpen={false}
					>
						<p className="swc-text-muted">
							{__(
								'These modules require additional plugins to be installed.',
								'smart-woo-chatbot'
							)}
						</p>
						<div className="swc-modules-grid">
							{unavailableModules.map((module) => (
								<ModuleCard
									key={module.slug}
									module={module}
									onToggle={toggleModule}
									isLoading={toggling === module.slug}
								/>
							))}
						</div>
					</PanelBody>
				)}
			</Panel>
		</div>
	);
}
