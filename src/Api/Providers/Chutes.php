<?php
declare(strict_types=1);


/**
 * Chutes Provider
 * 
 * 
 * Chutes AI
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
 * Chutes Provider
 */
class Chutes extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Chutes',
            'https://llm.chutes.ai/v1',
            $settings->chutesModelId ?? 'deepseek-ai/DeepSeek-V3',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->chutesApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->chutesModelId ?? 'deepseek-ai/DeepSeek-V3';
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $info = new ModelInfo(64000, false);
        return ['id' => $id, 'info' => $info];
    }
}
