/**
 * Widget Preset Templates
 *
 * Pre-configured widget setups for common use cases.
 * Admins can select these when creating a new widget to get started quickly.
 */

export const WIDGET_PRESETS = [
    {
        id: 'ecommerce-support',
        name: 'E-commerce Support',
        description: 'Perfect for WooCommerce stores. Includes order tracking, product help, and cart recovery.',
        icon: 'cart',
        category: 'ecommerce',
        config: {
            display_name: 'Shop Assistant',
            behavior: {
                greeting_message: 'Hi there! I\'m your shopping assistant. How can I help you today?',
                placeholder_text: 'Ask about orders, products, or shipping...',
                returning_visitor_greeting: 'Welcome back! Ready to continue shopping or need help with your order?',
                auto_open_enabled: false,
                exit_intent_enabled: true,
                exit_intent_message: 'Wait! Before you go - need help finding something or have questions about your order?',
                cart_abandonment_enabled: true,
                cart_abandonment_message: 'I noticed you have items in your cart. Need help completing your order? I can answer questions about shipping, returns, or products.',
            },
            appearance: {
                template: 'friendly-bubble',
                color_primary: '#10b981',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: ['/my-account/*'],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: 'Track My Order', action: 'message' },
                    { text: 'Find a Product', action: 'message' },
                    { text: 'Shipping Info', action: 'message' },
                    { text: 'Returns & Refunds', action: 'message' },
                ],
                typing_indicator: true,
                pass_user_context: true,
                session_persistence: true,
            },
        },
    },
    {
        id: 'lead-generation',
        name: 'Lead Generation',
        description: 'Capture leads with exit intent and proactive engagement. Great for landing pages.',
        icon: 'target',
        category: 'marketing',
        config: {
            display_name: 'Quick Connect',
            behavior: {
                greeting_message: 'Hey! Have a quick question? I\'m here to help!',
                placeholder_text: 'Type your question here...',
                auto_open_enabled: true,
                auto_open_delay: 15,
                exit_intent_enabled: true,
                exit_intent_message: 'Before you go! Have any questions I can answer? Takes just 30 seconds!',
                scroll_depth_enabled: true,
                scroll_depth_percent: 60,
                time_on_page_enabled: false,
            },
            appearance: {
                template: 'playful-pop',
                color_primary: '#f59e0b',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: ['/thank-you/*'],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: 'Get a Quote', action: 'message' },
                    { text: 'Book a Demo', action: 'message' },
                    { text: 'Ask a Question', action: 'message' },
                ],
                typing_indicator: true,
                auto_send_welcome: true,
                pass_user_context: false,
            },
        },
    },
    {
        id: 'customer-support',
        name: 'Customer Support Hub',
        description: 'Professional support with business hours and FAQ-focused interactions.',
        icon: 'headset',
        category: 'support',
        config: {
            display_name: 'Support Team',
            behavior: {
                greeting_message: 'Hello! I\'m here to help with any questions. What can I assist you with today?',
                placeholder_text: 'Describe your issue or question...',
                auto_open_enabled: false,
                exit_intent_enabled: false,
                time_on_page_enabled: true,
                time_on_page_seconds: 60,
            },
            appearance: {
                template: 'corporate-clean',
                color_primary: '#3b82f6',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: [],
                devices: ['desktop', 'tablet', 'mobile'],
                schedule_enabled: true,
                schedule_start: '09:00',
                schedule_end: '18:00',
                schedule_days: [1, 2, 3, 4, 5],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: 'Technical Issue', action: 'message' },
                    { text: 'Billing Question', action: 'message' },
                    { text: 'How-to Guide', action: 'message' },
                    { text: 'General Inquiry', action: 'message' },
                ],
                typing_indicator: true,
                pass_user_context: true,
                post_chat_rating: true,
                email_transcript: true,
            },
        },
    },
    {
        id: 'sales-assistant',
        name: 'Sales Assistant',
        description: 'Engage shoppers on product pages with helpful recommendations and answers.',
        icon: 'briefcase',
        category: 'sales',
        config: {
            display_name: 'Sales Helper',
            behavior: {
                greeting_message: 'Looking for the perfect product? I can help you find exactly what you need!',
                placeholder_text: 'Ask me about our products...',
                returning_visitor_greeting: 'Good to see you again! Found what you were looking for, or need some recommendations?',
                auto_open_enabled: false,
                scroll_depth_enabled: true,
                scroll_depth_percent: 40,
                time_on_page_enabled: true,
                time_on_page_seconds: 45,
            },
            appearance: {
                template: 'elegant-glass',
                color_primary: '#8b5cf6',
                position: 'right',
            },
            display: {
                include_urls: ['/shop/*', '/product/*', '/products/*'],
                exclude_urls: ['/cart', '/checkout/*'],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: 'Best Sellers', action: 'message' },
                    { text: 'Today\'s Deals', action: 'message' },
                    { text: 'Compare Products', action: 'message' },
                    { text: 'Need Advice', action: 'message' },
                ],
                typing_indicator: true,
                pass_user_context: true,
                sound_enabled: false,
            },
        },
    },
    {
        id: 'simple-greeter',
        name: 'Simple Greeter',
        description: 'Minimal setup for quick deployment. Works on all pages with basic engagement.',
        icon: 'wave',
        category: 'general',
        config: {
            display_name: 'Chat with Us',
            behavior: {
                greeting_message: 'Hi! How can I help you today?',
                placeholder_text: 'Type your message...',
                auto_open_enabled: false,
                exit_intent_enabled: false,
            },
            appearance: {
                template: 'modern-minimal',
                color_primary: '#6366f1',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: [],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: 'Ask a Question', action: 'message' },
                    { text: 'Contact Us', action: 'message' },
                ],
                typing_indicator: true,
                session_persistence: true,
            },
        },
    },
    {
        id: 'vip-concierge',
        name: 'VIP Concierge',
        description: 'Exclusive gold and black design for high-ticket items and premium service.',
        icon: 'diamond',
        category: 'premium',
        config: {
            display_name: 'Concierge',
            behavior: {
                greeting_message: 'Welcome to our exclusive service. How may we assist you today?',
                placeholder_text: 'Type your request...',
                auto_open_enabled: true,
                auto_open_delay: 5,
                exit_intent_enabled: true,
                exit_intent_message: 'Before you leave, may we offer you a personalized recommendation?',
            },
            appearance: {
                template: 'luxury-gold',
                color_primary: '#d4af37',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: [],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: 'Private Consultation', action: 'message' },
                    { text: 'Exclusive Offers', action: 'message' },
                    { text: 'Priority Support', action: 'message' },
                ],
                typing_indicator: true,
                pass_user_context: true,
            },
        },
    },
    {
        id: 'cyberpunk-support',
        name: 'Cyberpunk Support',
        description: 'Futuristic neon style perfect for tech, gaming, or crypto brands.',
        icon: 'cpu',
        category: 'tech',
        config: {
            display_name: 'SysAdmin',
            behavior: {
                greeting_message: 'System online. Ready to assist. Input command or query.',
                placeholder_text: '> Input query...',
                auto_open_enabled: false,
                time_on_page_enabled: true,
                time_on_page_seconds: 20,
            },
            appearance: {
                template: 'cyberpunk-neon',
                color_primary: '#39ff14',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: [],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: '/status', action: 'message' },
                    { text: '/help', action: 'message' },
                    { text: '/report_bug', action: 'message' },
                ],
                typing_indicator: true,
                sound_enabled: true,
            },
        },
    },
    {
        id: 'minimalist-mono',
        name: 'Minimalist Mono',
        description: 'Ultra-clean black and white design. Distraction-free.',
        icon: 'maximize',
        category: 'professional',
        config: {
            display_name: 'Assistant',
            behavior: {
                greeting_message: 'Hello. How can I help?',
                placeholder_text: 'Message...',
                auto_open_enabled: false,
                exit_intent_enabled: false,
            },
            appearance: {
                template: 'minimalist-mono',
                color_primary: '#000000',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: [],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: 'Products', action: 'message' },
                    { text: 'Help', action: 'message' },
                ],
                typing_indicator: true,
                pass_user_context: true,
            },
        },
    },
    {
        id: 'social-gradient',
        name: 'Social Gradient',
        description: 'Trendy, colorful gradient design for lifestyle and influencer brands.',
        icon: 'camera',
        category: 'creative',
        config: {
            display_name: 'Social Team',
            behavior: {
                greeting_message: 'Hey fam!  What\'s the vibe today?',
                placeholder_text: 'Slide into DMs...',
                auto_open_enabled: true,
                auto_open_delay: 8,
                exit_intent_enabled: true,
                exit_intent_message: 'Don\'t miss out! Join our community before you go ',
            },
            appearance: {
                template: 'social-gradient',
                color_primary: '#833ab4',
                position: 'right',
            },
            display: {
                include_urls: [],
                exclude_urls: [],
                devices: ['desktop', 'tablet', 'mobile'],
            },
            engagement: {
                quick_replies_enabled: true,
                quick_replies: [
                    { text: ' Hot Trends', action: 'message' },
                    { text: ' Collabs', action: 'message' },
                    { text: ' New Drop', action: 'message' },
                ],
                typing_indicator: true,
                pass_user_context: true,
            },
        },
    },
];

export const PRESET_CATEGORIES = [
    { id: 'all', label: 'All Presets' },
    { id: 'ecommerce', label: 'E-commerce' },
    { id: 'marketing', label: 'Marketing' },
    { id: 'support', label: 'Support' },
    { id: 'sales', label: 'Sales' },
    { id: 'general', label: 'General' },
];

/**
 * Get a preset by ID
 */
export function getPresetById(id) {
    return WIDGET_PRESETS.find((preset) => preset.id === id);
}

/**
 * Apply a preset to form data, merging with defaults
 */
export function applyPreset(preset, currentFormData = {}) {
    if (!preset?.config) return currentFormData;

    return {
        ...currentFormData,
        display_name: preset.config.display_name || currentFormData.display_name,
        behavior: {
            ...currentFormData.behavior,
            ...preset.config.behavior,
            // Support legacy preset format with separate triggers
            ...(preset.config.triggers || {}),
        },
        appearance: {
            ...currentFormData.appearance,
            ...preset.config.appearance,
        },
        display: {
            ...currentFormData.display,
            ...preset.config.display,
        },
        engagement: {
            ...currentFormData.engagement,
            ...preset.config.engagement,
        },
    };
}
