<?php
declare(strict_types=1);
/**
 * Settings REST Controller
 * 
 * REST API endpoints for chatbot settings management.
 * Replaces legacy PHP form submission with modern API.
 * 
 * @package SWC_Chatbot
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsController
 */
class SettingsController {

    /**
     * Option name for settings
     */
    const OPTION_NAME = 'swc_chatbot_settings';

    /**
     * Register REST routes
     */
    public function register(): void {
        register_rest_route('quark-agentflow-ai/v1', '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/settings/test-connection', [
            'methods' => 'POST',
            'callback' => [$this, 'testConnection'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/settings/dismiss-intro', [
            'methods' => 'POST',
            'callback' => [$this, 'dismissIntro'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/settings/providers', [
            'methods' => 'GET',
            'callback' => [$this, 'getProviders'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // === AI Search Hub Endpoints ===
        register_rest_route('quark-agentflow-ai/v1', '/search/test', [
            'methods' => 'POST',
            'callback' => [$this, 'testSearch'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/search/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getSearchStats'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/search/clear-cache', [
            'methods' => 'POST',
            'callback' => [$this, 'clearSearchCache'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/search/synonyms', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSynonyms'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'saveSynonyms'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // === Provider Instance Management Endpoints ===
        register_rest_route('quark-agentflow-ai/v1', '/provider-instances', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getProviderInstances'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'saveProviderInstance'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // IMPORTANT: Register specific sub-routes BEFORE the parameterized route.
        // WordPress REST API matches routes in registration order using regex.
        // The pattern (?P<id>[a-zA-Z0-9-]+) would match "test" and "dropdown"
        // as IDs, causing method-not-allowed errors if registered first.
        register_rest_route('quark-agentflow-ai/v1', '/provider-instances/test', [
            'methods' => 'POST',
            'callback' => [$this, 'testProviderInstance'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/provider-instances/dropdown', [
            'methods' => 'GET',
            'callback' => [$this, 'getProviderInstancesDropdown'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('quark-agentflow-ai/v1', '/provider-instances/credentials', [
            'methods' => 'POST',
            'callback' => [$this, 'updateProviderInstanceCredentials'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // POST-based delete route (avoids WordPress regex conflicts with parameterized routes)
        register_rest_route('quark-agentflow-ai/v1', '/provider-instances/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'deleteProviderInstance'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Diagnostic endpoint to check which provider an agent actually resolves to
        register_rest_route('quark-agentflow-ai/v1', '/provider-instances/diagnose', [
            'methods' => 'GET',
            'callback' => [$this, 'diagnoseProviderForAgent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Keep parameterized DELETE route as fallback for external callers
        register_rest_route('quark-agentflow-ai/v1', '/provider-instances/(?P<id>[a-zA-Z0-9-]+)', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteProviderInstance'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // === Universal Provider Models Endpoint ===
        register_rest_route('quark-agentflow-ai/v1', '/provider-models', [
            'methods' => 'GET',
            'callback' => [$this, 'getProviderModels'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // === OpenRouter Dynamic Models Endpoint (backward compat alias) ===
        register_rest_route('quark-agentflow-ai/v1', '/openrouter-models', [
            'methods' => 'GET',
            'callback' => [$this, 'getOpenRouterModels'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);


    }

    /**
     * Check user permission
     */
    public function checkPermission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * GET /provider-models - Fetch available models for any supported provider
     * 
     * Query params:
     *   provider (required) - Provider ID (e.g., 'openai', 'anthropic', 'groq')
     *   api_key  (optional) - API key to authenticate with the provider
     *   base_url (optional) - Custom base URL (for Ollama, LM Studio, etc.)
     */
    public function getProviderModels(\WP_REST_Request $request): \WP_REST_Response {
        $provider = sanitize_text_field($request->get_param('provider') ?? '');

        if (empty($provider)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Provider is required',
                'data'    => [],
            ], 400);
        }

        if (!\Quarksol\SmartChatbot\Services\ModelFetcher::isSupported($provider)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Provider '{$provider}' does not support dynamic model listing",
                'data'    => [],
            ]);
        }

        $apiKey = sanitize_text_field($request->get_param('api_key') ?? '');
        $baseUrl = esc_url_raw($request->get_param('base_url') ?? '');
        $instanceId = sanitize_text_field($request->get_param('instance_id') ?? $request->get_param('instanceId') ?? '');
        $credentialGroupId = sanitize_text_field($request->get_param('credential_group_id') ?? $request->get_param('credentialGroupId') ?? '');

        if ((!empty($instanceId) || !empty($credentialGroupId)) && !class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        if (class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            if (!empty($instanceId)) {
                $instance = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstance($instanceId);
                if ($instance && ($instance['provider'] ?? '') === $provider) {
                    if (empty($apiKey) && !empty($instance['api_key'])) {
                        $apiKey = $instance['api_key'];
                    }
                    if (empty($baseUrl) && !empty($instance['base_url'])) {
                        $baseUrl = $instance['base_url'];
                    }
                }
            }

            if ((!empty($credentialGroupId)) && (empty($apiKey) || empty($baseUrl))) {
                $instances = \Quarksol\SmartChatbot\Services\ProviderResolver::ensureCredentialGroups();
                foreach ($instances as $instance) {
                    if (($instance['provider'] ?? '') !== $provider) {
                        continue;
                    }
                    if (($instance['credential_group_id'] ?? '') !== $credentialGroupId) {
                        continue;
                    }
                    if (empty($apiKey) && !empty($instance['api_key'])) {
                        $apiKey = $instance['api_key'];
                    }
                    if (empty($baseUrl) && !empty($instance['base_url'])) {
                        $baseUrl = $instance['base_url'];
                    }
                    break;
                }
            }
        }

        // If no API key provided, try to resolve from saved settings
        if (empty($apiKey) && \Quarksol\SmartChatbot\Services\ModelFetcher::requiresKey($provider)) {
            $settings = get_option(self::OPTION_NAME, []);
            $providerConfigs = $settings['provider_configs'] ?? [];
            if (!empty($providerConfigs[$provider]['api_key'])) {
                $apiKey = $providerConfigs[$provider]['api_key'];
            }
            // Also try from provider instances
            if (empty($apiKey)) {
                $instances = get_option('swc_provider_instances', []);
                foreach ($instances as $inst) {
                    if (($inst['provider'] ?? '') === $provider && !empty($inst['api_key'])) {
                        $apiKey = $inst['api_key'];
                        break;
                    }
                }
            }
        }

        // If still no key and provider requires one
        if (empty($apiKey) && \Quarksol\SmartChatbot\Services\ModelFetcher::requiresKey($provider)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'API key required to fetch models for this provider',
                'requires_key' => true,
                'data'    => [],
            ]);
        }

        $models = \Quarksol\SmartChatbot\Services\ModelFetcher::fetchModels($provider, $apiKey ?: null, $baseUrl ?: null);

        return new \WP_REST_Response([
            'success' => true,
            'provider' => $provider,
            'count'   => count($models),
            'data'    => $models,
        ]);
    }

    /**
     * GET /openrouter-models - Fetch available OpenRouter models (cached)
     */
    public function getOpenRouterModels(\WP_REST_Request $request): \WP_REST_Response {
        // Ensure the provider class is loaded
        $provider_file = SWC_CHATBOT_PATH . 'includes/api-providers/class-openrouter-provider.php';
        if (file_exists($provider_file)) {
            require_once $provider_file;
        }

        if (!class_exists('SWC_Chatbot_OpenRouter_Provider')) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'OpenRouter provider not available',
                'data'    => [],
            ], 500);
        }

        $models = \SWC_Chatbot_OpenRouter_Provider::get_cached_models();

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $models,
        ]);
    }



    /**
     * Get all settings with caching
     */
    public function getSettings(\WP_REST_Request $request): \WP_REST_Response {
        // Check cache (10 minute cache for settings)
        $cacheKey = 'swc_settings_v2';
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return new \WP_REST_Response([
                'success' => true,
                'settings' => $cached,
            ]);
        }
        
        $settings = get_option(self::OPTION_NAME, []);
        
        // Migrate legacy flat settings to provider_configs if needed
        $settings = $this->migrateToProviderConfigs($settings);
        
        // Mask API keys for security (legacy field)
        if (!empty($settings['ai_api_key'])) {
            $key = $settings['ai_api_key'];
            $settings['ai_api_key_masked'] = substr($key, 0, 8) . '...' . substr($key, -4);
        }
        
        // Mask API keys in provider_configs
        if (!empty($settings['provider_configs']) && is_array($settings['provider_configs'])) {
            foreach ($settings['provider_configs'] as $providerId => $config) {
                if (!empty($config['api_key'])) {
                    $key = $config['api_key'];
                    $settings['provider_configs'][$providerId]['api_key_masked'] = 
                        strlen($key) > 12 ? substr($key, 0, 8) . '...' . substr($key, -4) : '***';
                }
            }
        }

        // Include activation-triggered intro flag
        $settings['show_intro'] = (bool) get_option('swc_show_intro', false);

        // Cache for 10 minutes
        set_transient($cacheKey, $settings, 10 * MINUTE_IN_SECONDS);

        return new \WP_REST_Response([
            'success' => true,
            'settings' => $settings,
        ]);
    }
    
    /**
     * Dismiss the introduction popup (called once after first activation)
     */
    public function dismissIntro(): \WP_REST_Response {
        delete_option('swc_show_intro');
        delete_transient('swc_settings_v2');
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Introduction dismissed',
        ]);
    }
    
    /**
     * Migrate legacy flat settings to per-provider configs
     * 
     * This ensures existing users don't lose their settings after the update.
     */
    private function migrateToProviderConfigs(array $settings): array {
        // If provider_configs already exists and has data, no migration needed
        if (!empty($settings['provider_configs']) && is_array($settings['provider_configs'])) {
            return $settings;
        }
        
        // Check if there are legacy settings to migrate
        $hasLegacySettings = !empty($settings['ai_api_key']) || 
                             !empty($settings['ai_model']) || 
                             !empty($settings['ai_base_url']);
        
        if (!$hasLegacySettings) {
            $settings['provider_configs'] = [];
            return $settings;
        }
        
        // Migrate legacy settings to provider_configs under the current provider
        $currentProvider = $settings['ai_provider'] ?? 'openai';
        $settings['provider_configs'] = [
            $currentProvider => [
                'api_key' => $settings['ai_api_key'] ?? '',
                'model' => $settings['ai_model'] ?? '',
                'base_url' => $settings['ai_base_url'] ?? '',
                'extra' => [],
            ]
        ];
        
        // Save the migrated settings
        update_option(self::OPTION_NAME, $settings);
        
        return $settings;
    }

    /**
     * Update settings
     */
    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response {
        $newSettings = $request->get_param('settings');
        
        if (!is_array($newSettings)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid settings format'
            ], 400);
        }

        // Get existing settings to preserve API key if not changed
        $existingSettings = get_option(self::OPTION_NAME, []);
        
        // Sanitize settings
        $sanitized = $this->sanitizeSettings($newSettings, $existingSettings);
        
        // Save
        $result = update_option(self::OPTION_NAME, $sanitized);
        
        // Invalidate cache
        delete_transient('swc_settings_v2');

        // Sync provider_configs to swc_provider_instances so Provider Hub shows them
        $this->syncProviderConfigsToInstances($sanitized);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Settings saved successfully',
        ]);
    }

    /**
     * Test AI connection
     */
    public function testConnection(\WP_REST_Request $request): \WP_REST_Response {
        $provider = sanitize_text_field($request->get_param('provider') ?? '');
        $apiKey = sanitize_text_field($request->get_param('api_key') ?? '');
        $model = sanitize_text_field($request->get_param('model') ?? '');
        $baseUrl = sanitize_text_field($request->get_param('base_url') ?? '');

        // Use ProviderBridge if available (with request-scoped settings)
        $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
        if (file_exists($bootstrap)) {
            require_once $bootstrap;
            
            try {
                $providerInstance = \Quarksol\SmartChatbot\Bridge\ProviderBridge::fromOptions([
                    'provider' => $provider,
                    'api_key' => $apiKey,
                    'model' => $model,
                    'base_url' => $baseUrl,
                ]);
                
                $response = $providerInstance->completePrompt(
                    'Say "Connection successful!" and nothing else.'
                );
                
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => 'Connection successful!',
                    'response' => $response
                ]);
            } catch (\Exception $e) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
        }

        // Fallback to legacy provider factory
        if (class_exists('SWC_Chatbot_Provider_Factory')) {
            $config = [
                'provider' => $provider,
                'apiKey' => $apiKey,
                'model' => $model,
                'baseUrl' => $baseUrl
            ];
            
            $providerInstance = \SWC_Chatbot_Provider_Factory::create_provider($config);
            
            if (is_wp_error($providerInstance)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $providerInstance->get_error_message()
                ], 400);
            }
            
            $response = $providerInstance->chat('Say "Hello! Connection successful." and nothing else.');
            
            if (is_wp_error($response)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $response->get_error_message()
                ], 400);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Connection successful!',
                'response' => $response
            ]);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'No provider system available'
        ], 500);
    }

    /**
     * Get available providers
     * 
     * Returns all available AI providers dynamically from the provider registry.
     */
    public function getProviders(\WP_REST_Request $request): \WP_REST_Response {
        // Try to use the dynamic provider list first
        if (function_exists('\Quarksol\SmartChatbot\Api\\getAvailableProviders')) {
            $providerRegistry = \Quarksol\SmartChatbot\Api\getAvailableProviders();
            $providers = [];
            
            foreach ($providerRegistry as $id => $info) {
                $providers[] = [
                    'id' => $id,
                    'name' => $info['name'],
                    'description' => $info['description'] ?? '',
                    'requiresApiKey' => $info['requiresApiKey'] ?? true,
                    'requiresBaseUrl' => $info['requiresBaseUrl'] ?? $info['local'] ?? false,
                ];
            }
            
            // Add Azure explicitly if not in registry
            $hasAzure = array_filter($providers, fn($p) => $p['id'] === 'azure');
            if (empty($hasAzure)) {
                $providers[] = [
                    'id' => 'azure',
                    'name' => 'Azure OpenAI',
                    'description' => 'Microsoft Azure-hosted OpenAI models',
                    'requiresApiKey' => true,
                    'requiresBaseUrl' => true,
                ];
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'providers' => $providers
            ]);
        }
        
        // Fallback to comprehensive static list (kept in sync with assets/admin-react/constants/providers.js)
        $providers = [
            ['id' => 'openrouter', 'name' => 'OpenRouter (Multi-Model)', 'description' => 'Access 200+ models through one API', 'requiresApiKey' => true],
            ['id' => 'openai', 'name' => 'OpenAI (GPT)', 'description' => 'GPT-4, GPT-4o, o1, o3 models', 'requiresApiKey' => true],
            ['id' => 'anthropic', 'name' => 'Anthropic (Claude)', 'description' => 'Claude 3.5 Sonnet, Claude 3 Opus', 'requiresApiKey' => true],
            ['id' => 'gemini', 'name' => 'Google Gemini', 'description' => 'Gemini Pro, Flash, Ultra', 'requiresApiKey' => true],
            ['id' => 'groq', 'name' => 'Groq (Fast)', 'description' => 'Ultra-fast Llama, Mixtral inference', 'requiresApiKey' => true],
            ['id' => 'deepseek', 'name' => 'DeepSeek (R1)', 'description' => 'DeepSeek R1, DeepSeek Chat', 'requiresApiKey' => true],
            ['id' => 'mistral', 'name' => 'Mistral', 'description' => 'Mistral Large, Codestral', 'requiresApiKey' => true],
            ['id' => 'xai', 'name' => 'xAI (Grok)', 'description' => 'Grok models from xAI', 'requiresApiKey' => true],
            ['id' => 'fireworks', 'name' => 'Fireworks', 'description' => 'Fast inference for open models', 'requiresApiKey' => true],
            ['id' => 'cerebras', 'name' => 'Cerebras', 'description' => 'Ultra-fast Llama inference', 'requiresApiKey' => true],
            ['id' => 'sambanova', 'name' => 'SambaNova', 'description' => 'Enterprise AI platform', 'requiresApiKey' => true],
            ['id' => 'huggingface', 'name' => 'HuggingFace', 'description' => 'Inference API for open models', 'requiresApiKey' => true],
            ['id' => 'deepinfra', 'name' => 'DeepInfra', 'description' => 'Fast inference for open models', 'requiresApiKey' => true],
            ['id' => 'io-intelligence', 'name' => 'IO Intelligence', 'description' => 'IO Intelligence inference', 'requiresApiKey' => true],
            ['id' => 'azure', 'name' => 'Azure OpenAI', 'description' => 'Azure-hosted OpenAI models', 'requiresApiKey' => true, 'requiresBaseUrl' => true],
            ['id' => 'bedrock', 'name' => 'AWS Bedrock', 'description' => 'Claude, Titan, Llama on AWS', 'requiresApiKey' => true],
            ['id' => 'vertex', 'name' => 'Google Vertex AI', 'description' => 'Enterprise Gemini & PaLM', 'requiresApiKey' => true],
            ['id' => 'requesty', 'name' => 'Requesty', 'description' => 'AI gateway and proxy', 'requiresApiKey' => true],
            ['id' => 'chutes', 'name' => 'Chutes', 'description' => 'Chutes AI inference', 'requiresApiKey' => true],
            ['id' => 'litellm', 'name' => 'LiteLLM', 'description' => 'Unified LLM proxy', 'requiresApiKey' => true, 'requiresBaseUrl' => true],
            ['id' => 'ollama', 'name' => 'Ollama (Local)', 'description' => 'Run models locally', 'requiresApiKey' => false, 'requiresBaseUrl' => true],
            ['id' => 'lmstudio', 'name' => 'LM Studio (Local)', 'description' => 'Local model inference', 'requiresApiKey' => false, 'requiresBaseUrl' => true],
        ];

        return new \WP_REST_Response([
            'success' => true,
            'providers' => $providers
        ]);
    }

    /**
     * Sanitize settings array
     */
    private function sanitizeSettings(array $new, array $existing): array {
        $sanitized = is_array($existing) ? $existing : [];
        $schemaSanitized = [];

        if (class_exists('\Quarksol\SmartChatbot\Foundation\Settings\\SettingsSchemaRegistry')) {
            $schemaSanitized = \Quarksol\SmartChatbot\Foundation\Settings\SettingsSchemaRegistry::sanitize($new, $existing);
        }

        if (!empty($schemaSanitized)) {
            $sanitized = array_merge($sanitized, $schemaSanitized);
        }

        if (empty($schemaSanitized)) {
            $fallback = [];
            // Boolean settings
            $fallback['enabled'] = !empty($new['enabled']);
            $fallback['ai_enabled'] = !empty($new['ai_enabled']);
            $fallback['guest_order_lookup'] = !empty($new['guest_order_lookup']);
            $fallback['enable_logging'] = !empty($new['enable_logging']);
            $fallback['enable_streaming'] = !empty($new['enable_streaming']) || !empty($new['streaming_enabled']);
            $fallback['enable_proactive'] = !empty($new['enable_proactive']) || !empty($new['proactive_enabled']);
            $fallback['enable_history'] = !empty($new['enable_history']);
            $fallback['enable_analytics'] = !empty($new['enable_analytics']);

            // Text settings
            $fallback['bot_name'] = sanitize_text_field($new['bot_name'] ?? 'Shopping Assistant');
            $fallback['welcome_message'] = sanitize_textarea_field($new['welcome_message'] ?? '');
            $fallback['ai_provider'] = sanitize_text_field($new['ai_provider'] ?? 'openai');
            $fallback['ai_model'] = sanitize_text_field($new['ai_model'] ?? '');
            $fallback['ai_base_url'] = esc_url_raw($new['ai_base_url'] ?? '');
            $fallback['ai_system_prompt'] = sanitize_textarea_field($new['ai_system_prompt'] ?? '');
            $fallback['primary_color'] = sanitize_hex_color($new['primary_color'] ?? '#6366f1');
            $fallback['position'] = in_array($new['position'] ?? '', ['left', 'right']) ? $new['position'] : 'right';

            // Numeric settings
            $fallback['ai_temperature'] = floatval($new['ai_temperature'] ?? 0.7);
            $fallback['max_tokens'] = intval($new['max_tokens'] ?? 1000);
            $fallback['proactive_delay'] = intval($new['proactive_delay'] ?? $new['proactiveDelay'] ?? 30);
            $fallback['max_history_messages'] = intval($new['max_history_messages'] ?? 50);

            $sanitized = array_merge($sanitized, $fallback);
        }

        $enableRateLimiting = $sanitized['enable_rate_limiting']
            ?? (!empty($new['enable_rate_limiting']) || !empty($new['rate_limiting']));
        $sanitized['enable_rate_limiting'] = $enableRateLimiting;
        $sanitized['rate_limiting'] = $enableRateLimiting;
        
        // API key - preserve if not changed or empty
        $existingKey = $existing['ai_api_key'] ?? '';
        $maskedLegacy = strlen($existingKey) > 12 
            ? substr($existingKey, 0, 8) . '...' . substr($existingKey, -4) 
            : '***';
            
        if (!empty($new['ai_api_key']) && $new['ai_api_key'] !== $maskedLegacy) {
            $sanitized['ai_api_key'] = sanitize_text_field($new['ai_api_key']);
        } else {
            $sanitized['ai_api_key'] = $existingKey;
        }
        
        // === Appearance Settings ===
        // Color settings (with fallback to existing values)
        $sanitized['color_primary'] = $this->sanitizeColor($new['color_primary'] ?? $existing['color_primary'] ?? '#6366f1');
        $sanitized['color_primary_hover'] = $this->sanitizeColor($new['color_primary_hover'] ?? $existing['color_primary_hover'] ?? '#4f46e5');
        $sanitized['color_bg_main'] = $this->sanitizeColor($new['color_bg_main'] ?? $existing['color_bg_main'] ?? '#ffffff');
        $sanitized['color_bg_light'] = $this->sanitizeColor($new['color_bg_light'] ?? $existing['color_bg_light'] ?? '#f8fafc');
        $sanitized['color_text_primary'] = $this->sanitizeColor($new['color_text_primary'] ?? $existing['color_text_primary'] ?? '#1e293b');
        $sanitized['color_text_secondary'] = $this->sanitizeColor($new['color_text_secondary'] ?? $existing['color_text_secondary'] ?? '#64748b');
        $sanitized['color_border'] = $this->sanitizeColor($new['color_border'] ?? $existing['color_border'] ?? '#e2e8f0');
        
        // Typography settings
        $allowedFonts = ['system', '"Inter", sans-serif', '"Roboto", sans-serif', '"Poppins", sans-serif',
                         '"Nunito", sans-serif', '"Open Sans", sans-serif', '"Outfit", sans-serif',
                         '"DM Sans", sans-serif', '"IBM Plex Sans", sans-serif', '"Quicksand", sans-serif'];
        $fontFamily = $new['font_family'] ?? $existing['font_family'] ?? 'system';
        $sanitized['font_family'] = in_array($fontFamily, $allowedFonts, true)
            ? $fontFamily
            : 'system';
        $fontSizeBase = $new['font_size_base'] ?? $existing['font_size_base'] ?? 14;
        $sanitized['font_size_base'] = min(24, max(10, intval($fontSizeBase)));
        $lineHeight = $new['line_height'] ?? $existing['line_height'] ?? 1.5;
        $sanitized['line_height'] = min(2.0, max(1.2, floatval($lineHeight)));
        
        // Sizing settings
        $windowWidth = $new['window_width'] ?? $existing['window_width'] ?? 380;
        $sanitized['window_width'] = min(600, max(280, intval($windowWidth)));
        $windowHeight = $new['window_height'] ?? $existing['window_height'] ?? 550;
        $sanitized['window_height'] = min(800, max(380, intval($windowHeight)));
        $borderRadius = $new['border_radius'] ?? $existing['border_radius'] ?? 16;
        $sanitized['border_radius'] = min(32, max(0, intval($borderRadius)));
        $bubbleRadius = $new['bubble_radius'] ?? $existing['bubble_radius'] ?? 18;
        $sanitized['bubble_radius'] = min(28, max(0, intval($bubbleRadius)));
        $toggleSize = $new['toggle_size'] ?? $existing['toggle_size'] ?? 60;
        $sanitized['toggle_size'] = min(80, max(48, intval($toggleSize)));
        
        // Effects settings
        $allowedShadows = ['none', 'light', 'medium', 'heavy'];
        $shadowIntensity = $new['shadow_intensity'] ?? $existing['shadow_intensity'] ?? 'medium';
        $sanitized['shadow_intensity'] = in_array($shadowIntensity, $allowedShadows, true)
            ? $shadowIntensity
            : 'medium';
        $allowedAnimations = ['none', 'fast', 'normal', 'slow'];
        $animationSpeed = $new['animation_speed'] ?? $existing['animation_speed'] ?? 'normal';
        $sanitized['animation_speed'] = in_array($animationSpeed, $allowedAnimations, true)
            ? $animationSpeed
            : 'normal';
        $sanitized['enable_hover_effects'] = array_key_exists('enable_hover_effects', $new)
            ? (bool) $new['enable_hover_effects']
            : (bool) ($existing['enable_hover_effects'] ?? true);
        $sanitized['enable_glassmorphism'] = array_key_exists('enable_glassmorphism', $new)
            ? !empty($new['enable_glassmorphism'])
            : !empty($existing['enable_glassmorphism']);
        $sanitized['enable_sounds'] = array_key_exists('enable_sounds', $new)
            ? !empty($new['enable_sounds'])
            : !empty($existing['enable_sounds']);
        
        // Template selection
        $appearanceTemplate = $new['appearance_template'] ?? $existing['appearance_template'] ?? 'modern-minimal';
        $sanitized['appearance_template'] = sanitize_text_field($appearanceTemplate);
        
        // Header/Footer/Message controls (arrays)
        $controlsHeader = null;
        if (array_key_exists('controls_header', $new)) {
            $controlsHeader = is_array($new['controls_header']) ? $new['controls_header'] : null;
        } elseif (isset($existing['controls_header']) && is_array($existing['controls_header'])) {
            $controlsHeader = $existing['controls_header'];
        }
        $sanitized['controls_header'] = $controlsHeader !== null
            ? array_map('sanitize_text_field', $controlsHeader)
            : ['close'];

        $controlsFooter = null;
        if (array_key_exists('controls_footer', $new)) {
            $controlsFooter = is_array($new['controls_footer']) ? $new['controls_footer'] : null;
        } elseif (isset($existing['controls_footer']) && is_array($existing['controls_footer'])) {
            $controlsFooter = $existing['controls_footer'];
        }
        $sanitized['controls_footer'] = $controlsFooter !== null
            ? array_map('sanitize_text_field', $controlsFooter)
            : ['voice'];

        $controlsMessage = null;
        if (array_key_exists('controls_message', $new)) {
            $controlsMessage = is_array($new['controls_message']) ? $new['controls_message'] : null;
        } elseif (isset($existing['controls_message']) && is_array($existing['controls_message'])) {
            $controlsMessage = $existing['controls_message'];
        }
        $sanitized['controls_message'] = $controlsMessage !== null
            ? array_map('sanitize_text_field', $controlsMessage)
            : [];
        
        // === Knowledge/RAG Settings ===
        $sanitized['enable_rag'] = array_key_exists('enable_rag', $new)
            ? !empty($new['enable_rag'])
            : !empty($existing['enable_rag']);
        $embeddingsProvider = $new['embeddings_provider'] ?? $existing['embeddings_provider'] ?? '';
        $sanitized['embeddings_provider'] = in_array($embeddingsProvider, ['openai', 'voyage', 'ollama'], true)
            ? $embeddingsProvider
            : 'openai';
        $embeddingsModel = $new['embeddings_model'] ?? $existing['embeddings_model'] ?? 'text-embedding-3-small';
        $sanitized['embeddings_model'] = sanitize_text_field($embeddingsModel);
        $sanitized['auto_sync_wordpress'] = array_key_exists('auto_sync_wordpress', $new)
            ? !empty($new['auto_sync_wordpress'])
            : !empty($existing['auto_sync_wordpress']);
        $searchTopK = $new['search_top_k'] ?? $existing['search_top_k'] ?? 5;
        $sanitized['search_top_k'] = min(20, max(1, intval($searchTopK)));
        
        // Embeddings API key (falls back to main AI key if not set)
        if (!empty($new['embeddings_api_key']) && $new['embeddings_api_key'] !== ($existing['embeddings_api_key_masked'] ?? '')) {
            $sanitized['embeddings_api_key'] = sanitize_text_field($new['embeddings_api_key']);
        } else {
            $sanitized['embeddings_api_key'] = $existing['embeddings_api_key'] ?? '';
        }
        
        // Sync knowledge config with KnowledgeConfig class
        if (class_exists('\Quarksol\SmartChatbot\Knowledge\\KnowledgeConfig')) {
            \Quarksol\SmartChatbot\Knowledge\KnowledgeConfig::save([
                'enable_rag' => $sanitized['enable_rag'],
                'embeddings_provider' => $sanitized['embeddings_provider'],
                'embeddings_model' => $sanitized['embeddings_model'],
                'embeddings_api_key' => $sanitized['embeddings_api_key'],
                'auto_sync_wordpress' => $sanitized['auto_sync_wordpress'],
                'search_top_k' => $sanitized['search_top_k'],
            ]);
        }
        
        // === Per-Provider Configuration Storage ===
        // This is the key fix for provider persistence - each provider's settings are stored separately
        $sanitized['provider_configs'] = $this->sanitizeProviderConfigs(
            $new['provider_configs'] ?? [],
            $existing['provider_configs'] ?? []
        );
        
        // === Search Enhancement Settings ===
        $sanitized['search_enhancement_enabled'] = array_key_exists('search_enhancement_enabled', $new)
            ? !empty($new['search_enhancement_enabled'])
            : !empty($existing['search_enhancement_enabled']);
        $sanitized['search_max_keywords'] = array_key_exists('search_max_keywords', $new)
            ? min(15, max(3, intval($new['search_max_keywords'])))
            : intval($existing['search_max_keywords'] ?? 5);
        $sanitized['search_cache_duration'] = array_key_exists('search_cache_duration', $new)
            ? intval($new['search_cache_duration'])
            : intval($existing['search_cache_duration'] ?? 3600);
        $sanitized['search_provider_instance_id'] = array_key_exists('search_provider_instance_id', $new)
            ? sanitize_text_field($new['search_provider_instance_id'])
            : ($existing['search_provider_instance_id'] ?? '');
        
        return $sanitized;
    }

    /**
     * Sanitize color value (supports hex, rgb, rgba, and CSS color names)
     * 
     * @param string $color
     * @return string
     */
    private function sanitizeColor(string $color): string {
        $color = trim($color);
        
        // Check for hex color
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return $color;
        }
        
        // Check for rgb/rgba
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)$/i', $color)) {
            return $color;
        }
        
        // Check for CSS gradient
        if (preg_match('/^linear-gradient\(.*\)$/i', $color)) {
            // Basic sanitization for gradients - remove potential script injection
            return preg_replace('/[<>]/', '', $color);
        }
        
        // Fallback to default
        return '#6366f1';
    }
    
    /**
     * Sanitize per-provider configuration storage
     * 
     * @param array $new New provider configs from request
     * @param array $existing Existing provider configs from database
     * @return array Sanitized provider configs
     */
    private function sanitizeProviderConfigs(array $new, array $existing): array {
        $configs = $existing; // Start with existing to preserve all providers
        
        foreach ($new as $providerId => $config) {
            if (!is_string($providerId) || !is_array($config)) {
                continue;
            }
            
            $providerId = sanitize_text_field($providerId);
            $existingConfig = $configs[$providerId] ?? [];
            
            // Handle API key - preserve if unchanged (checking against masked version)
            $apiKey = '';
            $existingKey = $existingConfig['api_key'] ?? '';
            $maskedExisting = strlen($existingKey) > 8 
                ? substr($existingKey, 0, 4) . '...' . substr($existingKey, -4) 
                : '***';

            if (!empty($config['api_key'])) {
                // Check if this is the masked version (unchanged) or a new key
                if ($config['api_key'] !== $maskedExisting) {
                    // New key provided
                    $apiKey = sanitize_text_field($config['api_key']);
                } else {
                    // Masked key sent back, preserve original
                    $apiKey = $existingKey;
                }
            } else {
                // No key in request, preserve existing
                $apiKey = $existingKey;
            }
            
            $configs[$providerId] = [
                'api_key' => $apiKey,
                'model' => sanitize_text_field($config['model'] ?? $existingConfig['model'] ?? ''),
                'base_url' => esc_url_raw($config['base_url'] ?? $existingConfig['base_url'] ?? ''),
                'extra' => $this->sanitizeProviderExtra($config['extra'] ?? $existingConfig['extra'] ?? []),
            ];
        }
        
        return $configs;
    }
    
    /**
     * Sanitize provider-specific extra fields
     * 
     * @param array $extra
     * @return array
     */
    private function sanitizeProviderExtra(array $extra): array {
        $sanitized = [];
        foreach ($extra as $key => $value) {
            $key = sanitize_text_field($key);
            if (is_array($value)) {
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized;
    }

    // ===================================================================
    // AI Search Hub Endpoints
    // ===================================================================

    /**
     * Test AI search enhancement with a sample query
     */
    public function testSearch(\WP_REST_Request $request): \WP_REST_Response {
        $searchTerm = sanitize_text_field($request->get_param('search_term') ?? '');

        if (empty($searchTerm) || strlen($searchTerm) < 2) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Search term must be at least 2 characters',
            ], 400);
        }

        try {
            // Load the SearchEnhancer service
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) {
                require_once $bootstrap;
            }

            if (!class_exists('\Quarksol\SmartChatbot\Services\SearchEnhancer')) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'SearchEnhancer service not available',
                ], 500);
            }

            $result = \Quarksol\SmartChatbot\Services\SearchEnhancer::test($searchTerm);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get search enhancement statistics
     */
    public function getSearchStats(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        try {
            // Count cached search terms
            $cachePrefix = '_transient_swc_search_keywords_';
            $totalCached = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $cachePrefix . '%'
                )
            );

            // Approximate cache size
            $cacheSize = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $cachePrefix . '%'
                )
            );

            // Get current settings
            $settings = get_option(self::OPTION_NAME, []);

            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'total_cached' => $totalCached,
                    'cache_size_bytes' => $cacheSize,
                    'cache_duration' => (int) ($settings['search_cache_duration'] ?? 3600),
                    'min_query_length' => (int) ($settings['search_min_query_length'] ?? 2),
                    'enhancement_active' => !empty($settings['search_enhancement_enabled']),
                    'agent_status' => class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')
                        ? (\Quarksol\SmartChatbot\Models\ChatAgent::findBySlug('search_agent') ? 'Ready' : 'Agent not found')
                        : 'Unknown',
                ],
            ]);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear search keyword cache
     */
    public function clearSearchCache(\WP_REST_Request $request): \WP_REST_Response {
        $searchTerm = $request->get_param('search_term');

        try {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) {
                require_once $bootstrap;
            }

            if (class_exists('\Quarksol\SmartChatbot\Services\SearchEnhancer')) {
                \Quarksol\SmartChatbot\Services\SearchEnhancer::clearCache(
                    $searchTerm ? sanitize_text_field($searchTerm) : null
                );
            } else {
                // Fallback: clear manually
                global $wpdb;
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                        '_transient_swc_search_keywords_%'
                    )
                );
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => $searchTerm
                    ? 'Cache cleared for: ' . $searchTerm
                    : 'All search cache cleared',
            ]);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get search synonyms
     */
    public function getSynonyms(\WP_REST_Request $request): \WP_REST_Response {
        $synonyms = get_option('swc_search_synonyms', []);

        return new \WP_REST_Response([
            'success' => true,
            'data' => is_array($synonyms) ? $synonyms : [],
        ]);
    }

    /**
     * Save search synonyms
     */
    public function saveSynonyms(\WP_REST_Request $request): \WP_REST_Response {
        $synonyms = $request->get_param('synonyms');

        if (!is_array($synonyms)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid synonyms format',
            ], 400);
        }

        // Sanitize
        $sanitized = [];
        foreach ($synonyms as $entry) {
            if (!is_array($entry) || empty($entry['term']) || empty($entry['synonyms'])) {
                continue;
            }
            $sanitized[] = [
                'term' => sanitize_text_field($entry['term']),
                'synonyms' => array_map('sanitize_text_field', (array) $entry['synonyms']),
            ];
        }

        update_option('swc_search_synonyms', $sanitized);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Synonyms saved',
            'data' => $sanitized,
        ]);
    }

    // ===================================================================
    // Provider Instance Management
    // ===================================================================

    /**
     * Sync provider_configs from Settings into swc_provider_instances
     *
     * When a user saves providers via Settings → AI Provider tab, this method
     * creates or updates corresponding entries in the Provider Hub storage
     * (swc_provider_instances) so both pages share the same data.
     *
     * Instances created from Settings use IDs prefixed with "settings_".
     */
    private function syncProviderConfigsToInstances(array $sanitizedSettings): void {
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            return; // Can't sync without ProviderResolver
        }

        $providerConfigs = $sanitizedSettings['provider_configs'] ?? [];
        
        foreach ($providerConfigs as $providerId => $config) {
            $settingsId = 'settings_' . $providerId;
            
            // If API key AND Base URL are empty/cleared, remove the synced instance
            if (empty($config['api_key']) && empty($config['base_url'])) {
                $existing = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstance($settingsId);
                if ($existing) {
                    \Quarksol\SmartChatbot\Services\ProviderResolver::deleteInstance($settingsId);
                }
                continue;
            }

            $existing = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstance($settingsId);

            $instanceData = [
                'id'           => $settingsId,
                'display_name' => ucfirst($providerId),
                'provider'     => $providerId,
                'api_key'      => $config['api_key'],
                'model'        => $config['model'] ?? '',
                'base_url'     => $config['base_url'] ?? '',
                'credential_group_id' => $existing['credential_group_id'] ?? ('credential_settings_' . sanitize_key($providerId)),
                'is_default'   => $existing['is_default'] ?? false, // preserve default flag
                'source'       => 'settings', // marks it as synced from global settings
                'extra'        => $config['extra'] ?? [],
            ];

            if ($existing && !empty($existing['display_name'])) {
                $instanceData['display_name'] = $existing['display_name'];
            }

            \Quarksol\SmartChatbot\Services\ProviderResolver::saveInstance($instanceData);
        }
    }

    /**
     * Get all provider instances
     */
    public function getProviderInstances(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        $instances = \Quarksol\SmartChatbot\Services\ProviderResolver::ensureCredentialGroups();

        // Merge in legacy provider_configs from Settings (auto-sync on first load)
        $settings = get_option(self::OPTION_NAME, []);
        $providerConfigs = $settings['provider_configs'] ?? [];
        $instanceIds = array_column($instances, 'id');

        foreach ($providerConfigs as $providerId => $config) {
            $settingsId = 'settings_' . $providerId;
            // Only add if not already synced and has a valid configuration (Key or URL)
            if (!in_array($settingsId, $instanceIds, true) && (!empty($config['api_key']) || !empty($config['base_url']))) {
                // Auto-create the instance from legacy settings
                $instanceData = [
                    'id'           => $settingsId,
                    'display_name' => ucfirst($providerId),
                    'provider'     => $providerId,
                    'api_key'      => $config['api_key'],
                    'model'        => $config['model'] ?? '',
                    'base_url'     => $config['base_url'] ?? '',
                    'credential_group_id' => 'credential_settings_' . sanitize_key($providerId),
                    'is_default'   => false,
                    'source'       => 'settings',
                    'extra'        => $config['extra'] ?? [],
                ];
                \Quarksol\SmartChatbot\Services\ProviderResolver::saveInstance($instanceData);
                $instances[] = $instanceData;
            }
        }

        // Auto-repair: fix instances with empty IDs (bug where '' was saved instead of UUID)
        $needsSave = false;
        foreach ($instances as &$inst) {
            if (empty($inst['id'])) {
                $inst['id'] = 'provider_' . wp_generate_uuid4();
                $needsSave = true;
                error_log("[ProviderInstances] Auto-repaired empty ID → {$inst['id']} for '{$inst['display_name']}'");
            }
        }
        unset($inst);
        if ($needsSave) {
            update_option('swc_provider_instances', $instances);
        }

        // Filter out instances without valid configuration (Key or Base URL)
        $instances = array_filter($instances, function($inst) {
            return !empty($inst['api_key']) || !empty($inst['base_url']);
        });

        $groupCounts = [];
        foreach ($instances as $inst) {
            $groupId = $inst['credential_group_id'] ?? '';
            if (!empty($groupId)) {
                $groupCounts[$groupId] = ($groupCounts[$groupId] ?? 0) + 1;
            }
        }

        // Mask API keys for display
        $masked = array_map(function ($inst) use ($groupCounts) {
            $maskedKey = '';
            if (!empty($inst['api_key'])) {
                $maskedKey = \Quarksol\SmartChatbot\Services\ProviderResolver::maskApiKey((string) $inst['api_key']);
                $inst['api_key_masked'] = $maskedKey;
                // Don't expose full key in list response
                unset($inst['api_key']);
            }
            $groupId = $inst['credential_group_id'] ?? '';
            $inst['credential_group_size'] = !empty($groupId) ? ($groupCounts[$groupId] ?? 1) : 1;
            $inst['credential_label'] = !empty($maskedKey) ? ('Key ' . $maskedKey) : (!empty($inst['base_url']) ? 'Custom endpoint' : 'No credentials');
            return $inst;
        }, $instances);

        return new \WP_REST_Response([
            'success' => true,
            'data' => array_values($masked),
        ]);
    }

    /**
     * Save (create or update) a provider instance
     */
    public function saveProviderInstance(\WP_REST_Request $request): \WP_REST_Response {
        error_log('[SettingsController::saveProviderInstance] Request Params: ' . print_r($request->get_params(), true));
        
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        $data = [
            'id' => sanitize_text_field($request->get_param('id') ?? ''),
            'display_name' => sanitize_text_field($request->get_param('display_name') ?? ''),
            'provider' => sanitize_text_field($request->get_param('provider') ?? ''),
            'api_key' => sanitize_text_field($request->get_param('api_key') ?? $request->get_param('apiKey') ?? ''),
            'model' => sanitize_text_field($request->get_param('model') ?? ''),
            'base_url' => esc_url_raw($request->get_param('base_url') ?? ''),
            'credential_group_id' => sanitize_text_field($request->get_param('credential_group_id') ?? $request->get_param('credentialGroupId') ?? ''),
            'is_default' => (bool) $request->get_param('is_default'),
            'extra' => is_array($request->get_param('extra')) ? $request->get_param('extra') : [],
        ];

        // If api_key is empty OR matches masked version, keep the old key
        if (!empty($data['id'])) {
            $existing = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstance($data['id']);
            if ($existing) {
                $existingKey = $existing['api_key'] ?? '';
                $maskedExisting = strlen($existingKey) > 8 
                    ? substr($existingKey, 0, 4) . '...' . substr($existingKey, -4) 
                    : '***';

                if (empty($data['api_key']) || $data['api_key'] === $maskedExisting) {
                    $data['api_key'] = $existingKey;
                }
                if (empty($data['credential_group_id']) && !empty($existing['credential_group_id'])) {
                    $data['credential_group_id'] = $existing['credential_group_id'];
                }
            }
        }

        // Inherit API key from parent instance (for "Add Model" flow)
        $inheritFrom = sanitize_text_field($request->get_param('inherit_key_from') ?? '');
        if (!empty($inheritFrom) && empty($data['api_key'])) {
            $parentInstance = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstance($inheritFrom);
            if ($parentInstance && !empty($parentInstance['api_key'])) {
                $data['api_key'] = $parentInstance['api_key'];
                error_log("[SettingsController] Inherited API key from parent instance '{$inheritFrom}'");
                // Also inherit base_url if not set
                if (empty($data['base_url']) && !empty($parentInstance['base_url'])) {
                    $data['base_url'] = $parentInstance['base_url'];
                }
                if (empty($data['credential_group_id']) && !empty($parentInstance['credential_group_id'])) {
                    $data['credential_group_id'] = $parentInstance['credential_group_id'];
                }
            }
        }

        if (empty($data['display_name']) || empty($data['provider'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Display name and provider are required',
            ], 400);
        }

        $id = \Quarksol\SmartChatbot\Services\ProviderResolver::saveInstance($data);

        // If this is a settings-synced instance, also update provider_configs in settings
        if (str_starts_with($id, 'settings_')) {
            $providerId = substr($id, 9); // Remove 'settings_' prefix
            $settings = get_option(self::OPTION_NAME, []);
            $providerConfigs = $settings['provider_configs'] ?? [];
            $providerConfigs[$providerId] = [
                'api_key'  => $data['api_key'],
                'model'    => $data['model'] ?? '',
                'base_url' => $data['base_url'] ?? '',
                'credential_group_id' => $data['credential_group_id'] ?? '',
                'extra'    => $data['extra'] ?? [],
            ];
            $settings['provider_configs'] = $providerConfigs;
            update_option(self::OPTION_NAME, $settings);
            delete_transient('swc_settings_v2');
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Provider instance saved',
            'data' => ['id' => $id],
        ]);
    }

    /**
     * Update the API key/base URL shared by a provider credential group.
     */
    public function updateProviderInstanceCredentials(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        $provider = sanitize_text_field($request->get_param('provider') ?? '');
        $groupId = sanitize_text_field($request->get_param('credential_group_id') ?? $request->get_param('credentialGroupId') ?? '');
        $apiKey = sanitize_text_field($request->get_param('api_key') ?? $request->get_param('apiKey') ?? '');
        $params = $request->get_params();
        $baseUrl = array_key_exists('base_url', $params)
            ? esc_url_raw($request->get_param('base_url') ?? '')
            : null;

        if (empty($provider) || empty($groupId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Provider and credential group are required',
            ], 400);
        }

        $instances = \Quarksol\SmartChatbot\Services\ProviderResolver::ensureCredentialGroups();
        $groupInstances = array_values(array_filter($instances, function($inst) use ($provider, $groupId) {
            return ($inst['provider'] ?? '') === $provider
                && ($inst['credential_group_id'] ?? '') === $groupId;
        }));

        if (empty($groupInstances)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Credential group not found',
            ], 404);
        }

        if (!empty($apiKey)) {
            foreach ($groupInstances as $instance) {
                $existingKey = $instance['api_key'] ?? '';
                if (!empty($existingKey) && $apiKey === \Quarksol\SmartChatbot\Services\ProviderResolver::maskApiKey((string) $existingKey)) {
                    $apiKey = '';
                    break;
                }
            }
        }

        $result = \Quarksol\SmartChatbot\Services\ProviderResolver::updateCredentialGroup(
            $provider,
            $groupId,
            $apiKey !== '' ? $apiKey : null,
            $baseUrl
        );

        if ($result['updated'] > 0) {
            $settings = get_option(self::OPTION_NAME, []);
            $providerConfigs = $settings['provider_configs'] ?? [];
            $settingsChanged = false;

            foreach ($result['instances'] as $instance) {
                if (($instance['provider'] ?? '') !== $provider || ($instance['credential_group_id'] ?? '') !== $groupId) {
                    continue;
                }

                $instanceId = $instance['id'] ?? '';
                if (!str_starts_with($instanceId, 'settings_')) {
                    continue;
                }

                $providerId = substr($instanceId, 9);
                $providerConfigs[$providerId] = array_merge($providerConfigs[$providerId] ?? [], [
                    'api_key' => $instance['api_key'] ?? '',
                    'model' => $instance['model'] ?? ($providerConfigs[$providerId]['model'] ?? ''),
                    'base_url' => $instance['base_url'] ?? '',
                    'extra' => $instance['extra'] ?? ($providerConfigs[$providerId]['extra'] ?? []),
                ]);
                $settingsChanged = true;
            }

            if ($settingsChanged) {
                $settings['provider_configs'] = $providerConfigs;
                update_option(self::OPTION_NAME, $settings);
                delete_transient('swc_settings_v2');
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Credential group updated',
            'data' => [
                'updated' => $result['updated'],
            ],
        ]);
    }

    /**
     * Diagnose which provider an agent will resolve to.
     * Admin-only endpoint for testing/debugging.
     *
     * Usage: GET /smart-ai-chatbot/v1/provider-instances/diagnose?agent_id=5
     *        GET /smart-ai-chatbot/v1/provider-instances/diagnose?agent_slug=sales_assistant
     */
    public function diagnoseProviderForAgent(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        $agentId = (int) ($request->get_param('agent_id') ?? 0);
        $agentSlug = sanitize_text_field($request->get_param('agent_slug') ?? '');

        // Find agent
        $chatAgent = null;
        if ($agentId > 0) {
            $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find($agentId);
        } elseif (!empty($agentSlug)) {
            $chatAgent = \Quarksol\SmartChatbot\Models\ChatAgent::findBySlug($agentSlug);
        }

        if (!$chatAgent) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Agent not found. Provide agent_id (numeric DB ID) or agent_slug.',
            ], 404);
        }

        $config = $chatAgent->config;
        $providerInstanceId = $config->providerInstanceId ?? null;

        // Check what instance is stored
        $instanceData = null;
        if (!empty($providerInstanceId)) {
            $instanceData = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstance($providerInstanceId);
        }

        // Check default instance
        $defaultInstance = \Quarksol\SmartChatbot\Services\ProviderResolver::getDefaultInstance();

        // Check global/legacy settings
        $globalSettings = get_option('swc_chatbot_settings', []);
        $globalProvider = $globalSettings['ai_provider'] ?? 'not set';
        $globalModel = $globalSettings['ai_model'] ?? 'not set';

        // Determine which provider would actually be used
        $resolvedSource = 'unknown';
        $resolvedProvider = 'unknown';
        $resolvedModel = 'unknown';
        $resolvedInstanceId = null;

        if (!empty($providerInstanceId) && $instanceData) {
            $resolvedSource = '1. Agent-specific instance';
            $resolvedProvider = $instanceData['provider'] ?? 'unknown';
            $resolvedModel = $instanceData['model'] ?? 'default';
            $resolvedInstanceId = $providerInstanceId;
        } elseif (!empty($providerInstanceId) && !$instanceData) {
            // Instance ID set but not found — this is a bug
            $resolvedSource = ' BROKEN: Instance ID set but not found in storage!';
            $resolvedProvider = 'will fall through to default/global';
            if ($defaultInstance) {
                $resolvedSource .= ' → Falling back to default instance';
                $resolvedProvider = $defaultInstance['provider'] ?? 'unknown';
                $resolvedModel = $defaultInstance['model'] ?? 'default';
            } else {
                $resolvedSource .= ' → Falling back to global settings';
                $resolvedProvider = $globalProvider;
                $resolvedModel = $globalModel;
            }
        } elseif ($defaultInstance) {
            $resolvedSource = '2. Default provider instance';
            $resolvedProvider = $defaultInstance['provider'] ?? 'unknown';
            $resolvedModel = $defaultInstance['model'] ?? 'default';
            $resolvedInstanceId = $defaultInstance['id'] ?? null;
        } else {
            $resolvedSource = '3. Legacy global settings';
            $resolvedProvider = $globalProvider;
            $resolvedModel = $globalModel;
        }

        // Mask API key if present
        $hasApiKey = false;
        if ($instanceData && !empty($instanceData['api_key'])) {
            $hasApiKey = true;
        }

        return new \WP_REST_Response([
            'success' => true,
            'agent' => [
                'db_id' => $chatAgent->id,
                'slug' => $chatAgent->agentId,
                'name' => $chatAgent->name,
            ],
            'config' => [
                'provider_instance_id' => $providerInstanceId ?? '(not set — using default/global)',
            ],
            'resolution' => [
                'source' => $resolvedSource,
                'provider' => $resolvedProvider,
                'model' => $resolvedModel,
                'instance_id' => $resolvedInstanceId,
                'has_api_key' => $hasApiKey,
            ],
            'fallback_chain' => [
                'agent_instance' => $providerInstanceId ? [
                    'id' => $providerInstanceId,
                    'found' => $instanceData !== null,
                    'provider' => $instanceData['provider'] ?? null,
                    'display_name' => $instanceData['display_name'] ?? null,
                ] : null,
                'default_instance' => $defaultInstance ? [
                    'id' => $defaultInstance['id'] ?? null,
                    'provider' => $defaultInstance['provider'] ?? null,
                    'display_name' => $defaultInstance['display_name'] ?? null,
                ] : '(no default instance set)',
                'global_settings' => [
                    'provider' => $globalProvider,
                    'model' => $globalModel,
                ],
            ],
        ]);
    }

    /**
     * Delete a provider instance
     */
    public function deleteProviderInstance(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        $id = sanitize_text_field($request->get_param('id'));
        $deleted = \Quarksol\SmartChatbot\Services\ProviderResolver::deleteInstance($id);

        // If this was a settings-synced instance, also remove from provider_configs
        if ($deleted && str_starts_with($id, 'settings_')) {
            $providerId = substr($id, 9);
            $settings = get_option(self::OPTION_NAME, []);
            if (isset($settings['provider_configs'][$providerId])) {
                unset($settings['provider_configs'][$providerId]);
                update_option(self::OPTION_NAME, $settings);
                delete_transient('swc_settings_v2');
            }
        }

        return new \WP_REST_Response([
            'success' => $deleted,
            'message' => $deleted ? 'Provider instance deleted' : 'Instance not found',
        ]);
    }

    /**
     * Test a provider instance connection
     */
    public function testProviderInstance(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        $config = [
            'provider' => sanitize_text_field($request->get_param('provider') ?? ''),
            'api_key' => sanitize_text_field($request->get_param('api_key') ?? ''),
            'model' => sanitize_text_field($request->get_param('model') ?? ''),
            'base_url' => esc_url_raw($request->get_param('base_url') ?? ''),
            'extra' => is_array($request->get_param('extra')) ? $request->get_param('extra') : [],
        ];

        // Debug: log what we received
        $instanceId = $request->get_param('instance_id') ?? $request->get_param('instanceId') ?? '';
        error_log("[TestProvider] Received: provider={$config['provider']}, model={$config['model']}, api_key_empty=" . (empty($config['api_key']) ? 'YES' : 'NO') . ", instance_id={$instanceId}");

        // If testing an existing instance without providing full API key, look it up
        if (!empty($instanceId)) {
            $existing = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstance($instanceId);
            if ($existing) {
                // Merge stored values for any empty fields
                if (empty($config['api_key'])) {
                    $config['api_key'] = $existing['api_key'] ?? '';
                }
                if (empty($config['base_url']) && !empty($existing['base_url'])) {
                    $config['base_url'] = $existing['base_url'];
                }
                if (empty($config['model']) && !empty($existing['model'])) {
                    $config['model'] = $existing['model'];
                }
                error_log("[TestProvider]  Found stored instance '{$instanceId}', api_key_present=" . (!empty($config['api_key']) ? 'YES' : 'NO'));
            } else {
                error_log("[TestProvider]  Instance '{$instanceId}' NOT found in storage");
                
                // Fallback: try loading from provider_configs in settings
                if (str_starts_with($instanceId, 'settings_')) {
                    $providerId = substr($instanceId, 9);
                    $settings = get_option('swc_chatbot_settings', []);
                    $providerConfigs = $settings['provider_configs'] ?? [];
                    if (!empty($providerConfigs[$providerId]['api_key'])) {
                        $config['api_key'] = $providerConfigs[$providerId]['api_key'];
                        error_log("[TestProvider]  Found key in provider_configs for '{$providerId}'");
                    }
                }
            }
        }
        
        // Last resort: if api_key is still empty and provider matches, try global settings
        if (empty($config['api_key'])) {
            $settings = get_option('swc_chatbot_settings', []);
            $providerConfigs = $settings['provider_configs'] ?? [];
            if (!empty($providerConfigs[$config['provider']]['api_key'])) {
                $config['api_key'] = $providerConfigs[$config['provider']]['api_key'];
                error_log("[TestProvider]  Using global settings key for provider '{$config['provider']}'");
            }
        }

        $result = \Quarksol\SmartChatbot\Services\ProviderResolver::testInstance($config);

        return new \WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Get provider instances formatted for dropdown
     */
    public function getProviderInstancesDropdown(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProviderResolver')) {
            $bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap)) { require_once $bootstrap; }
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => \Quarksol\SmartChatbot\Services\ProviderResolver::getInstancesForDropdown(),
        ]);
    }
}
