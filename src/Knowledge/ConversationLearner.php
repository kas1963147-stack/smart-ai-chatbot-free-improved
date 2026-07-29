<?php
declare(strict_types=1);
/**
 * Conversation Learner
 * 
 * Learn from conversations to improve knowledge base.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Conversation Learner
 */
class ConversationLearner {
    
    /** @var string Option key for learned FAQs */
    const LEARNED_FAQS_OPTION = 'swc_knowledge_learned_faqs';
    
    /** @var string Option key for search gaps */
    const SEARCH_GAPS_OPTION = 'swc_knowledge_search_gaps';
    
    /**
     * Extract potential FAQ from conversation
     */
    public function extractFAQ(array $messages): ?array {
        if (count($messages) < 2) {
            return null;
        }
        
        // Find question-answer pairs
        $faqs = [];
        
        for ($i = 0; $i < count($messages) - 1; $i++) {
            $current = $messages[$i];
            $next = $messages[$i + 1];
            
            // Look for user question followed by bot answer
            if (($current['role'] ?? '') === 'user' &&
                ($next['role'] ?? '') === 'assistant') {
                
                $question = $current['content'] ?? '';
                $answer = $next['content'] ?? '';
                
                // Check if it's a good FAQ candidate
                if ($this->isGoodFAQCandidate($question, $answer)) {
                    $faqs[] = [
                        'question' => $question,
                        'answer' => $answer,
                        'confidence' => $this->calculateConfidence($question, $answer),
                    ];
                }
            }
        }
        
        if (empty($faqs)) {
            return null;
        }
        
        // Return highest confidence FAQ
        usort($faqs, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        return $faqs[0];
    }
    
    /**
     * Check if question-answer pair is a good FAQ candidate
     */
    protected function isGoodFAQCandidate(string $question, string $answer): bool {
        // Minimum lengths
        if (strlen($question) < 10 || strlen($answer) < 20) {
            return false;
        }
        
        // Question should end with ? or be a question phrase
        $questionIndicators = ['?', 'how', 'what', 'where', 'when', 'why', 'who', 'can', 'do', 'does', 'is', 'are'];
        $hasQuestionIndicator = false;
        
        foreach ($questionIndicators as $indicator) {
            if (str_contains(strtolower($question), $indicator)) {
                $hasQuestionIndicator = true;
                break;
            }
        }
        
        if (!$hasQuestionIndicator) {
            return false;
        }
        
        // Answer should be informative (not just "I don't know")
        $negativePatterns = [
            'i don\'t know',
            'i\'m not sure',
            'i cannot',
            'i can\'t help',
            'please contact',
        ];
        
        foreach ($negativePatterns as $pattern) {
            if (str_contains(strtolower($answer), $pattern)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Calculate confidence score for FAQ
     */
    protected function calculateConfidence(string $question, string $answer): float {
        $score = 0.5; // Base score
        
        // Longer, more detailed answers = higher confidence
        if (strlen($answer) > 100) $score += 0.1;
        if (strlen($answer) > 200) $score += 0.1;
        
        // Well-formed questions = higher confidence
        if (str_ends_with($question, '?')) $score += 0.1;
        
        // Common FAQ patterns = higher confidence
        $faqPatterns = [
            'return policy', 'shipping', 'refund', 'exchange',
            'hours', 'location', 'contact', 'price', 'cost',
            'payment', 'warranty', 'guarantee'
        ];
        
        foreach ($faqPatterns as $pattern) {
            if (str_contains(strtolower($question), $pattern)) {
                $score += 0.1;
                break;
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Save learned FAQ
     */
    public function saveFAQ(array $faq): int {
        $faqs = get_option(self::LEARNED_FAQS_OPTION, []);
        
        // Check for duplicates
        foreach ($faqs as $existing) {
            if ($this->isSimilar($existing['question'], $faq['question'])) {
                return 0; // Already exists
            }
        }
        
        $faq['id'] = count($faqs) + 1;
        $faq['created_at'] = current_time('mysql');
        $faq['status'] = 'pending'; // pending, approved, rejected
        
        $faqs[] = $faq;
        update_option(self::LEARNED_FAQS_OPTION, $faqs);
        
        return $faq['id'];
    }
    
    /**
     * Get learned FAQs
     */
    public function getLearnedFAQs(?string $status = null): array {
        $faqs = get_option(self::LEARNED_FAQS_OPTION, []);
        
        if ($status) {
            $faqs = array_filter($faqs, fn($f) => $f['status'] === $status);
        }
        
        return array_values($faqs);
    }
    
    /**
     * Approve FAQ and add to knowledge base
     */
    public function approveFAQ(int $faqId): bool {
        $faqs = get_option(self::LEARNED_FAQS_OPTION, []);
        
        foreach ($faqs as &$faq) {
            if ($faq['id'] === $faqId) {
                $faq['status'] = 'approved';
                
                // Add to knowledge base as a document
                $this->addFAQToKnowledgeBase($faq);
                
                update_option(self::LEARNED_FAQS_OPTION, $faqs);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Reject FAQ
     */
    public function rejectFAQ(int $faqId): bool {
        $faqs = get_option(self::LEARNED_FAQS_OPTION, []);
        
        foreach ($faqs as &$faq) {
            if ($faq['id'] === $faqId) {
                $faq['status'] = 'rejected';
                update_option(self::LEARNED_FAQS_OPTION, $faqs);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add FAQ to knowledge base
     */
    protected function addFAQToKnowledgeBase(array $faq): void {
        // Create FAQ document
        $content = "## {$faq['question']}\n\n{$faq['answer']}";
        
        $doc = new KnowledgeDocument();
        $doc->title = $faq['question'];
        $doc->path = 'learned_faq_' . $faq['id'];
        $doc->contentType = 'faq';
        $doc->wordCount = str_word_count($content);
        $doc->setMetadata('is_learned', true);
        $doc->setMetadata('confidence', $faq['confidence']);
        $doc->save();
        
        // Note: Full indexing with embeddings would be done by KnowledgeIndexer
    }
    
    /**
     * Record a search gap (query with no results)
     */
    public function recordSearchGap(string $query): void {
        $gaps = get_option(self::SEARCH_GAPS_OPTION, []);
        
        $normalized = strtolower(trim($query));
        $found = false;
        
        foreach ($gaps as &$gap) {
            if (strtolower($gap['query']) === $normalized) {
                $gap['count']++;
                $gap['last_seen'] = current_time('mysql');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $gaps[] = [
                'query' => $query,
                'count' => 1,
                'first_seen' => current_time('mysql'),
                'last_seen' => current_time('mysql'),
            ];
        }
        
        // Keep only top 100
        usort($gaps, fn($a, $b) => $b['count'] <=> $a['count']);
        $gaps = array_slice($gaps, 0, 100);
        
        update_option(self::SEARCH_GAPS_OPTION, $gaps);
    }
    
    /**
     * Get search gaps (content holes)
     */
    public function getSearchGaps(int $limit = 20): array {
        $gaps = get_option(self::SEARCH_GAPS_OPTION, []);
        
        usort($gaps, fn($a, $b) => $b['count'] <=> $a['count']);
        
        return array_slice($gaps, 0, $limit);
    }
    
    /**
     * Clear search gaps
     */
    public function clearSearchGaps(): void {
        delete_option(self::SEARCH_GAPS_OPTION);
    }
    
    /**
     * Check if two strings are similar
     */
    protected function isSimilar(string $a, string $b, float $threshold = 0.8): bool {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));
        
        similar_text($a, $b, $percent);
        
        return ($percent / 100) >= $threshold;
    }
}
