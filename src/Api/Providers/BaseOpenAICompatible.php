<?php
declare(strict_types=1);


/**
 * Base OpenAI-Compatible Provider
 * 
 * 
 * Base class for providers that use OpenAI-compatible API format
 * (OpenAI, OpenRouter, Groq, DeepSeek, Together, Fireworks, etc.)
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
 * Base class for OpenAI-compatible API providers
 */
abstract class BaseOpenAICompatible extends BaseProvider {
    /** API base URL */
    protected string $baseURL;
    
    /** Default temperature */
    protected float $defaultTemperature = 0;
    
    /** Default model ID */
    protected string $defaultModelId;
    
    /** Available models for this provider */
    protected array $providerModels = [];
    
    /** Default headers sent with requests */
    protected array $defaultHeaders = [
        'Content-Type' => 'application/json',
        'HTTP-Referer' => 'https://chatbot-ai.com',
        'X-Title' => 'App',
    ];
    
    public function __construct(
        ProviderSettings $settings,
        string $providerName,
        string $baseURL,
        string $defaultModelId,
        array $providerModels = []
    ) {
        parent::__construct($settings);
        $this->providerName = $providerName;
        $this->baseURL = $baseURL;
        $this->defaultModelId = $defaultModelId;
        $this->providerModels = $providerModels;
    }
    
    /**
     * Get API key for requests
     */
    abstract protected function getApiKey(): ?string;
    
    /**
     * Get model ID for requests
     */
    protected function getModelId(): string {
        return $this->settings->apiModelId ?? $this->defaultModelId;
    }
    
    /**
     * Get current model info
     */
    public function getModel(): array {
        $id = $this->getModelId();
        $info = $this->providerModels[$id] ?? new ModelInfo(128000, false);
        
        return [
            'id' => $id,
            'info' => $info,
        ];
    }
    
    /**
     * Create message stream
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelId = $model['id'];
        $modelInfo = $model['info'];
        
        // Calculate max tokens (default to model max or reasonable default)
        $maxTokens = $this->settings->modelMaxTokens 
            ?? $modelInfo->maxTokens 
            ?? 4096;
        
        // Get temperature
        $temperature = $this->settings->modelTemperature 
            ?? $modelInfo->defaultTemperature 
            ?? $this->defaultTemperature;
        
        // Build OpenAI-format messages
        $openAiMessages = $this->buildMessages($systemPrompt, $messages);
        
        // Build request body
        $body = [
            'model' => $modelId,
            'messages' => $openAiMessages,
            'stream' => true,
        ];
        
        // Helper to check if model requires max_completion_tokens
        $isModernModel = str_starts_with($modelId, 'o1-') 
            || str_starts_with($modelId, 'o3-') 
            || str_starts_with($modelId, 'gpt-4o')
            || str_starts_with($modelId, 'gpt-4.1')
            || str_starts_with($modelId, 'gpt-5');
        
        // Auto-detect Azure based on setting OR URL
        $isAzure = $this->settings->openAiUseAzure || strpos($this->baseURL, 'azure.com') !== false;

        if ($isAzure) {
             // Azure OpenAI strict parameter handling
             $body['max_completion_tokens'] = $maxTokens;
             
             // Azure "mini" models often reject temperature if not 1.0 or if passed at all in some API versions
             // gpt-5 models on Azure also reject non-default temperature
             if (str_contains($modelId, 'mini') || str_starts_with($modelId, 'o1') || str_starts_with($modelId, 'o3') || str_starts_with($modelId, 'gpt-5')) {
                  // Don't send temperature for these models on Azure
             } else {
                  $body['temperature'] = $temperature;
             }
        } elseif (str_starts_with($modelId, 'o1-') || str_starts_with($modelId, 'o3-')) {
            // Standard OpenAI O1/O3
            $body['max_completion_tokens'] = $maxTokens;
            $body['stream_options'] = ['include_usage' => true];
        } elseif ($isModernModel) {
            // GPT-4o, GPT-5 native
            $body['max_completion_tokens'] = $maxTokens;
            $body['temperature'] = $temperature;
            $body['stream_options'] = ['include_usage' => true];
        } else {
            // Legacy
            $body['max_tokens'] = $maxTokens;
            $body['temperature'] = $temperature;
            $body['stream_options'] = ['include_usage' => true];
        }
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForOpenAI($metadata->tools);
        }
        if ($metadata?->toolChoice) {
            $body['tool_choice'] = $metadata->toolChoice;
        }
        if ($metadata?->parallelToolCalls !== null) {
            $body['parallel_tool_calls'] = $metadata->parallelToolCalls;
        }
        
        // Add reasoning parameters if supported
        if ($this->settings->enableReasoningEffort && $modelInfo->supportsReasoningBinary) {
            $body['thinking'] = ['type' => 'enabled'];
        }
        
        // Make streaming request
        yield from $this->streamRequest($body, $modelInfo);
    }
    
    /**
     * Build OpenAI-format messages from our message array
     */
    protected function buildMessages(string $systemPrompt, array $messages): array {
        $result = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $result[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            } else {
                // Already in array format
                $result[] = $message;
            }
        }
        
        return $result;
    }
    
    /**
     * Make streaming HTTP request and yield chunks
     */
    protected function streamRequest(array $body, ModelInfo $modelInfo): Generator {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => 'API key is required for ' . $this->providerName,
            ];
            return;
        }
        
        // Azure OpenAI uses different URL format and authentication
        if ($this->settings->openAiUseAzure) {
            $modelId = $body['model'] ?? $this->getModelId();
            $apiVersion = $this->settings->azureApiVersion ?? '2024-05-01-preview';
            
            // Check if base URL already contains the deployment path
            $baseUrl = rtrim($this->baseURL, '/');
            if (str_contains($baseUrl, '/openai/deployments/')) {
                // User provided full deployment URL, just append chat/completions
                $url = $baseUrl . '/chat/completions?api-version=' . $apiVersion;
            } else {
                // User provided just the resource endpoint, build full URL
                $url = $baseUrl . '/openai/deployments/' . $modelId . '/chat/completions?api-version=' . $apiVersion;
            }
            
            $headers = array_merge($this->defaultHeaders, [
                'api-key' => $apiKey,
            ]);
        } else {
            $url = rtrim($this->baseURL, '/') . '/chat/completions';
            
            $headers = array_merge($this->defaultHeaders, [
                'Authorization' => 'Bearer ' . $apiKey,
            ]);
        }
        
        // Use WordPress HTTP API with streaming
        // Note: For true SSE streaming, you'd need cURL directly
        $response = $this->makeStreamingRequest($url, $body, $headers);
        
        if (is_wp_error($response)) {
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => $response->get_error_message(),
            ];
            return;
        }
        
        $lastUsage = null;
        $activeToolCallIds = [];
        
        // Parse SSE stream
        foreach ($this->parseSSEResponse($response) as $chunk) {
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
        
        // Yield final usage
        if ($lastUsage) {
            $metrics = $this->processUsageMetrics($lastUsage, $modelInfo);
            yield $metrics->toArray();
        }
    }

    /**
     * Stream request using REAL-TIME streaming (for SSE endpoints)
     * 
     * Uses makeRealStreamingRequest() which yields parsed SSE data as it
     * arrives from the API, enabling true real-time streaming to clients.
     */
    protected function streamRequestRealtime(array $body, ModelInfo $modelInfo): Generator {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => 'API key is required for ' . $this->providerName,
            ];
            return;
        }

        // Build URL and headers (same logic as streamRequest)
        if ($this->settings->openAiUseAzure) {
            $modelId = $body['model'] ?? $this->getModelId();
            $apiVersion = $this->settings->azureApiVersion ?? '2024-05-01-preview';
            $baseUrl = rtrim($this->baseURL, '/');
            if (str_contains($baseUrl, '/openai/deployments/')) {
                $url = $baseUrl . '/chat/completions?api-version=' . $apiVersion;
            } else {
                $url = $baseUrl . '/openai/deployments/' . $modelId . '/chat/completions?api-version=' . $apiVersion;
            }
            $headers = array_merge($this->defaultHeaders, ['api-key' => $apiKey]);
        } else {
            $url = rtrim($this->baseURL, '/') . '/chat/completions';
            $headers = array_merge($this->defaultHeaders, ['Authorization' => 'Bearer ' . $apiKey]);
        }

        $activeToolCallIds = [];

        // Use real-time streaming — chunks arrive as they come from the API
        foreach ($this->makeRealStreamingRequest($url, $body, $headers) as $chunk) {
            if (isset($chunk['error'])) {
                yield [
                    'type' => StreamChunk::TYPE_ERROR,
                    'error' => $chunk['error']['message'] ?? 'Unknown error',
                ];
                continue;
            }

            $delta = $chunk['choices'][0]['delta'] ?? null;
            $finishReason = $chunk['choices'][0]['finish_reason'] ?? null;

            if (isset($delta['content']) && $delta['content'] !== '') {
                yield [
                    'type' => StreamChunk::TYPE_TEXT,
                    'text' => $delta['content'],
                ];
            }

            // Tool calls
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

            if ($finishReason === 'tool_calls' && !empty($activeToolCallIds)) {
                foreach (array_keys($activeToolCallIds) as $id) {
                    yield ['type' => StreamChunk::TYPE_TOOL_CALL_END, 'id' => $id];
                }
                $activeToolCallIds = [];
            }

            // Usage
            if (isset($chunk['usage'])) {
                $metrics = $this->processUsageMetrics($chunk['usage'], $modelInfo);
                yield $metrics->toArray();
            }
        }
    }

    /**
     * Create message stream with REAL-TIME delivery
     * 
     * Same as createMessage() but uses curl_multi for true real-time 
     * streaming instead of waiting for the full response.
     */
    public function createMessageRealtime(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelId = $model['id'];
        $modelInfo = $model['info'];

        $maxTokens = $this->settings->modelMaxTokens ?? $modelInfo->maxTokens ?? 4096;
        $temperature = $this->settings->modelTemperature ?? $modelInfo->defaultTemperature ?? $this->defaultTemperature;

        $openAiMessages = $this->buildMessages($systemPrompt, $messages);

        $body = [
            'model' => $modelId,
            'messages' => $openAiMessages,
            'stream' => true,
        ];

        $isAzure = $this->settings->openAiUseAzure || strpos($this->baseURL, 'azure.com') !== false;

        if ($isAzure) {
            $body['max_completion_tokens'] = $maxTokens;
            if (str_contains($modelId, 'mini') || str_starts_with($modelId, 'o1') || str_starts_with($modelId, 'o3') || str_starts_with($modelId, 'gpt-5')) {
                // Don't send temperature
            } else {
                $body['temperature'] = $temperature;
            }
        } elseif (str_starts_with($modelId, 'o1-') || str_starts_with($modelId, 'o3-')) {
            $body['max_completion_tokens'] = $maxTokens;
            $body['stream_options'] = ['include_usage' => true];
        } else {
            $body['max_completion_tokens'] = $maxTokens;
            $body['temperature'] = $temperature;
            $body['stream_options'] = ['include_usage' => true];
        }

        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForOpenAI($metadata->tools);
        }
        if ($metadata?->toolChoice) {
            $body['tool_choice'] = $metadata->toolChoice;
        }

        yield from $this->streamRequestRealtime($body, $modelInfo);
    }

    /**
     * Make streaming HTTP request using cURL (blocking - waits for full response)
     */
    protected function makeStreamingRequest(string $url, array $body, array $headers) {
        
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
            return new \WP_Error('curl_error', $error);
        }
        
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $message = $decoded['error']['message'] ?? "HTTP {$httpCode} error";
            return new \WP_Error('http_error', $message);
        }
        
        return $response;
    }

    /**
     * Make a TRUE streaming HTTP request using a write callback.
     * 
     * Instead of waiting for the entire response, this yields parsed SSE 
     * chunks as they arrive from the API in real-time.
     * 
     * @param string $url
     * @param array $body
     * @param array $headers
     * @return Generator yields parsed SSE data arrays
     */
    protected function makeRealStreamingRequest(string $url, array $body, array $headers): Generator {
        $response = $this->makeStreamingRequest($url, $body, $headers);
        if (is_wp_error($response)) {
            yield ['error' => ['message' => $response->get_error_message()]];
            return;
        }
        foreach ($this->parseSSEResponse($response) as $chunk) {
            yield $chunk;
        }
    }

    /**
     * Parse SSE response into chunks
     */
    protected function parseSSEResponse(string $response): Generator {
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }
            
            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                $decoded = json_decode($json, true);
                
                if ($decoded) {
                    yield $decoded;
                }
            }
        }
    }
    
    /**
     * Complete a simple prompt (non-streaming)
     */
    public function completePrompt(string $prompt): string {
        $model = $this->getModel();
        $modelId = $model['id'];
        
        $body = [
            'model' => $modelId,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        
        if ($this->settings->enableReasoningEffort && $model['info']->supportsReasoningBinary) {
            $body['thinking'] = ['type' => 'enabled'];
        }
        
        $apiKey = $this->getApiKey();
        
        // Check for API key before making request
        if (empty($apiKey)) {
            throw new \Exception("API key is required for {$this->providerName}. Please configure your API key in Settings.");
        }
        
        // Azure OpenAI uses different URL format and authentication
        $isAzure = $this->settings->openAiUseAzure || strpos($this->baseURL, 'azure.com') !== false;
        
        if ($isAzure) {
            $apiVersion = $this->settings->azureApiVersion ?? '2024-05-01-preview';
            $baseUrl = rtrim($this->baseURL, '/');
            
            if (str_contains($baseUrl, '/openai/deployments/')) {
                // User provided full deployment URL
                $url = $baseUrl . '/chat/completions?api-version=' . $apiVersion;
            } else {
                // User provided just the resource endpoint
                $url = $baseUrl . '/openai/deployments/' . $modelId . '/chat/completions?api-version=' . $apiVersion;
            }
            
            $headers = array_merge($this->defaultHeaders, [
                'api-key' => $apiKey,
            ]);
            
            // Azure reasoning/mini models don't support temperature
            if (str_contains($modelId, 'mini') || str_starts_with($modelId, 'o1') || str_starts_with($modelId, 'o3') || str_starts_with($modelId, 'gpt-5')) {
                // Don't send temperature
            } else {
                $body['temperature'] = $this->settings->modelTemperature ?? $this->defaultTemperature;
            }
        } else {
            $url = rtrim($this->baseURL, '/') . '/chat/completions';
            $headers = array_merge($this->defaultHeaders, [
                'Authorization' => 'Bearer ' . $apiKey,
            ]);
        }
        
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception("Network error: " . $response->get_error_message());
        }
        
        $httpCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $decoded = json_decode($responseBody, true);
        
        // Check HTTP status code FIRST (before checking response structure)
        if ($httpCode >= 400) {
            // Try to extract error from various response formats
            $errorMessage = null;
            
            // Standard OpenAI format: {"error": {"message": "..."}}
            if (isset($decoded['error']['message'])) {
                $errorMessage = $decoded['error']['message'];
                $errorCode = $decoded['error']['code'] ?? $decoded['error']['type'] ?? '';
                if ($errorCode) {
                    $errorMessage .= " (Code: {$errorCode})";
                }
            }
            // HuggingFace format: {"error": "string message"}
            elseif (isset($decoded['error']) && is_string($decoded['error'])) {
                $errorMessage = $decoded['error'];
            }
            // HuggingFace alternate: {"message": "..."}
            elseif (isset($decoded['message']) && is_string($decoded['message'])) {
                $errorMessage = $decoded['message'];
            }
            // Raw response
            elseif (!empty($responseBody)) {
                $errorMessage = substr($responseBody, 0, 500);
            }
            else {
                $errorMessage = "HTTP {$httpCode} error";
            }
            
            throw new \Exception("[{$this->providerName}] {$errorMessage}");
        }
        
        // Check for API error response in successful HTTP responses
        if (isset($decoded['error'])) {
            $errorMessage = is_string($decoded['error']) 
                ? $decoded['error'] 
                : ($decoded['error']['message'] ?? 'Unknown API error');
            $errorCode = is_array($decoded['error']) 
                ? ($decoded['error']['code'] ?? $decoded['error']['type'] ?? '') 
                : '';
            
            // Build detailed error message
            $detailedError = "[{$this->providerName}] {$errorMessage}";
            if ($errorCode) {
                $detailedError .= " (Code: {$errorCode})";
            }
            
            throw new \Exception($detailedError);
        }
        
        // Check for missing response content
        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new \Exception("[{$this->providerName}] Unexpected response format: " . substr($responseBody, 0, 500));
        }
        
        return $decoded['choices'][0]['message']['content'];
    }
}
