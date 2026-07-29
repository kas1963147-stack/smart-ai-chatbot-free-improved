<?php
declare(strict_types=1);


/**
 * Tool Response Wrapper
 * 
 * Standardizes tool output format for consistent AI processing.
 * 
 * @package Quarksol\SmartChatbot\Types
 */

namespace Quarksol\SmartChatbot\Types;

if (!defined('ABSPATH')) {
    exit;
}

class ToolResponse {
    
    /**
     * Return successful response
     */
    public static function success(array $data): string {
        return json_encode(array_merge(['success' => true], $data));
    }
    
    /**
     * Return error response
     */
    public static function error(string $message, ?string $code = null): string {
        $response = ['success' => false, 'error' => $message];
        if ($code) {
            $response['code'] = $code;
        }
        return json_encode($response);
    }
}
