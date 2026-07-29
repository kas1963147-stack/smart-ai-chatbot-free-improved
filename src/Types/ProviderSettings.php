<?php
declare(strict_types=1);


/**
 * Provider Settings Type Definition
 * 
 * 
 * Contains all settings for all supported providers
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Types;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base provider settings shared by all providers
 */
class BaseProviderSettings {
    /** Include max tokens in request */
    public ?bool $includeMaxTokens = null;
    
    /** Enable diff mode */
    public ?bool $diffEnabled = null;
    
    /** Model temperature (0-2) */
    public ?float $modelTemperature = null;
    
    /** Rate limit between requests (seconds) */
    public ?int $rateLimitSeconds = null;
    
    /** Maximum consecutive mistakes before stopping */
    public ?int $consecutiveMistakeLimit = null;
    
    // Reasoning/Thinking model settings
    
    /** Enable reasoning effort parameter */
    public ?bool $enableReasoningEffort = null;
    
    /** Reasoning effort level: disable, none, minimal, low, medium, high, xhigh */
    public ?string $reasoningEffort = null;
    
    /** Maximum model output tokens */
    public ?int $modelMaxTokens = null;
    
    /** Maximum thinking/reasoning tokens */
    public ?int $modelMaxThinkingTokens = null;
    
    /** Output verbosity: low, medium, high */
    public ?string $verbosity = null;
}

/**
 * Full provider settings including all provider-specific options
 */
class ProviderSettings extends BaseProviderSettings {
    /** Currently selected provider */
    public ?string $apiProvider = null;
    
    /** Generic model ID for providers using apiModelId */
    public ?string $apiModelId = null;
    
    // ========== Anthropic ==========
    public ?string $apiKey = null;
    public ?string $anthropicBaseUrl = null;
    public ?bool $anthropicUseAuthToken = null;
    public ?bool $anthropicBeta1MContext = null;
    
    // ========== OpenRouter ==========
    public ?string $openRouterApiKey = null;
    public ?string $openRouterModelId = null;
    public ?string $openRouterBaseUrl = null;
    public ?string $openRouterSpecificProvider = null;
    
    // ========== AWS Bedrock ==========
    public ?string $awsAccessKey = null;
    public ?string $awsSecretKey = null;
    public ?string $awsSessionToken = null;
    public ?string $awsRegion = null;
    public ?bool $awsUseCrossRegionInference = null;
    public ?bool $awsUseGlobalInference = null;
    public ?bool $awsUsePromptCache = null;
    public ?string $awsProfile = null;
    public ?bool $awsUseProfile = null;
    public ?string $awsApiKey = null;
    public ?bool $awsUseApiKey = null;
    public ?string $awsCustomArn = null;
    public ?int $awsModelContextWindow = null;
    public ?bool $awsBedrockEndpointEnabled = null;
    public ?string $awsBedrockEndpoint = null;
    public ?bool $awsBedrock1MContext = null;
    public ?string $awsBedrockServiceTier = null; // STANDARD, FLEX, PRIORITY
    
    // ========== Google Vertex ==========
    public ?string $vertexKeyFile = null;
    public ?string $vertexJsonCredentials = null;
    public ?string $vertexProjectId = null;
    public ?string $vertexRegion = null;
    public ?bool $enableUrlContext = null;
    public ?bool $enableGrounding = null;
    public ?bool $vertex1MContext = null;
    
    // ========== OpenAI ==========
    public ?string $openAiBaseUrl = null;
    public ?string $openAiApiKey = null;
    public ?bool $openAiR1FormatEnabled = null;
    public ?string $openAiModelId = null;
    public ?array $openAiCustomModelInfo = null;
    public ?bool $openAiUseAzure = null;
    public ?string $azureApiVersion = null;
    public ?bool $openAiStreamingEnabled = null;
    public ?array $openAiHeaders = null;
    
    // ========== Ollama ==========
    public ?string $ollamaModelId = null;
    public ?string $ollamaBaseUrl = null;
    public ?string $ollamaApiKey = null;
    public ?int $ollamaNumCtx = null;
    
    // ========== LM Studio ==========
    public ?string $lmStudioModelId = null;
    public ?string $lmStudioBaseUrl = null;
    public ?string $lmStudioDraftModelId = null;
    public ?bool $lmStudioSpeculativeDecodingEnabled = null;
    
    // ========== Gemini ==========
    public ?string $geminiApiKey = null;
    public ?string $googleGeminiBaseUrl = null;
    
    // ========== OpenAI Native ==========
    public ?string $openAiNativeApiKey = null;
    public ?string $openAiNativeBaseUrl = null;
    public ?string $openAiNativeServiceTier = null; // default, flex, priority
    
    // ========== Mistral ==========
    public ?string $mistralApiKey = null;
    public ?string $mistralCodestralUrl = null;
    
    // ========== DeepSeek ==========
    public ?string $deepSeekBaseUrl = null;
    public ?string $deepSeekApiKey = null;
    
    // ========== DeepInfra ==========
    public ?string $deepInfraApiKey = null;
    
    // ========== Groq ==========
    public ?string $groqApiKey = null;
    
    // ========== Cerebras ==========
    public ?string $cerebrasApiKey = null;
    
    // ========== SambaNova ==========
    public ?string $sambaNovaApiKey = null;
    
    // ========== Fireworks ==========
    public ?string $fireworksApiKey = null;
    
    // ========== xAI ==========
    public ?string $xaiApiKey = null;
    
    // ========== Moonshot ==========
    public ?string $moonshotApiKey = null;
    
    // ========== Doubao ==========
    public ?string $doubaoApiKey = null;
    
    // ========== MiniMax ==========
    public ?string $minimaxApiKey = null;
    public ?string $minimaxGroupId = null;
    
    // ========== HuggingFace ==========
    public ?string $huggingFaceApiKey = null;
    public ?string $huggingFaceModelId = null;
    
    // ========== LiteLLM ==========
    public ?string $liteLlmApiKey = null;
    public ?string $liteLlmBaseUrl = null;
    public ?string $liteLlmModelId = null;
    
    // ========== Requesty ==========
    public ?string $requestyApiKey = null;
    public ?string $requestyModelId = null;
    
    // ========== Unbound ==========
    public ?string $unboundApiKey = null;
    public ?string $unboundModelId = null;
    
    // ========== Chutes ==========
    public ?string $chutesApiKey = null;
    public ?string $chutesModelId = null;
    
    // ========== Featherless ==========
    public ?string $featherlessApiKey = null;
    
    // ========== Baseten ==========
    public ?string $basetenApiKey = null;
    
    // ========== ZAI ==========
    public ?string $zaiApiKey = null;
    
    // ========== IO Intelligence ==========
    public ?string $ioIntelligenceApiKey = null;
    public ?string $ioIntelligenceModelId = null;
    
    // ========== Qwen Code ==========
    public ?string $qwenCodeApiKey = null;
    
    // ========== Vercel AI Gateway ==========
    public ?string $vercelAiGatewayApiKey = null;
    public ?string $vercelAiGatewayModelId = null;
    public ?string $vercelAiGatewayBaseUrl = null;
    
    /**
     * Create from array (e.g., WordPress options or API request)
     */
    public static function fromArray(array $data): self {
        $settings = new self();
        
        foreach ($data as $key => $value) {
            // Convert snake_case to camelCase if needed
            $camelKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
            
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            } elseif (property_exists($settings, $camelKey)) {
                $settings->$camelKey = $value;
            }
        }
        
        return $settings;
    }
    
    /**
     * Convert to array for storage
     */
    public function toArray(): array {
        $result = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    
    /**
     * Get WordPress option key for a setting
     */
    public static function getOptionKey(string $property): string {
        // Convert camelCase to snake_case
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
        return 'chatbot_ai_' . $snakeCase;
    }
}
