<?php
declare(strict_types=1);
/**
 * ChatSession Model
 * 
 * Manages chat conversation sessions with message history.
 * Sessions are associated with a specific agent and user/visitor.
 * 
 * @package SWC\Models
 */

namespace Quarksol\SmartChatbot\Models;

use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatSession Model
 */
class ChatSession {
    
    // Status constants
    const STATUS_ACTIVE   = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_DELETED  = 'deleted';
    
    // Role constants
    const ROLE_USER      = 'user';
    const ROLE_ASSISTANT = 'assistant';
    const ROLE_SYSTEM    = 'system';
    
    /** Database ID */
    public int $id = 0;
    
    /** Session UUID */
    public string $sessionId = '';
    
    /** Agent database ID */
    public int $agentDbId = 0;
    
    /** WordPress user ID (null for guests) */
    public ?int $userId = null;
    
    /** Visitor ID for guests (localStorage-based) */
    public ?string $visitorId = null;
    
    /** Message history */
    public array $messages = [];
    
    /** Session metadata (page URL, user agent, etc.) */
    public array $metadata = [];
    
    /** Session status */
    public string $status = self::STATUS_ACTIVE;
    
    /** Started timestamp */
    public ?string $startedAt = null;
    
    /** Last message timestamp */
    public ?string $lastMessageAt = null;
    
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
        $this->userId = !empty($data['user_id']) ? (int) $data['user_id'] : null;
        $this->visitorId = $data['visitor_id'] ?? null;
        $this->status = $data['status'] ?? self::STATUS_ACTIVE;
        $this->startedAt = $data['started_at'] ?? null;
        $this->lastMessageAt = $data['last_message_at'] ?? null;
        
        // Parse JSON fields
        if (!empty($data['messages'])) {
            $this->messages = is_string($data['messages']) 
                ? json_decode($data['messages'], true) ?? []
                : (array) $data['messages'];
        }
        
        if (!empty($data['metadata'])) {
            $this->metadata = is_string($data['metadata'])
                ? json_decode($data['metadata'], true) ?? []
                : (array) $data['metadata'];
        }
    }
    
    /**
     * Generate a new session ID
     */
    public static function generateSessionId(): string {
        return wp_generate_uuid4();
    }
    
    /**
     * Find session by UUID
     */
    public static function find(string $sessionId): ?self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tables['sessions']} WHERE session_id = %s AND status = %s",
                $sessionId,
                self::STATUS_ACTIVE
            ),
            ARRAY_A
        );
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Find session by session UUID (alias for find)
     * @param string $sessionId Session UUID
     * @return self|null
     */
    public static function findBySessionId(string $sessionId): ?self {
        return self::find($sessionId);
    }
    
    /**
     * Find session by database ID
     */
    public static function findById(int $id): ?self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['sessions']} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Get sessions for a user/visitor
     */
    public static function forUser(?int $userId, ?string $visitorId = null): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        if ($userId) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['sessions']} 
                     WHERE user_id = %d AND status = %s 
                     ORDER BY last_message_at DESC",
                    $userId,
                    self::STATUS_ACTIVE
                ),
                ARRAY_A
            );
        } elseif ($visitorId) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['sessions']} 
                     WHERE visitor_id = %s AND status = %s 
                     ORDER BY last_message_at DESC",
                    $visitorId,
                    self::STATUS_ACTIVE
                ),
                ARRAY_A
            );
        } else {
            return [];
        }
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Get sessions for an agent
     */
    public static function forAgent(int $agentDbId, int $limit = 100): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['sessions']} 
                 WHERE agent_db_id = %d AND status = %s 
                 ORDER BY last_message_at DESC LIMIT %d",
                $agentDbId,
                self::STATUS_ACTIVE,
                $limit
            ),
            ARRAY_A
        );
        
        return array_map(fn($row) => new self($row), $rows ?: []);
    }
    
    /**
     * Find or create session for user/visitor with specific agent
     */
    public static function findOrCreate(
        int $agentDbId,
        ?int $userId = null,
        ?string $visitorId = null,
        array $metadata = []
    ): self {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        // Try to find existing active session
        if ($userId) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['sessions']} 
                     WHERE agent_db_id = %d AND user_id = %d AND status = %s 
                     ORDER BY last_message_at DESC LIMIT 1",
                    $agentDbId,
                    $userId,
                    self::STATUS_ACTIVE
                ),
                ARRAY_A
            );
        } elseif ($visitorId) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['sessions']} 
                     WHERE agent_db_id = %d AND visitor_id = %s AND status = %s 
                     ORDER BY last_message_at DESC LIMIT 1",
                    $agentDbId,
                    $visitorId,
                    self::STATUS_ACTIVE
                ),
                ARRAY_A
            );
        } else {
            $row = null;
        }
        
        if ($row) {
            return new self($row);
        }
        
        // Create new session
        $session = new self();
        $session->sessionId = self::generateSessionId();
        $session->agentDbId = $agentDbId;
        $session->userId = $userId;
        $session->visitorId = $visitorId;
        $session->metadata = $metadata;
        $session->save();
        
        return $session;
    }
    
    /**
     * Maximum messages per session
     */
    const MAX_MESSAGES = 100;
    
    /**
     * Add a message to the session
     */
    public function addMessage(string $role, string $content, array $extra = []): void {
        // Enforce message limit by removing oldest messages
        while (count($this->messages) >= self::MAX_MESSAGES) {
            array_shift($this->messages);
        }
        
        $messageId = !empty($extra['id']) ? $extra['id'] : ('msg_' . wp_generate_uuid4());
        $message = array_merge([
            'id' => $messageId,
            'role' => $role,
            'content' => $content,
            'timestamp' => current_time('mysql'),
        ], $extra);
        
        $this->messages[] = $message;
        $this->lastMessageAt = $message['timestamp'];
    }
    
    /**
     * Get messages
     */
    public function getMessages(): array {
        return $this->messages;
    }
    
    /**
     * Get messages formatted for Neuron AI ChatHistory
     */
    public function toChatHistory(): array {
        return array_map(function($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }, $this->messages);
    }
    
    /**
     * Clear messages (start fresh)
     */
    public function clearMessages(): void {
        $this->messages = [];
    }
    
    /**
     * Save session
     */
    public function save(): bool {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $data = [
            'session_id' => $this->sessionId,
            'agent_db_id' => $this->agentDbId,
            'user_id' => $this->userId,
            'visitor_id' => $this->visitorId,
            'messages' => wp_json_encode($this->messages),
            'metadata' => wp_json_encode($this->metadata),
            'status' => $this->status,
            'last_message_at' => $this->lastMessageAt,
        ];
        
        $format = ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s'];
        
        if ($this->id > 0) {
            $result = $wpdb->update($tables['sessions'], $data, ['id' => $this->id], $format, ['%d']);
            return $result !== false;
        } else {
            $result = $wpdb->insert($tables['sessions'], $data, $format);
            if ($result) {
                $this->id = $wpdb->insert_id;
                return true;
            }
            return false;
        }
    }
    
    /**
     * Archive session (soft delete)
     */
    public function archive(): bool {
        $this->status = self::STATUS_ARCHIVED;
        return $this->save();
    }
    
    /**
     * Get the agent for this session
     */
    public function getAgent(): ?ChatAgent {
        return ChatAgent::find($this->agentDbId);
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'session_id' => $this->sessionId,
            'agent_db_id' => $this->agentDbId,
            'user_id' => $this->userId,
            'visitor_id' => $this->visitorId,
            'messages' => $this->messages,
            'metadata' => $this->metadata,
            'status' => $this->status,
            'started_at' => $this->startedAt,
            'last_message_at' => $this->lastMessageAt,
            'message_count' => count($this->messages),
        ];
    }
}
