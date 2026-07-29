<?php
declare(strict_types=1);


/**
 * DeepInfra Provider
 * 
 * 
 * DeepInfra serverless inference
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;

const DEEPINFRA_MODELS = [
    'meta-llama/Llama-3.3-70B-Instruct' => [
        'contextWindow' => 131072,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'inputPrice' => 0.35,
        'outputPrice' => 0.40,
    ],
    'meta-llama/Llama-3.1-405B-Instruct' => [
        'contextWindow' => 32000,
        'maxTokens' => 4096,
        'supportsImages' => false,
        'inputPrice' => 1.79,
        'outputPrice' => 1.79,
    ],
    'Qwen/Qwen2.5-72B-Instruct' => [
        'contextWindow' => 32768,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'inputPrice' => 0.35,
        'outputPrice' => 0.40,
    ],
];

const DEEPINFRA_DEFAULT_MODEL = 'meta-llama/Llama-3.3-70B-Instruct';

/**
 * DeepInfra Provider
 */
class DeepInfra extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'DeepInfra',
            'https://api.deepinfra.com/v1/openai',
            DEEPINFRA_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->deepInfraApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (DEEPINFRA_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
