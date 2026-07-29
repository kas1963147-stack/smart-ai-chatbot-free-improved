<?php
declare(strict_types=1);
/**
 * Free Search Products Tool (NeuronAI)
 *
 * Hidden free-tier tool: WooCommerce product search.
 * Always available to agents — no admin configuration needed.
 *
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

if (!defined('ABSPATH')) {
    exit;
}

class FreeSearchProductsTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'search_products',
            description: 'Search for products available in the store. Returns product names, prices, stock status, and links.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'keyword',
                type: PropertyType::STRING,
                description: 'Search keyword or phrase (e.g. "running shoes", "coffee maker")',
                required: true
            ),
            new ToolProperty(
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Maximum number of results to return (default: 5)',
                required: false
            ),
        ];
    }

    public function __invoke(string $keyword, ?int $limit = 5): string
    {
        error_log('[SWC Free Tool] search_products called: ' . $keyword);

        if (!class_exists('WooCommerce') || !function_exists('wc_get_products')) {
            return json_encode(['error' => 'WooCommerce is not active on this site.']);
        }

        $limit = $limit ?? 5;

        $products = wc_get_products([
            's'      => sanitize_text_field($keyword),
            'limit'  => $limit,
            'status' => 'publish',
        ]);

        if (empty($products)) {
            return json_encode([
                'message' => 'No products found matching "' . $keyword . '".',
                'products' => [],
            ]);
        }

        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'id'           => $product->get_id(),
                'name'         => $product->get_name(),
                'price'        => strip_tags(wc_price($product->get_price())),
                'regular_price'=> strip_tags(wc_price($product->get_regular_price())),
                'on_sale'      => $product->is_on_sale(),
                'in_stock'     => $product->is_in_stock(),
                'url'          => get_permalink($product->get_id()),
                'short_desc'   => wp_trim_words($product->get_short_description(), 20, '...'),
            ];
        }

        return json_encode([
            'products' => $results,
            'total_found' => count($results),
        ]);
    }
}
