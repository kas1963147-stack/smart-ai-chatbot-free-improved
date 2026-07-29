<?php
declare(strict_types=1);


/**
 * Ollama Provider
 * 
 * 
 * Run models locally with Ollama
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

const OLLAMA_DEFAULT_MODEL = 'llama3.1:8b';
const OLLAMA_DEFAULT_URL = 'http://localhost:11434';

/**
 * Ollama Local Provider
 * 
 * Uses native Ollama API (not OpenAI-compatible endpoint)
 */
class Ollama extends BaseProvider {
    protected string $providerName = 'Ollama';
    protected string $baseURL;
    
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        $this->baseURL = rtrim($settings->ollamaBaseUrl ?? OLLAMA_DEFAULT_URL, '/');
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->ollamaApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->ollamaModelId ?? OLLAMA_DEFAULT_MODEL;
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        
        // Ollama models are dynamic, create default info
        $info = new ModelInfo(
            $this->settings->ollamaNumCtx ?? 8192, // contextWindow
            false // supportsImages
        );
        
        return ['id' => $id, 'info' => $info];
    }
    
    /**
     * Create streaming message using Ollama's native API
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelId = $model['id'];
        
        // Build messages in Ollama format
        $ollamaMessages = [];
        $ollamaMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $ollamaMessages[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            } else {
                $ollamaMessages[] = $message;
            }
        }
        
        $body = [
            'model' => $modelId,
            'messages' => $ollamaMessages,
            'stream' => true,
            'options' => [
                'num_ctx' => $this->settings->ollamaNumCtx ?? 8192,
            ],
        ];
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForOllama($metadata->tools);
        }
        
        yield from $this->streamOllamaRequest($body);
    }
    
    /**
     * Convert OpenAI-format tools to Ollama format
     */
    protected function convertToolsForOllama(?array $tools): ?array {
        if (!$tools) {
            return null;
        }
        
        return array_map(function($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['function']['name'] ?? '',
                    'description' => $tool['function']['description'] ?? '',
                    'parameters' => $tool['function']['parameters'] ?? [],
                ],
            ];
        }, $tools);
    }
    
    /**
     * Stream request to Ollama's native API
     */
    protected function streamOllamaRequest(array $body): Generator {
        $url = $this->baseURL . '/api/chat';
        
        $headers = ['Content-Type: application/json'];
        
        // Add API key if provided
        if ($apiKey = $this->getApiKey()) {
            $headers[] = "Authorization: Bearer {$apiKey}";
        }
        
        
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
            $errorMessage = $decoded['error'] ?? "HTTP {$httpCode} error";
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => "Ollama Error: {$errorMessage}"];
            return;
        }
        
        // Ollama streams JSON objects separated by newlines
        $lines = explode("\n", $response);
        $totalPromptTokens = 0;
        $totalEvalTokens = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            
            $chunk = json_decode($line, true);
            if (!$chunk) continue;
            
            // Text content
            if (isset($chunk['message']['content']) && $chunk['message']['content'] !== '') {
                yield ['type' => StreamChunk::TYPE_TEXT, 'text' => $chunk['message']['content']];
            }
            
            // Tool calls
            if (isset($chunk['message']['tool_calls'])) {
                foreach ($chunk['message']['tool_calls'] as $i => $toolCall) {
                    yield [
                        'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                        'index' => $i,
                        'id' => "ollama-" . uniqid(),
                        'name' => $toolCall['function']['name'] ?? null,
                        'arguments' => json_encode($toolCall['function']['arguments'] ?? []),
                    ];
                }
            }
            
            // Track tokens from final message
            if (isset($chunk['prompt_eval_count'])) {
                $totalPromptTokens = $chunk['prompt_eval_count'];
            }
            if (isset($chunk['eval_count'])) {
                $totalEvalTokens = $chunk['eval_count'];
            }
            
            // Done indicator
            if (isset($chunk['done']) && $chunk['done'] === true) {
                break;
            }
        }
        
        // Yield usage
        if ($totalPromptTokens > 0 || $totalEvalTokens > 0) {
            yield [
                'type' => StreamChunk::TYPE_USAGE,
                'inputTokens' => $totalPromptTokens,
                'outputTokens' => $totalEvalTokens,
            ];
        }
    }
    
    /**
     * List available models from Ollama
     */
    public function listModels(): array {
        $url = $this->baseURL . '/api/tags';
        
        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array_map(function($model) {
            return [
                'id' => $model['name'],
                'name' => $model['name'],
                'size' => $model['size'] ?? 0,
            ];
        }, $body['models'] ?? []);
    }
}
