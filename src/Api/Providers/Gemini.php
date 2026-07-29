<?php
declare(strict_types=1);


/**
 * Google Gemini Provider
 * 
 * 
 * Google's Gemini Pro, Ultra, Flash models
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
 * Gemini model definitions
 */
/**
 * Gemini model definitions - Updated to current available models
 * Note: gemini-1.5-flash and gemini-1.5-pro are deprecated by Google
 */
const GEMINI_MODELS = [
    'gemini-2.0-flash' => [
        'contextWindow' => 1048576,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 0.10,
        'outputPrice' => 0.40,
    ],
    'gemini-2.0-flash-lite' => [
        'contextWindow' => 1048576,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 0.05,
        'outputPrice' => 0.20,
    ],
    'gemini-2.5-flash' => [
        'contextWindow' => 1048576,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 0.075,
        'outputPrice' => 0.30,
    ],
    'gemini-2.5-pro' => [
        'contextWindow' => 2097152,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 1.25,
        'outputPrice' => 5.0,
    ],
    'gemini-3-flash-preview' => [
        'contextWindow' => 1048576,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 0.10,
        'outputPrice' => 0.40,
    ],
];

const GEMINI_DEFAULT_MODEL = 'gemini-2.0-flash';

/**
 * Google Gemini API Provider
 */
class Gemini extends BaseProvider {
    protected string $providerName = 'Gemini';
    protected string $baseURL;
    
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        $this->baseURL = $settings->googleGeminiBaseUrl ?? 'https://generativelanguage.googleapis.com/v1beta';
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->geminiApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->apiModelId ?? GEMINI_DEFAULT_MODEL;
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $modelData = GEMINI_MODELS[$id] ?? GEMINI_MODELS[GEMINI_DEFAULT_MODEL];
        $info = ModelInfo::fromArray($modelData);
        
        return ['id' => $id, 'info' => $info];
    }
    
    /**
     * Create streaming message using Gemini API
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelId = $model['id'];
        $modelInfo = $model['info'];
        
        // Convert messages to Gemini format
        $geminiContents = $this->buildGeminiContents($messages);
        
        // Build request body
        $body = [
            'contents' => $geminiContents,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $this->settings->modelMaxTokens ?? $modelInfo->maxTokens ?? 8192,
                'temperature' => $this->settings->modelTemperature ?? 0,
            ],
        ];
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForGemini($metadata->tools);
        }
        
        // Enable thinking for thinking models
        if (strpos($modelId, 'thinking') !== false) {
            $body['generationConfig']['enableThinking'] = true;
        }
        
        yield from $this->streamGeminiRequest($modelId, $body, $modelInfo);
    }
    
    /**
     * Build Gemini-format contents from messages
     */
    protected function buildGeminiContents(array $messages): array {
        $contents = [];
        
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $role = $message->role === 'assistant' ? 'model' : 'user';
                $content = $message->content;
            } else {
                $role = ($message['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
                $content = $message['content'] ?? '';
            }
            
            if (is_string($content)) {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $content]],
                ];
            } else {
                // Multi-part content
                $parts = [];
                foreach ($content as $part) {
                    if (isset($part['text'])) {
                        $parts[] = ['text' => $part['text']];
                    } elseif (isset($part['image_url'])) {
                        $parts[] = [
                            'inlineData' => [
                                'mimeType' => 'image/jpeg',
                                'data' => $this->extractBase64($part['image_url']['url']),
                            ],
                        ];
                    }
                }
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }
        
        return $contents;
    }
    
    /**
     * Convert tools to Gemini format
     */
    protected function convertToolsForGemini(?array $tools): ?array {
        if (!$tools) {
            return null;
        }
        
        $functionDeclarations = array_map(function($tool) {
            return [
                'name' => $tool['function']['name'] ?? '',
                'description' => $tool['function']['description'] ?? '',
                'parameters' => $tool['function']['parameters'] ?? [],
            ];
        }, $tools);
        
        return [['functionDeclarations' => $functionDeclarations]];
    }
    
    /**
     * Extract base64 from data URL
     */
    protected function extractBase64(string $url): string {
        if (strpos($url, 'data:') === 0) {
            $parts = explode(',', $url);
            return $parts[1] ?? '';
        }
        return $url;
    }
    
    /**
     * Stream request to Gemini API
     */
    protected function streamGeminiRequest(string $modelId, array $body, ModelInfo $modelInfo): Generator {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => 'Gemini API key is required'];
            return;
        }
        
        $url = "{$this->baseURL}/models/{$modelId}:streamGenerateContent?key={$apiKey}&alt=sse";
        
        
        $wp_response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
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
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => "Gemini API Error: {$errorMessage}"];
            return;
        }
        
        $inputTokens = 0;
        $outputTokens = 0;
        
        // Parse SSE response
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) continue;
            
            $chunk = json_decode(substr($line, 6), true);
            if (!$chunk) continue;
            
            // Process candidates
            foreach ($chunk['candidates'] ?? [] as $candidate) {
                $content = $candidate['content'] ?? [];
                
                foreach ($content['parts'] ?? [] as $part) {
                    // Text content
                    if (isset($part['text'])) {
                        yield ['type' => StreamChunk::TYPE_TEXT, 'text' => $part['text']];
                    }
                    
                    // Thinking content
                    if (isset($part['thought'])) {
                        yield ['type' => StreamChunk::TYPE_REASONING, 'text' => $part['thought']];
                    }
                    
                    // Function calls
                    if (isset($part['functionCall'])) {
                        yield [
                            'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                            'index' => 0,
                            'id' => 'gemini-' . uniqid(),
                            'name' => $part['functionCall']['name'] ?? null,
                            'arguments' => json_encode($part['functionCall']['args'] ?? []),
                        ];
                    }
                }
            }
            
            // Track usage
            if (isset($chunk['usageMetadata'])) {
                $inputTokens = $chunk['usageMetadata']['promptTokenCount'] ?? 0;
                $outputTokens = $chunk['usageMetadata']['candidatesTokenCount'] ?? 0;
            }
        }
        
        if ($inputTokens > 0 || $outputTokens > 0) {
            yield [
                'type' => StreamChunk::TYPE_USAGE,
                'inputTokens' => $inputTokens,
                'outputTokens' => $outputTokens,
            ];
        }
    }
    
    /**
     * Complete a simple prompt (non-streaming)
     */
    public function completePrompt(string $prompt): string {
        $model = $this->getModel();
        $modelId = $model['id'];
        $apiKey = $this->getApiKey();
        
        // Check for API key before making request
        if (empty($apiKey)) {
            throw new \Exception("[Gemini] API key is required. Please configure your Google Gemini API key in Settings.");
        }
        
        $url = "{$this->baseURL}/models/{$modelId}:generateContent?key={$apiKey}";
        
        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $this->settings->modelMaxTokens ?? 8192,
                'temperature' => $this->settings->modelTemperature ?? 0,
            ],
        ];
        
        $wp_response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
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
            throw new \Exception("[Gemini] Network error: {$error}");
        }
        
        $decoded = json_decode($response, true);
        
        // Check for API error response
        if (isset($decoded['error'])) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown API error';
            $errorCode = $decoded['error']['code'] ?? '';
            $errorStatus = $decoded['error']['status'] ?? '';
            
            // Build detailed error message
            $detailedError = "[Gemini] {$errorMessage}";
            if ($errorStatus) {
                $detailedError .= " (Status: {$errorStatus})";
            } elseif ($errorCode) {
                $detailedError .= " (Code: {$errorCode})";
            }
            
            throw new \Exception($detailedError);
        }
        
        // Check HTTP status code
        if ($httpCode >= 400) {
            $errorMessage = "[Gemini] HTTP {$httpCode} error";
            if (!empty($response)) {
                $errorMessage .= ": " . substr($response, 0, 500);
            }
            throw new \Exception($errorMessage);
        }
        
        // Extract text from response
        foreach ($decoded['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (isset($part['text'])) {
                    return $part['text'];
                }
            }
        }
        
        // No text content found
        throw new \Exception("[Gemini] Unexpected response format: " . substr($response, 0, 500));
    }
}
