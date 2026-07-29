<?php
declare(strict_types=1);
/**
 * Reply Payloads
 * 
 * Typed payloads for reply-related hooks.
 * 
 * @package Quarksol\SmartChatbot\Hooks\Payloads
 */

namespace Quarksol\SmartChatbot\Hooks\Payloads;

use Quarksol\SmartChatbot\Hooks\HookPayload;
use Quarksol\SmartChatbot\Models\ChatAgent;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reply Received Payload
 * 
 * Fired when a raw response is received from the AI provider.
 */
class ReplyReceivedPayload extends HookPayload
{

    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public array $usage,
        public ?ChatAgent $agent,
        public array $rawResponse = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'content_length' => strlen($this->content),
            'provider' => $this->provider,
            'model' => $this->model,
            'usage' => $this->usage,
            'agent_id' => $this->agent?->id,
            'has_raw_response' => !empty($this->rawResponse),
        ];
    }

    /**
     * Create a modified copy with different content
     */
    public function withContent(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;
        return $clone;
    }

    /**
     * Get token usage
     */
    public function getTokens(): int
    {
        return ($this->usage['prompt_tokens'] ?? 0) + ($this->usage['completion_tokens'] ?? 0);
    }
}

/**
 * Reply Tool Calls Payload
 * 
 * Fired when tool calls are detected in the response.
 */
class ReplyToolCallsPayload extends HookPayload
{

    public function __construct(
        public array $toolCalls,
        public ?ChatAgent $agent,
        public string $originalContent = '',
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'tool_calls_count' => count($this->toolCalls),
            'tool_names' => array_column($this->toolCalls, 'name'),
            'agent_id' => $this->agent?->id,
            'has_content' => $this->originalContent !== '',
        ];
    }

    /**
     * Filter tool calls
     */
    public function filterToolCalls(callable $callback): self
    {
        $clone = clone $this;
        $clone->toolCalls = array_filter($clone->toolCalls, $callback);
        return $clone;
    }

    /**
     * Check if a specific tool is being called
     */
    public function hasToolCall(string $toolName): bool
    {
        foreach ($this->toolCalls as $call) {
            if (($call['name'] ?? '') === $toolName) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Reply Complete Payload
 * 
 * Fired when the final reply is ready to be sent to the client.
 */
class ReplyCompletePayload extends HookPayload
{

    public function __construct(
        public string $content,
        public array $usage,
        public ?ChatAgent $agent,
        public array $toolResults = [],
        public array $images = [],
        public array $actions = [],
        public ?string $responseId = null,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'content_length' => strlen($this->content),
            'usage' => $this->usage,
            'agent_id' => $this->agent?->id,
            'tool_results_count' => count($this->toolResults),
            'images_count' => count($this->images),
            'actions_count' => count($this->actions),
            'response_id' => $this->responseId,
        ];
    }

    /**
     * Create a modified copy with different content
     */
    public function withContent(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;
        return $clone;
    }

    /**
     * Append to content
     */
    public function appendContent(string $suffix): self
    {
        $clone = clone $this;
        $clone->content .= $suffix;
        return $clone;
    }

    /**
     * Add an action for the client
     */
    public function withAction(array $action): self
    {
        $clone = clone $this;
        $clone->actions[] = $action;
        return $clone;
    }
}

/**
 * Usage Payload
 * 
 * Fired for usage tracking and analytics.
 */
class UsagePayload extends HookPayload
{

    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public ?float $cost,
        public string $provider,
        public string $model,
        public ?ChatAgent $agent,
        public ?int $userId = null,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->getTotalTokens(),
            'cost' => $this->cost,
            'provider' => $this->provider,
            'model' => $this->model,
            'agent_id' => $this->agent?->id,
            'user_id' => $this->userId,
        ];
    }

    /**
     * Get total tokens
     */
    public function getTotalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    /**
     * Set calculated cost
     */
    public function withCost(float $cost): self
    {
        $clone = clone $this;
        $clone->cost = $cost;
        return $clone;
    }
}
