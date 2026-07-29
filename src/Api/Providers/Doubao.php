<?php
declare(strict_types=1);


/**
 * Doubao Provider
 * 
 * 
 * ByteDance Doubao (豆包)
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;

const DOUBAO_DEFAULT_MODEL = 'doubao-1.5-pro-32k';

/**
 * Doubao (ByteDance) Provider
 */
class Doubao extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'Doubao',
            'https://ark.cn-beijing.volces.com/api/v3',
            DOUBAO_DEFAULT_MODEL,
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->doubaoApiKey ?? null;
    }
    
    public function getModel(): array {
        $id = $this->settings->apiModelId ?? DOUBAO_DEFAULT_MODEL;
        $info = new ModelInfo(32768, true);
        return ['id' => $id, 'info' => $info];
    }
}
