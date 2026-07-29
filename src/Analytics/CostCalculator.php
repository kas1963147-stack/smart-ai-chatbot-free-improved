<?php
declare(strict_types=1);
/**
 * Cost Calculator Service
 * 
 * Calculates API token costs using hardcoded defaults and optional admin overrides.
 * 
 * @package Quarksol\SmartChatbot\Analytics
 */

namespace Quarksol\SmartChatbot\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cost Calculator with Dual Pricing System
 */
class CostCalculator {
    
    /**
     * Cached pricing data loaded from JSON file
     * @var array|null
     */
    private static ?array $pricingCache = null;
    
    /**
     * Get default pricing data.
     * 
     * Loads from pricing.json on first call. Cached for the rest of the request.
     * To update pricing, edit src/Analytics/pricing.json.
     * 
     * @return array Pricing data grouped by provider
     */
    private static function getDefaultPricing(): array {
        if (self::$pricingCache !== null) {
            return self::$pricingCache;
        }
        
        $jsonPath = __DIR__ . '/pricing.json';
        
        if (file_exists($jsonPath)) {
            $json = file_get_contents($jsonPath);
            $data = json_decode($json, true);
            
            if (is_array($data)) {
                $pricing = [];
                
                foreach ($data as $provider => $models) {
                    // Skip metadata keys
                    if (str_starts_with($provider, '_')) {
                        // Handle _free_providers section
                        if ($provider === '_free_providers') {
                            foreach ($models as $freeProvider => $freeModels) {
                                $pricing[$freeProvider] = $freeModels;
                            }
                        }
                        continue;
                    }
                    $pricing[$provider] = $models;
                }
                
                self::$pricingCache = $pricing;
                return self::$pricingCache;
            }
        }
        
        // Fallback: minimal inline pricing if JSON is missing
        error_log('[CostCalculator] Warning: pricing.json not found at ' . $jsonPath);
        self::$pricingCache = [
            'openai' => [
                'gpt-4o' => ['input' => 2.50, 'output' => 10.00, 'cache_write' => null, 'cache_read' => null],
                'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60, 'cache_write' => null, 'cache_read' => null],
            ],
            'anthropic' => [
                'claude-sonnet-4-5' => ['input' => 3.00, 'output' => 15.00, 'cache_write' => null, 'cache_read' => null],
            ],
            'ollama' => ['*' => ['input' => 0.00, 'output' => 0.00, 'cache_write' => null, 'cache_read' => null]],
            'lmstudio' => ['*' => ['input' => 0.00, 'output' => 0.00, 'cache_write' => null, 'cache_read' => null]],
        ];
        
        return self::$pricingCache;
    }
    
    /**
     * Clear the pricing cache (e.g., after pricing.json is updated)
     */
    public static function clearPricingCache(): void {
        self::$pricingCache = null;
    }
    
    /**
     * Calculate cost for API token usage
     * 
     * @param string $provider Provider name (anthropic, openai, google, etc.)
     * @param string $model Model identifier
     * @param int $inputTokens Input tokens count
     * @param int $outputTokens Output tokens count
     * @param int|null $cacheWriteTokens Cache creation tokens
     * @param int|null $cacheReadTokens Cache read tokens
     * @param string $protocol Token counting protocol ('anthropic' or 'openai')
     * @return float Cost in USD
     */
    public function calculateCost(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?int $cacheWriteTokens = null,
        ?int $cacheReadTokens = null,
        string $protocol = 'anthropic'
    ): float {
        $pricing = $this->getModelPricing($provider, $model);
        
        if (!$pricing) {
            error_log("CostCalculator: No pricing found for {$provider}/{$model}");
            return 0.0;
        }
        
        // Handle OpenAI vs Anthropic token counting differences
        if ($protocol === 'openai') {
            // OpenAI includes cache tokens in inputTokens count
            $cacheWriteTokens = $cacheWriteTokens ?? 0;
            $cacheReadTokens = $cacheReadTokens ?? 0;
            $nonCachedInputTokens = max(0, $inputTokens - $cacheWriteTokens - $cacheReadTokens);
        } else {
            // Anthropic does NOT include cache tokens in inputTokens count
            $nonCachedInputTokens = $inputTokens;
            $cacheWriteTokens = $cacheWriteTokens ?? 0;
            $cacheReadTokens = $cacheReadTokens ?? 0;
        }
        
        // Calculate costs (prices are per million tokens)
        $inputCost = ($pricing['input'] / 1_000_000) * $nonCachedInputTokens;
        $outputCost = ($pricing['output'] / 1_000_000) * $outputTokens;
        $cacheWriteCost = $pricing['cache_write'] 
            ? ($pricing['cache_write'] / 1_000_000) * $cacheWriteTokens 
            : 0;
        $cacheReadCost = $pricing['cache_read'] 
            ? ($pricing['cache_read'] / 1_000_000) * $cacheReadTokens 
            : 0;
        
        $totalCost = $inputCost + $outputCost + $cacheWriteCost + $cacheReadCost;
        
        return round($totalCost, 8);
    }
    
    /**
     * Get pricing for a model
     * 
     * Resolution order:
     * 1. Check database for custom override
     * 2. Fall back to hardcoded default
     * 3. Return null if neither exists
     * 
     * @param string $provider Provider name
     * @param string $model Model identifier
     * @return array|null Pricing array with keys: input, output, cache_write, cache_read
     */
    public function getModelPricing(string $provider, string $model): ?array {
        // Step 1: Check for custom override in database
        $customPricing = $this->getCustomPricing($provider, $model);
        if ($customPricing) {
            return $customPricing;
        }
        
        // Step 2: Check defaults from JSON
        $provider = strtolower($provider);
        $defaults = self::getDefaultPricing();
        if (isset($defaults[$provider][$model])) {
            return $defaults[$provider][$model];
        }
        
        // Step 3: Check for wildcard pricing (for local/free providers)
        if (isset($defaults[$provider]['*'])) {
            return $defaults[$provider]['*'];
        }
        
        // Step 4: No pricing found
        return null;
    }
    
    /**
     * Get custom pricing override from database
     * 
     * @param string $provider Provider name
     * @param string $model Model identifier
     * @return array|null Custom pricing or null if not found
     */
    private function getCustomPricing(string $provider, string $model): ?array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $table = $tables['pricing'];
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE provider = %s AND model = %s",
            $provider,
            $model
        ), ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        // Only return if at least one custom price is set (not NULL)
        if ($row['input_price_per_million'] === null && 
            $row['output_price_per_million'] === null) {
            return null;
        }
        
        return [
            'input' => $row['input_price_per_million'] !== null 
                ? (float) $row['input_price_per_million'] 
                : 0.0,
            'output' => $row['output_price_per_million'] !== null 
                ? (float) $row['output_price_per_million'] 
                : 0.0,
            'cache_write' => $row['cache_write_price_per_million'] !== null 
                ? (float) $row['cache_write_price_per_million'] 
                : null,
            'cache_read' => $row['cache_read_price_per_million'] !== null 
                ? (float) $row['cache_read_price_per_million'] 
                : null,
        ];
    }
    
    /**
     * Get all available pricing (defaults + custom overrides)
     * 
     * @return array Pricing data grouped by provider
     */
    public function getAllPricing(): array {
        $pricing = [];
        
        // Start with defaults from JSON
        foreach (self::getDefaultPricing() as $provider => $models) {
            foreach ($models as $model => $prices) {
                $pricing[] = [
                    'provider' => $provider,
                    'model' => $model,
                    'default_input' => $prices['input'],
                    'default_output' => $prices['output'],
                    'default_cache_write' => $prices['cache_write'],
                    'default_cache_read' => $prices['cache_read'],
                    'custom_input' => null,
                    'custom_output' => null,
                    'custom_cache_write' => null,
                    'custom_cache_read' => null,
                    'has_custom' => false,
                ];
            }
        }
        
        // Overlay custom overrides
        global $wpdb;
        $tables = AnalyticsSchema::getTableNames();
        $table = $tables['pricing'];
        
        $customRows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
        
        foreach ($customRows as $row) {
            $found = false;
            
            // Find matching default entry
            foreach ($pricing as &$entry) {
                if ($entry['provider'] === $row['provider'] && 
                    $entry['model'] === $row['model']) {
                    $entry['custom_input'] = $row['input_price_per_million'];
                    $entry['custom_output'] = $row['output_price_per_million'];
                    $entry['custom_cache_write'] = $row['cache_write_price_per_million'];
                    $entry['custom_cache_read'] = $row['cache_read_price_per_million'];
                    $entry['has_custom'] = true;
                    $found = true;
                    break;
                }
            }
            
            // Add entry for custom-only models (no default)
            if (!$found) {
                $pricing[] = [
                    'provider' => $row['provider'],
                    'model' => $row['model'],
                    'default_input' => null,
                    'default_output' => null,
                    'default_cache_write' => null,
                    'default_cache_read' => null,
                    'custom_input' => $row['input_price_per_million'],
                    'custom_output' => $row['output_price_per_million'],
                    'custom_cache_write' => $row['cache_write_price_per_million'],
                    'custom_cache_read' => $row['cache_read_price_per_million'],
                    'has_custom' => true,
                ];
            }
        }
        
        return $pricing;
    }
    
    /**
     * Save custom pricing override
     * 
     * @param string $provider Provider name
     * @param string $model Model identifier
     * @param float|null $inputPrice Input price per million or null to use default
     * @param float|null $outputPrice Output price per million or null to use default
     * @param float|null $cacheWritePrice Cache write price per million
     * @param float|null $cacheReadPrice Cache read price per million
     * @return bool Success
     */
    public function savePricing(
        string $provider,
        string $model,
        ?float $inputPrice,
        ?float $outputPrice,
        ?float $cacheWritePrice = null,
        ?float $cacheReadPrice = null
    ): bool {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $table = $tables['pricing'];
        
        $result = $wpdb->replace(
            $table,
            [
                'provider' => $provider,
                'model' => $model,
                'input_price_per_million' => $inputPrice,
                'output_price_per_million' => $outputPrice,
                'cache_write_price_per_million' => $cacheWritePrice,
                'cache_read_price_per_million' => $cacheReadPrice,
            ],
            ['%s', '%s', '%f', '%f', '%f', '%f']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete custom pricing override (revert to default)
     * 
     * @param string $provider Provider name
     * @param string $model Model identifier
     * @return bool Success
     */
    public function deletePricing(string $provider, string $model): bool {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $table = $tables['pricing'];
        
        $result = $wpdb->delete(
            $table,
            [
                'provider' => $provider,
                'model' => $model,
            ],
            ['%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Recalculate historical costs for analytics events
     * 
     * Updates the cost_usd field in swc_analytics_events table based on current pricing
     * 
     * @param string|null $fromDate Optional start date (Y-m-d format), defaults to 30 days ago
     * @param int $limit Maximum number of rows to update per batch
     * @return array Results with updated count and any errors
     */
    public function recalculateHistoricalCosts(?string $fromDate = null, int $limit = 1000): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $eventsTable = $tables['events'];
        
        // Default to 30 days ago if no date specified
        if (!$fromDate) {
            $fromDate = date('Y-m-d', strtotime('-30 days'));
        }
        
        // Get events with token data
        $query = $wpdb->prepare(
            "SELECT id, provider, model, input_tokens, output_tokens, 
                    cache_read_tokens, cache_write_tokens 
             FROM {$eventsTable} 
             WHERE event_date >= %s 
               AND provider IS NOT NULL 
               AND model IS NOT NULL
             LIMIT %d",
            $fromDate,
            $limit
        );
        
        $events = $wpdb->get_results($query, ARRAY_A);
        
        if (!$events) {
            return [
                'success' => true,
                'updated' => 0,
                'message' => 'No events found to update',
            ];
        }
        
        $updated = 0;
        $errors = [];
        
        foreach ($events as $event) {
            // Determine protocol based on provider
            $protocol = in_array(strtolower($event['provider']), ['anthropic', 'anthropicvertex', 'bedrock']) 
                ? 'anthropic' 
                : 'openai';
            
            // Calculate new cost
            $newCost = $this->calculateCost(
                $event['provider'],
                $event['model'],
                (int) $event['input_tokens'],
                (int) $event['output_tokens'],
                (int) $event['cache_write_tokens'],
                (int) $event['cache_read_tokens'],
                $protocol
            );
            
            // Update the event
            $result = $wpdb->update(
                $eventsTable,
                ['cost_usd' => $newCost],
               ['id' => $event['id']],
                ['%f'],
                ['%d']
            );
            
            if ($result !== false) {
                $updated++;
            } else {
                $errors[] = "Failed to update event ID {$event['id']}";
            }
        }
        
        // Also update aggregated usage stats
        $this->recalculateUsageStats($fromDate);
        
        return [
            'success' => count($errors) === 0,
            'updated' => $updated,
            'total' => count($events),
            'errors' => $errors,
            'message' => sprintf(
                'Updated costs for %d of %d events',
                $updated,
                count($events)
            ),
        ];
    }
    
    /**
     * Recalculate aggregated usage stats
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @return bool Success
     */
    private function recalculateUsageStats(string $fromDate): bool {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $eventsTable = $tables['events'];
        $statsTable = $tables['stats'];
        
        // Get affected stat dates
        $dates = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(event_date) as stat_date 
             FROM {$eventsTable} 
             WHERE event_date >= %s",
            $fromDate
        ));
        
        foreach ($dates as $date) {
            // Recalculate stats for this date
            $wpdb->query($wpdb->prepare(
                "UPDATE {$statsTable} s
                 SET s.total_cost_usd = (
                     SELECT COALESCE(SUM(e.cost_usd), 0)
                     FROM {$eventsTable} e
                     WHERE DATE(e.event_date) = s.stat_date
                       AND (s.provider IS NULL OR e.provider = s.provider)
                       AND (s.model IS NULL OR e.model = s.model)
                 )
                 WHERE s.stat_date = %s",
                $date
            ));
        }
        
        return true;
    }
}

