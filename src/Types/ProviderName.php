<?php
declare(strict_types=1);


/**
 * Provider Name Definitions
 * 
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Types;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * All supported provider names
 */
class ProviderName {
    // Dynamic providers (require external API calls for model list)
    public const OPENROUTER = 'openrouter';
    public const VERCEL_AI_GATEWAY = 'vercel-ai-gateway';
    public const HUGGINGFACE = 'huggingface';
    public const LITELLM = 'litellm';
    public const DEEPINFRA = 'deepinfra';
    public const IO_INTELLIGENCE = 'io-intelligence';
    public const REQUESTY = 'requesty';
    public const UNBOUND = 'unbound';
    public const CHUTES = 'chutes';
    
    // Local providers
    public const OLLAMA = 'ollama';
    public const LMSTUDIO = 'lmstudio';
    
    // Standard providers
    public const ANTHROPIC = 'anthropic';
    public const ANTHROPIC_VERTEX = 'anthropic-vertex';
    public const OPENAI = 'openai';
    public const AZURE = 'azure';
    public const BEDROCK = 'bedrock';
    public const BASETEN = 'baseten';
    public const CEREBRAS = 'cerebras';
    public const DOUBAO = 'doubao';
    public const DEEPSEEK = 'deepseek';
    public const FEATHERLESS = 'featherless';
    public const FIREWORKS = 'fireworks';
    public const GEMINI = 'gemini';
    public const GROQ = 'groq';
    public const MISTRAL = 'mistral';
    public const MOONSHOT = 'moonshot';
    public const MINIMAX = 'minimax';
    public const OPENAI_CODEX = 'openai-codex';
    public const OPENAI_NATIVE = 'openai-native';
    public const QWENCODE = 'qwen-code';
    public const SAMBANOVA = 'sambanova';
    public const VERTEX = 'vertex';
    public const XAI = 'xai';
    public const ZAI = 'zai';
    
    // Dynamic providers array
    public const DYNAMIC_PROVIDERS = [
        self::OPENROUTER,
        self::VERCEL_AI_GATEWAY,
        self::HUGGINGFACE,
        self::LITELLM,
        self::DEEPINFRA,
        self::IO_INTELLIGENCE,
        self::REQUESTY,
        self::UNBOUND,
        self::CHUTES,
    ];
    
    // Local providers array
    public const LOCAL_PROVIDERS = [
        self::OLLAMA,
        self::LMSTUDIO,
    ];
    
    // All provider names
    public const ALL = [
        self::OPENROUTER,
        self::VERCEL_AI_GATEWAY,
        self::HUGGINGFACE,
        self::LITELLM,
        self::DEEPINFRA,
        self::IO_INTELLIGENCE,
        self::REQUESTY,
        self::UNBOUND,
        self::CHUTES,
        self::OLLAMA,
        self::LMSTUDIO,
        self::ANTHROPIC,
        self::ANTHROPIC_VERTEX,
        self::OPENAI,
        self::AZURE,
        self::BEDROCK,
        self::BASETEN,
        self::CEREBRAS,
        self::DOUBAO,
        self::DEEPSEEK,
        self::FEATHERLESS,
        self::FIREWORKS,
        self::GEMINI,
        self::GROQ,
        self::MISTRAL,
        self::MOONSHOT,
        self::MINIMAX,
        self::OPENAI_CODEX,
        self::OPENAI_NATIVE,
        self::QWENCODE,
        self::SAMBANOVA,
        self::VERTEX,
        self::XAI,
        self::ZAI,
    ];
    
    public static function isValid(string $name): bool {
        return in_array($name, self::ALL, true);
    }
    
    public static function isDynamic(string $name): bool {
        return in_array($name, self::DYNAMIC_PROVIDERS, true);
    }
    
    public static function isLocal(string $name): bool {
        return in_array($name, self::LOCAL_PROVIDERS, true);
    }
}
