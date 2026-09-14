<?php
/**
 * Main Chatbot Class - Frontend & AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Models\AgentGroup;
use Quarksol\SmartChatbot\Services\OrchestratorService;
use Quarksol\SmartChatbot\Services\PageContext;

class SWC_Chatbot_Main {
    
    private $settings;

    /**
     * @var \Quarksol\SmartChatbot\Http\AjaxHandlers
     */
    private $ajax_handlers;

    /**
     * @var \Quarksol\SmartChatbot\View\ChatRenderer
     */
    private $renderer;

    public function __construct() {
        if (class_exists('\Quarksol\SmartChatbot\Config\SettingsManager')) {
            $this->settings = \Quarksol\SmartChatbot\Config\SettingsManager::getSettings();
        } else {
            $this->settings = get_option('swc_chatbot_settings', array());
        }
        
        if (empty($this->settings['enabled'])) {
            return;
        }


        // Initialize Ajax Handlers
        if (class_exists('\Quarksol\SmartChatbot\Http\\AjaxHandlers')) {
            $this->ajax_handlers = new \Quarksol\SmartChatbot\Http\AjaxHandlers();
            $this->ajax_handlers->registerHooks();
        }

        // Initialize Renderer - widget will be resolved at render time based on display rules
        if (class_exists('\Quarksol\SmartChatbot\View\\ChatRenderer')) {
            // Defer widget selection to wp_footer when we know the current URL
            add_action('wp_footer', array($this, 'render_chatbot_with_display_rules'), 5);
        } else {
            // Fallback if class not loaded yet
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_fallback'));
        }
    }
    
    /**
     * Render chatbot after evaluating display rules
     */
    public function render_chatbot_with_display_rules() {
        $matchingWidgets = [];
        
        if (class_exists('\Quarksol\SmartChatbot\Models\\ChatWidget')) {
            $activeWidgets = \Quarksol\SmartChatbot\Models\ChatWidget::findActive();
            
            // Build context for display rule evaluation
            $url = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '');
            
            // Add WooCommerce page type to URL for better matching
            $pageType = $this->get_woocommerce_page_type();
            if ($pageType) {
                $url .= '|wc:' . $pageType; // Append WooCommerce page type
            }
            
            $context = [
                'url' => $url,
                'user_id' => get_current_user_id(),
                'device' => $this->detect_device(),
                'page_type' => $pageType,
            ];
            
            // Collect ALL widgets that match display rules for current page
            foreach ($activeWidgets as $widget) {
                if ($widget->shouldDisplay($context)) {
                    $matchingWidgets[] = $widget;
                }
            }
            
            // Fallback: If no widget matched, add global widgets (no include_urls set)
            if (empty($matchingWidgets)) {
                foreach ($activeWidgets as $widget) {
                    $display = $widget->display ?? [];
                    if (empty($display['include_urls'])) {
                        $matchingWidgets[] = $widget;
                    }
                }
            }
        }
        
        // Create renderer with all matching widgets
        $primaryWidget = !empty($matchingWidgets) ? $matchingWidgets[0] : null;
        $renderer = new \Quarksol\SmartChatbot\View\ChatRenderer($this->settings, $primaryWidget, $matchingWidgets);
        $renderer->enqueueScripts();
        $renderer->render();
    }
    
    /**
     * Get WooCommerce page type
     */
    private function get_woocommerce_page_type() {
        if (!function_exists('is_shop')) {
            return null;
        }
        
        if (is_shop()) return 'shop';
        if (is_product()) return 'product';
        if (is_product_category()) return 'category';
        if (is_cart()) return 'cart';
        if (is_checkout()) return 'checkout';
        if (function_exists('is_account_page') && is_account_page()) return 'account';
        
        return null;
    }
    
    /**
     * Detect device type
     */
    private function detect_device() {
        $userAgent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            if (preg_match('/iPad|Tablet/i', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Fallback for enqueue scripts if renderer fails
     */
    public function enqueue_scripts_fallback() {
         wp_enqueue_style('smart-ai-chatbot-css', SWC_CHATBOT_URL . 'assets/css/chatbot.css', array(), SWC_CHATBOT_VERSION);
    }

    /**
     * Render chatbot markup (legacy compatibility).
     */
    public function render_chatbot(): void {
        if ($this->renderer instanceof \Quarksol\SmartChatbot\View\ChatRenderer) {
            $this->renderer->render();
            return;
        }

        if (class_exists('\Quarksol\SmartChatbot\View\\ChatRenderer')) {
            $renderer = new \Quarksol\SmartChatbot\View\ChatRenderer($this->settings);
            $renderer->render();
            return;
        }

        echo '<div id="agentflow-ai">';
        echo '<div class="swc-messages"></div>';
        echo '<textarea class="swc-input"></textarea>';
        echo '</div>';
    }
    
    public function handle_message() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        

        // Rate limiting check
        $settings = get_option('swc_chatbot_settings', []);
        if ((!empty($settings['enable_rate_limiting']) || !empty($settings['rate_limiting'])) && class_exists('\Quarksol\SmartChatbot\Services\RateLimiter')) {
            $identifier = \Quarksol\SmartChatbot\Services\RateLimiter::getIdentifier();
            if (!\Quarksol\SmartChatbot\Services\RateLimiter::check('chat', $identifier)) {
                $retry_after = \Quarksol\SmartChatbot\Services\RateLimiter::getRetryAfter('chat', $identifier);
                wp_send_json_error([
                    'message' => '⏳ You\'re sending messages too quickly. Please wait ' . $retry_after . ' seconds.',
                    'retry_after' => $retry_after
                ], 429);
            }
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = sanitize_text_field($_POST['context'] ?? '');
        $agent_id = intval($_POST['agent_id'] ?? 0);
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error('Empty message');
        }
        
        // Store agent_id for use in get_ai_response
        $this->current_agent_id = $agent_id;
        $this->current_session_id = $session_id;
        
        // Load error handler
        require_once SWC_CHATBOT_PATH . 'includes/class-chat-errors.php';
        
        // Wrap message processing with error handling
        $response = SWC_Chat_Errors::wrap(function() use ($message, $context) {
            return $this->process_message($message, $context);
        });
        
        // Send appropriate response based on type
        if (isset($response['type']) && $response['type'] === 'error') {
            wp_send_json_success($response); // Still success so frontend can handle gracefully
        } else {
            wp_send_json_success($response);
        }
    }
    
    private function process_message($message, $context = '') {
        $message_lower = strtolower($message);
        $store_name = get_bloginfo('name');
        
        // Context-based handling (order tracking flow)
        if ($context === 'awaiting_order_id') {
            return $this->handle_order_lookup($message);
        }
        
        if ($context === 'awaiting_email') {
            return $this->handle_email_verification($message, sanitize_text_field($_POST['order_id'] ?? ''));
        }
        
        // ======== @KNOWLEDGE COMMAND ========
        // Direct knowledge base search: @knowledge <query>
        if (preg_match('/^@knowledge\s+(.+)$/i', $message, $kbMatches) ||
            preg_match('/^search\s+knowledge\s+(.+)$/i', $message, $kbMatches) ||
            preg_match('/^kb:\s*(.+)$/i', $message, $kbMatches)) {
            
            $kbQuery = trim($kbMatches[1]);
            
            if (class_exists('\Quarksol\SmartChatbot\Knowledge\HybridSearcher') && 
                class_exists('\Quarksol\SmartChatbot\Knowledge\KnowledgeConfig') &&
                \Quarksol\SmartChatbot\Knowledge\KnowledgeConfig::isRAGEnabled()) {
                
                try {
                    $searcher = new \Quarksol\SmartChatbot\Knowledge\HybridSearcher();
                    $results = $searcher->search($kbQuery, 5);
                    
                    // Track search analytics
                    if (class_exists('\Quarksol\SmartChatbot\Knowledge\KnowledgeAnalytics')) {
                        \Quarksol\SmartChatbot\Knowledge\KnowledgeAnalytics::trackSearch($kbQuery, count($results));
                    }
                    
                    if (!empty($results)) {
                        $response = " **Knowledge Base Results** for \"*{$kbQuery}*\":\n\n";
                        
                        foreach ($results as $i => $doc) {
                            $source = $doc->getSourceName() ?: 'Unknown';
                            $content = $doc->getContent();
                            $score = round(($doc->score ?? 0) * 100);
                            
                            // Truncate content
                            if (strlen($content) > 300) {
                                $content = substr($content, 0, 300) . '...';
                            }
                            
                            $response .= "**" . ($i + 1) . ". {$source}** ({$score}% match)\n";
                            $response .= "{$content}\n\n---\n\n";
                        }
                        
                        return [
                            'type' => 'text',
                            'message' => $response,
                            'kb_results' => true,
                        ];
                    } else {
                        return [
                            'type' => 'text', 
                            'message' => " I couldn't find any knowledge base articles matching \"*{$kbQuery}*\". Try different keywords or ask me directly!",
                        ];
                    }
                } catch (\Throwable $e) {
                    if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                        \Quarksol\SmartChatbot\Services\Logger::debug('KB Search error', ['error' => $e->getMessage()]);
                    }
                }
            }
            
            return [
                'type' => 'text',
                'message' => " Knowledge base search is not enabled. Ask me anything and I'll try to help!",
            ];
        }
        
        // ======== PRODUCT SEARCH PATTERNS (CHECK FIRST) ========
        // These patterns indicate user wants products, NOT FAQ answers
        // But first check if this is asking about categories in general (NOT a product search)
        $is_asking_about_categories = preg_match('/(?:what|show|list|browse|see|view).*(?:categor|types?)/i', $message) ||
                                      preg_match('/categor(?:y|ies)\s+(?:do|you|have|are|list|available)/i', $message);
        
        // Skip product search if talking about appointments/bookings
        $is_appointment_related = preg_match('/(?:book|appointment|schedule|consult|meeting|call)/i', $message);
        
        $is_product_request = !$is_asking_about_categories && !$is_appointment_related && (
            preg_match('/(?:show|find|get|search|looking\s+for|need|want)\s+(?:me\s+)?(?:products?|items?)/i', $message) ||
            preg_match('/products?\s+(?:for|about|related|of|in|from)/i', $message) ||
            // Only match "category X" when X is a specific category name, not "do you have"
            preg_match('/(?:category|categories)\s+(?!do|you|have|are|list|available)(\w+)/i', $message) ||
            // Only catch product-looking requests, not appointment requests
            (preg_match('/(?:i\s+)?(?:need|want|looking\s+for|searching\s+for|find\s+me)\s+(?:a\s+)?(\w+)/i', $message) && 
             !preg_match('/(?:book|appointment|schedule|consult|meeting|yes|no|name|email)/i', $message))
        );
        
        // If this looks like a product request, skip FAQ and go straight to product search
        if ($is_product_request) {
            $product_result = $this->handle_product_search($message, $message_lower);
            if ($product_result) {
                return $product_result;
            }
            
            // Even if handle_product_search didn't find a matching pattern,
            // try smart_search with extracted keywords
            $keywords = SWC_Chatbot_Context_Retriever::extract_keywords($message);
            if (!empty($keywords)) {
                $search_query = implode(' ', $keywords);
                $products = SWC_Chatbot_WooCommerce::smart_search($search_query, 10);
                if (!empty($products)) {
                    $keyword_display = implode(', ', array_slice($keywords, 0, 3));
                    return array(
                        'type' => 'products',
                        'message' => "I found products matching **{$keyword_display}**! ",
                        'products' => $products
                    );
                }
                // No products found - give helpful message
                return array(
                    'type' => 'text',
                    'message' => "I couldn't find any products matching **{$search_query}** in our store. \n\nTry:\n• Checking the spelling\n• Using different keywords\n• Browse our **categories**\n• Check our **best sellers**"
                );
            }
        }
        
        // ======== FAQ CHECK ========
        // Check FAQs so custom answers take priority over built-in responses
        // BUT only if it's NOT a product request or a category exploration request
        
        // Skip FAQ for category exploration queries - let them reach the categories handler
        if (!$is_asking_about_categories) {
            $faq_match = SWC_Chatbot_FAQ::find_match($message);
            if ($faq_match) {
                return array(
                    'type' => 'text',
                    'message' => "Great question! \n\n**Q: {$faq_match->question}**\n\n{$faq_match->answer}"
                );
            }
        }
        
        // ======== STORE INFORMATION ========
        
        // About store / Tell me about store / Store info
        if ($this->contains_any($message_lower, array('about store', 'about your store', 'tell me about', 'store info', 'about this store', 'about shop', 'what is this', 'who are you'))) {
            $stats = SWC_Chatbot_WooCommerce::get_store_stats();
            $cats = implode(', ', array_slice($stats['categories'], 0, 5));
            
            $responses = array(
                "Great question! Let me tell you about **{$store_name}**! \n\nWe're an online store with a carefully curated collection of **{$stats['total_products']} products** across {$stats['categories_count']} categories including {$cats}.\n\nRight now, we have **{$stats['in_stock']}** items ready to ship, and **{$stats['on_sale']}** products are currently on sale! \n\nWould you like me to show you our best sellers or browse by category?",
                "Welcome to **{$store_name}**! \n\nWe're your go-to destination for quality products. Here's a quick overview:\n\n **{$stats['total_products']}** total products\n **{$stats['in_stock']}** in stock & ready to ship\n️ **{$stats['on_sale']}** currently on sale\n **{$stats['categories_count']}** categories to explore\n\nOur top categories include: {$cats}\n\nHow can I help you find what you're looking for today?"
            );
            
            return array(
                'type' => 'text',
                'message' => $responses[array_rand($responses)]
            );
        }
        
        // How many products / inventory / stock
        if ($this->contains_any($message_lower, array('how many product', 'inventory', 'stock level', 'total product', 'product count'))) {
            $stats = SWC_Chatbot_WooCommerce::get_store_stats();
            
            return array(
                'type' => 'text',
                'message' => "Let me check our inventory for you! \n\nWe currently have **{$stats['total_products']} products** in our catalog:\n\n **{$stats['in_stock']}** products are in stock and ready to ship\n️ **" . ($stats['total_products'] - $stats['in_stock']) . "** items are temporarily out of stock\n️ **{$stats['on_sale']}** products are currently on sale\n\nWant me to show you what's available or what's on sale?"
            );
        }
        
        // ======== BEST PRODUCTS / POPULAR ========
        
        if ($this->contains_any($message_lower, array('best product', 'best seller', 'bestseller', 'popular', 'most demanding', 'top selling', 'trending', 'hot product', 'what sells', 'top product', 'best item'))) {
            $products = SWC_Chatbot_WooCommerce::get_best_sellers(5);
            if (!empty($products)) {
                $responses = array(
                    "These are flying off our shelves!  Our customers absolutely love these products - they're our best sellers for a reason. Each one has been tried and trusted by our community:",
                    "Great choice asking about our top performers!  Here are the products our customers can't stop buying. These have the best reviews and highest sales:",
                    "Looking for what's popular? I've got you covered!  These are our most-loved products based on real customer purchases and feedback:"
                );
                return array(
                    'type' => 'products',
                    'message' => $responses[array_rand($responses)],
                    'products' => $products
                );
            }
            return array('type' => 'text', 'message' => "We're a new store and still building our sales data! Would you like to browse our available products instead?");
        }
        
        // ======== ON SALE / DISCOUNTS ========
        
        if ($this->contains_any($message_lower, array('sale', 'discount', 'offer', 'deal', 'on sale', 'special offer', 'promotion', 'saving'))) {
            $products = SWC_Chatbot_WooCommerce::get_on_sale(5);
            $stats = SWC_Chatbot_WooCommerce::get_stock_summary();
            if (!empty($products)) {
                $responses = array(
                    "You came at the perfect time! ️ We have **{$stats['on_sale']} products** on sale right now. These are limited-time offers, so don't miss out:",
                    "Who doesn't love a good deal?  Here are our current discounts - grab them before they're gone!",
                    "Great news!  We've got some amazing deals for you. These products are currently discounted:"
                );
                return array(
                    'type' => 'products',
                    'message' => $responses[array_rand($responses)],
                    'products' => $products
                );
            }
            return array('type' => 'text', 'message' => "We don't have any active sales right now, but I'll let you know when we do!  In the meantime, would you like to see our best sellers?");
        }
        
        // ======== NEW ARRIVALS ========
        
        if ($this->contains_any($message_lower, array('new arrival', 'new product', 'latest', 'just arrived', 'new in', 'newest', 'recent', 'just added', 'fresh'))) {
            $products = SWC_Chatbot_WooCommerce::get_new_arrivals(5);
            if (!empty($products)) {
                $responses = array(
                    "Fresh from our warehouse!  These just dropped and they're already getting attention. Be among the first to get them:",
                    "Hot off the press!  Here are our newest additions. These were just added to our collection:",
                    "You've got great timing!  Check out what's new - these products were recently added to our store:"
                );
                return array(
                    'type' => 'products',
                    'message' => $responses[array_rand($responses)],
                    'products' => $products
                );
            }
            return array('type' => 'text', 'message' => "We haven't added new products recently, but we're always updating our inventory! Want to see our best sellers instead?");
        }
        
        // ======== AVAILABLE / IN STOCK ========
        
        if ($this->contains_any($message_lower, array('available', 'in stock', 'what is available', 'what\'s available', 'ready to ship', 'can buy now'))) {
            $products = SWC_Chatbot_WooCommerce::get_available(5);
            $stats = SWC_Chatbot_WooCommerce::get_stock_summary();
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Good news!  We have **{$stats['in_stock']} products** in stock and ready to ship. Here are some options for you - all of these are available for immediate purchase:",
                    'products' => $products
                );
            }
        }
        
        // ======== CATEGORY-SPECIFIC SEARCH (EARLY CHECK) ========
        // This needs to run BEFORE "all products" to catch "show me products of category X"
        
        // Pattern: "products of category X" or "from category X"
        if (preg_match('/products?\s+(?:of|from|in)\s+(?:category|categorie?s?)\s+[:\"\']?\s*(\w[\w\s]+)/i', $message, $matches)) {
            $category_name = trim(preg_replace('/["\'\s]+$/', '', $matches[1]));
            if (!empty($category_name) && strlen($category_name) > 1) {
                $result = SWC_Chatbot_WooCommerce::get_products_by_category_name($category_name, 10);
                if (!empty($result['products']) && $result['category']) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products from our **{$result['category']['name']}** category! ️ We have {$result['category']['count']} items in this category:",
                        'products' => $result['products']
                    );
                }
            }
        }
        
        // Pattern: "category X" alone or at end
        if (preg_match('/(?:^|\s)(?:category|categorie?s?)\s+[:\"\']?\s*(\w[\w\s]+?)(?:\s*$|["\'])/i', $message, $matches)) {
            $category_name = trim(preg_replace('/["\'\s]+$/', '', $matches[1]));
            if (!empty($category_name) && strlen($category_name) > 1 && !preg_match('/^(of|from|in|list|all)$/i', $category_name)) {
                $result = SWC_Chatbot_WooCommerce::get_products_by_category_name($category_name, 10);
                if (!empty($result['products']) && $result['category']) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products from our **{$result['category']['name']}** category! ️ We have {$result['category']['count']} items in this category:",
                        'products' => $result['products']
                    );
                }
            }
        }
        
        // Pattern: "products for X" or "products related to X" (like "products for exercise")
        if (preg_match('/products?\s+(?:for|about|related\s+to)\s+(\w[\w\s]+)/i', $message, $matches)) {
            $search_term = trim($matches[1]);
            if (!empty($search_term) && strlen($search_term) > 1) {
                $products = SWC_Chatbot_WooCommerce::smart_search($search_term, 10);
                if (!empty($products)) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products related to **{$search_term}**! ",
                        'products' => $products
                    );
                }
            }
        }
        
        // Pattern: "product of X" or "show me product of X" (specific product name search)
        if (preg_match('/products?\s+(?:of|named?|called)\s+(\w[\w\s]+)/i', $message, $matches)) {
            $product_name = trim($matches[1]);
            // Skip if it looks like a category query
            if (!preg_match('/^categor/i', $product_name)) {
                if (!empty($product_name) && strlen($product_name) > 1) {
                    // First try title search
                    $products = SWC_Chatbot_WooCommerce::search_by_title($product_name, 10);
                    if (!empty($products)) {
                        return array(
                            'type' => 'products',
                            'message' => "Here's what I found for **{$product_name}**! ",
                            'products' => $products
                        );
                    }
                    // Fallback to smart search
                    $products = SWC_Chatbot_WooCommerce::smart_search($product_name, 10);
                    if (!empty($products)) {
                        return array(
                            'type' => 'products',
                            'message' => "Here's what I found for **{$product_name}**! ",
                            'products' => $products
                        );
                    }
                }
            }
        }
        
        // Pattern: "X products" at the end (like "buses products" or "electronics products")
        if (preg_match('/^(.+?)\s+products?\s*$/i', $message, $matches)) {
            $category_name = trim($matches[1]);
            // Skip common prefixes
            if (!preg_match('/^(show|all|your|best|new|on|top|the|some|any|featured|available|random|cheap|expensive|premium|me)/i', $category_name)) {
                // Try category first
                $result = SWC_Chatbot_WooCommerce::get_products_by_category_name($category_name, 10);
                if (!empty($result['products']) && $result['category']) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products from our **{$result['category']['name']}** category! ️ We have {$result['category']['count']} items in this category:",
                        'products' => $result['products']
                    );
                }
                // Try smart search
                $products = SWC_Chatbot_WooCommerce::smart_search($category_name, 10);
                if (!empty($products)) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products related to **{$category_name}**! ",
                        'products' => $products
                    );
                }
            }
        }
        
        // ======== ALL PRODUCTS ========
        
        if ($this->contains_any($message_lower, array('all product', 'show product', 'show everything', 'all item', 'list product', 'your product', 'what product', 'show me product', 'browse product'))) {
            $products = SWC_Chatbot_WooCommerce::get_all_products();
            $stats = SWC_Chatbot_WooCommerce::get_stock_summary();
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Absolutely! ️ We have **{$stats['total_products']} products** in our store. Here's a selection to get you started. Feel free to ask about specific categories or search for something specific!",
                    'products' => $products
                );
            }
        }
        
        // ======== CATEGORIES ========
        
        // How many categories question
        if (preg_match('/how\s+many\s+categor/i', $message)) {
            $categories = SWC_Chatbot_WooCommerce::get_categories();
            $count = count($categories);
            if ($count > 0) {
                $cat_names = array_column($categories, 'name');
                return array(
                    'type' => 'categories',
                    'message' => "We have **{$count} categories**!  Here they are:\n\n• " . implode("\n• ", $cat_names) . "\n\nClick any category to see products:",
                    'categories' => $categories
                );
            }
            return array('type' => 'text', 'message' => "We don't have any categories set up yet. Would you like to see all our products instead?");
        }
        
        if ($this->contains_any($message_lower, array('categor', 'browse', 'what do you sell', 'type of product', 'kind of product', 'what kind'))) {
            $categories = SWC_Chatbot_WooCommerce::get_categories();
            if (!empty($categories)) {
                return array(
                    'type' => 'categories',
                    'message' => "Let me show you what we offer!  Here are our product categories - just click on any one to explore:",
                    'categories' => $categories
                );
            }
        }
        
        // ======== FEATURED / RECOMMENDED ========
        
        if ($this->contains_any($message_lower, array('featured', 'recommend', 'suggestion', 'suggest', 'what should i buy', 'what do you recommend'))) {
            $products = SWC_Chatbot_WooCommerce::get_featured(5);
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Based on quality and customer satisfaction, I'd personally recommend these! ⭐ These are hand-picked products that we're really proud of:",
                    'products' => $products
                );
            }
            // Fallback to best sellers
            $products = SWC_Chatbot_WooCommerce::get_best_sellers(5);
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Let me recommend our most popular products!  These are customer favorites that get amazing reviews:",
                    'products' => $products
                );
            }
        }
        
        // ======== TOP RATED ========
        
        if ($this->contains_any($message_lower, array('top rated', 'best rated', 'highest rated', 'best review', '5 star', 'highly rated', 'good review'))) {
            $products = SWC_Chatbot_WooCommerce::get_top_rated(5);
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "These products have the best ratings from real customers!  Our shoppers have spoken - here are the highest-rated items:",
                    'products' => $products
                );
            }
            return array('type' => 'text', 'message' => "We're still collecting reviews! Would you like to see our best-selling products instead?");
        }
        
        // ======== PRODUCT COMPARISON ========
        
        // Compare by product IDs (legacy support)
        if (preg_match('/compare\s+(?:products?|ids?):?\s*([\d,\s]+)/i', $message, $matches)) {
            $product_ids = array_map('intval', preg_split('/[,\s]+/', $matches[1]));
            $product_ids = array_filter($product_ids);
            
            if (count($product_ids) >= 2) {
                $products = array();
                foreach ($product_ids as $id) {
                    $product = wc_get_product($id);
                    if ($product) {
                        $products[] = SWC_Chatbot_WooCommerce::format_product($product);
                    }
                }
                
                if (count($products) >= 2) {
                    return array(
                        'type' => 'comparison',
                        'message' => "Here's a side-by-side comparison of your selected products! ",
                        'products' => $products
                    );
                }
            }
            return array('type' => 'text', 'message' => "Please provide at least 2 valid product IDs to compare.");
        }
        
        // Compare by product names: "compare X and Y" or "compare X vs Y" or "compare X, Y"
        // Also handles common misspellings like "compair", "compar"
        if (preg_match('/compa[ri]+e?\s+(.+?)\s+(?:and|vs|versus|with|,)\s+(.+?)(?:\s+(?:and|vs|versus|with|,)\s+(.+?))?$/i', $message, $matches)) {
            $product_names = array(trim($matches[1]));
            if (!empty($matches[2])) $product_names[] = trim($matches[2]);
            if (!empty($matches[3])) $product_names[] = trim($matches[3]);
            
            // Remove common words that might interfere
            $product_names = array_map(function($name) {
                return preg_replace('/^(the|a|an)\s+/i', '', $name);
            }, $product_names);
            
            if (count($product_names) >= 2) {
                $products = array();
                $found_names = array();
                
                foreach ($product_names as $name) {
                    // Use smart search to find the product
                    $search_results = SWC_Chatbot_WooCommerce::smart_search($name, 1);
                    if (!empty($search_results)) {
                        $product = $search_results[0];
                        // Avoid duplicates
                        if (!in_array($product['id'], array_column($products, 'id'))) {
                            $products[] = $product;
                            $found_names[] = $product['name'];
                        }
                    }
                }
                
                if (count($products) >= 2) {
                    return array(
                        'type' => 'comparison',
                        'message' => "Here's a comparison of **" . implode('** vs **', $found_names) . "**! ",
                        'products' => $products
                    );
                } elseif (count($products) == 1) {
                    return array(
                        'type' => 'text',
                        'message' => "I found **{$products[0]['name']}**, but couldn't find the other product(s). Try more specific names or check spelling!"
                    );
                }
            }
            return array('type' => 'text', 'message' => "I couldn't find those products. Try: **compare [product 1] and [product 2]** with exact product names.");
        }
        
        if ($this->contains_any($message_lower, array('compare', 'comparison', 'versus', 'vs', 'difference between'))) {
            return array(
                'type' => 'text',
                'message' => "I can help you compare products! \n\n**How to compare:**\n• Say: **compare iPhone and Samsung**\n• Say: **compare Blue Shirt vs Red Shirt**\n• Say: **compare Product A, Product B, Product C**\n\nJust use the product names and I'll find them for you!"
            );
        }
        
        // ======== SIMILAR PRODUCTS ========
        
        if (preg_match('/similar\s+(?:products?\s+)?(?:to\s+)?(?:product\s+)?(?:id\s+)?(\d+)/i', $message, $matches)) {
            $product_id = intval($matches[1]);
            $product = wc_get_product($product_id);
            
            if ($product) {
                // Get related products (WooCommerce handles this based on categories/tags)
                $related_ids = wc_get_related_products($product_id, 5);
                $products = array();
                
                foreach ($related_ids as $id) {
                    $related = wc_get_product($id);
                    if ($related) {
                        $products[] = SWC_Chatbot_WooCommerce::format_product($related);
                    }
                }
                
                if (!empty($products)) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products similar to **{$product->get_name()}**!  These share similar categories or features:",
                        'products' => $products
                    );
                }
            }
            return array('type' => 'text', 'message' => "I couldn't find similar products for that item. Would you like me to show you products from a specific category instead?");
        }
        
        // ======== CART RECOVERY / ABANDONED CART ========
        
        if ($this->contains_any($message_lower, array('my cart', 'view cart', 'checkout', 'complete order', 'finish order', 'cart item'))) {
            $cart = WC()->cart;
            $cart_count = $cart->get_cart_contents_count();
            $cart_total = $cart->get_cart_total();
            
            if ($cart_count > 0) {
                $items = array();
                foreach ($cart->get_cart() as $item) {
                    $product = $item['data'];
                    $items[] = $product->get_name() . ' × ' . $item['quantity'];
                }
                
                return array(
                    'type' => 'cart',
                    'message' => " You have **{$cart_count} items** in your cart!\n\n**Items:** " . implode(', ', $items) . "\n**Total:** {$cart_total}\n\nReady to checkout? Click below!",
                    'cart' => array(
                        'count' => $cart_count,
                        'total' => $cart_total
                    )
                );
            }
            
            return array('type' => 'text', 'message' => "Your cart is empty!  Would you like me to show you some products? Just say 'show me products' or browse by category!");
        }
        
        // ======== WISHLIST ========
        
        if ($this->contains_any($message_lower, array('my wishlist', 'view wishlist', 'show wishlist', 'saved items', 'saved products'))) {
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $wishlist = get_user_meta($user_id, 'swc_wishlist', true);
                
                if (!empty($wishlist) && is_array($wishlist)) {
                    $products = array();
                    foreach ($wishlist as $product_id) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            $products[] = SWC_Chatbot_WooCommerce::format_product($product);
                        }
                    }
                    
                    if (!empty($products)) {
                        return array(
                            'type' => 'products',
                            'message' => "️ Here are your wishlisted products! These are items you've saved for later:",
                            'products' => $products
                        );
                    }
                }
            }
            return array('type' => 'text', 'message' => "Your wishlist is empty! ️ To add products, browse our items and click the heart button. Sign in to save your wishlist across devices!");
        }
        
        // ======== PRICE-BASED QUERIES ========
        
        // Budget / Cheapest
        if ($this->contains_any($message_lower, array('cheapest', 'budget', 'affordable', 'low price', 'inexpensive', 'cheap'))) {
            // Check for specific price
            if (preg_match('/under\s*\$?\s*(\d+)/i', $message, $matches)) {
                $max_price = intval($matches[1]);
                $products = SWC_Chatbot_WooCommerce::get_by_price(0, $max_price, 5);
                if (!empty($products)) {
                    return array(
                        'type' => 'products',
                        'message' => "Great budget choice!  Here are quality products under \${$max_price} - proving you don't have to break the bank for great products:",
                        'products' => $products
                    );
                }
                return array('type' => 'text', 'message' => "Hmm, I couldn't find products under \${$max_price}. Would you like me to show you our most affordable options overall?");
            }
            
            $products = SWC_Chatbot_WooCommerce::get_cheapest(5);
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Smart shopping!  Here are our most budget-friendly options - great quality at great prices:",
                    'products' => $products
                );
            }
        }
        
        // Premium / Expensive
        if ($this->contains_any($message_lower, array('expensive', 'premium', 'luxury', 'high end', 'top of the line', 'best quality'))) {
            $products = SWC_Chatbot_WooCommerce::get_expensive(5);
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Looking for the best?  Here are our premium products - these represent the top of our collection in terms of quality and features:",
                    'products' => $products
                );
            }
        }
        
        // Price range
        if (preg_match('/(?:between|from)\s*\$?\s*(\d+)\s*(?:to|and|-)\s*\$?\s*(\d+)/i', $message, $matches)) {
            $min_price = intval($matches[1]);
            $max_price = intval($matches[2]);
            $products = SWC_Chatbot_WooCommerce::get_by_price($min_price, $max_price, 5);
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Perfect!  Here are products in your \${$min_price} - \${$max_price} budget range:",
                    'products' => $products
                );
            }
            return array('type' => 'text', 'message' => "I couldn't find products in that price range. Would you like me to adjust the range or show you all available products?");
        }
        
        // ======== DELIVERY TO MY LOCATION (IP DETECTION) ========
        
        if ($this->contains_any($message_lower, array('deliver to my', 'ship to my', 'my location', 'my country', 'my area', 'do you deliver', 'can you deliver', 'ship here', 'deliver here', 'available in my', 'ship to me'))) {
            $delivery_info = SWC_Chatbot_WooCommerce::get_delivery_to_user_location();
            
            if (!$delivery_info['detected']) {
                return array(
                    'type' => 'text',
                    'message' => " " . $delivery_info['message']
                );
            }
            
            $location = $delivery_info['location'];
            $shipping = $delivery_info['shipping'];
            
            if ($shipping['ships']) {
                $methods_list = !empty($shipping['methods']) 
                    ? "\n\n**Available shipping methods:**\n• " . implode("\n• ", $shipping['methods'])
                    : "";
                    
                return array(
                    'type' => 'text',
                    'message' => " Great news! I detected you're in **{$location['country_name']}**.\n\n {$shipping['message']}{$methods_list}\n\nFeel free to browse our products and add them to your cart!"
                );
            } else {
                return array(
                    'type' => 'text',
                    'message' => " I detected you're in **{$location['country_name']}**.\n\n {$shipping['message']}\n\nWe're always expanding our shipping zones, so please check back soon or contact us for special arrangements!"
                );
            }
        }
        
        // ======== CHECK SPECIFIC COUNTRY SHIPPING ========
        
        if (preg_match('/(?:deliver|ship|shipping)\s+(?:to|in)\s+([a-zA-Z\s]+)/i', $message, $matches)) {
            $country_query = trim($matches[1]);
            
            // Skip if it's generic words
            if (!in_array(strtolower($country_query), array('my', 'my location', 'my country', 'here', 'me', 'us'))) {
                // Try to find country code
                $countries = WC()->countries->countries;
                $country_code = null;
                
                foreach ($countries as $code => $name) {
                    if (stripos($name, $country_query) !== false || strtolower($code) === strtolower($country_query)) {
                        $country_code = $code;
                        break;
                    }
                }
                
                if ($country_code) {
                    $shipping = SWC_Chatbot_WooCommerce::check_shipping_to_country($country_code);
                    
                    if ($shipping['ships']) {
                        $methods_list = !empty($shipping['methods']) 
                            ? "\n\n**Available methods:**\n• " . implode("\n• ", $shipping['methods'])
                            : "";
                            
                        return array(
                            'type' => 'text',
                            'message' => " {$shipping['message']}{$methods_list}"
                        );
                    } else {
                        return array(
                            'type' => 'text',
                            'message' => " {$shipping['message']}\n\nPlease contact us for special shipping arrangements!"
                        );
                    }
                }
            }
        }
        
        // ======== SHIPPING INFO ========
        
        if ($this->contains_any($message_lower, array('shipping', 'delivery', 'ship', 'deliver', 'how long', 'shipping cost', 'delivery time', 'free shipping'))) {
            $shipping = SWC_Chatbot_WooCommerce::get_shipping_info();
            if (!empty($shipping)) {
                $methods_text = "";
                foreach ($shipping as $method) {
                    $cost = is_numeric($method['cost']) ? '$' . $method['cost'] : $method['cost'];
                    $methods_text .= "• **{$method['title']}**: {$cost}\n";
                }
                return array(
                    'type' => 'text',
                    'message' => "Great question about shipping!  Here are our delivery options:\n\n{$methods_text}\nShipping times vary based on your location. Need more details? Just ask!"
                );
            }
            return array('type' => 'text', 'message' => "We offer shipping to most locations!  Shipping costs are calculated at checkout based on your address. Add items to your cart to see exact shipping rates.");
        }
        
        // ======== PAYMENT METHODS ========
        
        if ($this->contains_any($message_lower, array('payment', 'pay', 'credit card', 'paypal', 'how to pay', 'payment method', 'payment option', 'accept'))) {
            $payments = SWC_Chatbot_WooCommerce::get_payment_methods();
            if (!empty($payments)) {
                $methods_text = "";
                foreach ($payments as $method) {
                    $methods_text .= "• **{$method['title']}**\n";
                }
                return array(
                    'type' => 'text',
                    'message' => "We accept multiple payment methods for your convenience! \n\n{$methods_text}\nAll transactions are secure and encrypted. Ready to shop?"
                );
            }
            return array('type' => 'text', 'message' => "We accept various secure payment methods!  You'll see all available options at checkout. Your payment information is always protected.");
        }
        
        // ======== COUPONS / PROMO CODES ========
        
        if ($this->contains_any($message_lower, array('coupon', 'promo', 'discount code', 'voucher', 'code', 'promotion'))) {
            $coupons = SWC_Chatbot_WooCommerce::get_active_coupons();
            // Filter out private/restricted coupons (those with email restrictions)
            if (!empty($coupons)) {
                $coupons = array_filter($coupons, function($coupon) {
                    $wc_coupon = new \WC_Coupon($coupon['code'] ?? '');
                    return empty($wc_coupon->get_email_restrictions());
                });
                $coupons = array_values($coupons);
            }
            if (!empty($coupons)) {
                $coupon_text = "";
                foreach ($coupons as $coupon) {
                    $amount = $coupon['type'] === 'percent' ? $coupon['amount'] . '%' : '$' . $coupon['amount'];
                    $coupon_text .= "️ **{$coupon['code']}** - {$amount} off";
                    if (!empty($coupon['description'])) {
                        $coupon_text .= " ({$coupon['description']})";
                    }
                    $coupon_text .= "\n";
                }
                return array(
                    'type' => 'text',
                    'message' => "Great news!  We have active promotions for you:\n\n{$coupon_text}\nEnter the code at checkout to apply your discount!"
                );
            }
            return array('type' => 'text', 'message' => "We don't have any public coupon codes right now, but keep an eye out for special promotions!  Would you like to see our current sale items instead?");
        }
        
        // ======== REFUND / RETURN POLICY ========
        
        if ($this->contains_any($message_lower, array('refund', 'return', 'money back', 'exchange', 'return policy', 'refund policy'))) {
            $policies = SWC_Chatbot_WooCommerce::get_store_policies();
            if (isset($policies['refund'])) {
                return array(
                    'type' => 'text',
                    'message' => "Here's our **{$policies['refund']['title']}**: \n\n{$policies['refund']['content']}...\n\n[Read full policy]({$policies['refund']['url']})"
                );
            }
            return array('type' => 'text', 'message' => "We want you to be completely satisfied!  If you're not happy with your purchase, please contact us and we'll help resolve any issues. For detailed return information, check our store policies page.");
        }
        
        // ======== TERMS / PRIVACY POLICY ========
        
        if ($this->contains_any($message_lower, array('privacy', 'terms', 'policy', 'policies', 'terms and condition', 'legal'))) {
            $policies = SWC_Chatbot_WooCommerce::get_store_policies();
            $policy_text = "Here are our store policies: \n\n";
            $has_policies = false;
            
            if (isset($policies['privacy'])) {
                $policy_text .= "• [Privacy Policy]({$policies['privacy']['url']})\n";
                $has_policies = true;
            }
            if (isset($policies['terms'])) {
                $policy_text .= "• [Terms & Conditions]({$policies['terms']['url']})\n";
                $has_policies = true;
            }
            if (isset($policies['refund'])) {
                $policy_text .= "• [{$policies['refund']['title']}]({$policies['refund']['url']})\n";
                $has_policies = true;
            }
            
            if ($has_policies) {
                return array('type' => 'text', 'message' => $policy_text . "\nClick any link above for full details!");
            }
        }
        
        // ======== ALL FAQS ========
        
        // Show all FAQs when explicitly asked
        if ($this->contains_any($message_lower, array('faq', 'all faq', 'frequently asked', 'all question', 'common question', 'q&a', 'show faq', 'list faq'))) {
            $faqs = SWC_Chatbot_FAQ::get_all();
            if (!empty($faqs)) {
                $faq_text = "Here are our frequently asked questions! \n\n";
                $count = 0;
                foreach ($faqs as $faq) {
                    if ($count >= 5) break; // Limit to 5 FAQs
                    $faq_text .= "**Q: {$faq->question}**\n";
                    $faq_text .= "A: " . wp_trim_words($faq->answer, 20) . "\n\n";
                    $count++;
                }
                $faq_text .= "Have a specific question? Just ask me directly!";
                return array('type' => 'text', 'message' => $faq_text);
            }
            return array('type' => 'text', 'message' => "I'm here to help!  Just ask me any question about our products, shipping, orders, or anything else!");
        }
        
        // Search FAQs by keyword when user asks a question with specific keywords
        if (preg_match('/^(what|how|where|when|can|do|is|does|why|will|should)\s+.+\??\s*$/i', $message)) {
            // This is a question - search FAQs first
            $faq_results = SWC_Chatbot_FAQ::search($message);
            if (!empty($faq_results)) {
                $faq = $faq_results[0]; // Get best match
                return array(
                    'type' => 'text',
                    'message' => "Great question! \n\n**Q: {$faq->question}**\n\n{$faq->answer}\n\nDoes this answer your question? Feel free to ask more!"
                );
            }
        }
        
        // ======== CONTACT / SUPPORT ========
        // Only match if it's clearly about contacting the store, not product searches
        $is_contact_request = preg_match('/\b(contact\s+(?:us|you|store|shop|support)|your\s+(?:email|phone)|how\s+(?:to\s+)?(?:reach|contact)|customer\s+service|get\s+in\s+touch|support\s+(?:team|email|number))\b/i', $message);
        
        if ($is_contact_request) {
            $info = SWC_Chatbot_WooCommerce::get_store_info();
            return array(
                'type' => 'text',
                'message' => "We'd love to hear from you! \n\n**Email:** {$info['email']}\n**Website:** {$info['url']}\n\nYou can also reach us through our contact form. Is there something specific I can help you with right now?"
            );
        }
        
        // ======== LIVE AGENT / HUMAN SUPPORT ========
        
        if ($this->contains_any($message_lower, array('talk to human', 'real person', 'agent', 'live chat', 'speak to someone', 'human support', 'not a bot', 'representative', 'operator'))) {
            $info = SWC_Chatbot_WooCommerce::get_store_info();
            $settings = $this->settings;
            
            $response = "I understand you'd like to speak with a human! \n\n";
            
            // WhatsApp option if configured
            if (!empty($settings['whatsapp'])) {
                $whatsapp = preg_replace('/[^0-9]/', '', $settings['whatsapp']);
                $response .= " **WhatsApp:** [Chat Now](https://wa.me/{$whatsapp})\n";
            }
            
            // Email option
            if (!empty($info['email'])) {
                $response .= " **Email:** {$info['email']}\n";
            }
            
            // Phone option
            if (!empty($settings['phone'])) {
                $response .= " **Phone:** {$settings['phone']}\n";
            }
            
            $response .= "\nOur team typically responds within 24 hours. In the meantime, I'm here to help with any questions!";
            
            return array(
                'type' => 'text',
                'message' => $response
            );
        }
        
        // ======== HELP / WHAT CAN YOU DO ========
        
        if ($this->contains_any($message_lower, array('what can you do', 'help me', 'how do you work', 'your capabilities', 'what do you do'))) {
            return array(
                'type' => 'text',
                'message' => "I'm your AI shopping assistant!  Here's what I can help you with:\n\n" .
                    "️ **Product Discovery**\n• Browse products, categories, best sellers\n• Search by name, price range, ratings\n• Find similar products\n\n" .
                    " **Order Help**\n• Track your order status\n• View order history\n• Check cart contents\n\n" .
                    " **Compare & Decide**\n• Compare products side-by-side\n• Get recommendations\n• Check stock availability\n\n" .
                    " **Store Info**\n• Shipping options & delivery\n• Payment methods\n• Return policy & FAQs\n\n" .
                    "**Just type your question or use the quick buttons below!**"
            );
        }
        
        // ======== CATEGORY-SPECIFIC SEARCH ========
        // This checks if user is asking for products in a specific category
        
        // Pattern 1: "products of X" or "products in X" or "products from X"
        // [AI Migration] Legacy Regex Search disabled. Request falls through to AI Agent.
        if (false && preg_match('/products?\s+(?:of|in|from|for)\s+(.+?)$/i', $message, $matches)) {
            $category_name = trim($matches[1]);
            $result = SWC_Chatbot_WooCommerce::get_products_by_category_name($category_name, 5);
            if (!empty($result['products']) && $result['category']) {
                return array(
                    'type' => 'products',
                    'message' => "Here are products from our **{$result['category']['name']}** category! ️ We have {$result['category']['count']} items in this category:",
                    'products' => $result['products']
                );
            }
        }
        
        // Pattern 2: "show me X" or "X products" or "X items"
        $category_patterns = array(
            '/(?:show|get|find|see|view|display)\s+(?:me\s+)?(?:some\s+)?(?:the\s+)?(.+?)(?:\s+products?|\s+items?|\s+stuff)?$/i',
            '/^(.+?)\s+(?:products?|items?|category)$/i',
            '/(?:want|need|looking for)\s+(?:to see\s+)?(?:some\s+)?(.+?)$/i'
        );
        
        foreach ($category_patterns as $pattern) {
            // [AI Migration] Legacy Regex Search disabled. Request falls through to AI Agent.
            if (false && preg_match($pattern, $message, $matches)) {
                $potential_category = trim($matches[1]);
                
                // Clean up common words
                $potential_category = preg_replace('/^(the|some|a|an|any)\s+/i', '', $potential_category);
                $potential_category = preg_replace('/\s+(please|now|today)$/i', '', $potential_category);
                
                // Skip if it's a common command word
                $skip_words = array('best', 'new', 'sale', 'cheap', 'expensive', 'featured', 'all', 'your', 'random', 'available', 'top', 'popular', 'to see', 'products', 'help', 'faq', 'shipping', 'payment', 'order', 'cart', 'track', 'categories', 'category', 'browse', 'compare', 'wishlist', 'delivery', 'how many', 'what');
                $is_skip = false;
                foreach ($skip_words as $skip) {
                    if (strtolower($potential_category) === $skip || stripos($potential_category, $skip) === 0) {
                        $is_skip = true;
                        break;
                    }
                }
                
                if (!$is_skip && strlen($potential_category) > 1) {
                    $result = SWC_Chatbot_WooCommerce::get_products_by_category_name($potential_category, 5);
                    if (!empty($result['products']) && $result['category']) {
                        return array(
                            'type' => 'products',
                            'message' => "Here are products from our **{$result['category']['name']}** category! ️ We have {$result['category']['count']} items in this category:",
                            'products' => $result['products']
                        );
                    }
                }
            }
        }
        
        // ======== RANDOM / SURPRISE ========
        
        if ($this->contains_any($message_lower, array('random', 'surprise', 'anything', 'whatever', 'show me some', 'something'))) {
            $products = SWC_Chatbot_WooCommerce::get_random(5);
            if (!empty($products)) {
                $responses = array(
                    "Here's a surprise selection just for you!  Sometimes the best finds are unexpected:",
                    "Let me pick some random gems!  Here are some products you might not have considered:",
                    "Feeling adventurous?  Check out these picks - who knows, you might find something you love!"
                );
                return array(
                    'type' => 'products',
                    'message' => $responses[array_rand($responses)],
                    'products' => $products
                );
            }
        }
        
        // ======== ORDER TRACKING ========
        
        if ($this->contains_any($message_lower, array('order', 'track', 'tracking', 'where is my', 'delivery', 'shipment', 'shipping status'))) {
            if (is_user_logged_in()) {
                return $this->get_user_orders();
            }
            return array(
                'type' => 'text',
                'message' => "I'd be happy to help you track your order! \n\nPlease enter your **order number** (you can find this in your confirmation email):",
                'context' => 'awaiting_order_id'
            );
        }
        
        // ======== CART / CHECKOUT ========
        
        if ($this->contains_any($message_lower, array('cart', 'checkout', 'basket', 'my items', 'what did i add'))) {
            $cart_info = SWC_Chatbot_WooCommerce::get_cart_info();
            if ($cart_info['count'] > 0) {
                return array(
                    'type' => 'cart',
                    'message' => "Here's your cart summary! \n\nYou have **{$cart_info['count']} item(s)** in your cart with a total of **{$cart_info['total']}**.\n\nReady to complete your purchase?",
                    'cart' => $cart_info
                );
            }
            return array(
                'type' => 'text',
                'message' => "Your cart is empty!  Would you like me to help you find some products? Just tell me what you're looking for!"
            );
        }
        
        // ======== CONTACT / STORE INFO (Route to AI) ========
        // Questions about contact, address, phone, email, location should go to AI
        // NOT product search - these are handled by the AI fallback with RAG context
        $non_product_queries = array(
            'contact', 'phone', 'email', 'address', 'location', 'hours', 'open', 'close',
            'support', 'help desk', 'customer service', 'speak to', 'talk to',
            'policy', 'return', 'refund', 'exchange', 'warranty',
            'shipping cost', 'delivery time', 'how long', 'payment method', 'pay with'
        );
        
        // For non-product queries, skip directly to AI fallback
        $skip_to_ai_fallback = $this->contains_any($message_lower, $non_product_queries);
        
        // ======== SEARCH INTENT ========
        
        // Only trigger search if it looks like a product search AND doesn't contain non-product keywords
        if (!$skip_to_ai_fallback) {
        // [AI Migration] Legacy Regex Search disabled. Request falls through to AI Agent.
        if (false && $this->contains_any($message_lower, array('search', 'find', 'looking for', 'show me', 'want', 'need', 'buy', 'get me', 'where can i find'))) {
            // Skip if this is asking about store info or categories, not product search
            $is_store_query = $this->contains_any($message_lower, array(
                'your contact', 'your address', 'your phone', 'your email', 'your location',
                'store hours', 'store info', 'about you', 'about your store', 'who are you',
                'categor', 'browse' // These should go to the categories handler, not search
            ));
            
            if (!$is_store_query) {
                $keyword = $this->extract_search_term($message);
                if ($keyword) {
                    return $this->search_products($keyword);
                }
                return array(
                    'type' => 'text',
                    'message' => "I'd love to help you find something!  What are you looking for? Just type the product name or describe what you need.",
                    'context' => 'awaiting_search'
                );
            }
        }
        
        // ======== FAQ CHECK ========
        
        $faq_match = SWC_Chatbot_FAQ::find_match($message);
        if ($faq_match) {
            return array(
                'type' => 'text',
                'message' => $faq_match->answer
            );
        }
        
        // ======== GREETINGS - NOW HANDLED BY AI WITH AGENT SETTINGS ========
        // Instead of hardcoded greetings, let the AI respond using configured skills
        if ($this->contains_any($message_lower, array('hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening'))) {
            // Let AI handle greetings so it uses active skills (appointment booker, etc.)
            $ai_response = $this->get_ai_response($message);
            if ($ai_response) {
                return array(
                    'type' => 'text',
                    'message' => $ai_response
                );
            }
            // Fallback if AI fails
            return array(
                'type' => 'text',
                'message' => "Hi!  Welcome to **{$store_name}**! How can I help you today?"
            );
        }
        
        // ======== HELP ========
        
        if ($this->contains_any($message_lower, array('help', 'what can you do', 'how does this work', 'commands'))) {
            return array(
                'type' => 'text',
                'message' => "I'm your AI shopping assistant!  Here's how I can help:\n\n**️ Product Discovery**\n• Ask about our *best sellers*, *new arrivals*, or *deals*\n• Search for specific products by name\n• Browse by category or price range\n\n** Orders & Support**\n• Track your order status\n• Get answers to common questions\n• Find out about our store\n\n** Try asking:**\n• \"What are your most popular products?\"\n• \"Show me products under \$50\"\n• \"What's on sale right now?\"\n• \"Tell me about this store\"\n\nJust type naturally - I'll understand!"
            );
        }
        
        // ======== THANK YOU ========
        
        if ($this->contains_any($message_lower, array('thank', 'thanks', 'appreciate', 'thx'))) {
            $responses = array(
                "You're welcome!  Happy to help! Is there anything else you'd like to know?",
                "My pleasure!  Let me know if you need anything else!",
                "Anytime!  I'm here if you have more questions!"
            );
            return array(
                'type' => 'text',
                'message' => $responses[array_rand($responses)]
            );
        }
        
        // ======== DEFAULT - SMART SEARCH ========
        
        // Only run smart search if this looks like a product query
        // This prevents conversational messages from being treated as product searches
        // [AI Migration] Legacy Smart Search disabled. Request falls through to AI Agent.
        if (false && $this->looks_like_product_query($message_lower)) {
            // Extract meaningful keywords from message (remove stopwords)
            $keywords = SWC_Chatbot_Context_Retriever::extract_keywords($message);
            $search_query = !empty($keywords) ? implode(' ', $keywords) : $message;
            
            // Try product search with extracted keywords
            $products = SWC_Chatbot_WooCommerce::smart_search($search_query, 10);
            if (!empty($products)) {
                $keyword_display = implode(', ', array_slice($keywords, 0, 3));
                return array(
                    'type' => 'products',
                    'message' => "I found products related to **{$keyword_display}**!  Here's what we have:",
                    'products' => $products
                );
            }
            
            // If no products found for a product query, give helpful response
            if (!empty($keywords)) {
                return array(
                    'type' => 'text',
                    'message' => "I couldn't find any products matching **" . implode(' ', $keywords) . "** in our store. \n\nHere's what you can try:\n• Browse our **categories** to see what we have\n• Check out our **best sellers**\n• Look at what's **on sale**\n\nOr describe what you're looking for differently!"
                );
            }
        }
        
        // ======== OFF-TOPIC DETECTION ========
        // Check if this is clearly an off-topic question before AI fallback
        if ($this->is_off_topic_query($message)) {
            return $this->get_off_topic_response();
        }
        
        } // End of !$skip_to_ai_fallback block
        
        // ======== AI PROVIDER FALLBACK ========
        // If AI is enabled and no intent matched, forward to AI provider
        $settings = get_option('swc_chatbot_settings', array());
        $ai_enabled = !empty($settings['ai_enabled']);
        $ai_provider = $settings['ai_provider'] ?? 'openai';
        $ai_api_key = $settings['ai_api_key'] ?? '';
        
        // Local providers don't require API key
        $local_providers = array('ollama', 'lmstudio');
        $has_key_or_local = !empty($ai_api_key) || in_array($ai_provider, $local_providers);
        
        // Debug logging
        if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::debug('AI response check', [
                'enabled' => $ai_enabled,
                'provider' => $ai_provider,
            ]);
        }
        
        if ($ai_enabled && $has_key_or_local) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('Attempting AI response', ['message_length' => strlen($message)]);
            }
            $ai_response = $this->get_ai_response($message);
            if ($ai_response !== null) {
                if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                    \Quarksol\SmartChatbot\Services\Logger::debug('Got AI response');
                }
                
                // [Phase 1: UI Bridge] Check if response is a structured UI signal
                if (is_array($ai_response)) {
                    return $ai_response;
                }
                
                return array(
                    'type' => 'text',
                    'message' => ' ' . $ai_response
                );
            } else {
                if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                    \Quarksol\SmartChatbot\Services\Logger::warning('AI response was null (error occurred)');
                }
            }
        }
        
        // Fallback - friendly response (when AI is disabled or failed)
        $fallbacks = array(
            "I'm not quite sure what you mean, but I'd love to help! \n\nTry asking me:\n• \"Show me your best sellers\"\n• \"What's on sale?\"\n• \"Tell me about your store\"\n\nOr just type a product name to search!",
            "Hmm, I didn't catch that!  But no worries - here are some things I can help with:\n\n• Finding products (just type what you're looking for)\n• Showing you deals and new arrivals\n• Tracking your orders\n• Answering questions about our store\n\nWhat would you like to explore?",
            "I want to make sure I help you correctly! \n\nCould you try rephrasing that? You can ask me about products, sales, orders, or just tell me what you're shopping for today!"
        );
        
        return array(
            'type' => 'text',
            'message' => $fallbacks[array_rand($fallbacks)]
        );
    }

    /**
     * Get AI response using configured provider with RAG (Retrieval Augmented Generation)
     * 
     * NOW USES THE NEW 40+ PROVIDER SYSTEM via ProviderBridge!
     * 
     * @param string $message The user's message
     * @return string|null The AI response or null on failure
     */
    private function get_ai_response($message) {
        try {
            // ===== Load Bootstrap if Not Already Loaded =====
            $bootstrap_path = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($bootstrap_path) && !class_exists('Quarksol\SmartChatbot\Bridge\ProviderBridge')) {
                require_once $bootstrap_path;
            }
            
            // ===== Check if New Provider System is Available =====
            if (!class_exists('Quarksol\SmartChatbot\Bridge\ProviderBridge')) {
                if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                    \Quarksol\SmartChatbot\Services\Logger::info('ProviderBridge not available, falling back to legacy');
                }
                return $this->get_ai_response_legacy($message);
            }
            
            $settings = get_option('swc_chatbot_settings', array());
            
            // Debug logging (only in debug mode)
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('Using NEW provider system', ['provider' => $settings['ai_provider'] ?? 'NOT SET']);
            }
            
            // ===== Multi-Agent Orchestration =====
            // Check if current agent belongs to a group
            if (!empty($this->current_agent_id) && class_exists('\Quarksol\SmartChatbot\Models\AgentGroup')) {
                $group = \Quarksol\SmartChatbot\Models\AgentGroup::findByMember($this->current_agent_id);
                
                // Orchestrate if group is active AND has workflow steps or a multi-agent mode
                $shouldOrchestrate = $group && $group->isActive && (
                    $group->hasWorkflowSteps() ||
                    in_array($group->orchestrationMode, ['router', 'parallel', 'sequential', 'handoff'])
                );

                if ($shouldOrchestrate) {
                    if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                        \Quarksol\SmartChatbot\Services\Logger::debug('Orchestrating via group', [
                            'group_id' => $group->id,
                            'name' => $group->name,
                            'has_workflow_steps' => $group->hasWorkflowSteps(),
                        ]);
                    }
                    
                    if (class_exists('\Quarksol\SmartChatbot\Services\OrchestratorService')) {
                        try {
                            // Reconstruct PageContext (simplified)
                            $contextObj = class_exists('\Quarksol\SmartChatbot\Services\PageContext')
                                ? new \Quarksol\SmartChatbot\Services\PageContext()
                                : null;
                            
                            $response = \Quarksol\SmartChatbot\Services\OrchestratorService::handleRequest($contextObj, $message, $group);
                            
                            if ($response) {
                                return $response->content;
                            }
                        } catch (\Throwable $e) {
                             if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                                 \Quarksol\SmartChatbot\Services\Logger::error('Orchestration error', ['error' => $e->getMessage()]);
                             }
                             // Fall back to standard single-agent response
                        }
                    }
                }
            }

            
            // ===== PROGRESSIVE KNOWLEDGE LOADING =====
            // Knowledge content is NO LONGER auto-injected into the system prompt.
            // Instead, the AI sees a compact catalog of knowledge titles in its system prompt
            // and uses tools (search_knowledge, read_knowledge_item) to fetch content on-demand.
            // This saves ~5,000-8,000 tokens per message.
            //
            // The following tools handle knowledge retrieval:
            //   - search_knowledge(query) — keyword search
            //   - search_knowledge_rag(query) — semantic/vector search  
            //   - read_knowledge_item(slug) — read specific item
            //   - list_knowledge_sources() — discover sources
            //
            // Always-on knowledge items (always_on=true) are still injected via
            // KnowledgeManager::getAlwaysOnContent() in NeuronAgent::buildConfiguredPrompt()
            
            // Record search gaps for content gap analysis (doesn't inject anything)
            try {
                if (class_exists('\Quarksol\SmartChatbot\Knowledge\ConversationLearner')) {
                    // ConversationLearner tracks unanswered queries for knowledge improvement
                }
            } catch (Throwable $e) {
                // Silent — analytics should never break chat
            }
            
            // ===== Build Enhanced System Prompt with Agent Settings =====
            $store_name = get_bloginfo('name');
            
            // Try to load specific agent if agent_id is set
            $agent_system_prompt = null;
            $agent_config = null;
            if (!empty($this->current_agent_id) && class_exists('Quarksol\SmartChatbot\Models\ChatAgent')) {
                $agent = \Quarksol\SmartChatbot\Models\ChatAgent::find($this->current_agent_id);
                if ($agent) {
                    $agent_system_prompt = $agent->system_prompt;
                    $agent_config = $agent->config;
                    if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                        \Quarksol\SmartChatbot\Services\Logger::debug('Using agent-specific config', ['agent_id' => $this->current_agent_id]);
                    }
                }
            }
            
            // Load agent settings (fallback if no specific agent)
            $agent_tools = get_option('swc_chatbot_tools', []);
            $agent_prompts = get_option('swc_chatbot_prompts', []);
            $agent_skills = get_option('swc_active_skills', ['shopping_assistant']);
            
            // Build system prompt - use agent-specific if available
            if (!empty($agent_system_prompt)) {
                // Use agent's custom system prompt
                $system_prompt = $agent_system_prompt . "\n\n";
            } else {
                // Build from global settings
                $system_prompt = "# AI Agent for {$store_name}\n\n";
            }
            // Add custom prompts from admin settings
            if (!empty($agent_prompts)) {
                $system_prompt .= "## Core Behavior\n";
                foreach ($agent_prompts as $key => $prompt) {
                    if (!empty($prompt['content'])) {
                        $content = str_replace(
                            ['{{store_name}}', '{{user_name}}', '{{current_date}}'],
                            [$store_name, is_user_logged_in() ? wp_get_current_user()->display_name : 'Guest', date('F j, Y')],
                            $prompt['content']
                        );
                        $system_prompt .= $content . "\n\n";
                    }
                }
            } else {
                $system_prompt .= "You are a helpful shopping assistant for {$store_name}. Be friendly and helpful.\n\n";
            }
            
            // Add enabled tools information
            $enabled_tools = [];
            foreach ($agent_tools as $slug => $tool_config) {
                if (!empty($tool_config['enabled'])) {
                    $enabled_tools[] = $slug;
                }
            }
            
            if (!empty($enabled_tools)) {
                $system_prompt .= "## Your Capabilities\n";
                $system_prompt .= "You have access to the following capabilities:\n";
                
                // Basic tool descriptions (legacy tools)
                $tool_descriptions = [
                    'woocommerce' => ' WooCommerce: Search products, show prices, help with cart and orders',
                    'sql' => '️ Database: Save and retrieve custom data (leads, feedback, appointments)',
                    'calendar' => ' Calendar: Book appointments and check availability',
                    'email' => ' Email: Send notifications and confirmations',
                    'faq' => ' FAQ: Answer frequently asked questions',
                    'files' => ' Files: Read and write files in sandboxed directory'
                ];
                
                foreach ($enabled_tools as $slug) {
                    if (isset($tool_descriptions[$slug])) {
                        $system_prompt .= "- " . $tool_descriptions[$slug] . "\n";
                    }
                }
                
                // Load advanced toolkit tools if available
                $toolkit_path = SWC_CHATBOT_PATH . 'toolkits/loader.php';
                if (file_exists($toolkit_path) && function_exists('get_toolkits_status')) {
                    $toolkits = get_toolkits_status();
                    $available_toolkit_count = 0;
                    foreach ($toolkits as $toolkit) {
                        if (!empty($toolkit['available'])) {
                            $available_toolkit_count += $toolkit['count'] ?? 0;
                        }
                    }
                    if ($available_toolkit_count > 0) {
                        $system_prompt .= "\n **Advanced Toolkits Available:** {$available_toolkit_count}+ WordPress/WooCommerce tools for admin tasks, content management, SEO, security, and more.\n";
                    }
                }
                $system_prompt .= "\n";
            }
            
            // ===== PROGRESSIVE DISCLOSURE: Load Skill Summaries Only =====
            // Instead of loading full skill instructions (high token cost), we inject:
            // 1. Skill summaries (name + description only)
            // 2. The load_skill tool for on-demand instruction loading
            // This saves ~85% of skill-related tokens
            
            $skill_summaries_added = false;
            
            // Try new SkillRegistry for progressive disclosure
            $src_bootstrap = SWC_CHATBOT_PATH . 'src/bootstrap.php';
            if (file_exists($src_bootstrap) && class_exists('\Quarksol\SmartChatbot\Skills\SkillRegistry')) {
                try {
                    $registry = new \Quarksol\SmartChatbot\Skills\SkillRegistry();
                    $registry->setBasePath(SWC_CHATBOT_PATH . 'skills/');
                    $registry->discover();
                    
                    // Get compact skill summaries instead of full content
                    $skill_summaries = $registry::getSummariesForPrompt();
                    
                    if (!empty($skill_summaries)) {
                        $system_prompt .= $skill_summaries . "\n\n";
                        $skill_summaries_added = true;
                        if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                            \Quarksol\SmartChatbot\Services\Logger::debug('Added skill summaries (progressive disclosure active)');
                        }
                    }
                    
                    // Add system tool guidelines (explains how to use load_skill)
                    if (class_exists('\Quarksol\SmartChatbot\Config\SystemToolRegistry')) {
                        $system_prompt .= \Quarksol\SmartChatbot\Config\SystemToolRegistry::getGuidelines() . "\n\n";
                    }
                } catch (Exception $e) {
                    if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                        \Quarksol\SmartChatbot\Services\Logger::error('SkillRegistry error', ['error' => $e->getMessage()]);
                    }
                }
            }
            
            // Fallback: If no progressive disclosure, use legacy full-load (for backward compatibility)
            if (!$skill_summaries_added) {
                $skill_instructions = [];
                
                // Try legacy SWC_Skill_Loader
                require_once SWC_CHATBOT_PATH . 'includes/agent/class-skill-loader.php';
                $loader = SWC_Skill_Loader::get_instance();
                
                foreach ($agent_skills as $skill_slug) {
                    $content = $loader->get_skill_content($skill_slug);
                    if ($content) {
                        $skill_instructions[$skill_slug] = $content;
                    }
                }
                
                // Ultimate fallback: hardcoded core skills
                $core_skills = [
                    'shopping_assistant' => " **SHOPPING ASSISTANT MODE**: Help users find products and complete purchases.",
                    'appointment_booker' => " **APPOINTMENT BOOKING MODE**: Help users book appointments. Collect name, email, and purpose.",
                    'support_agent' => " **SUPPORT MODE**: Answer questions helpfully. Escalate to human support if needed.",
                    'lead_generator' => " **LEAD MODE**: Engage visitors and collect contact information naturally."
                ];
                
                // Fill in any missing skills from core defaults
                foreach ($agent_skills as $skill_slug) {
                    if (!isset($skill_instructions[$skill_slug]) && isset($core_skills[$skill_slug])) {
                        $skill_instructions[$skill_slug] = $core_skills[$skill_slug];
                    }
                }
                
                if (!empty($skill_instructions)) {
                    $system_prompt .= "## ️ YOUR ACTIVE BEHAVIOR MODES (FOLLOW THESE STRICTLY)\n\n";
                    $system_prompt .= "You MUST behave according to these active modes:\n\n";
                    foreach ($skill_instructions as $content) {
                        $system_prompt .= $content . "\n\n";
                    }
                    $system_prompt .= "---\n\n";
                }
            }
            
            // Note: RAG context and Knowledge Base content are no longer auto-injected.
            // The AI uses progressive disclosure via tools (search_knowledge, read_knowledge_item)
            // to fetch knowledge on-demand. See KnowledgeManager::getSummariesForPrompt().
            
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('Sending message via ProviderBridge');
            }
            
            // ===== USE NEW PROVIDER BRIDGE =====
            $response = \Quarksol\SmartChatbot\Bridge\ProviderBridge::chat($system_prompt, $message);
            
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('Got AI response successfully');
            }
            
            // ===== PARSE AND EXECUTE TOOL CALLS =====
            require_once SWC_CHATBOT_PATH . 'includes/agent/class-tool-call-parser.php';
            $tool_parser = new SWC_Tool_Call_Parser();
            
            // Check if response contains tool calls
            if ($tool_parser->has_tool_calls($response)) {
                if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                    \Quarksol\SmartChatbot\Services\Logger::debug('Detected tool calls in AI response');
                }
                $parsed = $tool_parser->parse_and_execute($response);
                
                if ($parsed['has_tool_calls']) {
                    if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                        \Quarksol\SmartChatbot\Services\Logger::debug('Executed tool calls', ['count' => count($parsed['tool_results'])]);
                    }
                    
                    // [Phase 1: UI Bridge] Check for UI signals in tool results
                    // If a tool requests a UI component (like product carousel), return that immediately
                    foreach ($parsed['tool_results'] as $res) {
                        if (isset($res['result']['__ui_signal'])) {
                            return $res['result']['__ui_signal'];
                        }
                    }
                    
                    $response = $parsed['modified_response'];
                }
            }
            
            return $response;
            
        } catch (Exception $e) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Chatbot exception', ['error' => $e->getMessage()]);
            }
            return null;
        } catch (Error $e) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::critical('Chatbot fatal error', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
    
    /**
     * Legacy fallback method using old provider system
     * 
     * @param string $message The user's message
     * @return string|null The AI response or null on failure
     */
    private function get_ai_response_legacy($message) {
        try {
            $settings = get_option('swc_chatbot_settings', array());
            
            $config = array(
                'provider' => $settings['ai_provider'] ?? 'openai',
                'apiKey' => $settings['ai_api_key'] ?? '',
                'model' => $settings['ai_model'] ?? '',
                'temperature' => floatval($settings['ai_temperature'] ?? 0.7),
                'baseUrl' => $settings['ai_base_url'] ?? ''
            );
            
            $provider = SWC_Chatbot_Provider_Factory::create_provider($config);
            
            if (is_wp_error($provider)) {
                if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                    \Quarksol\SmartChatbot\Services\Logger::error('Legacy provider error', ['error' => $provider->get_error_message()]);
                }
                return null;
            }
            
            $store_name = get_bloginfo('name');
            $system_prompt = "You are a helpful shopping assistant for {$store_name}. Be friendly and helpful.";
            
            $provider->set_system_prompt($system_prompt);
            $response = $provider->chat($message);
            
            if (is_wp_error($response)) {
                return null;
            }
            
            return $response;
            
        } catch (Exception $e) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Legacy chatbot exception', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
    
    private function search_products($keyword) {
        $products = SWC_Chatbot_WooCommerce::smart_search($keyword, 5);
        
        if (empty($products)) {
            $suggestions = array(
                "I couldn't find any products matching \"**{$keyword}**\" \n\nBut don't worry! Try:\n• Using different keywords\n• Checking for typos\n• Browsing our categories\n\nOr ask me about our best sellers!",
                "Hmm, no results for \"**{$keyword}**\" \n\nLet me help you find what you need! Try describing what you're looking for in different words, or I can show you our popular products instead.",
                "I searched high and low, but couldn't find \"**{$keyword}**\" in our store \n\nWould you like me to show you similar products or our best sellers?"
            );
            return array(
                'type' => 'text',
                'message' => $suggestions[array_rand($suggestions)]
            );
        }
        
        $count = count($products);
        $responses = array(
            "Found {$count} result(s) for \"**{$keyword}**\"!  Here's what we have:",
            "Great news! I found {$count} product(s) matching \"**{$keyword}**\" ️",
            "Here are {$count} product(s) for \"**{$keyword}**\" - take a look! "
        );
        
        return array(
            'type' => 'products',
            'message' => $responses[array_rand($responses)],
            'products' => $products
        );
    }
    
    /**
     * Handle product search requests - centralized product search logic
     */
    private function handle_product_search($message, $message_lower) {
        // Pattern: "products of category X" or "from category X"
        if (preg_match('/products?\s+(?:of|from|in)\s+(?:category|categorie?s?)\s+[:\"\']?\s*(\w[\w\s]+)/i', $message, $matches)) {
            $category_name = trim(preg_replace('/["\'\s]+$/', '', $matches[1]));
            if (!empty($category_name) && strlen($category_name) > 1) {
                $result = SWC_Chatbot_WooCommerce::get_products_by_category_name($category_name, 10);
                if (!empty($result['products']) && $result['category']) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products from our **{$result['category']['name']}** category! ️ We have {$result['category']['count']} items:",
                        'products' => $result['products']
                    );
                }
            }
        }
        
        // Pattern: "category X" alone
        if (preg_match('/(?:^|\s)(?:category|categorie?s?)\s+[:\"\']?\s*(\w[\w\s]+?)(?:\s*$|["\'])/i', $message, $matches)) {
            $category_name = trim(preg_replace('/["\'\s]+$/', '', $matches[1]));
            if (!empty($category_name) && strlen($category_name) > 1 && !preg_match('/^(of|from|in|list|all)$/i', $category_name)) {
                $result = SWC_Chatbot_WooCommerce::get_products_by_category_name($category_name, 10);
                if (!empty($result['products']) && $result['category']) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products from our **{$result['category']['name']}** category! ️",
                        'products' => $result['products']
                    );
                }
            }
        }
        
        // Pattern: "products for X" or "products related to X" or "products about X"
        if (preg_match('/products?\s+(?:for|about|related\s*(?:to)?)\s+(\w[\w\s]+)/i', $message, $matches)) {
            $search_term = trim($matches[1]);
            if (!empty($search_term) && strlen($search_term) > 1) {
                $products = SWC_Chatbot_WooCommerce::smart_search($search_term, 10);
                if (!empty($products)) {
                    return array(
                        'type' => 'products',
                        'message' => "Here are products related to **{$search_term}**! ",
                        'products' => $products
                    );
                }
                // No products found
                return array(
                    'type' => 'text',
                    'message' => "I couldn't find any products related to **{$search_term}**. Would you like to:\n\n• Browse our **categories**\n• See our **best sellers**\n• Check what's **on sale**"
                );
            }
        }
        
        // Pattern: "show me products" or "find products"
        if (preg_match('/(?:show|find|get|search)\s+(?:me\s+)?(?:all\s+)?products?/i', $message)) {
            $products = SWC_Chatbot_WooCommerce::get_all_products(10);
            $stats = SWC_Chatbot_WooCommerce::get_stock_summary();
            if (!empty($products)) {
                return array(
                    'type' => 'products',
                    'message' => "Here are our products! ️ We have **{$stats['total_products']} products** in our store:",
                    'products' => $products
                );
            }
        }
        
        // Pattern: "product of X" (search by name)
        if (preg_match('/products?\s+(?:of|named?|called)\s+(\w[\w\s]+)/i', $message, $matches)) {
            $product_name = trim($matches[1]);
            if (!preg_match('/^categor/i', $product_name) && !empty($product_name) && strlen($product_name) > 1) {
                $products = SWC_Chatbot_WooCommerce::search_by_title($product_name, 10);
                if (!empty($products)) {
                    return array(
                        'type' => 'products',
                        'message' => "Here's what I found for **{$product_name}**! ",
                        'products' => $products
                    );
                }
                // Fallback to smart search
                $products = SWC_Chatbot_WooCommerce::smart_search($product_name, 10);
                if (!empty($products)) {
                    return array(
                        'type' => 'products',
                        'message' => "Here's what I found for **{$product_name}**! ",
                        'products' => $products
                    );
                }
            }
        }
        
        return null; // No product search matched
    }
    
    /**
     * Check if message looks like a product query
     */
    private function looks_like_product_query($message) {
        $product_indicators = array(
            'need', 'want', 'looking for', 'find', 'search', 'buy', 'purchase',
            'phone', 'laptop', 'computer', 'shirt', 'dress', 'shoe', 'watch',
            'tv', 'television', 'camera', 'headphone', 'speaker', 'tablet',
            'samsung', 'apple', 'iphone', 'android', 'nike', 'adidas',
            'electronic', 'clothing', 'accessory', 'furniture', 'appliance',
            'cheap', 'best', 'good', 'quality', 'price', 'discount', 'sale'
        );
        
        foreach ($product_indicators as $indicator) {
            if (stripos($message, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function show_categories() {
        $categories = SWC_Chatbot_WooCommerce::get_categories();
        
        if (empty($categories)) {
            return array(
                'type' => 'text',
                'message' => "No product categories found."
            );
        }
        
        return array(
            'type' => 'categories',
            'message' => "Browse our categories:",
            'categories' => $categories
        );
    }
    
    private function get_user_orders() {
        $user_id = get_current_user_id();
        $orders = SWC_Chatbot_Orders::get_user_orders($user_id, 3);
        
        if (empty($orders)) {
            return array(
                'type' => 'text',
                'message' => "You don't have any orders yet. Start shopping! "
            );
        }
        
        return array(
            'type' => 'orders',
            'message' => "Here are your recent orders:",
            'orders' => $orders
        );
    }
    
    private function handle_order_lookup($order_id) {
        $order_id = preg_replace('/[^0-9]/', '', $order_id);
        
        if (empty($order_id)) {
            return array(
                'type' => 'text',
                'message' => "Please enter a valid order number.",
                'context' => 'awaiting_order_id'
            );
        }
        
        $settings = get_option('swc_chatbot_settings', array());
        
        if (!empty($settings['guest_order_lookup']) && !is_user_logged_in()) {
            return array(
                'type' => 'text',
                'message' => "For security, please enter the email address used for order #$order_id:",
                'context' => 'awaiting_email',
                'order_id' => $order_id
            );
        }
        
        $order = SWC_Chatbot_Orders::get_order($order_id);
        
        if (!$order) {
            return array(
                'type' => 'text',
                'message' => "Order #$order_id not found. Please check the order number and try again."
            );
        }
        
        return array(
            'type' => 'order',
            'message' => "Here's your order status:",
            'order' => $order
        );
    }
    
    private function handle_email_verification($email, $order_id) {
        if (!is_email($email)) {
            return array(
                'type' => 'text',
                'message' => "Please enter a valid email address.",
                'context' => 'awaiting_email',
                'order_id' => $order_id
            );
        }
        
        $result = SWC_Chatbot_Orders::get_order_by_email($order_id, $email);
        
        if (!$result['success']) {
            return array(
                'type' => 'text',
                'message' => $result['message']
            );
        }
        
        return array(
            'type' => 'order',
            'message' => "Here's your order status:",
            'order' => $result['order']
        );
    }
    
    public function ajax_add_to_cart() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        $result = SWC_Chatbot_WooCommerce::add_to_cart($product_id, $quantity);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function contains_any($haystack, $needles) {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if a message is clearly off-topic (not related to shopping/store)
     * 
     * @param string $message The user's message
     * @return bool True if the message is off-topic
     */
    private function is_off_topic_query($message) {
        $message_lower = strtolower($message);
        
        // Common off-topic patterns
        $off_topic_patterns = array(
            // People/celebrities
            '/\b(who is|tell me about|what do you know about|biography of|life of)\s+[a-z]+/i',
            // General knowledge not related to products
            '/\b(capital of|population of|weather in|how to cook|recipe for|news about)\b/i',
            // Sports/entertainment
            '/\b(football|soccer|basketball|cricket|movie|film|music|song|singer|actor|actress)\b(?!.*(product|item|merch))/i',
            // Philosophy/abstract
            '/\b(meaning of life|why are we here|what is the purpose|philosophical|existential)\b/i',
            // History/geography unrelated to products
            '/\b(history of|when was|where is|located in|country of)\b(?!.*(product|item|store|shop))/i',
            // Programming/tech help (not product related)
            '/\b(how to code|programming|javascript|python|what is ai|machine learning)\b(?!.*(product|item))/i',
        );
        
        foreach ($off_topic_patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        // Check for specific celebrity/person names
        $celebrities = array('elon musk', 'jeff bezos', 'bill gates', 'steve jobs', 'mark zuckerberg',
                            'trump', 'biden', 'obama', 'putin', 'modi',
                            'taylor swift', 'beyonce', 'kim kardashian', 'kanye', 'drake',
                            'cristiano ronaldo', 'messi', 'lebron james', 'michael jordan');
        
        foreach ($celebrities as $celeb) {
            if (strpos($message_lower, $celeb) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a friendly off-topic redirect response
     * 
     * @return array Response array
     */
    private function get_off_topic_response() {
        $store_name = get_bloginfo('name');
        
        $responses = array(
            "I'm your shopping assistant for **{$store_name}**! ️\n\nI can help you with:\n• Finding products\n• Tracking orders\n• Questions about our store\n\nFor general questions, try ChatGPT or Google! \n\nHow can I help you shop today?",
            "Oops! That's outside my expertise!  I'm the **{$store_name}** shopping assistant.\n\nI'm great at:\n• Product recommendations\n• Order tracking\n• Store FAQs\n\nAsk me about our products instead! ",
            "I specialize in helping you shop at **{$store_name}**! \n\nWhile I can't answer general questions, I can help you:\n• Find the perfect product\n• Check order status\n• Learn about our policies\n\nWhat would you like to explore?"
        );
        
        return array(
            'type' => 'text',
            'message' => $responses[array_rand($responses)]
        );
    }
    
    private function extract_search_term($message) {
        $patterns = array(
            '/search(?:\s+for)?\s+(.+)/i',
            '/find(?:\s+me)?\s+(.+)/i',
            '/looking\s+for\s+(.+)/i',
            '/show\s+me\s+(.+)/i',
            '/i\s+want\s+(.+)/i',
            '/i\s+need\s+(.+)/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * AJAX: Add to Wishlist
     */
    public function ajax_add_to_wishlist() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (!$product_id) {
            wp_send_json_error('Invalid product');
        }
        
        // Validate product exists
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            // Use SessionValidator for secure guest wishlist (transients, not PHP sessions)
            if (class_exists('\Quarksol\SmartChatbot\Services\SessionValidator')) {
                $visitorId = \Quarksol\SmartChatbot\Services\SessionValidator::getOrCreateVisitorId();
                $wishlist = \Quarksol\SmartChatbot\Services\SessionValidator::getVisitorData($visitorId, 'wishlist', []);
                
                if (!is_array($wishlist)) {
                    $wishlist = [];
                }
                
                if (!in_array($product_id, $wishlist)) {
                    $wishlist[] = $product_id;
                    \Quarksol\SmartChatbot\Services\SessionValidator::setVisitorData($visitorId, 'wishlist', $wishlist);
                }
                
                wp_send_json_success(['message' => '️ Added to your wishlist! Sign in to save it permanently.']);
            } else {
                // Fallback: Just acknowledge without persistent storage
                wp_send_json_success(['message' => '️ Added to your wishlist! Sign in to save it permanently.']);
            }
            return;
        }
        
        // For logged in users, use user meta
        $user_id = get_current_user_id();
        $wishlist = get_user_meta($user_id, 'swc_wishlist', true);
        if (!is_array($wishlist)) {
            $wishlist = [];
        }
        
        if (!in_array($product_id, $wishlist)) {
            $wishlist[] = $product_id;
            update_user_meta($user_id, 'swc_wishlist', $wishlist);
        }
        
        wp_send_json_success(['message' => '️ Added to your wishlist!']);
    }
    
    /**
     * AJAX: Stock Alert Subscription
     */
    public function ajax_stock_alert() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!$product_id || !is_email($email)) {
            wp_send_json_error('Please provide a valid email address.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_stock_alerts';
        
        // Check if table exists, if not create it
        $this->maybe_create_stock_alerts_table();
        
        // Check if already subscribed
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE product_id = %d AND email = %s",
            $product_id, $email
        ));
        
        if ($exists) {
            wp_send_json_success(array('message' => "You're already on the notification list for this product!"));
        }
        
        // Insert new subscription
        $wpdb->insert($table, array(
            'product_id' => $product_id,
            'email' => $email,
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array('message' => "We'll notify you at {$email} when this product is back in stock!"));
    }
    
    /**
     * AJAX handler to get product URL
     */
    public function ajax_get_product_url() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }
        
        $url = get_permalink($product_id);
        wp_send_json_success(array('url' => $url));
    }
    
    /**
     * Create stock alerts table if not exists
     */
    private function maybe_create_stock_alerts_table() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        global $wpdb;
        $table = $wpdb->prefix . 'swc_chatbot_stock_alerts';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                email varchar(255) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                notified tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY product_id (product_id),
                KEY email (email)
            ) $charset;";
            
            require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * AJAX: Handle message feedback (thumbs up/down)
     */
    public function ajax_message_feedback() {
        check_ajax_referer('swc_chatbot_nonce', 'nonce');
        
        $message_id = sanitize_text_field($_POST['message_id'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $feedback_type = sanitize_text_field($_POST['feedback_type'] ?? '');
        
        if (empty($message_id) || empty($feedback_type)) {
            wp_send_json_error('Missing required fields');
        }
        
        if (!in_array($feedback_type, ['up', 'down'], true)) {
            wp_send_json_error('Invalid feedback type');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swc_message_feedback';
        
        // Create table if not exists
        $this->maybe_create_feedback_table();
        
        // Insert or update feedback
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE message_id = %s AND session_id = %s",
            $message_id,
            $session_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $table,
                ['feedback' => $feedback_type, 'updated_at' => current_time('mysql')],
                ['id' => $existing],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert($table, [
                'message_id' => $message_id,
                'session_id' => $session_id,
                'feedback' => $feedback_type,
                'created_at' => current_time('mysql')
            ], ['%s', '%s', '%s', '%s']);
        }
        
        wp_send_json_success(['message' => 'Feedback recorded']);
    }
    
    /**
     * Create message feedback table if not exists
     */
    private function maybe_create_feedback_table() {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        global $wpdb;
        $table = $wpdb->prefix . 'swc_message_feedback';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                message_id varchar(100) NOT NULL,
                session_id varchar(100) NOT NULL,
                feedback varchar(10) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY message_id (message_id),
                KEY session_id (session_id)
            ) $charset;";
            
            require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}
