/**
 * Engagement Settings Configuration
 *
 * Configure how the widget engages with visitors.
 * Includes: quick questions, notifications, branding, rate limiting.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Panel, PanelBody, Toggle, Range, Button, cn } from '../ui';

export default function EngagementSettings({ engagement, onChange }) {
    const [newReply, setNewReply] = useState('');

    const handleChange = (field, value) => {
        onChange({ ...engagement, [field]: value });
    };

    const addQuickReply = () => {
        if (!newReply.trim()) return;
        const replies = engagement.quick_replies || [];
        handleChange('quick_replies', [...replies, { text: newReply.trim(), action: 'message' }]);
        setNewReply('');
    };

    const removeQuickReply = (index) => {
        const replies = [...(engagement.quick_replies || [])];
        replies.splice(index, 1);
        handleChange('quick_replies', replies);
    };

    const moveQuickReply = (index, direction) => {
        const replies = [...(engagement.quick_replies || [])];
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= replies.length) return;
        [replies[index], replies[newIndex]] = [replies[newIndex], replies[index]];
        handleChange('quick_replies', replies);
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-semibold text-slate-900">
                    {__('Engagement Settings', 'smart-woo-chatbot')}
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                    {__('Configure quick questions, notifications, and conversation settings.', 'smart-woo-chatbot')}
                </p>
            </div>

            <Panel>
                {/* Quick Questions */}
                <PanelBody title={__('Quick Questions', 'smart-woo-chatbot')} initialOpen>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Quick Questions', 'smart-woo-chatbot')}
                            help={__('Show preset question buttons for common topics when the chat opens.', 'smart-woo-chatbot')}
                            checked={engagement.quick_replies_enabled !== false}
                            onChange={(val) => handleChange('quick_replies_enabled', val)}
                        />

                        {engagement.quick_replies_enabled !== false && (
                            <>
                                <div className="space-y-2">
                                    {(engagement.quick_replies || []).map((reply, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center gap-2 p-2 bg-slate-50 rounded-lg border border-slate-200"
                                        >
                                            <span className="flex-1 text-sm font-medium text-slate-700">
                                                {reply.text}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => moveQuickReply(index, -1)}
                                                disabled={index === 0}
                                                className="p-1 text-slate-400 hover:text-slate-600 disabled:opacity-30"
                                                title={__('Move Up', 'smart-woo-chatbot')}
                                            >
                                                ↑
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => moveQuickReply(index, 1)}
                                                disabled={index === (engagement.quick_replies || []).length - 1}
                                                className="p-1 text-slate-400 hover:text-slate-600 disabled:opacity-30"
                                                title={__('Move Down', 'smart-woo-chatbot')}
                                            >
                                                ↓
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => removeQuickReply(index)}
                                                className="p-1 text-red-400 hover:text-red-600"
                                                title={__('Remove', 'smart-woo-chatbot')}
                                            >
                                                
                                            </button>
                                        </div>
                                    ))}
                                </div>

                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        className="flex-1 h-10 px-3 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                        value={newReply}
                                        onChange={(e) => setNewReply(e.target.value)}
                                        placeholder={__('Add a quick question...', 'smart-woo-chatbot')}
                                        onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addQuickReply())}
                                    />
                                    <Button onClick={addQuickReply} variant="secondary">
                                        {__('Add', 'smart-woo-chatbot')}
                                    </Button>
                                </div>

                                {(engagement.quick_replies || []).length === 0 && (
                                    <div className="text-center py-4 text-sm text-slate-500 bg-slate-50 rounded-lg border-2 border-dashed border-slate-200">
                                        {__('No quick questions yet. Add common questions like "Track Order" or "Help".', 'smart-woo-chatbot')}
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </PanelBody>

                {/* Notifications */}
                <PanelBody title={__('Notifications', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Sound on New Message', 'smart-woo-chatbot')}
                            help={__('Play a notification sound when a new message is received.', 'smart-woo-chatbot')}
                            checked={engagement.sound_enabled || false}
                            onChange={(val) => handleChange('sound_enabled', val)}
                        />
                        <Toggle
                            label={__('Flash Browser Tab', 'smart-woo-chatbot')}
                            help={__('Flash the browser tab title when there are unread messages.', 'smart-woo-chatbot')}
                            checked={engagement.tab_flash_enabled || false}
                            onChange={(val) => handleChange('tab_flash_enabled', val)}
                        />
                        <Toggle
                            label={__('Browser Notifications', 'smart-woo-chatbot')}
                            help={__('Request permission to send desktop notifications.', 'smart-woo-chatbot')}
                            checked={engagement.browser_notifications_enabled || false}
                            onChange={(val) => handleChange('browser_notifications_enabled', val)}
                        />
                    </div>
                </PanelBody>

                {/* Conversation Settings */}
                <PanelBody title={__('Conversation Settings', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Typing Indicator', 'smart-woo-chatbot')}
                            help={__('Show animated dots while the AI is generating a response.', 'smart-woo-chatbot')}
                            checked={engagement.typing_indicator !== false}
                            onChange={(val) => handleChange('typing_indicator', val)}
                        />
                        <Toggle
                            label={__('Auto-Send Welcome Message', 'smart-woo-chatbot')}
                            help={__('Automatically send the greeting message when the chat opens.', 'smart-woo-chatbot')}
                            checked={engagement.auto_send_welcome || false}
                            onChange={(val) => handleChange('auto_send_welcome', val)}
                        />
                        <Toggle
                            label={__('Pass User Context', 'smart-woo-chatbot')}
                            help={__('Send logged-in user data (name, email, order history) to the AI for personalized responses.', 'smart-woo-chatbot')}
                            checked={engagement.pass_user_context || false}
                            onChange={(val) => handleChange('pass_user_context', val)}
                        />
                        <Toggle
                            label={__('Session Persistence', 'smart-woo-chatbot')}
                            help={__('Remember conversation history across page loads within the same browser session.', 'smart-woo-chatbot')}
                            checked={engagement.session_persistence !== false}
                            onChange={(val) => handleChange('session_persistence', val)}
                        />
                    </div>
                </PanelBody>

                {/* Rate Limiting */}
                <PanelBody title={__('Rate Limiting', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Rate Limiting', 'smart-woo-chatbot')}
                            help={__('Limit messages per minute to prevent spam and abuse.', 'smart-woo-chatbot')}
                            checked={engagement.rate_limiting_enabled || false}
                            onChange={(val) => handleChange('rate_limiting_enabled', val)}
                        />
                        {engagement.rate_limiting_enabled && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-700">
                                        {__('Max Messages Per Minute', 'smart-woo-chatbot')}
                                    </span>
                                    <span className="text-sm font-semibold text-primary">
                                        {engagement.rate_limit_per_minute || 10}
                                    </span>
                                </div>
                                <Range
                                    value={engagement.rate_limit_per_minute || 10}
                                    onChange={(val) => handleChange('rate_limit_per_minute', val)}
                                    min={1}
                                    max={30}
                                    step={1}
                                />
                            </div>
                        )}
                    </div>
                </PanelBody>

                {/* Branding */}
                <PanelBody title={__('Branding', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Show "Powered by" Badge', 'smart-woo-chatbot')}
                            help={__('Display the Quarksol branding in the chat widget footer.', 'smart-woo-chatbot')}
                            checked={engagement.powered_by !== false}
                            onChange={(val) => handleChange('powered_by', val)}
                        />
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                {__('Custom CSS', 'smart-woo-chatbot')}
                            </label>
                            <textarea
                                className="w-full h-24 px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary font-mono"
                                value={engagement.custom_css || ''}
                                onChange={(e) => handleChange('custom_css', e.target.value)}
                                placeholder={__('/* Advanced CSS customization */\n.smart-ai-chatbot { ... }', 'smart-woo-chatbot')}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                {__('For advanced users. CSS will be injected into the widget.', 'smart-woo-chatbot')}
                            </p>
                        </div>
                    </div>
                </PanelBody>

                {/* Post-Chat */}
                <PanelBody title={__('Post-Chat', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Post-Chat Rating', 'smart-woo-chatbot')}
                            help={__('Ask users to rate their conversation after it ends.', 'smart-woo-chatbot')}
                            checked={engagement.post_chat_rating || false}
                            onChange={(val) => handleChange('post_chat_rating', val)}
                        />
                        <Toggle
                            label={__('Email Transcript', 'smart-woo-chatbot')}
                            help={__('Offer to email the conversation transcript to the user.', 'smart-woo-chatbot')}
                            checked={engagement.email_transcript || false}
                            onChange={(val) => handleChange('email_transcript', val)}
                        />
                    </div>
                </PanelBody>
            </Panel>
        </div>
    );
}

EngagementSettings.propTypes = {
    engagement: PropTypes.object.isRequired,
    onChange: PropTypes.func.isRequired,
};
