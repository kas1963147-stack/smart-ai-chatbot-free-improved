<?php
declare(strict_types=1);


/**
 * Provider Bridge
 * 
 * Connects the new 40-provider system (src/api/providers/) to WordPress settings.
 * This is the main integration point between the new provider system and legacy code.
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Bridge;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ProviderSettings;
use Quarksol\SmartChatbot\Types\ProviderName;
use Quarksol\SmartChatbot\Api\Providers\BaseProvider;
use Quarksol\SmartChatbot\Api\Providers\Message;
use Quarksol\SmartChatbot\Api\Providers\CreateMessageMetadata;
use Quarksol\SmartChatbot\Api\Providers\StreamChunk;
use Quarksol\SmartChatbot\Config\ChatbotConfig;
use function Quarksol\SmartChatbot\Api\buildApiHandler;
use Generator;

/**
 * Bridge between WordPress settings and the new provider system
 */
class ProviderBridge {
    
    /** Cached provider instance */
    private static ?BaseProvider $cachedProvider = null;
    
    /** Settings cache key */
    private static ?string $settingsHash = null;
    
    /**
     * Initialize provider bridge (call on plugin init)
     */
    public static function init(): void {
        add_action('shutdown', [self::class, 'clearCache']);
    }
    
    /**
     * Clear static cache (called on shutdown)
     */
    public static function clearCache(): void {
        self::$cachedProvider = null;
        self::$settingsHash = null;
    }
    
    /**
     * Get provider from WordPress settings
     */
    public static function fromSettings(): BaseProvider {
        $settings = ChatbotConfig::settings();
        $hash = md5(serialize($settings));
        
        // Return cached provider if settings haven't changed
        if (self::$cachedProvider && self::$settingsHash === $hash) {
            return self::$cachedProvider;
        }
        
        $providerSettings = self::buildProviderSettings($settings);
        self::$cachedProvider = buildApiHandler($providerSettings);
        self::$settingsHash = $hash;
        
        return self::$cachedProvider;
    }
    
    /**
     * Create provider with explicit settings (bypasses WordPress)
     */
    public static function create(array $options): BaseProvider {
        return self::fromOptions($options);
    }

    /**
     * Create provider from request-style options (provider/api_key/model/base_url)
     */
    public static function fromOptions(array $options): BaseProvider {
        $provider = $options['provider'] ?? ProviderName::OPENROUTER;
        $apiKey = $options['api_key'] ?? $options['apiKey'] ?? '';
        $model = $options['model'] ?? '';
        $baseUrl = $options['base_url'] ?? $options['baseUrl'] ?? '';

        $settings = [
            'ai_provider' => $provider,
            'ai_api_key' => $apiKey,
            'ai_model' => $model,
            'ai_base_url' => $baseUrl,
            'azure_api_version' => $options['azure_api_version'] ?? null,
            'provider_configs' => [
                $provider => [
                    'api_key' => $apiKey,
                    'model' => $model,
                    'base_url' => $baseUrl,
                    'extra' => $options['extra'] ?? [],
                ],
            ],
        ];

        $providerSettings = self::buildProviderSettings($settings);
        return buildApiHandler($providerSettings);
    }
    
    /**
     * Stream response through provider
     */
    public static function stream(string $systemPrompt, array $messages, ?array $metadata = null): Generator {
        $provider = self::fromSettings();
        return $provider->createMessage($systemPrompt, $messages, $metadata);
    }
    
    /**
     * Get completion (non-streaming)
     */
    public static function complete(string $prompt): string {
        $provider = self::fromSettings();
        return $provider->completePrompt($prompt);
    }
    
    /**
     * Chat with system prompt and user message (non-streaming)
     * This is the primary method for simple chatbot use cases.
     * 
     * @param string $systemPrompt System instructions
     * @param string $userMessage User's message
     * @return string AI response
     */
    public static function chat(string $systemPrompt, string $userMessage): string {
        $startTime = microtime(true);
        $provider = self::fromSettings();
        $settings = ChatbotConfig::settings();
        
        // Combine system prompt with user message for simple completion
        $fullPrompt = $systemPrompt . "\n\n---\n\nUser: " . $userMessage . "\n\nAssistant:";
        
        try {
            $response = $provider->completePrompt($fullPrompt);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            // Track analytics if available
            if (class_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService')) {
                $providerName = $settings['ai_provider'] ?? 'unknown';
                $modelId = $settings['model_id'] ?? 'unknown';
                $modelInfo = $provider->getModel();
                
                // Estimate tokens (actual tracking comes from streaming responses)
                $inputTokens = (int) ceil(strlen($fullPrompt) / 4);
                $outputTokens = (int) ceil(strlen($response) / 4);
                
                // Calculate cost using model pricing
                $cost = \Quarksol\SmartChatbot\Analytics\AnalyticsService::calculateCost(
                    $providerName,
                    $modelInfo['id'] ?? $modelId,
                    $inputTokens,
                    $outputTokens
                );
                
                \Quarksol\SmartChatbot\Analytics\AnalyticsService::trackChat(
                    $providerName,
                    $modelInfo['id'] ?? $modelId,
                    $inputTokens,
                    $outputTokens,
                    $cost,
                    $durationMs,
                    true
                );
            }
            
            return $response;
            
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            // Track error
            if (class_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService')) {
                \Quarksol\SmartChatbot\Analytics\AnalyticsService::trackChat(
                    $settings['ai_provider'] ?? 'unknown',
                    $settings['model_id'] ?? 'unknown',
                    0,
                    0,
                    0,
                    $durationMs,
                    false,
                    $e->getMessage()
                );
            }
            
            throw $e;
        }
    }


    
    /**
     * Chat with tools enabled
     * 
     * @param string $systemPrompt System instructions
     * @param array $messages Chat history (role array)
     * @param array $tools Available tools
     * @return array Response with message and tool_calls
     */
    public static function chatWithTools(string $systemPrompt, array $messages, array $tools): array {
        $provider = self::fromSettings();
        
        // Convert messages to Message objects
        $messageObjects = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            $messageObjects[] = new Message($role, $content);
        }
        
        // Convert Tool objects to OpenAI format
        $toolPayload = [];
        foreach ($tools as $tool) {
            // If already in array format, use as-is
            if (is_array($tool)) {
                $toolPayload[] = $tool;
                continue;
            }
            
            // Convert NeuronAI Tool to OpenAI format
            if (is_object($tool) && method_exists($tool, 'getName')) {
                $properties = [];
                $required = [];
                
                // Get tool properties if available
                if (method_exists($tool, 'getProperties')) {
                    foreach ($tool->getProperties() as $prop) {
                        $propName = $prop->getName();
                        $properties[$propName] = [
                            'type' => strtolower($prop->getType()->name ?? 'string'),
                            'description' => $prop->getDescription() ?? '',
                        ];
                        
                        // Handle enums
                        if (method_exists($prop, 'getEnum') && $prop->getEnum()) {
                            $properties[$propName]['enum'] = $prop->getEnum();
                        }
                        
                        if ($prop->required ?? false) {
                            $required[] = $propName;
                        }
                    }
                }
                
                // Ensure properties is an object (not array) when empty
                // This is required by strict OpenAI-compatible providers like IO Intelligence
                $parametersObj = [
                    'type' => 'object',
                    'properties' => empty($properties) ? new \stdClass() : $properties,
                ];
                
                // Only add required field if there are required properties
                if (!empty($required)) {
                    $parametersObj['required'] = $required;
                }
                
                $toolPayload[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->getName(),
                        'description' => $tool->getDescription() ?? '',
                        'parameters' => $parametersObj,
                    ],
                ];
            }
        }
        
        // Limit to 128 tools (API maximum)
        if (count($toolPayload) > 128) {
            $toolPayload = array_slice($toolPayload, 0, 128);
        }
        
        if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::info('ProviderBridge: chatWithTools', [
                'tools_received' => count($tools),
                'tools_converted' => count($toolPayload),
            ]);
        }
        
        // Setup metadata with converted tools
        $metadata = new CreateMessageMetadata();
        $metadata->tools = $toolPayload;
        $metadata->toolChoice = 'auto';
        $metadata->parallelToolCalls = true;
        
        // Create generator
        $generator = $provider->createMessage($systemPrompt, $messageObjects, $metadata);
        
        $fullContent = '';
        $toolCallsBuffer = [];
        
        $error = null;
        
        foreach ($generator as $chunk) {
            $type = $chunk['type'] ?? '';
            
            if ($type === StreamChunk::TYPE_TEXT) {
                $fullContent .= $chunk['text'] ?? '';
            } elseif ($type === StreamChunk::TYPE_TOOL_CALL_PARTIAL) {
                 $index = $chunk['index'] ?? 0;
                 if (!isset($toolCallsBuffer[$index])) {
                     $toolCallsBuffer[$index] = [
                         'id' => '',
                         'type' => 'function',
                         'function' => ['name' => '', 'arguments' => '']
                     ];
                 }
                 
                 if (!empty($chunk['id'])) $toolCallsBuffer[$index]['id'] .= $chunk['id'];
                 if (!empty($chunk['name'])) $toolCallsBuffer[$index]['function']['name'] .= $chunk['name'];
                 if (!empty($chunk['arguments'])) $toolCallsBuffer[$index]['function']['arguments'] .= $chunk['arguments'];
            } elseif ($type === StreamChunk::TYPE_ERROR) {
                $error = $chunk['error'] ?? 'Unknown error';
                if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                    \Quarksol\SmartChatbot\Services\Logger::error('ProviderBridge: Stream error', ['error' => $error]);
                }
            }
        }
        
        return [
            'content' => $fullContent,
            'tool_calls' => array_values($toolCallsBuffer),
            'error' => $error
        ];
    }

    /**
     * Get current model info
     */
    public static function getModelInfo(): array {
        return self::fromSettings()->getModel();
    }
    
    /**
     * Get available providers list
     */
    public static function getAvailableProviders(): array {
        return [
            ProviderName::OPENROUTER => 'OpenRouter (Multi-Model)',
            ProviderName::ANTHROPIC => 'Anthropic (Claude)',
            ProviderName::OPENAI => 'OpenAI (GPT)',
            ProviderName::OPENAI_NATIVE => 'OpenAI Native (o1/o3)',
            ProviderName::GEMINI => 'Google Gemini',
            ProviderName::GROQ => 'Groq (Fast)',
            ProviderName::DEEPSEEK => 'DeepSeek (R1)',
            ProviderName::MISTRAL => 'Mistral',
            ProviderName::XAI => 'xAI (Grok)',
            ProviderName::FIREWORKS => 'Fireworks',
            ProviderName::CEREBRAS => 'Cerebras',
            ProviderName::SAMBANOVA => 'SambaNova',
            ProviderName::OLLAMA => 'Ollama (Local)',
            ProviderName::LMSTUDIO => 'LM Studio (Local)',
        ];
    }
    
    /**
     * Build ProviderSettings from WordPress options
     */
    private static function buildProviderSettings(array $settings): ProviderSettings {
        $providerSettings = new ProviderSettings();
        
        // Provider selection
        $providerSettings->apiProvider = $settings['ai_provider'] ?? ProviderName::OPENROUTER;
        $providerSettings->apiModelId = $settings['model_id'] ?? null;
        
        // Map API keys
        self::mapApiKeys($providerSettings, $settings);
        
        // Map model options
        self::mapModelOptions($providerSettings, $settings);
        
        return $providerSettings;
    }
    
    /**
     * Map API keys from settings array to ProviderSettings
     * 
     * The settings UI now stores per-provider configs in 'provider_configs' array.
     * Falls back to legacy 'ai_api_key' field for backward compatibility.
     */
    private static function mapApiKeys(ProviderSettings $ps, array $settings): void {
        $provider = $settings['ai_provider'] ?? 'openai';
        
        // NEW: Read from per-provider configs (with fallback to legacy)
        $providerConfigs = $settings['provider_configs'] ?? [];
        $currentConfig = $providerConfigs[$provider] ?? [];
        
        // Get values from per-provider config, falling back to legacy flat fields
        $genericApiKey = $currentConfig['api_key'] ?? $settings['ai_api_key'] ?? null;
        $genericBaseUrl = $currentConfig['base_url'] ?? $settings['ai_base_url'] ?? null;
        $genericModel = $currentConfig['model'] ?? $settings['ai_model'] ?? null;
        
        // Map generic key to the appropriate provider-specific field
        switch ($provider) {
            case 'azure':
                $ps->openAiApiKey = $genericApiKey;
                $ps->openAiBaseUrl = $genericBaseUrl;
                $ps->openAiUseAzure = true;
                $ps->azureApiVersion = $settings['azure_api_version'] ?? '2024-05-01-preview';
                $ps->openAiModelId = $genericModel;
                break;
                
            case 'openai':
                $ps->openAiApiKey = $genericApiKey;
                $ps->openAiModelId = $genericModel ?: 'gpt-4o';
                break;
                
            case 'anthropic':
                $ps->apiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'claude-3-5-sonnet-20241022';
                break;
                
            case 'openrouter':
                $ps->openRouterApiKey = $genericApiKey;
                $ps->openRouterModelId = $genericModel ?: 'anthropic/claude-3.5-sonnet';
                break;
                
            case 'gemini':
                $ps->geminiApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'gemini-2.0-flash';
                break;
                
            case 'groq':
                $ps->groqApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'llama-3.1-70b-versatile';
                break;
                
            case 'deepseek':
                $ps->deepSeekApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'deepseek-chat';
                break;
                
            case 'mistral':
                $ps->mistralApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'mistral-large-latest';
                break;
                
            case 'ollama':
                $ps->ollamaBaseUrl = $genericBaseUrl ?: 'http://localhost:11434';
                $ps->ollamaModelId = $genericModel ?: 'llama3';
                break;
                
            case 'lmstudio':
                $ps->lmStudioBaseUrl = $genericBaseUrl ?: 'http://localhost:1234/v1';
                $ps->lmStudioModelId = $genericModel;
                break;
                
            case 'bedrock':
                $ps->awsAccessKey = $genericApiKey;
                $ps->awsSecretKey = $settings['aws_secret_key'] ?? null;
                $ps->awsRegion = $settings['aws_region'] ?? 'us-east-1';
                $ps->apiModelId = $genericModel ?: 'anthropic.claude-3-sonnet-20240229-v1:0';
                break;
                
            case 'vertex':
                $ps->vertexProjectId = $settings['vertex_project_id'] ?? null;
                $ps->vertexRegion = $settings['vertex_region'] ?? 'us-central1';
                $ps->apiModelId = $genericModel ?: 'gemini-pro';
                break;
                
            case 'xai':
                $ps->xaiApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'grok-beta';
                break;
                
            case 'fireworks':
                $ps->fireworksApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel;
                break;
                
            case 'cerebras':
                $ps->cerebrasApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'llama3.1-70b';
                break;
                
            case 'sambanova':
                $ps->sambaNovaApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel;
                break;
                
            case 'huggingface':
                $ps->huggingFaceApiKey = $genericApiKey;
                $ps->huggingFaceModelId = $genericModel ?: 'meta-llama/Llama-3.1-70B-Instruct';
                break;
                
            case 'deepinfra':
                $ps->deepInfraApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'meta-llama/Llama-3.3-70B-Instruct';
                break;
                
            case 'requesty':
                $ps->requestyApiKey = $genericApiKey;
                $ps->requestyModelId = $genericModel ?: 'google/gemini-2.0-flash';
                break;
                
            case 'minimax':
                $ps->minimaxApiKey = $genericApiKey;
                $ps->minimaxGroupId = $currentConfig['extra']['group_id'] ?? '';
                $ps->apiModelId = $genericModel ?: 'abab6.5s-chat';
                break;
                
            case 'moonshot':
                $ps->moonshotApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'moonshot-v1-8k';
                break;
                
            case 'doubao':
                $ps->doubaoApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'doubao-pro-4k';
                break;
                
            case 'litellm':
                $ps->liteLlmApiKey = $genericApiKey;
                $ps->liteLlmBaseUrl = $genericBaseUrl ?: 'http://localhost:4000';
                $ps->liteLlmModelId = $genericModel;
                break;
                
            case 'chutes':
                $ps->chutesApiKey = $genericApiKey;
                $ps->chutesModelId = $genericModel ?: 'deepseek-ai/DeepSeek-V3';
                break;
                
            case 'featherless':
                $ps->featherlessApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel ?: 'meta-llama/Llama-3.3-70B-Instruct';
                break;
                
            case 'baseten':
                $ps->basetenApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel;
                break;
                
            case 'unbound':
                $ps->unboundApiKey = $genericApiKey;
                $ps->unboundModelId = $genericModel;
                break;
                
            case 'zai':
                $ps->zaiApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel;
                break;
                
            case 'io-intelligence':
            case 'io_intelligence':
            case 'iointelligence':
                $ps->ioIntelligenceApiKey = $genericApiKey;
                $ps->ioIntelligenceModelId = $genericModel ?: 'meta-llama/Llama-3.1-70B-Instruct';
                break;
                
            case 'qwencode':
            case 'qwen_code':
                $ps->qwenCodeApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel;
                break;
                
            default:
                // Fallback: try to map generically
                $ps->openRouterApiKey = $genericApiKey;
                $ps->apiModelId = $genericModel;
                break;
        }
        
        // Also populate provider-specific keys if they exist (for advanced users)
        if (!empty($settings['openrouter_api_key'])) {
            $ps->openRouterApiKey = $settings['openrouter_api_key'];
        }
        if (!empty($settings['openai_api_key'])) {
            $ps->openAiApiKey = $settings['openai_api_key'];
        }
    }
    
    private static function mapModelOptions(ProviderSettings $ps, array $settings): void {
        $provider = $settings['ai_provider'] ?? 'openai';
        $currentConfig = $settings['provider_configs'][$provider] ?? [];
        $extra = $currentConfig['extra'] ?? [];

        // Token limits (Provider specific -> Global -> Default)
        $ps->modelMaxTokens = $extra['max_tokens'] ?? $settings['max_tokens'] ?? 4096;
        
        // Temperature
        $ps->modelTemperature = $extra['temperature'] ?? $settings['temperature'] ?? 0.7;
        
        // Reasoning models (o1, o3, R1)
        $ps->enableReasoningEffort = $settings['enable_reasoning'] ?? false;
        $ps->reasoningEffort = $settings['reasoning_effort'] ?? 'medium';
        $ps->modelMaxThinkingTokens = $settings['max_thinking_tokens'] ?? null;
    }
}
