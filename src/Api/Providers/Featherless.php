<?php
declare(strict_types=1);


/**
 * Featherless Provider
 * 
 * 
 * Featherless AI
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
 * Featherless Provider
 */
class Featherless extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Featherless',
            'https://api.featherless.ai/v1',
            $settings->apiModelId ?? 'meta-llama/Llama-3.1-70B-Instruct',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->featherlessApiKey ?? null;
    }
    
    public function getModel(): array {
        $id = $this->settings->apiModelId ?? 'meta-llama/Llama-3.1-70B-Instruct';
        $info = new ModelInfo(128000, false);
        return ['id' => $id, 'info' => $info];
    }
}
