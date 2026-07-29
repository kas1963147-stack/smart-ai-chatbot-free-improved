<?php
declare(strict_types=1);
/**
 * Content Gap Analyzer
 * 
 * Analyze search patterns to identify missing content.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Gap Analyzer
 */
class ContentGapAnalyzer {
    
    /** @var ConversationLearner */
    protected ConversationLearner $learner;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->learner = new ConversationLearner();
    }
    
    /**
     * Analyze and get content gap report
     */
    public function analyze(): array {
        $gaps = $this->learner->getSearchGaps(50);
        $pendingFaqs = $this->learner->getLearnedFAQs('pending');
        $sources = KnowledgeSource::active();
        $vectorStore = new WordPressVectorStore();
        
        // Categorize gaps by topic
        $categorized = $this->categorizeGaps($gaps);
        
        // Get freshness issues
        $staleContent = $this->getStaleContent($sources);
        
        return [
            'search_gaps' => [
                'total' => count($gaps),
                'by_category' => $categorized,
                'top_queries' => array_slice($gaps, 0, 10),
            ],
            'pending_faqs' => [
                'total' => count($pendingFaqs),
                'items' => $pendingFaqs,
            ],
            'content_freshness' => [
                'stale_sources' => $staleContent,
            ],
            'recommendations' => $this->generateRecommendations($categorized, $staleContent, $pendingFaqs),
        ];
    }
    
    /**
     * Categorize gaps by topic
     */
    protected function categorizeGaps(array $gaps): array {
        $categories = [
            'shipping' => ['shipping', 'delivery', 'ship', 'tracking', 'arrive'],
            'returns' => ['return', 'refund', 'exchange', 'money back'],
            'products' => ['product', 'item', 'stock', 'available', 'size', 'color'],
            'payment' => ['pay', 'payment', 'credit', 'card', 'invoice', 'price'],
            'account' => ['account', 'login', 'password', 'profile', 'order history'],
            'support' => ['help', 'support', 'contact', 'issue', 'problem'],
        ];
        
        $result = [];
        foreach ($categories as $category => $keywords) {
            $result[$category] = 0;
        }
        $result['other'] = 0;
        
        foreach ($gaps as $gap) {
            $query = strtolower($gap['query']);
            $matched = false;
            
            foreach ($categories as $category => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($query, $keyword)) {
                        $result[$category] += $gap['count'];
                        $matched = true;
                        break 2;
                    }
                }
            }
            
            if (!$matched) {
                $result['other'] += $gap['count'];
            }
        }
        
        return $result;
    }
    
    /**
     * Get stale content (not indexed recently)
     */
    protected function getStaleContent(array $sources): array {
        $stale = [];
        $threshold = strtotime('-7 days');
        
        foreach ($sources as $source) {
            if ($source->lastIndexed) {
                $lastIndexed = strtotime($source->lastIndexed);
                
                if ($lastIndexed < $threshold) {
                    $stale[] = [
                        'id' => $source->id,
                        'name' => $source->name,
                        'last_indexed' => $source->lastIndexed,
                        'days_stale' => floor((time() - $lastIndexed) / 86400),
                    ];
                }
            } else {
                $stale[] = [
                    'id' => $source->id,
                    'name' => $source->name,
                    'last_indexed' => null,
                    'days_stale' => null,
                ];
            }
        }
        
        return $stale;
    }
    
    /**
     * Generate recommendations
     */
    protected function generateRecommendations(array $gapsByCategory, array $staleContent, array $pendingFaqs): array {
        $recommendations = [];
        
        // Top gap categories
        arsort($gapsByCategory);
        $topCategories = array_slice($gapsByCategory, 0, 3, true);
        
        foreach ($topCategories as $category => $count) {
            if ($count > 5) {
                $recommendations[] = [
                    'type' => 'content_gap',
                    'priority' => 'high',
                    'message' => "Add more content about {$category}. {$count} unanswered queries detected.",
                    'category' => $category,
                    'action' => 'create_content',
                ];
            }
        }
        
        // Stale content
        foreach ($staleContent as $stale) {
            if ($stale['days_stale'] === null) {
                $recommendations[] = [
                    'type' => 'never_indexed',
                    'priority' => 'high',
                    'message' => "Source '{$stale['name']}' has never been indexed.",
                    'source_id' => $stale['id'],
                    'action' => 'sync_source',
                ];
            } elseif ($stale['days_stale'] > 30) {
                $recommendations[] = [
                    'type' => 'stale_content',
                    'priority' => 'medium',
                    'message' => "Source '{$stale['name']}' hasn't been indexed in {$stale['days_stale']} days.",
                    'source_id' => $stale['id'],
                    'action' => 'sync_source',
                ];
            }
        }
        
        // Pending FAQs
        if (count($pendingFaqs) > 5) {
            $recommendations[] = [
                'type' => 'pending_review',
                'priority' => 'medium',
                'message' => count($pendingFaqs) . " learned FAQs need review and approval.",
                'action' => 'review_faqs',
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get quick stats
     */
    public function getQuickStats(): array {
        $gaps = $this->learner->getSearchGaps();
        $pendingFaqs = $this->learner->getLearnedFAQs('pending');
        $sources = KnowledgeSource::active();
        
        $staleCount = 0;
        $threshold = strtotime('-7 days');
        
        foreach ($sources as $source) {
            if (!$source->lastIndexed || strtotime($source->lastIndexed) < $threshold) {
                $staleCount++;
            }
        }
        
        return [
            'search_gaps' => count($gaps),
            'pending_faqs' => count($pendingFaqs),
            'stale_sources' => $staleCount,
            'total_sources' => count($sources),
        ];
    }
}
