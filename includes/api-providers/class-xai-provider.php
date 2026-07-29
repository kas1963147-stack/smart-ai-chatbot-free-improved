<?php
/**
 * xAI (Grok) Provider Class
 * 
 * Handles xAI's Grok models.
 * Uses OpenAI-compatible API endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_XAI_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.x.ai/v1';
    
    /**
     * Model information
     */
    private static $models = array(
        'grok-2-latest' => array(
            'id' => 'grok-2-latest',
            'name' => 'Grok 2',
            'contextWindow' => 131072,
            'maxOutputTokens' => 8192
        ),
        'grok-2-vision-latest' => array(
            'id' => 'grok-2-vision-latest',
            'name' => 'Grok 2 Vision',
            'contextWindow' => 32768,
            'maxOutputTokens' => 8192
        ),
        'grok-beta' => array(
            'id' => 'grok-beta',
            'name' => 'Grok Beta',
            'contextWindow' => 131072,
            'maxOutputTokens' => 8192
        )
    );
    
    public function __construct($api_key, $model = 'grok-2-latest', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() {
        return self::$models;
    }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 131072, 'maxOutputTokens' => 8192
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
            return new WP_Error('xai_error', 'xAI error: ' . ($body['error']['message'] ?? 'Unknown error'));
        }
        
        return $body['choices'][0]['message']['content'] ?? new WP_Error('xai_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
