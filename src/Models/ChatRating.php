<?php
declare(strict_types=1);
/**
 * ChatRating Model
 * 
 * Manages conversation ratings - both message-level (thumbs up/down)
 * and session-level (stars + comment).
 * 
 * @package SWC\Models
 */

namespace Quarksol\SmartChatbot\Models;

use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatRating Model
 */
class ChatRating {
    
    // Rating type constants
    const TYPE_UP      = 'up';      // Thumbs up (message-level)
    const TYPE_DOWN    = 'down';    // Thumbs down (message-level)
    const TYPE_STARS   = 'stars';   // Star rating (session-level)
    
    /** Database ID */
    public int $id = 0;
    
    /** Session UUID */
    public string $sessionId = '';
    
    /** Agent database ID */
    public int $agentDbId = 0;
    
    /** Message index (null for session-level ratings) */
    public ?int $messageIndex = null;
    
    /** Rating type */
    public string $ratingType = self::TYPE_STARS;
    
    /** Rating value (1-5 for stars, 1 for up, -1 for down) */
    public ?int $ratingValue = null;
    
    /** Optional comment */
    public ?string $comment = null;
    
    /** User ID (null for guests) */
    public ?int $userId = null;
    
    /** Visitor ID for guests */
    public ?string $visitorId = null;
    
    /** Created timestamp */
    public ?string $createdAt = null;
    
    /**
     * Constructor
     */
    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    /**
     * Hydrate from database row
     */
    protected function hydrate(array $data): void {
        $this->id = (int) ($data['id'] ?? 0);
        $this->sessionId = $data['session_id'] ?? '';
        $this->agentDbId = (int) ($data['agent_db_id'] ?? 0);
        $this->messageIndex = isset($data['message_index']) ? (int) $data['message_index'] : null;
        $this->ratingType = $data['rating_type'] ?? self::TYPE_STARS;
        $this->ratingValue = isset($data['rating_value']) ? (int) $data['rating_value'] : null;
        $this->comment = $data['comment'] ?? null;
        $this->userId = !empty($data['user_id']) ? (int) $data['user_id'] : null;
        $this->visitorId = $data['visitor_id'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
    }
    
    /**
     * Find rating by ID
     */
    public static function find(int $id): ?self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['ratings']} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get ratings for a session
     */
    public static function forSession(string $sessionId): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['ratings']} WHERE session_id = %s ORDER BY created_at ASC",
                $sessionId
            ),
            ARRAY_A
        );
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Get ratings for an agent
     */
    public static function forAgent(int $agentDbId, int $limit = 100): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['ratings']} WHERE agent_db_id = %d ORDER BY created_at DESC LIMIT %d",
                $agentDbId,
                $limit
            ),
            ARRAY_A
        );
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Get agent rating summary
     */
    public static function getAgentSummary(int $agentDbId): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        // Get star ratings average
        $starAvg = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(rating_value) FROM {$tables['ratings']} 
                 WHERE agent_db_id = %d AND rating_type = %s",
                $agentDbId,
                self::TYPE_STARS
            )
        );
        
        // Get thumbs up/down counts
        $thumbsUp = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['ratings']} 
                 WHERE agent_db_id = %d AND rating_type = %s",
                $agentDbId,
                self::TYPE_UP
            )
        );
        
        $thumbsDown = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['ratings']} 
                 WHERE agent_db_id = %d AND rating_type = %s",
                $agentDbId,
                self::TYPE_DOWN
            )
        );
        
        // Total ratings count
        $totalRatings = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['ratings']} WHERE agent_db_id = %d",
                $agentDbId
            )
        );
        
        return [
            'average_stars' => $starAvg ? round((float) $starAvg, 2) : null,
            'thumbs_up' => (int) $thumbsUp,
            'thumbs_down' => (int) $thumbsDown,
            'total_ratings' => (int) $totalRatings,
            'satisfaction_rate' => ($thumbsUp + $thumbsDown) > 0 
                ? round($thumbsUp / ($thumbsUp + $thumbsDown) * 100, 1) 
                : null
        ];
    }
    
    /**
     * Check if message already rated
     */
    public static function isMessageRated(string $sessionId, int $messageIndex): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['ratings']} 
                 WHERE session_id = %s AND message_index = %d",
                $sessionId,
                $messageIndex
            )
        );
        
        return (int) $count > 0;
    }
    
    /**
     * Check if session already has star rating
     */
    public static function hasSessionRating(string $sessionId): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['ratings']} 
                 WHERE session_id = %s AND rating_type = %s",
                $sessionId,
                self::TYPE_STARS
            )
        );
        
        return (int) $count > 0;
    }
    
    /**
     * Save rating
     */
    public function save(): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $data = [
            'session_id' => $this->sessionId,
            'agent_db_id' => $this->agentDbId,
            'message_index' => $this->messageIndex,
            'rating_type' => $this->ratingType,
            'rating_value' => $this->ratingValue,
            'comment' => $this->comment,
            'user_id' => $this->userId,
            'visitor_id' => $this->visitorId,
        ];
        
        $format = ['%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s'];
        
        if ($this->id > 0) {
            $result = $wpdb->update($tables['ratings'], $data, ['id' => $this->id], $format, ['%d']);
            return $result !== false;
        } else {
            $result = $wpdb->insert($tables['ratings'], $data, $format);
            if ($result) {
                $this->id = $wpdb->insert_id;
                return true;
            }
            return false;
        }
    }
    
    /**
     * Delete rating
     */
    public function delete(): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        if ($this->id <= 0) {
            return false;
        }
        
        return $wpdb->delete($tables['ratings'], ['id' => $this->id], ['%d']) !== false;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'session_id' => $this->sessionId,
            'agent_db_id' => $this->agentDbId,
            'message_index' => $this->messageIndex,
            'rating_type' => $this->ratingType,
            'rating_value' => $this->ratingValue,
            'comment' => $this->comment,
            'created_at' => $this->createdAt,
        ];
    }
}
