<?php
declare(strict_types=1);
/**
 * WorkflowExecutionService
 *
 * Executes workflow steps sequentially and handles approvals.
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Agent\NeuronAgent;
use Quarksol\SmartChatbot\Models\Workflow;
use Quarksol\SmartChatbot\Models\WorkflowExecution;
use NeuronAI\Chat\Messages\UserMessage;

if (!defined('ABSPATH')) {
    exit;
}

class WorkflowExecutionService
{
    public function executeWorkflow(Workflow $workflow, $input = null): WorkflowExecution
    {
        $execution = WorkflowExecution::create($workflow->id, $input);
        $execution->status = 'running';
        $execution->startedAt = current_time('mysql');
        $execution->save();

        $this->runSteps($workflow, $execution);

        return $execution;
    }

    public function resumeExecution(WorkflowExecution $execution, array $feedback = []): WorkflowExecution
    {
        $workflow = Workflow::find($execution->workflowId);
        if (!$workflow) {
            throw new \RuntimeException('Workflow not found');
        }

        $output = $execution->output ?? ['steps' => []];
        if (!isset($output['steps']) || !is_array($output['steps'])) {
            $output['steps'] = [];
        }

        $output['steps'][] = [
            'step' => $execution->currentStep,
            'type' => 'approval',
            'approved' => (bool) ($feedback['approved'] ?? false),
            'feedback' => $feedback,
        ];

        $execution->output = $output;
        $execution->interruptedData = null;
        $execution->status = 'running';
        $execution->currentStep = $execution->currentStep + 1;
        $execution->save();

        $this->runSteps($workflow, $execution);

        return $execution;
    }

    protected function runSteps(Workflow $workflow, WorkflowExecution $execution): void
    {
        $steps = $workflow->steps;
        $output = $execution->output ?? ['steps' => []];
        if (!isset($output['steps']) || !is_array($output['steps'])) {
            $output['steps'] = [];
        }

        $stepCount = count($steps);
        $index = $execution->currentStep;

        while ($index < $stepCount) {
            $execution->currentStep = $index;
            $step = $steps[$index] ?? null;

            if (!$step || empty($step['type'])) {
                $execution->status = 'failed';
                $execution->completedAt = current_time('mysql');
                $execution->output = $output;
                $execution->save();
                return;
            }

            if ($step['type'] === 'approval') {
                $execution->status = 'awaiting_approval';
                $execution->interruptedData = [
                    'prompt' => $step['prompt'] ?? '',
                    'step' => $index,
                ];
                $execution->output = $output;
                $execution->save();
                return;
            }

            if ($step['type'] === 'condition') {
                $lastOutput = end($output['steps']);
                $value = $lastOutput['output']['content'] ?? ($lastOutput['output'] ?? '');
                $condition = $step['condition'] ?? [];
                $result = $this->evaluateCondition($condition, $value);

                $jump = $result ? ($condition['if_true_step'] ?? null) : ($condition['if_false_step'] ?? null);
                if ($jump) {
                    $index = max(0, ((int) $jump) - 1);
                    continue;
                }

                $index++;
                continue;
            }

            if ($step['type'] === 'agent') {
                try {
                    $agentResult = $this->runAgentStep($step, $execution, $output);
                } catch (\Throwable $e) {
                    $execution->status = 'failed';
                    $execution->completedAt = current_time('mysql');
                    $output['error'] = $e->getMessage();
                    $execution->output = $output;
                    $execution->save();
                    return;
                }

                $output['steps'][] = [
                    'step' => $index,
                    'type' => 'agent',
                    'output' => $agentResult,
                ];

                $execution->output = $output;
                $execution->save();
                $index++;
                continue;
            }

            $index++;
        }

        $execution->status = 'completed';
        $execution->completedAt = current_time('mysql');
        $execution->output = $output;
        $execution->save();
    }

    protected function runAgentStep(array $step, WorkflowExecution $execution, array $output): array
    {
        $agentId = sanitize_text_field($step['agent_id'] ?? '');
        if (!$agentId) {
            throw new \RuntimeException('Agent ID missing for workflow step');
        }

        $prompt = (string) ($step['input'] ?? '');
        $inputContext = is_scalar($execution->input) ? (string) $execution->input : wp_json_encode($execution->input);
        $previousOutput = '';
        $lastStep = end($output['steps']);
        if (is_array($lastStep)) {
            $previousOutput = (string) ($lastStep['output']['content'] ?? ($lastStep['output'] ?? ''));
        }

        if ($prompt !== '') {
            if (!str_contains($prompt, '{{input}}') && !str_contains($prompt, '{{previous_output}}')) {
                $prompt = "Context / Previous Work:\n" . $inputContext . "\n\n" . ($previousOutput ? "Previous Step Output:\n" . $previousOutput . "\n\n" : "") . "Task:\n" . $prompt;
            } else {
                $prompt = str_replace(['{{input}}', '{{previous_output}}'], [$inputContext, $previousOutput], $prompt);
            }
        }

        $agent = NeuronAgent::withConfigId($agentId);
        if (class_exists(AgentContext::class)) {
            AgentContext::set($agent->getConfig(), null, $agentId);
        }

        try {
            $response = $agent->chat(new UserMessage($prompt ?: $inputContext));
        } finally {
            if (class_exists(AgentContext::class)) {
                AgentContext::clear();
            }
        }

        return [
            'content' => method_exists($response, 'content') ? $response->content() : (string) $response,
            'response_type' => is_object($response) ? get_class($response) : 'string',
        ];
    }

    protected function evaluateCondition(array $condition, string $value): bool
    {
        $operator = $condition['operator'] ?? 'contains';
        $expected = (string) ($condition['value'] ?? '');

        switch ($operator) {
            case 'equals':
                return trim($value) === trim($expected);
            case 'not_contains':
                return stripos($value, $expected) === false;
            case 'contains':
            default:
                return stripos($value, $expected) !== false;
        }
    }

    /**
     * Execute workflow from a raw array of steps.
     *
     * This is the bridge method that allows AgentGroup workflow_steps
     * (stored in routing_config) to be executed without needing a
     * legacy Workflow model or WorkflowExecution record.
     *
     * @param array  $steps   Array of step definitions from routing_config.workflow_steps
     * @param string $input   The user message / initial input
     * @return array{status: string, output: array, steps_completed: int}
     */
    public function executeFromSteps(array $steps, string $input): array
    {
        if (empty($steps)) {
            return [
                'status' => 'completed',
                'output' => ['steps' => []],
                'steps_completed' => 0,
            ];
        }

        $output = ['steps' => []];
        $index = 0;
        $stepCount = count($steps);
        $previousContent = $input;
        $lastAgentStepTime = 0;

        while ($index < $stepCount) {
            $step = $steps[$index] ?? null;

            if (!$step || empty($step['type'])) {
                $index++;
                continue;
            }

            $type = $step['type'];

            // ── Instruction steps: inject into context for subsequent agent calls ──
            if ($type === 'instruction') {
                $instruction = $step['input'] ?? $step['description'] ?? '';
                if (!empty($instruction)) {
                    $previousContent .= "\n\n[Instruction — {$step['name']}]: " . $instruction;
                }
                $output['steps'][] = [
                    'step'  => $index,
                    'type'  => 'instruction',
                    'name'  => $step['name'] ?? '',
                    'content' => $instruction,
                ];
                $index++;
                continue;
            }

            // ── Agent steps: execute the assigned agent ──
            if ($type === 'agent') {
                // Rate-limit protection: pause between consecutive agent calls
                if ($lastAgentStepTime > 0) {
                    $elapsed = microtime(true) - $lastAgentStepTime;
                    if ($elapsed < 1.5) {
                        usleep((int) ((1.5 - $elapsed) * 1_000_000));
                    }
                }

                try {
                    $result = $this->runAgentStepDirect($step, $previousContent, $output);
                    $lastAgentStepTime = microtime(true);
                    
                    $agentOutput = $result['content'] ?? '';
                    if (!empty($agentOutput)) {
                        $previousContent .= "\n\n[Agent Output — " . ($step['name'] ?? 'Agent') . "]:\n" . $agentOutput;
                    }

                    $output['steps'][] = [
                        'step'   => $index,
                        'type'   => 'agent',
                        'name'   => $step['name'] ?? '',
                        'output' => $result,
                    ];
                } catch (\Throwable $e) {
                    // Don't abort the whole workflow — return what we have so far
                    Logger::warning('Workflow agent step failed, returning partial result', [
                        'step_index' => $index,
                        'step_name'  => $step['name'] ?? '',
                        'error'      => $e->getMessage(),
                    ]);

                    $output['steps'][] = [
                        'step'   => $index,
                        'type'   => 'agent',
                        'name'   => $step['name'] ?? '',
                        'output' => ['content' => '', 'error' => $e->getMessage()],
                    ];

                    // Return partial success — earlier steps may have good content
                    return [
                        'status' => 'partial',
                        'output' => $output,
                        'steps_completed' => $index,
                    ];
                }

                $index++;
                continue;
            }

            // ── Condition steps: evaluate and potentially jump ──
            if ($type === 'condition') {
                $condition = $step['condition'] ?? [];
                if (!empty($condition)) {
                    $result = $this->evaluateCondition($condition, $previousContent);
                    $jump = $result
                        ? ($condition['if_true_step'] ?? null)
                        : ($condition['if_false_step'] ?? null);
                    if ($jump !== null) {
                        $index = max(0, ((int) $jump) - 1);
                        continue;
                    }
                }
                $index++;
                continue;
            }

            // ── Approval steps: return early, requiring external approval ──
            if ($type === 'approval') {
                $output['steps'][] = [
                    'step'   => $index,
                    'type'   => 'approval',
                    'name'   => $step['name'] ?? '',
                    'prompt' => $step['input'] ?? $step['description'] ?? '',
                ];
                return [
                    'status' => 'awaiting_approval',
                    'output' => $output,
                    'steps_completed' => $index,
                ];
            }

            // Unknown type — skip
            $index++;
        }

        return [
            'status' => 'completed',
            'output' => $output,
            'steps_completed' => $stepCount,
        ];
    }

    /**
     * Execute a single agent step from a raw step definition.
     *
     * Similar to runAgentStep() but does not require a WorkflowExecution object.
     *
     * @param array  $step            The step definition
     * @param string $contextMessage  Accumulated context / user message
     * @param array  $output          Current output state (for previous step references)
     * @return array{content: string, response_type: string}
     */
    protected function runAgentStepDirect(array $step, string $contextMessage, array $output): array
    {
        $agentId = sanitize_text_field($step['agent_id'] ?? '');
        if (empty($agentId)) {
            throw new \RuntimeException('Agent ID missing for workflow step: ' . ($step['name'] ?? 'unnamed'));
        }

        // Build prompt from step input, substituting placeholders
        $prompt = (string) ($step['input'] ?? $step['description'] ?? '');
        $previousOutput = '';
        $lastStep = !empty($output['steps']) ? end($output['steps']) : null;
        if (is_array($lastStep)) {
            $previousOutput = (string) ($lastStep['output']['content'] ?? ($lastStep['content'] ?? ''));
        }

        if ($prompt !== '') {
            if (!str_contains($prompt, '{{input}}') && !str_contains($prompt, '{{previous_output}}')) {
                // If placeholders not used, automatically prepend context
                $prompt = "Context / Previous Work:\n" . $contextMessage . "\n\nTask:\n" . $prompt;
            } else {
                $prompt = str_replace(
                    ['{{input}}', '{{previous_output}}'],
                    [$contextMessage, $previousOutput],
                    $prompt
                );
            }
        }

        // Resolve and execute the agent
        $agent = NeuronAgent::withConfigId($agentId);

        if (class_exists(AgentContext::class)) {
            AgentContext::set($agent->getConfig(), null, $agentId);
        }

        try {
            $messageToSend = !empty($prompt) ? $prompt : $contextMessage;
            $response = $agent->chat(new UserMessage($messageToSend));
        } finally {
            if (class_exists(AgentContext::class)) {
                AgentContext::clear();
            }
        }

        return [
            'content'       => method_exists($response, 'content') ? $response->content() : (string) $response,
            'response_type' => is_object($response) ? get_class($response) : 'string',
        ];
    }
}

