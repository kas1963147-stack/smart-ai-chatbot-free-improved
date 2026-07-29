<?php
declare(strict_types=1);
/**
 * Modern Provider Factory
 * 
 * Creates instances of modern AI providers.
 * 
 * @package Quarksol\SmartChatbot\Api\Providers
 */

namespace Quarksol\SmartChatbot\Api\Providers;

use Quarksol\SmartChatbot\Types\ProviderSettings;
use Quarksol\SmartChatbot\Api\Providers\OpenAI;
// Add other providers as needed

if (!defined('ABSPATH')) {
    exit;
}

class ProviderFactory {
    
    /**
     * Create a provider instance
     * 
     * @param string $providerId Provider slug (openai, anthropic, etc.)
     * @param array $config Configuration array
     * @return BaseProvider|null
     */
    public static function create(string $providerId, array $config): ?BaseProvider {
        
        // Map simplified config to ProviderSettings
        $settings = new ProviderSettings();
        
        // Common map (can be expanded for provider-specific fields)
        switch ($providerId) {
            case 'openai':
                $settings->openAiApiKey = $config['apiKey'] ?? '';
                $settings->openAiModelId = $config['model'] ?? 'gpt-4o';
                $settings->openAiBaseUrl = $config['baseUrl'] ?? 'https://api.openai.com/v1';
                return new OpenAI($settings);
                
            // Add other cases as we migrate them...
            // For now, we mainly support OpenAI via the modern path for demonstration
            
            default:
                // Fallback or error
                \Quarksol\SmartChatbot\Services\Logger::warning('Unknown provider', ['provider' => $providerId]);
                return null;
        }
    }
}
