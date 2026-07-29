<?php
declare(strict_types=1);
/**
 * Constants (DEPRECATED)
 * 
 * This file is deprecated. All constants have been consolidated into
 * Quarksol\SmartChatbot\Config\ChatbotConfig, which is the single source of truth.
 * 
 * This file is kept for backward compatibility only.
 * Use ChatbotConfig instead of referencing these constants.
 * 
 * @deprecated use Quarksol\SmartChatbot\Config\ChatbotConfig instead
 * @package App
 */

namespace Quarksol\SmartChatbot;

use Quarksol\SmartChatbot\Config\ChatbotConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Constants
 * 
 * @deprecated use Quarksol\SmartChatbot\Config\ChatbotConfig instead
 * @see ChatbotConfig
 */
final class Constants {
    
    // All constants below are aliases to ChatbotConfig for backward compatibility.
    // New code should use ChatbotConfig directly.
    
    // === Message Limits ===
    public const MAX_HISTORY_MESSAGES = ChatbotConfig::MAX_HISTORY_MESSAGES;
    public const MAX_SESSION_MESSAGES = ChatbotConfig::MAX_SESSION_MESSAGES;
    public const MAX_MESSAGE_LENGTH = ChatbotConfig::MAX_MESSAGE_LENGTH;
    
    // === Product Display ===
    public const DEFAULT_PRODUCT_LIMIT = ChatbotConfig::DEFAULT_PRODUCT_LIMIT;
    public const FAQ_LIMIT = ChatbotConfig::FAQ_LIMIT;
    public const NEW_ARRIVALS_DAYS = ChatbotConfig::NEW_ARRIVALS_DAYS;
    public const MAX_COMPARISON_PRODUCTS = ChatbotConfig::MAX_COMPARISON_PRODUCTS;
    
    // === Rate Limiting ===
    public const RATE_LIMIT_CHAT_REQUESTS = ChatbotConfig::DEFAULT_RATE_LIMIT;
    public const RATE_LIMIT_CHAT_WINDOW = ChatbotConfig::RATE_LIMIT_WINDOW;
    public const RATE_LIMIT_SESSION_REQUESTS = 10;
    public const RATE_LIMIT_SESSION_WINDOW = ChatbotConfig::RATE_LIMIT_WINDOW;
    
    // === API Timeouts ===
    public const API_TIMEOUT_DEFAULT = ChatbotConfig::AI_REQUEST_TIMEOUT;
    public const API_TIMEOUT_STREAMING = ChatbotConfig::AI_STREAMING_TIMEOUT;
    public const API_CONNECT_TIMEOUT = ChatbotConfig::AI_CONNECT_TIMEOUT;
    
    // === Token Limits ===
    public const DEFAULT_MAX_TOKENS = ChatbotConfig::DEFAULT_MAX_TOKENS;
    public const MIN_TOKENS = ChatbotConfig::MIN_TOKENS;
    public const MAX_TOKENS = ChatbotConfig::MAX_TOKENS;
    
    // === Temperature ===
    public const DEFAULT_TEMPERATURE = ChatbotConfig::DEFAULT_AI_TEMPERATURE;
    public const MIN_TEMPERATURE = ChatbotConfig::MIN_TEMPERATURE;
    public const MAX_TEMPERATURE = ChatbotConfig::MAX_TEMPERATURE;
    
    // === Session Settings ===
    public const SESSION_ARCHIVE_DAYS = ChatbotConfig::SESSION_ARCHIVE_DAYS;
    public const SESSION_DELETE_DAYS = ChatbotConfig::SESSION_DELETE_DAYS;
    
    // === Knowledge Base ===
    public const KB_CHUNK_SIZE = ChatbotConfig::KB_CHUNK_SIZE;
    public const KB_CHUNK_OVERLAP = ChatbotConfig::KB_CHUNK_OVERLAP;
    public const KB_SEARCH_TOP_K = ChatbotConfig::RAG_MAX_DOCUMENTS;
    public const KB_MIN_SIMILARITY_SCORE = ChatbotConfig::RAG_MIN_SIMILARITY;
    
    // === Cache TTL (seconds) ===
    public const CACHE_TTL_PRODUCTS = ChatbotConfig::PRODUCT_CACHE_TTL;
    public const CACHE_TTL_CATEGORIES = ChatbotConfig::CATEGORY_CACHE_TTL;
    public const CACHE_TTL_SETTINGS = ChatbotConfig::SETTINGS_CACHE_TTL;
    
    // === Log Settings ===
    public const LOG_MAX_SIZE = ChatbotConfig::LOG_MAX_SIZE;
    public const LOG_MAX_ROTATIONS = ChatbotConfig::LOG_MAX_ROTATIONS;
    public const LOG_RETENTION_DAYS = ChatbotConfig::LOG_RETENTION_DAYS;
    
    // === Proactive Engagement ===
    public const PROACTIVE_MIN_DELAY = ChatbotConfig::PROACTIVE_MIN_DELAY;
    public const PROACTIVE_MAX_DELAY = ChatbotConfig::PROACTIVE_MAX_DELAY;
    public const PROACTIVE_DEFAULT_DELAY = ChatbotConfig::PROACTIVE_DEFAULT_DELAY;
    
    // === Valid Providers ===
    public const VALID_PROVIDERS = ChatbotConfig::VALID_PROVIDERS;
    
    // === Chat Roles ===
    public const ROLE_USER = ChatbotConfig::ROLE_USER;
    public const ROLE_ASSISTANT = ChatbotConfig::ROLE_ASSISTANT;
    public const ROLE_SYSTEM = ChatbotConfig::ROLE_SYSTEM;
    
    // === Response Types ===
    public const TYPE_TEXT = ChatbotConfig::TYPE_TEXT;
    public const TYPE_PRODUCTS = ChatbotConfig::TYPE_PRODUCTS;
    public const TYPE_CART = ChatbotConfig::TYPE_CART;
    public const TYPE_ERROR = ChatbotConfig::TYPE_ERROR;
    
    // === Session Status ===
    public const STATUS_ACTIVE = ChatbotConfig::STATUS_ACTIVE;
    public const STATUS_ARCHIVED = ChatbotConfig::STATUS_ARCHIVED;
    public const STATUS_DELETED = ChatbotConfig::STATUS_DELETED;
    
    // Prevent instantiation
    private function __construct() {}
}
