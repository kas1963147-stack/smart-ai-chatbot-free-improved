<?php
declare(strict_types=1);


/**
 * DeepSeek Provider
 * 
 * 
 * Supports DeepSeek-R1 thinking mode
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
 * DeepSeek model definitions
 */
const DEEPSEEK_MODELS = [
    'deepseek-chat' => [
        'contextWindow' => 64000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => true,
        'inputPrice' => 0.14,
        'outputPrice' => 0.28,
        'cacheReadsPrice' => 0.014,
    ],
    'deepseek-reasoner' => [
        'contextWindow' => 64000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => true,
        'supportsReasoningBinary' => true,
        'inputPrice' => 0.55,
        'outputPrice' => 2.19,
    ],
];

const DEEPSEEK_DEFAULT_MODEL = 'deepseek-chat';
const DEEPSEEK_DEFAULT_TEMPERATURE = 0.6;

/**
 * DeepSeek API Provider
 * 
 * Supports thinking mode for deepseek-reasoner model
 */
class DeepSeek extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'DeepSeek',
            $settings->deepSeekBaseUrl ?? 'https://api.deepseek.com',
            DEEPSEEK_DEFAULT_MODEL,
            self::buildModels()
        );
        
        $this->defaultTemperature = DEEPSEEK_DEFAULT_TEMPERATURE;
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->deepSeekApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->apiModelId ?? DEEPSEEK_DEFAULT_MODEL;
    }
    
    /**
     * Override createMessage for DeepSeek-specific handling
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $modelId = $this->getModelId();
        $model = $this->getModel();
        $modelInfo = $model['info'];
        
        // Check if this is a thinking model (deepseek-reasoner)
        $isThinkingModel = strpos($modelId, 'deepseek-reasoner') !== false;
        
        // DeepSeek doesn't support successive same-role messages
        // Merge system prompt into first user message
        $mergedMessages = $this->mergeR1Format($systemPrompt, $messages);
        
        // Calculate max tokens
        $maxTokens = $this->settings->modelMaxTokens ?? $modelInfo->maxTokens ?? 8192;
        $temperature = $this->settings->modelTemperature ?? DEEPSEEK_DEFAULT_TEMPERATURE;
        
        // Build request body
        $body = [
            'model' => $modelId,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $mergedMessages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ];
        
        // Enable thinking for reasoner model
        if ($isThinkingModel) {
            $body['thinking'] = ['type' => 'enabled'];
        }
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForOpenAI($metadata->tools);
        }
        if ($metadata?->toolChoice) {
            $body['tool_choice'] = $metadata->toolChoice;
        }
        
        // Make streaming request
        yield from $this->streamRequestDeepSeek($body, $modelInfo);
    }
    
    /**
     * Merge messages into R1 format (no consecutive same-role messages)
     */
    protected function mergeR1Format(string $systemPrompt, array $messages): array {
        $result = [
            ['role' => 'user', 'content' => $systemPrompt],
        ];
        
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $role = $message->role;
                $content = $message->content;
            } else {
                $role = $message['role'] ?? 'user';
                $content = $message['content'] ?? '';
            }
            
            // Skip system messages (already handled)
            if ($role === 'system') {
                continue;
            }
            
            // Check if we need to merge with previous message
            $lastIdx = count($result) - 1;
            if ($lastIdx >= 0 && $result[$lastIdx]['role'] === $role) {
                // Merge content
                $prevContent = $result[$lastIdx]['content'];
                if (is_string($prevContent) && is_string($content)) {
                    $result[$lastIdx]['content'] = $prevContent . "\n\n" . $content;
                }
            } else {
                $result[] = ['role' => $role, 'content' => $content];
            }
        }
        
        return $result;
    }
    
    /**
     * Stream request with DeepSeek-specific reasoning_content handling
     */
    protected function streamRequestDeepSeek(array $body, ModelInfo $modelInfo): Generator {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            yield [
                'type' => StreamChunk::TYPE_ERROR,
                'error' => 'DeepSeek API key is required',
            ];
            return;
        }
        
        $url = rtrim($this->baseURL, '/') . '/chat/completions';
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ];
        
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
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => "cURL error: {$error}"];
            return;
        }
        
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errorMessage = $decoded['error']['message'] ?? "HTTP {$httpCode} error";
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => "DeepSeek API Error: {$errorMessage}"];
            return;
        }
        
        $lastUsage = null;
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'data: [DONE]') continue;
            
            if (strpos($line, 'data: ') === 0) {
                $chunk = json_decode(substr($line, 6), true);
                if (!$chunk) continue;
                
                $delta = $chunk['choices'][0]['delta'] ?? [];
                
                // Regular text content
                if (isset($delta['content']) && $delta['content'] !== '') {
                    yield ['type' => StreamChunk::TYPE_TEXT, 'text' => $delta['content']];
                }
                
                // Reasoning content (DeepSeek-R1 thinking)
                if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
                    yield ['type' => StreamChunk::TYPE_REASONING, 'text' => $delta['reasoning_content']];
                }
                
                // Tool calls
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $toolCall) {
                        yield [
                            'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                            'index' => $toolCall['index'] ?? 0,
                            'id' => $toolCall['id'] ?? null,
                            'name' => $toolCall['function']['name'] ?? null,
                            'arguments' => $toolCall['function']['arguments'] ?? null,
                        ];
                    }
                }
                
                if (isset($chunk['usage'])) {
                    $lastUsage = $chunk['usage'];
                }
            }
        }
        
        if ($lastUsage) {
            yield [
                'type' => StreamChunk::TYPE_USAGE,
                'inputTokens' => $lastUsage['prompt_tokens'] ?? 0,
                'outputTokens' => $lastUsage['completion_tokens'] ?? 0,
                'cacheWriteTokens' => $lastUsage['prompt_tokens_details']['cache_miss_tokens'] ?? null,
                'cacheReadTokens' => $lastUsage['prompt_tokens_details']['cached_tokens'] ?? null,
            ];
        }
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (DEEPSEEK_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
