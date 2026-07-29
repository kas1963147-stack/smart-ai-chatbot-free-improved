<?php
declare(strict_types=1);
/**
 * Message Handler Interface
 * 
 * @package Quarksol\SmartChatbot\Contracts
 */

namespace Quarksol\SmartChatbot\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for message handlers
 */
interface MessageHandlerInterface {
    /**
     * Handle a message
     * 
     * @param string $message User message
     * @param array $context Additional context
     * @return array Response
     */
    public function handle(string $message, array $context = []): array;
    
    /**
     * Check if handler can process message
     * 
     * @param string $message User message
     * @return bool True if handler can process
     */
    public function canHandle(string $message): bool;
}
