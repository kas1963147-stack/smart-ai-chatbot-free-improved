<?php
/**
 * OpenRouter Provider Class
 * 
 * Meta-provider that gives access to 200+ models from multiple providers.
 * Single API key, access to OpenAI, Anthropic, Google, Meta, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_OpenRouter_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    
    private static $models = array(
        'openai/gpt-4o' => array('id' => 'openai/gpt-4o', 'name' => 'GPT-4o (via OpenRouter)', 'contextWindow' => 128000, 'maxOutputTokens' => 16384),
        'anthropic/claude-3.5-sonnet' => array('id' => 'anthropic/claude-3.5-sonnet', 'name' => 'Claude 3.5 Sonnet', 'contextWindow' => 200000, 'maxOutputTokens' => 8192),
        'google/gemini-2.0-flash-exp:free' => array('id' => 'google/gemini-2.0-flash-exp:free', 'name' => 'Gemini 2.0 Flash (Free)', 'contextWindow' => 1048576, 'maxOutputTokens' => 8192),
        'meta-llama/llama-3.3-70b-instruct' => array('id' => 'meta-llama/llama-3.3-70b-instruct', 'name' => 'Llama 3.3 70B', 'contextWindow' => 131072, 'maxOutputTokens' => 8192),
        'deepseek/deepseek-r1' => array('id' => 'deepseek/deepseek-r1', 'name' => 'DeepSeek R1', 'contextWindow' => 64000, 'maxOutputTokens' => 8192),
        'qwen/qwen-2.5-72b-instruct' => array('id' => 'qwen/qwen-2.5-72b-instruct', 'name' => 'Qwen 2.5 72B', 'contextWindow' => 32768, 'maxOutputTokens' => 8192),
        'mistralai/mistral-large-2411' => array('id' => 'mistralai/mistral-large-2411', 'name' => 'Mistral Large', 'contextWindow' => 128000, 'maxOutputTokens' => 8192),
        'x-ai/grok-2-1212' => array('id' => 'x-ai/grok-2-1212', 'name' => 'Grok 2', 'contextWindow' => 131072, 'maxOutputTokens' => 8192),
        'cohere/command-r-plus' => array('id' => 'cohere/command-r-plus', 'name' => 'Command R+', 'contextWindow' => 128000, 'maxOutputTokens' => 4096),
        'perplexity/llama-3.1-sonar-large-128k-online' => array('id' => 'perplexity/llama-3.1-sonar-large-128k-online', 'name' => 'Perplexity Sonar (Online)', 'contextWindow' => 128000, 'maxOutputTokens' => 8192)
    );
    
    public function __construct($api_key, $model = 'openai/gpt-4o', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() {
        return self::$models;
    }
    
    /**
     * Get cached models from OpenRouter API
     * Uses WordPress transients for 5 minute caching
     * 
     * @return array List of models with id, name, context_length, pricing, and is_free flag
     */
    public static function get_cached_models() {
        $cache_key = 'swc_openrouter_models';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $models = self::fetch_models_from_api();
        
        if (!empty($models)) {
            set_transient($cache_key, $models, 5 * MINUTE_IN_SECONDS);
        }
        
        return $models;
    }
    
    /**
     * Fetch models from OpenRouter public API
     * No API key required — this is a public endpoint
     * 
     * @return array List of models
     */
    public static function fetch_models_from_api() {
        $url = 'https://openrouter.ai/api/v1/models';
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));
        
        if (is_wp_error($response)) {
            // Fallback to hardcoded models
            return self::format_hardcoded_models();
        }
        
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return self::format_hardcoded_models();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['data']) || !is_array($body['data'])) {
            return self::format_hardcoded_models();
        }
        
        $models = array();
        
        foreach ($body['data'] as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) continue;
            
            // Skip image generation models
            $output_modalities = $model['architecture']['output_modalities'] ?? array();
            if (in_array('image', $output_modalities)) continue;
            
            $prompt_price = (float)($model['pricing']['prompt'] ?? 0);
            $completion_price = (float)($model['pricing']['completion'] ?? 0);
            $is_free = ($prompt_price == 0 && $completion_price == 0);
            
            $models[] = array(
                'id'             => $id,
                'name'           => $model['name'] ?? $id,
                'context_length' => $model['context_length'] ?? 4096,
                'input_price'    => $prompt_price * 1000000, // Convert per-token to per-million
                'output_price'   => $completion_price * 1000000,
                'is_free'        => $is_free,
                'supports_images'=> in_array('image', $model['architecture']['input_modalities'] ?? array()),
                'description'    => $model['description'] ?? '',
            );
        }
        
        // Sort: free models first, then alphabetically by id
        usort($models, function($a, $b) {
            if ($a['is_free'] !== $b['is_free']) {
                return $a['is_free'] ? -1 : 1;
            }
            return strcmp($a['id'], $b['id']);
        });
        
        return $models;
    }
    
    /**
     * Format hardcoded models as fallback (same structure as API response)
     */
    private static function format_hardcoded_models() {
        $result = array();
        foreach (self::$models as $id => $model) {
            $result[] = array(
                'id'             => $id,
                'name'           => $model['name'],
                'context_length' => $model['contextWindow'],
                'input_price'    => 0,
                'output_price'   => 0,
                'is_free'        => false,
                'supports_images'=> false,
                'description'    => '',
            );
        }
        return $result;
    }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 32768, 'maxOutputTokens' => 8192
        );
    }
    
    public function chat($message, $history = array()) {
        $messages = $this->build_messages($message, $history);
        
        $body = array('model' => $this->model_id, 'messages' => $messages, 'temperature' => $this->temperature);
        if ($this->max_tokens) $body['max_tokens'] = $this->max_tokens;
        
        $response = wp_remote_post($this->base_url . '/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => wp_json_encode($body),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) return $response;
        
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status !== 200) {
            return new WP_Error('openrouter_error', 'OpenRouter error: ' . ($body['error']['message'] ?? 'Unknown error'));
        }
        
        return $body['choices'][0]['message']['content'] ?? new WP_Error('openrouter_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
