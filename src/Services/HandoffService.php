<?php
declare(strict_types=1);
/**
 * Handoff Service
 * 
 * Manages agent-to-agent conversation transfers with full context preservation.
 * 
 * @package SWC\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Models\ChatSession;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handoff Service
 */
class HandoffService {
    
    /**
     * Execute a handoff from one agent to another
     * 
     * @param ChatSession $session Current session
     * @param ChatAgent $targetAgent Agent to transfer to
     * @param string $reason Reason for transfer
     * @param string $contextSummary Summary of conversation
     * @return array Handoff result
     */
    public static function executeHandoff(
        ChatSession $session,
        ChatAgent $targetAgent,
        string $reason,
        string $contextSummary = ''
    ): array {
        // Get current agent
        $currentAgent = $session->getAgent();
        
        // Build handoff record
        $handoffRecord = [
            'from_agent_id' => $currentAgent ? $currentAgent->id : null,
            'from_agent_name' => $currentAgent ? $currentAgent->name : 'Unknown',
            'to_agent_id' => $targetAgent->id,
            'to_agent_name' => $targetAgent->name,
            'reason' => $reason,
            'context_summary' => $contextSummary,
            'message_count' => count($session->getMessages()),
            'timestamp' => current_time('mysql'),
        ];
        
        // Get existing handoff history
        $metadata = $session->metadata;
        $handoffHistory = $metadata['handoff_history'] ?? [];
        $handoffHistory[] = $handoffRecord;
        $metadata['handoff_history'] = $handoffHistory;
        
        // Update session
        $session->metadata = $metadata;
        $session->agentDbId = $targetAgent->id;
        
        // Add system message about the transfer
        $session->addMessage('system', sprintf(
            "[Conversation transferred from %s to %s]\nReason: %s",
            $handoffRecord['from_agent_name'],
            $targetAgent->name,
            $reason
        ));
        
        // Save session
        $session->save();
        
        return [
            'success' => true,
            'handoff' => $handoffRecord,
            'session_id' => $session->sessionId,
            'new_agent' => [
                'id' => $targetAgent->id,
                'agent_id' => $targetAgent->agentId,
                'name' => $targetAgent->name,
                'avatar' => $targetAgent->avatar,
                'welcome_message' => $targetAgent->config->welcomeMessage ?? '',
            ],
            'transition_message' => self::buildTransitionMessage($currentAgent, $targetAgent, $contextSummary)
        ];
    }
    
    /**
     * Get handoff history for a session
     */
    public static function getHandoffHistory(ChatSession $session): array {
        return $session->metadata['handoff_history'] ?? [];
    }
    
    /**
     * Build transition message for the new agent
     */
    protected static function buildTransitionMessage(
        ?ChatAgent $fromAgent,
        ChatAgent $toAgent,
        string $contextSummary
    ): string {
        $fromName = $fromAgent ? $fromAgent->name : 'the previous assistant';
        
        $message = sprintf(
            "Hello! I'm %s and I'll be helping you now. %s has filled me in on your conversation.",
            $toAgent->name,
            $fromName
        );
        
        if (!empty($contextSummary)) {
            $message .= "\n\nI understand you need help with: " . $contextSummary;
        }
        
        $message .= "\n\nHow can I assist you?";
        
        return $message;
    }
    
    /**
     * Get available agents for handoff (excluding current)
     */
    public static function getAvailableForHandoff(?int $currentAgentId = null): array {
        $agents = ChatAgent::all(true); // Active only
        
        if ($currentAgentId) {
            $agents = array_filter($agents, fn($a) => $a->id !== $currentAgentId);
        }
        
        return array_map(function($agent) {
            return [
                'id' => $agent->id,
                'agent_id' => $agent->agentId,
                'name' => $agent->name,
                'description' => $agent->description,
                'avatar' => $agent->avatar,
            ];
        }, $agents);
    }
    
    /**
     * Check if session has been handed off
     */
    public static function hasBeenHandedOff(ChatSession $session): bool {
        $history = $session->metadata['handoff_history'] ?? [];
        return count($history) > 0;
    }
    
    /**
     * Get the original agent for a session
     */
    public static function getOriginalAgent(ChatSession $session): ?ChatAgent {
        $history = $session->metadata['handoff_history'] ?? [];
        
        if (empty($history)) {
            return $session->getAgent();
        }
        
        $firstHandoff = $history[0];
        $originalId = $firstHandoff['from_agent_id'] ?? null;
        
        return $originalId ? ChatAgent::find($originalId) : null;
    }
}
