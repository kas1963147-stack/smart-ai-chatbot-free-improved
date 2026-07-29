<?php
/**
 * LM Studio Provider Class
 * 
 * Local models via LM Studio - no API key required.
 * Uses OpenAI-compatible API endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_LMStudio_Provider extends SWC_Chatbot_AI_Provider {
    
    private $base_url;
    
    private static $models = array(
        'local-model' => array('id' => 'local-model', 'name' => 'Local Model (Auto-detect)', 'contextWindow' => 8192, 'maxOutputTokens' => 4096)
    );
    
    public function __construct($api_key = '', $model = 'local-model', $base_url = null, $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->base_url = $base_url ?: 'http://localhost:1234/v1';
    }
    
    public static function get_available_models() { return self::$models; }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 8192, 'maxOutputTokens' => 4096
        );
    }
    
    public function chat($message, $history = array()) {
        $messages = $this->build_messages($message, $history);
        $body = array('model' => $this->model_id, 'messages' => $messages, 'temperature' => $this->temperature);
        if ($this->max_tokens) $body['max_tokens'] = $this->max_tokens;
        
        $response = wp_remote_post($this->base_url . '/chat/completions', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            if (strpos($response->get_error_message(), 'Connection refused') !== false) {
                return new WP_Error('lmstudio_error', 'Cannot connect to LM Studio. Make sure it\'s running with Local Server enabled.');
            }
            return $response;
        }
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200) return new WP_Error('lmstudio_error', 'LM Studio error: ' . ($body['error']['message'] ?? 'Unknown'));
        return $body['choices'][0]['message']['content'] ?? new WP_Error('lmstudio_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
