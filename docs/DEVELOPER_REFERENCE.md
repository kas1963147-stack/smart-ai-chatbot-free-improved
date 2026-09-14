# Developer Quick Reference

## Hook Reference

### Actions

```php
// After chatbot message processed
do_action('swc_message_processed', $message, $response, $session_id);

// After agent resolved
do_action('swc_agent_resolved', $agent_id, $context);

// Before AI request
do_action('swc_before_ai_request', $message, $provider);

// After AI response
do_action('swc_after_ai_response', $response, $tokens_used);

// Knowledge indexed
do_action('swc_knowledge_indexed', $document_id, $source_type);

// Task executed
do_action('swc_task_executed', $task_id, $result);
```

### Filters

```php
// Modify system prompt
add_filter('swc_system_prompt', function($prompt, $agent_id) {
    return $prompt . "\n\nCustom instructions...";
}, 10, 2);

// Modify AI response before sending
add_filter('swc_ai_response', function($response, $message) {
    return $response;
}, 10, 2);

// Customize provider settings
add_filter('swc_provider_settings', function($settings, $provider_name) {
    return $settings;
}, 10, 2);

// Filter available tools for agent
add_filter('swc_agent_tools', function($tools, $agent_id) {
    return $tools;
}, 10, 2);

// Modify RAG context
add_filter('swc_rag_context', function($context, $query) {
    return $context;
}, 10, 2);
```

---

## Adding a Custom Provider

### 1. Create Provider Class

```php
// src/Api/Providers/MyProvider.php
namespace App\Api\Providers;

class MyProvider extends BaseOpenAICompatible {
    protected string $name = 'My Provider';
    
    protected function getDefaultBaseUrl(): string {
        return 'https://api.myprovider.com/v1';
    }
    
    protected function getDefaultModel(): string {
        return 'my-model-name';
    }
}
```

### 2. Register in Factory

```php
// src/Api/index.php
case ProviderName::MY_PROVIDER:
    return new MyProvider($settings);
```

### 3. Add to ProviderName

```php
// src/types/ProviderName.php
const MY_PROVIDER = 'my_provider';
```

---

## Adding a Custom Tool

### 1. Create Tool Class

```php
// toolkits/MyDomain/MyTool.php
namespace SWC\Toolkits\MyDomain;

class MyTool implements \App\Modules\ToolInterface {
    public function getName(): string {
        return 'my_custom_tool';
    }
    
    public function getDescription(): string {
        return 'Does something amazing';
    }
    
    public function getParameters(): array {
        return [
            'param1' => [
                'type' => 'string',
                'description' => 'First parameter',
                'required' => true
            ]
        ];
    }
    
    public function execute(array $params): array {
        // Tool logic here
        return ['success' => true, 'data' => $result];
    }
}
```

### 2. Register in Loader

```php
// toolkits/loader.php
$registry->register(new MyDomain\MyTool());
```

---

## Adding a Custom Skill

### 1. Create SKILL.md

```markdown
---
name: my_skill
description: A custom skill that does X
category: custom
---

## Instructions

Tell the AI what to do when this skill is active.

## When to Use

- Scenario 1
- Scenario 2

## Tools Available

- `my_custom_tool` - Description

## Examples

User: "Do the thing"
AI: [Uses my_custom_tool to accomplish the task]
```

### 2. Place in skills directory

```
skills/custom/my-skill/SKILL.md
```

---

## REST API Quick Reference

### Authentication

All admin endpoints require WordPress authentication with `manage_options` capability.

### Common Headers

```http
Content-Type: application/json
X-WP-Nonce: {wp_nonce}
```

### Chat Endpoints

```bash
# Send message
curl -X POST /wp-json/swc-chatbot/v1/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello", "session_id": "abc123"}'

# Stream response
curl -X POST /wp-json/swc-chatbot/v1/chat/stream \
  -H "Accept: text/event-stream" \
  -d '{"message": "Hello"}'
```

### Agent Endpoints

```bash
# List agents
curl /wp-json/swc-chatbot/v1/agents

# Create agent
curl -X POST /wp-json/swc-chatbot/v1/agents \
  -d '{"name": "My Agent", "config": {...}}'

# Update agent
curl -X PUT /wp-json/swc-chatbot/v1/agents/1 \
  -d '{"name": "Updated Name"}'
```

### Knowledge Endpoints

```bash
# Add document
curl -X POST /wp-json/swc-chatbot/v1/knowledge \
  -d '{"title": "FAQ", "content": "...", "source_type": "manual"}'

# Trigger indexing
curl -X POST /wp-json/swc-chatbot/v1/knowledge/index

# Get stats
curl /wp-json/swc-chatbot/v1/knowledge/stats
```

---

## Database Queries

### Get Active Agent for Page

```php
global $wpdb;
$table = $wpdb->prefix . 'swc_assignments';

$agent_id = $wpdb->get_var($wpdb->prepare(
    "SELECT agent_id FROM {$table} 
     WHERE location_type = 'page' AND location_id = %s
     ORDER BY priority DESC LIMIT 1",
    $page_id
));
```

### Get Session Messages

```php
$sessions_table = $wpdb->prefix . 'swc_sessions';

$session = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$sessions_table} WHERE session_id = %s",
    $session_uuid
));

$messages = json_decode($session->messages, true);
```

### Analytics Query

```php
$analytics = new \App\Analytics\AnalyticsRepository();
$summary = $analytics->getSummary([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31'
]);
```

---

## Testing

### Run PHPUnit Tests

```bash
cd /path/to/plugin
./vendor/bin/phpunit
```

### Test Configuration

```xml
<!-- phpunit.xml -->
<testsuites>
    <testsuite name="Unit">
        <directory>tests/</directory>
    </testsuite>
</testsuites>
```

### E2E Tests

Located in `tests/e2e/`:
- API endpoint testing
- Provider connection tests
- Security validation

---

## Build Commands

### Frontend Build

```bash
cd assets/admin-react
npm install
npm run build
```

### Development Watch

```bash
npm run start
```

### Create Distribution

```bash
./build_plugin.sh
# Creates smart-ai-chatbot.zip
```

---

## Configuration Constants

Define in `wp-config.php`:

```php
// Enable debug mode
define('SWC_CHATBOT_DEBUG', true);

// Custom log path
define('SWC_LOG_PATH', '/path/to/logs/');

// Disable caching
define('SWC_DISABLE_CACHE', true);

// Rate limit override
define('SWC_RATE_LIMIT', 100); // requests per minute
```

---

## Common Patterns

### Getting Current Agent

```php
$resolver = new \App\Services\AgentResolver();
$agent = $resolver->resolve(get_the_ID(), get_post_type());
```

### Using RAG

```php
$rag = new \App\Knowledge\RAGRetriever();
$context = $rag->getContext('user question here', 5);
```

### Making AI Request

```php
use App\Bridge\ProviderBridge;

$bridge = new ProviderBridge();
$response = $bridge->chat($message, $systemPrompt, $context);
```

### Logging

```php
$logger = new \App\Services\Logger();
$logger->info('Something happened', ['key' => 'value']);
$logger->error('Something failed', ['exception' => $e]);
```

---

## File Locations

| Purpose | Location |
|---------|----------|
| Main plugin file | `agentflow-ai.php` |
| Frontend chatbot class | `includes/class-chatbot.php` |
| API Controllers | `src/Api/Controllers/` |
| AI Providers | `src/Api/Providers/` |
| Business Services | `src/Services/` |
| Database Models | `src/Models/` |
| Knowledge/RAG | `src/Knowledge/` |
| Toolkits | `toolkits/` |
| Skills | `skills/` |
| React Admin | `assets/admin-react/` |
| Frontend Assets | `assets/css/`, `assets/js/` |
| Tests | `tests/` |
| Logs | `logs/` |
