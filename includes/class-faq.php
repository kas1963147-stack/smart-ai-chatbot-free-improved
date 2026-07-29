<?php
/**
 * FAQ Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_FAQ {
    
    /**
     * Get all FAQs
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_faq';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is safely constructed from wpdb prefix
        return $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id ASC");
    }
    
    /**
     * Search FAQs by keyword
     */
    public static function search($keyword) {
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_faq';
        $keyword = sanitize_text_field($keyword);
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE question LIKE %s OR keywords LIKE %s LIMIT 3",
            $like, $like
        ));
        
        return $results;
    }
    
    /**
     * Find best matching FAQ
     * Returns a match only if there's a strong relevance to avoid false positives
     */
    public static function find_match($message) {
        $message = strtolower(trim($message));
        $faqs = self::get_all();
        
        if (empty($faqs)) {
            return null;
        }
        
        $best_match = null;
        $best_score = 0;
        
        foreach ($faqs as $faq) {
            $score = 0;
            $keyword_matched = false;
            
            // Check question similarity
            $question = strtolower($faq->question);
            similar_text($message, $question, $percent);
            $score = $percent;
            
            // Check keywords - this is the most important matching criteria
            if (!empty($faq->keywords)) {
                $keywords = array_map('trim', explode(',', strtolower($faq->keywords)));
                foreach ($keywords as $kw) {
                    if (strlen($kw) > 1 && strpos($message, $kw) !== false) {
                        $score += 30; // Increased from 20 to 30
                        $keyword_matched = true;
                    }
                }
            }
            
            // Only add question word bonus if keywords also matched
            if ($keyword_matched) {
                $question_words = array('what', 'how', 'where', 'when', 'why', 'can', 'do', 'is');
                foreach ($question_words as $qw) {
                    if (strpos($message, $qw) !== false && strpos($question, $qw) !== false) {
                        $score += 5;
                    }
                }
            }
            
            // Higher threshold (50 instead of 30) to avoid false matches
            // Also require either high similarity OR keyword match
            if ($score > $best_score && ($score >= 50 || ($keyword_matched && $score >= 40))) {
                $best_score = $score;
                $best_match = $faq;
            }
        }
        
        return $best_match;
    }
}
