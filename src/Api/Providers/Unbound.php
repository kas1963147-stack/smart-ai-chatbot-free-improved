<?php
declare(strict_types=1);


/**
 * Unbound Provider
 * 
 * 
 * Unbound AI gateway
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
 * Unbound AI Provider
 */
class Unbound extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Unbound',
            'https://api.getunbound.ai/v1',
            $settings->unboundModelId ?? 'gpt-4o',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->unboundApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->unboundModelId ?? 'gpt-4o';
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $info = new ModelInfo(128000, true);
        return ['id' => $id, 'info' => $info];
    }
}
