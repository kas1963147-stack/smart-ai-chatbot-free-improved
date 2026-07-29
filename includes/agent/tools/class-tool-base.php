<?php
/**
 * Base Tool Class
 * 
 * All tools must extend this class.
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SWC_Tool_Base {
    
    protected $config = [];
    
    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    /**
     * Get tool definition for AI
     * Override this in child classes
     */
    abstract public function get_ai_definition();
    
    /**
     * Get available actions
     */
    abstract public function get_actions();
    
    /**
     * Validate parameters
     */
    protected function validate_params($params, $required = []) {
        foreach ($required as $key) {
            if (!isset($params[$key]) || empty($params[$key])) {
                throw new Exception("Missing required parameter: {$key}");
            }
        }
        return true;
    }
    
    /**
     * Log tool action
     */
    protected function log($action, $params = [], $result = null) {
        if (\WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::debug('Agent Tool action', [
                'class' => get_class($this),
                'action' => $action,
                'params' => $params,
                'result' => $result
            ]);
        }
    }
}
