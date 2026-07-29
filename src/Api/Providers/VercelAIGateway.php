<?php
declare(strict_types=1);


/**
 * VercelAIGateway Provider
 * 
 * 
 * Vercel AI Gateway
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
 * VercelAIGateway Provider
 */
class VercelAIGateway extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Vercel AI Gateway',
            $settings->vercelAiGatewayBaseUrl ?? 'https://gateway.ai.vercel.app/v1',
            $settings->vercelAiGatewayModelId ?? 'gpt-4o',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->vercelAiGatewayApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->vercelAiGatewayModelId ?? 'gpt-4o';
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $info = new ModelInfo(128000, true);
        return ['id' => $id, 'info' => $info];
    }
}
