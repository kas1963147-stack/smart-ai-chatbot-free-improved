<?php
declare(strict_types=1);


/**
 * AWS Bedrock Provider
 * 
 * 
 * AWS Bedrock with Claude, Llama, and other models
 * Supports thinking/reasoning, prompt caching, and service tiers
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
 * Bedrock model definitions
 */
const BEDROCK_MODELS = [
    'anthropic.claude-sonnet-4-20250514-v1:0' => [
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
    'anthropic.claude-3-5-sonnet-20241022-v2:0' => [
        'contextWindow' => 200000,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 15.0,
    ],
    'anthropic.claude-3-7-sonnet-20250219-v1:0' => [
        'contextWindow' => 200000,
        'maxTokens' => 16384,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'supportsReasoningBudget' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 15.0,
    ],
    'anthropic.claude-3-opus-20240229-v1:0' => [
        'contextWindow' => 200000,
        'maxTokens' => 4096,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 15.0,
        'outputPrice' => 75.0,
    ],
    'anthropic.claude-3-haiku-20240307-v1:0' => [
        'contextWindow' => 200000,
        'maxTokens' => 4096,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 0.25,
        'outputPrice' => 1.25,
    ],
    'meta.llama3-1-405b-instruct-v1:0' => [
        'contextWindow' => 128000,
        'maxTokens' => 4096,
        'supportsImages' => false,
        'inputPrice' => 5.32,
        'outputPrice' => 16.0,
    ],
    'meta.llama3-1-70b-instruct-v1:0' => [
        'contextWindow' => 128000,
        'maxTokens' => 4096,
        'supportsImages' => false,
        'inputPrice' => 0.99,
        'outputPrice' => 0.99,
    ],
];

const BEDROCK_DEFAULT_MODEL = 'anthropic.claude-sonnet-4-20250514-v1:0';
const BEDROCK_DEFAULT_TEMPERATURE = 0;
const BEDROCK_MAX_TOKENS = 8192;
const BEDROCK_DEFAULT_CONTEXT = 200000;

/** Models that support 1M context beta */
const BEDROCK_1M_CONTEXT_MODELS = [
    'anthropic.claude-sonnet-4-20250514-v1:0',
];

/** Models that support service tiers */
const BEDROCK_SERVICE_TIER_MODELS = [
    'anthropic.claude-sonnet-4-20250514-v1:0',
    'anthropic.claude-3-5-sonnet-20241022-v2:0',
];

/**
 * AWS Bedrock Provider
 * 
 * Features:
 * - AWS signature v4 authentication
 * - Extended thinking/reasoning
 * - Prompt caching
 * - Service tiers (STANDARD, FLEX, PRIORITY)
 * - Cross-region inference
 */
class Bedrock extends BaseProvider {
    protected string $providerName = 'Bedrock';
    protected string $baseURL;
    protected string $region;
    protected ?string $accessKeyId;
    protected ?string $secretAccessKey;
    protected ?string $sessionToken;
    protected ?string $customArn;
    
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        
        $this->region = $settings->awsRegion ?? 'us-east-1';
        $this->accessKeyId = $settings->awsAccessKey ?? null;
        $this->secretAccessKey = $settings->awsSecretKey ?? null;
        $this->sessionToken = $settings->awsSessionToken ?? null;
        $this->customArn = $settings->awsCustomArn ?? null;
        
        // Set endpoint
        $endpoint = $settings->awsBedrockEndpoint ?? null;
        $this->baseURL = $endpoint ?? "https://bedrock-runtime.{$this->region}.amazonaws.com";
    }
    
    protected function getApiKey(): ?string {
        // Bedrock uses AWS credentials, not API key
        return null;
    }
    
    protected function getModelId(): string {
        // Handle custom ARN
        if ($this->customArn) {
            return $this->parseModelFromArn($this->customArn);
        }
        
        return $this->settings->apiModelId ?? BEDROCK_DEFAULT_MODEL;
    }
    
    /**
     * Parse model ID from ARN
     */
    protected function parseModelFromArn(string $arn): string {
        // ARN format: arn:aws:bedrock:region:account:model/model-id
        if (preg_match('/model\/([^\/]+)/', $arn, $matches)) {
            return $matches[1];
        }
        
        // Try inference-profile format
        if (preg_match('/inference-profile\/([^\/]+)/', $arn, $matches)) {
            return $matches[1];
        }
        
        return BEDROCK_DEFAULT_MODEL;
    }
    
    public function getModel(): array {
        $id = $this->customArn ?? $this->getModelId();
        $baseId = $this->parseBaseModelId($id);
        
        $modelData = BEDROCK_MODELS[$baseId] ?? $this->guessModelInfo($baseId);
        $info = ModelInfo::fromArray($modelData);
        
        // Check for thinking support
        $reasoning = null;
        $reasoningBudget = null;
        if ($this->settings->enableReasoningEffort && $info->supportsReasoningBudget) {
            $reasoningBudget = $this->settings->modelMaxThinkingTokens ?? 4096;
            $reasoning = [
                'type' => 'enabled',
                'budget_tokens' => $reasoningBudget,
            ];
        }
        
        $maxTokens = $this->settings->modelMaxTokens ?? $info->maxTokens ?? BEDROCK_MAX_TOKENS;
        $temperature = $this->settings->modelTemperature ?? BEDROCK_DEFAULT_TEMPERATURE;
        
        return [
            'id' => $id,
            'baseId' => $baseId,
            'info' => $info,
            'maxTokens' => $maxTokens,
            'temperature' => $temperature,
            'reasoning' => $reasoning,
            'reasoningBudget' => $reasoningBudget,
        ];
    }
    
    /**
     * Parse base model ID (remove cross-region prefixes)
     */
    protected function parseBaseModelId(string $modelId): string {
        // Remove cross-region inference prefixes
        $modelId = preg_replace('/^(us|eu|ap)\.\w+\./', '', $modelId);
        return $modelId;
    }
    
    /**
     * Guess model info from ID
     */
    protected function guessModelInfo(string $modelId): array {
        $id = strtolower($modelId);
        
        if (strpos($id, 'claude-4') !== false || strpos($id, 'claude-3-7') !== false) {
            return [
                'contextWindow' => 200000,
                'maxTokens' => 16384,
                'supportsImages' => true,
                'supportsPromptCache' => true,
                'supportsReasoningBudget' => true,
            ];
        }
        
        if (strpos($id, 'claude-3-5') !== false) {
            return [
                'contextWindow' => 200000,
                'maxTokens' => 8192,
                'supportsImages' => true,
                'supportsPromptCache' => true,
            ];
        }
        
        if (strpos($id, 'claude-3-opus') !== false) {
            return [
                'contextWindow' => 200000,
                'maxTokens' => 4096,
                'supportsImages' => true,
                'supportsPromptCache' => true,
            ];
        }
        
        if (strpos($id, 'claude') !== false) {
            return [
                'contextWindow' => 200000,
                'maxTokens' => 4096,
                'supportsImages' => true,
                'supportsPromptCache' => true,
            ];
        }
        
        // Default
        return [
            'contextWindow' => BEDROCK_DEFAULT_CONTEXT,
            'maxTokens' => BEDROCK_MAX_TOKENS,
            'supportsImages' => false,
            'supportsPromptCache' => false,
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
        $baseId = $model['baseId'];
        $modelInfo = $model['info'];
        $maxTokens = $model['maxTokens'];
        $temperature = $model['temperature'];
        $reasoning = $model['reasoning'];
        
        // Check for prompt cache support
        $usePromptCache = $this->settings->awsUsePromptCache && $modelInfo->supportsPromptCache;
        
        // Check for 1M context
        $is1MContext = in_array($baseId, BEDROCK_1M_CONTEXT_MODELS) && ($this->settings->awsBedrock1MContext ?? false);
        
        // Check for service tier
        $serviceTier = null;
        if ($this->settings->awsBedrockServiceTier && in_array($baseId, BEDROCK_SERVICE_TIER_MODELS)) {
            $serviceTier = $this->settings->awsBedrockServiceTier;
        }
        
        // Build Bedrock messages
        $bedrockMessages = $this->buildBedrockMessages($messages, $usePromptCache, $modelInfo);
        
        // Build system blocks
        $systemBlocks = $usePromptCache
            ? [['text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']]]
            : [['text' => $systemPrompt]];
        
        // Build inference config
        $inferenceConfig = [
            'maxTokens' => $maxTokens,
            'temperature' => $temperature,
        ];
        
        // Build additional model fields
        $additionalFields = [];
        
        // Add thinking if enabled
        if ($reasoning) {
            $additionalFields['thinking'] = $reasoning;
        }
        
        // Add anthropic_beta for features
        $betas = [];
        if ($is1MContext) {
            $betas[] = 'context-1m-2025-08-07';
        }
        if (strpos($baseId, 'claude') !== false) {
            $betas[] = 'fine-grained-tool-streaming-2025-05-14';
        }
        if (!empty($betas)) {
            $additionalFields['anthropic_beta'] = $betas;
        }
        
        // Build tool config
        $toolConfig = null;
        if ($metadata?->tools) {
            $toolConfig = [
                'tools' => $this->convertToolsForBedrock($metadata->tools),
            ];
            if ($metadata->toolChoice) {
                $toolConfig['toolChoice'] = $this->convertToolChoiceForBedrock($metadata->toolChoice);
            }
        }
        
        // Build payload
        $payload = [
            'modelId' => $modelId,
            'messages' => $bedrockMessages,
            'system' => $systemBlocks,
            'inferenceConfig' => $inferenceConfig,
        ];
        
        if (!empty($additionalFields)) {
            $payload['additionalModelRequestFields'] = $additionalFields;
        }
        
        if ($reasoning) {
            $payload['anthropic_version'] = 'bedrock-2023-05-31';
        }
        
        if ($toolConfig) {
            $payload['toolConfig'] = $toolConfig;
        }
        
        if ($serviceTier) {
            $payload['service_tier'] = $serviceTier;
        }
        
        yield from $this->streamBedrockRequest($payload, $modelInfo);
    }
    
    /**
     * Build Bedrock-format messages
     */
    protected function buildBedrockMessages(array $messages, bool $useCache, ModelInfo $modelInfo): array {
        $result = [];
        
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $role = $message->role;
                $content = $message->content;
            } else {
                $role = $message['role'] ?? 'user';
                $content = $message['content'] ?? '';
            }
            
            $bedrockRole = $role === 'assistant' ? 'assistant' : 'user';
            
            if (is_string($content)) {
                $result[] = [
                    'role' => $bedrockRole,
                    'content' => [['text' => $content]],
                ];
            } else {
                // Multi-part content
                $parts = [];
                foreach ($content as $part) {
                    if (isset($part['text'])) {
                        $parts[] = ['text' => $part['text']];
                    } elseif (isset($part['image_url'])) {
                        $imageData = $this->extractImageData($part['image_url']['url']);
                        $parts[] = [
                            'image' => [
                                'format' => $imageData['format'],
                                'source' => ['bytes' => $imageData['data']],
                            ],
                        ];
                    } elseif (isset($part['type']) && $part['type'] === 'tool_result') {
                        $parts[] = [
                            'toolResult' => [
                                'toolUseId' => $part['tool_use_id'] ?? '',
                                'content' => [['text' => is_string($part['content']) ? $part['content'] : json_encode($part['content'])]],
                            ],
                        ];
                    } elseif (isset($part['type']) && $part['type'] === 'tool_use') {
                        $parts[] = [
                            'toolUse' => [
                                'toolUseId' => $part['id'] ?? '',
                                'name' => $part['name'] ?? '',
                                'input' => $part['input'] ?? [],
                            ],
                        ];
                    }
                }
                $result[] = ['role' => $bedrockRole, 'content' => $parts];
            }
        }
        
        return $result;
    }
    
    /**
     * Extract image data from URL or base64
     */
    protected function extractImageData(string $url): array {
        if (strpos($url, 'data:') === 0) {
            preg_match('/data:image\/(\w+);base64,(.+)/', $url, $matches);
            return [
                'format' => $matches[1] ?? 'jpeg',
                'data' => base64_decode($matches[2] ?? ''),
            ];
        }
        
        return ['format' => 'jpeg', 'data' => ''];
    }
    
    /**
     * Convert tools for Bedrock
     */
    protected function convertToolsForBedrock(?array $tools): array {
        if (!$tools) return [];
        
        return array_map(function($tool) {
            return [
                'toolSpec' => [
                    'name' => $tool['function']['name'] ?? '',
                    'description' => $tool['function']['description'] ?? '',
                    'inputSchema' => [
                        'json' => $tool['function']['parameters'] ?? ['type' => 'object', 'properties' => []],
                    ],
                ],
            ];
        }, $tools);
    }
    
    /**
     * Convert tool choice for Bedrock
     */
    protected function convertToolChoiceForBedrock($toolChoice): ?array {
        if (!$toolChoice) return null;
        
        if (is_string($toolChoice)) {
            switch ($toolChoice) {
                case 'auto': return ['auto' => []];
                case 'any': return ['any' => []];
                case 'none': return null;
            }
        }
        
        if (is_array($toolChoice) && isset($toolChoice['type']) && $toolChoice['type'] === 'function') {
            return ['tool' => ['name' => $toolChoice['function']['name'] ?? '']];
        }
        
        return null;
    }
    
    /**
     * Stream request to Bedrock
     */
    protected function streamBedrockRequest(array $payload, ModelInfo $modelInfo): Generator {
        if (!$this->accessKeyId || !$this->secretAccessKey) {
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => 'AWS credentials are required'];
            return;
        }
        
        $modelId = $payload['modelId'];
        $url = "{$this->baseURL}/model/{$modelId}/converse-stream";
        
        // Sign request with AWS Signature v4
        $headers = $this->signRequest('POST', $url, json_encode($payload));
        
        $wp_response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($payload),
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
            $errorMessage = $decoded['message'] ?? "HTTP {$httpCode} error";
            yield ['type' => StreamChunk::TYPE_ERROR, 'error' => "Bedrock Error: {$errorMessage}"];
            return;
        }
        
        yield from $this->parseBedrockStream($response, $modelInfo);
    }
    
    /**
     * Parse Bedrock streaming response
     */
    protected function parseBedrockStream(string $response, ModelInfo $modelInfo): Generator {
        $inputTokens = 0;
        $outputTokens = 0;
        $cacheReadTokens = 0;
        $cacheWriteTokens = 0;
        
        // Bedrock uses event stream format
        $events = $this->parseEventStream($response);
        
        foreach ($events as $event) {
            $type = $event['type'] ?? null;
            
            switch ($type) {
                case 'contentBlockStart':
                    $block = $event['contentBlock'] ?? $event['start'] ?? [];
                    
                    if (isset($block['reasoningContent'])) {
                        yield ['type' => StreamChunk::TYPE_REASONING, 'text' => $block['reasoningContent']['text'] ?? ''];
                    } elseif (isset($block['text'])) {
                        yield ['type' => StreamChunk::TYPE_TEXT, 'text' => $block['text']];
                    } elseif (isset($block['toolUse'])) {
                        yield [
                            'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                            'index' => $event['contentBlockIndex'] ?? 0,
                            'id' => $block['toolUse']['toolUseId'] ?? null,
                            'name' => $block['toolUse']['name'] ?? null,
                            'arguments' => null,
                        ];
                    }
                    break;
                    
                case 'contentBlockDelta':
                    $delta = $event['delta'] ?? [];
                    
                    if (isset($delta['reasoningContent'])) {
                        yield ['type' => StreamChunk::TYPE_REASONING, 'text' => $delta['reasoningContent']['text'] ?? ''];
                    } elseif (isset($delta['thinking'])) {
                        yield ['type' => StreamChunk::TYPE_REASONING, 'text' => $delta['thinking']];
                    } elseif (isset($delta['text'])) {
                        yield ['type' => StreamChunk::TYPE_TEXT, 'text' => $delta['text']];
                    } elseif (isset($delta['toolUse']['input'])) {
                        yield [
                            'type' => StreamChunk::TYPE_TOOL_CALL_PARTIAL,
                            'index' => $event['contentBlockIndex'] ?? 0,
                            'id' => null,
                            'name' => null,
                            'arguments' => $delta['toolUse']['input'],
                        ];
                    }
                    break;
                    
                case 'metadata':
                    $usage = $event['usage'] ?? [];
                    $inputTokens = $usage['inputTokens'] ?? 0;
                    $outputTokens = $usage['outputTokens'] ?? 0;
                    $cacheReadTokens = $usage['cacheReadInputTokens'] ?? $usage['cacheReadInputTokenCount'] ?? 0;
                    $cacheWriteTokens = $usage['cacheWriteInputTokens'] ?? $usage['cacheWriteInputTokenCount'] ?? 0;
                    
                    yield [
                        'type' => StreamChunk::TYPE_USAGE,
                        'inputTokens' => $inputTokens,
                        'outputTokens' => $outputTokens,
                        'cacheReadTokens' => $cacheReadTokens,
                        'cacheWriteTokens' => $cacheWriteTokens,
                    ];
                    break;
            }
        }
        
        // Final cost calculation
        if ($inputTokens > 0 || $outputTokens > 0) {
            $cost = $this->calculateCost($modelInfo, $inputTokens, $outputTokens, $cacheWriteTokens, $cacheReadTokens);
            yield ['type' => StreamChunk::TYPE_USAGE, 'inputTokens' => 0, 'outputTokens' => 0, 'totalCost' => $cost];
        }
    }
    
    /**
     * Parse event stream format
     */
    protected function parseEventStream(string $response): array {
        $events = [];
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') !== 0) continue;
            
            // Try to parse as JSON
            $decoded = json_decode($line, true);
            if ($decoded) {
                $events[] = $decoded;
            }
        }
        
        return $events;
    }
    
    /**
     * Sign request with AWS Signature v4
     */
    protected function signRequest(string $method, string $url, string $payload): array {
        $service = 'bedrock';
        $algorithm = 'AWS4-HMAC-SHA256';
        $date = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $path = $parsedUrl['path'] ?? '/';
        
        $payloadHash = hash('sha256', $payload);
        
        // Create canonical request
        $canonicalHeaders = "content-type:application/json\nhost:{$host}\nx-amz-date:{$date}\n";
        $signedHeaders = 'content-type;host;x-amz-date';
        
        if ($this->sessionToken) {
            $canonicalHeaders .= "x-amz-security-token:{$this->sessionToken}\n";
            $signedHeaders .= ';x-amz-security-token';
        }
        
        $canonicalRequest = "{$method}\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        
        // Create string to sign
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n{$date}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        // Calculate signature
        $kDate = hash_hmac('sha256', $dateStamp, "AWS4{$this->secretAccessKey}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        // Build authorization header
        $authorization = "{$algorithm} Credential={$this->accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        
        $headers = [
            'Content-Type' => 'application/json',
            'Host' => $host,
            'X-Amz-Date' => $date,
            'Authorization' => $authorization,
        ];
        
        if ($this->sessionToken) {
            $headers['X-Amz-Security-Token'] = $this->sessionToken;
        }
        
        return $headers;
    }
    
    /**
     * Calculate cost with cache pricing
     */
    protected function calculateCost(ModelInfo $modelInfo, int $input, int $output, int $cacheWrite, int $cacheRead): float {
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
        
        $payload = [
            'modelId' => $model['id'],
            'messages' => [
                ['role' => 'user', 'content' => [['text' => $prompt]]],
            ],
            'inferenceConfig' => [
                'maxTokens' => $model['maxTokens'],
                'temperature' => $model['temperature'],
            ],
        ];
        
        $url = "{$this->baseURL}/model/{$model['id']}/converse";
        $headers = $this->signRequest('POST', $url, json_encode($payload));
        
        $wp_response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 60,
        ]);

        if (is_wp_error($wp_response)) {
            return '';
        }
        
        $response = wp_remote_retrieve_body($wp_response);
        
        $decoded = json_decode($response, true);
        $content = $decoded['output']['message']['content'] ?? [];
        
        foreach ($content as $block) {
            if (isset($block['text'])) {
                return $block['text'];
            }
        }
        
        return '';
    }
}
