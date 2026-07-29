# NeuronAI Agentic Framework

> **Foundation Documentation** - How the AI agent system works

---

## What Makes This Plugin Special

This plugin uses **NeuronAI**, a PHP agentic framework that enables AI agents to:
- 🔧 **Use tools** - Execute WordPress actions (create posts, manage products, etc.)
- 🔄 **Maintain context** - Feed tool results back to the AI for follow-up
- 🎯 **Complete multi-step tasks** - Autonomously work through complex requests

---

## The Agentic Loop

The core pattern that makes agents intelligent:

```
User: "Create a blog post about cats and add it to the Animals category"
    ↓
Agent receives message + available tools
    ↓
LLM decides: "I need to call wp_create_post"
    ↓
Tool executes → Returns: "Post ID 123 created"
    ↓
Result sent back to LLM
    ↓
LLM decides: "Now I need to call wp_taxonomy to add category"
    ↓
Tool executes → Returns: "Category added"
    ↓
Result sent back to LLM
    ↓
LLM responds: "Done! I created the post and added it to Animals category."
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        User Request                          │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                     WorkspaceAgent                           │
│  ├── provider()      → ProviderAdapter (OpenAI, Claude...)  │
│  ├── instructions()  → System prompt with context            │
│  └── tools()         → 128+ WordPress/WooCommerce tools      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    Agent::chat() Loop                        │
│  1. Send to LLM with tools                                   │
│  2. If tool_calls → executeTools() → get results             │
│  3. Send results back to LLM                                 │
│  4. Repeat until text response                               │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                     Final Response                           │
└─────────────────────────────────────────────────────────────┘
```

---

## Key Files

| File | Purpose |
|------|---------|
| `src/Agents/WorkspaceAgent.php` | NeuronAI Agent for admin workspace |
| `src/Services/WorkspaceService.php` | Orchestrates workspace chat |
| `src/Internal/ProviderAdapter.php` | Bridges providers to NeuronAI |
| `src/Config/ToolRegistry.php` | Registry of 128+ tools |
| `vendor/neuron-core/neuron-ai/` | NeuronAI framework core |

---

## Creating a New Agent

```php
use NeuronAI\Agent;

class MyCustomAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        // Return your AI provider (OpenAI, Claude, etc.)
        return ProviderAdapter::fromSettings($settings);
    }
    
    public function instructions(): string
    {
        // Return system prompt
        return "You are a helpful assistant...";
    }
    
    protected function tools(): array
    {
        // Return array of ToolInterface objects
        return [
            new MyCustomTool(),
            new AnotherTool(),
        ];
    }
}

// Usage
$agent = new MyCustomAgent();
$response = $agent->chat("Create a blog post about cats");
echo $response->getContent();  // Final text response after all tools executed
```

---

## Tool Execution Flow (NeuronAI Internals)

From `vendor/neuron-core/neuron-ai/src/HandleChat.php`:

```php
public function chatAsync(Message|array $messages): PromiseInterface
{
    // ... setup ...
    
    return $this->resolveProvider()
        ->setTools($tools)
        ->chatAsync($messages)
        ->then(function (Message $response) {
            
            // THE AGENTIC LOOP
            if ($response instanceof ToolCallMessage) {
                $toolCallResult = $this->executeTools($response);
                return self::chatAsync($toolCallResult);  // RECURSIVE!
            }
            
            return $response;  // Final text response
        });
}
```

---

## Debug Logging

When testing agents, look for these log entries:

```
[WorkspaceAgent] initialized
[WorkspaceAgent::chat] starting with agentic loop
[tool-calling] wp_create_post
[tool-called] wp_create_post - success
[WorkspaceAgent::chat] completed
```

---

## Common Issues

### Tools Not Executing
**Cause**: Calling `ProviderBridge::chatWithTools()` directly instead of `Agent::chat()`
**Solution**: Always use the Agent class which has the agentic loop

### "Too many tools" Error
**Cause**: API limit is 128 tools
**Solution**: Configure agent with fewer toolkits in admin

### Model Outputs JSON Instead of Calling Tools
**Cause**: Model doesn't support native function calling
**Solution**: Use a supported model (GPT-4o, Claude 3.5, etc.)

---

## Related Documentation

- [ARCHITECTURE.md](./ARCHITECTURE.md) - Overall system architecture
- [TOOLS_LOADING.md](./TOOLS_LOADING.md) - How tools are loaded and registered
- [DEVELOPER_REFERENCE.md](./DEVELOPER_REFERENCE.md) - API reference
