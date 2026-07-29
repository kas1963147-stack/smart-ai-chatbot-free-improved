<?php
declare(strict_types=1);
/**
 * History Observer
 * 
 * Custom SplObserver that captures tool executions from the Neuron AI framework
 * and stores them locally for the History tab feature.
 * 
 * Events captured:
 * - tool-calling: When a tool execution starts
 * - tool-called: When a tool execution completes
 * - inference-start/stop: LLM inference timing
 * 
 * @package Quarksol\SmartChatbot\Observability
 */

namespace Quarksol\SmartChatbot\Observability;

use NeuronAI\AgentInterface;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\AgentError;
use SplObserver;
use SplSubject;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * History Observer
 * 
 * Captures tool execution events for local storage and history display.
 */
class HistoryObserver implements SplObserver
{
    /**
     * Active tool executions (keyed by tool class name)
     * @var array<string, array>
     */
    private array $activeExecutions = [];

    /**
     * Completed tool executions for this request
     * @var array<array>
     */
    private array $completedExecutions = [];

    /**
     * Session ID for this request
     */
    private ?string $sessionId = null;

    /**
     * Message index within the session
     */
    private ?int $messageIndex = null;

    /**
     * Message ID within the session
     */
    private ?string $messageId = null;

    /**
     * Agent database ID
     */
    private ?int $agentDbId = null;

    /**
     * User ID
     */
    private ?int $userId = null;

    /**
     * Error captured during execution
     */
    private ?string $lastError = null;

    /**
     * Event to method mapping
     */
    private const EVENT_MAP = [
        'tool-calling' => 'handleToolCalling',
        'tool-called' => 'handleToolCalled',
        'inference-start' => 'handleInferenceStart',
        'inference-stop' => 'handleInferenceStop',
        'error' => 'handleError',
    ];

    /**
     * Constructor
     */
    public function __construct(
        ?string $sessionId = null,
        ?int $messageIndex = null,
        ?int $agentDbId = null,
        ?int $userId = null,
        ?string $messageId = null
    ) {
        $this->sessionId = $sessionId;
        $this->messageIndex = $messageIndex;
        $this->agentDbId = $agentDbId;
        $this->userId = $userId ?? get_current_user_id();
        $this->messageId = $messageId;
    }

    /**
     * Set context for this observer
     */
    public function setContext(
        string $sessionId,
        ?int $messageIndex = null,
        ?int $agentDbId = null,
        ?string $messageId = null
    ): self {
        $this->sessionId = $sessionId;
        $this->messageIndex = $messageIndex;
        $this->agentDbId = $agentDbId;
        $this->messageId = $messageId;
        return $this;
    }

    /**
     * SplObserver update method - receives events from the agent
     */
    public function update(SplSubject $subject, ?string $event = null, mixed $data = null): void
    {
        if ($event === null || !isset(self::EVENT_MAP[$event])) {
            return;
        }

        $method = self::EVENT_MAP[$event];
        $this->$method($subject, $data);
    }

    /**
     * Handle tool-calling event (tool execution starting)
     */
    private function handleToolCalling(SplSubject $agent, ToolCalling $data): void
    {
        $tool = $data->tool;
        $toolClass = $tool::class;

        // ==========================================
        // Hook: swc/tool/pre_execute (action)
        // Fired before tool execution begins
        // ==========================================
        \Quarksol\SmartChatbot\Hooks\Hooks::action('swc/tool/pre_execute', $tool, $this->sessionId, $this->agentDbId);

        $toolName = $tool->getName();
        $inputsPreview = '';
        try {
            $inputs = $tool->getInputs() ?? [];
            $inputsPreview = wp_json_encode($inputs);
            if (strlen($inputsPreview) > 200) {
                $inputsPreview = substr($inputsPreview, 0, 200) . '...';
            }
        } catch (\Throwable $e) {
            $inputsPreview = '(unavailable)';
        }
        error_log("[ToolCall]  CALLING: {$toolName} | Inputs: {$inputsPreview}");

        $this->activeExecutions[$toolClass] = [
            'action_id' => $this->generateActionId(),
            'tool_name' => $toolName,
            'tool_description' => $tool->getDescription(),
            'tool_class' => $toolClass,
            'properties' => $this->serializeProperties($tool->getProperties()),
            'started_at' => microtime(true),
            'started_at_datetime' => current_time('mysql'),
        ];
    }

    /**
     * Handle tool-called event (tool execution completed)
     */
    private function handleToolCalled(SplSubject $agent, ToolCalled $data): void
    {
        $tool = $data->tool;
        $toolClass = $tool::class;

        if (!isset($this->activeExecutions[$toolClass])) {
            // Tool was called but we didn't see the calling event
            // Create a minimal execution record
            $execution = [
                'action_id' => $this->generateActionId(),
                'tool_name' => $tool->getName(),
                'tool_description' => $tool->getDescription(),
                'started_at_datetime' => current_time('mysql'),
            ];
        } else {
            $execution = $this->activeExecutions[$toolClass];
            unset($this->activeExecutions[$toolClass]);
        }

        $endTime = microtime(true);
        $startTime = $execution['started_at'] ?? $endTime;
        $durationMs = (int) round(($endTime - $startTime) * 1000);

        // Log tool completion with result preview
        $toolName = $execution['tool_name'];
        $resultPreview = '';
        try {
            $result = $tool->getResult();
            $resultPreview = is_string($result) ? $result : wp_json_encode($result);
            if (strlen($resultPreview) > 300) {
                $resultPreview = substr($resultPreview, 0, 300) . '...';
            }
        } catch (\Throwable $e) {
            $resultPreview = '(unavailable)';
        }
        $status = $this->lastError === null ? '' : '';
        error_log("[ToolCall] {$status} COMPLETED: {$toolName} | {$durationMs}ms | Result: {$resultPreview}");

        $this->completedExecutions[] = [
            'action_id' => $execution['action_id'],
            'session_id' => $this->sessionId,
            'message_index' => $this->messageIndex,
            'message_id' => $this->messageId,
            'action_type' => 'tool_call',
            'tool_name' => $execution['tool_name'],
            'tool_description' => $execution['tool_description'] ?? null,
            'inputs' => $this->safeJsonEncode($tool->getInputs() ?? []),
            'outputs' => $this->safeJsonEncode($tool->getResult() ?? null),
            'duration_ms' => $durationMs,
            'success' => $this->lastError === null,
            'error_message' => $this->lastError,
            'agent_db_id' => $this->agentDbId,
            'user_id' => $this->userId,
            'started_at' => $execution['started_at_datetime'],
            'completed_at' => current_time('mysql'),
        ];

        // ==========================================
        // Hook: swc/tool/post_execute (action)
        // Fired after tool execution completes
        // ==========================================
        \Quarksol\SmartChatbot\Hooks\Hooks::action(
            'swc/tool/post_execute',
            $tool,
            $tool->getResult(),
            $this->lastError === null,
            $this->sessionId,
            $this->agentDbId
        );

        // Reset error after recording
        $this->lastError = null;
    }

    /**
     * Handle inference-start event
     */
    private function handleInferenceStart(SplSubject $agent, InferenceStart $data): void
    {
        // Could track inference timing if needed
    }

    /**
     * Handle inference-stop event
     */
    private function handleInferenceStop(SplSubject $agent, InferenceStop $data): void
    {
        // Could track inference timing if needed
    }

    /**
     * Handle error event
     */
    private function handleError(SplSubject $agent, AgentError $data): void
    {
        $this->lastError = $data->exception->getMessage();
    }

    /**
     * Get all completed executions
     * 
     * @return array<array>
     */
    public function getExecutions(): array
    {
        return $this->completedExecutions;
    }

    /**
     * Get executions formatted for message storage
     * Returns a simplified array suitable for JSON storage in message extra data
     * 
     * @return array<array>
     */
    public function getExecutionsForMessage(): array
    {
        return array_map(function (array $execution): array {
            return [
                'id' => $execution['action_id'],
                'name' => $execution['tool_name'],
                'inputs' => json_decode($execution['inputs'] ?? '{}', true),
                'output' => json_decode($execution['outputs'] ?? '{}', true),
                'duration_ms' => $execution['duration_ms'],
                'success' => $execution['success'],
                'error' => $execution['error_message'],
            ];
        }, $this->completedExecutions);
    }

    /**
     * Persist all executions to database
     */
    public function persist(): int
    {
        if (empty($this->completedExecutions)) {
            return 0;
        }

        global $wpdb;

        // Ensure table exists
        if (!class_exists('\Quarksol\SmartChatbot\History\\HistorySchema')) {
            return 0;
        }

        $tables = \Quarksol\SmartChatbot\History\HistorySchema::getTableNames();
        $table = $tables['action_log'];

        $inserted = 0;

        foreach ($this->completedExecutions as $execution) {
            // Build data array with all fields in correct order
            // message_index and message_id must be included at proper positions
            $data = [
                'session_id' => $execution['session_id'] ?? '',
                'message_index' => $execution['message_index'],
                'message_id' => $execution['message_id'] ?? null,
                'action_id' => $execution['action_id'],
                'action_type' => $execution['action_type'],
                'tool_name' => $execution['tool_name'],
                'tool_description' => $execution['tool_description'],
                'inputs' => $execution['inputs'],
                'outputs' => $execution['outputs'],
                'duration_ms' => $execution['duration_ms'],
                'success' => $execution['success'] ? 1 : 0,
                'error_message' => $execution['error_message'],
                'agent_db_id' => $execution['agent_db_id'],
                'user_id' => $execution['user_id'],
                'started_at' => $execution['started_at'],
                'completed_at' => $execution['completed_at'],
            ];
            $formats = ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s'];

            // Remove null values that shouldn't be inserted
            if ($data['message_index'] === null) {
                unset($data['message_index']);
                array_splice($formats, 1, 1); // Remove %d at position 1
            }
            if ($data['message_id'] === null) {
                unset($data['message_id']);
                // Position depends on whether message_index was removed
                $msgIdPos = isset($execution['message_index']) && $execution['message_index'] !== null ? 2 : 1;
                array_splice($formats, $msgIdPos, 1);
            }

            $result = $wpdb->insert($table, $data, $formats);

            if ($result) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Clear all executions (for reuse)
     */
    public function clear(): void
    {
        $this->activeExecutions = [];
        $this->completedExecutions = [];
        $this->lastError = null;
    }

    /**
     * Generate unique action ID
     */
    private function generateActionId(): string
    {
        return 'act_' . bin2hex(random_bytes(12));
    }

    /**
     * Serialize tool properties for storage
     */
    private function serializeProperties(array $properties): array
    {
        return array_map(function ($prop) {
            if (method_exists($prop, 'jsonSerialize')) {
                return $prop->jsonSerialize();
            }
            return (array) $prop;
        }, $properties);
    }

    /**
     * Safely encode data to JSON, handling circular references and large data
     */
    private function safeJsonEncode(mixed $data, int $maxLength = 65535): string
    {
        if ($data === null) {
            return 'null';
        }

        try {
            $json = wp_json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);

            if ($json === false) {
                return '{"error": "Failed to encode data"}';
            }

            // Truncate if too large
            if (strlen($json) > $maxLength) {
                return '{"error": "Data too large", "truncated": true, "preview": ' .
                    wp_json_encode(substr($json, 0, 1000)) . '}';
            }

            return $json;
        } catch (\Throwable $e) {
            return '{"error": "' . esc_js($e->getMessage()) . '"}';
        }
    }
}
