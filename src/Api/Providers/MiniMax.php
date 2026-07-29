<?php
declare(strict_types=1);


/**
 * MiniMax Provider
 * 
 * 
 * MiniMax AI
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;

const MINIMAX_MODELS = [
    'abab6.5s-chat' => [
        'contextWindow' => 245000,
        'maxTokens' => 8192,
        'inputPrice' => 0.015,
        'outputPrice' => 0.015,
    ],
    'abab6.5-chat' => [
        'contextWindow' => 8192,
        'maxTokens' => 4096,
        'inputPrice' => 0.03,
        'outputPrice' => 0.03,
    ],
];

const MINIMAX_DEFAULT_MODEL = 'abab6.5s-chat';

/**
 * MiniMax Provider
 */
class MiniMax extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        $groupId = $settings->minimaxGroupId ?? '';
        
        parent::__construct(
            $settings,
            'MiniMax',
            "https://api.minimax.chat/v1",
            MINIMAX_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->minimaxApiKey ?? null;
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (MINIMAX_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
