<?php
declare(strict_types=1);


/**
 * Baseten Provider
 * 
 * 
 * Baseten model deployment
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
 * Baseten Provider
 */
class Baseten extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Baseten',
            'https://bridge.baseten.co/v1/direct',
            'llama-3.1-70b-instruct',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->basetenApiKey ?? null;
    }
    
    public function getModel(): array {
        $id = $this->settings->apiModelId ?? 'llama-3.1-70b-instruct';
        $info = new ModelInfo(128000, false);
        return ['id' => $id, 'info' => $info];
    }
}
