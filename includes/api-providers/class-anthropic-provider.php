<?php
/**
 * Anthropic Provider Class
 * 
 * Handles Claude models from Anthropic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Anthropic_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.anthropic.com/v1';
    
    /**
     * Model information
     */
    private static $models = array(
        'claude-3-5-sonnet-20241022' => array(
            'id' => 'claude-3-5-sonnet-20241022',
            'name' => 'Claude 3.5 Sonnet',
            'contextWindow' => 200000,
            'maxOutputTokens' => 8192
        ),
        'claude-3-opus-20240229' => array(
            'id' => 'claude-3-opus-20240229',
            'name' => 'Claude 3 Opus',
            'contextWindow' => 200000,
            'maxOutputTokens' => 4096
        ),
        'claude-3-haiku-20240307' => array(
            'id' => 'claude-3-haiku-20240307',
            'name' => 'Claude 3 Haiku',
            'contextWindow' => 200000,
            'maxOutputTokens' => 4096
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model = 'claude-3-5-sonnet-20241022', $temperature = 0.7, $max_tokens = 4096) {
        parent::__construct($model, $temperature, $max_tokens ?: 4096);
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
            'contextWindow' => 200000,
            'maxOutputTokens' => 4096
        );
    }
    
    /**
     * Build Anthropic-specific messages (no system role in messages)
     */
    private function build_anthropic_messages($message, $history = array()) {
        $messages = array();
        
        // Add history (skip system messages)
        foreach ($history as $msg) {
            if ($msg['role'] !== 'system') {
                $messages[] = array(
                    'role' => $msg['role'],
                    'content' => $msg['content']
                );
            }
        }
        
        // Add current message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        return $messages;
    }
    
    /**
     * Send a chat message and get a complete response
     */
    public function chat($message, $history = array()) {
        $messages = $this->build_anthropic_messages($message, $history);
        
        $body = array(
            'model' => $this->model_id,
            'max_tokens' => $this->max_tokens,
            'messages' => $messages
        );
        
        // System prompt is a top-level parameter in Anthropic's API
        if (!empty($this->system_prompt)) {
            $body['system'] = $this->system_prompt;
        }
        
        $response = wp_remote_post($this->base_url . '/messages', array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
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
            return new WP_Error('anthropic_error', 'Anthropic API error: ' . $error_message);
        }
        
        // Extract text from content blocks
        if (isset($body['content']) && is_array($body['content'])) {
            foreach ($body['content'] as $block) {
                if ($block['type'] === 'text') {
                    return $block['text'];
                }
            }
        }
        
        return new WP_Error('anthropic_error', 'Invalid response from Anthropic');
    }
    
    /**
     * Send a chat message and stream the response
     */
    public function chat_stream($message, $history = array()) {
        $messages = $this->build_anthropic_messages($message, $history);
        
        $body = array(
            'model' => $this->model_id,
            'max_tokens' => $this->max_tokens,
            'messages' => $messages,
            'stream' => true
        );
        
        if (!empty($this->system_prompt)) {
            $body['system'] = $this->system_prompt;
        }
        
        $url = $this->base_url . '/messages';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key'         => $this->api_key,
                'Content-Type'      => 'application/json',
                'anthropic-version' => '2023-06-01',
                'Accept'            => 'text/event-stream'
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
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                $data = json_decode($json, true);
                
                if (isset($data['type']) && $data['type'] === 'content_block_delta') {
                    if (isset($data['delta']['type']) && $data['delta']['type'] === 'text_delta') {
                        yield array('type' => 'text', 'text' => $data['delta']['text']);
                    }
                }
                
                if (isset($data['type']) && $data['type'] === 'message_delta') {
                    if (isset($data['usage'])) {
                        yield array(
                            'type' => 'usage',
                            'inputTokens' => $data['usage']['input_tokens'] ?? 0,
                            'outputTokens' => $data['usage']['output_tokens'] ?? 0
                        );
                    }
                }
            }
        }
        
        yield array('type' => 'done');
    }
}
