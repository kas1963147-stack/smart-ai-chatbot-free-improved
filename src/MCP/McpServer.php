<?php

/**
 * MCP Server
 * 
 * Core MCP server implementation that exposes WordPress as an MCP server.
 * Handles SSE connections, JSON-RPC messages, and tool execution.
 * 
 * @package Quarksol\SmartChatbot\MCP
 */

namespace Quarksol\SmartChatbot\MCP;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MCP Server
 * 
 * Implements the Model Context Protocol for external AI clients.
 * 
 * Endpoints:
 * - GET /mcp/v1/sse - Server-Sent Events connection
 * - POST /mcp/v1/messages - JSON-RPC message handling
 * - GET /mcp/v1/{token}/sse - No-auth SSE connection
 */
class McpServer
{
    private const NAMESPACE = 'mcp/v1';

    /**
     * Register REST API routes
     */
    public static function registerRoutes(): void
    {
        // SSE endpoint
        register_rest_route(self::NAMESPACE , '/sse', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleSseConnection'],
            'permission_callback' => [self::class, 'checkBearerToken'],
        ]);

        // Messages endpoint (alternative to POST to /sse)
        register_rest_route(self::NAMESPACE , '/messages', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleMessage'],
            'permission_callback' => [self::class, 'checkBearerToken'],
        ]);

        // POST to SSE (some clients send JSON-RPC here)
        register_rest_route(self::NAMESPACE , '/sse', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleMessage'],
            'permission_callback' => [self::class, 'checkBearerToken'],
        ]);

        // No-auth SSE endpoint (token in URL)
        register_rest_route(self::NAMESPACE , '/(?P<token>[a-zA-Z0-9_]+)/sse', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleSseConnection'],
            'permission_callback' => [self::class, 'checkUrlToken'],
        ]);

        // No-auth messages endpoint
        register_rest_route(self::NAMESPACE , '/(?P<token>[a-zA-Z0-9_]+)/messages', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleMessage'],
            'permission_callback' => [self::class, 'checkUrlToken'],
        ]);
    }

    /**
     * Check bearer token from Authorization header
     */
    public static function checkBearerToken(\WP_REST_Request $request): bool
    {
        if (!McpServerService::isEnabled()) {
            return false;
        }

        $auth = $request->get_header('Authorization');
        if (!$auth) {
            // Also check query parameter
            $auth = $request->get_param('token');
            if ($auth) {
                return McpServerService::validateToken($auth);
            }
            return false;
        }

        // Extract Bearer token
        if (strpos($auth, 'Bearer ') === 0) {
            $token = substr($auth, 7);
            return McpServerService::validateToken($token);
        }

        return false;
    }

    /**
     * Check token from URL path
     */
    public static function checkUrlToken(\WP_REST_Request $request): bool
    {
        if (!McpServerService::isEnabled()) {
            return false;
        }

        $token = $request->get_param('token');
        return $token && McpServerService::validateToken($token);
    }

    /**
     * Handle SSE connection
     */
    public static function handleSseConnection(\WP_REST_Request $request): void
    {
        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Generate session ID
        $sessionId = wp_generate_uuid4();
        $messagesUrl = add_query_arg('session_id', $sessionId, McpServerService::getMessagesUrl());

        // Send endpoint event
        echo "event: endpoint\n";
        echo "data: " . $messagesUrl . "\n\n";

        // Flush output
        if (function_exists('fastcgi_finish_request')) {
            // Don't call this - keep connection open
        }
        flush();

        // Store session in transient for message correlation
        set_transient('mcp_session_' . $sessionId, [
            'created' => time(),
            'messages' => [],
        ], 3600);

        // Keep connection alive with periodic pings
        $startTime = time();
        $timeout = 300; // 5 minute timeout

        while ((time() - $startTime) < $timeout) {
            // Check for queued messages
            $session = get_transient('mcp_session_' . $sessionId);
            if ($session && !empty($session['messages'])) {
                foreach ($session['messages'] as $message) {
                    echo "event: message\n";
                    echo "data: " . json_encode($message) . "\n\n";
                }

                // Clear processed messages
                $session['messages'] = [];
                set_transient('mcp_session_' . $sessionId, $session, 3600);
            }

            // Send ping to keep connection alive
            echo ": ping\n\n";
            flush();

            // Check if client disconnected
            if (connection_aborted()) {
                break;
            }

            sleep(1);
        }

        // Cleanup
        delete_transient('mcp_session_' . $sessionId);
        exit;
    }

    /**
     * Handle JSON-RPC message
     */
    public static function handleMessage(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();

        if (!$body || !isset($body['method'])) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request',
                ],
                'id' => $body['id'] ?? null,
            ], 400);
        }

        $method = $body['method'];
        $params = $body['params'] ?? [];
        $id = $body['id'] ?? null;

        // Route to handler
        $result = match ($method) {
            'initialize' => self::handleInitialize($params),
            'initialized' => self::handleInitialized(),
            'tools/list' => self::handleToolsList($params),
            'tools/call' => self::handleToolsCall($params),
            'ping' => ['pong' => true],
            default => null,
        };

        if ($result === null) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found: ' . $method,
                ],
                'id' => $id,
            ], 404);
        }

        // For notifications (no ID), don't send response body
        if ($id === null && in_array($method, ['initialized', 'notifications/cancelled'])) {
            return new \WP_REST_Response(null, 204);
        }

        return new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ], 200);
    }

    /**
     * Handle initialize request
     */
    private static function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => McpServerService::getProtocolVersion(),
            'capabilities' => McpServerService::getCapabilities(),
            'serverInfo' => McpServerService::getServerInfo(),
        ];
    }

    /**
     * Handle initialized notification
     */
    private static function handleInitialized(): array
    {
        return [];
    }

    /**
     * Handle tools/list request
     */
    private static function handleToolsList(array $params): array
    {
        $tools = McpToolRegistry::getEnabledTools();

        $formattedTools = [];
        foreach ($tools as $name => $tool) {
            $formattedTools[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
                'annotations' => $tool['annotations'] ?? new \stdClass(),
            ];
        }

        return [
            'tools' => $formattedTools,
        ];
    }

    /**
     * Handle tools/call request
     */
    private static function handleToolsCall(array $params): array
    {
        $toolName = $params['name'] ?? null;
        $toolArgs = $params['arguments'] ?? [];

        if (!$toolName) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['error' => 'Tool name required']),
                    ]
                ],
                'isError' => true,
            ];
        }

        // Get enabled tools
        $tools = McpToolRegistry::getEnabledTools();

        if (!isset($tools[$toolName])) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['error' => 'Tool not found or disabled: ' . $toolName]),
                    ]
                ],
                'isError' => true,
            ];
        }

        // Execute tool
        try {
            $result = McpToolExecutor::execute($toolName, $toolArgs);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : json_encode($result),
                    ]
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['error' => $e->getMessage()]),
                    ]
                ],
                'isError' => true,
            ];
        }
    }

    /**
     * Initialize the MCP server
     */
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }
}
