# Smart AI Chatbot

A powerful AI-powered chatbot for WooCommerce stores, featuring multi-agent orchestration, 40+ AI provider support, and comprehensive analytics.

## Features

- 🤖 **Multi-Agent System** - Deploy specialized agents for shopping, support, and custom tasks
- 🛒 **WooCommerce Integration** - 22 shopping tools for products, cart, orders, and more
- 🧠 **RAG Knowledge Base** - Vector search with hybrid lexical/semantic retrieval
- 📊 **Analytics Dashboard** - Track costs, usage, and performance metrics
- 🔄 **Real-time Streaming** - Progressive response rendering with SSE
- 🎨 **Customizable UI** - Template gallery with drag-and-drop customization
- ⏰ **Task Scheduling** - Background agent tasks with cron/Action Scheduler

## Requirements

- WordPress 6.0+
- PHP 8.1+
- WooCommerce 7.0+ (optional, for e-commerce features)
- Composer

## Installation

1. Clone into `wp-content/plugins/`:
   ```bash
   git clone [repo-url] smart-ai-chatbot
   cd smart-ai-chatbot
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   npm run build
   ```

3. Activate plugin in WordPress admin.

4. Configure AI provider in **Smart Chatbot → Settings**.

## Supported AI Providers

| Provider | Models | Notes |
|----------|--------|-------|
| OpenAI | GPT-4o, o3-mini, o1 | Full support including reasoning |
| Anthropic | Claude 3.5 Sonnet/Haiku | Extended thinking support |
| Google Gemini | Gemini 2.0 Flash | Native tool calling |
| DeepSeek | V3, R1 Reasoner | Cost-effective |
| Groq | Llama, Mixtral | Ultra-fast inference |
| OpenRouter | 200+ models | Multi-provider gateway |
| Ollama | Local models | Self-hosted |
| + 30 more | Various | See provider list |

## Quick Start

```php
// Create a shopping agent
$agent = \App\Agent\ShoppingAgent::configured();

// Chat with the agent
$response = $agent->chat(new UserMessage("Find me wireless headphones under $100"));
```

## Documentation

- [API Reference](docs/api.md)
- [Agent Configuration](docs/agents.md)
- [Knowledge Base](docs/knowledge.md)
- [Analytics](docs/analytics.md)

## License

GPL v2 or later
