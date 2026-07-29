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
    auto_open: __('Automatically open the chat widget after the visitor has been on the page for the specified duration.', 'smart-woo-chatbot'),
    exit_intent: __('Show the chat widget when the visitor moves their mouse towards the browser close button.', 'smart-woo-chatbot'),
    scroll_depth: __('Open the chat widget when the visitor scrolls past a certain percentage of the page.', 'smart-woo-chatbot'),
    time_on_page: __('Trigger the widget after the visitor has been on the page for a set time.', 'smart-woo-chatbot'),
    cart_abandonment: __('Show a message when a WooCommerce customer tries to leave with items in their cart.', 'smart-woo-chatbot'),
    returning_visitor: __('Display a special greeting for visitors who have been to your site before.', 'smart-woo-chatbot'),
};

export default function WidgetTriggers({ triggers, onChange }) {
    const handleChange = (field, value) => {
        onChange({ ...triggers, [field]: value });
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-semibold text-slate-900">
                    {__('Proactive Triggers', 'smart-woo-chatbot')}
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                    {__('Automatically engage visitors at the right moment to boost conversions.', 'smart-woo-chatbot')}
                </p>
            </div>

            <Panel>
                {/* Auto-Open After Delay */}
                <PanelBody title={__('Auto-Open Delay', 'smart-woo-chatbot')} initialOpen>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Auto-Open', 'smart-woo-chatbot')}
                            help={TRIGGER_HELP.auto_open}
                            checked={triggers.auto_open_enabled || false}
                            onChange={(val) => handleChange('auto_open_enabled', val)}
                        />
                        {triggers.auto_open_enabled && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-700">
                                        {__('Delay (seconds)', 'smart-woo-chatbot')}
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
                <PanelBody title={__('Exit Intent', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Exit Intent', 'smart-woo-chatbot')}
                            help={TRIGGER_HELP.exit_intent}
                            checked={triggers.exit_intent_enabled || false}
                            onChange={(val) => handleChange('exit_intent_enabled', val)}
                        />
                        {triggers.exit_intent_enabled && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    {__('Exit Intent Message', 'smart-woo-chatbot')}
                                </label>
                                <textarea
                                    className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
                                    value={triggers.exit_intent_message || ''}
                                    onChange={(e) => handleChange('exit_intent_message', e.target.value)}
                                    placeholder={__('Wait! Before you go, can I help you find what you\'re looking for?', 'smart-woo-chatbot')}
                                    rows={2}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Scroll Depth */}
                <PanelBody title={__('Scroll Depth', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Scroll Trigger', 'smart-woo-chatbot')}
                            help={TRIGGER_HELP.scroll_depth}
                            checked={triggers.scroll_depth_enabled || false}
                            onChange={(val) => handleChange('scroll_depth_enabled', val)}
                        />
                        {triggers.scroll_depth_enabled && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-700">
                                        {__('Scroll Percentage', 'smart-woo-chatbot')}
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
                <PanelBody title={__('Time on Page', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Time Trigger', 'smart-woo-chatbot')}
                            help={TRIGGER_HELP.time_on_page}
                            checked={triggers.time_on_page_enabled || false}
                            onChange={(val) => handleChange('time_on_page_enabled', val)}
                        />
                        {triggers.time_on_page_enabled && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-700">
                                        {__('Time (seconds)', 'smart-woo-chatbot')}
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
                <PanelBody title={__('Cart Abandonment', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Cart Abandonment Trigger', 'smart-woo-chatbot')}
                            help={TRIGGER_HELP.cart_abandonment}
                            checked={triggers.cart_abandonment_enabled || false}
                            onChange={(val) => handleChange('cart_abandonment_enabled', val)}
                        />
                        {triggers.cart_abandonment_enabled && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    {__('Cart Abandonment Message', 'smart-woo-chatbot')}
                                </label>
                                <textarea
                                    className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
                                    value={triggers.cart_abandonment_message || ''}
                                    onChange={(e) => handleChange('cart_abandonment_message', e.target.value)}
                                    placeholder={__('Don\'t forget your items! Need help completing your order?', 'smart-woo-chatbot')}
                                    rows={2}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Returning Visitor */}
                <PanelBody title={__('Returning Visitor', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                {__('Returning Visitor Greeting', 'smart-woo-chatbot')}
                            </label>
                            <p className="text-xs text-gray-500 mb-2">{TRIGGER_HELP.returning_visitor}</p>
                            <textarea
                                className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none"
                                value={triggers.returning_visitor_greeting || ''}
                                onChange={(e) => handleChange('returning_visitor_greeting', e.target.value)}
                                placeholder={__('Welcome back! How can I assist you today?', 'smart-woo-chatbot')}
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
