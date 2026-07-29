<?php
declare(strict_types=1);
/**
 * Legacy AI Provider Adapter
 * 
 * Bridges modern Quarksol\SmartChatbot\Api\Providers with legacy SWC_Chatbot_AI_Provider interface.
 * Allows legacy code to use modern providers seamlessly.
 * 
 * @package Quarksol\SmartChatbot\Api\Providers
 */

namespace Quarksol\SmartChatbot\Api\Providers;

use SWC_Chatbot_AI_Provider;
use Quarksol\SmartChatbot\Api\Providers\BaseProvider;
use Quarksol\SmartChatbot\Types\CreateMessageMetadata;
use Exception;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adapter implementation
 */
class LegacyAdapter extends SWC_Chatbot_AI_Provider {
    
    private BaseProvider $provider;
    
    /**
     * Constructor
     */
    public function __construct(BaseProvider $provider) {
        $this->provider = $provider;
        
        // Sync basic properties for legacy compatibility (though modern providers manage their own)
        parent::__construct('adapted-model', 0.7, null);
    }
    
    /**
     * Get model info (Legacy format)
     */
    public function get_model() {
        // Modern providers don't always expose this structure publicly in the same way,
        // but we can try to return something sensible.
        return [
            'id' => 'adapted-model',
            'name' => 'Modern Provider Model',
            'contextWindow' => 128000,
            'maxOutputTokens' => 4096
        ];
    }
    
    /**
     * Chat (Non-streaming)
     */
    public function chat($message, $history = array()) {
        try {
            $stream = $this->chat_stream($message, $history);
            $fullResponse = '';
            
            foreach ($stream as $chunk) {
                if (is_string($chunk)) {
                    $fullResponse .= $chunk;
                } elseif (isset($chunk['content'])) {
                    $fullResponse .= $chunk['content'];
                }
            }
            
            return $fullResponse;
            
        } catch (Exception $e) {
            return new WP_Error('provider_error', $e->getMessage());
        }
    }
    
    /**
     * Chat Stream
     */
    public function chat_stream($message, $history = array()) {
        // 1. Build messages array (Modern format)
        $messages = [];
        
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }
        
        // Current user message is usually appended by the legacy caller before calling chat(),
        // OR legacy calls usually rely on us to construct the full array.
        // Looking at base class build_messages, it handles history + current message.
        // But here we need to call createMessage on the provider.
        
        // Add current message if not present in history
        // (Legacy interface passes message separate from history)
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        // 2. System Prompt
        $systemPrompt = $this->system_prompt ?: 'You are a helpful assistant.';
        
        // 3. Metadata/Options 
        // Modern providers use Settings object passed in constructor, but we might pass ephemeral overrides via Metadata if needed.
        // For now, assume settings are locked in constructor.
        
        try {
            $generator = $this->provider->createMessage($systemPrompt, $messages);
            
            foreach ($generator as $chunk) {
                // Modern providers yield array chunks, usually ['type' => 'content', 'content' => '...']
                // Legacy expects raw strings or specific array format? 
                // Base legacy class doesn't enforce return type of yield, but typically it yields strings for SSE.
                
                if (isset($chunk['type']) && $chunk['type'] === 'content') {
                    yield $chunk['content'];
                }
            }
        } catch (Exception $e) {
            // Log error
            \Quarksol\SmartChatbot\Services\Logger::error('LegacyAdapter stream error', ['message' => $e->getMessage()]);
            yield "Error: " . $e->getMessage();
        }
    }
}
