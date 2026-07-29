<?php
declare(strict_types=1);


/**
 * Helper Functions
 * 
 * Easy-to-use factory functions - clean public API
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\ChatbotConfig;
use Quarksol\SmartChatbot\Types\ProviderSettings;
use Quarksol\SmartChatbot\Types\ProviderName;
use Quarksol\SmartChatbot\Agent\ShoppingAgent;
use Quarksol\SmartChatbot\Api\Providers\BaseProvider;
use function Quarksol\SmartChatbot\Api\buildApiHandler;

/**
 * Create provider from WordPress settings
 */
function createProviderFromSettings(): BaseProvider {
    $settings = ChatbotConfig::settings();
    
    $providerSettings = new ProviderSettings();
    $providerSettings->apiProvider = $settings['ai_provider'] ?? ProviderName::OPENROUTER;
    $providerSettings->apiModelId = $settings['model_id'] ?? null;
    
    $providerSettings->openRouterApiKey = $settings['openrouter_api_key'] ?? null;
    $providerSettings->openAiApiKey = $settings['openai_api_key'] ?? null;
    $providerSettings->apiKey = $settings['anthropic_api_key'] ?? null;
    $providerSettings->geminiApiKey = $settings['gemini_api_key'] ?? null;
    $providerSettings->groqApiKey = $settings['groq_api_key'] ?? null;
    $providerSettings->deepSeekApiKey = $settings['deepseek_api_key'] ?? null;
    
    $providerSettings->modelMaxTokens = $settings['max_tokens'] ?? 4096;
    $providerSettings->modelTemperature = $settings['temperature'] ?? 0.7;
    $providerSettings->enableReasoningEffort = $settings['enable_reasoning'] ?? false;
    $providerSettings->reasoningEffort = $settings['reasoning_effort'] ?? 'medium';
    
    return buildApiHandler($providerSettings);
}

/**
 * Create agent from WordPress settings
 */
function createAgentFromSettings(): ShoppingAgent {
    $settings = ChatbotConfig::settings();
    
    return ShoppingAgent::make([
        'provider' => $settings['ai_provider'] ?? ProviderName::OPENROUTER,
        'model' => $settings['model_id'] ?? null,
        'api_key' => $settings['api_key'] ?? $settings['openrouter_api_key'] ?? null,
        'max_tokens' => $settings['max_tokens'] ?? 4096,
        'temperature' => $settings['temperature'] ?? 0.7,
        'enable_reasoning' => $settings['enable_reasoning'] ?? false,
        'reasoning_effort' => $settings['reasoning_effort'] ?? 'medium',
    ]);
}

/**
 * Create provider with explicit parameters
 */
function createProvider(string $providerName, string $apiKey, ?string $model = null, array $options = []): BaseProvider {
    $settings = new ProviderSettings();
    $settings->apiProvider = $providerName;
    $settings->apiModelId = $model;
    
    switch ($providerName) {
        case ProviderName::OPENROUTER:
            $settings->openRouterApiKey = $apiKey;
            break;
        case ProviderName::OPENAI:
        case ProviderName::OPENAI_NATIVE:
            $settings->openAiApiKey = $apiKey;
            $settings->openAiNativeApiKey = $apiKey;
            break;
        case ProviderName::ANTHROPIC:
            $settings->apiKey = $apiKey;
            break;
        case ProviderName::GEMINI:
            $settings->geminiApiKey = $apiKey;
            break;
        case ProviderName::GROQ:
            $settings->groqApiKey = $apiKey;
            break;
        case ProviderName::DEEPSEEK:
            $settings->deepSeekApiKey = $apiKey;
            break;
        case ProviderName::MISTRAL:
            $settings->mistralApiKey = $apiKey;
            break;
        case ProviderName::XAI:
            $settings->xaiApiKey = $apiKey;
            break;
        default:
            $settings->apiKey = $apiKey;
    }
    
    if (isset($options['max_tokens'])) $settings->modelMaxTokens = $options['max_tokens'];
    if (isset($options['temperature'])) $settings->modelTemperature = $options['temperature'];
    if (isset($options['enable_reasoning'])) $settings->enableReasoningEffort = $options['enable_reasoning'];
    if (isset($options['reasoning_effort'])) $settings->reasoningEffort = $options['reasoning_effort'];
    
    return buildApiHandler($settings);
}

/**
 * Create agent with explicit parameters
 */
function createAgent(string $providerName, string $apiKey, ?string $model = null, array $options = []): ShoppingAgent {
    return ShoppingAgent::make(array_merge([
        'provider' => $providerName,
        'api_key' => $apiKey,
        'model' => $model,
    ], $options));
}
