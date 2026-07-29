<?php
declare(strict_types=1);
/**
 * SupervisorService
 *
 * Implements the "Supervisor" (Manager Agent) orchestration mode.
 *
 * In this mode, the Primary agent in the group acts as a Manager.
 * Instead of following hardcoded workflow steps, the Manager uses
 * AI tool-calling to autonomously decide which Worker Agent to
 * delegate tasks to and when.
 *
 * Flow:
 *   1. The Manager agent receives the user message.
 *   2. It is given one "delegate_to_<agent>" tool per Worker Agent.
 *   3. The NeuronAI framework's built-in tool-calling loop handles
 *      the back-and-forth: Manager calls a tool → we execute the
 *      Worker → return result → Manager decides next step.
 *   4. When the Manager is satisfied, it produces a final text reply.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Agent\NeuronAgent;
use Quarksol\SmartChatbot\Models\AgentGroup;
use Quarksol\SmartChatbot\Models\ChatAgent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

if (!defined('ABSPATH')) {
    exit;
}

class SupervisorService
{
    /**
     * Maximum number of delegation rounds to prevent infinite loops.
     */
    protected const MAX_DELEGATION_ROUNDS = 8;

    /**
     * Execute the Supervisor orchestration.
     *
     * @param AgentGroup $group   The agent group (Primary = Manager)
     * @param string     $message The user's message
     * @return string The Manager's final response to the user
     */
    public static function execute(AgentGroup $group, string $message, string $sessionId = ''): string
    {
        $members = $group->getMembers();
        if (empty($members)) {
            return 'No agents are available in this team.';
        }

        try {
            Logger::info('SupervisorService: Starting Workflow engine', [
                'group_id' => $group->groupId,
                'session_id' => $sessionId,
                'message_length' => strlen($message),
            ]);
            
            // Derive a unique reliable workflow ID.
            $workflowId = 'supervisor_wf_' . $group->groupId . '_' . ($sessionId ?: md5($message));
            $persistence = new \Quarksol\SmartChatbot\Workflows\Persistence\TransientWorkflowPersistence(86400 * 7);
            
            // Separate transient key for task history (safe to serialize — just arrays)
            $historyKey = 'supervisor_history_' . md5($workflowId);
            $previousHistory = get_transient($historyKey) ?: [];
            $previousUserMessage = get_transient($historyKey . '_msg') ?: '';

            // Always start a fresh workflow (avoids __PHP_Incomplete_Class on resume)
            // but inject previous context if this is a follow-up after an INTERRUPT
            if (!empty($previousHistory)) {
                Logger::info('SupervisorService: Resuming with previous context', [
                    'workflow_id' => $workflowId,
                    'previous_tasks' => count($previousHistory),
                ]);
                
                // Build the full message with context
                $fullMessage = $previousUserMessage . "\n\n[User follow up]: " . $message;
                
                $state = new \NeuronAI\Workflow\WorkflowState();
                $state->set('user_message', $fullMessage);
                $state->set('group', $group);
                $state->set('task_history', $previousHistory);
                
                // Clean up the old persistence data
                $persistence->delete($workflowId);
                delete_transient($historyKey);
                delete_transient($historyKey . '_msg');
            } else {
                Logger::info('SupervisorService: Initiating fresh workflow context', ['workflow_id' => $workflowId]);
                $state = new \NeuronAI\Workflow\WorkflowState();
                $state->set('user_message', $message);
                $state->set('group', $group);
                $state->set('task_history', []);
            }

            $workflow = new \Quarksol\SmartChatbot\Workflows\Supervisor\SupervisorWorkflow($state, $persistence, $workflowId);
            $resultState = $workflow->start(false)->getResult();

            // Clean up any leftover history transients on successful completion
            delete_transient($historyKey);
            delete_transient($historyKey . '_msg');

            // 2. Return the Manager's final finalized response
            $finalContent = $resultState->get('final_response') ?? 'The manager completed the request but returned no text.';

            Logger::info('SupervisorService: Workflow completed', [
                'group_id'        => $group->groupId,
                'response_length' => strlen($finalContent),
            ]);

            return $finalContent;
        } catch (\NeuronAI\Workflow\WorkflowInterrupt $e) {
            // The workflow encountered a human-in-the-loop interruption pause
            Logger::info('SupervisorService: Workflow Interrupted for User Feedback', [
                'workflow_id' => isset($workflowId) ? $workflowId : '',
                'history_key' => isset($historyKey) ? $historyKey : 'NOT SET',
            ]);
            
            // Save task history safely (plain arrays, no objects that fail to serialize)
            try {
                $interruptState = $e->getState();
                $taskHistory = $interruptState ? ($interruptState->get('task_history') ?? []) : [];
                $userMsg = $interruptState ? ($interruptState->get('user_message') ?? $message) : $message;
                
                Logger::info('SupervisorService: Interrupt state extracted', [
                    'has_state' => ($interruptState !== null),
                    'task_history_count' => count($taskHistory),
                    'user_msg_length' => strlen($userMsg),
                ]);
                
                if (isset($historyKey) && !empty($taskHistory)) {
                    $saved1 = set_transient($historyKey, $taskHistory, 86400);
                    $saved2 = set_transient($historyKey . '_msg', $userMsg, 86400);
                    Logger::info('SupervisorService: Transients saved', [
                        'history_key' => $historyKey,
                        'history_saved' => $saved1,
                        'msg_saved' => $saved2,
                    ]);
                } else {
                    Logger::warning('SupervisorService: Could not save interrupt context', [
                        'history_key_set' => isset($historyKey),
                        'task_history_empty' => empty($taskHistory),
                    ]);
                }
            } catch (\Throwable $saveError) {
                Logger::error('SupervisorService: Failed to save interrupt state', [
                    'error' => $saveError->getMessage(),
                ]);
            }
            
            $interruptData = $e->getData();
            return $interruptData['message'] ?? 'Please respond to continue the workflow...';
        } catch (\Throwable $e) {
            Logger::error('SupervisorService: execution failed', [
                'group_id' => $group->groupId,
                'error'    => $e->getMessage(),
            ]);
            return "I encountered an error coordinating the team. Please try again.";
        }
    }
}
