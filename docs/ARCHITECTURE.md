# QuarksolAIAgent - Complete Architecture & Implementation Guide

> **Document Version:** 1.0  
> **Last Updated:** January 24, 2026  
> **Purpose:** Comprehensive technical documentation for the AI Agent system, covering architecture, implementation patterns, and integration guides.

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture Layers](#architecture-layers)
3. [AI Provider Integration](#ai-provider-integration)
4. [Tool Calling System](#tool-calling-system)
5. [Agent Configuration & Skills](#agent-configuration--skills)
6. [Chat History Management](#chat-history-management)
7. [Observability & Monitoring](#observability--monitoring)
8. [Critical Fixes & Lessons Learned](#critical-fixes--lessons-learned)
9. [Integration Guide for Other Applications](#integration-guide-for-other-applications)

---

## System Overview

QuarksolAIAgent is a WordPress plugin that provides an AI-powered chatbot with:
- **40+ AI provider support** (OpenAI, Azure, Anthropic, Google, OpenRouter, etc.)
- **159+ tools** organized into toolkits for WordPress/WooCommerce management
- **Skill-based architecture** for modular capabilities
- **Multi-agent support** with admin-configurable settings
- **Real-time observability** via Inspector.dev integration

### Core Technology Stack

| Component | Technology |
|-----------|------------|
| AI Framework | [NeuronAI](https://github.com/neuron-ai/neuron-ai) (PHP) |
| Frontend | React + WordPress REST API |
| Database | WordPress Database (custom tables) |
| Streaming | Server-Sent Events (SSE) |
| Monitoring | Inspector.dev AgentMonitoring |

---

## Architecture Layers

```
┌─────────────────────────────────────────────────────────┐
│                    Frontend (React)                      │
│  - ChatWidget, AdminPanel, AgentConfig UI               │
└───────────────────────────┬─────────────────────────────┘
                            │ REST API / SSE
┌───────────────────────────▼─────────────────────────────┐
│              Controllers (REST Endpoints)                │
│  StreamController, AgentController, SessionController    │
└───────────────────────────┬─────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────┐
│                  MessageRouter Service                   │
│  - Routes messages to AI Agent                          │
│  - Loads chat history                                   │
│  - Handles knowledge base context                       │
└───────────────────────────┬─────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────┐
│                    NeuronAgent                           │
│  - Extends NeuronAI\Agent                               │
│  - Manages tools, skills, prompts                       │
│  - Handles tool calling loop                            │
└───────────────────────────┬─────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────┐
│                  ProviderAdapter                         │
│  - Bridges NeuronAI to custom providers                 │
│  - Handles message/tool format conversion               │
│  - Processes streaming responses                        │
└───────────────────────────┬─────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────┐
│              AI Provider (Azure/OpenAI/etc)              │
│  - Actual LLM API calls                                 │
│  - Tool call parsing                                    │
│  - Response streaming                                   │
└─────────────────────────────────────────────────────────┘
```

### Key Files & Their Responsibilities

| File | Purpose |
|------|---------|
| `src/Agent/NeuronAgent.php` | Main agent class, extends NeuronAI |
| `src/Internal/ProviderAdapter.php` | Bridges NeuronAI to custom providers |
| `src/Services/MessageRouter.php` | Routes messages, manages history |
| `src/Api/Controllers/StreamController.php` | SSE streaming endpoint |
| `src/Api/Providers/BaseOpenAICompatible.php` | OpenAI/Azure API handler |
| `src/Config/AgentConfig.php` | Agent configuration storage |
| `src/Models/ChatAgent.php` | Database model for agents |
| `src/Skills/SkillRegistry.php` | Skill discovery and filtering |

---

## AI Provider Integration

### Provider Architecture

The system uses a **bridge pattern** to connect the NeuronAI framework to custom provider implementations:

```
NeuronAgent → ProviderAdapter → BaseOpenAICompatible → Azure/OpenAI API
```

### ProviderAdapter (src/Internal/ProviderAdapter.php)

This is the **critical bridge class** that implements `AIProviderInterface`:

```php
class ProviderAdapter implements AIProviderInterface {
    protected BaseProvider $provider;
    protected array $tools = [];
    protected ?string $systemPrompt = null;
    
    // Message mapping for different message types
    public function messageMapper(): MessageMapperInterface {
        return new class implements MessageMapperInterface {
            public function map(array $messages): array {
                foreach ($messages as $message) {
                    // Handle ToolCallResultMessage 
                    if ($message instanceof ToolCallResultMessage) {
                        foreach ($message->getTools() as $tool) {
                            $mapped[] = [
                                'role' => 'tool',
                                'tool_call_id' => $tool->getCallId(),
                                'content' => $tool->getResult() ?? '{}',
                            ];
                        }
                    }
                    // Handle ToolCallMessage (assistant requesting tools)
                    if ($message instanceof ToolCallMessage) {
                        $toolCalls = [];
                        foreach ($message->getTools() as $tool) {
                            $toolCalls[] = [
                                'id' => $tool->getCallId(),
                                'type' => 'function',
                                'function' => [
                                    'name' => $tool->getName(),
                                    'arguments' => json_encode($tool->getInputs()),
                                ],
                            ];
                        }
                        $mapped[] = [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => $toolCalls,
                        ];
                    }
                    // Standard messages
                    else {
                        $mapped[] = [
                            'role' => $message->getRole(),
                            'content' => $message->getContent(),
                        ];
                    }
                }
                return $mapped;
            }
        };
    }
}
```

> **CRITICAL:** The message mapper MUST handle `ToolCallResultMessage` and `ToolCallMessage` separately. The OpenAI/Azure API expects tool results with `role: 'tool'` and `tool_call_id`.

### Azure OpenAI Integration

Azure uses a different URL format than standard OpenAI:

```
Standard OpenAI:  https://api.openai.com/v1/chat/completions
Azure OpenAI:     https://{resource}.openai.azure.com/openai/deployments/{model}/chat/completions?api-version={version}
```

**URL Construction Logic** (BaseOpenAICompatible.php):

```php
if ($this->settings->openAiUseAzure) {
    $baseUrl = rtrim($this->baseURL, '/');
    
    // Check if user provided full deployment URL or just resource endpoint
    if (str_contains($baseUrl, '/openai/deployments/')) {
        // User provided: https://resource.openai.azure.com/openai/deployments/gpt-4
        $url = $baseUrl . '/chat/completions?api-version=' . $apiVersion;
    } else {
        // User provided: https://resource.openai.azure.com
        $url = $baseUrl . '/openai/deployments/' . $modelId . '/chat/completions?api-version=' . $apiVersion;
    }
    
    // Azure uses api-key header instead of Bearer token
    $headers['api-key'] = $apiKey;
}
```

> **LESSON LEARNED:** Users may provide partial or full deployment URLs. Always detect and handle both cases to avoid duplicate path segments.

### Tool Limit Enforcement

Azure/OpenAI APIs have a **128 tool maximum**:

```php
// In ProviderAdapter.chatAsync()
$toolsToSend = $this->tools;
$maxTools = 128;
if (count($toolsToSend) > $maxTools) {
    $toolsToSend = array_slice($toolsToSend, 0, $maxTools);
}
```

---

## Tool Calling System

### Tool Discovery & Loading

Tools are organized into **Toolkits** in the `/toolkits` directory:

```
toolkits/
├── WordPress/
│   ├── PluginTool.php        # wp_plugins
│   ├── ThemeTool.php         # wp_themes
│   └── UserTool.php          # wp_users
├── WordPressContent/
│   ├── PostCreateTool.php    # wp_create_post
│   ├── PostReadTool.php      # wp_read_posts
│   └── MediaTool.php         # wp_media
├── WooCommerce/
│   ├── ProductTool.php       # wc_products
│   └── OrderTool.php         # wc_orders
└── System/
    └── LoadSkillTool.php     # load_skill
```

**Toolkit Loader** (includes/toolkit-loader.php):

```php
function get_all_toolkit_tools(): array {
    $tools = [];
    $toolkitsPath = SWC_PLUGIN_PATH . 'toolkits';
    
    foreach (glob($toolkitsPath . '/*/*.php') as $file) {
        require_once $file;
        $className = extractClassName($file);
        if (class_exists($className) && is_subclass_of($className, Tool::class)) {
            $tools[] = new $className();
        }
    }
    return $tools;
}
```

### Tool Definition Structure

Each tool extends `NeuronAI\Tools\Tool`:

```php
class PluginTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'wp_plugins',
            description: 'Manage WordPress plugins. List, activate, deactivate.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'action',
                type: PropertyType::STRING,
                description: 'Action: list, get, activate, deactivate',
                required: true
            ),
            new ToolProperty(
                name: 'plugin',
                type: PropertyType::STRING,
                description: 'Plugin slug (e.g., "akismet/akismet.php")',
                required: false
            ),
        ];
    }
    
    public function __invoke(string $action, ?string $plugin = null): string
    {
        switch ($action) {
            case 'list':
                return json_encode(get_plugins());
            case 'activate':
                activate_plugin($plugin);
                return json_encode(['success' => true]);
            // ...
        }
    }
}
```

### Tool Payload Format (OpenAI/Azure)

```json
{
  "type": "function",
  "function": {
    "name": "wp_plugins",
    "description": "Manage WordPress plugins...",
    "parameters": {
      "type": "object",
      "properties": {
        "action": {
          "type": "string",
          "description": "Action: list, get, activate, deactivate"
        },
        "plugin": {
          "type": "string",
          "description": "Plugin slug"
        }
      },
      "required": ["action"]
    }
  }
}
```

### Tool Calling Flow

```
1. User Message → Agent.chat()
2. Agent sends to LLM with tools
3. LLM responds with tool_call (ToolCallMessage)
4. Agent.executeTools() runs the tool
5. Tool returns result → ToolCallResultMessage
6. Agent sends result back to LLM
7. LLM generates final response (AssistantMessage)
8. Response returned to user
```

---

## Agent Configuration & Skills

### AgentConfig Structure

```php
class AgentConfig {
    public string $agentId;
    public string $name;
    public array $enabledToolkits = [];
    public array $enabledTools = [];
    public array $disabledTools = [];
    public array $enabledSkills = [];     // ← Skills filtering
    public array $disabledSkills = [];
    public string $skillMode = 'all';     // 'all', 'selected', 'none'
    public array $promptSections = [];
    public string $welcomeMessage;
    // ...
}
```

### Skills Filtering

Skills are filtered based on agent configuration:

```php
// SkillRegistry::getSummariesForPrompt()
public static function getSummariesForPrompt(?array $enabledSkills = null): string
{
    $skills = self::discover();
    
    // If enabledSkills is empty array, agent has no skills
    if ($enabledSkills !== null && empty($enabledSkills)) {
        return '';
    }
    
    // Filter to only enabled skills
    if ($enabledSkills !== null) {
        $skills = array_filter($skills, function($skill) use ($enabledSkills) {
            return in_array($skill->name, $enabledSkills) || $skill->alwaysOn;
        });
    }
    
    // Build prompt section
    return implode("\n", array_map(fn($s) => $s->getSummary(), $skills));
}
```

### Storage Location Synchronization

> **CRITICAL BUG FIXED:** Skills were saved to `wp_options` but loaded from the `ChatAgent.config` database column.

**Correct Pattern:**
```php
// AgentSkillsController::updateAgentSkills()
$chatAgent = ChatAgent::findBySlug($agentId);

if ($chatAgent && $chatAgent->config) {
    $chatAgent->config->enabledSkills = $data['enabled_skills'];
    $chatAgent->save();  // Saves to database table, not wp_options
}
```

---

## Chat History Management

### Session Storage

Chat sessions are stored in the `swc_chat_sessions` table:

| Column | Type | Purpose |
|--------|------|---------|
| id | INT | Primary key |
| session_id | VARCHAR(36) | UUID for session |
| user_id | INT | WordPress user ID (nullable) |
| agent_db_id | INT | Agent ID |
| messages | JSON | Array of message objects |
| created_at | DATETIME | Session start |
| updated_at | DATETIME | Last activity |

### Message Format

```json
{
  "messages": [
    {"role": "user", "content": "Hello"},
    {"role": "assistant", "content": "Hi! How can I help?"},
    {"role": "user", "content": "Create a post"},
    {"role": "assistant", "content": "Done! Created post #123"}
  ]
}
```

### History Injection (CRITICAL)

The MessageRouter MUST inject history into the agent before calling chat():

```php
// MessageRouter::executeAIAgent()
if (!empty($this->history)) {
    foreach ($this->history as $historyMsg) {
        $role = $historyMsg['role'] ?? 'user';
        $content = $historyMsg['content'] ?? '';
        
        if ($role === 'assistant') {
            $msg = new AssistantMessage($content);
        } else {
            $msg = new UserMessage($content);
        }
        
        // Add to agent's internal chat history
        $agent->addToChatHistory($msg);
    }
}

// NOW call chat with current message
$agent->chat(new UserMessage($message));
```

> **Without this, each message is treated as a new conversation and the AI "forgets" previous context.**

---

## Observability & Monitoring

### Inspector.dev Integration

The system uses NeuronAI's built-in `AgentMonitoring` class:

```php
// NeuronAgent::attachInspector()
protected function attachInspector(): void
{
    $key = '7dcc8875438f987d24380bf62b583c60ab0155b7';
    $_ENV['INSPECTOR_INGESTION_KEY'] = $key;
    
    $monitoring = AgentMonitoring::instance($key);
    $this->observe($monitoring);
}
```

### Events Tracked

- `chat-start` / `chat-stop`
- `inference-start` / `inference-stop`
- `tool-calling` / `tool-called`
- `error`
- Token usage and latency

### Viewing in Inspector Dashboard

1. Visit [app.inspector.dev](https://app.inspector.dev)
2. Select your application
3. View real-time agent execution traces

---

## Critical Fixes & Lessons Learned

### Issue 1: Tools Not Loading

**Symptom:** Agent had 0 tools, couldn't execute any actions

**Root Cause:** `toolkit-loader.php` not included in bootstrap

**Fix:**
```php
// src/bootstrap.php
require_once SWC_PLUGIN_PATH . 'includes/toolkit-loader.php';
```

### Issue 2: Azure "Resource Not Found"

**Symptom:** API returned `{"error": "Resource not found"}`

**Root Cause:** URL was duplicated: `https://.../openai/deployments/gpt-4/openai/deployments/gpt-4/chat/completions`

**Fix:** Detect if base URL already contains deployment path:
```php
if (str_contains($baseUrl, '/openai/deployments/')) {
    $url = $baseUrl . '/chat/completions?api-version=' . $version;
}
```

### Issue 3: "Tool Array Too Long"

**Symptom:** `{"error": "Invalid 'tools': array too long. Expected maximum 128, got 159."}`

**Fix:** Limit tools to 128 max in ProviderAdapter

### Issue 4: "Content Expected String, Got Null"

**Symptom:** Tool executes but result not sent back to API

**Root Cause:** MessageMapper didn't handle `ToolCallResultMessage`

**Fix:** Add proper mapping for tool role messages with `tool_call_id`

### Issue 5: AI Forgets Conversation

**Symptom:** AI says "I don't have the previous instruction"

**Root Cause:** History stored but never injected into agent

**Fix:** Loop through history and call `$agent->addToChatHistory()` before chat

### Issue 6: Skills Not Filtering

**Symptom:** All 36 skills shown regardless of UI selection

**Root Cause:** Skills saved to `wp_options`, loaded from database table

**Fix:** Update `AgentSkillsController` to save to `ChatAgent.save()`

---

## Integration Guide for Other Applications

### Step 1: Install Dependencies

```bash
composer require neuron-core/neuron-ai
```

### Step 2: Create Provider Adapter

Implement `AIProviderInterface` to bridge to your LLM:

```php
class MyProviderAdapter implements AIProviderInterface {
    public function messageMapper(): MessageMapperInterface { ... }
    public function toolPayloadMapper(): ToolPayloadMapperInterface { ... }
    public function chatAsync(array $messages): PromiseInterface { ... }
}
```

### Step 3: Create Agent

```php
class MyAgent extends Agent {
    public function provider(): AIProviderInterface {
        return new MyProviderAdapter($this->apiKey);
    }
    
    public function instructions(): string {
        return "You are a helpful assistant with access to tools.";
    }
    
    public function tools(): array {
        return [
            new MyCustomTool(),
            // ...
        ];
    }
}
```

### Step 4: Handle Chat with History

```php
$agent = new MyAgent();

// Load prior messages
foreach ($conversationHistory as $msg) {
    $agent->addToChatHistory(
        $msg['role'] === 'assistant' 
            ? new AssistantMessage($msg['content'])
            : new UserMessage($msg['content'])
    );
}

// Process new message
$response = $agent->chat(new UserMessage($userInput));
echo $response->getContent();

// Save to history
$conversationHistory[] = ['role' => 'user', 'content' => $userInput];
$conversationHistory[] = ['role' => 'assistant', 'content' => $response->getContent()];
```

### Step 5: Add Observability

```php
$_ENV['INSPECTOR_INGESTION_KEY'] = 'your-key-here';
$agent->observe(AgentMonitoring::instance());
```

---

## Appendix: File Reference

| Path | Description |
|------|-------------|
| `src/Agent/NeuronAgent.php` | Main agent class |
| `src/Internal/ProviderAdapter.php` | Provider bridge |
| `src/Services/MessageRouter.php` | Message routing + history |
| `src/Api/Providers/BaseOpenAICompatible.php` | OpenAI/Azure streaming |
| `src/Config/AgentConfig.php` | Agent configuration |
| `src/Models/ChatAgent.php` | Database agent model |
| `src/Models/ChatSession.php` | Session management |
| `src/Skills/SkillRegistry.php` | Skill discovery |
| `src/Api/AgentSkillsController.php` | Skills API endpoints |
| `includes/toolkit-loader.php` | Tool auto-loading |
| `includes/debug-tool-calls.php` | Debug testing page |
| `toolkits/*/` | Tool implementations |

---

*This documentation was generated based on debugging and implementation work performed on January 24, 2026.*
