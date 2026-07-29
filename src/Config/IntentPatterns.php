<?php
declare(strict_types=1);
/**
 * Intent Patterns Configuration
 * 
 * Centralized repository for all intent detection patterns and keywords.
 * Replaces hardcoded patterns scattered throughout class-chatbot.php.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Intent Patterns
 * 
 * Contains all regex patterns and keyword lists for intent detection.
 */
class IntentPatterns
{
    // ========== INTENT TYPES ==========
    
    const INTENT_PRODUCT_SEARCH = 'product_search';
    const INTENT_PRODUCT_BROWSE = 'product_browse';
    const INTENT_CATEGORY_BROWSE = 'category_browse';
    const INTENT_BEST_SELLERS = 'best_sellers';
    const INTENT_ON_SALE = 'on_sale';
    const INTENT_NEW_ARRIVALS = 'new_arrivals';
    const INTENT_ORDER_TRACKING = 'order_tracking';
    const INTENT_CART = 'cart';
    const INTENT_WISHLIST = 'wishlist';
    const INTENT_SHIPPING = 'shipping';
    const INTENT_PAYMENT = 'payment';
    const INTENT_SUPPORT = 'support';
    const INTENT_FAQ = 'faq';
    const INTENT_STORE_INFO = 'store_info';
    const INTENT_KNOWLEDGE = 'knowledge';
    const INTENT_COMPARE = 'compare';
    const INTENT_GREETING = 'greeting';
    const INTENT_THANKS = 'thanks';
    const INTENT_HELP = 'help';
    const INTENT_OFF_TOPIC = 'off_topic';
    const INTENT_UNKNOWN = 'unknown';
    
    // ========== PRODUCT INTENT PATTERNS ==========
    
    /**
     * Patterns that indicate user wants to search/browse products
     */
    const PRODUCT_SEARCH_PATTERNS = [
        '/(?:show|find|get|search|looking\s+for|need|want)\s+(?:me\s+)?(?:products?|items?)/i',
        '/products?\s+(?:for|about|related|of|in|from)/i',
        '/(?:i\s+)?(?:need|want|looking\s+for|searching\s+for|find\s+me)\s+(?:a\s+)?(\w+)/i',
    ];
    
    /**
     * Patterns for category-based product requests
     */
    const CATEGORY_PATTERNS = [
        '/products?\s+(?:of|from|in)\s+(?:category|categorie?s?)\s+[:\"\']?\s*(\w[\w\s]+)/i',
        '/(?:^|\s)(?:category|categorie?s?)\s+[:\"\']?\s*(\w[\w\s]+?)(?:\s*$|["\'])/i',
        '/products?\s+(?:for|about|related\s+to)\s+(\w[\w\s]+)/i',
    ];
    
    /**
     * Keywords indicating product-related queries
     */
    const PRODUCT_KEYWORDS = [
        'product', 'item', 'buy', 'purchase', 'find', 'search', 'looking for',
        'need', 'want', 'show me', 'get me', 'where can i find',
    ];
    
    /**
     * Keywords that look like product types/names
     */
    const PRODUCT_TYPE_KEYWORDS = [
        'phone', 'laptop', 'computer', 'shirt', 'dress', 'shoe', 'watch',
        'tv', 'television', 'camera', 'headphone', 'speaker', 'tablet',
        'samsung', 'apple', 'iphone', 'android', 'nike', 'adidas',
        'electronic', 'clothing', 'accessory', 'furniture', 'appliance',
    ];
    
    // ========== ORDER INTENT KEYWORDS ==========
    
    const ORDER_KEYWORDS = [
        'order', 'track', 'tracking', 'where is my', 'delivery status',
        'shipment', 'shipping status', 'order status', 'my order',
    ];
    
    // ========== BEST SELLERS KEYWORDS ==========
    
    const BEST_SELLERS_KEYWORDS = [
        'best product', 'best seller', 'bestseller', 'popular', 'most demanding',
        'top selling', 'trending', 'hot product', 'what sells', 'top product', 'best item',
    ];
    
    // ========== SALE KEYWORDS ==========
    
    const SALE_KEYWORDS = [
        'sale', 'discount', 'offer', 'deal', 'on sale', 'special offer',
        'promotion', 'saving', 'clearance', 'markdown',
    ];
    
    // ========== NEW ARRIVALS KEYWORDS ==========
    
    const NEW_ARRIVALS_KEYWORDS = [
        'new arrival', 'new product', 'latest', 'just arrived', 'new in',
        'newest', 'recent', 'just added', 'fresh',
    ];
    
    // ========== CART KEYWORDS ==========
    
    const CART_KEYWORDS = [
        'my cart', 'view cart', 'checkout', 'complete order', 'finish order',
        'cart item', 'basket', 'my items', 'what did i add',
    ];
    
    // ========== WISHLIST KEYWORDS ==========
    
    const WISHLIST_KEYWORDS = [
        'my wishlist', 'view wishlist', 'show wishlist', 'saved items', 'saved products',
    ];
    
    // ========== SHIPPING KEYWORDS ==========
    
    const SHIPPING_KEYWORDS = [
        'shipping', 'delivery', 'ship', 'deliver', 'how long',
        'shipping cost', 'delivery time', 'free shipping',
    ];
    
    // ========== PAYMENT KEYWORDS ==========
    
    const PAYMENT_KEYWORDS = [
        'payment', 'pay', 'credit card', 'paypal', 'how to pay',
        'payment method', 'payment option', 'accept',
    ];
    
    // ========== SUPPORT KEYWORDS ==========
    
    const SUPPORT_KEYWORDS = [
        'talk to human', 'real person', 'agent', 'live chat',
        'speak to someone', 'human support', 'not a bot',
        'representative', 'operator', 'customer service',
    ];
    
    // ========== FAQ KEYWORDS ==========
    
    const FAQ_KEYWORDS = [
        'faq', 'all faq', 'frequently asked', 'all question',
        'common question', 'q&a', 'show faq', 'list faq',
    ];
    
    // ========== STORE INFO KEYWORDS ==========
    
    const STORE_INFO_KEYWORDS = [
        'about store', 'about your store', 'tell me about', 'store info',
        'about this store', 'about shop', 'what is this', 'who are you',
    ];
    
    // ========== KNOWLEDGE BASE PATTERNS ==========
    
    const KNOWLEDGE_PATTERNS = [
        '/^@knowledge\s+(.+)$/i',
        '/^search\s+knowledge\s+(.+)$/i',
        '/^kb:\s*(.+)$/i',
    ];
    
    // ========== COMPARISON PATTERNS ==========
    
    const COMPARE_PATTERNS = [
        '/compare\s+(?:products?|ids?):\s*([\d,\s]+)/i',
        '/compa[ri]+e?\s+(.+?)\s+(?:and|vs|versus|with|,)\s+(.+?)(?:\s+(?:and|vs|versus|with|,)\s+(.+?))?$/i',
    ];
    
    const COMPARE_KEYWORDS = [
        'compare', 'comparison', 'versus', 'vs', 'difference between',
    ];
    
    // ========== GREETING KEYWORDS ==========
    
    const GREETING_KEYWORDS = [
        'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening',
        'howdy', 'greetings', 'yo', 'hiya',
    ];
    
    // ========== THANKS KEYWORDS ==========
    
    const THANKS_KEYWORDS = [
        'thank', 'thanks', 'appreciate', 'thx', 'ty', 'thank you',
    ];
    
    // ========== HELP KEYWORDS ==========
    
    const HELP_KEYWORDS = [
        'help', 'what can you do', 'how does this work', 'commands',
        'your capabilities', 'what do you do',
    ];
    
    // ========== OFF-TOPIC PATTERNS ==========
    
    /**
     * Patterns that indicate off-topic queries (not related to shopping)
     */
    const OFF_TOPIC_PATTERNS = [
        // People/celebrities
        '/\b(who is|tell me about|what do you know about|biography of|life of)\s+[a-z]+/i',
        // General knowledge
        '/\b(capital of|population of|weather in|how to cook|recipe for|news about)\b/i',
        // Sports/entertainment (unless product-related)
        '/\b(football|soccer|basketball|cricket|movie|film|music|song|singer|actor|actress)\b(?!.*(product|item|merch))/i',
        // Philosophy/abstract
        '/\b(meaning of life|why are we here|what is the purpose|philosophical|existential)\b/i',
        // History/geography unrelated to products
        '/\b(history of|when was|where is|located in|country of)\b(?!.*(product|item|store|shop))/i',
        // Programming/tech help (not product related)
        '/\b(how to code|programming|javascript|python|what is ai|machine learning)\b(?!.*(product|item))/i',
    ];
    
    /**
     * Known celebrity/public figure names (for off-topic detection)
     */
    const OFF_TOPIC_CELEBRITIES = [
        'elon musk', 'jeff bezos', 'bill gates', 'steve jobs', 'mark zuckerberg',
        'trump', 'biden', 'obama', 'putin', 'modi',
        'taylor swift', 'beyonce', 'kim kardashian', 'kanye', 'drake',
        'cristiano ronaldo', 'messi', 'lebron james', 'michael jordan',
    ];
    
    // ========== POLICY KEYWORDS ==========
    
    const REFUND_KEYWORDS = [
        'refund', 'return', 'money back', 'exchange', 'return policy', 'refund policy',
    ];
    
    const POLICY_KEYWORDS = [
        'privacy', 'terms', 'policy', 'policies', 'terms and condition', 'legal',
    ];
    
    const COUPON_KEYWORDS = [
        'coupon', 'promo', 'discount code', 'voucher', 'code', 'promotion',
    ];
    
    // ========== PRICE KEYWORDS ==========
    
    const BUDGET_KEYWORDS = [
        'cheapest', 'budget', 'affordable', 'low price', 'inexpensive', 'cheap',
    ];
    
    const PREMIUM_KEYWORDS = [
        'expensive', 'premium', 'luxury', 'high end', 'top of the line', 'best quality',
    ];
    
    // ========== MISC KEYWORDS ==========
    
    const FEATURED_KEYWORDS = [
        'featured', 'recommend', 'suggestion', 'suggest',
        'what should i buy', 'what do you recommend',
    ];
    
    const TOP_RATED_KEYWORDS = [
        'top rated', 'best rated', 'highest rated', 'best review',
        '5 star', 'highly rated', 'good review',
    ];
    
    const RANDOM_KEYWORDS = [
        'random', 'surprise', 'anything', 'whatever', 'show me some', 'something',
    ];
    
    const AVAILABLE_KEYWORDS = [
        'available', 'in stock', 'what is available', 'what\'s available',
        'ready to ship', 'can buy now',
    ];
    
    // ========== HELPER METHODS ==========
    
    /**
     * Check if message matches any pattern in a list
     */
    public static function matchesAny(string $message, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if message contains any keyword from a list
     */
    public static function containsAny(string $message, array $keywords): bool
    {
        $messageLower = strtolower($message);
        foreach ($keywords as $keyword) {
            if (strpos($messageLower, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Extract matched group from first matching pattern
     */
    public static function extractMatch(string $message, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $matches[1] ?? null;
            }
        }
        return null;
    }
    
    /**
     * Get all keywords for a specific intent type
     */
    public static function getKeywordsForIntent(string $intent): array
    {
        return match($intent) {
            self::INTENT_PRODUCT_SEARCH => self::PRODUCT_KEYWORDS,
            self::INTENT_BEST_SELLERS => self::BEST_SELLERS_KEYWORDS,
            self::INTENT_ON_SALE => self::SALE_KEYWORDS,
            self::INTENT_NEW_ARRIVALS => self::NEW_ARRIVALS_KEYWORDS,
            self::INTENT_ORDER_TRACKING => self::ORDER_KEYWORDS,
            self::INTENT_CART => self::CART_KEYWORDS,
            self::INTENT_WISHLIST => self::WISHLIST_KEYWORDS,
            self::INTENT_SHIPPING => self::SHIPPING_KEYWORDS,
            self::INTENT_PAYMENT => self::PAYMENT_KEYWORDS,
            self::INTENT_SUPPORT => self::SUPPORT_KEYWORDS,
            self::INTENT_FAQ => self::FAQ_KEYWORDS,
            self::INTENT_STORE_INFO => self::STORE_INFO_KEYWORDS,
            self::INTENT_COMPARE => self::COMPARE_KEYWORDS,
            self::INTENT_GREETING => self::GREETING_KEYWORDS,
            self::INTENT_THANKS => self::THANKS_KEYWORDS,
            self::INTENT_HELP => self::HELP_KEYWORDS,
            default => [],
        };
    }
}
