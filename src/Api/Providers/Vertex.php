<?php
declare(strict_types=1);


/**
 * Vertex Provider (Google Cloud)
 * 
 * 
 * Extends Gemini for Vertex AI deployment
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
 * Vertex AI model definitions
 */
const VERTEX_MODELS = [
    'gemini-2.0-flash' => [
        'contextWindow' => 1048576,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 0.10,
        'outputPrice' => 0.40,
    ],
    'gemini-1.5-pro' => [
        'contextWindow' => 2097152,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 1.25,
        'outputPrice' => 5.0,
    ],
    'gemini-2.0-flash-thinking' => [
        'contextWindow' => 1048576,
        'maxTokens' => 8192,
        'supportsImages' => true,
        'supportsReasoningBinary' => true,
        'inputPrice' => 0.10,
        'outputPrice' => 0.40,
    ],
];

const VERTEX_DEFAULT_MODEL = 'gemini-1.5-pro';

/**
 * Vertex AI Provider
 * 
 * Uses Google Cloud's Vertex AI with Gemini models
 */
class Vertex extends Gemini {
    protected bool $isVertex = true;
    
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        
        // Override base URL for Vertex
        $project = $settings->vertexProjectId ?? 'your-project';
        $region = $settings->vertexRegion ?? 'us-central1';
        $this->baseURL = "https://{$region}-aiplatform.googleapis.com/v1/projects/{$project}/locations/{$region}/publishers/google/models";
    }
    
    public function getModel(): array {
        $id = $this->settings->apiModelId ?? VERTEX_DEFAULT_MODEL;
        
        // Handle :thinking suffix for hybrid models
        $actualId = str_replace(':thinking', '', $id);
        
        $modelData = VERTEX_MODELS[$actualId] ?? VERTEX_MODELS[VERTEX_DEFAULT_MODEL];
        $info = ModelInfo::fromArray($modelData);
        
        // Apply reasoning params
        $reasoning = null;
        if ($this->settings->enableReasoningEffort && $info->supportsReasoningBinary) {
            $reasoning = ['type' => 'enabled'];
        }
        
        return [
            'id' => $actualId,
            'info' => $info,
            'reasoning' => $reasoning,
        ];
    }
}
