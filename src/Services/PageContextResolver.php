<?php
declare(strict_types=1);
/**
 * Page Context Resolver
 *
 * Resolves the content of a WordPress page/post into a structured
 * Markdown summary for injection into the AI agent's system prompt.
 * This enables the chatbot to answer questions about the current page.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class PageContextResolver
{
    /** Maximum characters to include in page context (~1500 tokens) */
    private const MAX_CHARS = 6000;

    /** Cache duration in seconds (1 hour) */
    private const CACHE_TTL = 3600;

    /** Static storage for the resolved context (set per request) */
    private static ?string $currentPageContext = null;

    /**
     * Set the page context for the current request.
     *
     * Called by ChatController when a post_id is received.
     */
    public static function setCurrentContext(?string $context): void
    {
        self::$currentPageContext = $context;
    }

    /**
     * Get the page context for the current request.
     *
     * Called by NeuronAgent when building the system prompt.
     *
     * @return string|null Markdown formatted page context, or null if none.
     */
    public static function getCurrentContext(): ?string
    {
        return self::$currentPageContext;
    }

    /**
     * Resolve a post ID to a Markdown knowledge summary.
     *
     * @param int $postId WordPress post ID.
     * @return string|null Formatted Markdown, or null if post not found.
     */
    public static function resolve(int $postId): ?string
    {
        if ($postId <= 0) {
            return null;
        }

        // Check transient cache first
        $cacheKey = 'swc_page_ctx_' . $postId;
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }

        $markdown = self::buildMarkdown($post);

        if (empty(trim($markdown))) {
            return null;
        }

        // Cache for 1 hour
        set_transient($cacheKey, $markdown, self::CACHE_TTL);

        return $markdown;
    }

    /**
     * Build a Markdown summary from a WP_Post object.
     */
    private static function buildMarkdown(\WP_Post $post): string
    {
        $parts = [];
        $postType = $post->post_type;

        // Title
        $parts[] = '# ' . $post->post_title;

        // Type label
        $typeLabel = ucfirst($postType);
        if ($postType === 'product') {
            $typeLabel = 'WooCommerce Product';
        }
        $parts[] = "**Type:** {$typeLabel}";

        // URL
        $permalink = get_permalink($post);
        if ($permalink) {
            $parts[] = "**URL:** {$permalink}";
        }

        // WooCommerce product-specific data
        if ($postType === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $parts[] = self::buildProductData($product);
            }
        }

        // Main content (sanitized, truncated)
        $content = $post->post_content;
        $content = apply_filters('the_content', $content);
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (!empty($content)) {
            // Truncate to max chars
            if (mb_strlen($content) > self::MAX_CHARS) {
                $content = mb_substr($content, 0, self::MAX_CHARS) . '…';
            }
            $parts[] = "\n## Content\n" . $content;
        }

        // Excerpt / Short description
        $excerpt = trim($post->post_excerpt);
        if (!empty($excerpt)) {
            $excerpt = wp_strip_all_tags($excerpt);
            $parts[] = "\n**Summary:** " . $excerpt;
        }

        // Categories
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $catNames = array_map(fn($c) => $c->name, $categories);
            $parts[] = "**Categories:** " . implode(', ', $catNames);
        }

        // Tags
        $tags = get_the_tags($post->ID);
        if (!empty($tags)) {
            $tagNames = array_map(fn($t) => $t->name, $tags);
            $parts[] = "**Tags:** " . implode(', ', $tagNames);
        }

        $result = implode("\n", $parts);

        // Final length guard
        if (mb_strlen($result) > self::MAX_CHARS) {
            $result = mb_substr($result, 0, self::MAX_CHARS) . '…';
        }

        return $result;
    }

    /**
     * Build WooCommerce product-specific data.
     */
    private static function buildProductData(\WC_Product $product): string
    {
        $lines = [];

        // Price
        $price = $product->get_price();
        $regularPrice = $product->get_regular_price();
        $salePrice = $product->get_sale_price();

        if ($salePrice) {
            $lines[] = "**Price:** ~~{$regularPrice}~~ **{$salePrice}** (On Sale!)";
        } elseif ($price) {
            $lines[] = "**Price:** {$price}";
        }

        // Currency
        $currency = get_woocommerce_currency_symbol();
        if ($currency && !empty($lines)) {
            // Prepend currency to the last added price line
            $lastKey = array_key_last($lines);
            $lines[$lastKey] = str_replace(
                ['**Price:**'],
                ["**Price ({$currency}):**"],
                $lines[$lastKey]
            );
        }

        // SKU
        $sku = $product->get_sku();
        if ($sku) {
            $lines[] = "**SKU:** {$sku}";
        }

        // Stock
        if ($product->managing_stock()) {
            $stockQty = $product->get_stock_quantity();
            $stockStatus = $product->get_stock_status();
            $lines[] = "**Stock:** {$stockQty} ({$stockStatus})";
        } else {
            $stockStatus = $product->get_stock_status();
            $lines[] = "**Stock Status:** " . ucfirst(str_replace('_', ' ', $stockStatus));
        }

        // Weight & Dimensions
        $weight = $product->get_weight();
        if ($weight) {
            $lines[] = "**Weight:** {$weight} " . get_option('woocommerce_weight_unit', 'kg');
        }

        // Short description
        $shortDesc = $product->get_short_description();
        if ($shortDesc) {
            $shortDesc = wp_strip_all_tags($shortDesc);
            $shortDesc = trim($shortDesc);
            if (!empty($shortDesc)) {
                $lines[] = "**Short Description:** {$shortDesc}";
            }
        }

        // Product categories
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $catNames = array_map(fn($t) => $t->name, $terms);
            $lines[] = "**Product Categories:** " . implode(', ', $catNames);
        }

        // Average rating
        $rating = $product->get_average_rating();
        if ($rating && floatval($rating) > 0) {
            $reviewCount = $product->get_review_count();
            $lines[] = "**Rating:** {$rating}/5 ({$reviewCount} reviews)";
        }

        return implode("\n", $lines);
    }
}
