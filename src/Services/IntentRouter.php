<?php
declare(strict_types=1);
/**
 * Intent Router Service
 * 
 * Centralized intent detection and routing.
 * Replaces the monolithic process_message() method in class-chatbot.php.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Config\IntentPatterns;
use Quarksol\SmartChatbot\Config\ChatbotConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Intent Router
 * 
 * Detects user intent from messages and routes to appropriate handlers.
 */
class IntentRouter
{
    /** @var ProductService */
    protected ProductService $productService;
    
    /** @var string Current context (for multi-turn conversations) */
    protected string $context = '';
    
    /** @var int Current agent ID */
    protected int $agentId = 0;
    
    /** @var string Current session ID */
    protected string $sessionId = '';

    /** @var array Conversation history */
    protected array $history = [];

    /** @var int|null Message index for history linkage */
    protected ?int $messageIndex = null;

    /** @var string|null Message ID for history linkage */
    protected ?string $messageId = null;
    
    /**
     * Constructor
     */
    public function __construct(?ProductService $productService = null)
    {
        $this->productService = $productService ?? new ProductService();
    }
    
    /**
     * Route a message to the appropriate handler
     * 
     * @param string $message User message
     * @param string $context Current conversation context
     * @param int $agentId Active agent ID
     * @param string $sessionId Session identifier
     * @param array $history Previous conversation messages for context
     * @param int|null $messageIndex Message index for tool call linkage
     * @param string|null $messageId Message ID for tool call linkage
     * @return array Response array with type and message
     */
    public function route(
        string $message,
        string $context = '',
        int $agentId = 0,
        string $sessionId = '',
        array $history = [],
        ?int $messageIndex = null,
        ?string $messageId = null
    ): array
    {
        $this->context = $context;
        $this->agentId = $agentId;
        $this->sessionId = $sessionId;
        $this->history = $history;
        $this->messageIndex = $messageIndex;
        $this->messageId = $messageId;
        
        $config = null;
        $agentDbId = null;
        $agentSlug = null;
        if ($agentId > 0 && class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
            $dbAgent = \Quarksol\SmartChatbot\Models\ChatAgent::find($agentId);
            if ($dbAgent && $dbAgent->config) {
                $config = $dbAgent->config;
                $agentDbId = $dbAgent->id;
                $agentSlug = $dbAgent->agentId;
            }
        }
        
        if (!$config && class_exists('\Quarksol\SmartChatbot\Models\\ChatAgent')) {
            $defaultAgent = \Quarksol\SmartChatbot\Models\ChatAgent::getDefault();
            if ($defaultAgent && $defaultAgent->config) {
                $config = $defaultAgent->config;
                $agentDbId = $defaultAgent->id;
                $agentSlug = $defaultAgent->agentId;
            }
        }
        
        if ($config) {
            AgentContext::set($config, $agentDbId, $agentSlug);
        }
        
        try {
            $messageLower = strtolower($message);
            
            // Handle context-based flows first (multi-turn conversations)
            if (!empty($context)) {
                $contextResponse = $this->handleContextFlow($message, $context);
                if ($contextResponse !== null) {
                    return $contextResponse;
                }
            }
            
            // Detect intent
            $intent = $this->detectIntent($message, $messageLower);
            
            Logger::debug("Intent detected: {$intent}", ['message' => substr($message, 0, 50)]);
            error_log("[SWC Debug] IntentRouter detected intent: {$intent}");
            
            // Route to appropriate handler
            return $this->handleIntent($intent, $message, $messageLower);
        } finally {
            AgentContext::clear();
        }
    }
    
    /**
     * Detect the primary intent from a message
     */
    public function detectIntent(string $message, ?string $messageLower = null): string
    {
        $messageLower = $messageLower ?? strtolower($message);
        
        // Knowledge base command (explicit)
        if (IntentPatterns::matchesAny($message, IntentPatterns::KNOWLEDGE_PATTERNS)) {
            return IntentPatterns::INTENT_KNOWLEDGE;
        }
        
        // Off-topic detection (before other intents)
        if ($this->isOffTopic($message, $messageLower)) {
            return IntentPatterns::INTENT_OFF_TOPIC;
        }
        
        // Greeting
        if ($this->isGreeting($messageLower)) {
            return IntentPatterns::INTENT_GREETING;
        }
        
        // Thanks
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::THANKS_KEYWORDS)) {
            return IntentPatterns::INTENT_THANKS;
        }
        
        // Help
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::HELP_KEYWORDS)) {
            return IntentPatterns::INTENT_HELP;
        }
        
        // Comparison
        if (IntentPatterns::matchesAny($message, IntentPatterns::COMPARE_PATTERNS) ||
            IntentPatterns::containsAny($messageLower, IntentPatterns::COMPARE_KEYWORDS)) {
            return IntentPatterns::INTENT_COMPARE;
        }
        
        // Store info
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::STORE_INFO_KEYWORDS)) {
            return IntentPatterns::INTENT_STORE_INFO;
        }
        
        // Best sellers
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::BEST_SELLERS_KEYWORDS)) {
            return IntentPatterns::INTENT_BEST_SELLERS;
        }
        
        // On sale
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::SALE_KEYWORDS)) {
            return IntentPatterns::INTENT_ON_SALE;
        }
        
        // New arrivals
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::NEW_ARRIVALS_KEYWORDS)) {
            return IntentPatterns::INTENT_NEW_ARRIVALS;
        }
        
        // Order tracking
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::ORDER_KEYWORDS)) {
            return IntentPatterns::INTENT_ORDER_TRACKING;
        }
        
        // Cart
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::CART_KEYWORDS)) {
            return IntentPatterns::INTENT_CART;
        }
        
        // Wishlist
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::WISHLIST_KEYWORDS)) {
            return IntentPatterns::INTENT_WISHLIST;
        }
        
        // Shipping
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::SHIPPING_KEYWORDS)) {
            return IntentPatterns::INTENT_SHIPPING;
        }
        
        // Payment
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::PAYMENT_KEYWORDS)) {
            return IntentPatterns::INTENT_PAYMENT;
        }
        
        // Support
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::SUPPORT_KEYWORDS)) {
            return IntentPatterns::INTENT_SUPPORT;
        }
        
        // FAQ
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::FAQ_KEYWORDS)) {
            return IntentPatterns::INTENT_FAQ;
        }
        
        // Category browse (explicit category mentions)
        if ($this->isCategoryRequest($message, $messageLower)) {
            return IntentPatterns::INTENT_CATEGORY_BROWSE;
        }
        
        // Product search (general product-related queries)
        if ($this->isProductRequest($message, $messageLower)) {
            return IntentPatterns::INTENT_PRODUCT_SEARCH;
        }
        
        // Default to unknown (will be handled by AI fallback)
        return IntentPatterns::INTENT_UNKNOWN;
    }
    
    /**
     * Handle intent and return response
     */
    protected function handleIntent(string $intent, string $message, string $messageLower): array
    {
        return match($intent) {
            IntentPatterns::INTENT_KNOWLEDGE => $this->handleKnowledge($message),
            IntentPatterns::INTENT_OFF_TOPIC => $this->handleOffTopic(),
            IntentPatterns::INTENT_GREETING => $this->handleGreeting($message),
            IntentPatterns::INTENT_THANKS => $this->handleThanks(),
            IntentPatterns::INTENT_HELP => $this->handleHelp(),
            IntentPatterns::INTENT_COMPARE => $this->handleCompare($message),
            IntentPatterns::INTENT_STORE_INFO => $this->handleStoreInfo(),
            IntentPatterns::INTENT_BEST_SELLERS => $this->handleBestSellers(),
            IntentPatterns::INTENT_ON_SALE => $this->handleOnSale(),
            IntentPatterns::INTENT_NEW_ARRIVALS => $this->handleNewArrivals(),
            IntentPatterns::INTENT_ORDER_TRACKING => $this->handleOrderTracking($message),
            IntentPatterns::INTENT_CART => $this->handleCart(),
            IntentPatterns::INTENT_WISHLIST => $this->handleWishlist(),
            IntentPatterns::INTENT_SHIPPING => $this->handleShipping($message),
            IntentPatterns::INTENT_PAYMENT => $this->handlePayment(),
            IntentPatterns::INTENT_SUPPORT => $this->handleSupport(),
            IntentPatterns::INTENT_FAQ => $this->handleFAQ($message),
            IntentPatterns::INTENT_CATEGORY_BROWSE => $this->handleCategoryBrowse($message),
            IntentPatterns::INTENT_PRODUCT_SEARCH => $this->handleProductSearch($message),
            default => $this->handleFallback($message),
        };
    }
    
    // ========== CONTEXT HANDLERS ==========
    
    protected function handleContextFlow(string $message, string $context): ?array
    {
        if ($context === 'awaiting_order_id') {
            return $this->handleOrderLookup($message);
        }
        
        if ($context === 'awaiting_email') {
            // This would need order_id from session - delegate to legacy for now
            return null;
        }
        
        return null;
    }
    
    // ========== INTENT HANDLERS ==========
    
    protected function handleKnowledge(string $message): array
    {
        $query = IntentPatterns::extractMatch($message, IntentPatterns::KNOWLEDGE_PATTERNS);
        
        if ($query && AgentContext::isKnowledgeEnabled() && class_exists('\Quarksol\SmartChatbot\Knowledge\HybridSearcher')) {
            $allowedSources = AgentContext::getAllowedKnowledgeSources();
            if ($allowedSources !== null && empty($allowedSources)) {
                return ['type' => 'text', 'message' => 'No knowledge sources are enabled for this agent.'];
            }

            try {
                $searcher = new \Quarksol\SmartChatbot\Knowledge\HybridSearcher();
                $results = $searcher->search($query, 5);

                if ($allowedSources !== null) {
                    $results = array_values(array_filter($results, function($doc) use ($allowedSources) {
                        $metadata = $doc->metadata ?? [];
                        $sourceId = isset($metadata['source_id']) ? (int) $metadata['source_id'] : null;
                        return $sourceId !== null && in_array($sourceId, $allowedSources, true);
                    }));
                }
                
                if (!empty($results)) {
                    $response = " **Knowledge Base Results** for \"*{$query}*\":\n\n";
                    foreach ($results as $i => $doc) {
                        $source = $doc->getSourceName() ?: 'Unknown';
                        $content = $doc->getContent();
                        $score = round(($doc->score ?? 0) * 100);
                        if (strlen($content) > 300) {
                            $content = substr($content, 0, 300) . '...';
                        }
                        $response .= "**" . ($i + 1) . ". {$source}** ({$score}% match)\n{$content}\n\n---\n\n";
                    }
                    return ['type' => 'text', 'message' => $response, 'kb_results' => true];
                }
            } catch (\Throwable $e) {
                Logger::error('KB Search error: ' . $e->getMessage());
            }
        }
        
        return ['type' => 'text', 'message' => " Knowledge base search is not enabled. Ask me anything and I'll try to help!"];
    }
    
    protected function handleOffTopic(): array
    {
        $storeName = get_bloginfo('name');
        $responses = [
            "I'm your shopping assistant for **{$storeName}**! ️\n\nI can help you with:\n• Finding products\n• Tracking orders\n• Questions about our store\n\nFor general questions, try ChatGPT or Google! \n\nHow can I help you shop today?",
            "Oops! That's outside my expertise!  I'm the **{$storeName}** shopping assistant.\n\nI'm great at:\n• Product recommendations\n• Order tracking\n• Store FAQs\n\nAsk me about our products instead! ",
        ];
        return ['type' => 'text', 'message' => $responses[array_rand($responses)]];
    }
    
    protected function handleGreeting(string $message): array
    {
        // Let AI handle greetings for personalization
        return $this->handleFallback($message);
    }
    
    protected function handleThanks(): array
    {
        $responses = [
            "You're welcome!  Happy to help! Is there anything else you'd like to know?",
            "My pleasure!  Let me know if you need anything else!",
            "Anytime!  I'm here if you have more questions!",
        ];
        return ['type' => 'text', 'message' => $responses[array_rand($responses)]];
    }
    
    protected function handleHelp(): array
    {
        return [
            'type' => 'text',
            'message' => "I'm your AI shopping assistant!  Here's how I can help:\n\n" .
            "**️ Product Discovery**\n• Ask about our *best sellers*, *new arrivals*, or *deals*\n• Search for specific products by name\n• Browse by category or price range\n\n" .
            "** Orders & Support**\n• Track your order status\n• Get answers to common questions\n• Find out about our store\n\n" .
            "** Try asking:**\n• \"What are your most popular products?\"\n• \"Show me products under \$50\"\n• \"What's on sale right now?\"\n\nJust type naturally - I'll understand!"
        ];
    }
    
    protected function handleCompare(string $message): array
    {
        // Extract product names from comparison request
        if (preg_match('/compa[ri]+e?\s+(.+?)\s+(?:and|vs|versus|with|,)\s+(.+?)(?:\s+(?:and|vs|versus|with|,)\s+(.+?))?$/i', $message, $matches)) {
            $productNames = [trim($matches[1])];
            if (!empty($matches[2])) $productNames[] = trim($matches[2]);
            if (!empty($matches[3])) $productNames[] = trim($matches[3]);
            
            $products = [];
            foreach ($productNames as $name) {
                $searchResults = \SWC_Chatbot_WooCommerce::smart_search($name, 1);
                if (!empty($searchResults)) {
                    $products[] = $searchResults[0];
                }
            }
            
            if (count($products) >= 2) {
                $names = array_column($products, 'name');
                return [
                    'type' => 'comparison',
                    'message' => "Here's a comparison of **" . implode('** vs **', $names) . "**! ",
                    'products' => $products,
                ];
            }
        }
        
        return [
            'type' => 'text',
            'message' => "I can help you compare products! \n\n**How to compare:**\n• Say: **compare iPhone and Samsung**\n• Say: **compare Blue Shirt vs Red Shirt**\n\nJust use the product names and I'll find them for you!"
        ];
    }
    
    protected function handleStoreInfo(): array
    {
        $storeName = get_bloginfo('name');
        $stats = \SWC_Chatbot_WooCommerce::get_store_stats();
        $cats = implode(', ', array_slice($stats['categories'], 0, 5));
        
        return [
            'type' => 'text',
            'message' => "Welcome to **{$storeName}**! \n\nWe're your go-to destination for quality products. Here's a quick overview:\n\n" .
            " **{$stats['total_products']}** total products\n" .
            " **{$stats['in_stock']}** in stock & ready to ship\n" .
            "️ **{$stats['on_sale']}** currently on sale\n" .
            " **{$stats['categories_count']}** categories to explore\n\n" .
            "Our top categories include: {$cats}\n\nHow can I help you find what you're looking for today?"
        ];
    }
    
    protected function handleBestSellers(): array
    {
        $products = \SWC_Chatbot_WooCommerce::get_best_sellers(5);
        if (!empty($products)) {
            return [
                'type' => 'products',
                'message' => "These are flying off our shelves!  Our customers absolutely love these products - they're our best sellers for a reason:",
                'products' => $products,
            ];
        }
        return ['type' => 'text', 'message' => "We're a new store and still building our sales data! Would you like to browse our available products instead?"];
    }
    
    protected function handleOnSale(): array
    {
        $products = \SWC_Chatbot_WooCommerce::get_on_sale(5);
        if (!empty($products)) {
            $stats = \SWC_Chatbot_WooCommerce::get_stock_summary();
            return [
                'type' => 'products',
                'message' => "You came at the perfect time! ️ We have **{$stats['on_sale']} products** on sale right now:",
                'products' => $products,
            ];
        }
        return ['type' => 'text', 'message' => "We don't have any active sales right now, but keep an eye out! Would you like to see our best sellers?"];
    }
    
    protected function handleNewArrivals(): array
    {
        $products = \SWC_Chatbot_WooCommerce::get_new_arrivals(5);
        if (!empty($products)) {
            return [
                'type' => 'products',
                'message' => "Fresh from our warehouse!  These just dropped and they're already getting attention:",
                'products' => $products,
            ];
        }
        return ['type' => 'text', 'message' => "We haven't added new products recently, but we're always updating! Want to see our best sellers?"];
    }
    
    protected function handleOrderTracking(string $message): array
    {
        if (is_user_logged_in()) {
            $userId = get_current_user_id();
            $orders = \SWC_Chatbot_Orders::get_user_orders($userId, 3);
            if (!empty($orders)) {
                return ['type' => 'orders', 'message' => "Here are your recent orders:", 'orders' => $orders];
            }
            return ['type' => 'text', 'message' => "You don't have any orders yet. Start shopping! "];
        }
        
        return [
            'type' => 'text',
            'message' => "I'd be happy to help you track your order! \n\nPlease enter your **order number** (you can find this in your confirmation email):",
            'context' => 'awaiting_order_id',
        ];
    }
    
    protected function handleOrderLookup(string $orderId): array
    {
        $orderId = preg_replace('/[^0-9]/', '', $orderId);
        if (empty($orderId)) {
            return [
                'type' => 'text',
                'message' => "Please enter a valid order number.",
                'context' => 'awaiting_order_id',
            ];
        }
        
        $order = \SWC_Chatbot_Orders::get_order($orderId);
        if (!$order) {
            return ['type' => 'text', 'message' => "Order #{$orderId} not found. Please check the order number and try again."];
        }
        
        return ['type' => 'order', 'message' => "Here's your order status:", 'order' => $order];
    }
    
    protected function handleCart(): array
    {
        $cartInfo = \SWC_Chatbot_WooCommerce::get_cart_info();
        if ($cartInfo['count'] > 0) {
            return [
                'type' => 'cart',
                'message' => "Here's your cart summary! \n\nYou have **{$cartInfo['count']} item(s)** with a total of **{$cartInfo['total']}**.\n\nReady to complete your purchase?",
                'cart' => $cartInfo,
            ];
        }
        return ['type' => 'text', 'message' => "Your cart is empty!  Would you like me to help you find some products?"];
    }
    
    protected function handleWishlist(): array
    {
        if (is_user_logged_in()) {
            $userId = get_current_user_id();
            $wishlist = get_user_meta($userId, 'swc_wishlist', true);
            if (!empty($wishlist) && is_array($wishlist)) {
                $products = [];
                foreach ($wishlist as $productId) {
                    $product = wc_get_product($productId);
                    if ($product) {
                        $products[] = \SWC_Chatbot_WooCommerce::format_product($product);
                    }
                }
                if (!empty($products)) {
                    return ['type' => 'products', 'message' => "️ Here are your wishlisted products:", 'products' => $products];
                }
            }
        }
        return ['type' => 'text', 'message' => "Your wishlist is empty! ️ Browse our items and click the heart button to save products."];
    }
    
    protected function handleShipping(string $message): array
    {
        $shipping = \SWC_Chatbot_WooCommerce::get_shipping_info();
        if (!empty($shipping)) {
            $methodsText = "";
            foreach ($shipping as $method) {
                $cost = is_numeric($method['cost']) ? '$' . $method['cost'] : $method['cost'];
                $methodsText .= "• **{$method['title']}**: {$cost}\n";
            }
            return [
                'type' => 'text',
                'message' => "Great question about shipping!  Here are our delivery options:\n\n{$methodsText}\nShipping times vary based on your location."
            ];
        }
        return ['type' => 'text', 'message' => "We offer shipping to most locations!  Shipping costs are calculated at checkout based on your address."];
    }
    
    protected function handlePayment(): array
    {
        $payments = \SWC_Chatbot_WooCommerce::get_payment_methods();
        if (!empty($payments)) {
            $methodsText = "";
            foreach ($payments as $method) {
                $methodsText .= "• **{$method['title']}**\n";
            }
            return [
                'type' => 'text',
                'message' => "We accept multiple payment methods! \n\n{$methodsText}\nAll transactions are secure and encrypted."
            ];
        }
        return ['type' => 'text', 'message' => "We accept various secure payment methods!  You'll see all options at checkout."];
    }
    
    protected function handleSupport(): array
    {
        $info = \SWC_Chatbot_WooCommerce::get_store_info();
        $settings = ChatbotConfig::settings();
        
        $response = "I understand you'd like to speak with a human! \n\n";
        if (!empty($settings['whatsapp'])) {
            $whatsapp = preg_replace('/[^0-9]/', '', $settings['whatsapp']);
            $response .= " **WhatsApp:** [Chat Now](https://wa.me/{$whatsapp})\n";
        }
        if (!empty($info['email'])) {
            $response .= " **Email:** {$info['email']}\n";
        }
        if (!empty($settings['phone'])) {
            $response .= " **Phone:** {$settings['phone']}\n";
        }
        $response .= "\nOur team typically responds within 24 hours. In the meantime, I'm here to help!";
        
        return ['type' => 'text', 'message' => $response];
    }
    
    protected function handleFAQ(string $message): array
    {
        $faqs = \SWC_Chatbot_FAQ::get_all();
        if (!empty($faqs)) {
            $faqText = "Here are our frequently asked questions! \n\n";
            $count = 0;
            foreach ($faqs as $faq) {
                if ($count >= 5) break;
                $faqText .= "**Q: {$faq->question}**\n";
                $faqText .= "A: " . wp_trim_words($faq->answer, 20) . "\n\n";
                $count++;
            }
            $faqText .= "Have a specific question? Just ask me directly!";
            return ['type' => 'text', 'message' => $faqText];
        }
        return ['type' => 'text', 'message' => "I'm here to help!  Just ask me any question about our products, shipping, orders, or anything else!"];
    }
    
    protected function handleCategoryBrowse(string $message): array
    {
        $categories = \SWC_Chatbot_WooCommerce::get_categories();
        if (!empty($categories)) {
            return [
                'type' => 'categories',
                'message' => "Let me show you what we offer!  Here are our product categories - just click on any one to explore:",
                'categories' => $categories,
            ];
        }
        return ['type' => 'text', 'message' => "We don't have categories set up yet. Would you like to see all our products instead?"];
    }
    
    protected function handleProductSearch(string $message): array
    {
        // Extract search term
        $keywords = [];
        if (class_exists('SWC_Chatbot_Context_Retriever')) {
            $keywords = \SWC_Chatbot_Context_Retriever::extract_keywords($message);
        }
        
        $searchQuery = !empty($keywords) ? implode(' ', $keywords) : $message;
        $products = \SWC_Chatbot_WooCommerce::smart_search($searchQuery, 10);
        
        if (!empty($products)) {
            $keywordDisplay = !empty($keywords) ? implode(', ', array_slice($keywords, 0, 3)) : $searchQuery;
            return [
                'type' => 'products',
                'message' => "I found products matching **{$keywordDisplay}**! ",
                'products' => $products,
            ];
        }
        
        return [
            'type' => 'text',
            'message' => "I couldn't find any products matching your search. \n\nTry:\n• Checking the spelling\n• Using different keywords\n• Browse our **categories**\n• Check our **best sellers**"
        ];
    }
    
    protected function handleFallback(string $message): array
    {
        error_log('[SWC Debug] IntentRouter: Handling Fallback -> Routing to MessageRouter AI');
        // Route to AI via MessageRouter for complex/unknown intents
        if (class_exists('Quarksol\SmartChatbot\Services\MessageRouter')) {
            return MessageRouter::route(
                $message,
                $this->context,
                $this->agentId,
                $this->sessionId,
                $this->history,
                $this->messageIndex,
                $this->messageId
            );
        }
        
        // Ultimate fallback
        return [
            'type' => 'text',
            'message' => "I'm not quite sure what you mean, but I'd love to help! \n\nTry asking me:\n• \"Show me your best sellers\"\n• \"What's on sale?\"\n• \"Tell me about your store\"\n\nOr just type a product name to search!"
        ];
    }
    
    // ========== HELPER METHODS ==========
    
    protected function isOffTopic(string $message, string $messageLower): bool
    {
        // Check patterns
        if (IntentPatterns::matchesAny($message, IntentPatterns::OFF_TOPIC_PATTERNS)) {
            return true;
        }
        
        // Check celebrity names
        foreach (IntentPatterns::OFF_TOPIC_CELEBRITIES as $celeb) {
            if (strpos($messageLower, $celeb) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function isGreeting(string $messageLower): bool
    {
        // Only match if the message is primarily a greeting (short)
        if (strlen($messageLower) > 30) {
            return false;
        }
        return IntentPatterns::containsAny($messageLower, IntentPatterns::GREETING_KEYWORDS);
    }
    
    protected function isCategoryRequest(string $message, string $messageLower): bool
    {
        // Check for explicit category mentions
        $categoryKeywords = ['category', 'categories', 'browse', 'what do you sell', 'type of product', 'kind of product'];
        return IntentPatterns::containsAny($messageLower, $categoryKeywords);
    }
    
    protected function isProductRequest(string $message, string $messageLower): bool
    {
        // Skip if asking about appointments/bookings
        if (preg_match('/(?:book|appointment|schedule|consult|meeting|call)/i', $message)) {
            return false;
        }
        
        // Check product patterns
        if (IntentPatterns::matchesAny($message, IntentPatterns::PRODUCT_SEARCH_PATTERNS)) {
            return true;
        }
        
        // Check product keywords
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::PRODUCT_KEYWORDS)) {
            return true;
        }
        
        // Check product type keywords
        if (IntentPatterns::containsAny($messageLower, IntentPatterns::PRODUCT_TYPE_KEYWORDS)) {
            return true;
        }
        
        return false;
    }
}
