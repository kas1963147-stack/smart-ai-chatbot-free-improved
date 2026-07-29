<?php
declare(strict_types=1);
/**
 * Skill Analytics Tracker
 * 
 * Track skill usage and performance metrics.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) { exit; }
if (!defined('ABSPATH')) { exit; }

/**
 * SkillAnalytics - Track and query skill metrics
 */
class SkillAnalytics
{
    /** Database table name */
    const TABLE_NAME = 'swc_skill_analytics';
    
    /** Event types */
    const EVENT_LOAD = 'load';
    const EVENT_SUCCESS = 'success';
    const EVENT_FAILURE = 'failure';
    const EVENT_TIMEOUT = 'timeout';
    
    /**
     * Create database table (run on activation)
     */
    public static function createTable(): void
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . self::TABLE_NAME;
        $charsetCollate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            skill_id VARCHAR(64) NOT NULL,
            agent_id VARCHAR(64) DEFAULT NULL,
            event_type VARCHAR(20) NOT NULL,
            conversation_id VARCHAR(64) DEFAULT NULL,
            tokens_used INT UNSIGNED DEFAULT 0,
            response_time_ms INT UNSIGNED DEFAULT 0,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_skill_date (skill_id, created_at),
            INDEX idx_agent (agent_id),
            INDEX idx_event_type (event_type)
        ) {$charsetCollate};";
        
        require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Track skill load event
     */
    public static function trackLoad(
        string $skillId,
        ?string $agentId = null,
        ?string $conversationId = null,
        array $metadata = []
    ): void {
        self::track($skillId, self::EVENT_LOAD, $agentId, $conversationId, 0, 0, $metadata);
    }
    
    /**
     * Track skill success event
     */
    public static function trackSuccess(
        string $skillId,
        ?string $agentId = null,
        ?string $conversationId = null,
        int $tokensUsed = 0,
        int $responseTimeMs = 0,
        array $metadata = []
    ): void {
        self::track($skillId, self::EVENT_SUCCESS, $agentId, $conversationId, $tokensUsed, $responseTimeMs, $metadata);
    }
    
    /**
     * Track skill failure event
     */
    public static function trackFailure(
        string $skillId,
        ?string $agentId = null,
        ?string $conversationId = null,
        array $metadata = []
    ): void {
        self::track($skillId, self::EVENT_FAILURE, $agentId, $conversationId, 0, 0, $metadata);
    }
    
    /**
     * Generic track method
     */
    protected static function track(
        string $skillId,
        string $eventType,
        ?string $agentId,
        ?string $conversationId,
        int $tokensUsed,
        int $responseTimeMs,
        array $metadata
    ): void {
        global $wpdb;
        
        $tableName = $wpdb->prefix . self::TABLE_NAME;
        
        $wpdb->insert($tableName, [
            'skill_id' => $skillId,
            'agent_id' => $agentId,
            'event_type' => $eventType,
            'conversation_id' => $conversationId,
            'tokens_used' => $tokensUsed,
            'response_time_ms' => $responseTimeMs,
            'metadata' => !empty($metadata) ? json_encode($metadata) : null,
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%s']);
    }
    
    /**
     * Get skill stats
     */
    public static function getStats(string $skillId, int $days = 30): array
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . self::TABLE_NAME;
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total loads
        $loads = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableName} 
             WHERE skill_id = %s AND event_type = %s AND created_at >= %s",
            $skillId, self::EVENT_LOAD, $since
        ));
        
        // Successes
        $successes = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableName} 
             WHERE skill_id = %s AND event_type = %s AND created_at >= %s",
            $skillId, self::EVENT_SUCCESS, $since
        ));
        
        // Failures
        $failures = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableName} 
             WHERE skill_id = %s AND event_type = %s AND created_at >= %s",
            $skillId, self::EVENT_FAILURE, $since
        ));
        
        // Average tokens
        $avgTokens = (float)$wpdb->get_var($wpdb->prepare(
            "SELECT AVG(tokens_used) FROM {$tableName} 
             WHERE skill_id = %s AND tokens_used > 0 AND created_at >= %s",
            $skillId, $since
        ));
        
        // Average response time
        $avgResponseTime = (float)$wpdb->get_var($wpdb->prepare(
            "SELECT AVG(response_time_ms) FROM {$tableName} 
             WHERE skill_id = %s AND response_time_ms > 0 AND created_at >= %s",
            $skillId, $since
        ));
        
        return [
            'loads' => $loads,
            'successes' => $successes,
            'failures' => $failures,
            'success_rate' => $loads > 0 ? round(($successes / $loads) * 100, 1) : 0,
            'avg_tokens' => round($avgTokens),
            'avg_response_time_ms' => round($avgResponseTime),
        ];
    }
    
    /**
     * Get top skills by usage
     */
    public static function getTopSkills(int $limit = 10, int $days = 30): array
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . self::TABLE_NAME;
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT skill_id, COUNT(*) as load_count,
                    SUM(CASE WHEN event_type = 'success' THEN 1 ELSE 0 END) as success_count
             FROM {$tableName}
             WHERE event_type IN ('load', 'success') AND created_at >= %s
             GROUP BY skill_id
             ORDER BY load_count DESC
             LIMIT %d",
            $since, $limit
        ), ARRAY_A);
        
        return array_map(function($row) {
            return [
                'skill_id' => $row['skill_id'],
                'loads' => (int)$row['load_count'],
                'successes' => (int)$row['success_count'],
            ];
        }, $results ?: []);
    }
    
    /**
     * Get usage over time
     */
    public static function getUsageTimeline(int $days = 30, ?string $skillId = null): array
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . self::TABLE_NAME;
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $whereSkill = $skillId ? $wpdb->prepare("AND skill_id = %s", $skillId) : '';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$tableName}
             WHERE event_type = 'load' AND created_at >= %s {$whereSkill}
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $since
        ), ARRAY_A);
        
        return array_map(function($row) {
            return [
                'date' => $row['date'],
                'count' => (int)$row['count'],
            ];
        }, $results ?: []);
    }
    
    /**
     * Cleanup old data
     */
    public static function cleanup(int $olderThanDays = 90): int
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . self::TABLE_NAME;
        $before = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tableName} WHERE created_at < %s",
            $before
        ));
    }
}


