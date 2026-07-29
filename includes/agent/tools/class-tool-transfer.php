<?php
/**
 * Transfer Tool
 * 
 * Allows AI agents to transfer conversations to other agents.
 * Implements agent-to-agent handoff with context preservation.
 * 
 * @package SWC\Tools
 */

namespace Quarksol\SmartChatbot\Agent\Tools;

use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Models\ChatSession;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transfer Tool
 * 
 * Enables seamless handoff between AI agents.
 */
class TransferTool extends Tool {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'transfer_to_agent',
            'Transfer the current conversation to another AI agent. Use this when the user needs help from a specialist agent (e.g., transfer to Support Agent for order issues, or to Booking Agent for appointments).',
            [
                new ToolProperty(
                    'target_agent_id',
                    'string',
                    'The agent_id of the agent to transfer to (e.g., "support_agent", "booking_agent")',
                    true
                ),
                new ToolProperty(
                    'reason',
                    'string',
                    'Brief reason for the transfer to provide context to the new agent',
                    true
                ),
                new ToolProperty(
                    'context_summary',
                    'string',
                    'Summary of the conversation so far and what the user needs help with',
                    false
                ),
            ]
        );
    }
    
    /**
     * Execute the transfer
     * 
     * @param string $target_agent_id Target agent slug
     * @param string $reason Reason for transfer
     * @param string $context_summary Optional context summary
     * @return array Transfer result
     */
    public function __invoke(
        string $target_agent_id, 
        string $reason, 
        string $context_summary = ''
    ): array {
        // Find target agent
        $targetAgent = ChatAgent::findBySlug($target_agent_id);
        
        if (!$targetAgent) {
            return [
                'success' => false,
                'error' => "Agent '{$target_agent_id}' not found. Please check the agent ID.",
                'available_agents' => $this->getAvailableAgents()
            ];
        }
        
        if (!$targetAgent->isActive) {
            return [
                'success' => false,
                'error' => "Agent '{$targetAgent->name}' is currently unavailable.",
                'available_agents' => $this->getAvailableAgents()
            ];
        }
        
        // Get current session context
        $currentContext = $this->getCurrentContext();
        
        // Build handoff data
        $handoff = [
            'from_agent_id' => $currentContext['current_agent_id'] ?? null,
            'to_agent_id' => $targetAgent->id,
            'to_agent_slug' => $target_agent_id,
            'to_agent_name' => $targetAgent->name,
            'reason' => $reason,
            'context_summary' => $context_summary,
            'timestamp' => current_time('mysql'),
        ];
        
        return [
            'success' => true,
            'handoff' => $handoff,
            'message' => $this->buildHandoffMessage($targetAgent, $reason),
            'new_agent' => [
                'id' => $targetAgent->id,
                'agent_id' => $targetAgent->agentId,
                'name' => $targetAgent->name,
                'avatar' => $targetAgent->avatar,
                'welcome_message' => $targetAgent->config->welcomeMessage ?? '',
            ]
        ];
    }
    
    /**
     * Get list of available agents for transfer
     */
    protected function getAvailableAgents(): array {
        $agents = ChatAgent::all(true); // Active only
        
        return array_map(function($agent) {
            return [
                'agent_id' => $agent->agentId,
                'name' => $agent->name,
                'description' => $agent->description,
            ];
        }, $agents);
    }
    
    /**
     * Get current context from thread
     */
    protected function getCurrentContext(): array {
        // This will be populated by the agent runtime
        return [
            'session_id' => null,
            'current_agent_id' => null,
        ];
    }
    
    /**
     * Build handoff transition message
     */
    protected function buildHandoffMessage(ChatAgent $targetAgent, string $reason): string {
        return sprintf(
            "I'm transferring you to our %s. %s\n\n" .
            "Please wait a moment while %s takes over...",
            $targetAgent->name,
            ucfirst($reason),
            $targetAgent->name
        );
    }
}
