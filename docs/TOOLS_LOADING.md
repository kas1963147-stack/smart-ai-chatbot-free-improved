# Tools Loading Architecture

> **Status**: ✅ Fully Functional  
> **Last Updated**: January 2025

This document explains how tool configuration flows from the admin UI to agent execution.

---

## Overview

The plugin uses a **two-level filtering system** for tools:

1. **Toolkits** (coarse-grained) - Groups of related tools (e.g., WooCommerce, WordPress)
2. **Tools** (fine-grained) - Individual tools within toolkits

```
Admin UI → AgentConfig → ToolRegistry → Agent.tools()
```

---

## Architecture Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN UI (React)                         │
│  ToolkitManager.jsx                                         │
│  ├── enabledToolkits[] ← Toolkit toggles                   │
│  └── disabledTools[]   ← Individual tool toggles           │
└───────────────────────────┬─────────────────────────────────┘
                            │ Save to API
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    DATABASE                                  │
│  wp_swc_agents.config (JSON column)                         │
│  {                                                          │
│    "enabled_toolkits": ["WooCommerce", "WordPress"],        │
│    "disabled_tools": ["woo_settings", "wp_user_delete"]    │
│  }                                                          │
└───────────────────────────┬─────────────────────────────────┘
                            │ ChatAgent::find()
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    AgentConfig.php                          │
│  isToolkitEnabled(id) → Check if toolkit in whitelist      │
│  isToolEnabled(id)    → Check if tool NOT in blacklist     │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    ToolRegistry.php                         │
│  getToolsForConfig($config)                                 │
│  ├── Get all tools from toolkits                           │
│  ├── Filter by toolkit enabled                             │
│  └── Filter by tool not disabled                           │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    NeuronAgent.php                          │
│  tools()                                                    │
│  ├── SystemTools (always included, cannot be disabled)     │
│  └── getFilteredTools() → ToolRegistry::getToolsForConfig  │
└─────────────────────────────────────────────────────────────┘
```

---

## Key Components

### 1. ToolkitManager.jsx (UI)

Located in `assets/admin-react/components/ToolkitManager.jsx`

```javascript
// Toolkit toggle - whitelist approach
const toggleToolkit = (toolkitId, enabled) => {
    // Updates enabledToolkits array
    onToolkitsChange(newEnabled);
};

// Tool toggle - blacklist approach
const toggleTool = (toolId, enabled) => {
    // Updates disabledTools array
    onToolsChange(newDisabled);
};
```

### 2. AgentConfig.php (Configuration)

Located in `src/Config/AgentConfig.php`

```php
// Toolkit check (whitelist logic)
public function isToolkitEnabled(string $toolkitId): bool {
    if (empty($this->enabledToolkits)) {
        return true;  // Empty = ALL enabled
    }
    return in_array($toolkitId, $this->enabledToolkits);
}

// Tool check (blacklist logic)
public function isToolEnabled(string $toolId): bool {
    if (in_array($toolId, $this->disabledTools)) {
        return false;  // Explicitly disabled
    }
    return true;
}
```

### 3. ToolRegistry.php (Filtering)

Located in `src/Config/ToolRegistry.php`

```php
public static function getToolsForConfig(AgentConfig $config): array {
    $allTools = get_all_toolkit_tools();
    
    return array_filter($allTools, function($tool) use ($config) {
        $toolId = $tool->getName();
        $toolInfo = self::getToolInfo($toolId);
        
        // Check toolkit enabled
        if (!$config->isToolkitEnabled($toolInfo['toolkit_id'])) {
            return false;
        }
        
        // Check tool not disabled
        return $config->isToolEnabled($toolId);
    });
}
```

### 4. NeuronAgent.php (Agent)

Located in `src/Agent/NeuronAgent.php`

```php
protected function tools(): array {
    $systemTools = $this->getSystemTools();      // Always included
    $configurableTools = $this->getFilteredTools(); // From config
    return array_merge($systemTools, $configurableTools);
}

protected function getFilteredTools(): array {
    return ToolRegistry::getToolsForConfig($this->config);
}
```

---

## Filtering Logic

### Toolkits (Whitelist)

| `enabledToolkits` Value | Behavior |
|-------------------------|----------|
| `[]` (empty) | ALL toolkits enabled |
| `["WooCommerce"]` | ONLY WooCommerce enabled |
| `["WooCommerce", "WordPress"]` | Only listed toolkits enabled |

### Tools (Blacklist)

| `disabledTools` Value | Behavior |
|-----------------------|----------|
| `[]` (empty) | ALL tools enabled |
| `["woo_settings"]` | All tools EXCEPT woo_settings |

### System Tools (Protected)

System tools from `SystemToolRegistry` are **always included** and cannot be disabled:
- Task management tools
- Session tools
- Core utilities

---

## Usage in Different Contexts

### Frontend Chat (StreamController → MessageRouter)

```php
// MessageRouter loads agent and uses its configured tools
$agent = ChatAgent::find($agentId)->toNeuronAgent();
$response = $agent->chat($message);  // Uses filtered tools
```

### Workspace (WorkspaceService)

```php
// WorkspaceService also uses ToolRegistry::getToolsForConfig
$chatAgent = ChatAgent::findBySlug($agentId);
$tools = ToolRegistry::getToolsForConfig($chatAgent->config);
```

---

## Adding New Toolkits

1. Create toolkit class in `toolkits/YourToolkit/`
2. Register in `ToolRegistry::getToolkits()`
3. UI will automatically show the toolkit in ToolkitManager

---

## Verification Checklist

- [x] UI toggles update `enabled_toolkits` and `disabled_tools`
- [x] Config is saved to database correctly
- [x] `AgentConfig` loads and filters correctly
- [x] `ToolRegistry::getToolsForConfig()` applies both filters
- [x] `NeuronAgent.tools()` returns filtered + system tools
- [x] System tools cannot be disabled
