<?php
declare(strict_types=1);

/**
 * Shopping Agent
 *
 * E-commerce shopping assistant powered by Neuron AI framework.
 * Uses WooCommerce toolkit with admin-configurable tool access.
 *
 * @package Quarksol\SmartChatbot\Agent
 */

namespace Quarksol\SmartChatbot\Agent;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\SystemPrompt;
use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Config\ToolRegistry;

// Load WooCommerce tools (namespace updated to Quarksol\AgentFlowAI\Toolkits\WooCommerce)
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\ProductSearchTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\ProductDetailsTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\ProductManageTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\VariationsTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\AttributesTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\CategoryBrowseTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\CartManageTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\CartEnhancedTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\CouponsTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\CouponManageTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\OrderTrackTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\OrderManageTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\CustomerTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\ReviewsTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\StoreInfoTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\ShippingInfoTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\ShippingZonesTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\SettingsTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\TaxTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\PaymentGatewaysTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\ReportsTool;
use Quarksol\AgentFlowAI\Toolkits\WooCommerce\WebhooksTool;

/**
 * Shopping Agent - E-commerce assistant
 *
 * Features:
 * - Full WooCommerce toolkit (22 tools)
 * - Admin-configurable tool access
 * - Customizable prompt sections
 */
class ShoppingAgent extends NeuronAgent
{
    /** Agent ID for configuration */
    const AGENT_ID = 'shopping';

    /**
     * Get default instructions (when no config is set)
     *
     * NOTE: We still inject skills and system tool guidelines so load_skill works
     */
    protected function getDefaultInstructions(): string
    {
        $settings = function_exists('get_option')
            ? \Quarksol\SmartChatbot\Config\ChatbotConfig::settings()
            : [];
        $storeName = $settings['store_name'] ?? 'our store';
        $botName   = $settings['bot_name'] ?? 'Shopping Assistant';
        $siteName  = function_exists('get_bloginfo') ? get_bloginfo('name') : $storeName;
        $currency  = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';

        $prompt = (string) new SystemPrompt(
            background: [
                "You are {$botName}, a shopping assistant for {$siteName}.",
                "You have real-time access to the store's full product catalog, cart, orders, and customer data through your tools.",
                "You represent the {$siteName} brand. Be warm, knowledgeable, and helpful — like a great salesperson who genuinely wants to help.",
                "Current currency: {$currency}.",
            ],
            steps: [
                "For every customer message, follow this process:",
                "",
                "1. CLASSIFY the intent:",
                "   A) SPECIFIC product search (user mentions a concrete product type, name, or category):",
                "      Examples: 'red shoes', 'iPhone case', 'laptop stand', 'running headphones'",
                "      → Call `woo_search_products` with specific keywords. Use `min_price`/`max_price` if price is mentioned.",
                "",
                "   B) VAGUE/discovery query (user describes an occasion, person, or feeling — NOT a specific product):",
                "      Examples: 'gift for my mom', 'something nice', 'for a birthday', 'what do you recommend'",
                "      → Do NOT blindly search with vague words like 'mom' or 'gift'. Instead:",
                "        1. Call `woo_categories` to see what product categories exist in the store",
                "        2. Ask the customer a clarifying question about what type of product they're interested in",
                "        3. Suggest 2-3 relevant categories from the store's actual catalog",
                "      Example response: 'I'd love to help find something for your mom! We have [categories]. What kind of gift are you thinking — something practical, decorative, or personal?'",
                "",
                "   C) Product details → Call `woo_product_details` with the product ID",
                "   D) Category browsing → Call `woo_categories` to show available categories",
                "   E) Cart action → Call `woo_cart_enhanced` to add/remove/update items",
                "   F) Order tracking → Ask for order ID if not provided, then call `woo_order_track`",
                "   G) Coupon/discount → Call `woo_coupons` to check available promotions",
                "   H) Store info → Call `woo_store_info` or `woo_shipping_info`",
                "   I) General chat → Respond conversationally, guide to how you can help",
                "",
                "2. ACT immediately — don't describe what you'll do. Call the tool and present results.",
                "",
                "3. PRESENT results with: product name, price (with {$currency}), stock status. For orders: status, items, tracking.",
                "",
                "4. FOLLOW UP with ONE helpful suggestion (related products, checkout prompt, or clarification).",
                "",
                "5. If uncertain, ask ONE specific question — not multiple.",
            ],
            output: [
                "Keep responses concise (2-4 sentences for simple questions, longer for comparisons/lists).",
                "Always show prices with the {$currency} symbol. Show sale prices with original price crossed out.",
                "When displaying product data from tool results, use the 'price_display' or 'price_formatted' field for prices — NEVER use the raw 'price' field as it contains HTML markup.",
                "Use bullet points for product lists. Bold product names and prices.",
                "Include direct product links when available.",
                "Confirm before destructive actions (removing cart items, canceling orders).",
                "Use emojis sparingly (1-2 per message max) for warmth.",
                "Match the customer's language and tone — casual with casual, formal with formal.",
            ],
            toolsUsage: [
                "YOU MUST ACTUALLY CALL TOOLS — never simulate or describe what you'd do.",
                "",
                "Tool selection guide:",
                "- `woo_search_products` → Find products by keyword, category, price range",
                "- `woo_product_details` → Get full info on a specific product (ID required)",
                "- `woo_categories` → Browse/list product categories",
                "- `woo_cart_enhanced` → Add, remove, update cart items, view cart",
                "- `woo_order_track` → Check order status and tracking info",
                "- `woo_coupons` → Check available active coupons/discounts",
                "- `woo_store_info` → Get store details, policies, contact info",
                "- `woo_shipping_info` → Get shipping methods and rates",
                "- `woo_reviews` → Check/add product reviews",
                "",
                "NEVER make up product data, prices, or order statuses. Every data point must come from a tool response.",
                "",
                "RELEVANCE CHECK — After receiving search results, compare the product names to what the customer actually asked for. If the products are clearly unrelated to the customer's request, do NOT show them. Instead tell the customer you couldn't find matching products and suggest browsing categories or refining their search.",
                "",
                "SEARCH STRATEGY — When the customer's message is vague or describes an occasion/person (not a specific product), first call `woo_categories` to see what the store offers, then ask the customer what type of product they want. Only call `woo_search_products` when you have a specific product type to search for.",
            ]
        );

        // Add skill summaries for on-demand loading
        $skillSummaries = \Quarksol\SmartChatbot\Skills\SkillRegistry::getSummariesForPrompt();
        if (!empty($skillSummaries)) {
            $prompt .= "\n\n" . $skillSummaries;
        }

        // Add system tool guidelines
        $guidelines = \Quarksol\SmartChatbot\Config\SystemToolRegistry::getGuidelines();
        if (!empty($guidelines)) {
            $prompt .= "\n\n" . $guidelines;
        }

        return $prompt;
    }

    /**
     * Get default tools - ALL 22 WooCommerce tools
     * (filtering happens via config)
     */
    protected function getDefaultTools(): array
    {
        // Only load WooCommerce tools if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return [];
        }

        return [
            // === PRODUCT TOOLS (5) ===
            new ProductSearchTool(),
            new ProductDetailsTool(),
            new ProductManageTool(),
            new VariationsTool(),
            new AttributesTool(),

            // === CATEGORY (1) ===
            new CategoryBrowseTool(),

            // === CART & CHECKOUT (4) ===
            new CartManageTool(),
            new CartEnhancedTool(),
            new CouponsTool(),
            new CouponManageTool(),

            // === ORDERS (2) ===
            new OrderTrackTool(),
            new OrderManageTool(),

            // === CUSTOMERS (1) ===
            new CustomerTool(),

            // === REVIEWS (1) ===
            new ReviewsTool(),

            // === STORE INFO (3) ===
            new StoreInfoTool(),
            new ShippingInfoTool(),
            new ShippingZonesTool(),

            // === CONFIGURATION (3) ===
            new SettingsTool(),
            new TaxTool(),
            new PaymentGatewaysTool(),

            // === ANALYTICS (1) ===
            new ReportsTool(),

            // === INTEGRATIONS (1) ===
            new WebhooksTool(),
        ];
    }

    /**
     * Create default configuration for shopping agent
     */
    protected function createDefaultConfig(string $agentId): AgentConfig
    {
        $config              = new AgentConfig($agentId, 'Shopping Assistant');
        $config->description = 'E-commerce shopping assistant for customers';
        $config->enabledToolkits = ['woocommerce'];
        $config->isDefault   = true;

        // Disable admin-only tools by default
        $config->disabledTools = [
            'woo_settings',         // Store settings - admin only
            'woo_webhooks',         // Webhooks - admin only
            'woo_tax',              // Tax config - admin only
            'woo_payment_gateways', // Payment gateways - admin only
        ];

        // Default editable prompt sections
        $config->promptSections = [
            'behavior'       => "Be friendly, helpful, and concise.",
            'response_style' => "Always show product prices and availability.\nFormat product lists nicely.",
        ];

        return $config;
    }

    /**
     * Factory with configuration loaded
     */
    public static function configured(): static
    {
        return static::withConfigId(self::AGENT_ID);
    }
}
