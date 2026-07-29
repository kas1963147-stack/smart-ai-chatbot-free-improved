<?php
declare(strict_types=1);


/**
 * Model Info Type Definition
 * 
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Types;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reasoning effort levels for thinking models
 */
class ReasoningEffort {
    public const DISABLE = 'disable';
    public const NONE = 'none';
    public const MINIMAL = 'minimal';
    public const LOW = 'low';
    public const MEDIUM = 'medium';
    public const HIGH = 'high';
    public const XHIGH = 'xhigh';
    
    public const ALL = [
        self::DISABLE,
        self::NONE,
        self::MINIMAL,
        self::LOW,
        self::MEDIUM,
        self::HIGH,
        self::XHIGH,
    ];
    
    public static function isValid(string $value): bool {
        return in_array($value, self::ALL, true);
    }
}

/**
 * Verbosity levels for model output
 */
class VerbosityLevel {
    public const LOW = 'low';
    public const MEDIUM = 'medium';
    public const HIGH = 'high';
    
    public const ALL = [self::LOW, self::MEDIUM, self::HIGH];
}

/**
 * Service tiers (OpenAI Responses API)
 */
class ServiceTier {
    public const DEFAULT = 'default';
    public const FLEX = 'flex';
    public const PRIORITY = 'priority';
    
    public const ALL = [self::DEFAULT, self::FLEX, self::PRIORITY];
}

/**
 * Model parameters that can be configured
 */
class ModelParameter {
    public const MAX_TOKENS = 'max_tokens';
    public const TEMPERATURE = 'temperature';
    public const REASONING = 'reasoning';
    public const INCLUDE_REASONING = 'include_reasoning';
    
    public const ALL = [
        self::MAX_TOKENS,
        self::TEMPERATURE,
        self::REASONING,
        self::INCLUDE_REASONING,
    ];
}

/**
 * Model information structure
 * 
 * Contains all capability flags and pricing for a model
 */
class ModelInfo {
    /** Maximum output tokens */
    public ?int $maxTokens = null;
    
    /** Maximum thinking/reasoning tokens */
    public ?int $maxThinkingTokens = null;
    
    /** Context window size */
    public int $contextWindow;
    
    /** Whether model supports image inputs */
    public bool $supportsImages = false;
    
    /** Whether model supports prompt caching */
    public bool $supportsPromptCache = false;
    
    /** Prompt cache retention: 'in_memory' or '24h' */
    public ?string $promptCacheRetention = null;
    
    /** Whether model supports verbosity parameter */
    public bool $supportsVerbosity = false;
    
    /** Whether model supports reasoning budget (token count) */
    public bool $supportsReasoningBudget = false;
    
    /** Whether model supports simple on/off binary reasoning */
    public bool $supportsReasoningBinary = false;
    
    /** Whether model supports temperature parameter */
    public bool $supportsTemperature = true;
    
    /** Default temperature for this model */
    public ?float $defaultTemperature = null;
    
    /** Whether reasoning budget is required */
    public bool $requiredReasoningBudget = false;
    
    /** 
     * Reasoning effort support
     * Can be boolean or array of allowed values
     * @var bool|array|null
     */
    public $supportsReasoningEffort = null;
    
    /** Whether reasoning effort is required */
    public bool $requiredReasoningEffort = false;
    
    /** Whether to preserve reasoning in output */
    public bool $preserveReasoning = false;
    
    /** Supported parameters */
    public array $supportedParameters = [];
    
    /** Input price per 1M tokens */
    public ?float $inputPrice = null;
    
    /** Output price per 1M tokens */
    public ?float $outputPrice = null;
    
    /** Cache writes price per 1M tokens */
    public ?float $cacheWritesPrice = null;
    
    /** Cache reads price per 1M tokens */
    public ?float $cacheReadsPrice = null;
    
    /** Model description */
    public ?string $description = null;
    
    /** Default reasoning effort level */
    public ?string $reasoningEffort = null;
    
    /** Minimum tokens per cache point */
    public ?int $minTokensPerCachePoint = null;
    
    /** Maximum cache points */
    public ?int $maxCachePoints = null;
    
    /** Cacheable fields */
    public array $cachableFields = [];
    
    /** Whether model is deprecated */
    public bool $deprecated = false;
    
    /** Whether model hides vendor identity */
    public bool $isStealthModel = false;
    
    /** Whether model is free */
    public bool $isFree = false;
    
    /** Tools to exclude from this model */
    public array $excludedTools = [];
    
    /** Tools to include for this model */
    public array $includedTools = [];
    
    /** Service tiers with specific pricing */
    public array $tiers = [];
    
    public function __construct(int $contextWindow, bool $supportsPromptCache = false) {
        $this->contextWindow = $contextWindow;
        $this->supportsPromptCache = $supportsPromptCache;
    }
    
    /**
     * Create from array (e.g., from JSON or API response)
     */
    public static function fromArray(array $data): self {
        $info = new self(
            $data['contextWindow'] ?? 128000,
            $data['supportsPromptCache'] ?? false
        );
        
        foreach ($data as $key => $value) {
            if (property_exists($info, $key)) {
                $info->$key = $value;
            }
        }
        
        return $info;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return get_object_vars($this);
    }
}
