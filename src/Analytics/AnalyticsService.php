<?php
declare(strict_types=1);
/**
 * Analytics Service
 * 
 * Core business logic for analytics tracking and aggregation.
 * Provides unified API for tracking events and retrieving metrics.
 * 
 * @package Quarksol\SmartChatbot\Analytics
 */

namespace Quarksol\SmartChatbot\Analytics;

use Quarksol\SmartChatbot\Types\ModelInfo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics Service
 */
class AnalyticsService {
    
    /** Singleton instance */
    protected static ?self $instance = null;
    
    /** Repository */
    protected AnalyticsRepository $repository;
    
    /** Current context */
    protected static array $context = [];
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new AnalyticsRepository();
    }
    
    /**
     * Set tracking context (session, agent, user info)
     * Call at the start of a request to set context for all tracked events
     */
    public static function setContext(array $context): void {
        self::$context = array_merge(self::$context, $context);
    }
    
    /**
     * Clear context
     */
    public static function clearContext(): void {
        self::$context = [];
    }
    
    /**
     * Track an analytics event
     * 
     * @param string $eventType Event type (chat, tool_call, skill_load, rag_query, etc.)
     * @param array $data Event data
     */
    public static function track(string $eventType, array $data = []): void {
        // Ensure tables exist
        if (!AnalyticsSchema::tablesExist()) {
            return; // Silently skip if not installed
        }
        
        try {
            $instance = self::getInstance();
            
            // Merge context with event data
            $eventData = array_merge([
                'event_type' => $eventType,
                'created_at' => current_time('mysql'),
            ], self::$context, $data);
            
            // Insert event
            $instance->repository->insertEvent($eventData);
        } catch (\Throwable $e) {
            // Log but don't throw - analytics should never break the app
            \Quarksol\SmartChatbot\Services\Logger::error('Analytics track error', ['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Track a chat/AI request
     */
    public static function trackChat(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $costUsd,
        int $durationMs,
        bool $success = true,
        ?string $errorMessage = null,
        ?int $cacheReadTokens = null,
        ?int $cacheWriteTokens = null
    ): void {
        self::track('chat', [
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheReadTokens ?? 0,
            'cache_write_tokens' => $cacheWriteTokens ?? 0,
            'cost_usd' => $costUsd,
            'duration_ms' => $durationMs,
            'success' => $success ? 1 : 0,
            'error_message' => $errorMessage,
        ]);
    }
    
    /**
     * Track a tool call
     */
    public static function trackToolCall(
        string $toolName,
        bool $success = true,
        ?int $durationMs = null,
        ?string $errorMessage = null
    ): void {
        self::track('tool_call', [
            'tool_name' => $toolName,
            'success' => $success ? 1 : 0,
            'duration_ms' => $durationMs,
            'error_message' => $errorMessage,
        ]);
    }
    
    /**
     * Track skill activation
     */
    public static function trackSkillLoad(string $skillSlug): void {
        self::track('skill_load', [
            'skill_slug' => $skillSlug,
        ]);
    }
    
    /**
     * Track RAG/knowledge base query
     */
    public static function trackRAGQuery(
        string $query,
        int $resultCount,
        ?int $durationMs = null
    ): void {
        self::track('rag_query', [
            'metadata' => ['query' => substr($query, 0, 500), 'result_count' => $resultCount],
            'duration_ms' => $durationMs,
            'success' => $resultCount > 0 ? 1 : 0,
        ]);
    }
    
    /**
     * Get dashboard summary
     * 
     * @param string $period Period (today, 7d, 30d, 90d, all)
     * @param int|null $agentDbId Optional agent filter
     * @return array Dashboard data
     */
    public static function getDashboardSummary(string $period = '30d', ?int $agentDbId = null): array {
        $instance = self::getInstance();
        [$startDate, $endDate] = self::getPeriodDates($period);
        
        // Get basic stats
        $sessionStats = $instance->repository->getSessionStats($startDate, $endDate);
        $performance = $instance->repository->getPerformanceMetrics($startDate, $endDate);
        
        return [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            
            // Volume
            'total_sessions' => (int) ($sessionStats['total_sessions'] ?? 0),
            'total_messages' => (int) ($sessionStats['total_requests'] ?? 0),
            'unique_users' => (int) ($sessionStats['unique_users'] ?? 0),
            'unique_visitors' => (int) ($sessionStats['unique_visitors'] ?? 0),
            
            // Tokens
            'total_input_tokens' => (int) ($sessionStats['total_input_tokens'] ?? 0),
            'total_output_tokens' => (int) ($sessionStats['total_output_tokens'] ?? 0),
            'total_tokens' => (int) (($sessionStats['total_input_tokens'] ?? 0) + ($sessionStats['total_output_tokens'] ?? 0)),
            
            // Cost
            'total_cost' => round((float) ($sessionStats['total_cost'] ?? 0), 4),
            'cost_per_session' => $sessionStats['total_sessions'] > 0 
                ? round(($sessionStats['total_cost'] ?? 0) / $sessionStats['total_sessions'], 6)
                : 0,
            
            // Performance
            'avg_response_time_ms' => (int) ($performance['avg_response_time'] ?? 0),
            'p95_response_time_ms' => (int) ($performance['p95_response_time'] ?? 0),
            'error_count' => (int) ($performance['error_count'] ?? 0),
            'success_rate' => (float) ($performance['success_rate'] ?? 100),
        ];
    }
    
    /**
     * Get cost breakdown
     * 
     * @param string $period Period
     * @param string $groupBy Group by (provider, model, agent)
     * @return array Cost breakdown
     */
    public static function getCostBreakdown(string $period = '30d', string $groupBy = 'provider'): array {
        $instance = self::getInstance();
        [$startDate, $endDate] = self::getPeriodDates($period);
        
        return match($groupBy) {
            'model' => $instance->repository->getCostByModel($startDate, $endDate),
            'agent' => $instance->repository->getAgentComparison($startDate, $endDate),
            default => $instance->repository->getCostByProvider($startDate, $endDate),
        };
    }
    
    /**
     * Get usage trends over time
     * 
     * @param string $period Period
     * @param string $metric Metric (cost, sessions, tokens, requests)
     * @return array Trend data
     */
    public static function getUsageTrends(string $period = '30d', string $metric = 'cost'): array {
        $instance = self::getInstance();
        [$startDate, $endDate] = self::getPeriodDates($period);
        
        return $instance->repository->getDailyTrends($startDate, $endDate, $metric);
    }
    
    /**
     * Get top tools by usage
     * 
     * @param string $period Period
     * @param int $limit Max tools
     * @return array Tool stats
     */
    public static function getTopTools(string $period = '30d', int $limit = 20): array {
        $instance = self::getInstance();
        [$startDate, $endDate] = self::getPeriodDates($period);
        
        return $instance->repository->getToolUsageStats($startDate, $endDate, $limit);
    }
    
    /**
     * Get skill usage stats
     * 
     * @param string $period Period
     * @return array Skill stats
     */
    public static function getSkillStats(string $period = '30d'): array {
        $instance = self::getInstance();
        [$startDate, $endDate] = self::getPeriodDates($period);
        
        return $instance->repository->getSkillUsageStats($startDate, $endDate);
    }
    
    /**
     * Get agent performance comparison
     * 
     * @param string $period Period
     * @return array Agent comparison
     */
    public static function getAgentPerformance(string $period = '30d'): array {
        $instance = self::getInstance();
        [$startDate, $endDate] = self::getPeriodDates($period);
        
        return $instance->repository->getAgentComparison($startDate, $endDate);
    }
    
    /**
     * Roll up daily statistics
     * Should be called by cron job daily
     * 
     * @param string|null $date Date to rollup (defaults to yesterday)
     * @return int Number of stats created
     */
    public static function rollupDailyStats(?string $date = null): int {
        $instance = self::getInstance();
        
        $date = $date ?? date('Y-m-d', strtotime('-1 day'));
        
        return $instance->repository->rollupDailyStats($date);
    }
    
    /**
     * Export analytics data
     * 
     * @param string $period Period
     * @param string $format Format (csv, json)
     * @return array Export data
     */
    public static function exportData(string $period = '30d', string $format = 'json'): array {
        $summary = self::getDashboardSummary($period);
        $costByProvider = self::getCostBreakdown($period, 'provider');
        $costByModel = self::getCostBreakdown($period, 'model');
        $tools = self::getTopTools($period);
        $skills = self::getSkillStats($period);
        $agents = self::getAgentPerformance($period);
        $trends = self::getUsageTrends($period);
        
        return [
            'exported_at' => current_time('c'),
            'period' => $period,
            'summary' => $summary,
            'cost_by_provider' => $costByProvider,
            'cost_by_model' => $costByModel,
            'top_tools' => $tools,
            'skill_usage' => $skills,
            'agent_performance' => $agents,
            'daily_trends' => $trends,
        ];
    }
    
    /**
     * Cleanup old data
     * 
     * @param int $daysToKeep Days of events to retain
     * @return int Number of events deleted
     */
    public static function cleanup(int $daysToKeep = 90): int {
        $instance = self::getInstance();
        return $instance->repository->cleanupOldEvents($daysToKeep);
    }
    
    /**
     * Convert period string to date range
     * 
     * @param string $period Period (today, 7d, 30d, 90d, all)
     * @return array [startDate, endDate]
     */
    protected static function getPeriodDates(string $period): array {
        $endDate = date('Y-m-d');
        
        $startDate = match($period) {
            'today' => $endDate,
            '7d' => date('Y-m-d', strtotime('-6 days')),
            '30d' => date('Y-m-d', strtotime('-29 days')),
            '90d' => date('Y-m-d', strtotime('-89 days')),
            'all' => '2020-01-01',
            default => date('Y-m-d', strtotime('-29 days')),
        };
        
        return [$startDate, $endDate];
    }
    
    /**
     * Calculate cost for a given provider/model
     * 
     * @param string $provider Provider name
     * @param string $model Model ID
     * @param int $inputTokens Input tokens
     * @param int $outputTokens Output tokens
     * @param int $cacheReadTokens Cache read tokens
     * @param int $cacheWriteTokens Cache write tokens
     * @return float Cost in USD
     */
    public static function calculateCost(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0
    ): float {
        // Get pricing from model info
        $pricing = self::getModelPricing($provider, $model);
        
        if (!$pricing) {
            return 0.0;
        }
        
        // Calculate cost per 1M tokens
        $inputCost = ($inputTokens / 1_000_000) * ($pricing['inputPrice'] ?? 0);
        $outputCost = ($outputTokens / 1_000_000) * ($pricing['outputPrice'] ?? 0);
        $cacheReadCost = ($cacheReadTokens / 1_000_000) * ($pricing['cacheReadsPrice'] ?? 0);
        $cacheWriteCost = ($cacheWriteTokens / 1_000_000) * ($pricing['cacheWritesPrice'] ?? 0);
        
        return $inputCost + $outputCost + $cacheReadCost + $cacheWriteCost;
    }
    
    /**
     * Get model pricing info
     * 
     * Uses CostCalculator as the primary pricing source for comprehensive coverage.
     */
    protected static function getModelPricing(string $provider, string $model): ?array {
        // Primary: Use the comprehensive CostCalculator
        if (class_exists(CostCalculator::class)) {
            $calculator = new CostCalculator();
            $pricing = $calculator->getModelPricing($provider, $model);
            
            if ($pricing) {
                // Convert CostCalculator format to expected format
                return [
                    'inputPrice' => $pricing['input'] ?? 0,
                    'outputPrice' => $pricing['output'] ?? 0,
                    'cacheReadsPrice' => $pricing['cache_read'] ?? 0,
                    'cacheWritesPrice' => $pricing['cache_write'] ?? 0,
                ];
            }
        }
        
        // Fallback: Try provider constants
        $providerFile = SWC_CHATBOT_PATH . "src/api/providers/{$provider}.php";
        
        if (file_exists($providerFile)) {
            // Load provider constants
            $constantName = strtoupper($provider) . '_MODELS';
            
            if (defined("Quarksol\SmartChatbot\Api\\Providers\\{$constantName}")) {
                $models = constant("Quarksol\SmartChatbot\Api\\Providers\\{$constantName}");
                return $models[$model] ?? null;
            }
        }
        
        // Final fallback: common pricing defaults
        return self::getDefaultPricing($provider, $model);
    }
    
    /**
     * Get default pricing for common models
     * 
     * This is a last-resort fallback. CostCalculator should handle most cases.
     */
    protected static function getDefaultPricing(string $provider, string $model): ?array {
        $defaults = [
            'anthropic' => [
                'claude-sonnet-4-5' => ['inputPrice' => 3.0, 'outputPrice' => 15.0],
                'claude-3-5-sonnet-20241022' => ['inputPrice' => 3.0, 'outputPrice' => 15.0],
                'claude-3-5-haiku-20241022' => ['inputPrice' => 0.8, 'outputPrice' => 4.0],
            ],
            'openai' => [
                'gpt-4o' => ['inputPrice' => 2.5, 'outputPrice' => 10.0],
                'gpt-4o-mini' => ['inputPrice' => 0.15, 'outputPrice' => 0.6],
                'o3-mini' => ['inputPrice' => 1.1, 'outputPrice' => 4.4],
            ],
            'azure' => [
                'gpt-4o' => ['inputPrice' => 2.5, 'outputPrice' => 10.0],
                'gpt-4o-mini' => ['inputPrice' => 0.15, 'outputPrice' => 0.6],
                'gpt-5-mini' => ['inputPrice' => 1.1, 'outputPrice' => 4.4],
                'gpt-4-turbo' => ['inputPrice' => 10.0, 'outputPrice' => 30.0],
                'gpt-4' => ['inputPrice' => 30.0, 'outputPrice' => 60.0],
            ],
            'deepseek' => [
                'deepseek-chat' => ['inputPrice' => 0.27, 'outputPrice' => 1.1],
                'deepseek-reasoner' => ['inputPrice' => 0.55, 'outputPrice' => 2.19],
            ],
        ];
        
        $providerLower = strtolower($provider);
        return $defaults[$providerLower][$model] ?? null;
    }
}
