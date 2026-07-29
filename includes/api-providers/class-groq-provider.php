<?php
/**
 * Groq Provider Class
 * 
 * Handles Groq's ultra-fast inference API.
 * Uses OpenAI-compatible API endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Groq_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.groq.com/openai/v1';
    
    /**
     * Model information
     */
    private static $models = array(
        'llama-3.3-70b-versatile' => array(
            'id' => 'llama-3.3-70b-versatile',
            'name' => 'Llama 3.3 70B',
            'contextWindow' => 128000,
            'maxOutputTokens' => 32768
        ),
        'llama-3.1-8b-instant' => array(
            'id' => 'llama-3.1-8b-instant',
            'name' => 'Llama 3.1 8B (Fast)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'mixtral-8x7b-32768' => array(
            'id' => 'mixtral-8x7b-32768',
            'name' => 'Mixtral 8x7B',
            'contextWindow' => 32768,
            'maxOutputTokens' => 8192
        ),
        'gemma2-9b-it' => array(
            'id' => 'gemma2-9b-it',
            'name' => 'Gemma 2 9B',
            'contextWindow' => 8192,
            'maxOutputTokens' => 8192
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model = 'llama-3.3-70b-versatile', $temperature = 0.7, $max_tokens = null) {
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
            'contextWindow' => 32768,
            'maxOutputTokens' => 8192
        );
    }
    
    /**
     * Send a chat message and get a complete response
     */
    public function chat($message, $history = array()) {
        if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::debug('Groq Provider: Starting chat', [
                'model' => $this->model_id,
                'temperature' => $this->temperature
            ]);
        }
        
        $messages = $this->build_messages($message, $history);
        
        if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::debug('Groq Provider: Built messages', ['count' => count($messages)]);
        }
        
        $body = array(
            'model' => $this->model_id,
            'messages' => $messages,
            'temperature' => $this->temperature
        );
        
        if ($this->max_tokens) {
            $body['max_tokens'] = $this->max_tokens;
        }
        
        if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::debug('Groq Provider: Making API request', ['url' => $this->base_url . '/chat/completions']);
        }
        
        $response = wp_remote_post($this->base_url . '/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 60 // Increased timeout
        ));
        
        if (is_wp_error($response)) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Groq Provider: WP Error', ['error' => $response->get_error_message()]);
            }
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::debug('Groq Provider: Response received', ['status' => $status_code]);
        }
        
        $body = json_decode($response_body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Groq Provider: API Error', ['error' => $error_message, 'status' => $status_code]);
            }
            return new WP_Error('groq_error', 'Groq API error: ' . $error_message);
        }
        
        if (isset($body['choices'][0]['message']['content'])) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('Groq Provider: Success');
            }
            return $body['choices'][0]['message']['content'];
        }
        
        if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::error('Groq Provider: Invalid response', ['response' => substr($response_body, 0, 200)]);
        }
        return new WP_Error('groq_error', 'Invalid response from Groq');
    }
    
    /**
     * Send a chat message and stream the response
     */
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        
        if (is_wp_error($response)) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Groq Provider: WP Error', ['error' => $response->get_error_message()]);
            }
            yield array('type' => 'error', 'error' => $response->get_error_message());
            return;
        }
        
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
