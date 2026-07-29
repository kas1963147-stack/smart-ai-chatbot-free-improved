<?php
declare(strict_types=1);


/**
 * HuggingFace Provider
 * 
 * 
 * HuggingFace Inference API via Router (OpenAI-compatible)
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;

const HUGGINGFACE_DEFAULT_MODEL = 'meta-llama/Llama-3.1-70B-Instruct';

/**
 * HuggingFace Provider
 * 
 * Uses the HuggingFace Router API (router.huggingface.co) which provides
 * an OpenAI-compatible interface for all available models.
 */
class HuggingFace extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'HuggingFace',
            'https://router.huggingface.co/v1',
            $settings->huggingFaceModelId ?? HUGGINGFACE_DEFAULT_MODEL,
            []
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->huggingFaceApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->huggingFaceModelId ?? HUGGINGFACE_DEFAULT_MODEL;
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $info = new ModelInfo(32768, false);
        return ['id' => $id, 'info' => $info];
    }
}
