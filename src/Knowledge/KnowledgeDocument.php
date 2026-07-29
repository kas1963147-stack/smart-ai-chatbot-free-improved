<?php
declare(strict_types=1);
/**
 * Knowledge Document Model
 * 
 * Represents a single unified document in the simplified knowledge base.
 * Provides basic CRUD and text search capabilities.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Document
 */
class KnowledgeDocument {
    
    /** @var int Database ID */
    public int $id = 0;
    
    /** @var string Document title (used by AI to select document) */
    public string $title = '';
    
    /** @var string Short description injected into AI prompt (< 30 words) */
    public string $description = '';
    
    /** @var string Full markdown content of the document */
    public string $content = '';
    
    /** @var string Document category */
    public string $category = 'general';
    
    /** @var bool Document active status */
    public bool $isActive = true;
    
    /** @var string Document type (manual, pdf, url) */
    public string $type = 'manual';
    
    /** @var string Original URL or file name (metadata) */
    public string $sourceUrl = '';
    
    /** @var int Content length in characters (used for metadata without loading full content) */
    public int $contentLength = 0;
    
    /** @var string Created timestamp */
    public string $createdAt = '';
    
    /** @var ?string Updated timestamp */
    public ?string $updatedAt = null;
    
    /**
     * Get table name
     */
    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'swc_knowledge_documents';
    }
    
    /**
     * Find by ID
     */
    public static function find(int $id): ?self {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::tableName() . " WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        
        return $row ? self::fromRow($row) : null;
    }
    
    /**
     * Find by title (exact match)
     */
    public static function findByTitle(string $title): ?self {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::tableName() . " WHERE title = %s",
                $title
            ),
            ARRAY_A
        );
        
        return $row ? self::fromRow($row) : null;
    }
    
    /**
     * Get all documents (usually just basic metadata for prompt injection)
     * @param bool $content Include full content (default: false to save memory)
     */
    public static function all(bool $content = false): array {
        global $wpdb;
        
        $fields = $content ? '*' : 'id, title, description, category, is_active, type, source_url, created_at, updated_at, LENGTH(content) as content_length';
        $rows = $wpdb->get_results("SELECT {$fields} FROM " . self::tableName() . " ORDER BY title ASC", ARRAY_A);
        
        return array_map([self::class, 'fromRow'], $rows ?: []);
    }
    
    /**
     * Simple keyword search across document content
     */
    public static function search(string $query, int $limit = 5): array {
        global $wpdb;
        $table = self::tableName();
        
        $terms = array_filter(explode(' ', $query));
        if (empty($terms)) return [];

        $where = [];
        $params = [];
        
        foreach ($terms as $term) {
            // Search title, desc, and content
            $where[] = "(title LIKE %s OR description LIKE %s OR content LIKE %s)";
            $likeStr = '%' . $wpdb->esc_like($term) . '%';
            $params[] = $likeStr;
            $params[] = $likeStr;
            $params[] = $likeStr;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $params[] = $limit;
        
        $sql = $wpdb->prepare(
            "SELECT id, title, description, category, is_active, type, source_url FROM {$table} {$whereClause} LIMIT %d",
            ...$params
        );
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return array_map([self::class, 'fromRow'], $rows ?: []);
    }
    
    /**
     * Create from database row
     */
    public static function fromRow(array $row): self {
        $doc = new self();
        $doc->id = (int) ($row['id'] ?? 0);
        $doc->title = $row['title'] ?? '';
        $doc->description = $row['description'] ?? '';
        $doc->content = $row['content'] ?? '';
        $doc->category = $row['category'] ?? 'general';
        $doc->isActive = (bool) ($row['is_active'] ?? true);
        $doc->type = $row['type'] ?? 'manual';
        $doc->sourceUrl = $row['source_url'] ?? '';
        $doc->contentLength = (int) ($row['content_length'] ?? 0);
        $doc->createdAt = $row['created_at'] ?? '';
        $doc->updatedAt = $row['updated_at'] ?? null;
        
        return $doc;
    }
    
    /**
     * Save document to database
     */
    public function save(): bool {
        global $wpdb;
        
        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'category' => $this->category,
            'is_active' => $this->isActive ? 1 : 0,
            'type' => $this->type,
            'source_url' => $this->sourceUrl,
            'updated_at' => current_time('mysql'),
        ];
        
        if ($this->id > 0) {
            $result = $wpdb->update(
                self::tableName(),
                $data,
                ['id' => $this->id]
            );
            return $result !== false;
        }
        
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert(self::tableName(), $data);
        
        if ($result) {
            $this->id = $wpdb->insert_id;
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete document
     */
    public function delete(): bool {
        if ($this->id === 0) {
            return false;
        }
        
        global $wpdb;
        return $wpdb->delete(self::tableName(), ['id' => $this->id]) !== false;
    }
    
    /**
     * Convert to array for API
     */
    public function toArray(bool $includeContent = true): array {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'is_active' => $this->isActive,
            'type' => $this->type,
            'source_url' => $this->sourceUrl,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
        
        if ($includeContent) {
            $data['content'] = $this->content;
        } else {
            $data['content_length'] = $this->contentLength;
        }
        
        return $data;
    }
}
