<?php
/**
 * Ollama Provider Class
 * 
 * Handles local Ollama models - no API key required.
 * Uses OpenAI-compatible API endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Ollama_Provider extends SWC_Chatbot_AI_Provider {
    
    private $base_url;
    
    /**
     * Model information (common local models)
     */
    private static $models = array(
        'llama3.2' => array(
            'id' => 'llama3.2',
            'name' => 'Llama 3.2 (3B)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'llama3.1' => array(
            'id' => 'llama3.1',
            'name' => 'Llama 3.1 (8B)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'mistral' => array(
            'id' => 'mistral',
            'name' => 'Mistral 7B',
            'contextWindow' => 32000,
            'maxOutputTokens' => 8192
        ),
        'codellama' => array(
            'id' => 'codellama',
            'name' => 'CodeLlama',
            'contextWindow' => 16000,
            'maxOutputTokens' => 8192
        ),
        'phi3' => array(
            'id' => 'phi3',
            'name' => 'Phi-3 Mini',
            'contextWindow' => 4096,
            'maxOutputTokens' => 4096
        ),
        'gemma2' => array(
            'id' => 'gemma2',
            'name' => 'Gemma 2',
            'contextWindow' => 8192,
            'maxOutputTokens' => 8192
        ),
        'qwen2.5' => array(
            'id' => 'qwen2.5',
            'name' => 'Qwen 2.5',
            'contextWindow' => 32768,
            'maxOutputTokens' => 8192
        ),
        'deepseek-r1' => array(
            'id' => 'deepseek-r1',
            'name' => 'DeepSeek R1',
            'contextWindow' => 64000,
            'maxOutputTokens' => 8192
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key = '', $model = 'llama3.2', $base_url = null, $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        // Default to localhost:11434
        $this->base_url = $base_url ?: 'http://localhost:11434/v1';
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
            'contextWindow' => 8192,
            'maxOutputTokens' => 4096
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
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 120 // Local models can be slow
        ));
        
        if (is_wp_error($response)) {
            // Check if Ollama is running
            if (strpos($response->get_error_message(), 'Connection refused') !== false) {
                return new WP_Error('ollama_error', 'Cannot connect to Ollama. Make sure Ollama is running (ollama serve).');
            }
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            return new WP_Error('ollama_error', 'Ollama error: ' . $error_message);
        }
        
        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }
        
        return new WP_Error('ollama_error', 'Invalid response from Ollama');
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
