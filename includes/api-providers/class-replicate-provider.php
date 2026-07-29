<?php
/**
 * Replicate Provider Class
 * 
 * Run open source models via Replicate's API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Replicate_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://api.replicate.com/v1';
    
    private static $models = array(
        'meta/meta-llama-3-70b-instruct' => array('id' => 'meta/meta-llama-3-70b-instruct', 'name' => 'Llama 3 70B', 'contextWindow' => 8192, 'maxOutputTokens' => 4096),
        'meta/meta-llama-3-8b-instruct' => array('id' => 'meta/meta-llama-3-8b-instruct', 'name' => 'Llama 3 8B', 'contextWindow' => 8192, 'maxOutputTokens' => 4096),
        'mistralai/mixtral-8x7b-instruct-v0.1' => array('id' => 'mistralai/mixtral-8x7b-instruct-v0.1', 'name' => 'Mixtral 8x7B', 'contextWindow' => 32768, 'maxOutputTokens' => 8192),
        'snowflake/snowflake-arctic-instruct' => array('id' => 'snowflake/snowflake-arctic-instruct', 'name' => 'Snowflake Arctic', 'contextWindow' => 4096, 'maxOutputTokens' => 4096)
    );
    
    public function __construct($api_key, $model = 'meta/meta-llama-3-70b-instruct', $temperature = 0.7, $max_tokens = null) {
        parent::__construct($model, $temperature, $max_tokens);
        $this->api_key = $api_key;
    }
    
    public static function get_available_models() { return self::$models; }
    
    public function get_model() {
        return isset(self::$models[$this->model_id]) ? self::$models[$this->model_id] : array(
            'id' => $this->model_id, 'name' => $this->model_id, 'contextWindow' => 4096, 'maxOutputTokens' => 4096
        );
    }
    
    public function chat($message, $history = array()) {
        $prompt = '';
        if (!empty($this->system_prompt)) $prompt .= "System: {$this->system_prompt}\n\n";
        foreach ($history as $msg) $prompt .= ucfirst($msg['role']) . ": {$msg['content']}\n";
        $prompt .= "User: {$message}\nAssistant:";
        
        $body = array('input' => array('prompt' => $prompt, 'temperature' => $this->temperature));
        if ($this->max_tokens) $body['input']['max_tokens'] = $this->max_tokens;
        
        // Create prediction
        $response = wp_remote_post($this->base_url . '/models/' . $this->model_id . '/predictions', array(
            'headers' => array('Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) return $response;
        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status !== 201) return new WP_Error('replicate_error', 'Replicate error: ' . ($body['detail'] ?? 'Unknown'));
        
        // Poll for result
        $get_url = $body['urls']['get'] ?? null;
        if (!$get_url) return new WP_Error('replicate_error', 'No result URL');
        
        for ($i = 0; $i < 60; $i++) {
            sleep(1);
            $result = wp_remote_get($get_url, array('headers' => array('Authorization' => 'Bearer ' . $this->api_key)));
            if (is_wp_error($result)) continue;
            $data = json_decode(wp_remote_retrieve_body($result), true);
            if ($data['status'] === 'succeeded') return is_array($data['output']) ? implode('', $data['output']) : $data['output'];
            if ($data['status'] === 'failed') return new WP_Error('replicate_error', $data['error'] ?? 'Prediction failed');
        }
        return new WP_Error('replicate_error', 'Timeout waiting for result');
    }
    
    public function chat_stream($message, $history = array()) {
        $response = $this->chat($message, $history);
        if (is_wp_error($response)) { yield array('type' => 'error', 'error' => $response->get_error_message()); return; }
        yield array('type' => 'text', 'text' => $response);
        yield array('type' => 'done');
    }
}
