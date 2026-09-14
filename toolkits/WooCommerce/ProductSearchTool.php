<?php

/**
 * Product Search Tool
 * 
 * Search WooCommerce products by keyword, category, price, or filters.
 * 
 * @package Toolkits\WooCommerce
 */

namespace Quarksol\AgentFlowAI\Toolkits\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class ProductSearchTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'woo_search_products',
            description: 'Search for products in the store by keyword, category, price range, or special filters like best sellers, on sale, etc.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Search keyword or product name',
                required: false
            ),
            new ToolProperty(
                name: 'category',
                type: PropertyType::STRING,
                description: 'Category name or slug to filter by',
                required: false
            ),
            new ToolProperty(
                name: 'min_price',
                type: PropertyType::NUMBER,
                description: 'Minimum price filter',
                required: false
            ),
            new ToolProperty(
                name: 'max_price',
                type: PropertyType::NUMBER,
                description: 'Maximum price filter',
                required: false
            ),
            new ToolProperty(
                name: 'filter',
                type: PropertyType::STRING,
                description: 'Special filter: best_sellers, on_sale, new_arrivals, featured, top_rated, cheapest, expensive',
                required: false
            ),
            new ToolProperty(
                name: 'limit',
                type: PropertyType::STRING,
                description: 'Maximum number of results (default: 5)',
                required: false
            ),
        ];
    }
    
    public function __invoke(
        ?string $query = null,
        ?string $category = null,
        ?float $min_price = null,
        ?float $max_price = null,
        ?string $filter = null,
        string|int|null $limit = 5
    ): string {
        $limit = $limit ?? 5;
        
        if (!class_exists('\Quarksol\SmartChatbot\Services\ProductService')) {
            return \Quarksol\SmartChatbot\Types\ToolResponse::error('ProductService not available');
        }
        
        $service = new \Quarksol\SmartChatbot\Services\ProductService();
        
        // Build price filter options
        $priceFilter = [];
        if ($min_price !== null) $priceFilter['min_price'] = $min_price;
        if ($max_price !== null) $priceFilter['max_price'] = $max_price;
        $hasPriceFilter = !empty($priceFilter);
        
        try {
            // Special filters
            if ($filter) {
                $products = match($filter) {
                    'best_sellers' => $service->getBestSellers($limit),
                    'on_sale' => $service->getOnSale($limit),
                    default => $service->search($query ?? '', $limit, $priceFilter),
                };
                // Apply price filter to special filter results if needed
                if ($hasPriceFilter) {
                    $products = $this->applyPriceFilter($products, $min_price, $max_price);
                }
                return \Quarksol\SmartChatbot\Types\ToolResponse::success(['products' => $products, 'filter' => $filter]);
            }
            
            // Search by query (with price filter)
            if ($query) {
                $products = $service->search($query, $hasPriceFilter ? 50 : $limit, $priceFilter);
                if ($hasPriceFilter) {
                    $products = $this->applyPriceFilter($products, $min_price, $max_price);
                    $products = array_slice($products, 0, $limit);
                }
                $meta = ['query' => $query];
                if ($min_price !== null) $meta['min_price'] = $min_price;
                if ($max_price !== null) $meta['max_price'] = $max_price;
                return \Quarksol\SmartChatbot\Types\ToolResponse::success(['products' => $products] + $meta);
            }
            
            // Search by category (with price filter)
            if ($category) {
                $products = $service->searchByCategory($category, $hasPriceFilter ? 50 : $limit);
                if ($hasPriceFilter) {
                    $products = $this->applyPriceFilter($products, $min_price, $max_price);
                    $products = array_slice($products, 0, $limit);
                }
                return \Quarksol\SmartChatbot\Types\ToolResponse::success(['products' => $products, 'category' => $category]);
            }
            
            // Price-only filter (no query, no category) - e.g. "show me products under $50"
            if ($hasPriceFilter) {
                $products = $service->searchByPrice($min_price, $max_price, $limit);
                $meta = [];
                if ($min_price !== null) $meta['min_price'] = $min_price;
                if ($max_price !== null) $meta['max_price'] = $max_price;
                return \Quarksol\SmartChatbot\Types\ToolResponse::success(['products' => $products] + $meta);
            }
            
            // Default logic - if no specific search/filter, list recent products
            $products = $service->search('', $limit);
            
            // If no products found, check if there are drafts
            if (empty($products)) {
                $statusCounts = $service->getProductStatusCounts();
                
                if ($statusCounts['total'] === 0) {
                    return \Quarksol\SmartChatbot\Types\ToolResponse::success(['products' => [], 'message' => 'No products found in the store.']);
                }
                
                if ($statusCounts['publish'] === 0 && $statusCounts['draft'] > 0) {
                    return \Quarksol\SmartChatbot\Types\ToolResponse::success([
                        'products' => [], 
                        'message' => "No published products found, but found {$statusCounts['draft']} draft products. Please publish them to make them visible."
                    ]);
                }
            }

            return \Quarksol\SmartChatbot\Types\ToolResponse::success(['products' => $products, 'note' => 'Showing recent products']);
            
        } catch (\Throwable $e) {
            $logMsg = date('Y-m-d H:i:s') . " ProductSearchTool Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            error_log( $logMsg, FILE_APPEND);
            return \Quarksol\SmartChatbot\Types\ToolResponse::error($e->getMessage());
        }
    }
    
    /**
     * Apply price filter to product results
     */
    private function applyPriceFilter(array $products, ?float $minPrice, ?float $maxPrice): array
    {
        return array_values(array_filter($products, function ($product) use ($minPrice, $maxPrice) {
            $price = floatval($product['price_raw'] ?? 0);
            if ($price <= 0) return false;
            if ($minPrice !== null && $price < $minPrice) return false;
            if ($maxPrice !== null && $price > $maxPrice) return false;
            return true;
        }));
    }
}
