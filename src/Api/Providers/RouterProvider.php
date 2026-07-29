<?php
declare(strict_types=1);


/**
 * Router Provider Abstract Class
 * 
 * 
 * Base class for providers that route to multiple models dynamically
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
 * Abstract Router Provider
 * 
 * Base class for dynamic model routing providers like OpenRouter, Requesty, etc.
 */
abstract class RouterProvider extends BaseProvider {
    /** Provider name for routing */
    protected string $routerName;
    
    /** Base API URL */
    protected string $baseURL;
    
    /** Default model ID */
    protected string $defaultModelId;
    
    /** Default model info */
    protected ModelInfo $defaultModelInfo;
    
    /** Cached models from API */
    protected array $models = [];
    
    /** Model cache file path */
    protected string $cacheFilePath;
    
    public function __construct(
        ProviderSettings $settings,
        string $routerName,
        string $baseURL,
        string $defaultModelId,
        ModelInfo $defaultModelInfo
    ) {
        parent::__construct($settings);
        
        $this->routerName = $routerName;
        $this->baseURL = $baseURL;
        $this->defaultModelId = $defaultModelId;
        $this->defaultModelInfo = $defaultModelInfo;
        
        // Set cache file path
        $uploadDir = wp_upload_dir();
        $this->cacheFilePath = $uploadDir['basedir'] . "/chatbot-ai-cache/{$routerName}-models.json";
        
        // Load models from cache
        $this->loadModelsFromCache();
    }
    
    /**
     * Get API key - must be implemented by subclass
     */
    abstract protected function getApiKey(): ?string;
    
    /**
     * Get model ID to use
     */
    protected function getModelId(): string {
        return $this->settings->apiModelId ?? $this->defaultModelId;
    }
    
    /**
     * Get current model info
     */
    public function getModel(): array {
        $id = $this->getModelId();
        
        // Check cached models first
        if (isset($this->models[$id])) {
            return ['id' => $id, 'info' => $this->models[$id]];
        }
        
        // Fall back to default
        return ['id' => $this->defaultModelId, 'info' => $this->defaultModelInfo];
    }
    
    /**
     * Fetch models from API and cache
     */
    public function fetchModels(): array {
        $url = rtrim($this->baseURL, '/') . '/models';
        $apiKey = $this->getApiKey();
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $this->models;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->models = $this->parseModelsResponse($body);
        
        // Save to cache
        $this->saveModelsToCache();
        
        return $this->models;
    }
    
    /**
     * Parse models response from API
     */
    protected function parseModelsResponse(array $body): array {
        $models = [];
        
        foreach ($body['data'] ?? [] as $model) {
            $id = $model['id'] ?? null;
            if (!$id) continue;
            
            $models[$id] = ModelInfo::fromArray([
                'contextWindow' => $model['context_length'] ?? 128000,
                'maxTokens' => $model['max_completion_tokens'] ?? 4096,
                'supportsImages' => in_array('image', $model['supported_modalities'] ?? []),
                'supportsPromptCache' => $model['supports_prompt_cache'] ?? false,
                'inputPrice' => (float)($model['pricing']['prompt'] ?? 0) * 1000000,
                'outputPrice' => (float)($model['pricing']['completion'] ?? 0) * 1000000,
                'description' => $model['description'] ?? null,
            ]);
        }
        
        return $models;
    }
    
    /**
     * Load models from cache file
     */
    protected function loadModelsFromCache(): void {
        if (!file_exists($this->cacheFilePath)) {
            return;
        }
        
        $json = file_get_contents($this->cacheFilePath);
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['models'])) {
            return;
        }
        
        // Check if cache is expired (24 hours)
        $cacheTime = $data['timestamp'] ?? 0;
        if (time() - $cacheTime > 86400) {
            return;
        }
        
        foreach ($data['models'] as $id => $modelData) {
            $this->models[$id] = ModelInfo::fromArray($modelData);
        }
    }
    
    /**
     * Save models to cache file
     */
    protected function saveModelsToCache(): void {
        $dir = dirname($this->cacheFilePath);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        
        $data = [
            'timestamp' => time(),
            'models' => [],
        ];
        
        foreach ($this->models as $id => $model) {
            $data['models'][$id] = $model->toArray();
        }
        
        file_put_contents($this->cacheFilePath, json_encode($data));
    }
    
    /**
     * Check if model supports temperature
     */
    protected function supportsTemperature(string $modelId): bool {
        // o3-mini doesn't support temperature
        return strpos($modelId, 'openai/o3-mini') !== 0;
    }
    
    /**
     * Get list of available model IDs
     */
    public function getAvailableModels(): array {
        return array_keys($this->models);
    }
}
