<?php
declare(strict_types=1);


/**
 * QwenCode Provider
 * 
 * 
 * Alibaba Qwen Code models
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
 * QwenCode Provider (Alibaba)
 */
class QwenCode extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'QwenCode',
            'https://dashscope.aliyuncs.com/compatible-mode/v1',
            'qwen-coder-plus',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->qwenCodeApiKey ?? null;
    }
    
    public function getModel(): array {
        $id = $this->settings->apiModelId ?? 'qwen-coder-plus';
        $info = new ModelInfo(131072, false);
        return ['id' => $id, 'info' => $info];
    }
}
