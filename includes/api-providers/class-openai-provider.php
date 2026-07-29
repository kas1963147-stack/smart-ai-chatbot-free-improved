<?php
/**
 * OpenAI Provider Class
 * 
 * Handles OpenAI and OpenAI-compatible API endpoints.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_OpenAI_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url;
    
    /**
     * Model information
     */
    private static $models = array(
        'gpt-4o' => array(
            'id' => 'gpt-4o',
            'name' => 'GPT-4o',
            'contextWindow' => 128000,
            'maxOutputTokens' => 16384
        ),
        'gpt-4o-mini' => array(
            'id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'contextWindow' => 128000,
            'maxOutputTokens' => 16384
        ),
        'gpt-4-turbo' => array(
            'id' => 'gpt-4-turbo',
            'name' => 'GPT-4 Turbo',
            'contextWindow' => 128000,
            'maxOutputTokens' => 4096
        ),
        'gpt-3.5-turbo' => array(
            'id' => 'gpt-3.5-turbo',
            'name' => 'GPT-3.5 Turbo',
            'contextWindow' => 16385,
            'maxOutputTokens' => 4096
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model = 'gpt-4o-mini', $base_url = null, $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
        $this->base_url = $base_url ?: 'https://api.openai.com/v1';
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
            return new WP_Error('openai_error', 'OpenAI API error: ' . $error_message);
        }
        
        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }
        
        return new WP_Error('openai_error', 'Invalid response from OpenAI');
    }
    
    /**
     * Send a chat message and stream the response
     * Note: This is a simplified streaming implementation
     */
    public function chat_stream($message, $history = array()) {
        $messages = $this->build_messages($message, $history);
        
        $body = array(
            'model' => $this->model_id,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'stream' => true
        );
        
        if ($this->max_tokens) {
            $body['max_tokens'] = $this->max_tokens;
        }
        
        // For streaming, we need to use a custom approach
        $url = $this->base_url . '/chat/completions';
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 120,
            'stream' => true,
            'filename' => false
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'text/event-stream'
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            yield array('type' => 'error', 'error' => $response->get_error_message());
            return;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $result = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            $error_body = json_decode($result, true);
            $error_message = isset($error_body['error']['message']) ? $error_body['error']['message'] : 'API request failed';
            yield array('type' => 'error', 'error' => $error_message);
            return;
        }
        
        // Parse SSE response
        $lines = explode("\n", $result);
        $full_content = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === 'data: [DONE]') {
                continue;
            }
            
            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                $data = json_decode($json, true);
                
                if (isset($data['choices'][0]['delta']['content'])) {
                    $text = $data['choices'][0]['delta']['content'];
                    $full_content .= $text;
                    yield array('type' => 'text', 'text' => $text);
                }
            }
        }
        
        yield array('type' => 'done');
    }
}
