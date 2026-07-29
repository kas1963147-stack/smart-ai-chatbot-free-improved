<?php
declare(strict_types=1);
/**
 * Hook Definitions
 * 
 * Registers all plugin hooks with documentation.
 * Called during plugin initialization.
 * 
 * @package Quarksol\SmartChatbot\Hooks
 */

namespace Quarksol\SmartChatbot\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all plugin hooks
 * 
 * This function should be called during plugin initialization
 * to register all hooks with their documentation.
 */
function register_all_hooks(): void
{

    // =========================================================================
    // Query Lifecycle Hooks
    // =========================================================================

    HookRegistry::register('swc/query/created', [
        'type' => 'filter',
        'description' => 'Fires when a new query is created. Allows modification of message, instructions, context, and tools.',
        'params' => [
            'payload' => 'QueryCreatedPayload - Full query payload with message, agent, session',
        ],
        'returns' => 'QueryCreatedPayload',
        'since' => '2.0.0',
        'example' => "add_filter('swc/query/created', function(\$payload) {
    // Add user context
    \$payload = \$payload->withContext(\"User timezone: \" . wp_timezone_string());
    return \$payload;
});
"
    ]);

    HookRegistry::register('swc/query/instructions', [
        'type' => 'filter',
        'description' => 'Modify agent instructions before sending to the AI provider.',
        'params' => [
            'payload' => 'InstructionsPayload - Instructions with placeholders',
        ],
        'returns' => 'InstructionsPayload',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/query/context', [
        'type' => 'filter',
        'description' => 'Inject additional context from knowledge bases or external sources.',
        'params' => [
            'context' => 'string|null - Current context',
            'message' => 'string - User message',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'string|null',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/query/messages', [
        'type' => 'filter',
        'description' => 'Modify message history before sending to the AI.',
        'params' => [
            'messages' => 'array - Message history',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'array',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/query/tools', [
        'type' => 'filter',
        'description' => 'Modify available tools before query execution.',
        'params' => [
            'tools' => 'array - Tool definitions',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'array',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/query/pre_send', [
        'type' => 'filter',
        'description' => 'Last chance to modify the query before sending to the API.',
        'params' => [
            'payload' => 'QueryPreSendPayload - Complete query ready for API',
        ],
        'returns' => 'QueryPreSendPayload',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Reply Lifecycle Hooks
    // =========================================================================

    HookRegistry::register('swc/reply/received', [
        'type' => 'filter',
        'description' => 'Fires when a raw response is received from the AI provider.',
        'params' => [
            'payload' => 'ReplyReceivedPayload - Raw response with usage data',
        ],
        'returns' => 'ReplyReceivedPayload',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/reply/tool_calls', [
        'type' => 'filter',
        'description' => 'Fires when tool calls are detected. Allows filtering or modifying tool calls.',
        'params' => [
            'payload' => 'ReplyToolCallsPayload - Tool calls from AI response',
        ],
        'returns' => 'ReplyToolCallsPayload',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/reply/content', [
        'type' => 'filter',
        'description' => 'Modify the final reply content before sending to client.',
        'params' => [
            'content' => 'string - Reply content',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/reply/usage', [
        'type' => 'action',
        'description' => 'Fired for usage tracking. Use for analytics and cost monitoring.',
        'params' => [
            'payload' => 'UsagePayload - Token and cost usage data',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/reply/complete', [
        'type' => 'filter',
        'description' => 'Final reply payload ready to be sent to client.',
        'params' => [
            'payload' => 'ReplyCompletePayload - Complete reply with all data',
        ],
        'returns' => 'ReplyCompletePayload',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Agent System Hooks
    // =========================================================================

    HookRegistry::register('swc/agent/loaded', [
        'type' => 'filter',
        'description' => 'Fires when an agent is loaded from database.',
        'params' => [
            'agent' => 'ChatAgent - The loaded agent',
        ],
        'returns' => 'ChatAgent',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/agent/instructions', [
        'type' => 'filter',
        'description' => 'Modify agent instructions after loading.',
        'params' => [
            'instructions' => 'string - Agent instructions',
            'agent' => 'ChatAgent - The agent',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/agent/tools_loaded', [
        'type' => 'filter',
        'description' => 'Modify tools assigned to an agent.',
        'params' => [
            'tools' => 'array - Tool definitions',
            'agent' => 'ChatAgent - The agent',
        ],
        'returns' => 'array',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/agent/pre_execute', [
        'type' => 'action',
        'description' => 'Fires before an agent executes a query.',
        'params' => [
            'agent' => 'ChatAgent - The agent',
            'message' => 'string - User message',
            'session' => 'ChatSession|null - Current session',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/agent/post_execute', [
        'type' => 'action',
        'description' => 'Fires after an agent completes a query.',
        'params' => [
            'agent' => 'ChatAgent - The agent',
            'response' => 'string - Agent response',
            'session' => 'ChatSession|null - Current session',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Orchestration Hooks
    // =========================================================================

    HookRegistry::register('swc/orchestrator/route', [
        'type' => 'filter',
        'description' => 'Fires when router is selecting an agent from a group.',
        'params' => [
            'selected_agent' => 'ChatAgent|null - Currently selected agent',
            'group' => 'AgentGroup - The agent group',
            'message' => 'string - User message',
        ],
        'returns' => 'ChatAgent|null',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/orchestrator/aggregate', [
        'type' => 'filter',
        'description' => 'Modify aggregated response from multiple agents.',
        'params' => [
            'response' => 'string - Combined response',
            'responses' => 'array - Individual agent responses',
            'mode' => 'string - Aggregation mode',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Tool Execution Hooks
    // =========================================================================

    HookRegistry::register('swc/tool/access_check', [
        'type' => 'filter',
        'description' => 'Check if a tool is allowed to execute.',
        'params' => [
            'allowed' => 'bool - Current access decision',
            'tool' => 'array - Tool definition',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'bool',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/tool/pre_execute', [
        'type' => 'filter',
        'description' => 'Fires before a tool executes. Can modify arguments or block execution.',
        'params' => [
            'args' => 'array - Tool arguments',
            'tool' => 'array - Tool definition',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'array',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/tool/post_execute', [
        'type' => 'filter',
        'description' => 'Fires after a tool executes. Can modify the result.',
        'params' => [
            'result' => 'mixed - Tool execution result',
            'tool' => 'array - Tool definition',
            'args' => 'array - Tool arguments',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'mixed',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/tool/error', [
        'type' => 'action',
        'description' => 'Fires when a tool execution fails.',
        'params' => [
            'error' => 'string - Error message',
            'tool' => 'array - Tool definition',
            'args' => 'array - Tool arguments',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Session/Chat Hooks
    // =========================================================================

    HookRegistry::register('swc/session/created', [
        'type' => 'action',
        'description' => 'Fires when a new session is created.',
        'params' => [
            'session' => 'ChatSession - The new session',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/session/resumed', [
        'type' => 'action',
        'description' => 'Fires when an existing session is resumed.',
        'params' => [
            'session' => 'ChatSession - The resumed session',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/chat/widget_render', [
        'type' => 'filter',
        'description' => 'Modify chat widget configuration before rendering.',
        'params' => [
            'config' => 'array - Widget configuration',
            'widget_id' => 'int - Widget ID',
        ],
        'returns' => 'array',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/chat/message_sent', [
        'type' => 'action',
        'description' => 'Fires when a user sends a message.',
        'params' => [
            'message' => 'string - User message',
            'session' => 'ChatSession|null - Current session',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Knowledge System Hooks
    // =========================================================================

    HookRegistry::register('swc/knowledge/search', [
        'type' => 'filter',
        'description' => 'Modify knowledge search results.',
        'params' => [
            'results' => 'array - Search results',
            'query' => 'string - Search query',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'array',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/knowledge/context', [
        'type' => 'filter',
        'description' => 'Modify context before injection into query.',
        'params' => [
            'context' => 'string - Context content',
            'sources' => 'array - Source documents',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Provider Hooks
    // =========================================================================

    HookRegistry::register('swc/provider/selected', [
        'type' => 'filter',
        'description' => 'Fires when a provider is selected for a query.',
        'params' => [
            'provider' => 'string - Provider ID',
            'model' => 'string - Model ID',
            'agent' => 'ChatAgent|null - Current agent',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/provider/error', [
        'type' => 'filter',
        'description' => 'Modify error message from provider.',
        'params' => [
            'error' => 'string - Error message',
            'provider' => 'string - Provider ID',
            'exception' => 'Exception|null - Original exception',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Streaming Hooks
    // =========================================================================

    HookRegistry::register('swc/stream/chunk', [
        'type' => 'filter',
        'description' => 'Modify each streaming chunk before sending to client.',
        'params' => [
            'chunk' => 'string - Chunk content',
            'index' => 'int - Chunk index',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/stream/complete', [
        'type' => 'action',
        'description' => 'Fires when streaming is complete.',
        'params' => [
            'content' => 'string - Full streamed content',
            'chunks_count' => 'int - Number of chunks',
        ],
        'returns' => 'void',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // Permission Hooks
    // =========================================================================

    HookRegistry::register('swc/permission/access_settings', [
        'type' => 'filter',
        'description' => 'Check if user can access plugin settings.',
        'params' => [
            'allowed' => 'bool - Current decision',
            'user_id' => 'int - User ID',
        ],
        'returns' => 'bool',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/permission/use_agent', [
        'type' => 'filter',
        'description' => 'Check if user can use a specific agent.',
        'params' => [
            'allowed' => 'bool - Current decision',
            'agent' => 'ChatAgent - The agent',
            'user_id' => 'int|null - User ID',
        ],
        'returns' => 'bool',
        'since' => '2.0.0',
    ]);

    // =========================================================================
    // MCP Hooks
    // =========================================================================

    HookRegistry::register('swc/mcp/tool_call', [
        'type' => 'filter',
        'description' => 'Fires when an MCP tool is being called.',
        'params' => [
            'tool_name' => 'string - Tool name',
            'arguments' => 'array - Tool arguments',
            'server' => 'string - MCP server ID',
        ],
        'returns' => 'array',
        'since' => '2.0.0',
    ]);

    HookRegistry::register('swc/mcp/resource_read', [
        'type' => 'filter',
        'description' => 'Fires when an MCP resource is being accessed.',
        'params' => [
            'uri' => 'string - Resource URI',
            'server' => 'string - MCP server ID',
        ],
        'returns' => 'string',
        'since' => '2.0.0',
    ]);
}
