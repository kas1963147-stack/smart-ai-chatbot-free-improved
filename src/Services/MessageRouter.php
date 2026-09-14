<?php
declare(strict_types=1);
/**
 * Message Router
 * 
 * Routes user messages to appropriate handlers based on intent.
 * Integrates with AI agents (22 WooCommerce tools) and Knowledge Base (RAG).
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Config\ChatbotConfig;
use Quarksol\SmartChatbot\Config\IntentPatterns;
use Quarksol\SmartChatbot\Services\ProductService;
use NeuronAI\Chat\Messages\UserMessage;
use SWC_Chatbot_FAQ; // Legacy support
use SWC_Chatbot_Context_Retriever; // Legacy support

if (!defined('ABSPATH')) {
    exit;
}

class MessageRouter {
    
    /**
     * Route message to appropriate handler
     * 
     * @param string $message User message
     * @param string $context Current conversation context
     * @param int $agentId Active agent ID
     * @param string $sessionId Session identifier
     * @param array $history Previous conversation messages for context
     * @param int|null $messageIndex Message index for tool call linkage
     * @param string|null $messageId Message ID for tool call linkage
     */
    public static function route(
        string $message,
        string $context = '',
        int $agentId = 0,
        string $sessionId = '',
        array $history = [],
        ?int $messageIndex = null,
        ?string $messageId = null
    ): array {
        $router = new self();
        $router->history = $history;
        $router->sessionId = $sessionId;
        $router->agentId = $agentId;
        $router->messageIndex = $messageIndex;
        $router->messageId = $messageId;
        
        $config = null;
        $agentDbId = null;
        $agentSlug = null;
        if ($agentId > 0 && class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
            $dbAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find($agentId);
            if ($dbAgent && $dbAgent->config) {
                $config = $dbAgent->config;
                $agentDbId = $dbAgent->id;
                $agentSlug = $dbAgent->agentId;
            }
        }
        
        // STRICT: Only use config if a specific agent was requested
        // No fallback to default agent — the caller (StreamController) handles this
        
        if ($config) {
            AgentContext::set($config, $agentDbId, $agentSlug, $sessionId);
        }
        
        try {
            return $router->handle($message, $context);
        } finally {
            AgentContext::clear();
        }
    }
    
    /**
     * Route directly to AI agent with tools - NO pattern matching
     * 
     * This bypasses all automated responses and sends the message
     * directly to the AI agent (ShoppingAgent or custom agent).
     */
    public static function routeDirectToAI(
        string $message, 
        string $context = '', 
        int $agentId = 0, 
        string $sessionId = '', 
        array $history = [],
        ?int $messageIndex = null,
        ?string $messageId = null
    ): array {
        $router = new self();
        $router->history = $history;
        $router->sessionId = $sessionId;
        $router->agentId = $agentId;
        $router->messageIndex = $messageIndex;
        $router->messageId = $messageId;
        
        // Skip all pattern matching - go directly to AI
        return $router->executeAIAgent($message, $agentId);
    }
    
    private ?ProductService $productService = null;
    private array $history = [];
    private string $sessionId = '';
    private int $agentId = 0;
    private ?int $messageIndex = null;
    private ?string $messageId = null;
    
    public function __construct() {
        // ProductService is lazy-loaded only when needed
    }
    
    /**
     * Get the ProductService instance (lazy-loaded)
     */
    private function getProductService(): ProductService {
        if ($this->productService === null) {
            $this->productService = new ProductService();
        }
        return $this->productService;
    }
    
    // ========================================================================
    // SHARED AGENT INFRASTRUCTURE
    // ========================================================================
    
    /**
     * Resolve the AI agent to use for this request.
     * 
     * Resolution order:
     * 1. Specific agent from DB (by agentId)
     * 2. Default agent from DB
     * 3. ShoppingAgent fallback (if WooCommerce is active)
     * 
     * @param int $agentId Preferred agent ID
     * @return object|null The resolved Neuron agent, or null
     */
    private function resolveAgent(int $agentId = 0): ?object {
        error_log("[AGENT_RESOLVE] Starting agent resolution. Requested DB ID: {$agentId}");
        
        // Try specific agent from DB
        if ($agentId > 0 && class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
            $dbAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find($agentId);
            if ($dbAgent) {
                error_log("[AGENT_RESOLVE] Found agent in DB: id={$dbAgent->id}, slug='{$dbAgent->agentId}', name='{$dbAgent->name}'");
                try {
                    $agent = $dbAgent->toNeuronAgent();
                    if (method_exists($agent, 'getConfig')) {
                        AgentContext::set($agent->getConfig(), $dbAgent->id, $dbAgent->agentId);
                    }
                    return $agent;
                } catch (\Throwable $e) {
                    Logger::warning('Failed to create agent from DB', ['error' => $e->getMessage()]);
                    error_log("[AGENT_RESOLVE] ERROR creating agent: " . $e->getMessage());
                    // Fall through to strict throw
                }
            }
        }
        
        // STRICT: No fallback to default agent or ShoppingAgent
        error_log("[AGENT_RESOLVE] No agent could be resolved — strict mode, no fallbacks");
        throw new \RuntimeException(
            "This agent is not fully configured yet. Please contact the site administrator."
        );
    }
    
    /**
     * Load conversation history into an agent for context.
     * 
     * @param object $agent The Neuron agent
     */
    private function loadHistoryIntoAgent(object $agent): void {
        if (empty($this->history)) {
            return;
        }
        
        foreach ($this->history as $historyMsg) {
            $role = $historyMsg['role'] ?? 'user';
            $content = $historyMsg['content'] ?? '';
            
            if (empty($content)) {
                continue;
            }
            
            $historyMessage = ($role === 'assistant')
                ? new \NeuronAI\Chat\Messages\AssistantMessage($content)
                : new UserMessage($content);
            
            $agent->addToChatHistory($historyMessage);
        }
        
        // Set history context for tool call persistence
        $agentDbId = $this->agentId > 0 ? $this->agentId : null;
        if (!empty($this->sessionId) && method_exists($agent, 'setHistoryContext')) {
            $agent->setHistoryContext(
                $this->sessionId,
                $this->messageIndex,
                $agentDbId,
                $this->messageId
            );
        }
    }
    
    /**
     * Extract text content from an AI response object.
     * 
     * @param mixed $responseObj The response from agent->chat()
     * @return string The extracted text content
     */
    private function extractResponse($responseObj): string {
        if (is_object($responseObj) && method_exists($responseObj, 'getMessage')) {
            $msg = $responseObj->getMessage();
            if ($msg && method_exists($msg, 'getContent')) {
                return $msg->getContent() ?? '';
            }
        }
        if (is_object($responseObj) && method_exists($responseObj, 'getContent')) {
            return $responseObj->getContent() ?? '';
        }
        if (is_object($responseObj) && isset($responseObj->content)) {
            return $responseObj->content ?? '';
        }
        if (is_string($responseObj)) {
            return $responseObj;
        }
        return '';
    }
    
    /**
     * Execute a chat with an agent, including timing, tool call capture, and analytics.
     * 
     * @param object $agent The Neuron agent
     * @param string $message The user message
     * @param string $source Analytics source identifier
     * @return array The response array
     */
    private function executeAgentChat(object $agent, string $message, string $source = 'direct_ai'): array {
        $userMessage = new UserMessage($message);
        
        $chatStartTime = microtime(true);
        $responseObj = $agent->chat($userMessage);
        $durationMs = (int) ((microtime(true) - $chatStartTime) * 1000);
        
        // Capture tool executions from HistoryObserver
        $toolCalls = [];
        $historyObserver = $agent->getHistoryObserver();
        if ($historyObserver) {
            $toolCalls = $historyObserver->getExecutionsForMessage();
            
            try {
                $historyObserver->persist();
            } catch (\Throwable $e) {
                Logger::warning('Failed to persist tool executions', ['error' => $e->getMessage()]);
            }
        }
        
        $responseContent = $this->extractResponse($responseObj);
        
        if (!empty($responseContent)) {
            $this->trackAIUsage($source, $message, $responseContent, $durationMs);
            return [
                'type' => 'text',
                'message' => $responseContent,
                'tool_calls' => $toolCalls,
            ];
        }
        
        Logger::warning('AI Agent returned empty response', [
            'source' => $source,
            'message_preview' => substr($message, 0, 50),
            'response_type' => is_object($responseObj) ? get_class($responseObj) : gettype($responseObj),
        ]);
        
        return [
            'type' => 'text',
            'message' => __('I apologize, but I could not generate a response. Please try again.', 'quark-agentflow-ai'),
            'tool_calls' => $toolCalls,
        ];
    }
    
    // ========================================================================
    // ROUTING METHODS
    // ========================================================================
    
    /**
     * Execute AI agent directly with full tool access
     * 
     * This method bypasses all pattern matching and sends the message
     * directly to the AI agent (custom agent from DB or ShoppingAgent).
     * 
     * If the agent belongs to a team (AgentGroup) with workflow steps,
     * those steps are executed instead of the single-agent flow.
     */
    private function executeAIAgent(string $message, int $agentId = 0): array {
        // Allow admin users (agent testing) to bypass the ai_enabled check
        if (!ChatbotConfig::isAIEnabled() && !current_user_can('manage_options')) {
            return [
                'type' => 'text', 
                'message' => __('AI is not enabled. Please enable AI in the chatbot settings.', 'quark-agentflow-ai'),
            ];
        }

        // ── Team Workflow Orchestration ──
        // Check if this agent belongs to a team with workflow steps
        if ($agentId > 0 && class_exists('\Quarksol\SmartChatbot\Models\\AgentGroup') && class_exists('\Quarksol\SmartChatbot\Services\OrchestratorService')) {
            try {
                $group = \Quarksol\SmartChatbot\Models\AgentGroup::findByMember($agentId);

                if ($group && $group->isActive && $group->hasWorkflowSteps()) {
                    Logger::info('Agent belongs to team with workflow steps', [
                        'agent_id'   => $agentId,
                        'group_id'   => $group->groupId,
                        'group_name' => $group->name,
                        'step_count' => count($group->getWorkflowSteps()),
                    ]);

                    $result = OrchestratorService::handleRequest(null, $message, $group, $this->sessionId);

                    if ($result && !empty($result->content)) {
                        return [
                            'type'    => 'text',
                            'message' => $result->content,
                            'orchestration' => [
                                'mode'    => $result->mode,
                                'details' => $result->details,
                            ],
                        ];
                    }

                    // If orchestration returned nothing, fall through to single-agent
                    Logger::info('Workflow returned no content, falling back to single agent');
                }
            } catch (\Throwable $e) {
                Logger::error('Team orchestration failed, falling back to single agent', [
                    'agent_id' => $agentId,
                    'error'    => $e->getMessage(),
                ]);
                // Fall through to normal single-agent execution
            }
        }
        
        $agent = $this->resolveAgent($agentId);
        
        try {
            $this->loadHistoryIntoAgent($agent);
            return $this->executeAgentChat($agent, $message, 'direct_ai');
        } catch (\Throwable $e) {
            Logger::error('Direct AI execution failed', [
                'error' => $e->getMessage(),
                'message' => substr($message, 0, 100),
            ]);
            
            return [
                'type' => 'text',
                'message' => __('Sorry, I encountered an error processing your request. Please try again.', 'quark-agentflow-ai'),
                'debug' => defined('WP_DEBUG') && \WP_DEBUG ? $e->getMessage() : null,
            ];
        } finally {
            AgentContext::clear();
        }
    }
    
    /**
     * Main handler logic (used when DIRECT_AI_MODE is off)
     * 
     * Routes messages through pattern matching before falling back to AI.
     * Uses IntentPatterns for consistent keyword matching.
     */
    public function handle(string $message, string $context = ''): array {
        $messageLower = strtolower($message);
        
        // 1. Context-based handling
        if ($context === 'awaiting_order_id') {
            return $this->handleOrderLookup($message);
        }
        
        // 2. Knowledge Base Command (@knowledge)
        if (IntentPatterns::matchesAny($message, IntentPatterns::KNOWLEDGE_PATTERNS)) {
            $query = IntentPatterns::extractMatch($message, IntentPatterns::KNOWLEDGE_PATTERNS);
            if ($query) {
                return $this->handleKnowledgeSearch($query);
            }
        }
        
        // 3. Product Intent Detection
        if (IntentPatterns::matchesAny($message, IntentPatterns::PRODUCT_SEARCH_PATTERNS)) {
            return $this->handleProductSearch($message);
        }
        
        // 4. Store Information
        if (IntentPatterns::containsAny($message, IntentPatterns::STORE_INFO_KEYWORDS)) {
            return $this->handleStoreInfo();
        }
        
        // 5. Best Sellers & Trends
        if (IntentPatterns::containsAny($message, IntentPatterns::BEST_SELLERS_KEYWORDS)) {
            return $this->handleBestSellers();
        }
        
        // 6. Sales & Discounts
        if (IntentPatterns::containsAny($message, IntentPatterns::SALE_KEYWORDS)) {
            return $this->handleOnSale();
        }
        
        // 7. General FAQ
        if (class_exists('SWC_Chatbot_FAQ')) {
            $faqMatch = SWC_Chatbot_FAQ::find_match($message);
            if ($faqMatch) {
                return ['type' => 'text', 'message' => "Great question! \n\n**Q: {$faqMatch->question}**\n\n{$faqMatch->answer}"];
            }
        }
        
        // 8. Default: AI Agent Fallback
        return $this->handleFallback($message);
    }
    
    /**
     * Handle Product Search
     */
    private function handleProductSearch(string $message): array {
        $keywords = [];
        if (class_exists('SWC_Chatbot_Context_Retriever')) {
            $keywords = SWC_Chatbot_Context_Retriever::extract_keywords($message);
        }
        
        $query = !empty($keywords) ? implode(' ', $keywords) : $message;
        $products = $this->getProductService()->search($query, 10);
        
        if (!empty($products)) {
            return [
                'type' => 'products',
                'message' => __("Here are some products I found for you!", 'quark-agentflow-ai'),
                'products' => $products,
            ];
        }
        
        return ['type' => 'text', 'message' => __("I couldn't find products matching that. Try different keywords!", 'quark-agentflow-ai')];
    }
    
    /**
     * Handle Order Lookup
     */
    private function handleOrderLookup(string $orderId): array {
        $orderId = preg_replace('/[^0-9]/', '', $orderId);
        
        if (empty($orderId)) {
            return ['type' => 'text', 'message' => __("Please enter a valid order number.", 'quark-agentflow-ai')];
        }
        
        if (!class_exists('WooCommerce')) {
            return ['type' => 'text', 'message' => __("Order tracking is not available.", 'quark-agentflow-ai')];
        }
        
        $order = wc_get_order($orderId);
        if (!$order) {
            return ['type' => 'text', 'message' => sprintf(__("Order #%s not found. Please check the number and try again.", 'quark-agentflow-ai'), $orderId)];
        }
        
        $status = wc_get_order_status_name($order->get_status());
        $date = $order->get_date_created()->format('M j, Y');
        $total = $order->get_formatted_order_total();
        
        return [
            'type' => 'text',
            'message' => " **Order #{$orderId}**\n\n**Status:** {$status}\n**Date:** {$date}\n**Total:** {$total}",
        ];
    }
    
    /**
     * Handle Store Info
     */
    private function handleStoreInfo(): array {
        $settings = ChatbotConfig::settings();
        $storeName = $settings['store_name'] ?? get_bloginfo('name');
        $description = $settings['store_description'] ?? get_bloginfo('description');
        
        return [
            'type' => 'text',
            'message' => " **Welcome to {$storeName}!**\n\n{$description}\n\nHow can I help you today?",
        ];
    }
    
    /**
     * Handle Best Sellers
     */
    private function handleBestSellers(): array {
        $products = $this->getProductService()->getBestSellers(5);
        if (!empty($products)) {
            return ['type' => 'products', 'message' => __(" Here are our best sellers!", 'quark-agentflow-ai'), 'products' => $products];
        }
        return ['type' => 'text', 'message' => __("Check out our featured products on the homepage!", 'quark-agentflow-ai')];
    }
    
    /**
     * Handle On Sale Products
     */
    private function handleOnSale(): array {
        $products = $this->getProductService()->getOnSale(5);
        if (!empty($products)) {
            return ['type' => 'products', 'message' => __(" Check out these great deals!", 'quark-agentflow-ai'), 'products' => $products];
        }
        return ['type' => 'text', 'message' => __("No active sales right now. Check back soon!", 'quark-agentflow-ai')];
    }
    
    /**
     * Handle Knowledge Base Search
     */
    private function handleKnowledgeSearch(string $query): array {
        if (!AgentContext::isKnowledgeEnabled() || !class_exists('\Quarksol\SmartChatbot\Knowledge\\HybridSearcher')) {
            return ['type' => 'text', 'message' => __("Knowledge base is not available.", 'quark-agentflow-ai')];
        }
        
        $allowedSources = AgentContext::getAllowedKnowledgeSources();
        if ($allowedSources !== null && empty($allowedSources)) {
            return ['type' => 'text', 'message' => __('No knowledge sources are enabled for this agent.', 'quark-agentflow-ai')];
        }
        
        try {
            $searcher = new \Quarksol\SmartChatbot\Knowledge\HybridSearcher();
            $results = $searcher->search($query, 5);
            
            if ($allowedSources !== null) {
                $results = array_values(array_filter($results, function($doc) use ($allowedSources) {
                    $metadata = $doc->metadata ?? [];
                    $sourceId = isset($metadata['source_id']) ? (int) $metadata['source_id'] : null;
                    return $sourceId !== null && in_array($sourceId, $allowedSources, true);
                }));
            }
            
            if (!empty($results)) {
                $response = " **Knowledge Base Results:**\n\n";
                foreach ($results as $doc) {
                    $response .= "• " . substr($doc->getContent(), 0, 150) . "...\n";
                }
                return ['type' => 'text', 'message' => $response];
            }
        } catch (\Throwable $e) {
            Logger::warning('Knowledge search failed', ['error' => $e->getMessage()]);
        }
        return ['type' => 'text', 'message' => sprintf(__("No knowledge base articles found for '%s'.", 'quark-agentflow-ai'), $query)];
    }
    
    /**
     * Fallback Handler - Routes through AI Agent with full tool access
     * 
     * Falls back through:
     * 1. Configured agent or ShoppingAgent with tools
     * 2. Simple ProviderBridge AI call
     * 3. Product search as last resort
     */
    private function handleFallback(string $message): array {
        if (!ChatbotConfig::isAIEnabled() && !current_user_can('manage_options')) {
            return $this->simpleProductSearch($message);
        }
        
        try {
            // === PHASE 1: AI Agent with tools ===
            // This will now throw a RuntimeException if no agent/provider is configured
            $agent = $this->resolveAgent($this->agentId);
            
            if ($agent) {
                try {
                    $this->loadHistoryIntoAgent($agent);
                    $result = $this->executeAgentChat($agent, $message, 'shopping_agent');
                    if (!empty($result['message'])) {
                        return $result;
                    }
                } finally {
                    AgentContext::clear();
                }
            }
            
        } catch (\RuntimeException $e) {
            // Configuration/strict errors — return message to user
            return [
                'type' => 'text',
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Logger::warning('AI execution failed completely', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
        
        // Simple fallback
        return [
            'type' => 'text',
            'message' => __("I'm not sure I understood. Please try again or contact support if the issue persists.", 'quark-agentflow-ai'),
        ];
    }
    
    /**
     * Simple product search fallback
     */
    private function simpleProductSearch(string $message): array {
        $products = $this->getProductService()->search($message, 5);
        if (!empty($products)) {
            return [
                'type' => 'products',
                'message' => __("I found these products for you:", 'quark-agentflow-ai'),
                'products' => $products,
            ];
        }
        
        return [
            'type' => 'text',
            'message' => __("I'm not sure I understood. You can search for products, ask for best sellers, or track an order!", 'quark-agentflow-ai'),
        ];
    }
    
    // ========================================================================
    // ANALYTICS
    // ========================================================================
    
    /**
     * Track AI usage for analytics
     * 
     * Properly tracks provider, model, token counts, and cost for analytics dashboard.
     * Token counts are estimated from string lengths when actual usage isn't available.
     * 
     * @param string $source Source identifier (e.g., 'direct_ai', 'shopping_agent')
     * @param string $query The user's query
     * @param string|null $response The AI's response
     * @param int $durationMs Duration in milliseconds (optional)
     */
    private function trackAIUsage(string $source, string $query, ?string $response, int $durationMs = 0): void {
        if (!class_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService')) {
            return;
        }
        
        try {
            $settings = ChatbotConfig::settings();
            $providerName = $settings['ai_provider'] ?? 'unknown';
            $modelId = $settings['model_id'] ?? 'unknown';
            
            // Try to get model from per-provider configs (new format)
            $providerConfigs = $settings['provider_configs'] ?? [];
            $currentConfig = $providerConfigs[$providerName] ?? [];
            if (!empty($currentConfig['model'])) {
                $modelId = $currentConfig['model'];
            }
            
            // Estimate tokens from string lengths
            // Average: ~4 characters per token for English text
            $inputTokens = (int) ceil(strlen($query) / 4);
            $outputTokens = (int) ceil(strlen($response ?? '') / 4);
            
            // Calculate cost (default to 0.0)
            $costUsd = 0.0;
            
            //  Log token usage and cost to debug.log IMMEDIATELY
            error_log(sprintf(
                '[SWC Token Usage] Provider: %s | Model: %s | Input: %d tokens | Output: %d tokens | Duration: %dms | Source: %s',
                $providerName,
                $modelId,
                $inputTokens,
                $outputTokens,
                $durationMs,
                $source
            ));
            
            try {
                // Calculate cost using the pricing system
                $costUsd = \Quarksol\SmartChatbot\Analytics\AnalyticsService::calculateCost(
                    $providerName,
                    $modelId,
                    $inputTokens,
                    $outputTokens
                );
                
                // Get agent context if available
                $agentDbId = null;
                if (class_exists('\Quarksol\SmartChatbot\Services\AgentContext')) {
                    $agentDbId = AgentContext::getAgentDbId();
                }
                
                // Set context for tracking
                \Quarksol\SmartChatbot\Analytics\AnalyticsService::setContext([
                    'agent_db_id' => $agentDbId ?? $this->agentId,
                    'session_id' => $this->sessionId,
                ]);
                
                // Track using the proper trackChat method
                \Quarksol\SmartChatbot\Analytics\AnalyticsService::trackChat(
                    $providerName,
                    $modelId,
                    $inputTokens,
                    $outputTokens,
                    $costUsd,
                    $durationMs,
                    !empty($response), // success
                    null // no error
                );
            } catch (\Throwable $e) {
                // Log analytics failure but don't stop execution
                Logger::error('Analytics calculation/tracking failed', ['error' => $e->getMessage()]);
            }
            
        } catch (\Throwable $e) {
            // Log complete failure
            Logger::error('Token usage logging failed completely', ['error' => $e->getMessage()]);
        }
    }
}
