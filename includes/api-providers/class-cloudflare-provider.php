<?php
/**
 * Cloudflare Workers AI Provider Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Cloudflare_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $account_id;
    private $base_url;
    
    private static $models = array(
        '@cf/meta/llama-3.3-70b-instruct-fp8-fast' => array('id' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast', 'name' => 'Llama 3.3 70B (Fast)', 'contextWindow' => 128000, 'maxOutputTokens' => 8192),
        '@cf/meta/llama-3.1-8b-instruct' => array('id' => '@cf/meta/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B', 'contextWindow' => 128000, 'maxOutputTokens' => 8192),
        '@cf/qwen/qwen1.5-14b-chat-awq' => array('id' => '@cf/qwen/qwen1.5-14b-chat-awq', 'name' => 'Qwen 1.5 14B', 'contextWindow' => 32768, 'maxOutputTokens' => 8192),
        '@cf/mistral/mistral-7b-instruct-v0.1' => array('id' => '@cf/mistral/mistral-7b-instruct-v0.1', 'name' => 'Mistral 7B', 'contextWindow' => 32768, 'maxOutputTokens' => 8192),
        '@hf/google/gemma-7b-it' => array('id' => '@hf/google/gemma-7b-it', 'name' => 'Gemma 7B', 'contextWindow' => 8192, 'maxOutputTokens' => 8192)
    );
    
    public function __construct($api_key, $model = '@cf/meta/llama-3.3-70b-instruct-fp8-fast', $account_id = null, $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
        $this->account_id = $account_id ?: '';
        $this->base_url = 'https://api.cloudflare.com/client/v4/accounts/' . $this->account_id . '/ai/run/' . $model;
    }
    
    public static function get_available_models() { return self::$models; }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 32768, 'maxOutputTokens' => 8192
        );
    }
    
    public function chat($message, $history = array()) {
        $messages = $this->build_messages($message, $history);
        $body = array('messages' => $messages);
        
        $response = wp_remote_post($this->base_url, array(
            'headers' => array('Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) return $response;
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200 || !$body['success']) return new WP_Error('cf_error', 'Cloudflare error: ' . ($body['errors'][0]['message'] ?? 'Unknown'));
        return $body['result']['response'] ?? new WP_Error('cf_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
