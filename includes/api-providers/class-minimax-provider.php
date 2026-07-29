<?php
/**
 * Minimax Provider Class
 * 
 * Chinese AI provider.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Minimax_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.minimax.chat/v1';
    
    private static $models = array(
        'abab6.5s-chat' => array('id' => 'abab6.5s-chat', 'name' => 'ABAB 6.5S', 'contextWindow' => 245760, 'maxOutputTokens' => 8192),
        'abab6.5t-chat' => array('id' => 'abab6.5t-chat', 'name' => 'ABAB 6.5T', 'contextWindow' => 8192, 'maxOutputTokens' => 4096),
        'abab6.5g-chat' => array('id' => 'abab6.5g-chat', 'name' => 'ABAB 6.5G', 'contextWindow' => 8192, 'maxOutputTokens' => 4096)
    );
    
    public function __construct($api_key, $model = 'abab6.5s-chat', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
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
        
        $response = wp_remote_post($this->base_url . '/text/chatcompletion_v2', array(
            'headers' => array('Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) return $response;
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200) return new WP_Error('minimax_error', 'Minimax error: ' . ($body['base_resp']['status_msg'] ?? 'Unknown'));
        return $body['choices'][0]['message']['content'] ?? new WP_Error('minimax_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
