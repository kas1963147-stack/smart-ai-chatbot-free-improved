<?php
declare(strict_types=1);


/**
 * API Handler Factory
 * 
 * 
 * Factory function to build the appropriate provider handler
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ProviderSettings;
use Quarksol\SmartChatbot\Types\ProviderName;
use Quarksol\SmartChatbot\Api\Providers\BaseProvider;

// Import all providers
use Quarksol\SmartChatbot\Api\Providers\OpenAI;
use Quarksol\SmartChatbot\Api\Providers\OpenAINative;
use Quarksol\SmartChatbot\Api\Providers\OpenAICodex;
use Quarksol\SmartChatbot\Api\Providers\OpenRouter;
use Quarksol\SmartChatbot\Api\Providers\Anthropic;
use Quarksol\SmartChatbot\Api\Providers\AnthropicVertex;
use Quarksol\SmartChatbot\Api\Providers\Gemini;
use Quarksol\SmartChatbot\Api\Providers\Vertex;
use Quarksol\SmartChatbot\Api\Providers\Bedrock;
use Quarksol\SmartChatbot\Api\Providers\Groq;
use Quarksol\SmartChatbot\Api\Providers\DeepSeek;
use Quarksol\SmartChatbot\Api\Providers\Mistral;
use Quarksol\SmartChatbot\Api\Providers\XAI;
use Quarksol\SmartChatbot\Api\Providers\Fireworks;
use Quarksol\SmartChatbot\Api\Providers\Cerebras;
use Quarksol\SmartChatbot\Api\Providers\SambaNova;
use Quarksol\SmartChatbot\Api\Providers\Ollama;
use Quarksol\SmartChatbot\Api\Providers\LMStudio;
use Quarksol\SmartChatbot\Api\Providers\HuggingFace;
use Quarksol\SmartChatbot\Api\Providers\DeepInfra;
use Quarksol\SmartChatbot\Api\Providers\LiteLLM;
use Quarksol\SmartChatbot\Api\Providers\Requesty;
use Quarksol\SmartChatbot\Api\Providers\Moonshot;
use Quarksol\SmartChatbot\Api\Providers\Doubao;
use Quarksol\SmartChatbot\Api\Providers\MiniMax;
use Quarksol\SmartChatbot\Api\Providers\Unbound;
use Quarksol\SmartChatbot\Api\Providers\Chutes;
use Quarksol\SmartChatbot\Api\Providers\Featherless;
use Quarksol\SmartChatbot\Api\Providers\QwenCode;
use Quarksol\SmartChatbot\Api\Providers\Baseten;
use Quarksol\SmartChatbot\Api\Providers\ZAI;
use Quarksol\SmartChatbot\Api\Providers\IoIntelligence;
use Quarksol\SmartChatbot\Api\Providers\VercelAIGateway;

/**
 * Build the appropriate API handler based on provider settings
 * 
 * @param ProviderSettings $settings Provider configuration
 * @return BaseProvider The configured provider handler
 */
function buildApiHandler(ProviderSettings $settings): BaseProvider {
    $provider = $settings->apiProvider ?? ProviderName::OPENROUTER;
    
    switch ($provider) {
        case ProviderName::ANTHROPIC:
            return new Anthropic($settings);
            
        case ProviderName::OPENROUTER:
            return new OpenRouter($settings);
            
        case ProviderName::OPENAI:
            return new OpenAI($settings);
            
        case ProviderName::AZURE:
            // Azure uses OpenAI provider with Azure flag
            $settings->openAiUseAzure = true;
            return new OpenAI($settings);
            
        case ProviderName::GEMINI:
        case ProviderName::VERTEX:
            return new Gemini($settings);
            
        case ProviderName::GROQ:
            return new Groq($settings);
            
        case ProviderName::DEEPSEEK:
            return new DeepSeek($settings);
            
        case ProviderName::MISTRAL:
            return new Mistral($settings);
            
        case ProviderName::XAI:
            return new XAI($settings);
            
        case ProviderName::FIREWORKS:
            return new Fireworks($settings);
            
        case ProviderName::CEREBRAS:
            return new Cerebras($settings);
            
        case ProviderName::SAMBANOVA:
            return new SambaNova($settings);
            
        case ProviderName::OLLAMA:
            return new Ollama($settings);
            
        case ProviderName::LMSTUDIO:
            return new LMStudio($settings);
            
        case ProviderName::HUGGINGFACE:
            return new HuggingFace($settings);
            
        case ProviderName::DEEPINFRA:
            return new DeepInfra($settings);
            
        case ProviderName::LITELLM:
            return new LiteLLM($settings);
            
        case ProviderName::REQUESTY:
            return new Requesty($settings);
            
        case ProviderName::MOONSHOT:
            return new Moonshot($settings);
            
        case ProviderName::DOUBAO:
            return new Doubao($settings);
            
        case ProviderName::MINIMAX:
            return new MiniMax($settings);
            
        case ProviderName::UNBOUND:
            return new Unbound($settings);
            
        case ProviderName::CHUTES:
            return new Chutes($settings);
            
        case ProviderName::FEATHERLESS:
            return new Featherless($settings);
            
        case ProviderName::QWENCODE:
            return new QwenCode($settings);
            
        case ProviderName::BASETEN:
            return new Baseten($settings);
            
        case ProviderName::ZAI:
            return new ZAI($settings);
            
        case ProviderName::IO_INTELLIGENCE:
            return new IoIntelligence($settings);
            
        case ProviderName::VERCEL_AI_GATEWAY:
            return new VercelAIGateway($settings);
            
        case ProviderName::BEDROCK:
            return new Bedrock($settings);
            
        case ProviderName::OPENAI_NATIVE:
            return new OpenAINative($settings);
            
        case ProviderName::OPENAI_CODEX:
            return new OpenAICodex($settings);
            
        case ProviderName::VERTEX:
            return new Vertex($settings);
            
        case ProviderName::ANTHROPIC_VERTEX:
            return new AnthropicVertex($settings);
            

            
        // Default to OpenRouter - covers 200+ models
        default:
            return new OpenRouter($settings);
    }
}

/**
 * Get list of all available providers
 * 
 * Returns ALL 42 available providers for admin UI with configuration requirements.
 * 
 * @return array Provider info for admin UI
 */
function getAvailableProviders(): array {
    return [
        // === Tier 1: Major Cloud Providers ===
        ProviderName::OPENROUTER => [
            'name' => 'OpenRouter',
            'description' => 'Access 200+ AI models through a single API',
            'requiresApiKey' => true,
            'settingsField' => 'openRouterApiKey',
            'modelField' => 'openRouterModelId',
            'dynamic' => true,
        ],
        ProviderName::OPENAI => [
            'name' => 'OpenAI',
            'description' => 'GPT-4, GPT-4o, o1, o3 models',
            'requiresApiKey' => true,
            'settingsField' => 'openAiApiKey',
            'modelField' => 'openAiModelId',
            'dynamic' => false,
        ],
        ProviderName::ANTHROPIC => [
            'name' => 'Anthropic',
            'description' => 'Claude 3.5 Sonnet, Claude 3 Opus, Haiku',
            'requiresApiKey' => true,
            'settingsField' => 'apiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::GEMINI => [
            'name' => 'Google Gemini',
            'description' => 'Gemini Pro, Gemini Ultra, Gemini Flash',
            'requiresApiKey' => true,
            'settingsField' => 'geminiApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::AZURE => [
            'name' => 'Azure OpenAI',
            'description' => 'Microsoft Azure-hosted OpenAI models',
            'requiresApiKey' => true,
            'settingsField' => 'azureApiKey',
            'modelField' => 'apiModelId',
            'requiresBaseUrl' => true,
            'dynamic' => false,
        ],
        
        // === Tier 2: Fast Inference ===
        ProviderName::GROQ => [
            'name' => 'Groq',
            'description' => 'Ultra-fast inference for Llama, Mixtral',
            'requiresApiKey' => true,
            'settingsField' => 'groqApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::CEREBRAS => [
            'name' => 'Cerebras',
            'description' => 'Ultra-fast Llama inference',
            'requiresApiKey' => true,
            'settingsField' => 'cerebrasApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::SAMBANOVA => [
            'name' => 'SambaNova',
            'description' => 'Enterprise AI platform with fast inference',
            'requiresApiKey' => true,
            'settingsField' => 'sambanovaApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::FIREWORKS => [
            'name' => 'Fireworks',
            'description' => 'Fast inference for open models',
            'requiresApiKey' => true,
            'settingsField' => 'fireworksApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        
        // === Tier 3: Specialized Providers ===
        ProviderName::DEEPSEEK => [
            'name' => 'DeepSeek',
            'description' => 'DeepSeek R1, DeepSeek Coder, DeepSeek Chat',
            'requiresApiKey' => true,
            'settingsField' => 'deepSeekApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::MISTRAL => [
            'name' => 'Mistral',
            'description' => 'Mistral Large, Medium, Small, Codestral',
            'requiresApiKey' => true,
            'settingsField' => 'mistralApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::XAI => [
            'name' => 'xAI (Grok)',
            'description' => 'Grok models from xAI',
            'requiresApiKey' => true,
            'settingsField' => 'xaiApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        
        // === Tier 4: Enterprise/Cloud ===
        ProviderName::BEDROCK => [
            'name' => 'AWS Bedrock',
            'description' => 'Claude, Titan, Llama on AWS',
            'requiresApiKey' => true,
            'settingsField' => 'awsAccessKey',
            'extraFields' => ['awsSecretKey', 'awsRegion'],
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::VERTEX => [
            'name' => 'Google Vertex AI',
            'description' => 'Enterprise Gemini and PaLM models',
            'requiresApiKey' => true,
            'settingsField' => 'vertexApiKey',
            'extraFields' => ['gcpProject', 'gcpRegion'],
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::ANTHROPIC_VERTEX => [
            'name' => 'Anthropic via Vertex',
            'description' => 'Claude models on Google Cloud',
            'requiresApiKey' => true,
            'settingsField' => 'vertexApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        
        // === Tier 5: Open Model Hosting ===
        ProviderName::HUGGINGFACE => [
            'name' => 'HuggingFace',
            'description' => 'Inference API for open models',
            'requiresApiKey' => true,
            'settingsField' => 'huggingfaceApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => true,
        ],
        ProviderName::DEEPINFRA => [
            'name' => 'DeepInfra',
            'description' => 'Fast inference for open models',
            'requiresApiKey' => true,
            'settingsField' => 'deepinfraApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => true,
        ],
        
        // === Tier 6: Local Providers ===
        ProviderName::OLLAMA => [
            'name' => 'Ollama',
            'description' => 'Run models locally (Llama, Mistral, etc.)',
            'requiresApiKey' => false,
            'settingsField' => null,
            'modelField' => 'ollamaModelId',
            'requiresBaseUrl' => true,
            'defaultBaseUrl' => 'http://localhost:11434',
            'dynamic' => true,
            'local' => true,
        ],
        ProviderName::LMSTUDIO => [
            'name' => 'LM Studio',
            'description' => 'Run models locally with LM Studio',
            'requiresApiKey' => false,
            'settingsField' => null,
            'modelField' => 'lmStudioModelId',
            'requiresBaseUrl' => true,
            'defaultBaseUrl' => 'http://localhost:1234',
            'dynamic' => true,
            'local' => true,
        ],
        
        // === Tier 7: Gateways & Proxies ===
        ProviderName::LITELLM => [
            'name' => 'LiteLLM',
            'description' => 'Universal LLM proxy gateway',
            'requiresApiKey' => true,
            'settingsField' => 'litellmApiKey',
            'modelField' => 'apiModelId',
            'requiresBaseUrl' => true,
            'dynamic' => true,
        ],
        ProviderName::REQUESTY => [
            'name' => 'Requesty',
            'description' => 'AI proxy service',
            'requiresApiKey' => true,
            'settingsField' => 'requestyApiKey',
            'modelField' => 'requestyModelId',
            'dynamic' => true,
        ],
        ProviderName::VERCEL_AI_GATEWAY => [
            'name' => 'Vercel AI Gateway',
            'description' => 'Vercel-hosted AI gateway',
            'requiresApiKey' => true,
            'settingsField' => 'vercelApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::UNBOUND => [
            'name' => 'Unbound',
            'description' => 'AI model proxy',
            'requiresApiKey' => true,
            'settingsField' => 'unboundApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        
        // === Tier 8: Chinese AI Providers ===
        ProviderName::MOONSHOT => [
            'name' => 'Moonshot',
            'description' => 'Kimi models from Moonshot AI',
            'requiresApiKey' => true,
            'settingsField' => 'moonshotApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::DOUBAO => [
            'name' => 'Doubao',
            'description' => 'ByteDance Doubao models',
            'requiresApiKey' => true,
            'settingsField' => 'doubaoApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::MINIMAX => [
            'name' => 'MiniMax',
            'description' => 'MiniMax AI models',
            'requiresApiKey' => true,
            'settingsField' => 'minimaxApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::QWENCODE => [
            'name' => 'Qwen Code',
            'description' => 'Alibaba Qwen coding models',
            'requiresApiKey' => true,
            'settingsField' => 'qwenApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        
        // === Tier 9: Emerging & Specialized ===
        ProviderName::CHUTES => [
            'name' => 'Chutes',
            'description' => 'AI model hosting',
            'requiresApiKey' => true,
            'settingsField' => 'chutesApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::FEATHERLESS => [
            'name' => 'Featherless',
            'description' => 'Lightweight AI inference',
            'requiresApiKey' => true,
            'settingsField' => 'featherlessApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::BASETEN => [
            'name' => 'Baseten',
            'description' => 'Model deployment platform',
            'requiresApiKey' => true,
            'settingsField' => 'basetenApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::ZAI => [
            'name' => 'ZAI',
            'description' => 'ZAI inference API',
            'requiresApiKey' => true,
            'settingsField' => 'zaiApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::IO_INTELLIGENCE => [
            'name' => 'IO Intelligence',
            'description' => 'IO Intelligence API',
            'requiresApiKey' => true,
            'settingsField' => 'ioApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        
        // === Tier 10: OpenAI Variants ===
        ProviderName::OPENAI_NATIVE => [
            'name' => 'OpenAI Native SDK',
            'description' => 'Direct OpenAI SDK integration',
            'requiresApiKey' => true,
            'settingsField' => 'openAiApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
        ProviderName::OPENAI_CODEX => [
            'name' => 'OpenAI Codex',
            'description' => 'Code-specialized OpenAI models',
            'requiresApiKey' => true,
            'settingsField' => 'openAiApiKey',
            'modelField' => 'apiModelId',
            'dynamic' => false,
        ],
    ];
}
