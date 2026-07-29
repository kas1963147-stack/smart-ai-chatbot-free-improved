<?php
declare(strict_types=1);


/**
 * LiteLLM Provider
 * 
 * 
 * Universal LLM proxy supporting 100+ providers
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
 * LiteLLM Provider
 * 
 * Acts as a universal proxy to many LLM providers
 */
class LiteLLM extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'LiteLLM',
            $settings->liteLlmBaseUrl ?? 'http://localhost:4000',
            $settings->liteLlmModelId ?? 'gpt-4o',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->liteLlmApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->liteLlmModelId ?? 'gpt-4o';
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $info = new ModelInfo(128000, true);
        return ['id' => $id, 'info' => $info];
    }
}
