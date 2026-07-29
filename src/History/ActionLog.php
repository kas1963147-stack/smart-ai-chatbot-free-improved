<?php
declare(strict_types=1);
/**
 * ActionLog Model
 * 
 * Model for querying and managing action log entries.
 * Represents tool execution history stored in swc_action_log table.
 * 
 * @package Quarksol\SmartChatbot\History
 */

namespace Quarksol\SmartChatbot\History;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ActionLog Model
 */
class ActionLog {
    
    /** Database ID */
    public int $id = 0;
    
    /** Session ID this action belongs to */
    public string $sessionId = '';
    
    /** Message index within session */
    public ?int $messageIndex = null;

    /** Message ID within session */
    public ?string $messageId = null;
    
    /** Unique action ID */
    public string $actionId = '';
    
    /** Action type (tool_call, etc.) */
    public string $actionType = 'tool_call';
    
    /** Tool name */
    public string $toolName = '';
    
    /** Tool description */
    public ?string $toolDescription = null;
    
    /** Input parameters (JSON) */
    public ?string $inputs = null;
    
    /** Output/result (JSON) */
    public ?string $outputs = null;
    
    /** Execution duration in milliseconds */
    public ?int $durationMs = null;
    
    /** Whether execution succeeded */
    public bool $success = true;
    
    /** Error message if failed */
    public ?string $errorMessage = null;
    
    /** Agent database ID */
    public ?int $agentDbId = null;
    
    /** User ID */
    public ?int $userId = null;
    
    /** When execution started */
    public ?string $startedAt = null;
    
    /** When execution completed */
    public ?string $completedAt = null;
    
    /** When record was created */
    public ?string $createdAt = null;
    
    /**
     * Get table name
     */
    public static function getTableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'swc_action_log';
    }
    
    /**
     * Hydrate from database row
     */
    public static function fromRow(array $row): self {
        $log = new self();
        $log->id = (int) ($row['id'] ?? 0);
        $log->sessionId = $row['session_id'] ?? '';
        $log->messageIndex = isset($row['message_index']) ? (int) $row['message_index'] : null;
        $log->messageId = $row['message_id'] ?? null;
        $log->actionId = $row['action_id'] ?? '';
        $log->actionType = $row['action_type'] ?? 'tool_call';
        $log->toolName = $row['tool_name'] ?? '';
        $log->toolDescription = $row['tool_description'] ?? null;
        $log->inputs = $row['inputs'] ?? null;
        $log->outputs = $row['outputs'] ?? null;
        $log->durationMs = isset($row['duration_ms']) ? (int) $row['duration_ms'] : null;
        $log->success = (bool) ($row['success'] ?? true);
        $log->errorMessage = $row['error_message'] ?? null;
        $log->agentDbId = isset($row['agent_db_id']) ? (int) $row['agent_db_id'] : null;
        $log->userId = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $log->startedAt = $row['started_at'] ?? null;
        $log->completedAt = $row['completed_at'] ?? null;
        $log->createdAt = $row['created_at'] ?? null;
        return $log;
    }
    
    /**
     * Find by action ID
     */
    public static function findByActionId(string $actionId): ?self {
        global $wpdb;
        $table = self::getTableName();
        
        $suppress = $wpdb->suppress_errors(true);
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE action_id = %s", $actionId),
            ARRAY_A
        );
        $wpdb->suppress_errors($suppress);
        
        return $row ? self::fromRow($row) : null;
    }
    
    /**
     * Get actions for a session
     */
    public static function forSession(string $sessionId, int $limit = 500): array {
        global $wpdb;
        $table = self::getTableName();
        
        $suppress = $wpdb->suppress_errors(true);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE session_id = %s 
                 ORDER BY started_at ASC 
                 LIMIT %d",
                $sessionId,
                $limit
            ),
            ARRAY_A
        );
        $wpdb->suppress_errors($suppress);
        
        return array_map([self::class, 'fromRow'], $rows ?: []);
    }
    
    /**
     * Get actions for a specific message
     */
    public static function forMessage(string $sessionId, int $messageIndex): array {
        global $wpdb;
        $table = self::getTableName();
        
        $suppress = $wpdb->suppress_errors(true);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE session_id = %s AND message_index = %d 
                 ORDER BY started_at ASC",
                $sessionId,
                $messageIndex
            ),
            ARRAY_A
        );
        $wpdb->suppress_errors($suppress);
        
        return array_map([self::class, 'fromRow'], $rows ?: []);
    }
    
    /**
     * Get recent actions across all sessions
     */
    public static function getRecent(int $limit = 50, ?int $agentDbId = null): array {
        global $wpdb;
        $table = self::getTableName();
        
        $sql = "SELECT * FROM {$table}";
        $params = [];
        
        if ($agentDbId !== null) {
            $sql .= " WHERE agent_db_id = %d";
            $params[] = $agentDbId;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        $suppress = $wpdb->suppress_errors(true);
        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, ...$params),
            ARRAY_A
        );
        $wpdb->suppress_errors($suppress);
        
        return array_map([self::class, 'fromRow'], $rows ?: []);
    }
    
    /**
     * Get tool usage statistics
     */
    public static function getToolStats(string $period = '30d', ?int $agentDbId = null): array {
        global $wpdb;
        $table = self::getTableName();
        
        $dateFilter = self::getPeriodDateFilter($period);
        
        $sql = "SELECT 
                    tool_name,
                    COUNT(*) as call_count,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failure_count,
                    AVG(duration_ms) as avg_duration_ms,
                    MAX(duration_ms) as max_duration_ms
                FROM {$table}
                WHERE {$dateFilter}";
        
        $params = [];
        
        if ($agentDbId !== null) {
            $sql .= " AND agent_db_id = %d";
            $params[] = $agentDbId;
        }
        
        $sql .= " GROUP BY tool_name ORDER BY call_count DESC";
        
        $suppress = $wpdb->suppress_errors(true);
        if (!empty($params)) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }
        $wpdb->suppress_errors($suppress);
        
        return $rows ?: [];
    }
    
    /**
     * Delete actions for a session
     */
    public static function deleteForSession(string $sessionId): int {
        global $wpdb;
        $table = self::getTableName();
        
        $suppress = $wpdb->suppress_errors(true);
        $result = (int) $wpdb->delete($table, ['session_id' => $sessionId], ['%s']);
        $wpdb->suppress_errors($suppress);
        
        return $result;
    }
    
    /**
     * Cleanup old actions
     */
    public static function cleanup(int $daysToKeep = 90): int {
        global $wpdb;
        $table = self::getTableName();
        
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $suppress = $wpdb->suppress_errors(true);
        $result = (int) $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $cutoff)
        );
        $wpdb->suppress_errors($suppress);
        
        return $result;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'session_id' => $this->sessionId,
            'message_index' => $this->messageIndex,
            'message_id' => $this->messageId,
            'action_id' => $this->actionId,
            'action_type' => $this->actionType,
            'tool_name' => $this->toolName,
            'tool_description' => $this->toolDescription,
            'inputs' => $this->inputs ? json_decode($this->inputs, true) : null,
            'outputs' => $this->outputs ? json_decode($this->outputs, true) : null,
            'duration_ms' => $this->durationMs,
            'success' => $this->success,
            'error_message' => $this->errorMessage,
            'agent_db_id' => $this->agentDbId,
            'user_id' => $this->userId,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'created_at' => $this->createdAt,
        ];
    }
    
    /**
     * Get date filter for period
     */
    private static function getPeriodDateFilter(string $period): string {
        switch ($period) {
            case 'today':
                return "DATE(created_at) = CURDATE()";
            case '7d':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30d':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90d':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            default:
                return "1=1";
        }
    }
}
