<?php
/**
 * AI21 Labs Provider Class
 * 
 * Handles AI21's Jamba and Jurassic models.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_AI21_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.ai21.com/studio/v1';
    
    private static $models = array(
        'jamba-1.5-large' => array(
            'id' => 'jamba-1.5-large',
            'name' => 'Jamba 1.5 Large',
            'contextWindow' => 256000,
            'maxOutputTokens' => 4096
        ),
        'jamba-1.5-mini' => array(
            'id' => 'jamba-1.5-mini',
            'name' => 'Jamba 1.5 Mini',
            'contextWindow' => 256000,
            'maxOutputTokens' => 4096
        ),
        'j2-ultra' => array(
            'id' => 'j2-ultra',
            'name' => 'Jurassic-2 Ultra',
            'contextWindow' => 8192,
            'maxOutputTokens' => 8192
        ),
        'j2-mid' => array(
            'id' => 'j2-mid',
            'name' => 'Jurassic-2 Mid',
            'contextWindow' => 8192,
            'maxOutputTokens' => 8192
        )
    );
    
    public function __construct($api_key, $model = 'jamba-1.5-large', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() {
        return self::$models;
    }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 8192, 'maxOutputTokens' => 4096
        );
    }
    
    public function chat($message, $history = array()) {
        $messages = $this->build_messages($message, $history);
        
        // AI21 uses chat/completions for Jamba models
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
            return new WP_Error('ai21_error', 'AI21 error: ' . ($body['detail'] ?? $body['error'] ?? 'Unknown error'));
        }
        
        return $body['choices'][0]['message']['content'] ?? new WP_Error('ai21_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
