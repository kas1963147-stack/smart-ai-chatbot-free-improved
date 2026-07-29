<?php
declare(strict_types=1);
/**
 * Knowledge Base Configuration
 * 
 * Settings and configuration for the knowledge base system.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Config
 */
class KnowledgeConfig {
    
    /** Option key for KB settings */
    const OPTION_KEY = 'swc_knowledge_config';
    
    /** Default embeddings provider */
    const DEFAULT_EMBEDDINGS_PROVIDER = 'openai';
    
    /** Default embeddings model */
    const DEFAULT_EMBEDDINGS_MODEL = 'text-embedding-3-small';
    
    /** Default vector store type */
    const DEFAULT_VECTOR_STORE = 'file';
    
    /** Allowed file extensions */
    const ALLOWED_EXTENSIONS = ['md', 'txt', 'pdf', 'html'];
    
    /** @var array Cached config */
    protected static ?array $config = null;
    
    /**
     * Get knowledge folder path
     */
    public static function getKnowledgePath(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/smart-ai-chatbot/knowledge';
    }
    
    /**
     * Get vector store path
     */
    public static function getVectorStorePath(): string {
        return self::getKnowledgePath() . '/vectors';
    }
    
    /**
     * Get all config
     */
    public static function all(): array {
        if (self::$config === null) {
            self::$config = get_option(self::OPTION_KEY, self::defaults());
        }
        return self::$config;
    }
    
    /**
     * Get config value
     */
    public static function get(string $key, $default = null) {
        $config = self::all();
        return $config[$key] ?? $default;
    }
    
    /**
     * Set config value
     */
    public static function set(string $key, $value): void {
        $config = self::all();
        $config[$key] = $value;
        self::$config = $config;
        update_option(self::OPTION_KEY, $config);
    }
    
    /**
     * Save multiple values
     */
    public static function save(array $values): void {
        $config = array_merge(self::all(), $values);
        self::$config = $config;
        update_option(self::OPTION_KEY, $config);
    }
    
    /**
     * Default config values
     */
    public static function defaults(): array {
        return [
            // Embeddings
            'embeddings_provider' => self::DEFAULT_EMBEDDINGS_PROVIDER,
            'embeddings_model' => self::DEFAULT_EMBEDDINGS_MODEL,
            'embeddings_api_key' => '', // Will use main plugin AI key if empty
            
            // Vector Store
            'vector_store_type' => self::DEFAULT_VECTOR_STORE,
            'vector_store_config' => [],
            
            // Indexing
            'chunk_size' => 500,        // Characters per chunk
            'chunk_overlap' => 50,      // Overlap between chunks
            'auto_sync_wordpress' => false,
            'sync_post_types' => ['page', 'post'],
            
            // Search
            'search_top_k' => 5,
            'search_threshold' => 0.5,
            
            // File types
            'allowed_extensions' => self::ALLOWED_EXTENSIONS,
            
            // RAG integration
            'enable_rag' => true,
            'rag_auto_augment' => true,  // Auto add to agent prompts
        ];
    }
    
    /**
     * Check if RAG is enabled
     */
    public static function isRAGEnabled(): bool {
        return (bool) self::get('enable_rag', true);
    }
    
    /**
     * Check if auto-augment is enabled
     */
    public static function isAutoAugmentEnabled(): bool {
        return (bool) self::get('rag_auto_augment', true);
    }
    
    /**
     * Get embeddings provider name
     */
    public static function getEmbeddingsProvider(): string {
        return self::get('embeddings_provider', self::DEFAULT_EMBEDDINGS_PROVIDER);
    }
    
    /**
     * Get embeddings model
     */
    public static function getEmbeddingsModel(): string {
        return self::get('embeddings_model', self::DEFAULT_EMBEDDINGS_MODEL);
    }
    
    /**
     * Get embeddings API key (falls back to main plugin key)
     */
    public static function getEmbeddingsApiKey(): string {
        // 1. Dedicated embeddings API key (set in Knowledge settings)
        $key = self::get('embeddings_api_key', '');
        if (!empty($key)) {
            return $key;
        }
        
        // 2. Check provider_configs matching the embeddings provider name (e.g. 'openai')
        if (class_exists('\Quarksol\SmartChatbot\Config\ChatbotConfig')) {
            $settings = \Quarksol\SmartChatbot\Config\ChatbotConfig::settings();
            $providerConfigs = $settings['provider_configs'] ?? [];
            $embeddingsProvider = self::getEmbeddingsProvider();
            
            if (!empty($providerConfigs[$embeddingsProvider]['api_key'])) {
                return $providerConfigs[$embeddingsProvider]['api_key'];
            }
        }
        
        // 3. Legacy option
        $legacyKey = get_option('swc_chatbot_api_key', '');
        if (!empty($legacyKey)) {
            return $legacyKey;
        }
        
        // Return empty — EmbeddingsService::resolveProvider will auto-detect from chat provider
        return '';
    }
    
    /**
     * Get chunk size
     */
    public static function getChunkSize(): int {
        return (int) self::get('chunk_size', 500);
    }
    
    /**
     * Get chunk overlap
     */
    public static function getChunkOverlap(): int {
        return (int) self::get('chunk_overlap', 50);
    }
    
    /**
     * Get search top K
     */
    public static function getSearchTopK(): int {
        return (int) self::get('search_top_k', 5);
    }
    
    /**
     * Get allowed extensions
     */
    public static function getAllowedExtensions(): array {
        return self::get('allowed_extensions', self::ALLOWED_EXTENSIONS);
    }
    
    /**
     * Check if extension is allowed
     */
    public static function isExtensionAllowed(string $ext): bool {
        $ext = strtolower(ltrim($ext, '.'));
        return in_array($ext, self::getAllowedExtensions());
    }
    
    /**
     * Ensure knowledge directories exist
     */
    public static function ensureDirectories(): void {
        $paths = [
            self::getKnowledgePath(),
            self::getVectorStorePath(),
        ];
        
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                wp_mkdir_p($path);
                
                // Add .htaccess for security
                $htaccess = $path . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "deny from all\n");
                }
                
                // Add index.php
                $index = $path . '/index.php';
                if (!file_exists($index)) {
                    file_put_contents($index, "<?php // Silence is golden");
                }
            }
        }
    }
}
