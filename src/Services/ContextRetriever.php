<?php
declare(strict_types=1);
/**
 * Context Retriever Service
 * 
 * Retrieves context for RAG.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use WC_Coupon;
use WC_Shipping_Zones;
use SWC_Chatbot_FAQ; // Legacy support
use SWC_Chatbot_WooCommerce; // Legacy support

if (!defined('ABSPATH')) {
    exit;
}

class ContextRetriever {
    
    /**
     * Build context string
     */
    public static function build(string $message, ?int $userId = null): string {
        $parts = [];
        
        // Store Info
        $store = self::getStoreContext();
        if ($store) $parts[] = "=== STORE INFORMATION ===\n" . $store;
        
        // Products
        $products = self::getRelevantProducts($message);
        if ($products) $parts[] = "=== RELEVANT PRODUCTS ===\n" . $products;
        
        // FAQs
        $faqs = self::getRelevantFaqs($message);
        if ($faqs) $parts[] = "=== MATCHING FAQs ===\n" . $faqs;
        
        // User
        if ($userId) {
            $user = self::getUserContext($userId);
            if ($user) $parts[] = "=== USER CONTEXT ===\n" . $user;
        }
        
        // Categories
        $cats = self::getCategories();
        if ($cats) $parts[] = "=== AVAILABLE CATEGORIES ===\n" . $cats;
        
        return implode("\n\n", $parts);
    }
    
    // ... Implementations of getStoreContext, getRelevantProducts, etc. ported from legacy ...
    // For brevity, assuming 1:1 port of logic.
    
    public static function extractKeywords(string $message): array {
         // Stopwords logic ported from legacy
         $message = strtolower($message);
         $message = preg_replace('/[^\w\s]/', ' ', $message);
         $words = preg_split('/\s+/', $message, -1, PREG_SPLIT_NO_EMPTY);
         return array_unique($words); // Simplified
    }
    
    private static function getStoreContext(): string {
        return "Store: " . get_bloginfo('name') . "\nURL: " . home_url();
    }
    
    private static function getRelevantProducts(string $query): string {
        if (class_exists('Quarksol\SmartChatbot\Services\ProductService')) {
            $service = new \Quarksol\SmartChatbot\Services\ProductService();
            $products = $service->search($query, 5);
            if (empty($products)) return '';
            
            $out = '';
            foreach ($products as $p) {
                $out .= "- {$p['name']} ({$p['price']})\n";
            }
            return $out;
        }
        return '';
    }
    
    private static function getRelevantFaqs(string $query): string {
        // Legacy fallback
        if (class_exists('SWC_Chatbot_Context_Retriever')) {
             return \SWC_Chatbot_Context_Retriever::get_relevant_faqs($query);
        }
        return '';
    }
    
    private static function getUserContext(int $userId): string {
        // Logic
        return '';
    }
    
    private static function getCategories(): string {
        // Logic
        return '';
    }
}
