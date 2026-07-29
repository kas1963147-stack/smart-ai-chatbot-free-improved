<?php
declare(strict_types=1);


/**
 * Fireworks Provider
 * 
 * 
 * Fast inference with Fireworks AI
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
 * Fireworks model definitions
 */
const FIREWORKS_MODELS = [
    'accounts/fireworks/models/llama-v3p1-405b-instruct' => [
        'contextWindow' => 131072,
        'maxTokens' => 16384,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 3.0,
        'outputPrice' => 3.0,
    ],
    'accounts/fireworks/models/llama-v3p1-70b-instruct' => [
        'contextWindow' => 131072,
        'maxTokens' => 16384,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.9,
        'outputPrice' => 0.9,
    ],
    'accounts/fireworks/models/llama-v3p1-8b-instruct' => [
        'contextWindow' => 131072,
        'maxTokens' => 16384,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.2,
        'outputPrice' => 0.2,
    ],
    'accounts/fireworks/models/mixtral-8x22b-instruct' => [
        'contextWindow' => 65536,
        'maxTokens' => 16384,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.9,
        'outputPrice' => 0.9,
    ],
];

const FIREWORKS_DEFAULT_MODEL = 'accounts/fireworks/models/llama-v3p1-70b-instruct';

/**
 * Fireworks AI Provider
 */
class Fireworks extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Fireworks',
            'https://api.fireworks.ai/inference/v1',
            FIREWORKS_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->fireworksApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (FIREWORKS_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
