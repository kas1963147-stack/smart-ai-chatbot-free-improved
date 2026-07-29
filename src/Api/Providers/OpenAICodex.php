<?php
declare(strict_types=1);


/**
 * OpenAI Codex Provider
 * 
 * 
 * OpenAI Codex CLI integration
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ModelInfo;
use Quarksol\SmartChatbot\Types\ProviderSettings;
use Generator;

/**
 * OpenAI Codex models
 */
const OPENAI_CODEX_MODELS = [
    'codex-0' => [
        'contextWindow' => 192000,
        'maxTokens' => 32768,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 12.0,
    ],
    'codex-1' => [
        'contextWindow' => 192000,
        'maxTokens' => 32768,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 12.0,
    ],
    'o4-mini' => [
        'contextWindow' => 200000,
        'maxTokens' => 100000,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'supportsReasoningEffort' => true,
        'inputPrice' => 1.10,
        'outputPrice' => 4.40,
    ],
];

const OPENAI_CODEX_DEFAULT_MODEL = 'codex-1';

/**
 * OpenAI Codex Provider
 * 
 * Supports Codex CLI models with workspace awareness
 */
class OpenAICodex extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'OpenAI Codex',
            $settings->openCodexBaseUrl ?? 'https://api.openai.com/v1',
            OPENAI_CODEX_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->openCodexApiKey ?? $this->settings->openAiApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->openCodexModelId ?? OPENAI_CODEX_DEFAULT_MODEL;
    }
    
    public function getModel(): array {
        $id = $this->getModelId();
        $modelData = OPENAI_CODEX_MODELS[$id] ?? OPENAI_CODEX_MODELS[OPENAI_CODEX_DEFAULT_MODEL];
        $info = ModelInfo::fromArray($modelData);
        
        // Handle reasoning effort
        $reasoning = null;
        if ($this->settings->enableReasoningEffort && ($info->supportsReasoningEffort ?? false)) {
            $reasoning = ['effort' => $this->settings->reasoningEffort ?? 'medium'];
        }
        
        $maxTokens = $this->settings->modelMaxTokens ?? $info->maxTokens ?? 32768;
        $temperature = $this->settings->modelTemperature ?? 0;
        
        return [
            'id' => $id,
            'info' => $info,
            'maxTokens' => $maxTokens,
            'temperature' => $temperature,
            'reasoning' => $reasoning,
        ];
    }
    
    /**
     * Override to use Chat Completions for codex models
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelId = $model['id'];
        
        // For codex models, use the parent OpenAI-compatible implementation
        yield from parent::createMessage($systemPrompt, $messages, $metadata);
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (OPENAI_CODEX_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
