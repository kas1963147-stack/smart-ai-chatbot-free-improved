<?php
declare(strict_types=1);
/**
 * Analytics Repository
 * 
 * Database query layer for analytics operations.
 * Handles insertions, aggregations, and complex queries.
 * 
 * @package Quarksol\SmartChatbot\Analytics
 */

namespace Quarksol\SmartChatbot\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics Repository
 */
class AnalyticsRepository {

    /**
     * Cached event table columns
     * @var array<string>|null
     */
    private static ?array $eventColumns = null;
    
    /**
     * Insert an analytics event
     * 
     * @param array $data Event data
     * @return int|false Insert ID or false on failure
     */
    public function insertEvent(array $data): int|false {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        if (AnalyticsSchema::needsUpgrade()) {
            AnalyticsSchema::createTables();
            self::$eventColumns = null;
        }
        
        $defaults = [
            'event_type' => 'unknown',
            'session_id' => null,
            'agent_db_id' => null,
            'user_id' => null,
            'visitor_id' => null,
            'provider' => null,
            'model' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cache_read_tokens' => 0,
            'cache_write_tokens' => 0,
            'cost_usd' => 0,
            'duration_ms' => null,
            'tool_name' => null,
            'skill_slug' => null,
            'success' => 1,
            'error_message' => null,
            'metadata' => null,
            'source' => null,
            'query_length' => null,
            'response_length' => null,
            'created_at' => null,
        ];
        
        $data = array_merge($defaults, $data);
        $data = array_intersect_key($data, $defaults);
        if (empty($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }

        $columns = $this->getEventColumns($tables['events']);
        if (empty($columns)) {
            return false;
        }
        $data = array_intersect_key($data, array_flip($columns));
        
        // JSON encode metadata if array
        if (is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }
        
        $result = $wpdb->insert($tables['events'], $data);
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Fetch and cache event table columns
     */
    private function getEventColumns(string $table): array {
        if (self::$eventColumns !== null) {
            return self::$eventColumns;
        }

        global $wpdb;
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!is_array($columns)) {
            self::$eventColumns = [];
            return [];
        }

        self::$eventColumns = $columns;
        return $columns;
    }
    
    /**
     * Get events with filters
     * 
     * @param array $filters Filter options
     * @param int $limit Max results
     * @param int $offset Offset for pagination
     * @return array Events
     */
    public function getEvents(array $filters = [], int $limit = 100, int $offset = 0): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $params[] = $filters['event_type'];
        }
        
        if (!empty($filters['session_id'])) {
            $where[] = 'session_id = %s';
            $params[] = $filters['session_id'];
        }
        
        if (!empty($filters['agent_db_id'])) {
            $where[] = 'agent_db_id = %d';
            $params[] = $filters['agent_db_id'];
        }
        
        if (!empty($filters['provider'])) {
            $where[] = 'provider = %s';
            $params[] = $filters['provider'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['end_date'];
        }
        
        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$tables['events']} 
             WHERE {$whereClause} 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            ...$params
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get cost breakdown by provider
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param int|null $agentDbId Optional agent filter
     * @return array Cost by provider
     */
    public function getCostByProvider(string $startDate, string $endDate, ?int $agentDbId = null): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $agentFilter = $agentDbId ? $wpdb->prepare("AND agent_db_id = %d", $agentDbId) : '';
        
        $sql = $wpdb->prepare(
            "SELECT 
                provider,
                COUNT(*) as request_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(cost_usd) as total_cost
             FROM {$tables['events']}
             WHERE event_type = 'chat'
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
               {$agentFilter}
             GROUP BY provider
             ORDER BY total_cost DESC",
            $startDate,
            $endDate
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get cost breakdown by model
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Cost by model
     */
    public function getCostByModel(string $startDate, string $endDate): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $sql = $wpdb->prepare(
            "SELECT 
                provider,
                model,
                COUNT(*) as request_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(cache_read_tokens) as total_cache_read,
                SUM(cache_write_tokens) as total_cache_write,
                SUM(cost_usd) as total_cost,
                AVG(duration_ms) as avg_duration_ms
             FROM {$tables['events']}
             WHERE event_type = 'chat'
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
             GROUP BY provider, model
             ORDER BY total_cost DESC",
            $startDate,
            $endDate
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get tool usage statistics
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param int $limit Max tools to return
     * @return array Tool usage stats
     */
    public function getToolUsageStats(string $startDate, string $endDate, int $limit = 20): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $sql = $wpdb->prepare(
            "SELECT 
                tool_name,
                COUNT(*) as call_count,
                SUM(success) as success_count,
                COUNT(*) - SUM(success) as error_count,
                ROUND(SUM(success) / COUNT(*) * 100, 2) as success_rate,
                AVG(duration_ms) as avg_duration_ms
             FROM {$tables['events']}
             WHERE event_type = 'tool_call'
               AND tool_name IS NOT NULL
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
             GROUP BY tool_name
             ORDER BY call_count DESC
             LIMIT %d",
            $startDate,
            $endDate,
            $limit
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get skill usage statistics
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Skill usage stats
     */
    public function getSkillUsageStats(string $startDate, string $endDate): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $sql = $wpdb->prepare(
            "SELECT 
                skill_slug,
                COUNT(*) as activation_count,
                COUNT(DISTINCT session_id) as unique_sessions
             FROM {$tables['events']}
             WHERE event_type = 'skill_load'
               AND skill_slug IS NOT NULL
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
             GROUP BY skill_slug
             ORDER BY activation_count DESC",
            $startDate,
            $endDate
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get session statistics
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Session stats
     */
    public function getSessionStats(string $startDate, string $endDate): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT session_id) as total_sessions,
                COUNT(*) as total_requests,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT visitor_id) as unique_visitors,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(cost_usd) as total_cost,
                AVG(duration_ms) as avg_response_time
             FROM {$tables['events']}
             WHERE event_type = 'chat'
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)",
            $startDate,
            $endDate
        );
        
        return $wpdb->get_row($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get daily trends
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $metric Metric to trend (cost, sessions, tokens)
     * @return array Daily trend data
     */
    public function getDailyTrends(string $startDate, string $endDate, string $metric = 'cost'): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $metricSelect = match($metric) {
            'cost' => 'SUM(cost_usd) as value',
            'sessions' => 'COUNT(DISTINCT session_id) as value',
            'tokens' => 'SUM(input_tokens + output_tokens) as value',
            'requests' => 'COUNT(*) as value',
            default => 'SUM(cost_usd) as value',
        };
        
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                {$metricSelect}
             FROM {$tables['events']}
             WHERE event_type = 'chat'
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $startDate,
            $endDate
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get performance metrics
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Performance data
     */
    public function getPerformanceMetrics(string $startDate, string $endDate): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        // Get average and p95 response times
        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                AVG(duration_ms) as avg_response_time,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as error_count,
                ROUND(SUM(success) / COUNT(*) * 100, 2) as success_rate
             FROM {$tables['events']}
             WHERE event_type = 'chat'
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)",
            $startDate,
            $endDate
        );
        
        $stats = $wpdb->get_row($sql, ARRAY_A) ?: [];
        
        // Get P95 separately (MySQL doesn't have native percentile)
        $p95Sql = $wpdb->prepare(
            "SELECT duration_ms 
             FROM {$tables['events']}
             WHERE event_type = 'chat'
               AND duration_ms IS NOT NULL
               AND created_at >= %s
               AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
             ORDER BY duration_ms DESC
             LIMIT 1 OFFSET %d",
            $startDate,
            $endDate,
            (int) (($stats['total_requests'] ?? 0) * 0.05)
        );
        
        $p95 = $wpdb->get_var($p95Sql);
        $stats['p95_response_time'] = $p95 ? (int) $p95 : null;
        
        return $stats;
    }
    
    /**
     * Get agent comparison stats
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Agent comparison
     */
    public function getAgentComparison(string $startDate, string $endDate): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $agentsTable = $wpdb->prefix . 'swc_chat_agents';
        
        $sql = $wpdb->prepare(
            "SELECT 
                e.agent_db_id,
                COALESCE(a.name, CONCAT('Agent #', e.agent_db_id), 'Workspace') as agent_name,
                COUNT(DISTINCT e.session_id) as sessions,
                COUNT(*) as requests,
                SUM(e.input_tokens) as input_tokens,
                SUM(e.output_tokens) as output_tokens,
                SUM(e.cost_usd) as total_cost,
                AVG(e.duration_ms) as avg_response_time
             FROM {$tables['events']} e
             LEFT JOIN {$agentsTable} a ON e.agent_db_id = a.id
             WHERE e.event_type = 'chat'
               AND e.created_at >= %s
               AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
             GROUP BY e.agent_db_id, a.name
             ORDER BY total_cost DESC",
            $startDate,
            $endDate
        );
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    
    /**
     * Rollup daily stats from events
     * 
     * @param string $date Date to rollup (Y-m-d)
     * @return int Number of stats rows created/updated
     */
    public function rollupDailyStats(string $date): int {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $ratingsTable = $wpdb->prefix . 'swc_chat_ratings';
        
        // Get chat stats grouped by agent/provider/model
        $sql = $wpdb->prepare(
            "SELECT 
                %s as stat_date,
                agent_db_id,
                provider,
                model,
                COUNT(DISTINCT session_id) as total_sessions,
                COUNT(*) as total_messages,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(cache_read_tokens) as total_cache_read_tokens,
                SUM(cache_write_tokens) as total_cache_write_tokens,
                SUM(cost_usd) as total_cost_usd,
                AVG(duration_ms) as avg_response_time_ms,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as error_count,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT visitor_id) as unique_visitors
             FROM {$tables['events']}
             WHERE event_type = 'chat'
               AND DATE(created_at) = %s
             GROUP BY agent_db_id, provider, model",
            $date,
            $date
        );
        
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $count = 0;
        
        foreach ($rows as $row) {
            // Upsert into stats table
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$tables['stats']} 
                 (stat_date, agent_db_id, provider, model, total_sessions, total_messages,
                  total_input_tokens, total_output_tokens, total_cache_read_tokens, 
                  total_cache_write_tokens, total_cost_usd, avg_response_time_ms, error_count,
                  unique_users, unique_visitors)
                 VALUES (%s, %d, %s, %s, %d, %d, %d, %d, %d, %d, %f, %d, %d, %d, %d)
                 ON DUPLICATE KEY UPDATE
                  total_sessions = VALUES(total_sessions),
                  total_messages = VALUES(total_messages),
                  total_input_tokens = VALUES(total_input_tokens),
                  total_output_tokens = VALUES(total_output_tokens),
                  total_cache_read_tokens = VALUES(total_cache_read_tokens),
                  total_cache_write_tokens = VALUES(total_cache_write_tokens),
                  total_cost_usd = VALUES(total_cost_usd),
                  avg_response_time_ms = VALUES(avg_response_time_ms),
                  error_count = VALUES(error_count),
                  unique_users = VALUES(unique_users),
                  unique_visitors = VALUES(unique_visitors)",
                $row['stat_date'],
                $row['agent_db_id'] ?? 0,
                $row['provider'],
                $row['model'],
                $row['total_sessions'],
                $row['total_messages'],
                $row['total_input_tokens'],
                $row['total_output_tokens'],
                $row['total_cache_read_tokens'],
                $row['total_cache_write_tokens'],
                $row['total_cost_usd'],
                $row['avg_response_time_ms'],
                $row['error_count'],
                $row['unique_users'],
                $row['unique_visitors']
            ));
            $count++;
        }
        
        // Add tool call stats
        $toolSql = $wpdb->prepare(
            "SELECT agent_db_id, COUNT(*) as tool_calls
             FROM {$tables['events']}
             WHERE event_type = 'tool_call' AND DATE(created_at) = %s
             GROUP BY agent_db_id",
            $date
        );
        
        $toolRows = $wpdb->get_results($toolSql, ARRAY_A) ?: [];
        
        foreach ($toolRows as $row) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tables['stats']} 
                 SET total_tool_calls = %d 
                 WHERE stat_date = %s AND agent_db_id = %d",
                $row['tool_calls'],
                $date,
                $row['agent_db_id'] ?? 0
            ));
        }
        
        return $count;
    }
    
    /**
     * Get summary stats from rollup table
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Summary
     */
    public function getSummaryFromRollup(string $startDate, string $endDate): array {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $sql = $wpdb->prepare(
            "SELECT 
                SUM(total_sessions) as total_sessions,
                SUM(total_messages) as total_messages,
                SUM(total_tool_calls) as total_tool_calls,
                SUM(total_input_tokens) as total_input_tokens,
                SUM(total_output_tokens) as total_output_tokens,
                SUM(total_cost_usd) as total_cost,
                AVG(avg_response_time_ms) as avg_response_time,
                SUM(error_count) as total_errors,
                SUM(unique_users) as unique_users,
                SUM(unique_visitors) as unique_visitors
             FROM {$tables['stats']}
             WHERE stat_date >= %s AND stat_date <= %s",
            $startDate,
            $endDate
        );
        
        return $wpdb->get_row($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Cleanup old events (data retention)
     * 
     * @param int $daysToKeep Days of events to keep
     * @return int Number of events deleted
     */
    public function cleanupOldEvents(int $daysToKeep = 90): int {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['events']} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $daysToKeep
        ));
        
        return $deleted ?: 0;
    }
    /**
     * Recalculate costs for existing events with zero cost
     * 
     * This fixes historical events that were tracked before proper pricing was configured.
     * Also handles events where provider/model were not recorded.
     * 
     * @return int Number of events updated
     */
    public function recalculateCosts(): int {
        global $wpdb;
        
        $tables = AnalyticsSchema::getTableNames();
        $updated = 0;
        
        // Get default provider/model from settings for events without them
        $settings = get_option('swc_chatbot_settings', []);
        $defaultProvider = $settings['ai_provider'] ?? 'azure';
        $defaultModel = $settings['ai_model'] ?? 'gpt-5-mini';
        
        // Get ALL events with zero cost and tokens > 0
        $events = $wpdb->get_results(
            "SELECT id, provider, model, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens 
             FROM {$tables['events']} 
             WHERE event_type = 'chat' 
               AND cost_usd = 0 
               AND (input_tokens > 0 OR output_tokens > 0)
             ORDER BY id DESC
             LIMIT 1000",
            ARRAY_A
        );
        
        if (empty($events)) {
            return 0;
        }
        
        foreach ($events as $event) {
            // Use recorded provider/model or fall back to settings defaults
            $provider = !empty($event['provider']) ? $event['provider'] : $defaultProvider;
            $model = !empty($event['model']) ? $event['model'] : $defaultModel;
            
            // Calculate cost using the updated pricing
            $cost = AnalyticsService::calculateCost(
                $provider,
                $model,
                (int) $event['input_tokens'],
                (int) $event['output_tokens'],
                (int) ($event['cache_read_tokens'] ?? 0),
                (int) ($event['cache_write_tokens'] ?? 0)
            );
            
            if ($cost > 0) {
                // Update cost and also fill in provider/model if they were empty
                $updateData = ['cost_usd' => $cost];
                $updateFormats = ['%f'];
                
                if (empty($event['provider'])) {
                    $updateData['provider'] = $provider;
                    $updateFormats[] = '%s';
                }
                if (empty($event['model'])) {
                    $updateData['model'] = $model;
                    $updateFormats[] = '%s';
                }
                
                $wpdb->update(
                    $tables['events'],
                    $updateData,
                    ['id' => $event['id']],
                    $updateFormats,
                    ['%d']
                );
                $updated++;
            }
        }
        
        return $updated;
    }
}
