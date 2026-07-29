<?php
/**
 * Perplexity Provider Class
 * 
 * Handles Perplexity's online search-augmented AI models.
 * Uses OpenAI-compatible API endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Perplexity_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.perplexity.ai';
    
    /**
     * Model information
     */
    private static $models = array(
        'llama-3.1-sonar-large-128k-online' => array(
            'id' => 'llama-3.1-sonar-large-128k-online',
            'name' => 'Sonar Large (Online)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'llama-3.1-sonar-small-128k-online' => array(
            'id' => 'llama-3.1-sonar-small-128k-online',
            'name' => 'Sonar Small (Online)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'llama-3.1-sonar-large-128k-chat' => array(
            'id' => 'llama-3.1-sonar-large-128k-chat',
            'name' => 'Sonar Large (Chat)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'llama-3.1-sonar-small-128k-chat' => array(
            'id' => 'llama-3.1-sonar-small-128k-chat',
            'name' => 'Sonar Small (Chat)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model = 'llama-3.1-sonar-large-128k-online', $temperature = 0.7, $max_tokens = null) {
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
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        );
    }
    
    /**
     * Send a chat message and get a complete response
     */
    public function chat($message, $history = array()) {
        $messages = $this->build_messages($message, $history);
        
        $body = array(
            'model' => $this->model_id,
            'messages' => $messages,
            'temperature' => $this->temperature
        );
        
        if ($this->max_tokens) {
            $body['max_tokens'] = $this->max_tokens;
        }
        
        $response = wp_remote_post($this->base_url . '/chat/completions', array(
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
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            return new WP_Error('perplexity_error', 'Perplexity API error: ' . $error_message);
        }
        
        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }
        
        return new WP_Error('perplexity_error', 'Invalid response from Perplexity');
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
