<?php
declare(strict_types=1);


/**
 * Mistral Provider
 * 
 * 
 * Mistral AI models including Codestral
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;

/**
 * Mistral model definitions
 */
const MISTRAL_MODELS = [
    'mistral-large-latest' => [
        'contextWindow' => 128000,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 2.0,
        'outputPrice' => 6.0,
    ],
    'mistral-medium-latest' => [
        'contextWindow' => 32000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 2.7,
        'outputPrice' => 8.1,
    ],
    'mistral-small-latest' => [
        'contextWindow' => 32000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.2,
        'outputPrice' => 0.6,
    ],
    'codestral-latest' => [
        'contextWindow' => 32000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.2,
        'outputPrice' => 0.6,
    ],
    'open-mixtral-8x22b' => [
        'contextWindow' => 64000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 2.0,
        'outputPrice' => 6.0,
    ],
    'open-mixtral-8x7b' => [
        'contextWindow' => 32000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.7,
        'outputPrice' => 0.7,
    ],
];

const MISTRAL_DEFAULT_MODEL = 'mistral-large-latest';

/**
 * Mistral AI Provider
 */
class Mistral extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        // Use Codestral URL if model is codestral
        $modelId = $settings->apiModelId ?? MISTRAL_DEFAULT_MODEL;
        $baseUrl = strpos($modelId, 'codestral') !== false
            ? ($settings->mistralCodestralUrl ?? 'https://codestral.mistral.ai/v1')
            : 'https://api.mistral.ai/v1';
        
        parent::__construct(
            $settings,
            'Mistral',
            $baseUrl,
            MISTRAL_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->mistralApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (MISTRAL_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
