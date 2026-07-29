<?php
declare(strict_types=1);


/**
 * ZAI Provider
 * 
 * 
 * ZAI AI platform
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
 * ZAI Provider
 */
class ZAI extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'ZAI',
            'https://api.zai.dev/v1',
            $settings->apiModelId ?? 'gpt-4o',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->zaiApiKey ?? null;
    }
    
    public function getModel(): array {
        $id = $this->settings->apiModelId ?? 'gpt-4o';
        $info = new ModelInfo(128000, true);
        return ['id' => $id, 'info' => $info];
    }
}
