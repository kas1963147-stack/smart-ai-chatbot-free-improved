<?php
declare(strict_types=1);


/**
 * Base Provider Abstract Class
 * 
 * 
 * Abstract base class that all API providers extend
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;
use Generator;

/**
 * Stream chunk types
 */
class StreamChunk
{
    public const TYPE_TEXT = 'text';
    public const TYPE_REASONING = 'reasoning';
    public const TYPE_TOOL_CALL = 'tool_call';  // Complete tool call (non-streaming)
    public const TYPE_TOOL_CALL_PARTIAL = 'tool_call_partial';
    public const TYPE_TOOL_CALL_END = 'tool_call_end';
    public const TYPE_USAGE = 'usage';
    public const TYPE_ERROR = 'error';
}

/**
 * Usage metrics from API response
 */
class UsageMetrics
{
    public int $inputTokens = 0;
    public int $outputTokens = 0;
    public ?int $cacheWriteTokens = null;
    public ?int $cacheReadTokens = null;
    public float $totalCost = 0.0;

    public function toArray(): array
    {
        return [
            'type' => StreamChunk::TYPE_USAGE,
            'inputTokens' => $this->inputTokens,
            'outputTokens' => $this->outputTokens,
            'cacheWriteTokens' => $this->cacheWriteTokens,
            'cacheReadTokens' => $this->cacheReadTokens,
            'totalCost' => $this->totalCost,
        ];
    }
}

/**
 * Message structure for API calls
 */
class Message
{
    public const ROLE_SYSTEM = 'system';
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    public string $role;
    public $content; // string or array for multi-modal
    public ?string $name = null;
    public ?array $toolCalls = null;
    public ?string $toolCallId = null;

    public function __construct(string $role, $content)
    {
        $this->role = $role;
        $this->content = $content;
    }

    public static function system(string $content): self
    {
        return new self(self::ROLE_SYSTEM, $content);
    }

    public static function user($content): self
    {
        return new self(self::ROLE_USER, $content);
    }

    public static function assistant(string $content): self
    {
        return new self(self::ROLE_ASSISTANT, $content);
    }
}

/**
 * Metadata for create message requests
 */
class CreateMessageMetadata
{
    public ?string $taskId = null;
    public ?string $mode = null;
    public ?bool $store = true;
    public ?array $tools = null;
    public ?string $toolChoice = null; // none, auto, required
    public ?bool $parallelToolCalls = false;
    public ?array $allowedFunctionNames = null;
}

/**
 * Abstract base class for all API providers
 */
abstract class BaseProvider
{
    /** Provider name for identification */
    protected string $providerName;

    /** Provider settings */
    protected ProviderSettings $settings;

    public function __construct(ProviderSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Create a message/completion - must be implemented by providers
     * 
     * @param string $systemPrompt The system prompt
     * @param array $messages Array of Message objects
     * @param CreateMessageMetadata|null $metadata Optional metadata
     * @return Generator Yields stream chunks
     */
    abstract public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator;

    /**
     * Get the current model info
     * 
     * @return array ['id' => string, 'info' => ModelInfo]
     */
    abstract public function getModel(): array;

    /**
     * Count tokens for content (default implementation)
     * Providers can override for native token counting
     * 
     * @param array $content Content blocks to count
     * @return int Token count
     */
    public function countTokens(array $content): int
    {
        // Simple estimation: ~4 chars per token
        $text = '';
        foreach ($content as $block) {
            if (is_string($block)) {
                $text .= $block;
            } elseif (isset($block['text'])) {
                $text .= $block['text'];
            }
        }
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Convert tools to OpenAI-compatible format
     * 
     * @param array|null $tools Tool definitions
     * @return array|null Converted tools
     */
    protected function convertToolsForOpenAI(?array $tools): ?array
    {
        if (!$tools) {
            return null;
        }

        return array_map(function ($tool) {
            if (($tool['type'] ?? null) !== 'function') {
                return $tool;
            }

            $params = $tool['function']['parameters'] ?? [];
            
            // Always sanitize schemas (fix array types missing 'items' which OpenAI rejects)
            $params = $this->sanitizeArraySchemas($params);

            // Check if it's an MCP tool (uses 'mcp--' prefix)
            $isMcp = strpos($tool['function']['name'] ?? '', 'mcp--') === 0;

            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['function']['name'],
                    'description' => $tool['function']['description'] ?? '',
                    'strict' => false,
                    'parameters' => $isMcp
                        ? $params
                        : $this->convertToolSchemaForOpenAI($params),
                ],
            ];
        }, $tools);
    }

    /**
     * Recursively sanitize schemas to fix array types missing 'items'
     * OpenAI/Azure REQUIRES 'items' on every array-typed property
     */
    protected function sanitizeArraySchemas(array $schema): array
    {
        // Fix this node if it's an array without items
        if (isset($schema['type']) && $schema['type'] === 'array' && !isset($schema['items'])) {
            $schema['items'] = ['type' => 'string'];
        }

        // Recurse into properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $prop) {
                if (is_array($prop)) {
                    $schema['properties'][$key] = $this->sanitizeArraySchemas($prop);
                }
            }
        }

        // Recurse into items
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->sanitizeArraySchemas($schema['items']);
        }

        // Recurse into allOf/anyOf/oneOf
        foreach (['allOf', 'anyOf', 'oneOf'] as $combiner) {
            if (isset($schema[$combiner]) && is_array($schema[$combiner])) {
                foreach ($schema[$combiner] as $i => $subSchema) {
                    if (is_array($subSchema)) {
                        $schema[$combiner][$i] = $this->sanitizeArraySchemas($subSchema);
                    }
                }
            }
        }

        return $schema;
    }

    /**
     * Convert tool schema for OpenAI strict mode
     * Ensures all properties are required and additionalProperties is false
     * 
     * @param array $schema Tool parameter schema
     * @return array Converted schema
     */
    protected function convertToolSchemaForOpenAI(array $schema): array
    {
        // Return a proper empty object schema for empty arrays
        // This ensures JSON serializes to {} instead of []
        if (empty($schema)) {
            return [
                'type' => 'object',
                'properties' => new \stdClass(),
            ];
        }
        
        if (($schema['type'] ?? null) !== 'object') {
            return $schema;
        }

        $result = $schema;
        // $result['additionalProperties'] = false;

        if (isset($result['properties']) && is_array($result['properties'])) {
            // $allKeys = array_keys($result['properties']);
            // $result['required'] = $allKeys;

            foreach ($result['properties'] as $key => $prop) {
                // Handle nullable types
                if (isset($prop['type']) && is_array($prop['type']) && in_array('null', $prop['type'])) {
                    $nonNullTypes = array_filter($prop['type'], fn($t) => $t !== 'null');
                    $result['properties'][$key]['type'] = count($nonNullTypes) === 1
                        ? reset($nonNullTypes)
                        : array_values($nonNullTypes);
                }

                // Recursively process nested objects
                if (($prop['type'] ?? null) === 'object') {
                    $result['properties'][$key] = $this->convertToolSchemaForOpenAI($prop);
                } elseif (($prop['type'] ?? null) === 'array' && ($prop['items']['type'] ?? null) === 'object') {
                    $result['properties'][$key]['items'] = $this->convertToolSchemaForOpenAI($prop['items']);
                }
            }
        }

        return $result;
    }

    /**
     * Process usage metrics from API response
     * 
     * @param array $usage Usage data from API
     * @param ModelInfo|null $modelInfo Model info for cost calculation
     * @return UsageMetrics
     */
    protected function processUsageMetrics(array $usage, ?ModelInfo $modelInfo = null): UsageMetrics
    {
        $metrics = new UsageMetrics();

        $metrics->inputTokens = $usage['prompt_tokens'] ?? 0;
        $metrics->outputTokens = $usage['completion_tokens'] ?? 0;
        $metrics->cacheWriteTokens = $usage['prompt_tokens_details']['cache_write_tokens'] ?? null;
        $metrics->cacheReadTokens = $usage['prompt_tokens_details']['cached_tokens'] ?? null;

        if ($modelInfo) {
            $metrics->totalCost = $this->calculateCost($metrics, $modelInfo);
        }

        return $metrics;
    }

    /**
     * Calculate cost based on usage and model pricing
     * Uses CostCalculator service for dynamic pricing
     */
    protected function calculateCost(UsageMetrics $usage, ModelInfo $modelInfo): float
    {
        // Extract provider name from class
        $providerClass = get_class($this);
        $providerShortName = substr($providerClass, strrpos($providerClass, '\\') + 1);
        $providerName = strtolower($providerShortName);
        
        // Get model ID
        $modelId = $this->getModel()['id'] ?? '';
        
        // Use CostCalculator service
        $calculator = new \Quarksol\SmartChatbot\Analytics\CostCalculator();
        
        // Determine protocol based on provider
        $protocol = in_array($providerName, ['anthropic', 'anthropicvertex', 'bedrock']) 
            ? 'anthropic' 
            : 'openai';
        
        return $calculator->calculateCost(
            $providerName,
            $modelId,
            $usage->inputTokens,
            $usage->outputTokens,
            $usage->cacheWriteTokens,
            $usage->cacheReadTokens,
            $protocol
        );
    }

    /**
     * Handle provider request errors
     * 
     * @param string $model Model ID
     * @param \Exception $e Exception from request
     * @throws \Exception
     */
    public function handleRequestException(string $model, \Exception $e): void
    {
        $statusCode = method_exists($e, 'getCode') ? $e->getCode() : 0;

        switch ($statusCode) {
            case 413:
                throw new \Exception("Request too large for {$this->providerName}", 413);
            case 429:
                throw new \Exception("Rate limited by {$this->providerName}", 429);
            case 529:
                throw new \Exception("{$this->providerName} is overloaded", 529);
            default:
                throw new \Exception(
                    "{$this->providerName} error ({$model}): " . $e->getMessage(),
                    $statusCode
                );
        }
    }
}
