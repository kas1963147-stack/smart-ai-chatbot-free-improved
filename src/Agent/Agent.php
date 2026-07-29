<?php
declare(strict_types=1);


/**
 * Agent - Legacy Compatibility Layer
 * 
 * This class provides backward compatibility for code that directly
 * instantiates Agent. New code should use AgentFactory instead.
 * 
 * @package Quarksol\SmartChatbot\Agent
 * @deprecated Use AgentFactory::create() or AgentFactory::getDefault() instead
 */

namespace Quarksol\SmartChatbot\Agent;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Legacy Agent Class
 * 
 * Extends NeuronAgent directly for backward compatibility.
 * All agents should now be configured via the agents/ folder
 * and instantiated via AgentFactory.
 * 
 * @deprecated
 */
class Agent extends NeuronAgent
{
    /**
     * Get default instructions
     * 
     * NOTE: We inject skills and system tool guidelines so load_skill works
     */
    protected function getDefaultInstructions(): string
    {
        $settings = function_exists('get_option') 
            ? \Quarksol\SmartChatbot\Config\ChatbotConfig::settings() 
            : [];
        
        $botName = $settings['bot_name'] ?? 'AI Assistant';
        
        $prompt = $this->buildSystemPrompt(
            background: [
                "You are {$botName}, a helpful AI assistant.",
            ],
            steps: [
                "1. Understand the user's request clearly.",
                "2. Determine if tools are needed to complete the request.",
                "3. Use appropriate tools to gather information or perform actions.",
                "4. Provide a helpful and accurate response.",
            ],
            output: [
                "Be friendly, helpful, and concise.",
                "Use markdown formatting for better readability.",
                "Always confirm before making changes or taking actions.",
            ]
        );
        
        // Add skill summaries for on-demand loading
        $skillSummaries = \Quarksol\SmartChatbot\Skills\SkillRegistry::getSummariesForPrompt();
        if (!empty($skillSummaries)) {
            $prompt .= "\n\n" . $skillSummaries;
        }
        
        // Add system tool guidelines
        $guidelines = \Quarksol\SmartChatbot\Config\SystemToolRegistry::getGuidelines();
        if (!empty($guidelines)) {
            $prompt .= "\n\n" . $guidelines;
        }
        
        return $prompt;
    }
    
    /**
     * Get default tools
     * 
     * Returns all available toolkit tools.
     */
    protected function getDefaultTools(): array
    {
        // Use the toolkit loader if available
        if (function_exists('get_all_toolkit_tools')) {
            return \get_all_toolkit_tools();
        }
        
        return [];
    }
}

