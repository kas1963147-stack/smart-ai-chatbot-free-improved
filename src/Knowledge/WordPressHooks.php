<?php
declare(strict_types=1);
/**
 * WordPress Hooks
 * 
 * Auto-sync WordPress content with knowledge base.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Hooks
 */
class WordPressHooks {
    
    /** @var bool Whether hooks are registered */
    protected static bool $registered = false;
    
    /**
     * Register all WordPress hooks for knowledge sync
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }
        
        // Only register if auto-sync is enabled
        if (!KnowledgeConfig::get('auto_sync_wordpress', false)) {
            return;
        }
        
        // Post/page save hooks
        add_action('save_post', [self::class, 'onSavePost'], 20, 3);
        add_action('delete_post', [self::class, 'onDeletePost'], 10, 1);
        add_action('wp_trash_post', [self::class, 'onTrashPost'], 10, 1);
        
        // WooCommerce hooks
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_update_product', [self::class, 'onUpdateProduct'], 20, 1);
            add_action('woocommerce_delete_product', [self::class, 'onDeleteProduct'], 10, 1);
        }
        
        // Schedule background indexing
        add_action('swc_knowledge_reindex', [self::class, 'processReindexQueue']);
        
        self::$registered = true;
    }
    
    /**
     * Handle post save
     */
    public static function onSavePost(int $postId, \WP_Post $post, bool $update): void {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($postId)) {
            return;
        }
        
        // Check if post type is enabled for sync
        $syncPostTypes = KnowledgeConfig::get('sync_post_types', ['page', 'post']);
        if (!in_array($post->post_type, $syncPostTypes)) {
            return;
        }
        
        // Only published posts
        if ($post->post_status !== 'publish') {
            // Remove from index if unpublished
            self::queueRemoval($postId, $post->post_type);
            return;
        }
        
        // Queue for reindexing
        self::queueReindex($postId, $post->post_type);
    }
    
    /**
     * Handle post deletion
     */
    public static function onDeletePost(int $postId): void {
        $post = get_post($postId);
        if ($post) {
            self::removeFromIndex($postId, $post->post_type);
        }
    }
    
    /**
     * Handle post trash
     */
    public static function onTrashPost(int $postId): void {
        $post = get_post($postId);
        if ($post) {
            self::removeFromIndex($postId, $post->post_type);
        }
    }
    
    /**
     * Handle WooCommerce product update
     */
    public static function onUpdateProduct(int $productId): void {
        $product = wc_get_product($productId);
        
        if (!$product) {
            return;
        }
        
        if ($product->get_status() !== 'publish') {
            self::removeFromIndex($productId, 'product');
            return;
        }
        
        self::queueReindex($productId, 'product');
    }
    
    /**
     * Handle WooCommerce product deletion
     */
    public static function onDeleteProduct(int $productId): void {
        self::removeFromIndex($productId, 'product');
    }
    
    /**
     * Queue post for reindexing
     */
    protected static function queueReindex(int $postId, string $postType): void {
        $queue = get_option('swc_knowledge_reindex_queue', []);
        
        $key = "{$postType}_{$postId}";
        $queue[$key] = [
            'post_id' => $postId,
            'post_type' => $postType,
            'queued_at' => time(),
        ];
        
        update_option('swc_knowledge_reindex_queue', $queue);
        
        // Schedule processing if not already scheduled
        if (!wp_next_scheduled('swc_knowledge_reindex')) {
            wp_schedule_single_event(time() + 5, 'swc_knowledge_reindex');
        }
    }
    
    /**
     * Queue post for removal from index
     */
    protected static function queueRemoval(int $postId, string $postType): void {
        self::removeFromIndex($postId, $postType);
    }
    
    /**
     * Remove post from index immediately
     */
    protected static function removeFromIndex(int $postId, string $postType): void {
        try {
            $indexer = new KnowledgeIndexer();
            $indexer->removeFromIndex('wordpress', "{$postType}_{$postId}");
        } catch (\Throwable $e) {
            \Quarksol\SmartChatbot\Services\Logger::error('Failed to remove post from index', ['post_id' => $postId, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Process the reindex queue
     */
    public static function processReindexQueue(): void {
        $queue = get_option('swc_knowledge_reindex_queue', []);
        
        if (empty($queue)) {
            return;
        }
        
        $indexer = new KnowledgeIndexer();
        $processed = [];
        
        foreach ($queue as $key => $item) {
            try {
                if ($item['post_type'] === 'product') {
                    // Handle WooCommerce product
                    $docs = WordPressDataLoader::loadProduct($item['post_id']);
                    if (!empty($docs)) {
                        // Remove old and add new
                        $indexer->removeFromIndex('woocommerce', 'product_' . $item['post_id']);
                        // Embed and store handled by indexer
                    }
                } else {
                    // Handle regular post
                    $indexer->reindexPost($item['post_id']);
                }
                
                $processed[] = $key;
                
            } catch (\Throwable $e) {
                \Quarksol\SmartChatbot\Services\Logger::error('Failed to reindex', ['key' => $key, 'error' => $e->getMessage()]);
                
                // Remove if too old (> 1 hour)
                if (time() - $item['queued_at'] > 3600) {
                    $processed[] = $key;
                }
            }
        }
        
        // Remove processed items
        foreach ($processed as $key) {
            unset($queue[$key]);
        }
        
        update_option('swc_knowledge_reindex_queue', $queue);
        
        // Reschedule if more items remain
        if (!empty($queue) && !wp_next_scheduled('swc_knowledge_reindex')) {
            wp_schedule_single_event(time() + 30, 'swc_knowledge_reindex');
        }
    }
    
    /**
     * Force full reindex of a post type
     */
    public static function forceReindexPostType(string $postType): int {
        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        
        foreach ($posts as $postId) {
            self::queueReindex($postId, $postType);
        }
        
        return count($posts);
    }
    
    /**
     * Clear reindex queue
     */
    public static function clearQueue(): void {
        delete_option('swc_knowledge_reindex_queue');
        wp_clear_scheduled_hook('swc_knowledge_reindex');
    }
    
    /**
     * Get queue status
     */
    public static function getQueueStatus(): array {
        $queue = get_option('swc_knowledge_reindex_queue', []);
        
        return [
            'pending' => count($queue),
            'next_run' => wp_next_scheduled('swc_knowledge_reindex'),
            'items' => array_values($queue),
        ];
    }
}
