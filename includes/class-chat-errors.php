<?php
/**
 * Chat Error Messages
 * 
 * User-friendly error messages for the chatbot interface.
 * Provides helpful, non-technical error messages to users.
 * 
 * @package SWC_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SWC_Chat_Errors
 */
class SWC_Chat_Errors {

    /**
     * Error codes for frontend retry logic
     */
    const RETRYABLE_ERRORS = [
        'rate_limit',
        'timeout',
        'connection_error',
        'server_overload',
        'temporary_error'
    ];

    /**
     * Get a user-friendly error response
     * 
     * @param \Throwable|string $error Error or message
     * @param string $code Optional error code
     * @return array Response array for frontend
     */
    public static function get_response($error, $code = 'unknown') {
        $message = is_string($error) ? $error : $error->getMessage();
        $detected_code = self::detect_error_code($message);
        $code = $detected_code ?: $code;
        
        return [
            'type' => 'error',
            'error_code' => $code,
            'message' => self::get_friendly_message($code, $message),
            'retryable' => in_array($code, self::RETRYABLE_ERRORS),
            'retry_after' => self::get_retry_delay($code),
        ];
    }

    /**
     * Detect error code from message
     * 
     * @param string $message
     * @return string|null
     */
    private static function detect_error_code($message) {
        $message_lower = strtolower($message);
        
        // API key issues
        if (str_contains($message_lower, 'api key') || 
            str_contains($message_lower, 'authentication') ||
            str_contains($message_lower, '401') ||
            str_contains($message_lower, 'unauthorized')) {
            return 'api_key_error';
        }
        
        // Rate limiting
        if (str_contains($message_lower, 'rate limit') || 
            str_contains($message_lower, '429') ||
            str_contains($message_lower, 'too many requests')) {
            return 'rate_limit';
        }
        
        // Timeout
        if (str_contains($message_lower, 'timeout') || 
            str_contains($message_lower, 'timed out')) {
            return 'timeout';
        }
        
        // Connection errors
        if (str_contains($message_lower, 'connection') || 
            str_contains($message_lower, 'curl') ||
            str_contains($message_lower, 'network') ||
            str_contains($message_lower, 'could not resolve')) {
            return 'connection_error';
        }
        
        // Server overload
        if (str_contains($message_lower, '503') || 
            str_contains($message_lower, 'overloaded') ||
            str_contains($message_lower, 'capacity')) {
            return 'server_overload';
        }
        
        // Context length
        if (str_contains($message_lower, 'context length') || 
            str_contains($message_lower, 'token limit') ||
            str_contains($message_lower, 'too long')) {
            return 'context_too_long';
        }
        
        // Model not found
        if (str_contains($message_lower, 'model') && 
            (str_contains($message_lower, 'not found') || str_contains($message_lower, 'invalid'))) {
            return 'invalid_model';
        }
        
        // Content filtered
        if (str_contains($message_lower, 'content filter') || 
            str_contains($message_lower, 'safety') ||
            str_contains($message_lower, 'blocked')) {
            return 'content_filtered';
        }
        
        return null;
    }

    /**
     * Get user-friendly message for error code
     * 
     * @param string $code
     * @param string $original_message For debug
     * @return string
     */
    private static function get_friendly_message($code, $original_message = '') {
        $store_name = get_bloginfo('name');
        
        $messages = [
            'api_key_error' => "I'm having trouble connecting to my AI brain. Please ask an admin to check the API settings.",
            
            'rate_limit' => "I'm getting a lot of questions right now! Please wait a moment and try again.",
            
            'timeout' => "That took a bit too long. Could you try asking again, maybe with a shorter message?",
            
            'connection_error' => "I'm having trouble connecting to my AI service. Please check your internet connection and try again.",
            
            'server_overload' => "My AI service is very busy right now. Please try again in a few minutes.",
            
            'context_too_long' => "That conversation got quite long! Let me start fresh. Could you repeat your question?",
            
            'invalid_model' => "There's a configuration issue. Please let an admin know to check the AI model settings.",
            
            'content_filtered' => "I can't respond to that type of content. Let me know if you have other questions about {$store_name}!",
            
            'empty_response' => "I'm not sure how to respond to that. Could you rephrase your question?",
            
            'unknown' => "Oops! Something unexpected happened. Please try again, or ask a different question.",
            
            'temporary_error' => "A temporary glitch occurred. Please try again in a moment.",
        ];
        
        return $messages[$code] ?? $messages['unknown'];
    }

    /**
     * Get retry delay in milliseconds for error code
     * 
     * @param string $code
     * @return int
     */
    private static function get_retry_delay($code) {
        $delays = [
            'rate_limit' => 5000,      // 5 seconds
            'timeout' => 2000,          // 2 seconds
            'connection_error' => 3000, // 3 seconds
            'server_overload' => 10000, // 10 seconds
            'temporary_error' => 2000,  // 2 seconds
        ];
        
        return $delays[$code] ?? 0;
    }

    /**
     * Wrap a callable with error handling
     * 
     * @param callable $callback
     * @return array Response array
     */
    public static function wrap($callback) {
        try {
            $result = $callback();
            
            // Check for empty response
            if ($result === null || $result === '' || 
                (is_array($result) && empty($result['message']))) {
                return self::get_response('Empty response from AI', 'empty_response');
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            // Log the error
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Chatbot error', ['message' => $e->getMessage()]);
            }
            
            // Check if Quarksol\SmartChatbot\Services\ErrorHandler is available
            if (class_exists('\Quarksol\SmartChatbot\Services\ErrorHandler')) {
                \Quarksol\SmartChatbot\Services\ErrorHandler::handleException($e);
            }
            
            return self::get_response($e);
        }
    }

    /**
     * Create a standardized success response
     * 
     * @param string $message
     * @param string $type
     * @param array $extra Additional data
     * @return array
     */
    public static function success($message, $type = 'text', $extra = []) {
        return array_merge([
            'type' => $type,
            'message' => $message,
        ], $extra);
    }
}
