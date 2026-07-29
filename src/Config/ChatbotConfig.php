<?php
declare(strict_types=1);
/**
 * Chatbot Configuration
 * 
 * Single source of truth for all chatbot configuration constants and defaults.
 * Provides cached access to WordPress settings to avoid repeated DB queries.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chatbot Config
 * 
 * Contains all configuration constants, defaults, and cached settings access.
 */
class ChatbotConfig
{

    // ========== SETTINGS CACHE ==========
    
    /** @var array|null Cached settings from wp_options */
    private static ?array $settingsCache = null;
    
    /**
     * Get cached chatbot settings from WordPress options.
     * 
     * Eliminates repeated get_option() calls across the request lifecycle.
     * Call clearCache() if settings are updated during a request.
     * 
     * @return array The chatbot settings
     */
    public static function settings(): array
    {
        if (self::$settingsCache === null) {
            self::$settingsCache = get_option('swc_chatbot_settings', []);
        }
        return self::$settingsCache;
    }
    
    /**
     * Clear the settings cache.
     * 
     * Call after updating settings via the admin panel or API.
     */
    public static function clearCache(): void
    {
        self::$settingsCache = null;
    }
    
    // ========== RATE LIMITING ==========
    
    /** Default rate limit (requests per window) */
    const DEFAULT_RATE_LIMIT = 30;
    
    /** Rate limit window in seconds */
    const RATE_LIMIT_WINDOW = 60;
    
    /** Backoff multiplier for repeated violations */
    const RATE_LIMIT_BACKOFF_MULTIPLIER = 2;
    
    /** Maximum backoff time in seconds */
    const RATE_LIMIT_MAX_BACKOFF = 300;
    
    // ========== SESSION ==========
    
    /** Session timeout in seconds (30 minutes) */
    const SESSION_TIMEOUT = 1800;
    
    /** Maximum messages to keep in session history */
    const MAX_HISTORY_MESSAGES = 20;
    
    /** Maximum messages per session (hard limit) */
    const MAX_SESSION_MESSAGES = 100;
    
    /** Visitor cookie name */
    const VISITOR_COOKIE_NAME = 'swc_visitor_id';
    
    /** Visitor cookie lifetime in seconds (1 week) */
    const VISITOR_COOKIE_LIFETIME = 604800;
    
    /** Maximum user message length */
    const MAX_MESSAGE_LENGTH = 4000;
    
    // ========== SESSION LIFECYCLE ==========
    
    /** Days before sessions are archived */
    const SESSION_ARCHIVE_DAYS = 30;
    
    /** Days before sessions are deleted */
    const SESSION_DELETE_DAYS = 90;
    
    // ========== ANALYTICS ==========
    
    /** Days to retain raw analytics events */
    const ANALYTICS_RETENTION_DAYS = 90;
    
    /** Days to retain daily rollup data */
    const ANALYTICS_ROLLUP_RETENTION_DAYS = 365;
    
    // ========== KNOWLEDGE BASE ==========
    
    /** Maximum documents to retrieve for RAG context */
    const RAG_MAX_DOCUMENTS = 5;
    
    /** Maximum characters for RAG context */
    const RAG_MAX_CONTEXT_LENGTH = 4000;
    
    /** Minimum similarity score for document retrieval */
    const RAG_MIN_SIMILARITY = 0.3;
    
    /** Chunk size for knowledge base documents */
    const KB_CHUNK_SIZE = 1000;
    
    /** Overlap between chunks */
    const KB_CHUNK_OVERLAP = 200;
    
    // ========== AI PROVIDER ==========
    
    /** Default AI temperature */
    const DEFAULT_AI_TEMPERATURE = 0.7;
    
    /** Minimum temperature */
    const MIN_TEMPERATURE = 0.0;
    
    /** Maximum temperature */
    const MAX_TEMPERATURE = 2.0;
    
    /** Maximum tokens for AI response */
    const DEFAULT_MAX_TOKENS = 1000;
    
    /** Minimum tokens */
    const MIN_TOKENS = 100;
    
    /** Maximum tokens (hard limit) */
    const MAX_TOKENS = 32000;
    
    /** Request timeout in seconds */
    const AI_REQUEST_TIMEOUT = 30;
    
    /** Streaming request timeout in seconds */
    const AI_STREAMING_TIMEOUT = 120;
    
    /** Connection timeout in seconds */
    const AI_CONNECT_TIMEOUT = 10;
    
    /** Maximum retries for failed AI requests */
    const AI_MAX_RETRIES = 3;
    
    /** Providers that don't require API keys */
    const LOCAL_PROVIDERS = ['ollama', 'lmstudio'];
    
    /** All valid provider identifiers */
    const VALID_PROVIDERS = [
        'openai', 'anthropic', 'openrouter', 'gemini', 'groq',
        'deepseek', 'mistral', 'ollama', 'lmstudio', 'xai',
        'fireworks', 'cerebras', 'sambanova', 'azure', 'bedrock', 'vertex',
    ];
    
    // ========== PRODUCTS ==========
    
    /** Default product limit for queries */
    const DEFAULT_PRODUCT_LIMIT = 10;
    
    /** Maximum products in comparison */
    const MAX_COMPARISON_PRODUCTS = 5;
    
    /** FAQ display limit */
    const FAQ_LIMIT = 5;
    
    /** Days to consider a product "new arrival" */
    const NEW_ARRIVALS_DAYS = 30;
    
    // ========== CACHE TTL ==========
    
    /** Cache duration for product queries (5 minutes) */
    const PRODUCT_CACHE_TTL = 300;
    
    /** Cache duration for categories (24 hours) */
    const CATEGORY_CACHE_TTL = 86400;
    
    /** Cache duration for settings (5 minutes) */
    const SETTINGS_CACHE_TTL = 300;
    
    // ========== LOGGING ==========
    
    /** Log prefix for all chatbot logs */
    const LOG_PREFIX = '[SWC]';
    
    /** Enable debug logging */
    const DEBUG_LOGGING = false;
    
    /** Max log file size in bytes (5MB) */
    const LOG_MAX_SIZE = 5242880;
    
    /** Maximum log rotations to keep */
    const LOG_MAX_ROTATIONS = 3;
    
    /** Days to retain log files */
    const LOG_RETENTION_DAYS = 30;
    
    // ========== PROACTIVE ENGAGEMENT ==========
    
    /** Minimum delay before proactive message (seconds) */
    const PROACTIVE_MIN_DELAY = 5;
    
    /** Maximum delay before proactive message (seconds) */
    const PROACTIVE_MAX_DELAY = 300;
    
    /** Default delay for proactive messages (seconds) */
    const PROACTIVE_DEFAULT_DELAY = 30;
    
    // ========== FEATURE FLAGS ==========
    
    /** Use new IntentRouter instead of legacy process_message */
    const USE_NEW_ROUTER = true;
    
    /** Direct AI Mode - bypass all automated responses, send straight to AI agent with tools */
    const DIRECT_AI_MODE = true;
    
    /** Enable streaming responses */
    const ENABLE_STREAMING = true;
    
    /** Enable proactive engagement */
    const ENABLE_PROACTIVE = true;
    
    // ========== CHAT ROLES ==========
    
    const ROLE_USER = 'user';
    const ROLE_ASSISTANT = 'assistant';
    const ROLE_SYSTEM = 'system';
    
    // ========== RESPONSE TYPES ==========
    
    const TYPE_TEXT = 'text';
    const TYPE_PRODUCTS = 'products';
    const TYPE_CART = 'cart';
    const TYPE_ERROR = 'error';
    
    // ========== SESSION STATUS ==========
    
    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_DELETED = 'deleted';
    

    
    // ========== HELPER METHODS ==========
    
    /**
     * Get a configuration value with optional override from settings
     */
    public static function get(string $key, $default = null)
    {
        $settings = self::settings();
        
        // Check settings first, then fall back to constant
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        
        // Map to constants
        return match($key) {
            'rate_limit' => self::DEFAULT_RATE_LIMIT,
            'rate_limit_window' => self::RATE_LIMIT_WINDOW,
            'session_timeout' => self::SESSION_TIMEOUT,
            'max_history' => self::MAX_HISTORY_MESSAGES,
            'ai_temperature' => self::DEFAULT_AI_TEMPERATURE,
            'max_tokens' => self::DEFAULT_MAX_TOKENS,
            'ai_timeout' => self::AI_REQUEST_TIMEOUT,
            'product_limit' => self::DEFAULT_PRODUCT_LIMIT,
            'rag_max_docs' => self::RAG_MAX_DOCUMENTS,
            'use_new_router' => self::USE_NEW_ROUTER,
            'enable_streaming' => self::ENABLE_STREAMING,
            default => $default,
        };
    }
    
    /**
     * Check if a provider is local (no API key needed)
     */
    public static function isLocalProvider(string $provider): bool
    {
        return in_array($provider, self::LOCAL_PROVIDERS, true);
    }
    
    /**
     * Check if a provider identifier is valid
     */
    public static function isValidProvider(string $provider): bool
    {
        return in_array(strtolower($provider), self::VALID_PROVIDERS, true);
    }
    
    /**
     * Check if feature flag is enabled
     */
    public static function isFeatureEnabled(string $feature): bool
    {
        return match($feature) {
            'new_router' => self::USE_NEW_ROUTER,
            'streaming' => self::ENABLE_STREAMING,
            'proactive' => self::ENABLE_PROACTIVE,
            'debug' => self::DEBUG_LOGGING,
            'direct_ai' => self::DIRECT_AI_MODE,
            default => false,
        };
    }
    
    /**
     * Check if AI is enabled in settings
     */
    public static function isAIEnabled(): bool
    {
        $settings = self::settings();
        return !empty($settings['ai_enabled']);
    }
}
