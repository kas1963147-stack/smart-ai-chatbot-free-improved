<?php
/**
 * Gemini Provider Class
 * 
 * Handles Google Gemini models.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Gemini_Provider extends SWC_Chatbot_AI_Provider {
    
    private $api_key;
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta';
    
    /**
     * Model information - Updated to current available models
     * Note: gemini-1.5-flash and gemini-1.5-pro are deprecated
     */
    private static $models = array(
        'gemini-2.0-flash' => array(
            'id' => 'gemini-2.0-flash',
            'name' => 'Gemini 2.0 Flash (Recommended)',
            'contextWindow' => 1048576,
            'maxOutputTokens' => 8192
        ),
        'gemini-2.0-flash-lite' => array(
            'id' => 'gemini-2.0-flash-lite',
            'name' => 'Gemini 2.0 Flash Lite (Faster)',
            'contextWindow' => 1048576,
            'maxOutputTokens' => 8192
        ),
        'gemini-2.5-flash' => array(
            'id' => 'gemini-2.5-flash',
            'name' => 'Gemini 2.5 Flash (Newest)',
            'contextWindow' => 1048576,
            'maxOutputTokens' => 8192
        ),
        'gemini-2.5-pro' => array(
            'id' => 'gemini-2.5-pro',
            'name' => 'Gemini 2.5 Pro (Advanced)',
            'contextWindow' => 2097152,
            'maxOutputTokens' => 8192
        ),
        'gemini-3-flash-preview' => array(
            'id' => 'gemini-3-flash-preview',
            'name' => 'Gemini 3 Flash Preview (Beta)',
            'contextWindow' => 1048576,
            'maxOutputTokens' => 8192
        )
    );
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model = 'gemini-2.0-flash', $temperature = 0.7, $max_tokens = null) {
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
            'contextWindow' => 1000000,
            'maxOutputTokens' => 8192
        );
    }
    
    /**
     * Convert history to Gemini format
     */
    private function convert_history($history) {
        $result = array();
        
        foreach ($history as $msg) {
            if ($msg['role'] === 'system') {
                continue; // System handled separately
            }
            
            $result[] = array(
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => array(
                    array('text' => $msg['content'])
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Send a chat message and get a complete response
     */
    public function chat($message, $history = array()) {
        $contents = $this->convert_history($history);
        
        // Add current message
        $contents[] = array(
            'role' => 'user',
            'parts' => array(
                array('text' => $message)
            )
        );
        
        $body = array(
            'contents' => $contents,
            'generationConfig' => array(
                'temperature' => $this->temperature
            )
        );
        
        if ($this->max_tokens) {
            $body['generationConfig']['maxOutputTokens'] = $this->max_tokens;
        }
        
        // Add system instruction if set
        if (!empty($this->system_prompt)) {
            $body['systemInstruction'] = array(
                'parts' => array(
                    array('text' => $this->system_prompt)
                )
            );
        }
        
        $url = $this->base_url . '/models/' . $this->model_id . ':generateContent?key=' . $this->api_key;
        
        $response = wp_remote_post($url, array(
            'headers' => array(
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
            return new WP_Error('gemini_error', 'Gemini API error: ' . $error_message);
        }
        
        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return $body['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return new WP_Error('gemini_error', 'Invalid response from Gemini');
    }
    
    /**
     * Send a chat message and stream the response
     */
    public function chat_stream($message, $history = array()) {
        $contents = $this->convert_history($history);
        
        // Add current message
        $contents[] = array(
            'role' => 'user',
            'parts' => array(
                array('text' => $message)
            )
        );
        
        $body = array(
            'contents' => $contents,
            'generationConfig' => array(
                'temperature' => $this->temperature
            )
        );
        
        if ($this->max_tokens) {
            $body['generationConfig']['maxOutputTokens'] = $this->max_tokens;
        }
        
        if (!empty($this->system_prompt)) {
            $body['systemInstruction'] = array(
                'parts' => array(
                    array('text' => $this->system_prompt)
                )
            );
        }
        
        $url = $this->base_url . '/models/' . $this->model_id . ':streamGenerateContent?key=' . $this->api_key . '&alt=sse';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
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
                
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    yield array('type' => 'text', 'text' => $data['candidates'][0]['content']['parts'][0]['text']);
                }
                
                if (isset($data['usageMetadata'])) {
                    yield array(
                        'type' => 'usage',
                        'inputTokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                        'outputTokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0
                    );
                }
            }
        }
        
        yield array('type' => 'done');
    }
}
