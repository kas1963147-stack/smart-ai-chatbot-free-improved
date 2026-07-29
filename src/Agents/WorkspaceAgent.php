<?php
declare(strict_types=1);
/**
 * Workspace Agent
 * 
 * A NeuronAI Agent for the admin workspace that properly implements
 * the agentic loop: LLM → tool calls → execute → feed results back → repeat.
 * 
 * @package Quarksol\SmartChatbot\Agents
 */

namespace Quarksol\SmartChatbot\Agents;

use NeuronAI\Agent;
use NeuronAI\AgentInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\ToolInterface;
use Quarksol\SmartChatbot\Internal\ProviderAdapter;
use Quarksol\SmartChatbot\Types\ProviderSettings;
use Quarksol\SmartChatbot\Services\Logger;
use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Config\PromptBuilder;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WorkspaceAgent extends NeuronAI Agent to provide proper agentic functionality
 * with automatic tool execution and result feedback loop.
 */
class WorkspaceAgent extends Agent
{
    /**
     * Agent ID (slug) from database
     */
    protected string $agentId;
    
    /**
     * Pre-built tools array
     * @var ToolInterface[]
     */
    protected array $agentTools = [];
    
    /**
     * Custom system instructions
     */
    protected string $customInstructions = '';
    
    /**
     * Chat agent config from database
     */
    protected ?ChatAgent $chatAgent = null;
    
    /**
     * Constructor
     * 
     * @param string $agentId The agent slug (e.g., 'content-editor')
     * @param array $tools Array of NeuronAI Tool instances
     * @param string|null $customInstructions Optional custom system prompt
     */
    public function __construct(string $agentId, array $tools = [], ?string $customInstructions = null)
    {
        $this->agentId = $agentId;
        $this->agentTools = $tools;
        
        if ($customInstructions !== null) {
            $this->customInstructions = $customInstructions;
        }
        
        // Load agent from database if exists
        if ($agentId !== 'none') {
            $this->chatAgent = ChatAgent::findBySlug($agentId);
        }
        
        Logger::info('WorkspaceAgent initialized', [
            'agent_id' => $agentId,
            'tools_count' => count($tools),
            'has_db_agent' => $this->chatAgent !== null,
        ]);
    }
    
    /**
     * Build the AI provider from WordPress settings
     * 
     * @return AIProviderInterface
     */
    protected function provider(): AIProviderInterface
    {
        $settings = get_option('swc_chatbot_settings', []);
        
        $providerSettings = new ProviderSettings(
            provider: $settings['ai_provider'] ?? 'openai',
            apiKey: $settings['ai_api_key'] ?? '',
            model: $settings['ai_model'] ?? 'gpt-4o-mini',
            baseUrl: $settings['ai_base_url'] ?? null,
            temperature: null  // Let model use default to avoid temperature errors
        );
        
        Logger::info('WorkspaceAgent provider', [
            'provider' => $providerSettings->provider,
            'model' => $providerSettings->model,
        ]);
        
        return ProviderAdapter::fromSettings($providerSettings);
    }
    
    /**
     * Return the system instructions for this agent
     * 
     * @return string
     */
    public function instructions(): string
    {
        // If custom instructions were provided, use them
        if (!empty($this->customInstructions)) {
            return $this->customInstructions;
        }
        
        // If 'none' mode, return minimal instructions
        if ($this->agentId === 'none') {
            return $this->buildRawLlmInstructions();
        }
        
        // Build from database agent config
        if ($this->chatAgent && $this->chatAgent->config) {
            try {
                $builder = PromptBuilder::forAgent($this->chatAgent->config, $this->agentTools);
                $prompt = $builder->build([
                    'site_name' => get_bloginfo('name'),
                    'user_name' => wp_get_current_user()->display_name,
                ]);
                
                // Add knowledge source catalog (names only — on-demand loading)
                $sourceCatalog = $this->getKnowledgeSourceCatalog($this->chatAgent->config);
                if (!empty($sourceCatalog)) {
                    $prompt .= "\n\n" . $sourceCatalog;
                }
                
                return $prompt;
            } catch (\Throwable $e) {
                Logger::error('Failed to build agent prompt', ['error' => $e->getMessage()]);
            }
        }
        
        // Fallback to basic instructions
        return $this->buildDefaultInstructions();
    }
    
    /**
     * Return the tools available to this agent
     * 
     * @return ToolInterface[]
     */
    protected function tools(): array
    {
        return $this->agentTools;
    }
    
    /**
     * Build default instructions when no agent config available
     */
    protected function buildDefaultInstructions(): string
    {
        $siteName = get_bloginfo('name');
        $userName = wp_get_current_user()->display_name;
        $toolCount = count($this->agentTools);
        
        return "You are an AI assistant for \"{$siteName}\".

## Context
- Current admin: {$userName}
- Tools available: {$toolCount}

## Rules
1. Use the provided tools to accomplish tasks
2. Always verify results from tool responses
3. Be concise and helpful
4. If a tool fails, explain what happened

NEVER fabricate results. Only report what tools actually return.
";
    }
    
    /**
     * Build minimal instructions for raw LLM mode
     */
    protected function buildRawLlmInstructions(): string
    {
        $siteName = get_bloginfo('name');
        
        return "You are a helpful AI assistant for \"{$siteName}\".
Be concise, helpful, and accurate.
";
    }
    
    /**
     * Static factory method for easier creation
     */
    public static function create(string $agentId, array $tools = [], ?string $instructions = null): self
    {
        return new self($agentId, $tools, $instructions);
    }
    
    /**
     * Get the agent ID
     */
    public function getAgentId(): string
    {
        return $this->agentId;
    }
    
    /**
     * Get compact catalog of knowledge source names for prompt
     */
    protected function getKnowledgeSourceCatalog(\Quarksol\SmartChatbot\Config\AgentConfig $config): string
    {
        if (!$config->knowledgeEnabled) {
            return '';
        }
        
        $allowedSources = $config->knowledgeSourcesConfigured 
            ? $config->enabledKnowledgeSources 
            : null;
        
        $allSources = \Quarksol\SmartChatbot\Knowledge\KnowledgeSource::all();
        
        if ($allowedSources !== null) {
            $allSources = array_filter($allSources, fn($s) => in_array($s->id, $allowedSources));
        }
        
        if (empty($allSources)) {
            return '';
        }
        
        $lines = ["## Available Knowledge Sources\n"];
        $lines[] = "You can load detailed knowledge using the `load_knowledge_source` tool when a user's question relates to one of these topics.\n";
        
        foreach ($allSources as $source) {
            $type = str_replace('_', ' ', $source->sourceType);
            $lines[] = "- **{$source->name}** ({$type})";
        }
        
        $lines[] = "\n**IMPORTANT**: When a user asks about a topic that matches one of these knowledge sources, ALWAYS call `load_knowledge_source(\"source name\")` FIRST to get the accurate information before answering.";
        
        return implode("\n", $lines);
    }
}
