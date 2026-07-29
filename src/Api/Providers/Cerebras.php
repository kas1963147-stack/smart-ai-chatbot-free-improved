<?php
declare(strict_types=1);


/**
 * Cerebras Provider
 * 
 * 
 * Ultra-fast inference with Cerebras Wafer-Scale Engine
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
 * Cerebras model definitions
 */
const CEREBRAS_MODELS = [
    'llama3.1-70b' => [
        'contextWindow' => 128000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.85,
        'outputPrice' => 1.2,
    ],
    'llama3.1-8b' => [
        'contextWindow' => 128000,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 0.10,
        'outputPrice' => 0.10,
    ],
];

const CEREBRAS_DEFAULT_MODEL = 'llama3.1-70b';

/**
 * Cerebras Provider
 */
class Cerebras extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Cerebras',
            'https://api.cerebras.ai/v1',
            CEREBRAS_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->cerebrasApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (CEREBRAS_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
