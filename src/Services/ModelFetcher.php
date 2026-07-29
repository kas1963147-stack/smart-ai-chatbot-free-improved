<?php
declare(strict_types=1);


/**
 * Model Fetcher Service
 * 
 * Unified service to fetch available models from AI provider APIs.
 * Supports 16 providers with caching via WordPress transients.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ModelFetcher
{
    /** Cache duration in seconds (5 minutes) */
    private const CACHE_TTL = 300;

    /** Cache key prefix */
    private const CACHE_PREFIX = 'swc_models_';

    /**
     * Providers that support model listing without an API key
     */
    private const NO_KEY_PROVIDERS = ['openrouter', 'huggingface', 'ollama', 'lmstudio'];

    /**
     * Providers that support dynamic model listing
     */
    public const SUPPORTED_PROVIDERS = [
        'openrouter', 'openai', 'anthropic', 'gemini', 'groq', 'deepseek',
        'mistral', 'xai', 'fireworks', 'cerebras', 'sambanova', 'huggingface',
        'deepinfra', 'together', 'ollama', 'lmstudio',
    ];

    /**
     * Fetch models for a provider
     * 
     * @param string $provider Provider ID
     * @param string|null $apiKey API key (required for most providers)
     * @param string|null $baseUrl Custom base URL (for Ollama/LM Studio/custom)
     * @param bool $forceRefresh Skip cache
     * @return array Normalized model list
     */
    public static function fetchModels(string $provider, ?string $apiKey = null, ?string $baseUrl = null, bool $forceRefresh = false): array
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return [];
        }

        // Check if API key is required but not provided
        if (!in_array($provider, self::NO_KEY_PROVIDERS, true) && empty($apiKey)) {
            return [];
        }

        // Check cache first
        $cacheKey = self::CACHE_PREFIX . $provider;
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch from API
        $models = match ($provider) {
            'openrouter'  => self::fetchOpenRouter(),
            'openai'      => self::fetchOpenAICompatible('https://api.openai.com/v1/models', $apiKey, 'gpt'),
            'anthropic'   => self::fetchAnthropic($apiKey),
            'gemini'      => self::fetchGemini($apiKey),
            'groq'        => self::fetchOpenAICompatible('https://api.groq.com/openai/v1/models', $apiKey),
            'deepseek'    => self::fetchOpenAICompatible('https://api.deepseek.com/v1/models', $apiKey),
            'mistral'     => self::fetchOpenAICompatible('https://api.mistral.ai/v1/models', $apiKey),
            'xai'         => self::fetchOpenAICompatible('https://api.x.ai/v1/models', $apiKey),
            'fireworks'   => self::fetchOpenAICompatible('https://api.fireworks.ai/inference/v1/models', $apiKey),
            'cerebras'    => self::fetchOpenAICompatible('https://api.cerebras.ai/v1/models', $apiKey),
            'sambanova'   => self::fetchOpenAICompatible('https://api.sambanova.ai/v1/models', $apiKey),
            'huggingface' => self::fetchHuggingFace($apiKey),
            'deepinfra'   => self::fetchOpenAICompatible('https://api.deepinfra.com/v1/openai/models', $apiKey),
            'together'    => self::fetchOpenAICompatible('https://api.together.xyz/v1/models', $apiKey),
            'ollama'      => self::fetchOllama($baseUrl),
            'lmstudio'    => self::fetchOpenAICompatible(rtrim($baseUrl ?: 'http://localhost:1234', '/') . '/v1/models', null),
            default       => [],
        };

        // Enrich models with pricing from pricing.json (for providers that don't include pricing in their API)
        if (!empty($models)) {
            $models = self::enrichWithPricing($provider, $models);
            set_transient($cacheKey, $models, self::CACHE_TTL);
        }

        return $models;
    }

    /**
     * Check if a provider supports dynamic model listing
     */
    public static function isSupported(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED_PROVIDERS, true);
    }

    /**
     * Check if a provider needs an API key to list models
     */
    public static function requiresKey(string $provider): bool
    {
        return !in_array($provider, self::NO_KEY_PROVIDERS, true);
    }

    // =========================================================================
    // Provider-Specific Fetchers
    // =========================================================================

    /**
     * OpenRouter — public API, no key needed
     * Already implemented in class-openrouter-provider.php, but we duplicate the
     * logic here for consistency and to avoid loading the legacy provider class.
     */
    private static function fetchOpenRouter(): array
    {
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
            'timeout' => 30,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $body = self::parseResponse($response);
        if ($body === null || empty($body['data'])) {
            return [];
        }

        $models = [];
        foreach ($body['data'] as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) continue;

            // Skip image-only models
            $outputModalities = $model['architecture']['output_modalities'] ?? [];
            if (in_array('image', $outputModalities) && !in_array('text', $outputModalities)) continue;

            // OpenRouter pricing is per-token — convert to per-million-tokens for display
            $promptPrice = (float)($model['pricing']['prompt'] ?? 0);
            $completionPrice = (float)($model['pricing']['completion'] ?? 0);
            $isFree = ($promptPrice == 0 && $completionPrice == 0);
            $inputPricePerMillion = $promptPrice * 1_000_000;
            $outputPricePerMillion = $completionPrice * 1_000_000;

            $models[] = self::normalizeModel(
                $id,
                $model['name'] ?? $id,
                (int)($model['context_length'] ?? 4096),
                $isFree,
                $model['description'] ?? '',
                $inputPricePerMillion,
                $outputPricePerMillion
            );
        }

        // Sort: free first, then alphabetical
        usort($models, function ($a, $b) {
            if ($a['is_free'] !== $b['is_free']) return $a['is_free'] ? -1 : 1;
            return strcmp($a['id'], $b['id']);
        });

        return $models;
    }

    /**
     * OpenAI-compatible `/v1/models` endpoint
     * Works for: OpenAI, Groq, DeepSeek, Mistral, xAI, Fireworks, Cerebras,
     *            SambaNova, DeepInfra, Together AI, LM Studio
     * 
     * @param string $url Full URL to the models endpoint
     * @param string|null $apiKey Bearer token
     * @param string|null $filterPrefix Only include models whose ID starts with this prefix
     */
    private static function fetchOpenAICompatible(string $url, ?string $apiKey, ?string $filterPrefix = null): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => $headers,
        ]);

        $body = self::parseResponse($response);
        if ($body === null) {
            return [];
        }

        // OpenAI-compatible APIs return { data: [...] }
        $data = $body['data'] ?? $body['models'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        $models = [];
        foreach ($data as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) continue;

            // Apply prefix filter if set (e.g., 'gpt' for OpenAI to skip embedding models)
            if ($filterPrefix && !str_starts_with($id, $filterPrefix) && !str_contains($id, 'o1') && !str_contains($id, 'o3') && !str_contains($id, 'o4')) continue;

            // Skip known non-chat models
            if (self::isNonChatModel($id)) continue;

            $name = $model['name'] ?? $model['id'] ?? $id;
            $contextLength = (int)($model['context_length'] ?? $model['context_window'] ?? 0);

            $models[] = self::normalizeModel($id, $name, $contextLength);
        }

        // Sort alphabetically by ID
        usort($models, fn($a, $b) => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * Anthropic — uses x-api-key header and different response format
     */
    private static function fetchAnthropic(?string $apiKey): array
    {
        if (empty($apiKey)) return [];

        $response = wp_remote_get('https://api.anthropic.com/v1/models', [
            'timeout' => 30,
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Accept' => 'application/json',
            ],
        ]);

        $body = self::parseResponse($response);
        if ($body === null) {
            return [];
        }

        // Anthropic returns { data: [...] }
        $data = $body['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        $models = [];
        foreach ($data as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) continue;

            $name = $model['display_name'] ?? $model['name'] ?? $id;

            $models[] = self::normalizeModel(
                $id,
                $name,
                (int)($model['context_window'] ?? 200000)
            );
        }

        usort($models, fn($a, $b) => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * Google Gemini — uses x-goog-api-key and different response structure
     */
    private static function fetchGemini(?string $apiKey): array
    {
        if (empty($apiKey)) return [];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($apiKey);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $body = self::parseResponse($response);
        if ($body === null) {
            return [];
        }

        // Gemini returns { models: [...] }
        $data = $body['models'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        $models = [];
        foreach ($data as $model) {
            $fullName = $model['name'] ?? '';
            if (empty($fullName)) continue;

            // Model name is like "models/gemini-1.5-pro" — extract just the model part
            $id = str_replace('models/', '', $fullName);

            // Only include generative models
            $methods = $model['supportedGenerationMethods'] ?? [];
            if (!in_array('generateContent', $methods)) continue;

            $displayName = $model['displayName'] ?? $id;
            $contextLength = (int)($model['inputTokenLimit'] ?? 0);

            $models[] = self::normalizeModel($id, $displayName, $contextLength);
        }

        usort($models, fn($a, $b) => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * HuggingFace — public API, filters for text-generation pipeline
     */
    private static function fetchHuggingFace(?string $apiKey = null): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = wp_remote_get('https://huggingface.co/api/models?pipeline_tag=text-generation&sort=downloads&direction=-1&limit=100', [
            'timeout' => 30,
            'headers' => $headers,
        ]);

        $body = self::parseResponse($response);
        if ($body === null || !is_array($body)) {
            return [];
        }

        $models = [];
        foreach ($body as $model) {
            $id = $model['modelId'] ?? $model['id'] ?? '';
            if (empty($id)) continue;

            $models[] = self::normalizeModel(
                $id,
                $id, // HuggingFace uses modelId as the display name
                0
            );
        }

        return $models;
    }

    /**
     * Ollama — local API at configurable base URL
     */
    private static function fetchOllama(?string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl ?: 'http://localhost:11434', '/');
        $url = $baseUrl . '/api/tags';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $body = self::parseResponse($response);
        if ($body === null) {
            return [];
        }

        // Ollama returns { models: [...] }
        $data = $body['models'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        $models = [];
        foreach ($data as $model) {
            $name = $model['name'] ?? '';
            if (empty($name)) continue;

            $models[] = self::normalizeModel(
                $name,
                $model['model'] ?? $name,
                0,
                true // Local models are free
            );
        }

        usort($models, fn($a, $b) => strcmp($a['id'], $b['id']));

        return $models;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Normalize a model to a consistent structure
     */
    private static function normalizeModel(
        string $id,
        string $name,
        int $contextLength = 0,
        bool $isFree = false,
        string $description = '',
        ?float $inputPrice = null,
        ?float $outputPrice = null
    ): array {
        $model = [
            'id'             => $id,
            'name'           => $name,
            'context_length' => $contextLength,
            'is_free'        => $isFree,
            'description'    => $description,
        ];

        // Include pricing if available (per million tokens, USD)
        if ($inputPrice !== null) {
            $model['input_price'] = round($inputPrice, 4);
        }
        if ($outputPrice !== null) {
            $model['output_price'] = round($outputPrice, 4);
        }

        return $model;
    }

    /**
     * Parse HTTP response to JSON
     */
    private static function parseResponse($response): ?array
    {
        if (is_wp_error($response)) {
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return null;
        }

        return $body;
    }

    /**
     * Check if a model ID is a non-chat model (embeddings, TTS, etc.)
     */
    private static function isNonChatModel(string $id): bool
    {
        $nonChatPrefixes = [
            'text-embedding', 'embedding', 'tts-', 'whisper', 'dall-e',
            'davinci', 'babbage', 'ada', 'curie',  // Legacy completion models
            'text-moderation', 'moderation',
            'canary-', // Canary/test models
        ];

        $idLower = strtolower($id);
        foreach ($nonChatPrefixes as $prefix) {
            if (str_starts_with($idLower, $prefix)) {
                return true;
            }
        }

        // Also skip if the model contains known non-chat keywords
        $nonChatKeywords = ['embedding', 'tts', 'whisper', 'realtime', 'audio-preview'];
        foreach ($nonChatKeywords as $keyword) {
            if (str_contains($idLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enrich models with pricing from pricing.json for providers that don't include pricing in their API.
     * Models that already have pricing set (e.g., OpenRouter) are left unchanged.
     *
     * @param string $provider Provider ID
     * @param array $models Normalized model list
     * @return array Models with pricing data attached where available
     */
    private static function enrichWithPricing(string $provider, array $models): array
    {
        // Load the built-in pricing database
        $pricingJsonPath = dirname(__DIR__) . '/Analytics/pricing.json';
        if (!file_exists($pricingJsonPath)) {
            return $models;
        }

        static $pricingDb = null;
        if ($pricingDb === null) {
            $json = file_get_contents($pricingJsonPath);
            $pricingDb = json_decode($json, true) ?: [];

            // Merge _free_providers into the main array
            if (isset($pricingDb['_free_providers'])) {
                foreach ($pricingDb['_free_providers'] as $fp => $fm) {
                    $pricingDb[$fp] = $fm;
                }
                unset($pricingDb['_free_providers']);
            }
            // Remove metadata keys
            foreach (array_keys($pricingDb) as $key) {
                if (str_starts_with($key, '_')) {
                    unset($pricingDb[$key]);
                }
            }
        }

        $providerPricing = $pricingDb[$provider] ?? [];
        if (empty($providerPricing)) {
            return $models;
        }

        foreach ($models as &$model) {
            // Skip if already has pricing (e.g., OpenRouter models)
            if (isset($model['input_price']) || isset($model['output_price'])) {
                continue;
            }

            $modelId = $model['id'];

            // Try exact match first
            if (isset($providerPricing[$modelId])) {
                $prices = $providerPricing[$modelId];
                $model['input_price'] = round((float)($prices['input'] ?? 0), 4);
                $model['output_price'] = round((float)($prices['output'] ?? 0), 4);
                if ($model['input_price'] == 0 && $model['output_price'] == 0) {
                    $model['is_free'] = true;
                }
            }
            // Try wildcard pricing (e.g., ollama -> "*")
            elseif (isset($providerPricing['*'])) {
                $prices = $providerPricing['*'];
                $model['input_price'] = round((float)($prices['input'] ?? 0), 4);
                $model['output_price'] = round((float)($prices['output'] ?? 0), 4);
                if ($model['input_price'] == 0 && $model['output_price'] == 0) {
                    $model['is_free'] = true;
                }
            }
            // No pricing found — fields stay absent, frontend handles undefined gracefully
        }
        unset($model);

        return $models;
    }
}
