<?php
declare(strict_types=1);
/**
 * Orchestrator Service
 * 
 * Central orchestration engine for multi-agent coordination.
 * Handles routing, sequential execution, parallel processing,
 * and workflow step execution.
 * 
 * @package SWC\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Models\AgentGroup;
use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Models\ChatSession;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orchestration Result
 * 
 * Standard result object returned by handleRequest().
 */
class OrchestrationResult
{
    public string $content;
    public string $mode;
    public array $details;

    public function __construct(string $content, string $mode = 'single', array $details = [])
    {
        $this->content = $content;
        $this->mode = $mode;
        $this->details = $details;
    }
}

/**
 * Orchestration Plan
 * 
 * Represents the execution plan for a multi-agent request.
 */
class OrchestrationPlan
{

    /** The mode of orchestration */
    public string $mode;

    /** Selected agent(s) for execution */
    public array $agents = [];

    /** The group (if applicable) */
    public ?AgentGroup $group = null;

    /** Routing decision details */
    public array $routingDetails = [];

    /**
     * Create a single-agent plan
     */
    public static function singleAgent(ChatAgent $agent): self
    {
        $plan = new self();
        $plan->mode = 'single';
        $plan->agents = [$agent];
        return $plan;
    }

    /**
     * Create a group plan
     */
    public static function forGroup(AgentGroup $group, array $agents): self
    {
        $plan = new self();
        $plan->mode = $group->orchestrationMode;
        $plan->group = $group;
        $plan->agents = $agents;
        return $plan;
    }
}

/**
 * Orchestrator Service
 * 
 * Handles multi-agent orchestration including:
 * - Workflow mode: Execute defined workflow steps (instruction → agent → approval → condition)
 * - Router mode: Select best agent based on message analysis
 * - Sequential mode: Run agents in order, passing context
 * - Parallel mode: Run all agents simultaneously
 */
class OrchestratorService
{

    /**
     * Handle a chat request for a group.
     *
     * This is the main entry point called from the chat pipeline.
     * It checks for workflow steps first, then falls back to
     * orchestration mode-based execution.
     *
     * @param PageContext|null $context  The page context (nullable)
     * @param string          $message  The user message
     * @param AgentGroup      $group    The agent group
     * @param string          $sessionId The chat session ID
     * @return OrchestrationResult|null Result, or null if nothing executed
     */
    public static function handleRequest($context, string $message, AgentGroup $group, string $sessionId = ''): ?OrchestrationResult
    {
        Logger::info('OrchestratorService::handleRequest', [
            'group_id' => $group->groupId,
            'mode'     => $group->orchestrationMode,
            'has_workflow_steps' => $group->hasWorkflowSteps(),
        ]);

        // ── Priority 1: Execute workflow steps if defined (unless Supervisor mode, which handles them autonomously) ──
        if ($group->hasWorkflowSteps() && $group->orchestrationMode !== AgentGroup::MODE_SUPERVISOR) {
            try {
                $result = self::executeWorkflowSteps($group, $message);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $e) {
                Logger::error('Workflow step execution failed, falling back to orchestration', [
                    'group_id' => $group->groupId,
                    'error'    => $e->getMessage(),
                ]);
                // Fall through to normal orchestration
            }
        }

        // ── Priority 2: Normal orchestration mode ──
        try {
            // Need a session for sequential/parallel execution
            $session = null;
            if (class_exists(ChatSession::class)) {
                $session = new ChatSession();
            }

            switch ($group->orchestrationMode) {
                case AgentGroup::MODE_ROUTER:
                    $agent = self::routeToAgent($group, $message);
                    if ($session) {
                        $response = self::executeAgent($agent, $session, $message);
                    } else {
                        // Fallback without session
                        $neuronAgent = $agent->toNeuronAgent();
                        $chatResponse = $neuronAgent->chat(
                            new \NeuronAI\Chat\Messages\UserMessage($message)
                        );
                        $response = method_exists($chatResponse, 'content')
                            ? $chatResponse->content()
                            : (string) $chatResponse;
                    }
                    return new OrchestrationResult($response, 'router', [
                        'selected_agent' => $agent->agentId,
                    ]);

                case AgentGroup::MODE_SEQUENTIAL:
                    if (!$session) break;
                    $responses = self::executeSequential($group, $session, $message);
                    $combined = self::aggregateResponses($responses, 'combine');
                    return new OrchestrationResult($combined, 'sequential', [
                        'agent_count' => count($responses),
                    ]);

                case AgentGroup::MODE_PARALLEL:
                    if (!$session) break;
                    $responses = self::executeParallel($group, $session, $message);
                    $combined = self::aggregateResponses($responses, 'combine');
                    return new OrchestrationResult($combined, 'parallel', [
                        'agent_count' => count($responses),
                    ]);

                case AgentGroup::MODE_HANDOFF:
                    // Handoff mode: use primary agent
                    $agent = $group->getPrimaryAgent();
                    if (!$agent) {
                        Logger::error('Handoff mode failed: No primary agent in group', ['group_id' => $group->groupId]);
                        return null;
                    }
                    if ($session) {
                        $response = self::executeAgent($agent, $session, $message);
                    } else {
                        $neuronAgent = $agent->toNeuronAgent();
                        $chatResponse = $neuronAgent->chat(
                            new \NeuronAI\Chat\Messages\UserMessage($message)
                        );
                        $response = method_exists($chatResponse, 'content')
                            ? $chatResponse->content()
                            : (string) $chatResponse;
                    }
                    return new OrchestrationResult($response, 'handoff', [
                        'agent_id' => $agent->agentId,
                    ]);

                case AgentGroup::MODE_SUPERVISOR:
                    // Supervisor mode: Manager agent delegates tasks to Workers via tool calling
                    $response = SupervisorService::execute($group, $message, $sessionId);
                    return new OrchestrationResult($response, 'supervisor', [
                        'group_id' => $group->groupId,
                    ]);
            }
        } catch (\Throwable $e) {
            Logger::error('Orchestration mode execution failed', [
                'group_id' => $group->groupId,
                'mode'     => $group->orchestrationMode,
                'error'    => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Execute workflow steps defined in a group's routing_config.
     *
     * Uses WorkflowExecutionService::executeFromSteps() to run the steps
     * and returns the final agent response from the last agent step.
     *
     * @param AgentGroup $group   The group with workflow steps
     * @param string     $message The user message
     * @return OrchestrationResult|null
     */
    public static function executeWorkflowSteps(AgentGroup $group, string $message): ?OrchestrationResult
    {
        $steps = $group->getWorkflowSteps();
        if (empty($steps)) {
            return null;
        }

        Logger::info('Executing workflow steps', [
            'group_id'   => $group->groupId,
            'step_count' => count($steps),
        ]);

        $service = new WorkflowExecutionService();
        $result = $service->executeFromSteps($steps, $message);

        Logger::info('Workflow steps completed', [
            'group_id'        => $group->groupId,
            'status'          => $result['status'],
            'steps_completed' => $result['steps_completed'],
        ]);

        // Extract the final meaningful response from the step outputs
        $responseContent = self::extractWorkflowResponse($result, $message);

        if (empty($responseContent)) {
            // No agent step produced output — fall back to null so normal orchestration runs
            return null;
        }

        return new OrchestrationResult($responseContent, 'workflow', [
            'status'          => $result['status'],
            'steps_completed' => $result['steps_completed'],
            'step_count'      => count($steps),
        ]);
    }

    /**
     * Extract the best response content from a workflow execution result.
     *
     * Walks backward through completed steps to find the last agent response.
     *
     * @param array  $result  The executeFromSteps() result
     * @param string $fallback Fallback message if no agent output found
     * @return string
     */
    protected static function extractWorkflowResponse(array $result, string $fallback = ''): string
    {
        $completedSteps = $result['output']['steps'] ?? [];

        // Walk backward; take the content from the last agent step
        for ($i = count($completedSteps) - 1; $i >= 0; $i--) {
            $step = $completedSteps[$i];
            if ($step['type'] === 'agent' && !empty($step['output']['content'])) {
                return $step['output']['content'];
            }
        }

        // If awaiting approval, provide a message
        if ($result['status'] === 'awaiting_approval') {
            $lastStep = end($completedSteps);
            $prompt = $lastStep['prompt'] ?? 'This action requires approval before proceeding.';
            return " **Approval Required**\n\n" . $prompt;
        }

        return $fallback;
    }

    /**
     * Route message to the best agent in a group
     * 
     * @param AgentGroup $group The agent group
     * @param string $message The user message
     * @return ChatAgent The selected agent
     */
    public static function routeToAgent(AgentGroup $group, string $message): ChatAgent
    {
        $members = $group->getMembers();
        $messageLower = strtolower($message);

        // First, check routing keywords for each member
        foreach ($members as $member) {
            if (!empty($member['routing_keywords'])) {
                $keywords = array_map('trim', explode(',', strtolower($member['routing_keywords'])));

                foreach ($keywords as $keyword) {
                    if (!empty($keyword) && str_contains($messageLower, $keyword)) {
                        $agent = ChatAgent::find((int) $member['agent_db_id']);
                        if ($agent) {
                            Logger::info('Routed to agent by keyword', [
                                'keyword' => $keyword,
                                'agent_id' => $agent->agentId,
                                'group_id' => $group->groupId,
                            ]);
                            return $agent;
                        }
                    }
                }
            }
        }

        // Fall back to primary agent
        $primaryAgent = $group->getPrimaryAgent();
        if ($primaryAgent) {
            Logger::info('Routed to primary agent (no keyword match)', [
                'agent_id' => $primaryAgent->agentId,
                'group_id' => $group->groupId,
            ]);
            return $primaryAgent;
        }

        // Final resort: first agent in group
        if (!empty($members)) {
            $agent = ChatAgent::find((int) $members[0]['agent_db_id']);
            if ($agent) {
                return $agent;
            }
        }

        // STRICT: No absolute fallback to default agent
        Logger::warning('No agent could be routed for group', ['group_id' => $group->groupId]);
        return null;
    }

    /**
     * Execute sequential orchestration
     * 
     * Each agent processes in order, with previous responses as context.
     * 
     * @param AgentGroup $group The agent group
     * @param ChatSession $session The chat session
     * @param string $message The user message
     * @return array Array of responses from each agent
     */
    public static function executeSequential(
        AgentGroup $group,
        ChatSession $session,
        string $message
    ): array {
        $members = $group->getMembers();
        $responses = [];
        $context = $message;

        foreach ($members as $member) {
            $agent = ChatAgent::find((int) $member['agent_db_id']);
            if (!$agent)
                continue;

            // Execute agent with accumulated context
            $response = self::executeAgent($agent, $session, $context);

            $responses[] = [
                'agent_id' => $agent->agentId,
                'agent_name' => $agent->name,
                'response' => $response,
            ];

            // Add response to context for next agent
            $context .= "\n\n[{$agent->name}'s response]: " . $response;
        }

        return $responses;
    }

    /**
     * Execute parallel orchestration
     * 
     * All agents process simultaneously (simulated - PHP is synchronous).
     * In a real async environment, these would run in parallel.
     * 
     * @param AgentGroup $group The agent group
     * @param ChatSession $session The chat session
     * @param string $message The user message
     * @return array Array of responses from each agent
     */
    public static function executeParallel(
        AgentGroup $group,
        ChatSession $session,
        string $message
    ): array {
        $members = $group->getMembers();
        $responses = [];

        foreach ($members as $member) {
            $agent = ChatAgent::find((int) $member['agent_db_id']);
            if (!$agent)
                continue;

            $response = self::executeAgent($agent, $session, $message);

            $responses[] = [
                'agent_id' => $agent->agentId,
                'agent_name' => $agent->name,
                'role' => $member['role'],
                'response' => $response,
            ];
        }

        return $responses;
    }

    /**
     * Execute a single agent
     * 
     * @param ChatAgent $agent The agent to execute
     * @param ChatSession $session The session
     * @param string $message The message
     * @return string The agent response
     */
    protected static function executeAgent(
        ChatAgent $agent,
        ChatSession $session,
        string $message
    ): string {
        try {
            $neuronAgent = $agent->toNeuronAgent();
            AgentContext::set($neuronAgent->getConfig(), $agent->id, $agent->agentId);

            // ==========================================
            // Hook: swc/agent/pre_execute (action)
            // Fired before agent processing begins
            // ==========================================
            \Quarksol\SmartChatbot\Hooks\Hooks::action('swc/agent/pre_execute', $agent, $message, $session);

            // Get session messages for context
            $messages = $session->getMessages();

            // Add new message
            $messages[] = ['role' => 'user', 'content' => $message];

            // ==========================================
            // Hook: swc/query/messages (filter)
            // Modify the message history before sending
            // ==========================================
            $messages = \Quarksol\SmartChatbot\Hooks\Hooks::filter('swc/query/messages', $messages, $agent, $session);

            // Execute (simplified - actual implementation would use streaming)
            $response = $neuronAgent->chat($messages);

            $responseContent = $response ?? '';

            // ==========================================
            // Hook: swc/reply/content (filter)
            // Modify the reply content before returning
            // ==========================================
            $responseContent = \Quarksol\SmartChatbot\Hooks\Hooks::filter('swc/reply/content', $responseContent, $agent, $session);

            // ==========================================
            // Hook: swc/agent/post_execute (action)
            // Fired after agent processing completes
            // ==========================================
            \Quarksol\SmartChatbot\Hooks\Hooks::action('swc/agent/post_execute', $agent, $responseContent, $session);

            return $responseContent;
        } catch (\RuntimeException $e) {
            // Re-throw configuration/strict errors so they reach the UI
            throw $e;
        } catch (\Exception $e) {
            Logger::error('Agent execution failed', [
                'agent_id' => $agent->agentId,
                'error' => $e->getMessage(),
            ]);
            return "I apologize, but I encountered an error. Please try again.";
        } finally {
            AgentContext::clear();
        }
    }

    /**
     * Aggregate responses from multiple agents
     * 
     * @param array $responses Array of agent responses
     * @param string $mode Aggregation mode ('combine', 'best', 'summary')
     * @return string Combined response
     */
    public static function aggregateResponses(array $responses, string $mode = 'combine'): string
    {
        if (empty($responses)) {
            return '';
        }

        switch ($mode) {
            case 'best':
                // Return the longest/most detailed response
                usort($responses, fn($a, $b) => strlen($b['response']) <=> strlen($a['response']));
                return $responses[0]['response'];

            case 'summary':
                // Create a summary header with all responses
                $combined = "Here's what our team found:\n\n";
                foreach ($responses as $response) {
                    $combined .= "**{$response['agent_name']}**: {$response['response']}\n\n";
                }
                return $combined;

            case 'combine':
            default:
                // Combine all responses with agent attribution
                $parts = [];
                foreach ($responses as $response) {
                    if (!empty($response['response'])) {
                        $parts[] = $response['response'];
                    }
                }
                return implode("\n\n---\n\n", $parts);
        }
    }

    /**
     * Get orchestration mode description
     */
    public static function getModeDescription(string $mode): string
    {
        return match ($mode) {
            AgentGroup::MODE_ROUTER => 'Automatically routes to the best agent based on the message',
            AgentGroup::MODE_SEQUENTIAL => 'Agents process in order, each building on previous responses',
            AgentGroup::MODE_PARALLEL => 'All agents respond simultaneously for comprehensive answers',
            AgentGroup::MODE_HANDOFF => 'Manual handoff between agents as needed',
            AgentGroup::MODE_SUPERVISOR => 'Manager agent autonomously delegates tasks to worker agents',
            default => 'Unknown mode',
        };
    }
}

