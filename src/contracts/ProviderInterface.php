<?php
declare(strict_types=1);
/**
 * Provider Interface
 * 
 * @package Quarksol\SmartChatbot\Contracts
 */

namespace Quarksol\SmartChatbot\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for AI providers
 */
interface ProviderInterface {
    /**
     * Send a message and get response
     * 
     * @param string $message User message
     * @param array $history Conversation history
     * @return string AI response
     */
    public function chat(string $message, array $history = []): string;
    
    /**
     * Get provider name
     * 
     * @return string Provider identifier
     */
    public function getName(): string;
    
    /**
     * Check if provider is available
     * 
     * @return bool True if properly configured
     */
    public function isAvailable(): bool;
    
    /**
     * Get estimated token count
     * 
     * @param string $text Text to count
     * @return int Estimated tokens
     */
    public function estimateTokens(string $text): int;
}
