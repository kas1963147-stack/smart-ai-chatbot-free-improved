<?php
/**
 * Cohere Provider Class
 * 
 * Handles Cohere's Command models.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Cohere_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.cohere.ai/v1';
    
    /**
     * Model information
     */
    private static $models = array(
        'command-r-plus' => array(
            'id' => 'command-r-plus',
            'name' => 'Command R+',
            'contextWindow' => 128000,
            'maxOutputTokens' => 4096
        ),
        'command-r' => array(
            'id' => 'command-r',
            'name' => 'Command R',
            'contextWindow' => 128000,
            'maxOutputTokens' => 4096
        ),
        'command' => array(
            'id' => 'command',
            'name' => 'Command',
            'contextWindow' => 4096,
            'maxOutputTokens' => 4096
        ),
        'command-light' => array(
            'id' => 'command-light',
            'name' => 'Command Light (Fast)',
            'contextWindow' => 4096,
            'maxOutputTokens' => 4096
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model = 'command-r-plus', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    /**
     * Get available models
     */
    public static function get_available_models() {
        return self::$models;
    }
    
    /**
     * Get model information
     */
    public function get_model() {
        if (isset(self::$models[$this->model_id])) {
            return self::$models[$this->model_id];
        }
        
        return array(
            'id' => $this->model_id,
            'name' => $this->model_id,
            'contextWindow' => 4096,
            'maxOutputTokens' => 4096
        );
    }
    
    /**
     * Build chat history for Cohere format
     */
    private function build_cohere_history($history) {
        $result = array();
        
        foreach ($history as $msg) {
            if ($msg['role'] === 'system') {
                continue; // Handle system prompt separately
            }
            
            $result[] = array(
                'role' => $msg['role'] === 'assistant' ? 'CHATBOT' : 'USER',
                'message' => $msg['content']
            );
        }
        
        return $result;
    }
    
    /**
     * Send a chat message and get a complete response
     */
    public function chat($message, $history = array()) {
        $chat_history = $this->build_cohere_history($history);
        
        $body = array(
            'model' => $this->model_id,
            'message' => $message,
            'temperature' => $this->temperature
        );
        
        if (!empty($chat_history)) {
            $body['chat_history'] = $chat_history;
        }
        
        if (!empty($this->system_prompt)) {
            $body['preamble'] = $this->system_prompt;
        }
        
        if ($this->max_tokens) {
            $body['max_tokens'] = $this->max_tokens;
        }
        
        $response = wp_remote_post($this->base_url . '/chat', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = isset($body['message']) ? $body['message'] : 'Unknown API error';
            return new WP_Error('cohere_error', 'Cohere API error: ' . $error_message);
        }
        
        if (isset($body['text'])) {
            return $body['text'];
        }
        
        return new WP_Error('cohere_error', 'Invalid response from Cohere');
    }
    
    /**
     * Send a chat message and stream the response
     */
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        
        if (is_wp_error($response)) {
            yield array('type' => 'error', 'error' => $response->get_error_message());
            return;
        }
        
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
