<?php
declare(strict_types=1);


/**
 * Anthropic Provider
 * 
 * 
 * Supports Claude models with extended thinking and prompt caching
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
 * Default Anthropic model
 */
const ANTHROPIC_DEFAULT_MODEL = 'claude-3-5-sonnet-20241022';
const ANTHROPIC_DEFAULT_MAX_TOKENS = 8192;

/**
 * Anthropic model definitions
 */
const ANTHROPIC_MODELS = [
    'claude-sonnet-4-5' => [
        'contextWindow' => 200000,
        'maxTokens' => 16384,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'supportsReasoningBudget' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 15.0,
        'cacheWritesPrice' => 3.75,
        'cacheReadsPrice' => 0.30,
    ],
    'claude-sonnet-4-20250514' => [
        'contextWindow' => 200000,
        'maxTokens' => 16384,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'supportsReasoningBudget' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 15.0,
        'cacheWritesPrice' => 3.75,
        'cacheReadsPrice' => 0.30,
    ],
    'claude-3-5-sonnet-20241022' => [
        'contextWindow' => 200000,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 15.0,
        'cacheWritesPrice' => 3.75,
        'cacheReadsPrice' => 0.30,
    ],
    'claude-3-7-sonnet-20250219' => [
        'contextWindow' => 200000,
        'maxTokens' => 16384,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'supportsReasoningBudget' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 15.0,
    ],
    'claude-3-opus-20240229' => [
        'contextWindow' => 200000,
        'maxTokens' => 4096,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 15.0,
        'outputPrice' => 75.0,
    ],
    'claude-3-5-haiku-20241022' => [
        'contextWindow' => 200000,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 0.80,
        'outputPrice' => 4.0,
    ],
    'claude-3-haiku-20240307' => [
        'contextWindow' => 200000,
        'maxTokens' => 4096,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 0.25,
        'outputPrice' => 1.25,
    ],
];

/**
 * Models that support prompt caching
 */
const CACHE_SUPPORTED_MODELS = [
    'claude-sonnet-4-5',
    'claude-sonnet-4-20250514',
    'claude-opus-4-5-20251101',
    'claude-opus-4-1-20250805',
    'claude-opus-4-20250514',
    'claude-3-7-sonnet-20250219',
    'claude-3-5-sonnet-20241022',
    'claude-3-5-haiku-20241022',
    'claude-3-opus-20240229',
    'claude-haiku-4-5-20251001',
    'claude-3-haiku-20240307',
];

/**
 * Anthropic Claude API Provider
 * 
 * Features:
 * - Extended thinking (reasoning)
 * - Prompt caching
 * - Tool streaming
 * - Vision support
 */
class Anthropic extends BaseProvider {
    protected string $providerName = 'Anthropic';
    
    /** Base API URL */
    protected string $baseURL;
    
    /** Default headers */
    protected array $defaultHeaders = [
        'Content-Type' => 'application/json',
        'anthropic-version' => '2023-06-01',
    ];
    
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        $this->baseURL = $settings->anthropicBaseUrl ?? 'https://api.anthropic.com/v1';
    }
    
    /**
     * Get API key
     */
    protected function getApiKey(): ?string {
        return $this->settings->apiKey ?? null;
    }
    
    /**
     * Get model ID
     */
    protected function getModelId(): string {
        return $this->settings->apiModelId ?? ANTHROPIC_DEFAULT_MODEL;
    }
    
    /**
     * Get current model info
     */
    public function getModel(): array {
        $id = $this->getModelId();
        
        // Handle :thinking suffix for hybrid models
        $actualId = str_replace(':thinking', '', $id);
        
        $modelData = ANTHROPIC_MODELS[$actualId] ?? ANTHROPIC_MODELS[ANTHROPIC_DEFAULT_MODEL];
        $info = ModelInfo::fromArray($modelData);
        
        // Prepare reasoning/thinking params if enabled
        $reasoning = null;
        if ($this->settings->enableReasoningEffort && $info->supportsReasoningBudget) {
            $budget = $this->settings->modelMaxThinkingTokens ?? 4096;
            $reasoning = [
                'type' => 'enabled',
                'budget_tokens' => $budget,
            ];
        }
        
        // Get temperature
        $temperature = $this->settings->modelTemperature ?? 0;
        $maxTokens = $this->settings->modelMaxTokens ?? $info->maxTokens ?? ANTHROPIC_DEFAULT_MAX_TOKENS;
        
        // Determine beta flags
        $betas = ['fine-grained-tool-streaming-2025-05-14'];
        
        // Add prompt caching beta for supported models
        if (in_array($actualId, CACHE_SUPPORTED_MODELS)) {
            $betas[] = 'prompt-caching-2024-07-31';
        }
        
        // Add 1M context beta if enabled
        if ($this->settings->anthropicBeta1MContext && 
            in_array($actualId, ['claude-sonnet-4-20250514', 'claude-sonnet-4-5'])) {
            $betas[] = 'context-1m-2025-08-07';
        }
        
        return [
            'id' => $actualId,
            'info' => $info,
            'betas' => $betas,
            'maxTokens' => $maxTokens,
            'temperature' => $temperature,
            'reasoning' => $reasoning,
        ];
    }
    
    /**
     * Create streaming message
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelId = $model['id'];
        $modelInfo = $model['info'];
        $betas = $model['betas'];
        $maxTokens = $model['maxTokens'];
        $temperature = $model['temperature'];
        $reasoning = $model['reasoning'];
        
        // Build messages in Anthropic format
        $anthropicMessages = $this->buildMessages($messages);
        
        // Build request body
        $body = [
            'model' => $modelId,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => $anthropicMessages,
            'stream' => true,
        ];
        
        // Add thinking/reasoning if enabled
        if ($reasoning) {
            $body['thinking'] = $reasoning;
        }
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForAnthropic($metadata->tools);
        }
        
        // Make streaming request
        yield from $this->streamRequest($body, $modelInfo, $betas);
    }
    
    /**
     * Build messages in Anthropic format with cache control
     */
    protected function buildMessages(array $messages): array {
        $result = [];
        
        // Find indices of last two user messages for caching
        $userIndices = [];
        foreach ($messages as $i => $msg) {
            $role = $msg instanceof Message ? $msg->role : ($msg['role'] ?? '');
            if ($role === 'user') {
                $userIndices[] = $i;
            }
        }
        
        $lastUserIdx = end($userIndices) ?: -1;
        $secondLastUserIdx = count($userIndices) >= 2 
            ? $userIndices[count($userIndices) - 2] 
            : -1;
        
        foreach ($messages as $i => $message) {
            if ($message instanceof Message) {
                $role = $message->role;
                $content = $message->content;
            } else {
                $role = $message['role'];
                $content = $message['content'];
            }
            
            $formatted = ['role' => $role];
            
            // Add cache control to last two user messages
            if (($i === $lastUserIdx || $i === $secondLastUserIdx) && $role === 'user') {
                if (is_string($content)) {
                    $formatted['content'] = [
                        [
                            'type' => 'text',
                            'text' => $content,
                            'cache_control' => ['type' => 'ephemeral'],
                        ],
                    ];
                } else {
                    // Multi-part content
                    $formatted['content'] = $content;
                    // Add cache control to last part
                    $lastIdx = count($formatted['content']) - 1;
                    if ($lastIdx >= 0) {
                        $formatted['content'][$lastIdx]['cache_control'] = ['type' => 'ephemeral'];
                    }
                }
            } else {
                $formatted['content'] = is_string($content) 
                    ? $content 
                    : $content;
            }
            
            $result[] = $formatted;
        }
        
        return $result;
    }
    
    /**
     * Convert tools to Anthropic format
     */
    protected function convertToolsForAnthropic(?array $tools): ?array {
        if (!$tools) {
            return null;
        }
        
        return array_map(function($tool) {
            if (($tool['type'] ?? null) !== 'function') {
                return $tool;
            }
            
            return [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }, $tools);
    }
    
    /**
     * Make streaming request to Anthropic API
     */
    protected function streamRequest(array $body, ModelInfo $modelInfo, array $betas): Generator {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => 'Anthropic API key is required',
            ];
            return;
        }
        
        $url = rtrim($this->baseURL, '/') . '/messages';
        
        $headers = array_merge($this->defaultHeaders, [
            'x-api-key' => $apiKey,
            'anthropic-beta' => implode(',', $betas),
        ]);
        
        // Build header strings for cURL
        // Make cURL request for streaming
        
        $wp_response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 120,
        ]);

        if (is_wp_error($wp_response)) {
            $error = $wp_response->get_error_message();
            $response = '';
            $httpCode = 500;
        } else {
            $error = '';
            $response = wp_remote_retrieve_body($wp_response);
            $httpCode = wp_remote_retrieve_response_code($wp_response);
        }
        
        if ($error) {
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => "cURL error: {$error}",
            ];
            return;
        }
        
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errorMessage = $decoded['error']['message'] ?? "HTTP {$httpCode} error";
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => "Anthropic API Error ({$httpCode}): {$errorMessage}",
            ];
            return;
        }
        
        // Parse SSE response
        $inputTokens = 0;
        $outputTokens = 0;
        $cacheWriteTokens = 0;
        $cacheReadTokens = 0;
        
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === '' || strpos($line, 'event:') === 0) {
                continue;
            }
            
            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                $chunk = json_decode($json, true);
                
                if (!$chunk) {
                    continue;
                }
                
                $type = $chunk['type'] ?? null;
                
                switch ($type) {
                    case 'message_start':
                        // Track usage from message start
                        $usage = $chunk['message']['usage'] ?? [];
                        $inputTokens += $usage['input_tokens'] ?? 0;
                        $outputTokens += $usage['output_tokens'] ?? 0;
                        $cacheWriteTokens += $usage['cache_creation_input_tokens'] ?? 0;
                        $cacheReadTokens += $usage['cache_read_input_tokens'] ?? 0;
                        
                        yield [
                            'type' => StreamChunk::TYPE_USAGE,
                            'inputTokens' => $usage['input_tokens'] ?? 0,
                            'outputTokens' => $usage['output_tokens'] ?? 0,
                            'cacheWriteTokens' => $usage['cache_creation_input_tokens'] ?? null,
                            'cacheReadTokens' => $usage['cache_read_input_tokens'] ?? null,
                        ];
                        break;
                        
                    case 'message_delta':
                        // Track additional output tokens
                        $additionalTokens = $chunk['usage']['output_tokens'] ?? 0;
                        $outputTokens += $additionalTokens;
                        break;
                        
                    case 'content_block_start':
                        $block = $chunk['content_block'] ?? [];
                        $blockType = $block['type'] ?? null;
                        
                        if ($blockType === 'thinking') {
                            yield [
                                'type' => StreamChunk::TYPE_REASONING,
                                'text' => $block['thinking'] ?? '',
                            ];
                        } elseif ($blockType === 'text') {
                            yield [
                                'type' => StreamChunk::TYPE_TEXT,
                                'text' => $block['text'] ?? '',
                            ];
                        } elseif ($blockType === 'tool_use') {
                            yield [
                                'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                                'index' => $chunk['index'] ?? 0,
                                'id' => $block['id'] ?? null,
                                'name' => $block['name'] ?? null,
                                'arguments' => null,
                            ];
                        }
                        break;
                        
                    case 'content_block_delta':
                        $delta = $chunk['delta'] ?? [];
                        $deltaType = $delta['type'] ?? null;
                        
                        if ($deltaType === 'thinking_delta') {
                            yield [
                                'type' => StreamChunk::TYPE_REASONING,
                                'text' => $delta['thinking'] ?? '',
                            ];
                        } elseif ($deltaType === 'text_delta') {
                            yield [
                                'type' => StreamChunk::TYPE_TEXT,
                                'text' => $delta['text'] ?? '',
                            ];
                        } elseif ($deltaType === 'input_json_delta') {
                            yield [
                                'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                                'index' => $chunk['index'] ?? 0,
                                'id' => null,
                                'name' => null,
                                'arguments' => $delta['partial_json'] ?? '',
                            ];
                        }
                        break;
                        
                    case 'content_block_stop':
                        // Block complete
                        break;
                        
                    case 'message_stop':
                        // Message complete
                        break;
                }
            }
        }
        
        // Yield final usage with cost
        if ($inputTokens > 0 || $outputTokens > 0) {
            $cost = $this->calculateAnthropicCost(
                $modelInfo,
                $inputTokens,
                $outputTokens,
                $cacheWriteTokens,
                $cacheReadTokens
            );
            
            yield [
                'type' => StreamChunk::TYPE_USAGE,
                'inputTokens' => 0,
                'outputTokens' => 0,
                'totalCost' => $cost,
            ];
        }
    }
    
    /**
     * Calculate Anthropic API cost with cache pricing
     * Uses CostCalculator service for dynamic pricing
     */
    protected function calculateAnthropicCost(
        ModelInfo $modelInfo,
        int $inputTokens,
        int $outputTokens,
        int $cacheWriteTokens,
        int $cacheReadTokens
    ): float {
        // Use the new CostCalculator service
        $calculator = new \Quarksol\SmartChatbot\Analytics\CostCalculator();
        
        $modelId = $this->getModelId();
        $modelId = str_replace(':thinking', '', $modelId); // Remove thinking suffix
        
        return $calculator->calculateCost(
            'anthropic',
            $modelId,
            $inputTokens,
            $outputTokens,
            $cacheWriteTokens,
            $cacheReadTokens,
            'anthropic'  // Anthropic token counting protocol
        );
    }
    
    /**
     * Complete a simple prompt (non-streaming)
     */
    public function completePrompt(string $prompt): string {
        $model = $this->getModel();
        $apiKey = $this->getApiKey();
        
        // Check for API key before making request
        if (empty($apiKey)) {
            throw new \Exception("[Anthropic] API key is required. Please configure your Anthropic API key in Settings.");
        }
        
        $url = rtrim($this->baseURL, '/') . '/messages';
        
        $body = [
            'model' => $model['id'],
            'max_tokens' => $model['maxTokens'],
            'temperature' => $model['temperature'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        
        $response = wp_remote_post($url, [
            'headers' => array_merge($this->defaultHeaders, [
                'x-api-key' => $apiKey,
            ]),
            'body' => json_encode($body),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception("[Anthropic] Network error: " . $response->get_error_message());
        }
        
        $httpCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $decoded = json_decode($responseBody, true);
        
        // Check for API error response
        if (isset($decoded['error'])) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown API error';
            $errorType = $decoded['error']['type'] ?? '';
            
            // Build detailed error message
            $detailedError = "[Anthropic] {$errorMessage}";
            if ($errorType) {
                $detailedError .= " (Type: {$errorType})";
            }
            
            throw new \Exception($detailedError);
        }
        
        // Check HTTP status code
        if ($httpCode >= 400) {
            $errorMessage = "[Anthropic] HTTP {$httpCode} error";
            if (!empty($responseBody)) {
                $errorMessage .= ": " . substr($responseBody, 0, 500);
            }
            throw new \Exception($errorMessage);
        }
        
        // Find text content in response
        foreach ($decoded['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                return $block['text'] ?? '';
            }
        }
        
        // No text content found
        throw new \Exception("[Anthropic] Unexpected response format: " . substr($responseBody, 0, 500));
    }
}
