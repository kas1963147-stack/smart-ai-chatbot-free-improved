<?php
/**
 * Hyperbolic Provider Class
 * 
 * Handles Hyperbolic's inference API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Hyperbolic_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.hyperbolic.xyz/v1';
    
    private static $models = array(
        'meta-llama/Llama-3.3-70B-Instruct' => array('id' => 'meta-llama/Llama-3.3-70B-Instruct', 'name' => 'Llama 3.3 70B', 'contextWindow' => 128000, 'maxOutputTokens' => 8192),
        'Qwen/Qwen2.5-72B-Instruct' => array('id' => 'Qwen/Qwen2.5-72B-Instruct', 'name' => 'Qwen 2.5 72B', 'contextWindow' => 32768, 'maxOutputTokens' => 8192),
        'deepseek-ai/DeepSeek-V3' => array('id' => 'deepseek-ai/DeepSeek-V3', 'name' => 'DeepSeek V3', 'contextWindow' => 65536, 'maxOutputTokens' => 8192),
        'meta-llama/Llama-3.2-3B-Instruct' => array('id' => 'meta-llama/Llama-3.2-3B-Instruct', 'name' => 'Llama 3.2 3B (Fast)', 'contextWindow' => 128000, 'maxOutputTokens' => 8192)
    );
    
    public function __construct($api_key, $model = 'meta-llama/Llama-3.3-70B-Instruct', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() { return self::$models; }
    
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
            'headers' => array('Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) return $response;
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200) return new WP_Error('hyperbolic_error', 'Hyperbolic error: ' . ($body['error']['message'] ?? 'Unknown'));
        return $body['choices'][0]['message']['content'] ?? new WP_Error('hyperbolic_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
