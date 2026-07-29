<?php
declare(strict_types=1);


/**
 * OpenRouter Provider
 * 
 * 
 * OpenRouter provides access to 200+ AI models through a single API
 * This is the recommended provider for maximum model coverage with zero maintenance
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
 * Default OpenRouter model
 */
const OPENROUTER_DEFAULT_MODEL = 'anthropic/claude-3.5-sonnet';

/**
 * Default model info for when model data isn't available
 */
const OPENROUTER_DEFAULT_MODEL_INFO = [
    'contextWindow' => 128000,
    'maxTokens' => 8192,
    'supportsPromptCache' => true,
    'supportsImages' => true,
    'inputPrice' => 3.0,
    'outputPrice' => 15.0,
];

/**
 * OpenRouter API Provider
 * 
 * Routes requests to 200+ AI models including:
 * - OpenAI (GPT-4, GPT-4o, o1, o3)
 * - Anthropic (Claude 3.5, Claude 3)
 * - Google (Gemini Pro, Gemini Flash)
 * - Meta (Llama 3, Llama 3.1)
 * - Mistral (Mistral Large, Mixtral)
 * - And many more...
 */
class OpenRouter extends BaseProvider {
    protected string $providerName = 'OpenRouter';
    
    /** Base API URL */
    protected string $baseURL;
    
    /** Cached model list */
    protected array $models = [];
    
    /** Cached model endpoints */
    protected array $endpoints = [];
    
    /** Accumulated reasoning details for current request */
    protected array $currentReasoningDetails = [];
    
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        
        $this->baseURL = $settings->openRouterBaseUrl ?? 'https://openrouter.ai/api/v1';
        
        // Load models asynchronously
        $this->loadDynamicModels();
    }
    
    /**
     * Load available models from OpenRouter API
     */
    protected function loadDynamicModels(): void {
        // Models are loaded on-demand when needed
        // OpenRouter returns models dynamically via /models endpoint
    }
    
    /**
     * Get API key
     */
    protected function getApiKey(): ?string {
        return $this->settings->openRouterApiKey ?? null;
    }
    
    /**
     * Get model ID
     */
    protected function getModelId(): string {
        return $this->settings->openRouterModelId ?? OPENROUTER_DEFAULT_MODEL;
    }
    
    /**
     * Get accumulated reasoning details
     */
    public function getReasoningDetails(): ?array {
        return !empty($this->currentReasoningDetails) ? $this->currentReasoningDetails : null;
    }
    
    /**
     * Get current model info
     */
    public function getModel(): array {
        $id = $this->getModelId();
        
        // Check cached models first
        $info = $this->models[$id] ?? null;
        
        if (!$info) {
            // Use default info if model not in cache
            $info = ModelInfo::fromArray(OPENROUTER_DEFAULT_MODEL_INFO);
        }
        
        return [
            'id' => $id,
            'info' => $info,
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
        
        // Reset reasoning details for this request
        $this->currentReasoningDetails = [];
        
        // Calculate max tokens
        $maxTokens = $this->settings->modelMaxTokens ?? $modelInfo->maxTokens ?? 8192;
        
        // Get temperature
        $temperature = $this->settings->modelTemperature ?? $modelInfo->defaultTemperature ?? 0;
        
        // Build messages in OpenAI format
        $openAiMessages = $this->buildMessages($systemPrompt, $messages, $modelId);
        
        // Build request body
        $body = [
            'model' => $modelId,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $openAiMessages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ];
        
        // Add specific provider routing if configured
        if ($this->settings->openRouterSpecificProvider) {
            $body['provider'] = [
                'order' => [$this->settings->openRouterSpecificProvider],
            ];
        }
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForOpenRouter($metadata->tools);
        }
        if ($metadata?->toolChoice) {
            $body['tool_choice'] = $metadata->toolChoice;
        }
        if ($metadata?->parallelToolCalls !== null) {
            $body['parallel_tool_calls'] = $metadata->parallelToolCalls;
        }
        
        // Add reasoning parameters for thinking models
        if ($this->settings->enableReasoningEffort) {
            $body['include_reasoning'] = true;
            
            if ($this->settings->reasoningEffort) {
                $body['reasoning'] = [
                    'effort' => $this->settings->reasoningEffort,
                ];
            }
        }
        
        // Make streaming request
        yield from $this->streamRequest($body, $modelInfo);
    }
    
    /**
     * Build messages in OpenAI format with OpenRouter-specific handling
     */
    protected function buildMessages(string $systemPrompt, array $messages, string $modelId): array {
        $result = [];
        
        // DeepSeek R1 models prefer user role instead of system
        $isDeepSeekR1 = strpos($modelId, 'deepseek/deepseek-r1') === 0;
        $isPerplexitySonar = $modelId === 'perplexity/sonar-reasoning';
        
        if ($isDeepSeekR1 || $isPerplexitySonar) {
            // Use user role for system prompt
            $result[] = ['role' => 'user', 'content' => $systemPrompt];
        } else {
            $result[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $formatted = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
                
                if ($message->toolCalls) {
                    $formatted['tool_calls'] = $message->toolCalls;
                }
                if ($message->toolCallId) {
                    $formatted['tool_call_id'] = $message->toolCallId;
                }
                
                $result[] = $formatted;
            } else {
                $result[] = $message;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert tools for OpenRouter (handles MCP tools and strict mode)
     */
    protected function convertToolsForOpenRouter(?array $tools): ?array {
        if (!$tools) {
            return null;
        }
        
        return array_map(function($tool) {
            if (($tool['type'] ?? null) !== 'function') {
                return $tool;
            }
            
            $isMcp = strpos($tool['function']['name'] ?? '', 'mcp--') === 0;
            
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['function']['name'],
                    'description' => $tool['function']['description'] ?? '',
                    'strict' => false,
                    'parameters' => $isMcp 
                        ? ($tool['function']['parameters'] ?? [])
                        : $this->convertToolSchemaForOpenAI($tool['function']['parameters'] ?? []),
                ],
            ];
        }, $tools);
    }
    
    /**
     * Make streaming request
     */
    protected function streamRequest(array $body, ModelInfo $modelInfo): Generator {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => 'OpenRouter API key is required',
            ];
            return;
        }
        
        $url = rtrim($this->baseURL, '/') . '/chat/completions';
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
            'HTTP-Referer' => get_site_url(),
            'X-Title' => get_bloginfo('name') ?: 'App',
        ];
        
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
            $errorMessage = $this->extractErrorMessage($decoded);
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => "OpenRouter API Error ({$httpCode}): {$errorMessage}",
            ];
            return;
        }
        
        // Parse SSE response
        $lastUsage = null;
        $activeToolCallIds = [];
        
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }
            
            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                $chunk = json_decode($json, true);
                
                if (!$chunk) {
                    continue;
                }
                
                // Check for errors in chunk
                if (isset($chunk['error'])) {
                    yield [
                        'type' => StreamChunk::TYPE_ERROR,
                        'error' => $chunk['error']['message'] ?? 'Unknown error',
                    ];
                    continue;
                }
                
                $delta = $chunk['choices'][0]['delta'] ?? null;
                $finishReason = $chunk['choices'][0]['finish_reason'] ?? null;
                
                // Yield text content
                if (isset($delta['content']) && $delta['content'] !== '') {
                    yield [
                        'type' => StreamChunk::TYPE_TEXT,
                        'text' => $delta['content'],
                    ];
                }
                
                // Yield reasoning content
                foreach (['reasoning_content', 'reasoning'] as $key) {
                    if (isset($delta[$key]) && trim($delta[$key]) !== '') {
                        yield [
                            'type' => StreamChunk::TYPE_REASONING,
                            'text' => $delta[$key],
                        ];
                        break;
                    }
                }
                
                // Yield tool calls
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $toolCall) {
                        if (isset($toolCall['id'])) {
                            $activeToolCallIds[$toolCall['id']] = true;
                        }
                        yield [
                            'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                            'index' => $toolCall['index'] ?? 0,
                            'id' => $toolCall['id'] ?? null,
                            'name' => $toolCall['function']['name'] ?? null,
                            'arguments' => $toolCall['function']['arguments'] ?? null,
                        ];
                    }
                }
                
                // Finalize tool calls
                if ($finishReason === 'tool_calls' && !empty($activeToolCallIds)) {
                    foreach (array_keys($activeToolCallIds) as $id) {
                        yield [
                            'type' => StreamChunk::TYPE_TOOL_CALL_END,
                            'id' => $id,
                        ];
                    }
                    $activeToolCallIds = [];
                }
                
                // Track usage
                if (isset($chunk['usage'])) {
                    $lastUsage = $chunk['usage'];
                }
            }
        }
        
        // Yield final usage
        if ($lastUsage) {
            $metrics = $this->processUsageMetrics($lastUsage, $modelInfo);
            yield $metrics->toArray();
        }
    }
    
    /**
     * Extract error message from OpenRouter error response
     */
    protected function extractErrorMessage(array $response): string {
        // Check for metadata.raw which may contain upstream provider error
        $rawMetadata = $response['error']['metadata']['raw'] ?? null;
        
        if ($rawMetadata) {
            $parsed = json_decode($rawMetadata, true);
            if ($parsed) {
                if (isset($parsed['message'])) {
                    return $parsed['message'];
                }
                if (isset($parsed['error']['message'])) {
                    return $parsed['error']['message'];
                }
                if (isset($parsed['error']) && is_string($parsed['error'])) {
                    return $parsed['error'];
                }
            }
            return $rawMetadata;
        }
        
        return $response['error']['message'] ?? 'Unknown error';
    }
    
    /**
     * Fetch available models from OpenRouter
     */
    public function fetchModels(): array {
        $url = rtrim($this->baseURL, '/') . '/models';
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $models = [];
        
        foreach ($body['data'] ?? [] as $model) {
            $id = $model['id'];
            $models[$id] = ModelInfo::fromArray([
                'contextWindow' => $model['context_length'] ?? 128000,
                'maxTokens' => $model['max_completion_tokens'] ?? 4096,
                'supportsImages' => in_array('image', $model['supported_modalities'] ?? []),
                'supportsPromptCache' => $model['supports_prompt_cache'] ?? false,
                'inputPrice' => (float)($model['pricing']['prompt'] ?? 0) * 1000000,
                'outputPrice' => (float)($model['pricing']['completion'] ?? 0) * 1000000,
                'description' => $model['description'] ?? null,
            ]);
        }
        
        $this->models = $models;
        return $models;
    }
    
    /**
     * Complete a simple prompt (non-streaming)
     * Used for connection testing and simple queries
     */
    public function completePrompt(string $prompt): string {
        $apiKey = $this->getApiKey();
        
        if (empty($apiKey)) {
            throw new \Exception("OpenRouter API key is required. Please configure your API key in Settings.");
        }
        
        $model = $this->getModel();
        $modelId = $model['id'];
        
        $url = rtrim($this->baseURL, '/') . '/chat/completions';
        
        $body = [
            'model' => $modelId,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 100,
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name') ?: 'Smart Chatbot',
            ],
            'body' => json_encode($body),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception("Network error: " . $response->get_error_message());
        }
        
        $httpCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $decoded = json_decode($responseBody, true);
        
        // Check HTTP status code first
        if ($httpCode >= 400) {
            $errorMessage = $this->extractErrorMessage($decoded ?: []);
            throw new \Exception("[OpenRouter] {$errorMessage}");
        }
        
        // Check for error in response body
        if (isset($decoded['error'])) {
            $errorMessage = $this->extractErrorMessage($decoded);
            throw new \Exception("[OpenRouter] {$errorMessage}");
        }
        
        // Check for data wrapper (some error formats)
        if (isset($decoded['data']['message'])) {
            throw new \Exception("[OpenRouter] " . $decoded['data']['message']);
        }
        
        // Extract content from response
        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new \Exception("[OpenRouter] Unexpected response format: " . substr($responseBody, 0, 500));
        }
        
        return $decoded['choices'][0]['message']['content'];
    }
}
