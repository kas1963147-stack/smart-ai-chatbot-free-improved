/**
 * Introduction Popup Component
 *
 * A clean, professional welcome popup that appears once after plugin activation.
 * Shows plugin features and guides users to get started.
 *
 * @version 1.1.0
 * @package
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import Modal from './Modal';
import { Button } from '../ui';

/* ---------- SVG Icon Components ---------- */
const IconBot = () => (
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="11" width="18" height="10" rx="2" /><circle cx="12" cy="5" r="2" /><path d="M12 7v4" />
        <line x1="8" y1="16" x2="8" y2="16" strokeWidth="2.5" /><line x1="16" y1="16" x2="16" y2="16" strokeWidth="2.5" />
    </svg>
);

const IconAgents = () => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="11" width="18" height="10" rx="2" /><circle cx="12" cy="5" r="2" /><path d="M12 7v4" />
    </svg>
);

const IconToolkit = () => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
    </svg>
);

const IconKnowledge = () => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        <path d="M4 19.5A2.5 2.5 0 016.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" />
    </svg>
);

const IconChat = () => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
    </svg>
);

const IconProviders = () => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2L2 7l10 5 10-5-10-5z" /><path d="M2 17l10 5 10-5" /><path d="M2 12l10 5 10-5" />
    </svg>
);

const IconAnalytics = () => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        <path d="M18 20V10" /><path d="M12 20V4" /><path d="M6 20v-6" />
    </svg>
);

const IconCheck = () => (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <polyline points="20 6 9 17 4 12" />
    </svg>
);

const FEATURES = [
    {
        icon: IconAgents,
        title: __('AI-Powered Agents', 'agentflow-ai'),
        description: __('Create intelligent chatbot agents trained on your business data with support for multiple AI providers.', 'agentflow-ai'),
    },
    {
        icon: IconToolkit,
        title: __('Rich Toolkits', 'agentflow-ai'),
        description: __('Equip your agents with powerful tools — product search, order tracking, booking, and more.', 'agentflow-ai'),
    },
    {
        icon: IconKnowledge,
        title: __('Knowledge Base', 'agentflow-ai'),
        description: __('Feed your agents custom knowledge from pages, posts, PDFs, and URLs for accurate answers.', 'agentflow-ai'),
    },
    {
        icon: IconChat,
        title: __('Chat Widgets', 'agentflow-ai'),
        description: __('Deploy beautiful, customizable chat widgets anywhere on your site with full design control.', 'agentflow-ai'),
    },
    {
        icon: IconProviders,
        title: __('Multi-Provider Support', 'agentflow-ai'),
        description: __('Connect to OpenAI, Claude, Gemini, Azure, Groq, OpenRouter and more — all in one plugin.', 'agentflow-ai'),
    },
    {
        icon: IconAnalytics,
        title: __('Analytics & Insights', 'agentflow-ai'),
        description: __('Track conversations, monitor performance, and gain insights to improve your customer experience.', 'agentflow-ai'),
    },
];

const QUICK_START_STEPS = [
    {
        step: '1',
        title: __('Connect AI Provider', 'agentflow-ai'),
        description: __('Go to Settings and add your API key from OpenAI, Claude, or any supported provider.', 'agentflow-ai'),
    },
    {
        step: '2',
        title: __('Create Your First Agent', 'agentflow-ai'),
        description: __('Head to AI Agents, click "Create", and choose a template or build from scratch.', 'agentflow-ai'),
    },
    {
        step: '3',
        title: __('Deploy a Widget', 'agentflow-ai'),
        description: __('Go to Widgets, create a chat widget, assign your agent and it goes live instantly.', 'agentflow-ai'),
    },
];

/**
 * IntroductionPopup Component
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen
 * @param {Function} props.onClose
 */
export default function IntroductionPopup({ isOpen, onClose }) {
    const [currentSlide, setCurrentSlide] = useState(0);
    const [animating, setAnimating] = useState(false);

    const totalSlides = 3;

    useEffect(() => {
        if (isOpen) {
            setCurrentSlide(0);
        }
    }, [isOpen]);

    const goToSlide = (index) => {
        if (animating) return;
        setAnimating(true);
        setTimeout(() => {
            setCurrentSlide(index);
            setAnimating(false);
        }, 200);
    };

    const handleNext = () => {
        if (currentSlide < totalSlides - 1) {
            goToSlide(currentSlide + 1);
        } else {
            onClose();
        }
    };

    const handlePrev = () => {
        if (currentSlide > 0) {
            goToSlide(currentSlide - 1);
        }
    };

    const renderSlideContent = () => {
        switch (currentSlide) {
            case 0:
                return (
                    <div className="swc-intro__welcome" style={{
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        textAlign: 'center',
                        padding: '20px 10px',
                        opacity: animating ? 0 : 1,
                        transform: animating ? 'translateY(10px)' : 'translateY(0)',
                        transition: 'opacity 0.3s ease, transform 0.3s ease',
                    }}>
                        {/* Logo Icon */}
                        <div style={{
                            width: '80px',
                            height: '80px',
                            borderRadius: '24px',
                            background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            marginBottom: '24px',
                            boxShadow: '0 20px 40px rgba(99, 102, 241, 0.3)',
                        }}>
                            <IconBot />
                        </div>

                        <h2 style={{
                            fontSize: '28px',
                            fontWeight: '700',
                            margin: '0 0 12px 0',
                            background: 'linear-gradient(135deg, #1e293b 0%, #6366f1 50%, #8b5cf6 100%)',
                            WebkitBackgroundClip: 'text',
                            WebkitTextFillColor: 'transparent',
                            backgroundClip: 'text',
                            lineHeight: '1.2',
                        }}>
                            {__('Welcome to Smart Chatbot', 'agentflow-ai')}
                        </h2>

                        <p style={{
                            fontSize: '15px',
                            color: '#64748b',
                            maxWidth: '420px',
                            lineHeight: '1.7',
                            margin: '0 0 28px 0',
                        }}>
                            {__('The most powerful AI chatbot plugin for WordPress & WooCommerce. Create intelligent agents, deploy beautiful chat widgets, and delight your customers — all in minutes.', 'agentflow-ai')}
                        </p>

                        {/* Highlight Stats */}
                        <div style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(3, 1fr)',
                            gap: '16px',
                            width: '100%',
                            maxWidth: '420px',
                        }}>
                            {[
                                { value: '20+', label: __('Agent Templates', 'agentflow-ai') },
                                { value: '50+', label: __('Built-in Tools', 'agentflow-ai') },
                                { value: '6+', label: __('AI Providers', 'agentflow-ai') },
                            ].map((stat, i) => (
                                <div key={i} style={{
                                    background: 'linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%)',
                                    borderRadius: '16px',
                                    padding: '16px 12px',
                                    border: '1px solid #e2e8f0',
                                }}>
                                    <div style={{
                                        fontSize: '24px',
                                        fontWeight: '800',
                                        background: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                                        WebkitBackgroundClip: 'text',
                                        WebkitTextFillColor: 'transparent',
                                        backgroundClip: 'text',
                                    }}>
                                        {stat.value}
                                    </div>
                                    <div style={{
                                        fontSize: '12px',
                                        color: '#94a3b8',
                                        fontWeight: '500',
                                        marginTop: '4px',
                                    }}>
                                        {stat.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                );

            case 1:
                return (
                    <div className="swc-intro__features" style={{
                        padding: '10px',
                        opacity: animating ? 0 : 1,
                        transform: animating ? 'translateY(10px)' : 'translateY(0)',
                        transition: 'opacity 0.3s ease, transform 0.3s ease',
                    }}>
                        <h3 style={{
                            fontSize: '20px',
                            fontWeight: '700',
                            margin: '0 0 6px 0',
                            textAlign: 'center',
                            color: '#1e293b',
                        }}>
                            {__('Everything You Need', 'agentflow-ai')}
                        </h3>
                        <p style={{
                            fontSize: '13px',
                            color: '#94a3b8',
                            textAlign: 'center',
                            margin: '0 0 20px 0',
                        }}>
                            {__('Packed with features to supercharge your customer support', 'agentflow-ai')}
                        </p>

                        <div style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(2, 1fr)',
                            gap: '12px',
                        }}>
                            {FEATURES.map((feature, idx) => {
                                const FeatureIcon = feature.icon;
                                return (
                                    <div key={idx} style={{
                                        background: '#ffffff',
                                        border: '1px solid #e2e8f0',
                                        borderRadius: '14px',
                                        padding: '16px',
                                        transition: 'all 0.2s ease',
                                        cursor: 'default',
                                    }}
                                        onMouseEnter={(e) => {
                                            e.currentTarget.style.borderColor = '#c7d2fe';
                                            e.currentTarget.style.boxShadow = '0 4px 12px rgba(99, 102, 241, 0.1)';
                                            e.currentTarget.style.transform = 'translateY(-2px)';
                                        }}
                                        onMouseLeave={(e) => {
                                            e.currentTarget.style.borderColor = '#e2e8f0';
                                            e.currentTarget.style.boxShadow = 'none';
                                            e.currentTarget.style.transform = 'translateY(0)';
                                        }}
                                    >
                                        <span style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            width: '36px',
                                            height: '36px',
                                            borderRadius: '10px',
                                            background: 'linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%)',
                                            color: '#6366f1',
                                            marginBottom: '10px',
                                        }}>
                                            <FeatureIcon />
                                        </span>
                                        <h4 style={{
                                            fontSize: '13px',
                                            fontWeight: '600',
                                            color: '#1e293b',
                                            margin: '0 0 4px 0',
                                        }}>
                                            {feature.title}
                                        </h4>
                                        <p style={{
                                            fontSize: '12px',
                                            color: '#94a3b8',
                                            margin: 0,
                                            lineHeight: '1.5',
                                        }}>
                                            {feature.description}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                );

            case 2:
                return (
                    <div className="swc-intro__quickstart" style={{
                        padding: '20px 10px',
                        opacity: animating ? 0 : 1,
                        transform: animating ? 'translateY(10px)' : 'translateY(0)',
                        transition: 'opacity 0.3s ease, transform 0.3s ease',
                    }}>
                        <h3 style={{
                            fontSize: '20px',
                            fontWeight: '700',
                            margin: '0 0 6px 0',
                            textAlign: 'center',
                            color: '#1e293b',
                        }}>
                            {__('Quick Start Guide', 'agentflow-ai')}
                        </h3>
                        <p style={{
                            fontSize: '13px',
                            color: '#94a3b8',
                            textAlign: 'center',
                            margin: '0 0 28px 0',
                        }}>
                            {__('Get up and running in just 3 simple steps', 'agentflow-ai')}
                        </p>

                        <div style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: '16px',
                            maxWidth: '420px',
                            margin: '0 auto',
                        }}>
                            {QUICK_START_STEPS.map((item, idx) => (
                                <div key={idx} style={{
                                    display: 'flex',
                                    gap: '16px',
                                    alignItems: 'flex-start',
                                    background: '#ffffff',
                                    border: '1px solid #e2e8f0',
                                    borderRadius: '14px',
                                    padding: '20px',
                                    transition: 'all 0.2s ease',
                                }}
                                    onMouseEnter={(e) => {
                                        e.currentTarget.style.borderColor = '#c7d2fe';
                                        e.currentTarget.style.boxShadow = '0 4px 12px rgba(99, 102, 241, 0.1)';
                                    }}
                                    onMouseLeave={(e) => {
                                        e.currentTarget.style.borderColor = '#e2e8f0';
                                        e.currentTarget.style.boxShadow = 'none';
                                    }}
                                >
                                    <div style={{
                                        width: '40px',
                                        height: '40px',
                                        borderRadius: '12px',
                                        background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        color: '#ffffff',
                                        fontSize: '16px',
                                        fontWeight: '700',
                                        flexShrink: 0,
                                    }}>
                                        {item.step}
                                    </div>
                                    <div>
                                        <h4 style={{
                                            fontSize: '14px',
                                            fontWeight: '600',
                                            color: '#1e293b',
                                            margin: '0 0 4px 0',
                                        }}>
                                            {item.title}
                                        </h4>
                                        <p style={{
                                            fontSize: '13px',
                                            color: '#94a3b8',
                                            margin: 0,
                                            lineHeight: '1.6',
                                        }}>
                                            {item.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Ready message */}
                        <div style={{
                            marginTop: '24px',
                            textAlign: 'center',
                            padding: '16px',
                            background: 'linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%)',
                            borderRadius: '12px',
                            border: '1px solid #bbf7d0',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: '8px',
                        }}>
                            <IconCheck />
                            <p style={{
                                fontSize: '13px',
                                color: '#16a34a',
                                fontWeight: '600',
                                margin: 0,
                            }}>
                                {__("You're all set! Click \"Let's Go\" to start building.", 'agentflow-ai')}
                            </p>
                        </div>
                    </div>
                );

            default:
                return null;
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title=""
            size="lg"
            showCloseButton={true}
        >
            <div className="swc-intro" style={{ minHeight: '400px' }}>
                { /* Slide Content */}
                <div className="swc-intro__content">
                    {renderSlideContent()}
                </div>

                { /* Dot Indicators */}
                <div style={{
                    display: 'flex',
                    justifyContent: 'center',
                    gap: '8px',
                    margin: '24px 0 16px',
                }}>
                    {Array.from({ length: totalSlides }).map((_, idx) => (
                        <button
                            key={idx}
                            onClick={() => goToSlide(idx)}
                            style={{
                                width: currentSlide === idx ? '24px' : '8px',
                                height: '8px',
                                borderRadius: '4px',
                                border: 'none',
                                background: currentSlide === idx
                                    ? 'linear-gradient(135deg, #6366f1, #8b5cf6)'
                                    : '#e2e8f0',
                                cursor: 'pointer',
                                transition: 'all 0.3s ease',
                                padding: 0,
                            }}
                            aria-label={`Go to slide ${idx + 1}`}
                        />
                    ))}
                </div>

                { /* Footer Navigation */}
                <div style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    paddingTop: '12px',
                    borderTop: '1px solid #f1f5f9',
                }}>
                    <div>
                        {currentSlide > 0 && (
                            <Button variant="ghost" onClick={handlePrev}>
                                {__('Back', 'agentflow-ai')}
                            </Button>
                        )}
                    </div>

                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        {currentSlide === 0 && (
                            <Button variant="ghost" onClick={onClose}>
                                {__('Skip', 'agentflow-ai')}
                            </Button>
                        )}
                        <Button
                            variant="primary"
                            onClick={handleNext}
                            style={currentSlide === totalSlides - 1 ? {
                                background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
                                border: 'none',
                                boxShadow: '0 4px 14px rgba(99, 102, 241, 0.4)',
                            } : {}}
                        >
                            {currentSlide === totalSlides - 1
                                ? __("Let's Go", 'agentflow-ai')
                                : __('Next', 'agentflow-ai')}
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}

IntroductionPopup.propTypes = {
    isOpen: PropTypes.bool,
    onClose: PropTypes.func.isRequired,
};
