<?php
/**
 * Fireworks AI Provider Class
 * 
 * Handles Fireworks AI's hosted models.
 * Uses OpenAI-compatible API endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Fireworks_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.fireworks.ai/inference/v1';
    
    private static $models = array(
        'accounts/fireworks/models/llama-v3p3-70b-instruct' => array(
            'id' => 'accounts/fireworks/models/llama-v3p3-70b-instruct',
            'name' => 'Llama 3.3 70B',
            'contextWindow' => 131072,
            'maxOutputTokens' => 16384
        ),
        'accounts/fireworks/models/llama-v3p1-405b-instruct' => array(
            'id' => 'accounts/fireworks/models/llama-v3p1-405b-instruct',
            'name' => 'Llama 3.1 405B',
            'contextWindow' => 131072,
            'maxOutputTokens' => 16384
        ),
        'accounts/fireworks/models/qwen2p5-72b-instruct' => array(
            'id' => 'accounts/fireworks/models/qwen2p5-72b-instruct',
            'name' => 'Qwen 2.5 72B',
            'contextWindow' => 32768,
            'maxOutputTokens' => 8192
        ),
        'accounts/fireworks/models/deepseek-v3' => array(
            'id' => 'accounts/fireworks/models/deepseek-v3',
            'name' => 'DeepSeek V3',
            'contextWindow' => 65536,
            'maxOutputTokens' => 8192
        ),
        'accounts/fireworks/models/mixtral-8x22b-instruct' => array(
            'id' => 'accounts/fireworks/models/mixtral-8x22b-instruct',
            'name' => 'Mixtral 8x22B',
            'contextWindow' => 65536,
            'maxOutputTokens' => 8192
        )
    );
    
    public function __construct($api_key, $model = 'accounts/fireworks/models/llama-v3p3-70b-instruct', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() {
        return self::$models;
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
            'headers' => array('Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) return $response;
        
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status !== 200) {
            return new WP_Error('fireworks_error', 'Fireworks error: ' . ($body['error']['message'] ?? 'Unknown error'));
        }
        
        return $body['choices'][0]['message']['content'] ?? new WP_Error('fireworks_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
