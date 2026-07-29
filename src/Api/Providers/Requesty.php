<?php
declare(strict_types=1);


/**
 * Requesty Provider
 * 
 * 
 * Requesty AI gateway
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
 * Requesty Provider
 */
class Requesty extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Requesty',
            'https://router.requesty.ai/v1',
            $settings->requestyModelId ?? 'gpt-4o',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->requestyApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->requestyModelId ?? 'gpt-4o';
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $info = new ModelInfo(128000, true);
        return ['id' => $id, 'info' => $info];
    }
}
