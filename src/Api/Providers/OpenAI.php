<?php
declare(strict_types=1);


/**
 * OpenAI Provider
 * 
 * 
 * Supports GPT-4, GPT-4o, o1, o3, o4 models
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
 * OpenAI model definitions
 */
const OPENAI_MODELS = [
    'gpt-4o' => [
        'contextWindow' => 128000,
        'maxTokens' => 16384,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 2.50,
        'outputPrice' => 10.0,
        'cacheReadsPrice' => 1.25,
    ],
    'gpt-4o-mini' => [
        'contextWindow' => 128000,
        'maxTokens' => 16384,
        'supportsImages' => true,
        'supportsPromptCache' => true,
        'inputPrice' => 0.15,
        'outputPrice' => 0.60,
        'cacheReadsPrice' => 0.075,
    ],
    'gpt-4-turbo' => [
        'contextWindow' => 128000,
        'maxTokens' => 4096,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'inputPrice' => 10.0,
        'outputPrice' => 30.0,
    ],
    'gpt-4' => [
        'contextWindow' => 8192,
        'maxTokens' => 4096,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'inputPrice' => 30.0,
        'outputPrice' => 60.0,
    ],
    'o1' => [
        'contextWindow' => 200000,
        'maxTokens' => 100000,
        'supportsImages' => true,
        'supportsPromptCache' => false,
        'supportsReasoningEffort' => true,
        'inputPrice' => 15.0,
        'outputPrice' => 60.0,
    ],
    'o1-mini' => [
        'contextWindow' => 128000,
        'maxTokens' => 65536,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'supportsReasoningEffort' => true,
        'inputPrice' => 3.0,
        'outputPrice' => 12.0,
    ],
    'o1-preview' => [
        'contextWindow' => 128000,
        'maxTokens' => 32768,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'supportsReasoningEffort' => true,
        'inputPrice' => 15.0,
        'outputPrice' => 60.0,
    ],
    'o3-mini' => [
        'contextWindow' => 128000,
        'maxTokens' => 65536,
        'supportsImages' => false,
        'supportsPromptCache' => false,
        'supportsReasoningEffort' => true,
        'inputPrice' => 1.10,
        'outputPrice' => 4.40,
    ],
];

const OPENAI_DEFAULT_MODEL = 'gpt-4o';

/**
 * OpenAI API Provider
 * 
 * Supports GPT-4, o1, o3 model families with reasoning
 */
class OpenAI extends BaseOpenAICompatible {
    public function __construct(ProviderSettings $settings) {
        parent::__construct(
            $settings,
            'OpenAI',
            $settings->openAiBaseUrl ?? 'https://api.openai.com/v1',
            OPENAI_DEFAULT_MODEL,
            self::buildModels()
        );
    }
    
    protected function getApiKey(): ?string {
        return $this->settings->openAiApiKey ?? null;
    }
    
    protected function getModelId(): string {
        return $this->settings->openAiModelId ?? OPENAI_DEFAULT_MODEL;
    }
    
    /**
     * Override createMessage for o1/o3/o4 family handling
     */
    public function createMessage(
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $modelId = $this->getModelId();
        
        // Check if this is an o1/o3/o4 reasoning model
        if (preg_match('/^o[134]/', $modelId)) {
            yield from $this->handleReasoningModel($modelId, $systemPrompt, $messages, $metadata);
            return;
        }
        
        // Regular GPT model - use parent implementation
        yield from parent::createMessage($systemPrompt, $messages, $metadata);
    }
    
    /**
     * Handle o1/o3/o4 reasoning models
     */
    protected function handleReasoningModel(
        string $modelId,
        string $systemPrompt,
        array $messages,
        ?CreateMessageMetadata $metadata = null
    ): Generator {
        $model = $this->getModel();
        $modelInfo = $model['info'];
        
        // o1/o3 models use developer message instead of system
        $openAiMessages = [];
        $openAiMessages[] = ['role' => 'developer', 'content' => $systemPrompt];
        
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $openAiMessages[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            } else {
                $openAiMessages[] = $message;
            }
        }
        
        // Build request body
        $maxTokens = $this->settings->modelMaxTokens ?? $modelInfo->maxTokens ?? 16384;
        
        $body = [
            'model' => $modelId,
            'max_completion_tokens' => $maxTokens,
            'messages' => $openAiMessages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ];
        
        // Add reasoning effort if supported
        if ($this->settings->enableReasoningEffort && $modelInfo->supportsReasoningEffort) {
            $effort = $this->settings->reasoningEffort ?? 'medium';
            $body['reasoning_effort'] = $effort;
        }
        
        // Add tools if provided
        if ($metadata?->tools) {
            $body['tools'] = $this->convertToolsForOpenAI($metadata->tools);
        }
        if ($metadata?->toolChoice) {
            $body['tool_choice'] = $metadata->toolChoice;
        }
        
        // Make streaming request
        yield from $this->streamRequest($body, $modelInfo);
    }
    
    private static function buildModels(): array {
        $models = [];
        foreach (OPENAI_MODELS as $id => $data) {
            $models[$id] = ModelInfo::fromArray($data);
        }
        return $models;
    }
}
