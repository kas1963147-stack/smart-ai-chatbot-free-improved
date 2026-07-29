<?php
/**
 * Hugging Face Provider Class
 * 
 * Handles Hugging Face Inference API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_HuggingFace_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api-inference.huggingface.co/models';
    
    private static $models = array(
        'meta-llama/Llama-3.3-70B-Instruct' => array('id' => 'meta-llama/Llama-3.3-70B-Instruct', 'name' => 'Llama 3.3 70B', 'contextWindow' => 128000, 'maxOutputTokens' => 8192),
        'Qwen/Qwen2.5-72B-Instruct' => array('id' => 'Qwen/Qwen2.5-72B-Instruct', 'name' => 'Qwen 2.5 72B', 'contextWindow' => 32768, 'maxOutputTokens' => 8192),
        'mistralai/Mistral-7B-Instruct-v0.3' => array('id' => 'mistralai/Mistral-7B-Instruct-v0.3', 'name' => 'Mistral 7B', 'contextWindow' => 32768, 'maxOutputTokens' => 8192),
        'microsoft/Phi-3.5-mini-instruct' => array('id' => 'microsoft/Phi-3.5-mini-instruct', 'name' => 'Phi-3.5 Mini', 'contextWindow' => 128000, 'maxOutputTokens' => 4096),
        'google/gemma-2-27b-it' => array('id' => 'google/gemma-2-27b-it', 'name' => 'Gemma 2 27B', 'contextWindow' => 8192, 'maxOutputTokens' => 8192)
    );
    
    public function __construct($api_key, $model = 'meta-llama/Llama-3.3-70B-Instruct', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() { return self::$models; }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 32768, 'maxOutputTokens' => 8192
        );
    }
    
    public function chat($message, $history = array()) {
        // Build a simple prompt for HF models
        $prompt = '';
        if (!empty($this->system_prompt)) {
            $prompt .= "System: {$this->system_prompt}\n\n";
        }
        foreach ($history as $msg) {
            $role = ucfirst($msg['role']);
            $prompt .= "{$role}: {$msg['content']}\n";
        }
        $prompt .= "User: {$message}\nAssistant:";
        
        $body = array('inputs' => $prompt, 'parameters' => array('temperature' => $this->temperature, 'return_full_text' => false));
        if ($this->max_tokens) $body['parameters']['max_new_tokens'] = $this->max_tokens;
        
        $response = wp_remote_post($this->base_url . '/' . $this->model_id, array(
            'headers' => array('Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) return $response;
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status !== 200) return new WP_Error('hf_error', 'HuggingFace error: ' . ($body['error'] ?? 'Unknown'));
        
        // HF returns array of generated texts
        if (isset($body[0]['generated_text'])) return trim($body[0]['generated_text']);
        return new WP_Error('hf_error', 'Invalid response');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
