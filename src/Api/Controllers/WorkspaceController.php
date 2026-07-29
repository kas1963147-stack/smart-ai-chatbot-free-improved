<?php
declare(strict_types=1);
/**
 * Workspace Controller
 * 
 * REST API controller for admin AI workspace operations.
 * Provides a code-editor-like interface for site management via AI.
 * 
 * @package Quarksol\SmartChatbot\Api\Controllers
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

use Quarksol\SmartChatbot\Services\WorkspaceService;
use Quarksol\SmartChatbot\Services\Logger;
use Quarksol\SmartChatbot\Services\RateLimiter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Workspace REST API Controller
 * 
 * Admin-only endpoints for AI-powered site management workspace.
 */
class WorkspaceController
{

    private const API_NAMESPACE = 'smart-ai-chatbot/v1';

    /**
     * Register REST routes
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    /**
     * Register the workspace routes
     */
    public static function registerRoutes(): void
    {
        // POST /workspace/message - Send message to workspace AI
        register_rest_route(self::API_NAMESPACE, '/workspace/message', [
            'methods' => 'POST',
            'callback' => [self::class, 'sendMessage'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => function ($param) {
                        return !empty($param) && strlen($param) <= 10000;
                    },
                ],
                'conversation_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'agent_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                ],
            ],
        ]);

        // POST /workspace/stream - Stream message to workspace AI via SSE
        register_rest_route(self::API_NAMESPACE, '/workspace/stream', [
            'methods' => 'POST',
            'callback' => [self::class, 'streamMessage'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
        ]);

        // GET /workspace/available-agents - List agents for dropdown
        register_rest_route(self::API_NAMESPACE, '/workspace/available-agents', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAvailableAgents'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
        ]);

        // GET /workspace/conversations - List conversation history
        register_rest_route(self::API_NAMESPACE, '/workspace/conversations', [
            'methods' => 'GET',
            'callback' => [self::class, 'listConversations'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
        ]);

        // GET /workspace/conversations/{id} - Get single conversation
        register_rest_route(self::API_NAMESPACE, '/workspace/conversations/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'getConversation'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
        ]);

        // DELETE /workspace/conversations/{id} - Delete conversation
        register_rest_route(self::API_NAMESPACE, '/workspace/conversations/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'deleteConversation'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
        ]);

        // POST /workspace/conversations - Start new conversation
        register_rest_route(self::API_NAMESPACE, '/workspace/conversations', [
            'methods' => 'POST',
            'callback' => [self::class, 'createConversation'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
            'args' => [
                'title' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                ],
            ],
        ]);

        // POST /workspace/upload - Upload file attachment
        register_rest_route(self::API_NAMESPACE, '/workspace/upload', [
            'methods' => 'POST',
            'callback' => [self::class, 'uploadFile'],
            'permission_callback' => [self::class, 'canAccessWorkspace'],
        ]);
    }

    /**
     * Check if user can access workspace (admin only)
     */
    public static function canAccessWorkspace(\WP_REST_Request $request): bool
    {
        $isLoggedIn = is_user_logged_in();
        $canManage = current_user_can('manage_options');
        $userId = get_current_user_id();
        
        Logger::debug('Workspace access check', [
            'is_logged_in' => $isLoggedIn,
            'can_manage' => $canManage,
            'user_id' => $userId,
            'method' => $request->get_method(),
            'path' => $request->get_route(),
        ]);

        // Must be logged in and have manage_options capability
        if (!$isLoggedIn || !$canManage) {
            error_log('[SWC ERROR] Workspace access denied: logged_in=' . ($isLoggedIn?'Y':'N') . ', can_manage=' . ($canManage?'Y':'N') . ', user_id=' . $userId);
            return false;
        }

        // Rate limiting for workspace operations
        if (class_exists(RateLimiter::class)) {
            $identifier = RateLimiter::getIdentifier();
            if (!RateLimiter::check('workspace', $identifier)) {
                error_log('[SWC ERROR] Workspace rate limited');
                return false;
            }
        }

        return true;
    }

    /**
     * POST /workspace/message - Process admin workspace message
     */
    public static function sendMessage(\WP_REST_Request $request): \WP_REST_Response
    {

        $message = $request->get_param('message');
        $conversationId = $request->get_param('conversation_id');
        $agentId = $request->get_param('agent_id');

        // If no agent specified, use first available agent
        if (empty($agentId)) {
            $agents = \Quarksol\SmartChatbot\Models\ChatAgent::all(true);
            if (!empty($agents)) {
                $agentId = $agents[0]->agentId;
            } else {
                $agentId = 'none'; // Fallback to raw LLM if no agents configured
            }
        }

        Logger::info('Workspace message received', [
            'message_length' => strlen($message),
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'user_id' => get_current_user_id(),
        ]);

        try {
            $service = new WorkspaceService();
            $result = $service->processMessage($message, $conversationId, $agentId);

            $response = new \WP_REST_Response([
                'success' => true,
                'data' => $result,
            ], 200);
            $identifier = RateLimiter::getIdentifier($request);
            return RateLimiter::addHeaders($response, 'workspace', $identifier);

        } catch (\Throwable $e) {
            Logger::error('Workspace message failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Build debug info for development environments
            $debugInfo = null;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                    'previous' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
                ];
            }

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Failed to process message: ' . $e->getMessage(),
                'debug' => $debugInfo,
            ], 500);
        }
    }

    /**
     * POST /workspace/stream - Stream workspace message via SSE
     * 
     * Uses NeuronAI Agent::stream() for real-time text streaming.
     * Handles conversation management, then streams the agent response.
     */
    public static function streamMessage(\WP_REST_Request $request): void
    {
        $params = $request->get_json_params();
        $message = sanitize_textarea_field($params['message'] ?? '');
        $conversationId = sanitize_text_field($params['conversation_id'] ?? '');
        $agentId = sanitize_text_field($params['agent_id'] ?? '');


        // Validate
        if (empty($message)) {
            wp_send_json_error(['message' => 'Message is required'], 400);
            return;
        }

        if (strlen($message) > 10000) {
            wp_send_json_error(['message' => 'Message too long'], 400);
            return;
        }

        // If no agent specified, use first available agent
        if (empty($agentId)) {
            $agents = \Quarksol\SmartChatbot\Models\ChatAgent::all(true);
            if (!empty($agents)) {
                $agentId = $agents[0]->agentId;
            } else {
                $agentId = 'none';
            }
        }

        // Set SSE headers
        self::setStreamHeaders();

        try {
            $service = new WorkspaceService();

            // ── 1. Conversation management ─────────────────────────
            $conversation = null;
            if ($conversationId) {
                $conversation = $service->getConversation($conversationId);
            }
            if (!$conversation) {
                $conversation = $service->createConversation('Workspace Chat');
                $conversationId = $conversation['id'];
            }
            self::sendEvent('session', ['conversation_id' => $conversationId]);

            // Build history & save user message
            $messages = $conversation['messages'] ?? [];
            $messages[] = [
                'role' => 'user',
                'content' => $message,
                'timestamp' => current_time('mysql'),
            ];
            $maxHistory = 20;
            if (count($messages) > $maxHistory * 2) {
                $messages = array_slice($messages, -$maxHistory * 2);
            }
            $service->saveConversationMessages($conversationId, $messages);

            // ── 2. Try REAL LLM streaming ──────────────────────────
            $fullContent = '';
            $realStreamDone = false;

            // Extend PHP timeout for streaming (http_api can take time)
            @set_time_limit(120);

            if (class_exists('\Quarksol\SmartChatbot\Agent\\NeuronAgent') && $agentId !== 'none') {
                $isGroup = class_exists('\Quarksol\SmartChatbot\Models\\AgentGroup') && \Quarksol\SmartChatbot\Models\AgentGroup::findBySlug($agentId) !== null;
                
                if (!$isGroup) {
                    try {
                        Logger::debug('Workspace: attempting real LLM streaming', ['agent_id' => $agentId]);

                    // Resolve agent (same approach as StreamController::streamFromAgent)
                    $agent = \Quarksol\SmartChatbot\Agent\NeuronAgent::withConfigId($agentId);

                    if (class_exists('\Quarksol\SmartChatbot\Services\AgentContext')) {
                        \Quarksol\SmartChatbot\Services\AgentContext::set($agent->getConfig(), null, $agentId, $conversationId);
                    }

                    // Load conversation history (exclude current message)
                    $historyWithoutLast = array_slice($messages, 0, -1);
                    foreach ($historyWithoutLast as $msg) {
                        if (!isset($msg['role'], $msg['content'])) continue;
                        if ($msg['role'] === 'user') {
                            $agent->addToChatHistory(new \NeuronAI\Chat\Messages\UserMessage($msg['content']));
                        } elseif ($msg['role'] === 'assistant') {
                            $agent->addToChatHistory(new \NeuronAI\Chat\Messages\AssistantMessage($msg['content']));
                        }
                    }

                    // Set history context for observer
                    if (method_exists($agent, 'setHistoryContext')) {
                        $agentDbId = null;
                        if (class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
                            $agentModel = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
                            if ($agentModel) $agentDbId = $agentModel->id;
                        }
                        $agent->setHistoryContext($conversationId, null, $agentDbId);
                    }

                    // ── Real-time stream from NeuronAgent ──
                    $userMessage = new \NeuronAI\Chat\Messages\UserMessage($message);
                    $stream = $agent->stream($userMessage)->events();
                    $toolCallsSent = [];

                    foreach ($stream as $chunk) {
                        // Skip tool call messages silently (don't show in workspace UI)
                        if ($chunk instanceof \NeuronAI\Chat\Messages\ToolCallMessage) {
                            continue;
                        }

                        // Skip tool result messages silently
                        if ($chunk instanceof \NeuronAI\Chat\Messages\ToolCallResultMessage) {
                            continue;
                        }

                        // Skip usage/metadata
                        $decoded = @json_decode((string)$chunk, true);
                        if (is_array($decoded) && isset($decoded['usage'])) {
                            continue;
                        }

                        // Text chunk — forward to client immediately
                        $text = (string)$chunk;
                        if ($text !== '') {
                            $fullContent .= $text;
                            self::sendEvent('chunk', [
                                'text' => $text,
                                'ts' => round(microtime(true) * 1000), // Server timestamp in ms
                            ]);

                            if (ob_get_level()) {
                                ob_flush();
                            }
                            flush();
                        }
                    }

                    $realStreamDone = true;
                    Logger::debug('Workspace: real streaming completed', [
                        'content_length' => strlen($fullContent),
                    ]);

                } catch (\Throwable $streamErr) {
                    Logger::error('Workspace real streaming failed, falling back', [
                        'error' => $streamErr->getMessage(),
                        'file' => $streamErr->getFile(),
                        'line' => $streamErr->getLine(),
                        'agent_id' => $agentId,
                    ]);
                    $fullContent = '';
                }
                } // End if (!$isGroup)
            }

            // ── 3. Fallback: processMessage + simulated delivery ───
            if (!$realStreamDone) {
                Logger::debug('Workspace: using processMessage fallback');
                try {
                    if (isset($isGroup) && $isGroup) {
                        $group = \Quarksol\SmartChatbot\Models\AgentGroup::findBySlug($agentId);
                        $fullContent = \Quarksol\SmartChatbot\Services\SupervisorService::execute($group, $message, $conversationId);
                    } else {
                        $result = $service->executeWorkspaceAgent($message, $messages, $agentId, $conversationId);
                        $fullContent = $result['message'] ?? '';
                    }
                } catch (\Throwable $syncErr) {
                    Logger::error('Workspace sync fallback failed', [
                        'error' => $syncErr->getMessage(),
                    ]);
                    $fullContent = 'Sorry, I encountered an error: ' . $syncErr->getMessage();
                }

                // Simulate streaming by splitting into word batches
                if (!empty($fullContent)) {
                    $words = preg_split('/(\s+)/', $fullContent, -1, PREG_SPLIT_DELIM_CAPTURE);
                    $batch = '';
                    $wordCount = 0;
                    foreach ($words as $word) {
                        $batch .= $word;
                        if ($word !== '' && !ctype_space($word)) {
                            $wordCount++;
                        }
                        if ($wordCount >= 3) {
                            self::sendEvent('chunk', ['text' => $batch]);
                            $batch = '';
                            $wordCount = 0;
                            usleep(5000);
                        }
                    }
                    if ($batch !== '') {
                        self::sendEvent('chunk', ['text' => $batch]);
                    }
                }
            }

            // ── 4. Save assistant response ──────────────────────────
            if ($fullContent !== '') {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'timestamp' => current_time('mysql'),
                ];
                $service->saveConversationMessages($conversationId, $messages);
            }

            // Send final event
            self::sendEvent('final', [
                'conversation_id' => $conversationId,
                'message' => $fullContent,
                'tool_calls' => [],
            ]);

        } catch (\Throwable $e) {
            Logger::error('Workspace stream error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            self::sendEvent('error', [
                'error' => true,
                'message' => 'Sorry, I encountered an error: ' . $e->getMessage(),
            ]);
        }

        self::sendEvent('done', ['finished' => true]);
        exit;
    }

    /**
     * Stream workspace agent response using NeuronAgent::stream()
     * 
     * Uses real-time streaming from ProviderAdapter (http_api).
     * Returns full response text on success, false if streaming unavailable.
     */
    protected static function streamWorkspaceAgent(
        string $message,
        string &$conversationId,
        string $agentId,
        WorkspaceService $service
    ): string|false {
        if (!class_exists('\Quarksol\SmartChatbot\Agent\\NeuronAgent') || $agentId === 'none') {
            return false;
        }

        try {
            // Get or create conversation
            $conversation = null;
            if ($conversationId) {
                $conversation = $service->getConversation($conversationId);
            }
            if (!$conversation) {
                $conversation = $service->createConversation('Workspace Chat');
                $conversationId = $conversation['id'];
                self::sendEvent('session', ['conversation_id' => $conversationId]);
            }

            // Build history
            $messages = $conversation['messages'] ?? [];
            $messages[] = [
                'role' => 'user',
                'content' => $message,
                'timestamp' => current_time('mysql'),
            ];

            // Limit history
            $maxHistory = 20;
            if (count($messages) > $maxHistory * 2) {
                $messages = array_slice($messages, -$maxHistory * 2);
            }

            // Save user message immediately
            $service->saveConversationMessages($conversationId, $messages);

            // Create agent
            $agent = \Quarksol\SmartChatbot\Agent\NeuronAgent::withConfigId($agentId);

            if (class_exists('\Quarksol\SmartChatbot\Services\AgentContext')) {
                \Quarksol\SmartChatbot\Services\AgentContext::set($agent->getConfig(), null, $agentId, $conversationId);
            }

            // Load conversation history into agent (exclude current user msg)
            $historyWithoutLast = array_slice($messages, 0, -1);
            foreach ($historyWithoutLast as $msg) {
                if (!isset($msg['role'], $msg['content'])) continue;
                if ($msg['role'] === 'user') {
                    $agent->addToChatHistory(new \NeuronAI\Chat\Messages\UserMessage($msg['content']));
                } elseif ($msg['role'] === 'assistant') {
                    $agent->addToChatHistory(new \NeuronAI\Chat\Messages\AssistantMessage($msg['content']));
                }
            }

            // Set history context
            if (method_exists($agent, 'setHistoryContext')) {
                $agentDbId = null;
                if (class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
                    $agentModel = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
                    if ($agentModel) $agentDbId = $agentModel->id;
                }
                $agent->setHistoryContext($conversationId, null, $agentDbId);
            }

            // Stream from agent
            $userMessage = new \NeuronAI\Chat\Messages\UserMessage($message);
            $stream = $agent->stream($userMessage)->events();

            $fullContent = '';
            $toolCallsSent = [];

            foreach ($stream as $chunk) {
                // Tool call message
                if ($chunk instanceof \NeuronAI\Chat\Messages\ToolCallMessage) {
                    $tools = method_exists($chunk, 'getTools') ? $chunk->getTools() : [];
                    foreach ($tools as $tc) {
                        $toolName = method_exists($tc, 'getName') ? $tc->getName() : 'tool';
                        self::sendEvent('tool_start', ['name' => $toolName]);
                        $toolCallsSent[] = $toolName;
                    }
                    continue;
                }

                // Tool result message  
                if ($chunk instanceof \NeuronAI\Chat\Messages\ToolCallResultMessage) {
                    $toolName = array_pop($toolCallsSent) ?? 'tool';
                    self::sendEvent('tool_result', [
                        'name' => $toolName,
                        'status' => 'completed',
                    ]);
                    continue;
                }

                // Skip usage/metadata chunks
                $decoded = @json_decode((string)$chunk, true);
                if (is_array($decoded) && isset($decoded['usage'])) {
                    continue;
                }

                // Text chunk — send as SSE immediately
                $text = (string)$chunk;
                if ($text !== '') {
                    $fullContent .= $text;
                    self::sendEvent('chunk', ['text' => $text]);

                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            }

            // Save assistant response to conversation
            if ($fullContent !== '') {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'timestamp' => current_time('mysql'),
                ];
                $service->saveConversationMessages($conversationId, $messages);
            }

            return $fullContent;

        } catch (\Throwable $e) {
            Logger::error('Workspace real streaming failed', [
                'error' => $e->getMessage(),
                'agent_id' => $agentId,
            ]);
            return false;
        }
    }

    /**
     * Set SSE headers for streaming
     */
    protected static function setStreamHeaders(): void
    {
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
        header('X-Accel-Buffering: no'); // Nginx
        header('Content-Encoding: none'); // Disable mod_deflate

        // Send padding to force Apache to send the first chunk
        echo ": " . str_repeat(' ', 2048) . "\n\n";
        flush();
    }

    /**
     * Send an SSE event with aggressive flushing
     */
    protected static function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . wp_json_encode($data) . "\n\n";

        // Flush all output buffer levels
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * GET /workspace/conversations - List all conversations
     */
    public static function listConversations(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $service = new WorkspaceService();
            $conversations = $service->listConversations();

            return new \WP_REST_Response([
                'success' => true,
                'data' => $conversations,
            ], 200);

        } catch (\Throwable $e) {
            Logger::error('Failed to list conversations', ['error' => $e->getMessage()]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Failed to retrieve conversations',
            ], 500);
        }
    }

    /**
     * GET /workspace/conversations/{id} - Get single conversation
     */
    public static function getConversation(\WP_REST_Request $request): \WP_REST_Response
    {
        $conversationId = $request->get_param('id');

        try {
            $service = new WorkspaceService();
            $conversation = $service->getConversation($conversationId);

            if (!$conversation) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Conversation not found',
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $conversation,
            ], 200);

        } catch (\Throwable $e) {
            Logger::error('Failed to get conversation', ['error' => $e->getMessage()]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Failed to retrieve conversation',
            ], 500);
        }
    }

    /**
     * POST /workspace/conversations - Create new conversation
     */
    public static function createConversation(\WP_REST_Request $request): \WP_REST_Response
    {
        $title = $request->get_param('title');

        try {
            $service = new WorkspaceService();
            $conversation = $service->createConversation($title ?: 'New Conversation');

            return new \WP_REST_Response([
                'success' => true,
                'data' => $conversation,
            ], 201);

        } catch (\Throwable $e) {
            Logger::error('Failed to create conversation', ['error' => $e->getMessage()]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Failed to create conversation',
            ], 500);
        }
    }

    /**
     * GET /workspace/available-agents - List agents for selection dropdown
     */
    public static function getAvailableAgents(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $agents = \Quarksol\SmartChatbot\Models\ChatAgent::all(true); // Active only

            $result = [];

            // Add database agents FIRST (so they appear at top)
            foreach ($agents as $agent) {
                $result[] = [
                    'id' => $agent->agentId,
                    'name' => $agent->name,
                    'description' => $agent->description ?: 'Custom agent',
                    'avatar' => $agent->avatar,
                    'is_special' => false,
                ];
            }

            // Add "No Agent (Raw LLM)" at the end as a special option
            $result[] = [
                'id' => 'none',
                'name' => 'No Agent (Raw LLM)',
                'description' => 'Direct AI chat without any tools',
                'avatar' => '',
                'is_special' => true,
            ];

            return new \WP_REST_Response([
                'success' => true,
                'data' => $result,
            ], 200);

        } catch (\Throwable $e) {
            Logger::error('Failed to list available agents', ['error' => $e->getMessage()]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Failed to retrieve agents',
            ], 500);
        }
    }

    /**
     * DELETE /workspace/conversations/{id} - Delete conversation
     */
    public static function deleteConversation(\WP_REST_Request $request): \WP_REST_Response
    {
        $conversationId = $request->get_param('id');

        try {
            $service = new WorkspaceService();
            $deleted = $service->deleteConversation($conversationId);

            if (!$deleted) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Conversation not found',
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Conversation deleted',
            ], 200);

        } catch (\Throwable $e) {
            Logger::error('Failed to delete conversation', ['error' => $e->getMessage()]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Failed to delete conversation',
            ], 500);
        }
    }

    /**
     * POST /workspace/upload - Upload file attachment
     * 
     * Handles file uploads for workspace chat attachments.
     * Files are uploaded to WordPress media library.
     */
    public static function uploadFile(\WP_REST_Request $request): \WP_REST_Response
    {
        // Check if file was uploaded
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'No file uploaded',
            ], 400);
        }

        $file = $files['file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            ];

            return new \WP_REST_Response([
                'success' => false,
                'error' => $errorMessages[$file['error']] ?? 'Upload failed',
            ], 400);
        }

        // Validate file type
        $allowedTypes = [
            'text/plain',
            'text/csv',
            'text/markdown',
            'text/html',
            'application/json',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'File type not allowed: ' . $fileType,
            ], 400);
        }

        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'File too large. Maximum size is 10MB.',
            ], 400);
        }

        try {
            // Include WordPress media handling functions
            require_once(\ABSPATH . 'wp-admin/includes/file.php');
            require_once(\ABSPATH . 'wp-admin/includes/media.php');
            require_once(\ABSPATH . 'wp-admin/includes/image.php');

            // Prepare file for WordPress upload
            $uploadedFile = [
                'name' => sanitize_file_name($file['name']),
                'type' => $fileType,
                'tmp_name' => $file['tmp_name'],
                'error' => $file['error'],
                'size' => $file['size'],
            ];

            // Upload to WordPress
            $uploadOverrides = [
                'test_form' => false,
                'test_type' => true,
            ];

            $moveFile = wp_handle_upload($uploadedFile, $uploadOverrides);

            if (isset($moveFile['error'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => $moveFile['error'],
                ], 500);
            }

            // Create attachment in media library
            $attachment = [
                'post_mime_type' => $moveFile['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $file['name']),
                'post_content' => '',
                'post_status' => 'inherit',
            ];

            $attachmentId = wp_insert_attachment($attachment, $moveFile['file']);

            if (is_wp_error($attachmentId)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => $attachmentId->get_error_message(),
                ], 500);
            }

            // Generate attachment metadata
            $attachmentData = wp_generate_attachment_metadata($attachmentId, $moveFile['file']);
            wp_update_attachment_metadata($attachmentId, $attachmentData);

            // Read file content for text files (for AI processing)
            $fileContent = null;
            $textTypes = ['text/plain', 'text/csv', 'text/markdown', 'text/html', 'application/json'];

            if (in_array($fileType, $textTypes)) {
                $fileContent = file_get_contents($moveFile['file']);
                // Limit content length for safety
                if (strlen($fileContent) > 50000) {
                    $fileContent = substr($fileContent, 0, 50000) . "\n\n[Content truncated...]";
                }
            }

            Logger::info('Workspace file uploaded', [
                'attachment_id' => $attachmentId,
                'filename' => $file['name'],
                'type' => $fileType,
                'size' => $file['size'],
                'user_id' => get_current_user_id(),
            ]);

            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $attachmentId,
                    'url' => $moveFile['url'],
                    'filename' => $file['name'],
                    'type' => $fileType,
                    'size' => $file['size'],
                    'content' => $fileContent, // Text content for AI processing
                ],
            ], 200);

        } catch (\Throwable $e) {
            Logger::error('Failed to upload file', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Failed to upload file',
                'debug' => defined('WP_DEBUG') && \WP_DEBUG ? $e->getMessage() : null,
            ], 500);
        }
    }
}
