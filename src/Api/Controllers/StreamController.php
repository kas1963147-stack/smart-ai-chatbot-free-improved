<?php
declare(strict_types=1);
/**
 * Stream Controller
 * 
 * Handles Server-Sent Events (SSE) for streaming AI responses.
 * Refactored to use the central NeuronAgent infrastructure to ensure tool execution.
 * 
 * @package SWC\API
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

use Quarksol\SmartChatbot\Config\ChatbotConfig;
use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Services\ConversationService;
use Quarksol\SmartChatbot\Services\Logger;
use Quarksol\SmartChatbot\Services\MessageRouter;
use Quarksol\SmartChatbot\Services\RateLimiter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stream Controller
 */
class StreamController {
    
    /** API namespace */
    const NAMESPACE = 'smart-ai-chatbot/v1';
    
    /**
     * Register REST routes
     */
    public static function register(): void {
        register_rest_route(self::NAMESPACE, '/chat/stream', [
            'methods' => 'POST',
            'callback' => [self::class, 'stream'],
            'permission_callback' => [self::class, 'canStreamRequest'],
        ]);
        
        // Synchronous chat endpoint for testing agents
        register_rest_route(self::NAMESPACE, '/chat/message', [
            'methods' => 'POST',
            'callback' => [self::class, 'sendMessage'],
            'permission_callback' => [self::class, 'canAccessAdminChat'],
        ]);
    }
    
    /**
     * Permission callback for admin chat (agent testing)
     */
    public static function canAccessAdminChat(\WP_REST_Request $request): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Permission callback for streaming
     * 
     * IMPORTANT: Rate limiting is NOT done here because when permission_callback
     * returns false, WordPress returns a generic 403 Forbidden instead of a
     * proper 429 with retry-after information. Rate limiting is handled inside
     * the stream() method where we can return meaningful error responses.
     */
    public static function canStreamRequest(\WP_REST_Request $request): bool {
        return true; // Public endpoint — rate limiting done inside stream()
    }
    
    /**
     * Synchronous message endpoint for agent testing
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function sendMessage(\WP_REST_Request $request): \WP_REST_Response {

    $params = $request->get_json_params();
        $message = sanitize_textarea_field($params['message'] ?? '');
        $agentSlug = sanitize_text_field($params['agent_id'] ?? 'default');
        $sessionId = sanitize_text_field($params['session_id'] ?? '');
        
        // Extract and sanitize conversation history
        $history = [];
        if (isset($params['history']) && is_array($params['history'])) {
            foreach ($params['history'] as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $history[] = [
                        'role' => sanitize_text_field($msg['role']),
                        'content' => sanitize_textarea_field($msg['content']),
                    ];
                }
            }
        }
        
        if (empty($message)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Message is required',
            ], 400);
        }
        
        try {
            // Resolve agent - can be slug or numeric ID
            $agentId = 0;
            if (is_numeric($agentSlug)) {
                $agentId = (int) $agentSlug;
            } elseif ($agentSlug !== 'default') {
                $chatAgent = ChatAgent::findBySlug($agentSlug);
                if ($chatAgent) {
                    $agentId = $chatAgent->id;
                }
            }
            
            // STRICT: No fallback to default agent
            if ($agentId === 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'This chat is not available right now. Please contact the site administrator.',
                ], 400);
            }
            
            // Use MessageRouter for AI response with conversation history
            if (class_exists('\Quarksol\SmartChatbot\Services\MessageRouter')) {
                $response = MessageRouter::routeDirectToAI($message, '', $agentId, $sessionId, $history);
                
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'message' => $response['message'] ?? '',
                        'tool_calls' => $response['tool_calls'] ?? [],
                    ],
                ], 200);
            }
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'MessageRouter service not available',
            ], 500);
            
        } catch (\Throwable $e) {
            Logger::error('sendMessage error', [
                'error' => $e->getMessage(),
                'agent_id' => $agentSlug,
            ]);
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Stream chat response using SSE with real LLM streaming
     * 
     * Uses NeuronAI Agent::stream() to forward text chunks from the LLM
     * directly to the client as SSE events. Tool executions emit separate
     * events so the frontend can display tool cards in real-time.
     */
    public static function stream(\WP_REST_Request $request): void {
        $params = $request->get_json_params();
        $message = sanitize_textarea_field($params['message'] ?? '');
        $sessionId = sanitize_text_field($params['session_id'] ?? '');
        $agentId = (int) ($params['agent_id'] ?? 0);
        $context = sanitize_text_field($params['context'] ?? '');
        $visitorId = sanitize_text_field($params['visitor_id'] ?? '');
        $pageUrl = esc_url_raw($params['url'] ?? '');
        $postId = (int) ($params['post_id'] ?? 0);


        // Resolve page context for the current page
        if ($postId > 0 && class_exists('\Quarksol\SmartChatbot\Services\PageContextResolver')) {
            try {
                $pageContext = \Quarksol\SmartChatbot\Services\PageContextResolver::resolve($postId);
                if ($pageContext) {
                    \Quarksol\SmartChatbot\Services\PageContextResolver::setCurrentContext($pageContext);
                }
            } catch (\Throwable $e) {
                Logger::debug('PageContextResolver failed', ['post_id' => $postId, 'error' => $e->getMessage()]);
            }
        }
        
        // Validate required fields
        if (empty($message)) {
            self::sendError('Message is required');
            return;
        }
        
        // Rate limiting
        $identifier = RateLimiter::getIdentifier($request);
        if (!RateLimiter::check('chat', $identifier)) {
            self::sendError("You're sending messages too quickly. Please wait a moment and try again.", 429);
            return;
        }
        
        // Set SSE headers immediately
        self::setStreamHeaders();
        
        try {
            // Auto-resolve agent when not specified by the frontend
            if ($agentId === 0) {
                $agentId = self::resolveAgentForPage($pageUrl);
            }

            // STRICT: If no agent was resolved, send error — do not use fallback defaults
            if ($agentId === 0) {
                self::sendEvent('error', [
                    'error' => true,
                    'message' => 'This chat is not available right now. Please contact the site administrator.',
                ]);
                self::sendEvent('done', ['finished' => true]);
                exit;
            }

            // --- Session & History Management (from ConversationService logic) ---
            $session = null;
            $history = [];
            $assistantMessageIndex = null;
            $assistantMessageId = '';
            $historyLimit = defined('\Quarksol\SmartChatbot\Config\ChatbotConfig::MAX_HISTORY_MESSAGES')
                ? ChatbotConfig::MAX_HISTORY_MESSAGES
                : 10;

            if ($sessionId !== '') {
                $session = \Quarksol\SmartChatbot\Models\ChatSession::findBySessionId($sessionId);
            }

            if (!$session) {
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
                        'ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                    ];

                    $timezone = sanitize_text_field((string) ($params['timezone'] ?? $request->get_param('timezone') ?? ''));
                    if (!empty($timezone)) {
                        $metadata['timezone'] = $timezone;
                        $parts = explode('/', $timezone);
                        if (count($parts) >= 2) {
                            $city = str_replace('_', ' ', end($parts));
                            $metadata['location'] = $city;
                        } else {
                            $metadata['location'] = $timezone;
                        }
                    }
                    $userId = is_user_logged_in() ? get_current_user_id() : null;
                    $session = \Quarksol\SmartChatbot\Models\ChatSession::findOrCreate($agentDbId, $userId, $visitorId, $metadata);
                    $sessionId = $session->sessionId;
                    $agentId = $agentDbId;
                }
            }

            if ($session) {
                if (!$agentId) {
                    $agentId = $session->agentDbId;
                }
                $history = $session->getMessages();
                if (count($history) > $historyLimit) {
                    $history = array_slice($history, -$historyLimit);
                }

                // Save user message
                $userMessageId = 'msg_' . wp_generate_uuid4();
                $assistantMessageId = 'msg_' . wp_generate_uuid4();
                $session->addMessage('user', $message, ['id' => $userMessageId]);
                $session->save();
                $assistantMessageIndex = count($session->getMessages());
            }

            // Send session ID to frontend
            if ($sessionId) {
                self::sendEvent('session', ['session_id' => $sessionId]);
            }

            // --- Try Real LLM Streaming via NeuronAgent::stream() ---
            $streamed = self::streamFromAgent($message, $agentId, $sessionId, $history, $session, $assistantMessageId);
            
            if ($streamed === false) {
                // Fallback: use ConversationService for full response, then simulate streaming
                Logger::debug('Falling back to simulated streaming');
                $service = new ConversationService();
                $result = $service->processMessage($message, $context, $agentId, $sessionId, [
                    'visitor_id' => $visitorId,
                    'page_url' => $pageUrl,
                    'save_messages' => false, // We already saved user message above
                ]);

                $responseText = $result['response_text'];
                
                // Simulate streaming by splitting into words
                $words = preg_split('/(\s+)/', $responseText, -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($words as $word) {
                    if (connection_aborted()) {
                        // Connection lost — save whatever we had so far
                        if ($session) {
                            $extra = $assistantMessageId !== '' ? ['id' => $assistantMessageId] : [];
                            $session->addMessage('assistant', $responseText, $extra);
                            $session->save();
                        }
                        return;
                    }
                    self::sendEvent('chunk', ['text' => $word]);
                    usleep(15000); // 15ms
                }

                // Save assistant message
                if ($session) {
                    $extra = $assistantMessageId !== '' ? ['id' => $assistantMessageId] : [];
                    $session->addMessage('assistant', $responseText, $extra);
                    $session->save();
                }

                // Send final response
                self::sendEvent('final', [
                    'response' => $result['response'],
                    'streamed' => false,
                ]);
            } else {
                // Real streaming succeeded — save assistant message from streamed content
                // Skip if connection was aborted (partial response already saved in streamFromAgent)
                if (!connection_aborted() && $session && is_string($streamed) && $streamed !== '') {
                    $extra = $assistantMessageId !== '' ? ['id' => $assistantMessageId] : [];
                    $session->addMessage('assistant', $streamed, $extra);
                    $session->save();
                }

                // Send final event with structured response (only if client is still connected)
                if (!connection_aborted()) {
                    self::sendEvent('final', [
                        'response' => [
                            'type' => 'text',
                            'message' => $streamed,
                            'session_id' => $sessionId,
                        ],
                        'streamed' => true,
                    ]);
                }
            }
            
        } catch (\Throwable $e) {
            Logger::error('Stream execution error: ' . $e->getMessage(), [
                'agent_id' => $agentId,
                'session_id' => $sessionId,
                'trace' => $e->getTraceAsString()
            ]);
            
            self::sendEvent('error', [
                'error' => true,
                'message' => 'Sorry, I encountered an error. Please try again.',
            ]);
        }
        
        // Send done event
        self::sendEvent('done', ['finished' => true]);
        
        exit;
    }

    /**
     * Stream response from NeuronAgent using real LLM streaming.
     * 
     * Returns the full accumulated text on success, or false if streaming
     * is not available (caller should fall back to simulated streaming).
     * 
     * @param string $message The user message
     * @param int $agentId The agent database ID
     * @param string $sessionId The session ID
     * @param array $history Conversation history
     * @param object|null $session The chat session for saving partial responses on abort
     * @param string $assistantMessageId The pre-generated ID for the assistant message
     * @return string|false Full response text, or false if streaming unavailable
     */
    protected static function streamFromAgent(
        string $message,
        int $agentId,
        string $sessionId,
        array $history,
        $session = null,
        string $assistantMessageId = ''
    ): string|false {
        // Ensure NeuronAgent is available
        if (!class_exists('\Quarksol\SmartChatbot\Agent\\NeuronAgent')) {
            return false;
        }

        // Allow PHP to detect connection aborts
        ignore_user_abort(true);

        try {
            // Resolve the NeuronAgent (same logic as MessageRouter::resolveAgent)
            $agent = null;
            
            if ($agentId > 0) {
                $chatAgent = ChatAgent::find($agentId);
                if ($chatAgent && $chatAgent->agentId) {
                    error_log("[STREAM_RESOLVE] DB agent found: id={$chatAgent->id}, slug='{$chatAgent->agentId}', name='{$chatAgent->name}'");
                    error_log("[STREAM_RESOLVE] Agent skills: " . implode(', ', $chatAgent->config->enabledSkills ?? []));
                    error_log("[STREAM_RESOLVE] Agent skill_mode: " . ($chatAgent->config->skillMode ?? 'n/a'));
                    $agent = \Quarksol\SmartChatbot\Agent\NeuronAgent::withConfigId($chatAgent->agentId);
                } else {
                    error_log("[STREAM_RESOLVE] Agent NOT FOUND for DB id={$agentId}");
                }
            }

            // STRICT: No fallback to default agent
            if (!$agent) {
                error_log("[STREAM_RESOLVE] No agent could be resolved — strict mode, no fallbacks");
                return false;
            }

            // Set agent context
            if (class_exists('\Quarksol\SmartChatbot\Services\AgentContext')) {
                \Quarksol\SmartChatbot\Services\AgentContext::set($agent->getConfig(), null, $agent->getConfig()?->agentId ?? '', $sessionId);
            }

            // Load conversation history into agent (exclude current message)
            foreach ($history as $msg) {
                if (!isset($msg['role'], $msg['content'])) continue;
                
                if ($msg['role'] === 'user') {
                    $agent->addToChatHistory(
                        new \NeuronAI\Chat\Messages\UserMessage($msg['content'])
                    );
                } elseif ($msg['role'] === 'assistant') {
                    $agent->addToChatHistory(
                        new \NeuronAI\Chat\Messages\AssistantMessage($msg['content'])
                    );
                }
            }

            // Set history context for observer
            if (method_exists($agent, 'setHistoryContext')) {
                $agent->setHistoryContext($sessionId, null, $agentId);
            }

            // Stream from the agent
            $userMessage = new \NeuronAI\Chat\Messages\UserMessage($message);
            
            error_log("[STREAM_DEBUG] About to call agent->stream() for agent: " . ($chatAgent->agentId ?? 'unknown'));
            error_log("[STREAM_DEBUG] Agent tools count: " . count($agent->getTools() ?? []));
            error_log("[STREAM_DEBUG] Agent model: " . ($agent->getConfig()->model ?? 'global-default'));
            
            try {
                $stream = $agent->stream($userMessage)->events();
            } catch (\Throwable $streamErr) {
                error_log("[STREAM_DEBUG] stream() FAILED: " . $streamErr->getMessage());
                error_log("[STREAM_DEBUG] stream() trace: " . $streamErr->getTraceAsString());
                throw $streamErr;
            }

            $fullContent = '';
            $toolCallsSent = [];
            $connectionAborted = false;

            foreach ($stream as $chunk) {
                // Check if client disconnected (user clicked Stop)
                if (connection_aborted()) {
                    $connectionAborted = true;
                    Logger::debug('Client disconnected during streaming', [
                        'session_id' => $sessionId,
                        'partial_length' => strlen($fullContent),
                    ]);
                    break;
                }

                // Handle tool call messages (yielded during agentic loop)
                if ($chunk instanceof \NeuronAI\Chat\Messages\ToolCallMessage) {
                    // Emit tool execution events for each tool call
                    $toolCalls = method_exists($chunk, 'getTools') ? $chunk->getTools() : [];
                    foreach ($toolCalls as $tc) {
                        $toolName = is_object($tc) && method_exists($tc, 'getName') 
                            ? $tc->getName() 
                            : ($tc['function']['name'] ?? 'tool');
                        $toolArgs = is_object($tc) && method_exists($tc, 'getInputs')
                            ? $tc->getInputs()
                            : ($tc['function']['arguments'] ?? '{}');
                        
                        self::sendEvent('tool_start', [
                            'name' => $toolName,
                            'arguments' => is_string($toolArgs) ? $toolArgs : json_encode($toolArgs),
                        ]);
                        $toolCallsSent[] = $toolName;
                    }
                    continue;
                }

                // Handle tool result messages
                if ($chunk instanceof \NeuronAI\Chat\Messages\ToolResultMessage) {
                    $toolName = array_pop($toolCallsSent) ?? 'tool';
                    self::sendEvent('tool_result', [
                        'name' => $toolName,
                        'status' => 'completed',
                    ]);
                    continue;
                }

                // Skip usage JSON chunks
                $decoded = @json_decode((string)$chunk, true);
                if (is_array($decoded) && isset($decoded['usage'])) {
                    continue;
                }

                // Text content chunk — stream to client
                $text = (string)$chunk;
                if ($text !== '') {
                    $fullContent .= $text;
                    self::sendEvent('chunk', ['text' => $text]);
                    
                    // Flush immediately for real-time delivery
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            }

            // If connection was aborted, save whatever partial response we have
            // so the session history stays consistent (no unanswered user message)
            if ($connectionAborted && $session) {
                $partialResponse = $fullContent !== '' 
                    ? $fullContent . ' [response stopped]' 
                    : '[Response was stopped by user]';
                $extra = $assistantMessageId !== '' ? ['id' => $assistantMessageId] : [];
                $session->addMessage('assistant', $partialResponse, $extra);
                $session->save();
                Logger::debug('Saved partial response after client abort', [
                    'session_id' => $sessionId,
                    'partial_length' => strlen($partialResponse),
                ]);
                return $partialResponse; // Return so caller doesn't save again
            }

            return $fullContent;

        } catch (\RuntimeException $e) {
            // Provider/config errors — surface directly to the user, do NOT fall back
            Logger::error('Agent configuration error (strict mode)', [
                'error' => $e->getMessage(),
                'agent_id' => $agentId,
            ]);
            self::sendEvent('error', [
                'error' => true,
                'message' => $e->getMessage(),
            ]);
            self::sendEvent('done', ['finished' => true]);
            exit;
        } catch (\Throwable $e) {
            // On any other error, ensure partial content is saved if we have a session
            if ($session && isset($fullContent) && $fullContent !== '') {
                $extra = $assistantMessageId !== '' ? ['id' => $assistantMessageId] : [];
                $session->addMessage('assistant', $fullContent . ' [error during response]', $extra);
                $session->save();
            }

            Logger::error('Real streaming failed', [
                'error' => $e->getMessage(),
                'agent_id' => $agentId,
            ]);
            return false;
        }
    }

    /**
     * Resolve the correct agent for a given page URL.
     * 
     * Uses the same AgentResolver logic as the /resolve endpoint,
     * so agents assigned to "All Pages (Global)" or specific pages
     * are automatically used — even if the frontend doesn't send agent_id.
     *
     * @param string $pageUrl The URL of the page where the chat is happening
     * @return int The agent's database ID (0 if no agent found)
     */
    private static function resolveAgentForPage(string $pageUrl): int {
        try {
            // Try AgentResolver first (respects page assignments)
            if (class_exists('\Quarksol\SmartChatbot\Services\AgentResolver') && class_exists('\Quarksol\SmartChatbot\Services\PageContext')) {
                $context = \Quarksol\SmartChatbot\Services\PageContext::fromArray([
                    'url' => $pageUrl,
                ]);
                
                $resolver = new \Quarksol\SmartChatbot\Services\AgentResolver();
                $agents = $resolver->getAgentsForContext($context);
                
                if (!empty($agents)) {
                    $agent = $agents[0]; // Highest priority agent
                    error_log("[StreamController] AgentResolver found agent: {$agent->name} (ID: {$agent->id})");
                    return $agent->id;
                }
            }
        } catch (\Throwable $e) {
            error_log("[StreamController] AgentResolver failed: " . $e->getMessage());
        }
        
        // STRICT: No fallback to default agent — widget must have an agent assigned
        error_log("[StreamController] No agent resolved for page: {$pageUrl} — no fallback used");
        return 0;
    }

    /**
     * Check if the current provider supports streaming.
     *
     * @param array $settings
     * @return bool
     */
    public static function canStream(array $settings): bool {
        $provider = strtolower((string) ($settings['ai_provider'] ?? ''));
        $streamingProviders = ['openai', 'anthropic'];

        if (in_array($provider, $streamingProviders, true)) {
            return true;
        }

        return !empty($settings['enable_streaming']) || !empty($settings['streaming_enabled']);
    }

    /**
     * Build a message array for AI providers.
     *
     * @param string $message
     * @param array  $history
     * @param array  $settings
     * @return array
     */
    protected static function buildMessages(string $message, array $history = [], array $settings = []): array {
        $systemPrompt = $settings['system_prompt'] ?? '';
        $promptSections = $settings['prompt_sections'] ?? [];

        if (empty($systemPrompt) && is_array($promptSections)) {
            $systemPrompt = trim(implode("\n\n", array_filter($promptSections)));
        }

        $messages = [];

        if (!empty($systemPrompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        foreach ($history as $entry) {
            if (!isset($entry['role'], $entry['content'])) {
                continue;
            }
            $messages[] = [
                'role' => $entry['role'],
                'content' => $entry['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }

    /**
     * Central AI response handler for streaming flows.
     *
     * @param string $message
     * @param string $context
     * @param int    $agentId
     * @param string $sessionId
     * @param array  $history
     * @return array
     */
    protected static function streamAIResponse(
        string $message,
        string $context,
        int $agentId,
        string $sessionId,
        array $history
    ): array {
        if (!class_exists('\Quarksol\SmartChatbot\Services\MessageRouter')) {
            throw new \RuntimeException('MessageRouter service not found');
        }

        $directMode = defined('\Quarksol\SmartChatbot\Config\ChatbotConfig::DIRECT_AI_MODE') && ChatbotConfig::DIRECT_AI_MODE;

        if ($directMode) {
            return MessageRouter::routeDirectToAI($message, $context, $agentId, $sessionId, $history);
        }

        return MessageRouter::route($message, $context, $agentId, $sessionId, $history);
    }

    /**
     * Fetch AI settings from the SettingsManager or fallback option.
     *
     * @return array
     */
    protected static function getAISettings(): array {
        if (class_exists('\Quarksol\SmartChatbot\Config\SettingsManager')) {
            return \Quarksol\SmartChatbot\Config\SettingsManager::getSettings();
        }

        $settings = get_option('swc_chatbot_settings', []);
        return is_array($settings) ? $settings : [];
    }
    
    /**
     * Set SSE headers
     */
    protected static function setStreamHeaders(): void {
        // Disable PHP output buffering at all levels
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Disable implicit output buffering
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
        header('Content-Encoding: none'); // Disable mod_deflate
        
        // Send padding to force Apache to send the first chunk
        echo ": " . str_repeat(' ', 2048) . "\n\n";
        flush();
    }
    
    /**
     * Send SSE event with aggressive flushing
     */
    protected static function sendEvent(string $event, array $data): void {
        echo "event: {$event}\n";
        echo "data: " . wp_json_encode($data) . "\n\n";
        
        // Flush all output buffer levels
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Send error response
     */
    protected static function sendError(string $message, int $code = 400): void {
        wp_send_json_error(['message' => $message, 'code' => $code], $code);
    }
}
