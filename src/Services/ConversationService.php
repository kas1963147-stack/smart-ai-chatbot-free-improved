<?php
declare(strict_types=1);
/**
 * Conversation Service
 *
 * Centralizes session lifecycle, history loading, routing, and persistence.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Models\ChatSession;
use Quarksol\SmartChatbot\Config\ChatbotConfig;

if (!defined('ABSPATH')) {
    exit;
}

class ConversationService
{
    /**
     * Process a single user message through the unified chat pipeline.
     *
     * @return array{
     *   response: array,
     *   session: ?ChatSession,
     *   session_id: string,
     *   response_text: string,
     *   assistant_message_index: ?int
     * }
     */
    public function processMessage(
        string $message,
        string $context = '',
        int $agentId = 0,
        string $sessionId = '',
        array $options = []
    ): array {
        $visitorId = sanitize_text_field((string) ($options['visitor_id'] ?? ''));
        $pageUrl = esc_url_raw((string) ($options['page_url'] ?? ''));
        $saveMessages = isset($options['save_messages']) ? (bool) $options['save_messages'] : true;
        $historyLimit = isset($options['history_limit'])
            ? (int) $options['history_limit']
            : (defined('\Quarksol\SmartChatbot\Config\ChatbotConfig::MAX_HISTORY_MESSAGES') ? ChatbotConfig::MAX_HISTORY_MESSAGES : 10);
        $agentHistoryLimit = null;

        $session = null;
        $history = [];
        $assistantMessageIndex = null;
        $assistantMessageId = '';

        if ($sessionId !== '') {
            $session = ChatSession::findBySessionId($sessionId);
        }

        if (!$session && $saveMessages) {
            $agentDbId = $agentId;
            if (!$agentDbId) {
                $defaultAgent = ChatAgent::getDefault();
                if ($defaultAgent) {
                    $agentDbId = $defaultAgent->id;
                }
            }

            if ($agentDbId) {
                $metadata = [
                    'url' => $pageUrl,
                    'user_agent' => class_exists('\Quarksol\SmartChatbot\Services\ServerInput')
                        ? \Quarksol\SmartChatbot\Services\ServerInput::getUserAgent()
                        : substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
                ];
                $userId = is_user_logged_in() ? get_current_user_id() : null;
                $session = ChatSession::findOrCreate($agentDbId, $userId, $visitorId, $metadata);
                $sessionId = $session->sessionId;
                $agentId = $agentDbId;
            }
        }

        if ($session) {
            if (!$agentId) {
                $agentId = $session->agentDbId;
            }
            $history = $session->getMessages();
            if ($agentId > 0 && class_exists(ChatAgent::class)) {
                $agent = ChatAgent::find($agentId);
                if ($agent && $agent->config) {
                    $agentHistoryLimit = (int) $agent->config->maxHistoryLength;
                }
            }
            if ($agentHistoryLimit !== null) {
                $historyLimit = max(0, min($agentHistoryLimit, ChatSession::MAX_MESSAGES));
            } else {
                $historyLimit = max(0, min($historyLimit, ChatSession::MAX_MESSAGES));
            }
            if (count($history) > $historyLimit) {
                $history = array_slice($history, -$historyLimit);
            }

            if ($saveMessages) {
                $userMessageId = 'msg_' . wp_generate_uuid4();
                $assistantMessageId = 'msg_' . wp_generate_uuid4();
                $session->addMessage('user', $message, ['id' => $userMessageId]);
                $session->save();
                $assistantMessageIndex = count($session->getMessages());
            }
        }

        if (ChatbotConfig::DIRECT_AI_MODE) {
            error_log('[SWC Debug] ConversationService: Routing via DIRECT_AI_MODE');
            $response = MessageRouter::routeDirectToAI(
                $message,
                $context,
                $agentId,
                $sessionId,
                $history,
                $assistantMessageIndex,
                $assistantMessageId
            );
        } elseif (ChatbotConfig::USE_NEW_ROUTER && class_exists(IntentRouter::class)) {
            error_log('[SWC Debug] ConversationService: Routing via IntentRouter');
            $router = new IntentRouter();
            $response = $router->route(
                $message,
                $context,
                $agentId,
                $sessionId,
                $history,
                $assistantMessageIndex,
                $assistantMessageId
            );
        } elseif (class_exists(MessageRouter::class)) {
            error_log('[SWC Debug] ConversationService: Routing via MessageRouter (fallback path)');
            $response = MessageRouter::route(
                $message,
                $context,
                $agentId,
                $sessionId,
                $history,
                $assistantMessageIndex,
                $assistantMessageId
            );
        } else {
            error_log('[SWC Debug] ConversationService: No router found!');
            $response = ['type' => 'text', 'message' => 'System migration in progress.'];
        }

        $responseText = $response['message'] ?? '';
        if ($responseText === '') {
            $responseText = "I processed your request, but didn't have a specific response. If you asked me to do something, please check if it was done.";
        }

        if (empty($response['message'])) {
            $response['message'] = $responseText;
        }

        if ($session && $saveMessages) {
            $extra = $assistantMessageId !== '' ? ['id' => $assistantMessageId] : [];
            $session->addMessage('assistant', $responseText, $extra);
            $session->save();
        }

        if ($sessionId !== '') {
            $response['session_id'] = $sessionId;
        }

        return [
            'response' => $response,
            'session' => $session,
            'session_id' => $sessionId,
            'response_text' => $responseText,
            'assistant_message_index' => $assistantMessageIndex,
        ];
    }
}
