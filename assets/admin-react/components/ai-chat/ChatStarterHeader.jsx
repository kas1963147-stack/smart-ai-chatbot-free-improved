/**
 * Chat Starter Header - Metronic v9 AI Style
 * Welcome header shown when no messages exist
 * Shows dynamic greeting based on selected agent
 */
import * as React from 'react';

export function ChatStarterHeader({ selectedAgent }) {
    // Dynamic greeting based on agent type
    const getGreeting = () => {
        if (!selectedAgent) {
            return {
                title: "How can I help you today?",
                subtitle: "I'm here to assist you with any questions or tasks."
            };
        }

        const name = selectedAgent.name || 'Assistant';
        const agentId = (selectedAgent.agent_id || '').toLowerCase();

        // Agent-specific greetings
        if (agentId.includes('seo') || name.toLowerCase().includes('seo')) {
            return {
                title: `${name} Ready`,
                subtitle: "Let's optimize your content for search engines!"
            };
        }
        if (agentId.includes('content') || name.toLowerCase().includes('content') || name.toLowerCase().includes('editor')) {
            return {
                title: `${name} Ready`,
                subtitle: "Let's create amazing content together!"
            };
        }
        if (agentId.includes('support') || name.toLowerCase().includes('support') || name.toLowerCase().includes('customer')) {
            return {
                title: `${name} Ready`,
                subtitle: "I'll help you take care of your customers!"
            };
        }
        if (agentId.includes('order') || name.toLowerCase().includes('order')) {
            return {
                title: `${name} Ready`,
                subtitle: "Let's manage your orders efficiently!"
            };
        }
        if (agentId.includes('sales') || name.toLowerCase().includes('sales')) {
            return {
                title: `${name} Ready`,
                subtitle: "Let's boost your sales today!"
            };
        }

        return {
            title: `${name} Ready`,
            subtitle: selectedAgent.description || "I'm here to assist you with any questions or tasks."
        };
    };

    const { title, subtitle } = getGreeting();

    return (
        <div className="flex flex-col items-center justify-center gap-6 mb-8">
            <div className="flex items-center justify-center size-16 bg-primary/10 dark:bg-primary/10 rounded-2xl">
                {selectedAgent ? (
                    <span className="text-3xl">
                        {selectedAgent.name?.charAt(0)?.toUpperCase() || ''}
                    </span>
                ) : (
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="size-8 text-primary"
                    >
                        <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Z" />
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                        <line x1="12" x2="12" y1="19" y2="22" />
                    </svg>
                )}
            </div>

            <div className="flex flex-col items-center gap-2 text-center">
                <h1 className="text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    {title}
                </h1>
                <p className="text-gray-500 dark:text-slate-400 text-lg max-w-md">
                    {subtitle}
                </p>
            </div>
        </div>
    );
}
