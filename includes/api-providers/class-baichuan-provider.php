<?php
/**
 * Baichuan Provider Class
 * 
 * Chinese AI provider.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Baichuan_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.baichuan-ai.com/v1';
    
    private static $models = array(
        'Baichuan4' => array('id' => 'Baichuan4', 'name' => 'Baichuan 4', 'contextWindow' => 32768, 'maxOutputTokens' => 4096),
        'Baichuan3-Turbo' => array('id' => 'Baichuan3-Turbo', 'name' => 'Baichuan 3 Turbo', 'contextWindow' => 32768, 'maxOutputTokens' => 4096),
        'Baichuan2-Turbo' => array('id' => 'Baichuan2-Turbo', 'name' => 'Baichuan 2 Turbo', 'contextWindow' => 32768, 'maxOutputTokens' => 4096)
    );
    
    public function __construct($api_key, $model = 'Baichuan4', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() { return self::$models; }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 32768, 'maxOutputTokens' => 4096
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
        if ($status !== 200) return new WP_Error('baichuan_error', 'Baichuan error: ' . ($body['error']['message'] ?? 'Unknown'));
        return $body['choices'][0]['message']['content'] ?? new WP_Error('baichuan_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
