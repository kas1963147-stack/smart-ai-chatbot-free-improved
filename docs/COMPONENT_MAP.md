# Component Map

## Visual Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      SMART AI Chatbot                               │
│                         Component Architecture                               │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              PRESENTATION LAYER                              │
├─────────────────────────────┬───────────────────────────────────────────────┤
│     FRONTEND WIDGET         │              ADMIN UI (React)                  │
│  ┌─────────────────────┐    │  ┌─────────────────────────────────────────┐  │
│  │ assets/css/         │    │  │ assets/admin-react/                     │  │
│  │  └─ chatbot.css     │    │  │  ├─ App.jsx (Main Router)              │  │
│  │                     │    │  │  ├─ components/                         │  │
│  │ assets/js/          │    │  │  │  ├─ AgentEditor.jsx                 │  │
│  │  └─ chatbot.js      │    │  │  │  ├─ ToolkitManager.jsx              │  │
│  │                     │    │  │  │  ├─ analytics/ (7 files)            │  │
│  │ includes/           │    │  │  │  ├─ appearance/ (6 files)           │  │
│  │  └─ class-chatbot   │    │  │  │  ├─ knowledge/ (6 files)            │  │
│  │     (render_chatbot)│    │  │  │  ├─ skills/ (17 files)              │  │
│  └─────────────────────┘    │  │  │  └─ tasks/ (5 files)                │  │
│                             │  │  └─ styles/ (16 files)                  │  │
│                             │  └─────────────────────────────────────────┘  │
└─────────────────────────────┴───────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              API LAYER                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│  src/Api/Controllers/                                                        │
│  ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐             │
│  │ ChatController   │ │ AgentController  │ │SettingsController│             │
│  │ - chat()         │ │ - list()         │ │ - get()          │             │
│  │ - stream()       │ │ - create()       │ │ - update()       │             │
│  │ - sessions()     │ │ - update()       │ │ - providers()    │             │
│  └──────────────────┘ │ - delete()       │ │ - testConn()     │             │
│                       └──────────────────┘ └──────────────────┘             │
│  ┌──────────────────┐ ┌──────────────────┐                                  │
│  │ SessionController│ │ StreamController │                                  │
│  │ - get()          │ │ - handleStream() │                                  │
│  │ - history()      │ │ - sse()          │                                  │
│  └──────────────────┘ └──────────────────┘                                  │
│                                                                              │
│  src/Api/                                                                    │
│  ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐             │
│  │ SkillsController │ │ TasksApiCtrl     │ │ AgentSkillsCtrl  │             │
│  │ (25KB)           │ │ (17KB)           │ │ (6KB)            │             │
│  └──────────────────┘ └──────────────────┘ └──────────────────┘             │
│                                                                              │
│  src/Knowledge/KnowledgeController.php (25KB) - REST for Knowledge Base     │
│  src/Analytics/AnalyticsEndpoint.php (10KB) - REST for Analytics            │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              SERVICE LAYER                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  src/Services/                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ MessageRouter (8KB)        │ Routes messages by intent              │    │
│  │  - route()                 │ Detects: product, order, knowledge,    │    │
│  │  - handleProductSearch()   │ fallback intents                       │    │
│  │  - handleFallback()        │                                        │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ AgentResolver (12KB)       │ Priority-based agent resolution        │    │
│  │  - resolve()               │ Page → PostType → Global → Default     │    │
│  │  - getActiveAgent()        │                                        │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ OrchestratorService (9KB)  │ Multi-agent coordination               │    │
│  │  - orchestrate()           │ Manages agent handoffs                 │    │
│  │  - delegate()              │                                        │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ ProductService (11KB)      │ WooCommerce product operations         │    │
│  │  - search()                │ Search, filter, recommend              │    │
│  │  - getRecommendations()    │                                        │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐   │
│  │ Logger        │ │ ErrorHandler  │ │ RateLimiter   │ │ HandoffService│   │
│  │ (8KB)         │ │ (6KB)         │ │ (8KB)         │ │ (5KB)         │   │
│  └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              AI PROVIDER LAYER                               │
├─────────────────────────────────────────────────────────────────────────────┤
│  src/Bridge/ProviderBridge.php - Unified Entry Point                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                      src/Api/Providers/ (42 files)                   │    │
│  │                                                                      │    │
│  │  BaseProvider (abstract)                                             │    │
│  │       │                                                              │    │
│  │       ├── BaseOpenAICompatible                                       │    │
│  │       │   ├── OpenAI, Groq, DeepSeek, Mistral, XAI                   │    │
│  │       │   ├── Fireworks, Cerebras, SambaNova                         │    │
│  │       │   ├── LMStudio, Ollama (local)                               │    │
│  │       │   └── 20+ more...                                            │    │
│  │       │                                                              │    │
│  │       ├── Anthropic (custom Claude implementation)                   │    │
│  │       ├── Gemini (Google's format)                                   │    │
│  │       ├── Bedrock (AWS SDK)                                          │    │
│  │       └── OpenRouter (200+ model router)                             │    │
│  │                                                                      │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│  src/Api/index.php - buildApiHandler() factory function                     │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              KNOWLEDGE LAYER (RAG)                           │
├─────────────────────────────────────────────────────────────────────────────┤
│  src/Knowledge/ (21 files)                                                   │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                         INDEXING PIPELINE                             │   │
│  │  KnowledgeSource → KnowledgeIndexer → EmbeddingsService               │   │
│  │                           ↓                                           │   │
│  │                   WordPressVectorStore                                │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                         RETRIEVAL PIPELINE                            │   │
│  │              User Query                                               │   │
│  │                  ↓                                                    │   │
│  │  RAGRetriever → EmbeddingsService → WordPressVectorStore              │   │
│  │                  ↓                                                    │   │
│  │       HybridSearcher (semantic + lexical fusion)                      │   │
│  │                  ↓                                                    │   │
│  │       KnowledgeAugmenter → Context for AI                             │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│  Supporting: DocumentRegistry, FileUploadHandler, WordPressDataLoader,      │
│              ContentGapAnalyzer, ConversationLearner, KnowledgeAnalytics    │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              TOOLS & SKILLS                                  │
├─────────────────────────────┬───────────────────────────────────────────────┤
│   TOOLKITS (15 domains)     │              SKILLS (9 categories)            │
│  toolkits/                  │  skills/                                      │
│  ├─ WooCommerce/ (23 tools) │  ├─ woocommerce/ (11 skills)                 │
│  ├─ WordPress/ (14 tools)   │  ├─ admin/ (8 skills)                        │
│  ├─ WordPressContent/ (14)  │  ├─ content/ (5 skills)                      │
│  ├─ Security/ (11 tools)    │  ├─ marketing/ (3 skills)                    │
│  ├─ SEO/ (11 tools)         │  ├─ seo/ (2 skills)                          │
│  ├─ Performance/ (11 tools) │  ├─ support/ (1 skill)                       │
│  ├─ Forms/ (14 tools)       │  ├─ compliance/ (2 skills)                   │
│  ├─ Integrations/ (11)      │  ├─ general/ (2 skills)                      │
│  ├─ FileAccess/ (12 tools)  │  └─ templates/ (6 templates)                 │
│  ├─ WebResearch/ (17 tools) │                                              │
│  ├─ CLI/ (11 tools)         │  src/Skills/ (13 files)                      │
│  ├─ CustomFields/ (7 tools) │  ├─ SkillManager.php                         │
│  ├─ Expressions/ (11 tools) │  ├─ SkillRegistry.php                        │
│  ├─ Workflows/ (2 tools)    │  ├─ SkillLoader.php                          │
│  └─ research/ (6 tools)     │  └─ SkillValidator.php                       │
│                             │                                              │
│  src/Config/ToolRegistry.php (24KB) - Central tool management              │
└─────────────────────────────┴───────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              AUTOMATION LAYER                                │
├─────────────────────────────┬───────────────────────────────────────────────┤
│      SCHEDULER              │              ANALYTICS                        │
│  src/Scheduler/             │  src/Analytics/                               │
│  ├─ TaskScheduler.php       │  ├─ AnalyticsService.php (15KB)              │
│  ├─ TaskRunner.php (13KB)   │  ├─ AnalyticsRepository.php (20KB)           │
│  ├─ ScheduledTask.php (15KB)│  ├─ AnalyticsEndpoint.php (11KB)             │
│  ├─ TaskExecution.php (12KB)│  ├─ AnalyticsSchema.php (6KB)                │
│  ├─ CronSchedule.php (10KB) │  └─ AnalyticsScheduler.php (3KB)             │
│  ├─ RecurringSchedule.php   │                                              │
│  └─ Agents/ (4 files)       │  Tracks: Messages, Sessions, Costs,          │
│     └─ Proactive agents     │          Ratings, Tool Usage, Errors         │
└─────────────────────────────┴───────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              DATA LAYER                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│  src/Models/                                                                 │
│  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐                      │
│  │ ChatAgent     │ │ AgentGroup    │ │ ChatAssignment│                      │
│  │ (8KB)         │ │ (12KB)        │ │ (9KB)         │                      │
│  └───────────────┘ └───────────────┘ └───────────────┘                      │
│  ┌───────────────┐ ┌───────────────┐                                        │
│  │ ChatSession   │ │ ChatRating    │                                        │
│  │ (10KB)        │ │ (8KB)         │                                        │
│  └───────────────┘ └───────────────┘                                        │
│                                                                              │
│  includes/Database/Schema.php (18KB)                                        │
│  └─ Creates: swc_agents, swc_agent_groups, swc_assignments,                 │
│              swc_sessions, swc_scheduled_tasks, swc_task_executions,        │
│              swc_knowledge, swc_ratings, swc_analytics                      │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              CONFIGURATION                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  src/Config/                                                                 │
│  ├─ AgentConfig.php (16KB)      - Agent configuration DTO                   │
│  ├─ PromptBuilder.php (10KB)    - System prompt construction                │
│  ├─ ToolRegistry.php (24KB)     - Central tool registry                     │
│  ├─ ToolConfigSchema.php (8KB)  - Tool configuration schemas                │
│  ├─ SystemToolRegistry.php (5KB)- System-level tools                        │
│  └─ default-agents.php (7KB)    - Default agent definitions                 │
│                                                                              │
│  src/Types/                                                                  │
│  ├─ ProviderName.php           - Provider enumeration                       │
│  ├─ ProviderSettings.php       - Provider configuration DTO                 │
│  └─ ModelInfo.php              - Model metadata                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## File Size Reference

| File | Size | Purpose |
|------|------|---------|
| `includes/class-chatbot.php` | 108KB | Legacy frontend (2163 lines) |
| `src/Api/Controllers/AgentController.php` | 44KB | Agent REST API |
| `src/Api/Providers/Bedrock.php` | 26KB | AWS Bedrock integration |
| `src/Api/Providers/OpenAINative.php` | 26KB | Native OpenAI SDK |
| `src/Api/SkillsController.php` | 25KB | Skills REST API |
| `src/Knowledge/KnowledgeController.php` | 25KB | Knowledge REST API |
| `src/Config/ToolRegistry.php` | 24KB | Tool management |
| `src/Analytics/AnalyticsRepository.php` | 20KB | Analytics data layer |
| `includes/Database/Schema.php` | 18KB | Database schema |
| `src/Api/TasksApiController.php` | 18KB | Tasks REST API |
