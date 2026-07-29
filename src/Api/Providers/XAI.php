<?php
declare(strict_types=1);


/**
 * xAI (Grok) Provider
 * 
 * 
 * xAI's Grok models
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
 * xAI model definitions
 */
const XAI_MODELS = [
    'grok-2-1212' => [
        'contextWindow' => 131072,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 2.0,
        'outputPrice' => 10.0,
    ],
    'grok-2-vision-1212' => [
        'contextWindow' => 32768,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 2.0,
        'outputPrice' => 10.0,
    ],
    'grok-beta' => [
        'contextWindow' => 131072,
        'maxTokens' => 8192,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 5.0,
        'outputPrice' => 15.0,
    ],
];

const XAI_DEFAULT_MODEL = 'grok-2-1212';

/**
 * xAI Provider (Grok)
 */
class XAI extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'xAI',
            'https://api.x.ai/v1',
            XAI_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->xaiApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (XAI_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
