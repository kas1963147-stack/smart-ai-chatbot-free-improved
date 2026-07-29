/**
 * Unified AI Provider List — Single Source of Truth
 *
 * Both the Settings page and Provider Hub import from here
 * so the dropdown options always stay in sync.
 *
 * hasModelApi:       true if the provider has a public API to list available models
 * needsKeyForModels: true if an API key is required to fetch the model list
 */
const AI_PROVIDERS = [
	{ id: 'openrouter', label: 'OpenRouter (Multi-Model)', description: 'Access 200+ models through one API', requiresApiKey: true, hasModelApi: true },
	{ id: 'openai', label: 'OpenAI (GPT)', description: 'GPT-4, GPT-4o, o1, o3 models', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'anthropic', label: 'Anthropic (Claude)', description: 'Claude 3.5 Sonnet, Claude 3 Opus', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'gemini', label: 'Google Gemini', description: 'Gemini Pro, Flash, Ultra', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'groq', label: 'Groq (Fast)', description: 'Ultra-fast Llama, Mixtral inference', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'deepseek', label: 'DeepSeek (R1)', description: 'DeepSeek R1, DeepSeek Chat', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'mistral', label: 'Mistral', description: 'Mistral Large, Codestral', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'xai', label: 'xAI (Grok)', description: 'Grok models from xAI', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'fireworks', label: 'Fireworks', description: 'Fast inference for open models', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'cerebras', label: 'Cerebras', description: 'Ultra-fast Llama inference', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'sambanova', label: 'SambaNova', description: 'Enterprise AI platform', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'huggingface', label: 'HuggingFace', description: 'Inference API for open models', requiresApiKey: true, hasModelApi: true },
	{ id: 'deepinfra', label: 'DeepInfra', description: 'Fast inference for open models', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'together', label: 'Together AI', description: 'Open-source model catalog', requiresApiKey: true, hasModelApi: true, needsKeyForModels: true },
	{ id: 'io-intelligence', label: 'IO Intelligence', description: 'IO Intelligence inference', requiresApiKey: true },
	{ id: 'azure', label: 'Azure OpenAI', description: 'Azure-hosted OpenAI models', requiresApiKey: true, requiresBaseUrl: true },
	{ id: 'bedrock', label: 'AWS Bedrock', description: 'Claude, Titan, Llama on AWS', requiresApiKey: true },
	{ id: 'vertex', label: 'Google Vertex AI', description: 'Enterprise Gemini & PaLM', requiresApiKey: true },
	{ id: 'requesty', label: 'Requesty', description: 'AI gateway and proxy', requiresApiKey: true },
	{ id: 'chutes', label: 'Chutes', description: 'Chutes AI inference', requiresApiKey: true },
	{ id: 'litellm', label: 'LiteLLM', description: 'Unified LLM proxy', requiresApiKey: true, requiresBaseUrl: true },
	{ id: 'ollama', label: 'Ollama (Local)', description: 'Run models locally', requiresApiKey: false, requiresBaseUrl: true, hasModelApi: true },
	{ id: 'lmstudio', label: 'LM Studio (Local)', description: 'Local model inference', requiresApiKey: false, requiresBaseUrl: true, hasModelApi: true },
];

export default AI_PROVIDERS;
