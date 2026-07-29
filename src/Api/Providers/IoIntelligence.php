<?php
declare(strict_types=1);


/**
 * IoIntelligence Provider
 * 
 * 
 * IO Intelligence AI
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
 * IoIntelligence Provider
 */
class IoIntelligence extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'IO Intelligence',
            'https://api.intelligence.io.solutions/api/v1',
            $settings->ioIntelligenceModelId ?? 'meta-llama/Llama-3.1-70B-Instruct',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->ioIntelligenceApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->ioIntelligenceModelId ?? 'meta-llama/Llama-3.1-70B-Instruct';
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $info = new ModelInfo(128000, false);
        return ['id' => $id, 'info' => $info];
    }
}
