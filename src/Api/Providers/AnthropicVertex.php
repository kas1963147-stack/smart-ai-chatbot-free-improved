<?php
declare(strict_types=1);


/**
 * Anthropic Vertex Provider
 * 
 * 
 * Claude models on Google Cloud Vertex AI
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
 * Vertex-hosted Anthropic model definitions
 */
const VERTEX_ANTHROPIC_MODELS = [
    'claude-3-5-sonnet-v2@20241022' => [
        'contextWindow' => 200000,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 15.0,
        'cacheWritesPrice' => 3.75,
        'cacheReadsPrice' => 0.30,
    ],
    'claude-3-opus@20240229' => [
        'contextWindow' => 200000,
        'maxTokens' => 4096,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 15.0,
        'outputPrice' => 75.0,
    ],
    'claude-3-haiku@20240307' => [
        'contextWindow' => 200000,
        'maxTokens' => 4096,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 0.25,
        'outputPrice' => 1.25,
    ],
];

const VERTEX_ANTHROPIC_DEFAULT = 'claude-3-5-sonnet-v2@20241022';
const VERTEX_1M_CONTEXT_MODELS = ['claude-3-5-sonnet-v2@20241022'];

/**
 * Anthropic Vertex Provider
 * 
 * Runs Claude on Google Cloud Vertex AI
 */
class AnthropicVertex extends BaseProvider {
    protected string $providerName = 'AnthropicVertex';
    protected string $baseURL;
    protected string $projectId;
    protected string $region;
    
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        
        $this->projectId = $settings->vertexProjectId ?? 'not-provided';
        $this->region = $settings->vertexRegion ?? 'us-east5';
        
        // Vertex AI Anthropic endpoint
        $this->baseURL = "https://{$this->region}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->region}/publishers/anthropic/models";
    }
    
    protected function getApiKey(): ?string {
        // Vertex uses Google Cloud auth, not API key
        return null;
    }
    
    protected function getModelId(): string {
        return $this->settings->apiModelId ?? VERTEX_ANTHROPIC_DEFAULT;
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        
        // Handle :thinking suffix
        $actualId = str_replace(':thinking', '', $id);
        
        $modelData = VERTEX_ANTHROPIC_MODELS[$actualId] ?? VERTEX_ANTHROPIC_MODELS[VERTEX_ANTHROPIC_DEFAULT];
        $info = ModelInfo::fromArray($modelData);
        
        // Check 1M context beta
        $supports1M = in_array($actualId, VERTEX_1M_CONTEXT_MODELS);
        $enable1M = $supports1M && ($this->settings->vertex1MContext ?? false);
        
        if ($enable1M && !empty($info->tiers)) {
            $tier = $info->tiers[0];
            $info->contextWindow = $tier['contextWindow'] ?? $info->contextWindow;
            $info->inputPrice = $tier['inputPrice'] ?? $info->inputPrice;
            $info->outputPrice = $tier['outputPrice'] ?? $info->outputPrice;
        }
        
        // Get reasoning params
        $reasoning = null;
        if ($this->settings->enableReasoningEffort && $info->supportsReasoningBudget) {
            $budget = $this->settings->modelMaxThinkingTokens ?? 4096;
            $reasoning = ['type' => 'enabled', 'budget_tokens' => $budget];
        }
        
        // Build betas
        $betas = [];
        if ($enable1M) {
            $betas[] = 'context-1m-2025-08-07';
        }
        
        $maxTokens = $this->settings->modelMaxTokens ?? $info->maxTokens ?? 8192;
        $temperature = $this->settings->modelTemperature ?? 0;
        
        return [
            'id' => $actualId,
            'info' => $info,
            'betas' => !empty($betas) ? $betas : null,
            'maxTokens' => $maxTokens,
            'temperature' => $temperature,
            'reasoning' => $reasoning,
        ];
    }
    
    /**
     * Create streaming message via Vertex AI
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
        
        // Build Anthropic-format messages with caching
        $anthropicMessages = $this->buildMessagesWithCache($messages, $modelInfo->supportsPromptCache);
        
        // Build request body
        $body = [
            'anthropic_version' => 'vertex-2023-10-16',
            'model' => $modelId,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'stream' => true,
        ];
        
        // Add system with caching
        if ($modelInfo->supportsPromptCache) {
            $body['system'] = [
                ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
            ];
        } else {
            $body['system'] = $systemPrompt;
        }
        
        $body['messages'] = $anthropicMessages;
        
        // Add thinking/reasoning
        if ($reasoning) {
            $body['thinking'] = $reasoning;
        }
        
        // Add tools
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForAnthropic($metadata->tools);
        }
        
        yield from $this->streamVertexRequest($body, $modelInfo, $betas);
    }
    
    /**
     * Build messages with cache breakpoints for Vertex
     * Vertex allows max 4 cache_control blocks
     */
    protected function buildMessagesWithCache(array $messages, bool $supportsCache): array {
        $result = [];
        
        // Find user message indices for caching
        $userIndices = [];
        foreach ($messages as $i => $msg) {
            $role = $msg instanceof Message ? $msg->role : ($msg['role'] ?? '');
            if ($role === 'user') {
                $userIndices[] = $i;
            }
        }
        
        $lastUserIdx = end($userIndices) ?: -1;
        $secondLastUserIdx = count($userIndices) >= 2 ? $userIndices[count($userIndices) - 2] : -1;
        
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
            $shouldCache = $supportsCache && ($i === $lastUserIdx || $i === $secondLastUserIdx);
            
            if ($shouldCache && is_string($content)) {
                $formatted['content'] = [
                    ['type' => 'text', 'text' => $content, 'cache_control' => ['type' => 'ephemeral']],
                ];
            } else {
                $formatted['content'] = is_string($content) ? $content : $content;
            }
            
            $result[] = $formatted;
        }
        
        return $result;
    }
    
    /**
     * Convert tools to Anthropic format
     */
    protected function convertToolsForAnthropic(?array $tools): ?array {
        if (!$tools) return null;
        
        return array_map(function($tool) {
            return [
                'name' => $tool['function']['name'] ?? '',
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }, $tools);
    }
    
    /**
     * Stream request to Vertex AI Anthropic endpoint
     */
    protected function streamVertexRequest(array $body, ModelInfo $modelInfo, ?array $betas): Generator {
        $modelId = $body['model'];
        $url = "{$this->baseURL}/{$modelId}:streamRawPredict";
        
        // Get access token via Google Cloud auth
        $accessToken = $this->getGoogleAccessToken();
        if (!$accessToken) {
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => 'Failed to get Google Cloud access token'];
            return;
        }
        
        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer {$accessToken}",
        ];
        
        if ($betas) {
            $headers[] = 'anthropic-beta: ' . implode(',', $betas);
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
            $errorMessage = $decoded['error']['message'] ?? "HTTP {$httpCode} error";
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => "Vertex AI Error: {$errorMessage}"];
            return;
        }
        
        // Parse Anthropic-style SSE response
        yield from $this->parseAnthropicStream($response, $modelInfo);
    }
    
    /**
     * Parse Anthropic streaming response
     */
    protected function parseAnthropicStream(string $response, ModelInfo $modelInfo): Generator {
        $inputTokens = 0;
        $outputTokens = 0;
        $cacheWriteTokens = 0;
        $cacheReadTokens = 0;
        
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'event:') === 0) continue;
            
            if (strpos($line, 'data: ') === 0) {
                $chunk = json_decode(substr($line, 6), true);
                if (!$chunk) continue;
                
                $type = $chunk['type'] ?? null;
                
                switch ($type) {
                    case 'message_start':
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
                        $outputTokens += $chunk['usage']['output_tokens'] ?? 0;
                        break;
                        
                    case 'content_block_start':
                        $block = $chunk['content_block'] ?? [];
                        $blockType = $block['type'] ?? null;
                        
                        if ($blockType === 'thinking') {
                            yield ['type' => StreamChunk::TYPE_REASONING, 'text' => $block['thinking'] ?? ''];
                        } elseif ($blockType === 'text') {
                            yield ['type' => StreamChunk::TYPE_TEXT, 'text' => $block['text'] ?? ''];
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
                            yield ['type' => StreamChunk::TYPE_REASONING, 'text' => $delta['thinking'] ?? ''];
                        } elseif ($deltaType === 'text_delta') {
                            yield ['type' => StreamChunk::TYPE_TEXT, 'text' => $delta['text'] ?? ''];
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
                }
            }
        }
        
        // Final usage with cost
        if ($inputTokens > 0 || $outputTokens > 0) {
            $cost = $this->calculateAnthropicCost($modelInfo, $inputTokens, $outputTokens, $cacheWriteTokens, $cacheReadTokens);
            yield ['type' => StreamChunk::TYPE_USAGE, 'inputTokens' => 0, 'outputTokens' => 0, 'totalCost' => $cost];
        }
    }
    
    /**
     * Get Google Cloud access token
     */
    protected function getGoogleAccessToken(): ?string {
        // Try JSON credentials first
        if ($this->settings->vertexJsonCredentials) {
            return $this->getTokenFromJsonCredentials($this->settings->vertexJsonCredentials);
        }
        
        // Try key file
        if ($this->settings->vertexKeyFile) {
            $json = file_get_contents($this->settings->vertexKeyFile);
            if ($json) {
                return $this->getTokenFromJsonCredentials($json);
            }
        }
        
        // Try default credentials (GCE metadata, etc.)
        return $this->getDefaultCredentialsToken();
    }
    
    /**
     * Get token from JSON credentials
     */
    protected function getTokenFromJsonCredentials(string $json): ?string {
        $creds = json_decode($json, true);
        if (!$creds) return null;
        
        // Create JWT
        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss' => $creds['client_email'] ?? '',
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        
        $privateKey = $creds['private_key'] ?? '';
        if (!$privateKey) return null;
        
        $signature = '';
        openssl_sign("{$header}.{$payload}", $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwt = "{$header}.{$payload}." . base64_encode($signature);
        
        // Exchange JWT for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);
        
        if (is_wp_error($response)) return null;
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['access_token'] ?? null;
    }
    
    /**
     * Get token from default credentials
     */
    protected function getDefaultCredentialsToken(): ?string {
        // Try GCE metadata server
        $response = wp_remote_get('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token', [
            'headers' => ['Metadata-Flavor' => 'Google'],
            'timeout' => 2,
        ]);
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['access_token'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Calculate Anthropic cost
     */
    protected function calculateAnthropicCost(ModelInfo $modelInfo, int $input, int $output, int $cacheWrite, int $cacheRead): float {
        $inputCost = ($input / 1_000_000) * ($modelInfo->inputPrice ?? 0);
        $outputCost = ($output / 1_000_000) * ($modelInfo->outputPrice ?? 0);
        $cacheWriteCost = ($cacheWrite / 1_000_000) * ($modelInfo->cacheWritesPrice ?? 0);
        $cacheReadCost = ($cacheRead / 1_000_000) * ($modelInfo->cacheReadsPrice ?? 0);
        
        return $inputCost + $outputCost + $cacheWriteCost + $cacheReadCost;
    }
    
    /**
     * Complete a simple prompt
     */
    public function completePrompt(string $prompt): string {
        $model = $this->getModel();
        
        $body = [
            'anthropic_version' => 'vertex-2023-10-16',
            'model' => $model['id'],
            'max_tokens' => $model['maxTokens'],
            'temperature' => $model['temperature'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        
        $url = "{$this->baseURL}/{$model['id']}:rawPredict";
        $accessToken = $this->getGoogleAccessToken();
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$accessToken}",
            ],
            'body' => json_encode($body),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        
        foreach ($decoded['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                return $block['text'] ?? '';
            }
        }
        
        return '';
    }
}
