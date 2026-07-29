<?php
declare(strict_types=1);
/**
 * Chat Controller
 * 
 * Handles chat AJAX requests and responses.
 * Replaces the monolithic handling in class-chatbot.php
 * 
 * @package Quarksol\SmartChatbot\Api\Controllers
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

use Quarksol\SmartChatbot\Services\RateLimiter;
use Quarksol\SmartChatbot\Services\ConversationService;
use Quarksol\SmartChatbot\Services\PageContextResolver;
use Quarksol\SmartChatbot\Services\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chat Controller
 * 
 * Handles chat AJAX requests and responses.
 * Uses IntentRouter for primary intent detection, with MessageRouter fallback for AI.
 */
class ChatController
{

    /**
     * Register AJAX actions
     */
    public static function register(): void
    {
        add_action('wp_ajax_swc_chat_message', [self::class, 'handleMessage']);
        add_action('wp_ajax_nopriv_swc_chat_message', [self::class, 'handleMessage']);
    }

    /**
     * Handle incoming chat message
     */
    public static function handleMessage(): void
    {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');

        // Rate limiting (now mandatory, not optional)
        if (class_exists(RateLimiter::class)) {
            $identifier = RateLimiter::getIdentifier();
            if (!RateLimiter::check('chat', $identifier)) {
                $retryAfter = RateLimiter::getRetryAfter('chat', $identifier);
                wp_send_json_error([
                    'message' => "You're sending messages too quickly. Please wait a moment and try again.",
                    'retry_after' => $retryAfter
                ], 429);
            }
        }

        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = sanitize_text_field($_POST['context'] ?? '');
        $agentId = intval($_POST['agent_id'] ?? 0);
        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        $visitorId = sanitize_text_field($_POST['visitor_id'] ?? '');
        $pageUrl = esc_url_raw($_POST['url'] ?? '');
        $postId = intval($_POST['post_id'] ?? 0);

        // Resolve page context for the current page (if a post_id was provided)
        if ($postId > 0) {
            try {
                $pageContext = PageContextResolver::resolve($postId);
                if ($pageContext) {
                    PageContextResolver::setCurrentContext($pageContext);
                    Logger::debug('Page context resolved', [
                        'post_id' => $postId,
                        'context_length' => strlen($pageContext),
                    ]);
                }
            } catch (\Throwable $e) {
                Logger::error('PageContextResolver failed', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                ]);
                // Continue without page context — chat still works
            }
        }

        if (empty($message)) {
            wp_send_json_error('Empty message');
        }

        // Validate message length
        if (strlen($message) > 4000) {
            wp_send_json_error(['message' => 'Message too long. Maximum 4000 characters.']);
        }

        // Validate session ID format if provided
        if (!empty($sessionId) && !preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $sessionId)) {
            wp_send_json_error(['message' => 'Invalid session ID format']);
        }

        // Validate agent exists if specified
        if ($agentId > 0 && class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
            $agent = \Quarksol\SmartChatbot\Models\ChatAgent::find($agentId);
            if (!$agent) {
                wp_send_json_error(['message' => 'Invalid agent specified']);
            }
        }

        // ==========================================
        // Hook: swc/chat/message_received (action)
        // Fired when a chat message is received
        // ==========================================
        \Quarksol\SmartChatbot\Hooks\Hooks::action('swc/chat/message_received', $message, $sessionId, $agentId, $pageUrl);

        Logger::debug('Processing chat message', [
            'message_length' => strlen($message),
            'agent_id' => $agentId,
            'has_context' => !empty($context),
        ]);

        try {
            $service = new ConversationService();
            $result = $service->processMessage($message, $context, $agentId, $sessionId, [
                'visitor_id' => $visitorId,
                'page_url' => $pageUrl,
            ]);
            $response = $result['response'];

            // ==========================================
            // Hook: swc/chat/response (filter)
            // Modify the chat response before sending
            // ==========================================
            $response = \Quarksol\SmartChatbot\Hooks\Hooks::filter('swc/chat/response', $response, $message, $sessionId, $agentId);

            // Trigger Conversation Learning (learn from successful Q&A)
            if (!empty($response['message']) && $response['type'] === 'text') {
                try {
                    if (class_exists('\Quarksol\SmartChatbot\Knowledge\\ConversationLearner')) {
                        $learner = new \Quarksol\SmartChatbot\Knowledge\ConversationLearner();
                        $learner->extractFAQ([
                            ['role' => 'user', 'content' => $message],
                            ['role' => 'assistant', 'content' => $response['message']]
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Silent fail - learning is optional
                }
            }

            wp_send_json_success($response);

        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            wp_send_json_error([
                'message' => 'An unexpected error occurred.',
                'debug' => defined('WP_DEBUG') && \WP_DEBUG ? $e->getMessage() : null
            ]);
        }
    }
}
