/**
 * Widget Triggers Configuration
 *
 * Proactive triggers for automatically engaging visitors.
 * Includes: auto-open delay, exit intent, scroll depth, time on page, cart abandonment.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Panel, PanelBody, Toggle, Range } from '../ui';

const TRIGGER_HELP = {
    auto_open: __('Automatically open the chat widget after the visitor has been on the page for the specified duration.', 'agentflow-ai'),
    exit_intent: __('Show the chat widget when the visitor moves their mouse towards the browser close button.', 'agentflow-ai'),
    scroll_depth: __('Open the chat widget when the visitor scrolls past a certain percentage of the page.', 'agentflow-ai'),
    time_on_page: __('Trigger the widget after the visitor has been on the page for a set time.', 'agentflow-ai'),
    cart_abandonment: __('Show a message when a WooCommerce customer tries to leave with items in their cart.', 'agentflow-ai'),
    returning_visitor: __('Display a special greeting for visitors who have been to your site before.', 'agentflow-ai'),
};

export default function WidgetTriggers({ triggers, onChange }) {
    const handleChange = (field, value) => {
        onChange({ ...triggers, [field]: value });
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-semibold text-slate-900">
                    {__('Proactive Triggers', 'agentflow-ai')}
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                    {__('Automatically engage visitors at the right moment to boost conversions.', 'agentflow-ai')}
                </p>
            </div>

            <Panel>
                {/* Auto-Open After Delay */}
                <PanelBody title={__('Auto-Open Delay', 'agentflow-ai')} initialOpen>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Auto-Open', 'agentflow-ai')}
                            help={TRIGGER_HELP.auto_open}
                            checked={triggers.auto_open_enabled || false}
                            onChange={(val) => handleChange('auto_open_enabled', val)}
                        />
                        {triggers.auto_open_enabled && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-700">
                                        {__('Delay (seconds)', 'agentflow-ai')}
                                    </span>
                                    <span className="text-sm font-semibold text-primary">
                                        {triggers.auto_open_delay || 10}s
                                    </span>
                                </div>
                                <Range
                                    value={triggers.auto_open_delay || 10}
                                    onChange={(val) => handleChange('auto_open_delay', val)}
                                    min={0}
                                    max={60}
                                    step={1}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Exit Intent */}
                <PanelBody title={__('Exit Intent', 'agentflow-ai')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Exit Intent', 'agentflow-ai')}
                            help={TRIGGER_HELP.exit_intent}
                            checked={triggers.exit_intent_enabled || false}
                            onChange={(val) => handleChange('exit_intent_enabled', val)}
                        />
                        {triggers.exit_intent_enabled && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    {__('Exit Intent Message', 'agentflow-ai')}
                                </label>
                                <textarea
                                    className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
                                    value={triggers.exit_intent_message || ''}
                                    onChange={(e) => handleChange('exit_intent_message', e.target.value)}
                                    placeholder={__('Wait! Before you go, can I help you find what you\'re looking for?', 'agentflow-ai')}
                                    rows={2}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Scroll Depth */}
                <PanelBody title={__('Scroll Depth', 'agentflow-ai')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Scroll Trigger', 'agentflow-ai')}
                            help={TRIGGER_HELP.scroll_depth}
                            checked={triggers.scroll_depth_enabled || false}
                            onChange={(val) => handleChange('scroll_depth_enabled', val)}
                        />
                        {triggers.scroll_depth_enabled && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-700">
                                        {__('Scroll Percentage', 'agentflow-ai')}
                                    </span>
                                    <span className="text-sm font-semibold text-primary">
                                        {triggers.scroll_depth_percent || 50}%
                                    </span>
                                </div>
                                <Range
                                    value={triggers.scroll_depth_percent || 50}
                                    onChange={(val) => handleChange('scroll_depth_percent', val)}
                                    min={10}
                                    max={100}
                                    step={10}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Time on Page */}
                <PanelBody title={__('Time on Page', 'agentflow-ai')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Time Trigger', 'agentflow-ai')}
                            help={TRIGGER_HELP.time_on_page}
                            checked={triggers.time_on_page_enabled || false}
                            onChange={(val) => handleChange('time_on_page_enabled', val)}
                        />
                        {triggers.time_on_page_enabled && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-700">
                                        {__('Time (seconds)', 'agentflow-ai')}
                                    </span>
                                    <span className="text-sm font-semibold text-primary">
                                        {triggers.time_on_page_seconds || 30}s
                                    </span>
                                </div>
                                <Range
                                    value={triggers.time_on_page_seconds || 30}
                                    onChange={(val) => handleChange('time_on_page_seconds', val)}
                                    min={5}
                                    max={300}
                                    step={5}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Cart Abandonment (WooCommerce) */}
                <PanelBody title={__('Cart Abandonment', 'agentflow-ai')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Cart Abandonment Trigger', 'agentflow-ai')}
                            help={TRIGGER_HELP.cart_abandonment}
                            checked={triggers.cart_abandonment_enabled || false}
                            onChange={(val) => handleChange('cart_abandonment_enabled', val)}
                        />
                        {triggers.cart_abandonment_enabled && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    {__('Cart Abandonment Message', 'agentflow-ai')}
                                </label>
                                <textarea
                                    className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
                                    value={triggers.cart_abandonment_message || ''}
                                    onChange={(e) => handleChange('cart_abandonment_message', e.target.value)}
                                    placeholder={__('Don\'t forget your items! Need help completing your order?', 'agentflow-ai')}
                                    rows={2}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Returning Visitor */}
                <PanelBody title={__('Returning Visitor', 'agentflow-ai')}>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                {__('Returning Visitor Greeting', 'agentflow-ai')}
                            </label>
                            <p className="text-xs text-gray-500 mb-2">{TRIGGER_HELP.returning_visitor}</p>
                            <textarea
                                className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
                                value={triggers.returning_visitor_greeting || ''}
                                onChange={(e) => handleChange('returning_visitor_greeting', e.target.value)}
                                placeholder={__('Welcome back! How can I assist you today?', 'agentflow-ai')}
                                rows={2}
                            />
                        </div>
                    </div>
                </PanelBody>
            </Panel>
        </div>
    );
}

WidgetTriggers.propTypes = {
    triggers: PropTypes.object.isRequired,
    onChange: PropTypes.func.isRequired,
};
