<?php
declare(strict_types=1);
/**
 * Workspace Service
 * 
 * Service layer for admin AI workspace operations.
 * Provides a "super-agent" with all toolkits enabled for site management.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Config\PromptBuilder;
use Quarksol\SmartChatbot\Bridge\ProviderBridge;

if (!defined('ABSPATH')) {
    exit;
}



/**
 * Workspace Service
 * 
 * Handles AI workspace message processing and conversation management.
 */
class WorkspaceService {
    
    /** Option key for workspace conversation storage */
    private const CONVERSATIONS_OPTION = 'swc_workspace_conversations';
    
    /** Maximum conversation history messages */
    private const MAX_HISTORY = 50;
    
    /**
     * Process a message from the workspace
     * 
     * @param string $message The user message
     * @param string|null $conversationId Optional conversation ID
     * @param string $agentId Agent selection: 'ultimate', 'none', or agent slug
     * @return array Response data with message and tool calls
     */
    public function processMessage(string $message, ?string $conversationId = null, string $agentId = 'ultimate'): array {
        // Get or create conversation
        if ($conversationId) {
            $conversation = $this->getConversation($conversationId);
            if (!$conversation) {
                $conversation = $this->createConversation('Workspace Chat');
                $conversationId = $conversation['id'];
            }
        } else {
            $conversation = $this->createConversation('Workspace Chat');
            $conversationId = $conversation['id'];
        }
        
        // Build conversation history
        $messages = $conversation['messages'] ?? [];
        
        // Add the user message
        $messages[] = [
            'role' => 'user',
            'content' => $message,
            'timestamp' => current_time('mysql'),
        ];
        
        // Limit history
        if (count($messages) > self::MAX_HISTORY * 2) {
            $messages = array_slice($messages, -self::MAX_HISTORY * 2);
        }
        
        try {
            // Process with the workspace agent (tools based on agent selection)
            $result = $this->executeWorkspaceAgent($message, $messages, $agentId, $conversationId);
            
            // Add assistant response
            $messages[] = [
                'role' => 'assistant',
                'content' => $result['message'] ?? '',
                'tool_calls' => $result['tool_calls'] ?? [],
                'timestamp' => current_time('mysql'),
            ];
            
            // Update conversation
            $this->saveConversationMessages($conversationId, $messages);
            
            return [
                'conversation_id' => $conversationId,
                'message' => $result['message'] ?? '',
                'tool_calls' => $result['tool_calls'] ?? [],
                'type' => 'workspace',
            ];
            
        } catch (\Throwable $e) {
            Logger::error('Workspace agent execution failed', [
                'error' => $e->getMessage(),
            ]);
            
            // Still record the attempt in conversation
            $messages[] = [
                'role' => 'assistant',
                'content' => 'I encountered an error: ' . $e->getMessage(),
                'error' => true,
                'timestamp' => current_time('mysql'),
            ];
            $this->saveConversationMessages($conversationId, $messages);
            
            throw $e;
        }
    }
    
    /**
     * Execute the workspace agent with tools based on agent selection
     * 
     * @param string $message Current user message
     * @param array $history Full conversation history
     * @param string $agentId Agent selection: 'ultimate', 'none', or agent slug
     * @return array Result with message and tool_calls
     */
    public function executeWorkspaceAgent(
        string $message,
        array $history,
        string $agentId = 'ultimate',
        ?string $conversationId = null
    ): array {
        // Handle Team/AgentGroup selection
        if (class_exists('\Quarksol\SmartChatbot\Models\\AgentGroup')) {
            $group = \Quarksol\SmartChatbot\Models\AgentGroup::findBySlug($agentId);
            if ($group) {
                Logger::info('Workspace executeWorkspaceAgent: Using SupervisorService for group', ['group_id' => $agentId]);
                $content = \Quarksol\SmartChatbot\Services\SupervisorService::execute($group, $message, $conversationId ?? '');
                return [
                    'message' => $content,
                    'tool_calls' => [],
                ];
            }
        }

        // Get tools based on agent selection
        $allTools = $this->getToolsForAgent($agentId);
        
        Logger::info('Workspace executeWorkspaceAgent started (VERIFICATION)', [
            'agent_id' => $agentId,
            'tools_count' => count($allTools),
            'history_count' => count($history),
        ]);
        
        // Build the system prompt for workspace mode
        $systemPrompt = $this->buildWorkspaceSystemPrompt($agentId, $allTools);
        
        // Convert history to NeuronAI Message format for chat history
        // IMPORTANT: Skip the LAST message (current user message) because
        // $agent->chat($userMessage) will add it automatically.
        // Including it here would cause the AI to see the message twice and respond twice.
        $chatMessages = [];
        $historyWithoutLast = array_slice($history, 0, -1); // Exclude last (current user msg)
        foreach ($historyWithoutLast as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                if ($msg['role'] === 'user') {
                    $chatMessages[] = new \NeuronAI\Chat\Messages\UserMessage($msg['content']);
                } elseif ($msg['role'] === 'assistant') {
                    $chatMessages[] = new \NeuronAI\Chat\Messages\AssistantMessage($msg['content']);
                }
            }
        }
        
        Logger::info('Workspace using NeuronAgent with agentic loop', [
            'agent_id' => $agentId,
            'tools_count' => count($allTools),
            'history_count' => count($chatMessages),
        ]);
        
        // Use NeuronAgent with agent-specific configuration
        // This loads only the tools selected for this agent in admin
        try {
            // Create the agent WITH the agent's specific config
            // withConfigId loads the agent's toolkits/skills from database
            // NOTE: withConfigId is static - creates new agent with config loaded
            $agent = \Quarksol\SmartChatbot\Agent\NeuronAgent::withConfigId($agentId);
            AgentContext::set($agent->getConfig(), null, $agentId);
            
            // Load ONLY prior conversation history into agent (not current message)
            foreach ($chatMessages as $historyMessage) {
                $agent->addToChatHistory($historyMessage);
            }

            $agentDbId = null;
            if (!empty($agentId) && class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
                $agentModel = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
                if ($agentModel) {
                    $agentDbId = $agentModel->id;
                }
            }

            if ($conversationId && method_exists($agent, 'setHistoryContext')) {
                $agent->setHistoryContext($conversationId, null, $agentDbId);
            }
            
            $toolCount = count($agent->bootstrapTools());
            Logger::debug('NeuronAgent bootstrapped for workspace', [
                'agent_id' => $agentId,
                'tools_count' => $toolCount,
                'instructions_length' => strlen($agent->resolveInstructions()),
            ]);
            
            Logger::info('NeuronAgent::chat starting with agentic loop');
            
            // Agent::chat() is the AGENTIC entry point:
            // 1. Sends message to LLM
            // 2. If LLM returns tool_calls → executeTools → get results
            // 3. Sends tool results back to LLM
            // 4. Repeats until LLM returns final text response
            $chatStartTime = microtime(true);
            $userMessage = new \NeuronAI\Chat\Messages\UserMessage($message);
            $agentHandler = $agent->chat($userMessage);
            $durationMs = (int) ((microtime(true) - $chatStartTime) * 1000);

            $responseContent = '';
            if (is_object($agentHandler) && method_exists($agentHandler, 'getMessage')) {
                $responseMsg = $agentHandler->getMessage();
                if ($responseMsg && method_exists($responseMsg, 'getContent')) {
                    $responseContent = $responseMsg->getContent() ?? '';
                }
            } elseif (is_object($agentHandler) && method_exists($agentHandler, 'getContent')) {
                $responseContent = $agentHandler->getContent() ?? '';
            }

            Logger::info('NeuronAgent::chat completed', [
                'response_type' => get_class($agentHandler),
                'content_length' => strlen($responseContent),
                'duration_ms' => $durationMs,
            ]);

            //  Track token usage and cost
            try {
                $settings = \Quarksol\SmartChatbot\Config\ChatbotConfig::settings();
                $providerName = $settings['ai_provider'] ?? 'unknown';
                $modelId = $settings['model_id'] ?? 'unknown';
                
                // Check per-provider configs (new format) and agent-specific provider
                $providerConfigs = $settings['provider_configs'] ?? [];
                $currentConfig = $providerConfigs[$providerName] ?? [];
                if (!empty($currentConfig['model'])) {
                    $modelId = $currentConfig['model'];
                }
                
                // Check if agent has its own provider instance
                $agentConfig = $agent->getConfig();
                if ($agentConfig && !empty($agentConfig->providerInstanceId)) {
                    $instances = $settings['provider_instances'] ?? [];
                    foreach ($instances as $inst) {
                        if (($inst['id'] ?? '') === $agentConfig->providerInstanceId) {
                            $providerName = $inst['provider'] ?? $providerName;
                            $modelId = $inst['model'] ?? $modelId;
                            break;
                        }
                    }
                }

                // Estimate tokens (~4 chars per token for English)
                $inputTokens = (int) ceil(strlen($message) / 4);
                $outputTokens = (int) ceil(strlen($responseContent) / 4);
                
                // Calculate cost (default to 0.0)
                $costUsd = 0.0;
                
                // Log to debug.log IMMEDIATELY
                error_log(sprintf(
                    '[SWC Token Usage] Provider: %s | Model: %s | Input: %d tokens | Output: %d tokens | Duration: %dms | Agent: %s',
                    $providerName,
                    $modelId,
                    $inputTokens,
                    $outputTokens,
                    $durationMs,
                    $agentId
                ));

                try {
                    if (class_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService')) {
                        $costUsd = \Quarksol\SmartChatbot\Analytics\AnalyticsService::calculateCost(
                            $providerName,
                            $modelId,
                            $inputTokens,
                            $outputTokens
                        );
                        
                        // Set context and track
                        \Quarksol\SmartChatbot\Analytics\AnalyticsService::setContext([
                            'agent_db_id' => $agentDbId ?? $agentId,
                            'session_id' => $conversationId,
                        ]);
                        
                        \Quarksol\SmartChatbot\Analytics\AnalyticsService::trackChat(
                            $providerName,
                            $modelId,
                            $inputTokens,
                            $outputTokens,
                            $costUsd,
                            $durationMs,
                            !empty($responseContent),
                            null
                        );
                    }
                } catch (\Throwable $e) {
                    // Log error at ERROR level so it shows up in user logs
                    Logger::error('Analytics tracking failed', ['error' => $e->getMessage()]);
                }

                //  Log to internal Logger (visible in user logs) - NOW INCLUDES COST
                Logger::info('Token Usage Stats', [
                    'provider' => $providerName,
                    'model' => $modelId,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'cost_usd' => $costUsd, // Will be 0.0 if calculation failed
                    'duration_ms' => $durationMs,
                    'agent_id' => $agentId,
                ]);
            } catch (\Throwable $e) {
                Logger::error('Workspace logging failed completely', ['error' => $e->getMessage()]);
            }

            $historyObserver = $agent->getHistoryObserver();
            if ($historyObserver) {
                try {
                    $historyObserver->persist();
                } catch (\Throwable $e) {
                    Logger::warning('Workspace tool execution persistence failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            return [
                'message' => $responseContent,
                'tool_calls' => [], // Tools already executed by agent loop
            ];
            
        } catch (\Throwable $e) {
            Logger::error('NeuronAgent failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // NO FALLBACK: Fail explicitly so user knows something is wrong
            // A clear error is better than garbage output from a broken fallback
            throw new \RuntimeException(
                'Agent execution failed: ' . $e->getMessage(),
                0,
                $e
            );
        } finally {
            AgentContext::clear();
        }
    }
    
    /**
     * Get tools based on agent selection
     * 
     * @param string $agentId 'none' for raw LLM, or agent slug
     * @return array Tool instances
     */
    protected function getToolsForAgent(string $agentId): array {
        // No tools for raw LLM mode
        if ($agentId === 'none') {
            return [];
        }
        
        // Load agent from database
        $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
        if (!$chatAgent || !$chatAgent->config) {
            Logger::warning('Agent not found, no tools available', ['agent_id' => $agentId]);
            return [];
        }
        
        // Get filtered tools based on agent config
        return \Quarksol\SmartChatbot\Config\ToolRegistry::getToolsForConfig($chatAgent->config);
    }
    /**
     * Fallback simple chat without tools
     */
    protected function executeSimpleChat(string $message, array $history, string $systemPrompt): array {
        // Try to get a basic AI response
        $settings = get_option('swc_chatbot_settings', []);
        $provider = $settings['ai_provider'] ?? 'openai';
        $apiKey = $settings['ai_api_key'] ?? '';
        $model = $settings['ai_model'] ?? 'gpt-4o-mini';
        $baseUrl = $settings['ai_base_url'] ?? '';
        
        if (empty($apiKey)) {
            return [
                'message' => 'AI provider is not configured. Please configure an AI provider in Settings to use the workspace.',
                'tool_calls' => [],
            ];
        }
        
        // Determine endpoint based on provider
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        if ($provider === 'azure') {
            // Azure OpenAI uses different endpoint and auth
            if (empty($baseUrl)) {
                return [
                    'message' => 'Azure OpenAI requires a Base URL / Endpoint. Please configure it in Settings.',
                    'tool_calls' => [],
                ];
            }
            $endpoint = rtrim($baseUrl, '/') . '/openai/deployments/' . $model . '/chat/completions?api-version=2024-02-15-preview';
            $headers['api-key'] = $apiKey;
        } else {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }
        
        // Build request body
        $requestBody = [
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $history
            ),
        ];

        // Handle max_tokens vs max_completion_tokens
        $isModernModel = strpos($model, 'gpt-4o') !== false || strpos($model, 'gpt-5') !== false || strpos($model, 'o1') !== false || strpos($model, 'o3') !== false;
        
        if ($provider === 'azure' || $isModernModel) {
            $requestBody['max_completion_tokens'] = 2000;
        } else {
            $requestBody['max_tokens'] = 2000;
        }
        
        // Only add model for non-Azure (Azure uses deployment name in URL)
        if ($provider !== 'azure') {
            $requestBody['model'] = $model;
        }
        
        try {
            $response = wp_remote_post($endpoint, [
                'headers' => $headers,
                'body' => json_encode($requestBody),
                'timeout' => 60,
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            // Check for API errors
            if (isset($body['error'])) {
                throw new \Exception($body['error']['message'] ?? 'API error');
            }
            
            return [
                'message' => $body['choices'][0]['message']['content'] ?? 'No response received.',
                'tool_calls' => [],
            ];
            
        } catch (\Throwable $e) {
            return [
                'message' => 'Failed to get AI response: ' . $e->getMessage(),
                'tool_calls' => [],
            ];
        }
    }
    
    /**
     * Build the system prompt for workspace mode
     * 
     * @param string $agentId Agent selection
     * @param array $tools Available tools for this agent
     */
    protected function buildWorkspaceSystemPrompt(string $agentId, array $tools = []): string {
        // 1. Handle "No Agent" / Raw LLM Mode
        if ($agentId === 'none') {
            $siteName = get_bloginfo('name');
            $siteUrl = get_site_url();
            $currentUser = wp_get_current_user();
            $wpVersion = get_bloginfo('version');
            
            $wooActive = class_exists('WooCommerce');
            $wooVersion = $wooActive && defined('WC_VERSION') ? WC_VERSION : 'N/A';
            
            // Count actual tools available
            $toolCount = count($tools);
            $toolsAvailable = $toolCount > 0 ? "{$toolCount} tools" : "NO TOOLS (error: tools not loaded)";
            
            return "You are a WordPress admin assistant for \"{$siteName}\".

## Site Context
- **Site**: {$siteName} ({$siteUrl})
- **WordPress**: {$wpVersion}
- **WooCommerce**: {$wooVersion}
- **Admin**: {$currentUser->display_name}
- **Tools Available**: {$toolsAvailable}

## CRITICAL: TOOL USAGE REQUIREMENTS

**YOU MUST ACTUALLY CALL TOOLS VIA FUNCTION CALLS** - Never simulate, pretend, or describe what a tool would do.

### MANDATORY RULES (VIOLATION = LYING):

1. **ONLY USE REAL FUNCTION CALLS** - When you need to create, read, update, or delete anything, you MUST invoke the actual tool via function_call. Do NOT just say \"Done!\" or \" Created!\" without making a real function call.

2. **NEVER FABRICATE RESULTS** - Do not make up:
   - Post IDs, order IDs, or any database IDs
   - Success confirmations
   - Tool execution results
   - Timestamps or dates
   If you haven't called a tool, you DON'T HAVE the data. Period.

3. **NO HALLUCINATED TOOL CALLS** - If you say \"I'm creating a post now\" but don't make an actual function_call, YOU ARE LYING. The user will know because nothing will happen.

4. **VERIFY FROM TOOL RESPONSE** - Only report success/failure based on the ACTUAL response from a tool call. If the tool returns an error, say it failed.

5. **IF TOOLS NOT WORKING** - If you cannot make function calls or tools are unavailable, say: \"I cannot execute actions right now - my tools are not responding. Please check the configuration.\"

### WRONG BEHAVIOR (LYING):
User: \"Create a test post\"  
 Assistant: \" Done! I created post #128 titled 'test'\"
(No function call was made - this is a fabrication)

### CORRECT BEHAVIOR:
User: \"Create a test post\"  
 Assistant: [CALLS wp_create_post function with title=\"test\"]  
Tool Response: {\"id\": 128, \"title\": \"test\", \"status\": \"draft\"}  
 Assistant: \"Done! Created draft post #128.\"

## Response Guidelines
- Call tools first, then report results
- Use markdown for formatting
- Be concise
- If unsure, ASK instead of guessing
";
        }

        // 2. Handle Specific Agent Mode
        $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentId);
        
        if (!$chatAgent || !$chatAgent->config) {
            throw new \Exception("Agent not found: {$agentId}");
        }
        
        // Use PromptBuilder to generate the agent-specific prompt
        $builder = \Quarksol\SmartChatbot\Config\PromptBuilder::forAgent($chatAgent->config, $tools);
        
        // Add extra context variables
        $variables = [
            'site_name' => get_bloginfo('name'),
            'user_name' => wp_get_current_user()->display_name,
        ];
        
        return $builder->build($variables);
    }
    
    /**
     * List all workspace conversations
     * 
     * @return array List of conversations (id, title, created_at, preview)
     */
    public function listConversations(): array {
        $conversations = get_option(self::CONVERSATIONS_OPTION, []);
        
        // Return list without full message content
        $list = [];
        foreach ($conversations as $id => $conv) {
            $messages = $conv['messages'] ?? [];
            $lastMessage = end($messages);
            
            $list[] = [
                'id' => $id,
                'title' => $conv['title'] ?? 'Untitled',
                'created_at' => $conv['created_at'] ?? '',
                'updated_at' => $conv['updated_at'] ?? '',
                'message_count' => count($messages),
                'preview' => isset($lastMessage['content']) 
                    ? substr($lastMessage['content'], 0, 100) . '...' 
                    : '',
            ];
        }
        
        // Sort by updated_at descending
        usort($list, fn($a, $b) => strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0'));
        
        return $list;
    }
    
    /**
     * Get a single conversation with all messages
     * 
     * @param string $conversationId
     * @return array|null
     */
    public function getConversation(string $conversationId): ?array {
        $conversations = get_option(self::CONVERSATIONS_OPTION, []);
        
        if (!isset($conversations[$conversationId])) {
            return null;
        }
        
        return array_merge(
            ['id' => $conversationId],
            $conversations[$conversationId]
        );
    }
    
    /**
     * Create a new conversation
     * 
     * @param string $title Conversation title
     * @return array The created conversation
     */
    public function createConversation(string $title): array {
        $conversations = get_option(self::CONVERSATIONS_OPTION, []);
        
        $id = 'conv_' . wp_generate_uuid4();
        $now = current_time('mysql');
        
        $conversations[$id] = [
            'title' => $title,
            'created_at' => $now,
            'updated_at' => $now,
            'messages' => [],
        ];
        
        update_option(self::CONVERSATIONS_OPTION, $conversations);
        
        return [
            'id' => $id,
            'title' => $title,
            'created_at' => $now,
            'updated_at' => $now,
            'messages' => [],
        ];
    }
    
    /**
     * Save messages to a conversation
     * 
     * @param string $conversationId
     * @param array $messages
     */
    public function saveConversationMessages(string $conversationId, array $messages): void {
        $conversations = get_option(self::CONVERSATIONS_OPTION, []);
        
        if (!isset($conversations[$conversationId])) {
            return;
        }
        
        $conversations[$conversationId]['messages'] = $messages;
        $conversations[$conversationId]['updated_at'] = current_time('mysql');
        
        // Auto-update title from first user message if still default
        if ($conversations[$conversationId]['title'] === 'Workspace Chat' || $conversations[$conversationId]['title'] === 'New Conversation') {
            foreach ($messages as $msg) {
                if (($msg['role'] ?? '') === 'user' && !empty($msg['content'])) {
                    $conversations[$conversationId]['title'] = substr($msg['content'], 0, 50);
                    if (strlen($msg['content']) > 50) {
                        $conversations[$conversationId]['title'] .= '...';
                    }
                    break;
                }
            }
        }
        
        update_option(self::CONVERSATIONS_OPTION, $conversations);
    }
    
    /**
     * Delete a conversation
     * 
     * @param string $conversationId
     * @return bool True if deleted, false if not found
     */
    public function deleteConversation(string $conversationId): bool {
        $conversations = get_option(self::CONVERSATIONS_OPTION, []);
        
        if (!isset($conversations[$conversationId])) {
            return false;
        }
        
        unset($conversations[$conversationId]);
        update_option(self::CONVERSATIONS_OPTION, $conversations);
        
        return true;
    }
}
