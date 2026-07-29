<?php
declare(strict_types=1);
/**
 * Error Handler Service
 * 
 * Centralized exception handling with user-friendly error messages.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error Handler
 */
class ErrorHandler {
    
    /** Whether handler is registered */
    protected static bool $registered = false;
    
    /** Previous exception handler */
    protected static $previousHandler = null;
    
    /**
     * Register error handler
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }
        
        self::$previousHandler = set_exception_handler([self::class, 'handleException']);
        self::$registered = true;
        
        // Also handle errors as exceptions
        set_error_handler([self::class, 'handleError']);
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException(\Throwable $e): void {
        // Log the error
        Logger::error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => self::formatTrace($e->getTraceAsString()),
        ]);
        
        // If this is an API request, return JSON error
        if (self::isRestRequest()) {
            self::sendJsonError($e);
            return;
        }
        
        // Call previous handler if exists
        if (self::$previousHandler) {
            call_user_func(self::$previousHandler, $e);
        }
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Only handle non-suppressed errors
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Map error types
        $level = match($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => Logger::LEVEL_ERROR,
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => Logger::LEVEL_WARNING,
            E_NOTICE, E_USER_NOTICE, E_STRICT, E_DEPRECATED, E_USER_DEPRECATED => Logger::LEVEL_DEBUG,
            default => Logger::LEVEL_INFO,
        };
        
        // Log through Logger service
        Logger::log($level, $errstr, [
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline,
        ]);
        
        // Don't prevent default error handling for fatal errors
        return !in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]);
    }
    
    /**
     * Get user-friendly error message
     */
    public static function getUserFriendlyMessage(\Throwable $e): string {
        // Check for known error types
        $message = $e->getMessage();
        
        // AI Provider errors
        if (str_contains($message, 'API key')) {
            return 'There was an authentication issue with the AI service. Please check your API configuration.';
        }
        
        if (str_contains($message, 'rate limit') || str_contains($message, '429')) {
            return 'The AI service is temporarily busy. Please try again in a moment.';
        }
        
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'The request took too long. Please try again with a shorter message.';
        }
        
        if (str_contains($message, 'connection') || str_contains($message, 'curl')) {
            return 'Unable to connect to the AI service. Please check your internet connection.';
        }
        
        // Database errors
        if (str_contains($message, 'database') || str_contains($message, 'SQL')) {
            return 'A database error occurred. Please try again later.';
        }
        
        // Default message
        return 'Sorry, something went wrong. Please try again.';
    }
    
    /**
     * Check if this is a REST API request
     */
    protected static function isRestRequest(): bool {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
    
    /**
     * Send JSON error response
     */
    protected static function sendJsonError(\Throwable $e): void {
        $statusCode = 500;
        
        // Check for specific HTTP codes
        if (method_exists($e, 'getStatusCode')) {
            $statusCode = $e->getStatusCode();
        } elseif ($e->getCode() >= 400 && $e->getCode() < 600) {
            $statusCode = $e->getCode();
        }
        
        wp_send_json_error([
            'message' => self::getUserFriendlyMessage($e),
            'code' => 'server_error',
        ], $statusCode);
    }
    
    /**
     * Format stack trace for logging
     */
    protected static function formatTrace(string $trace): string {
        // Limit trace to first 10 frames
        $lines = explode("\n", $trace);
        $lines = array_slice($lines, 0, 10);
        return implode("\n", $lines);
    }
    
    /**
     * Wrap callable with error handling
     */
    public static function wrap(callable $callback, $fallback = null) {
        try {
            return $callback();
        } catch (\Throwable $e) {
            self::handleException($e);
            return $fallback;
        }
    }
    
    /**
     * Create a safe callback that logs errors
     */
    public static function safe(callable $callback): \Closure {
        return function(...$args) use ($callback) {
            try {
                return $callback(...$args);
            } catch (\Throwable $e) {
                self::handleException($e);
                return null;
            }
        };
    }
}
