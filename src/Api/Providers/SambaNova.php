<?php
declare(strict_types=1);


/**
 * SambaNova Provider
 * 
 * 
 * SambaNova AI inference
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
 * SambaNova model definitions
 */
const SAMBANOVA_MODELS = [
    'Meta-Llama-3.1-405B-Instruct' => [
        'contextWindow' => 8192,
        'maxTokens' => 4096,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 5.0,
        'outputPrice' => 10.0,
    ],
    'Meta-Llama-3.1-70B-Instruct' => [
        'contextWindow' => 8192,
        'maxTokens' => 4096,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.9,
        'outputPrice' => 0.9,
    ],
    'Meta-Llama-3.1-8B-Instruct' => [
        'contextWindow' => 8192,
        'maxTokens' => 4096,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.1,
        'outputPrice' => 0.1,
    ],
];

const SAMBANOVA_DEFAULT_MODEL = 'Meta-Llama-3.1-70B-Instruct';

/**
 * SambaNova Provider
 */
class SambaNova extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'SambaNova',
            'https://api.sambanova.ai/v1',
            SAMBANOVA_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->sambaNovaApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (SAMBANOVA_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
