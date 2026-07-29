<?php
/**
 * Base AI Provider Class
 * 
 * Abstract class that all AI providers must extend.
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SWC_Chatbot_AI_Provider {
    
    protected $model_id;
    protected $temperature;
    protected $max_tokens;
    protected $system_prompt = '';
    
    /**
     * Constructor
     */
    public function __construct($model, $temperature = 0.7, $max_tokens = null) {
        $this->model_id = $model;
        $this->temperature = $temperature;
        $this->max_tokens = $max_tokens;
    }
    
    /**
     * Set the system prompt
     */
    public function set_system_prompt($prompt) {
        $this->system_prompt = $prompt;
    }
    
    /**
     * Get model information
     * 
     * @return array Model info with id, name, contextWindow, maxOutputTokens
     */
    abstract public function get_model();
    
    /**
     * Send a chat message and get a complete response
     * 
     * @param string $message The user's message
     * @param array $history Previous messages in the conversation
     * @return string|WP_Error The AI response or error
     */
    abstract public function chat($message, $history = array());
    
    /**
     * Send a chat message and stream the response
     * 
     * @param string $message The user's message
     * @param array $history Previous messages in the conversation
     * @return Generator Yields response chunks
     */
    abstract public function chat_stream($message, $history = array());
    
    /**
     * Build messages array for API request
     */
    protected function build_messages($message, $history = array()) {
        $messages = array();
        
        // Add system prompt if set
        if (!empty($this->system_prompt)) {
            $messages[] = array(
                'role' => 'system',
                'content' => $this->system_prompt
            );
        }
        
        // Add history
        foreach ($history as $msg) {
            $messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content']
            );
        }
        
        // Add current message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        return $messages;
    }
}
