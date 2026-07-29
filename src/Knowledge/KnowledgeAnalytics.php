<?php
declare(strict_types=1);
/**
 * Knowledge Analytics
 * 
 * Track knowledge base usage and performance.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Analytics
 */
class KnowledgeAnalytics {
    
    /** Option key for analytics data */
    const OPTION_KEY = 'swc_knowledge_analytics';
    
    /** @var array Cached analytics */
    protected static ?array $analytics = null;
    
    /**
     * Track a search query
     */
    public static function trackSearch(string $query, int $resultsCount, ?bool $helpful = null): void {
        $data = self::get();
        
        // Update totals
        $data['total_searches'] = ($data['total_searches'] ?? 0) + 1;
        $data['last_search'] = current_time('mysql');
        
        // Track queries with no results
        if ($resultsCount === 0) {
            $data['no_result_searches'] = ($data['no_result_searches'] ?? 0) + 1;
        }
        
        // Track helpful/unhelpful
        if ($helpful !== null) {
            if ($helpful) {
                $data['helpful_searches'] = ($data['helpful_searches'] ?? 0) + 1;
            } else {
                $data['unhelpful_searches'] = ($data['unhelpful_searches'] ?? 0) + 1;
            }
        }
        
        // Track daily stats
        $today = date('Y-m-d');
        if (!isset($data['daily'][$today])) {
            $data['daily'][$today] = ['searches' => 0, 'no_results' => 0];
        }
        $data['daily'][$today]['searches']++;
        if ($resultsCount === 0) {
            $data['daily'][$today]['no_results']++;
        }
        
        // Keep only last 30 days
        $data['daily'] = array_slice($data['daily'], -30, 30, true);
        
        self::save($data);
    }
    
    /**
     * Track document access
     */
    public static function trackDocAccess(int $docId, string $source = 'search'): void {
        $data = self::get();
        
        // Top accessed docs
        $key = "doc_{$docId}";
        if (!isset($data['doc_access'][$key])) {
            $data['doc_access'][$key] = ['count' => 0, 'last' => null];
        }
        $data['doc_access'][$key]['count']++;
        $data['doc_access'][$key]['last'] = current_time('mysql');
        
        // Keep only top 100
        uasort($data['doc_access'], fn($a, $b) => $b['count'] <=> $a['count']);
        $data['doc_access'] = array_slice($data['doc_access'], 0, 100, true);
        
        self::save($data);
    }
    
    /**
     * Track indexing operation
     */
    public static function trackIndexing(int $sourceId, int $docsIndexed, float $duration): void {
        $data = self::get();
        
        $data['total_indexing_ops'] = ($data['total_indexing_ops'] ?? 0) + 1;
        $data['total_docs_indexed'] = ($data['total_docs_indexed'] ?? 0) + $docsIndexed;
        $data['last_indexing'] = [
            'source_id' => $sourceId,
            'docs' => $docsIndexed,
            'duration' => $duration,
            'timestamp' => current_time('mysql'),
        ];
        
        self::save($data);
    }
    
    /**
     * Get all analytics data
     */
    public static function get(): array {
        if (self::$analytics === null) {
            self::$analytics = get_option(self::OPTION_KEY, self::defaults());
        }
        return self::$analytics;
    }
    
    /**
     * Save analytics data
     */
    protected static function save(array $data): void {
        self::$analytics = $data;
        update_option(self::OPTION_KEY, $data);
    }
    
    /**
     * Default analytics structure
     */
    protected static function defaults(): array {
        return [
            'total_searches' => 0,
            'no_result_searches' => 0,
            'helpful_searches' => 0,
            'unhelpful_searches' => 0,
            'total_indexing_ops' => 0,
            'total_docs_indexed' => 0,
            'daily' => [],
            'doc_access' => [],
            'last_search' => null,
            'last_indexing' => null,
        ];
    }
    
    /**
     * Get summary statistics
     */
    public static function getSummary(): array {
        $data = self::get();
        
        $total = $data['total_searches'] ?? 0;
        $noResults = $data['no_result_searches'] ?? 0;
        $helpful = $data['helpful_searches'] ?? 0;
        $unhelpful = $data['unhelpful_searches'] ?? 0;
        
        return [
            'total_searches' => $total,
            'search_success_rate' => $total > 0 ? round((($total - $noResults) / $total) * 100, 1) : 0,
            'helpful_rate' => ($helpful + $unhelpful) > 0 
                ? round(($helpful / ($helpful + $unhelpful)) * 100, 1) 
                : null,
            'total_indexing_ops' => $data['total_indexing_ops'] ?? 0,
            'total_docs_indexed' => $data['total_docs_indexed'] ?? 0,
            'daily_trend' => array_slice($data['daily'] ?? [], -7, 7, true),
            'top_docs' => array_slice($data['doc_access'] ?? [], 0, 10, true),
        ];
    }
    
    /**
     * Reset analytics
     */
    public static function reset(): void {
        self::$analytics = self::defaults();
        update_option(self::OPTION_KEY, self::$analytics);
    }
}
