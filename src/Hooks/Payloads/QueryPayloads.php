<?php
declare(strict_types=1);
/**
 * Query Payloads
 * 
 * Typed payloads for query-related hooks.
 * 
 * @package Quarksol\SmartChatbot\Hooks\Payloads
 */

namespace Quarksol\SmartChatbot\Hooks\Payloads;

use Quarksol\SmartChatbot\Hooks\HookPayload;
use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Models\ChatSession;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Query Created Payload
 * 
 * Fired when a new query is created before processing.
 */
class QueryCreatedPayload extends HookPayload
{

    public function __construct(
        public string $message,
        public array $messages,
        public ?string $instructions,
        public ?ChatAgent $agent,
        public ?ChatSession $session,
        public array $tools = [],
        public ?string $context = null,
        public array $metadata = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'messages_count' => count($this->messages),
            'has_instructions' => $this->instructions !== null,
            'agent_id' => $this->agent?->id,
            'agent_name' => $this->agent?->name,
            'session_id' => $this->session?->sessionId,
            'tools_count' => count($this->tools),
            'has_context' => $this->context !== null,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a modified copy with updated message
     */
    public function withMessage(string $message): self
    {
        $clone = clone $this;
        $clone->message = $message;
        return $clone;
    }

    /**
     * Create a modified copy with updated instructions
     */
    public function withInstructions(?string $instructions): self
    {
        $clone = clone $this;
        $clone->instructions = $instructions;
        return $clone;
    }

    /**
     * Create a modified copy with added context
     */
    public function withContext(?string $context): self
    {
        $clone = clone $this;
        $clone->context = $context;
        return $clone;
    }

    /**
     * Create a modified copy with additional tools
     */
    public function withTools(array $tools): self
    {
        $clone = clone $this;
        $clone->tools = $tools;
        return $clone;
    }

    /**
     * Add metadata
     */
    public function withMetadata(string $key, $value): self
    {
        $clone = clone $this;
        $clone->metadata[$key] = $value;
        return $clone;
    }
}

/**
 * Query Pre-Send Payload
 * 
 * Fired just before sending query to the AI provider.
 */
class QueryPreSendPayload extends HookPayload
{

    public function __construct(
        public string $provider,
        public string $model,
        public string $message,
        public array $messages,
        public ?string $instructions,
        public array $tools,
        public ?ChatAgent $agent,
        public array $options = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'message' => $this->message,
            'messages_count' => count($this->messages),
            'has_instructions' => $this->instructions !== null,
            'tools_count' => count($this->tools),
            'agent_id' => $this->agent?->id,
            'options' => $this->options,
        ];
    }

    /**
     * Create a modified copy with different model
     */
    public function withModel(string $model): self
    {
        $clone = clone $this;
        $clone->model = $model;
        return $clone;
    }

    /**
     * Create a modified copy with options
     */
    public function withOption(string $key, $value): self
    {
        $clone = clone $this;
        $clone->options[$key] = $value;
        return $clone;
    }
}

/**
 * Instructions Payload
 * 
 * Fired when agent instructions are being resolved.
 */
class InstructionsPayload extends HookPayload
{

    public function __construct(
        public string $instructions,
        public ?ChatAgent $agent,
        public ?ChatSession $session,
        public array $placeholders = [],
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'instructions_length' => strlen($this->instructions),
            'agent_id' => $this->agent?->id,
            'session_id' => $this->session?->sessionId,
            'placeholders' => array_keys($this->placeholders),
        ];
    }

    /**
     * Create a modified copy with different instructions
     */
    public function withInstructions(string $instructions): self
    {
        $clone = clone $this;
        $clone->instructions = $instructions;
        return $clone;
    }

    /**
     * Apply placeholders to instructions
     */
    public function applyPlaceholders(): string
    {
        $result = $this->instructions;
        foreach ($this->placeholders as $key => $value) {
            $result = str_replace("{{$key}}", (string) $value, $result);
        }
        return $result;
    }
}
