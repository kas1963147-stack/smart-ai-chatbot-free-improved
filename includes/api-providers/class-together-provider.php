<?php
/**
 * Together AI Provider Class
 * 
 * Handles Together AI's hosted open-source models.
 * Uses OpenAI-compatible API endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Together_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.together.xyz/v1';
    
    /**
     * Model information
     */
    private static $models = array(
        'meta-llama/Llama-3.3-70B-Instruct-Turbo' => array(
            'id' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            'name' => 'Llama 3.3 70B Turbo',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo' => array(
            'id' => 'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo',
            'name' => 'Llama 3.1 405B',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192
        ),
        'Qwen/Qwen2.5-72B-Instruct-Turbo' => array(
            'id' => 'Qwen/Qwen2.5-72B-Instruct-Turbo',
            'name' => 'Qwen 2.5 72B',
            'contextWindow' => 32768,
            'maxOutputTokens' => 8192
        ),
        'deepseek-ai/DeepSeek-R1' => array(
            'id' => 'deepseek-ai/DeepSeek-R1',
            'name' => 'DeepSeek R1',
            'contextWindow' => 64000,
            'maxOutputTokens' => 8192
        ),
        'mistralai/Mixtral-8x22B-Instruct-v0.1' => array(
            'id' => 'mistralai/Mixtral-8x22B-Instruct-v0.1',
            'name' => 'Mixtral 8x22B',
            'contextWindow' => 65536,
            'maxOutputTokens' => 8192
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model = 'meta-llama/Llama-3.3-70B-Instruct-Turbo', $temperature = 0.7, $max_tokens = null) {
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
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            return new WP_Error('together_error', 'Together AI error: ' . $error_message);
        }
        
        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }
        
        return new WP_Error('together_error', 'Invalid response from Together AI');
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
