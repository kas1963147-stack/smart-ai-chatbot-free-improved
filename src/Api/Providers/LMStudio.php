<?php
declare(strict_types=1);


/**
 * LM Studio Provider
 * 
 * 
 * Run models locally with LM Studio
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;

const LMSTUDIO_DEFAULT_URL = 'http://localhost:1234/v1';

/**
 * LM Studio Provider
 * 
 * Uses OpenAI-compatible API
 */
class LMStudio extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'LM Studio',
            $settings->lmStudioBaseUrl ?? LMSTUDIO_DEFAULT_URL,
            $settings->lmStudioModelId ?? 'default',
            []
        );
    }
    
    protected function getApiKey(): ?string {
        // LM Studio doesn't require an API key for local use
        return 'lm-studio';
    }
    
    protected function getModelId(): string {
        return $this->settings->lmStudioModelId ?? 'default';
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        
        // LM Studio models are dynamic, create default info
        $info = new ModelInfo(
            32768, // contextWindow - typical for local models
            false  // supportsImages
        );
        
        return ['id' => $id, 'info' => $info];
    }
    
    /**
     * List available models from LM Studio
     */
    public function listModels(): array {
        $url = rtrim($this->baseURL, '/') . '/models';
        
        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array_map(function($model) {
            return [
                'id' => $model['id'],
                'name' => $model['id'],
            ];
        }, $body['data'] ?? []);
    }
}
