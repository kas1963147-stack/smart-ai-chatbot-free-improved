<?php
/**
 * Azure OpenAI Provider Class
 * 
 * Handles Azure-hosted OpenAI models including GPT-5 and reasoning models.
 * Updated to support newer API versions and reasoning model parameters.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Azure_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url;
    private $api_version = '2024-05-01-preview';
    
    /**
     * Model information (deployment names vary by customer)
     */
    private static $models = array(
        // GPT-5 Series (Reasoning Models)
        'gpt-5-mini' => array(
            'id' => 'gpt-5-mini',
            'name' => 'GPT-5 Mini (Azure)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 16384,
            'isReasoning' => true
        ),
        // GPT-4o Series
        'gpt-4o' => array(
            'id' => 'gpt-4o',
            'name' => 'GPT-4o (Azure)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 16384,
            'isReasoning' => false
        ),
        'gpt-4o-mini' => array(
            'id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini (Azure)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 16384,
            'isReasoning' => false
        ),
        // o-series Reasoning Models
        'o1' => array(
            'id' => 'o1',
            'name' => 'o1 Reasoning (Azure)',
            'contextWindow' => 200000,
            'maxOutputTokens' => 100000,
            'isReasoning' => true
        ),
        'o1-mini' => array(
            'id' => 'o1-mini',
            'name' => 'o1 Mini (Azure)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 65536,
            'isReasoning' => true
        ),
        'o3-mini' => array(
            'id' => 'o3-mini',
            'name' => 'o3 Mini (Azure)',
            'contextWindow' => 200000,
            'maxOutputTokens' => 100000,
            'isReasoning' => true
        ),
        // Legacy Models
        'gpt-4' => array(
            'id' => 'gpt-4',
            'name' => 'GPT-4 (Azure)',
            'contextWindow' => 128000,
            'maxOutputTokens' => 8192,
            'isReasoning' => false
        ),
        'gpt-35-turbo' => array(
            'id' => 'gpt-35-turbo',
            'name' => 'GPT-3.5 Turbo (Azure)',
            'contextWindow' => 16385,
            'maxOutputTokens' => 4096,
            'isReasoning' => false
        )
    );
    
    /**
     * Constructor
     * @param string $api_key Azure API key
     * @param string $model Deployment name
     * @param string $base_url Azure endpoint URL (e.g., https://YOUR-RESOURCE.openai.azure.com)
     */
    public function __construct($api_key, $model = 'gpt-5-mini', $base_url = null, $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
        $this->base_url = $base_url ?: '';
    }
    
    /**
     * Get available models
     */
    public static function get_available_models() {
        return self::$models;
    }
    
    /**
     * Check if the current model is a reasoning model
     * Reasoning models don't support temperature or top_p parameters
     */
    private function is_reasoning_model() {
        $model = strtolower($this->model_id);
        
        // Check model info first
        if (isset(self::$models[$this->model_id]['isReasoning'])) {
            return self::$models[$this->model_id]['isReasoning'];
        }
        
        // Fallback: check by name pattern
        if (strpos($model, 'o1') === 0 || strpos($model, 'o3') === 0) {
            return true;
        }
        if (strpos($model, 'gpt-5') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get model information
     */
    public function get_model() {
        if (isset(self::$models[$this->model_id])) {
            return self::$models[$this->model_id];
        }
        
        // For custom deployments not in our list
        return array(
            'id' => $this->model_id,
            'name' => $this->model_id,
            'contextWindow' => 128000,
            'maxOutputTokens' => 4096,
            'isReasoning' => $this->is_reasoning_model()
        );
    }
    
    /**
     * Send a chat message and get a complete response
     */
    public function chat($message, $history = array()) {
        if (empty($this->base_url)) {
            return new WP_Error('azure_error', 'Azure endpoint URL is required. Set it in the Base URL field.');
        }
        
        $messages = $this->build_messages($message, $history);
        $is_reasoning = $this->is_reasoning_model();
        
        // Build request body
        $body = array(
            'messages' => $messages
        );
        
        // Reasoning models (o-series, gpt-5) don't support temperature
        if (!$is_reasoning) {
            $body['temperature'] = $this->temperature;
        }
        
        // Use max_completion_tokens for newer models, max_tokens for legacy
        if ($this->max_tokens) {
            // Azure 2024-05-01-preview and later prefer max_completion_tokens
            // Reasoning models (o1, o3) and GPT-4o REQUIRE it.
            // Using it by default for Azure is safer for modern deployments.
            $body['max_completion_tokens'] = $this->max_tokens;
        }
        
        // Sanitize Base URL: Remove typical Azure endpoint paths if the user pasted the full URL
        // We want just: https://resource-name.openai.azure.com
        $clean_base_url = preg_replace('/\/openai\/deployments\/.*$/i', '', $this->base_url);
        $clean_base_url = rtrim($clean_base_url, '/');
        
        // Azure uses deployment name in URL
        $url = $clean_base_url . '/openai/deployments/' . $this->model_id . '/chat/completions?api-version=' . $this->api_version;
        
        // Debug log (optional)
        // Debug log (optional)
        if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::debug('Azure: Calling API', ['url' => $url]);
            \Quarksol\SmartChatbot\Services\Logger::debug('Azure: Is reasoning model', ['is_reasoning' => $is_reasoning]);
        }
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 120 // Reasoning models may take longer
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : 'Unknown API error';
            
            // Provide helpful suggestions for common errors
            if (strpos($error_message, 'max_tokens') !== false || strpos($error_message, 'max_completion_tokens') !== false) {
                $error_message .= ' (Hint: This model may require different token parameter. Check if it\'s a reasoning model.)';
            }
            if (strpos($error_message, 'temperature') !== false) {
                $error_message .= ' (Hint: Reasoning models like o1, o3, and GPT-5 do not support temperature parameter.)';
            }
            
            return new WP_Error('azure_error', 'Azure OpenAI error: ' . $error_message);
        }
        
        if (isset($response_body['choices'][0]['message']['content'])) {
            return $response_body['choices'][0]['message']['content'];
        }
        
        return new WP_Error('azure_error', 'Invalid response from Azure OpenAI');
    }
    
    /**
     * Check if this is a newer model that uses max_completion_tokens
     */
    private function is_newer_model() {
        $model = strtolower($this->model_id);
        
        // GPT-4o and newer use max_completion_tokens
        if (strpos($model, 'gpt-4o') === 0) {
            return true;
        }
        if (strpos($model, 'gpt-5') === 0) {
            return true;
        }
        
        return false;
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
