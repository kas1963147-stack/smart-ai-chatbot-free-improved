<?php
declare(strict_types=1);


/**
 * Moonshot Provider
 * 
 * 
 * Moonshot AI (Kimi)
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;

const MOONSHOT_MODELS = [
    'moonshot-v1-8k' => [
        'contextWindow' => 8192,
        'maxTokens' => 4096,
        'inputPrice' => 0.012,
        'outputPrice' => 0.012,
    ],
    'moonshot-v1-32k' => [
        'contextWindow' => 32768,
        'maxTokens' => 16384,
        'inputPrice' => 0.024,
        'outputPrice' => 0.024,
    ],
    'moonshot-v1-128k' => [
        'contextWindow' => 131072,
        'maxTokens' => 65536,
        'inputPrice' => 0.06,
        'outputPrice' => 0.06,
    ],
];

const MOONSHOT_DEFAULT_MODEL = 'moonshot-v1-32k';

/**
 * Moonshot AI Provider
 */
class Moonshot extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Moonshot',
            'https://api.moonshot.cn/v1',
            MOONSHOT_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->moonshotApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (MOONSHOT_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
