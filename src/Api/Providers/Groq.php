<?php
declare(strict_types=1);


/**
 * Groq Provider
 * 
 * 
 * Ultra-fast inference with Groq's LPU hardware
 * Supports ANY model the user wants to use - native Groq models or routed models
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
 * Groq model definitions (known models with pricing info)
 * Users can still use ANY model not listed here
 */
const GROQ_MODELS = [
    'llama-3.3-70b-versatile' => [
        'contextWindow' => 128000,
        'maxTokens' => 32768,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.59,
        'outputPrice' => 0.79,
    ],
    'llama-3.1-70b-versatile' => [
        'contextWindow' => 131072,
        'maxTokens' => 8000,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.59,
        'outputPrice' => 0.79,
    ],
    'llama-3.1-8b-instant' => [
        'contextWindow' => 131072,
        'maxTokens' => 8000,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.05,
        'outputPrice' => 0.08,
    ],
    'mixtral-8x7b-32768' => [
        'contextWindow' => 32768,
        'maxTokens' => 32768,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.24,
        'outputPrice' => 0.24,
    ],
    'gemma2-9b-it' => [
        'contextWindow' => 8192,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.20,
        'outputPrice' => 0.20,
    ],
];

const GROQ_DEFAULT_MODEL = 'llama-3.3-70b-versatile';

/**
 * Groq API Provider
 * 
 * Uses OpenAI-compatible API with ultra-fast inference.
 * Supports ANY model including:
 * - Native Groq models (llama, mixtral, gemma)
 * - Routed models via OpenRouter-style prefixes (openai/gpt-*, anthropic/*, etc.)
 */
class Groq extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Groq',
            'https://api.groq.com/openai/v1',
            GROQ_DEFAULT_MODEL,
            self::buildModels()
        );
        
        $this->defaultTemperature = 0.5;
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->groqApiKey ?? null;
    }
    
    /**
     * Get current model info - supports ANY model, not just known ones
     */
    public function getModel(): array {
        $id = $this->settings->apiModelId ?? GROQ_DEFAULT_MODEL;
        
        // Check if it's a known model with pricing info
        if (isset($this->providerModels[$id])) {
            return [
                'id' => $id,
                'info' => $this->providerModels[$id],
            ];
        }
        
        // For unknown/custom models, return sensible defaults
        // This allows users to use ANY model they want
        return [
            'id' => $id,
            'info' => ModelInfo::fromArray([
                'contextWindow' => 128000,  // generous default
                'supportsImages' => false,
                'maxTokens' => 32768,       // generous default
                'supportsPromptCache' => false,
                'inputPrice' => 0.50,       // reasonable estimate
                'outputPrice' => 0.50,      // reasonable estimate
            ]),
        ];
    }
    
    /**
     * Override createMessage to handle Groq-specific quirks
     * 
     * Groq doesn't support all OpenAI parameters, especially:
     * - stream_options (not supported on all models)
     * - Some models may not support parallel_tool_calls
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelId = $model['id'];
        $modelInfo = $model['info'];
        
        // Calculate max tokens
        $maxTokens = $this->settings->modelMaxTokens 
            ?? $modelInfo->maxTokens 
            ?? 4096;
        
        // Get temperature
        $temperature = $this->settings->modelTemperature 
            ?? $modelInfo->defaultTemperature 
            ?? $this->defaultTemperature;
        
        // Build OpenAI-format messages
        $openAiMessages = $this->buildMessages($systemPrompt, $messages);
        
        // Build request body - Groq-compatible format
        $body = [
            'model' => $modelId,
            'messages' => $openAiMessages,
            'stream' => true,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            // NOTE: We intentionally DO NOT include 'stream_options' here
            // Groq doesn't support it for all models (especially routed models like openai/*)
        ];
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForOpenAI($metadata->tools);
        }
        if ($metadata?->toolChoice) {
            $body['tool_choice'] = $metadata->toolChoice;
        }
        // Note: parallel_tool_calls may not be supported on all Groq models
        // Only add it for native Groq models that we know support it
        if ($metadata?->parallelToolCalls !== null && $this->isNativeGroqModel($modelId)) {
            $body['parallel_tool_calls'] = $metadata->parallelToolCalls;
        }
        
        // Debug: Log actual tool payload being sent to Groq
        if (isset($body['tools'])) {
            $toolSample = array_slice($body['tools'], 0, 3);
            error_log('[GROQ_DEBUG] Tools count: ' . count($body['tools']));
            error_log('[GROQ_DEBUG] Tool sample: ' . json_encode($toolSample, JSON_PRETTY_PRINT));
            error_log('[GROQ_DEBUG] tool_choice: ' . json_encode($body['tool_choice'] ?? 'not set'));
        }
        
        // Make streaming request
        yield from $this->streamRequest($body, $modelInfo);
    }
    
    /**
     * Override tool conversion for Groq compatibility
     * 
     * Groq does NOT support:
     * - 'strict' field in tool definitions (OpenAI-specific)  
     * - null descriptions (must be string)
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
            
            // Ensure parameters is a proper object schema, not empty array
            if (empty($params) || $params === []) {
                $params = [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ];
            }

            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['function']['name'],
                    'description' => $tool['function']['description'] ?? 'No description',
                    // NOTE: No 'strict' field - Groq doesn't support it
                    'parameters' => $params,
                ],
            ];
        }, $tools);
    }

    /**
     * Check if a model is a native Groq model (not routed)
     */
    private function isNativeGroqModel(string $modelId): bool {
        // Native Groq models don't have a "/" prefix
        // Models like "openai/gpt-4" are routed and may have different capabilities
        if (str_contains($modelId, '/')) {
            return false;
        }
        
        // Check against known Groq models
        $nativeModels = [
            'llama', 'mixtral', 'gemma', 'whisper', 'distil-whisper'
        ];
        
        foreach ($nativeModels as $prefix) {
            if (str_starts_with($modelId, $prefix)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Build model info objects from definitions
     */
    private static function buildModels(): array {
        $models = [];
        foreach (GROQ_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
