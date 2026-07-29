<?php
declare(strict_types=1);
/**
 * Knowledge Source Model
 * 
 * Represents a knowledge source in the system.
 * Maps to the swc_knowledge_documents table, treating each document
 * as a source for backward compatibility.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

class KnowledgeSource
{
    /** @var int */
    public int $id = 0;

    /** @var string Source name */
    public string $name = '';

    /** @var string Source type (manual, pdf, url, folder) */
    public string $sourceType = 'manual';

    /** @var string Description */
    public string $description = '';

    /** @var array Configuration data */
    public array $config = [];

    /** @var bool Whether this source is active */
    public bool $isActive = true;

    /** @var string Created timestamp */
    public string $createdAt = '';

    /** @var ?string Updated timestamp */
    public ?string $updatedAt = null;

    /**
     * Get table name — uses the existing knowledge_documents table
     */
    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'swc_knowledge_documents';
    }

    /**
     * Get all knowledge sources
     * Maps existing documents as sources
     * 
     * @return self[]
     */
    public static function all(): array
    {
        global $wpdb;
        $table = self::tableName();

        // Check if table exists before querying
        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );
        if (!$tableExists) {
            return [];
        }

        $rows = $wpdb->get_results(
            "SELECT id, title, description, type, source_url, created_at, updated_at FROM {$table} ORDER BY title ASC",
            ARRAY_A
        );

        return array_map([self::class, 'fromRow'], $rows ?: []);
    }

    /**
     * Get active sources only
     * 
     * @return self[]
     */
    public static function active(): array
    {
        // All documents are considered active in the current schema
        return self::all();
    }

    /**
     * Find by ID
     */
    public static function find(int $id): ?self
    {
        global $wpdb;
        $table = self::tableName();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Create from database row
     */
    public static function fromRow(array $row): self
    {
        $source = new self();
        $source->id = (int) ($row['id'] ?? 0);
        $source->name = $row['title'] ?? '';
        $source->description = $row['description'] ?? '';
        $source->sourceType = $row['type'] ?? 'manual';
        $source->isActive = true;
        $source->createdAt = $row['created_at'] ?? '';
        $source->updatedAt = $row['updated_at'] ?? null;

        return $source;
    }

    /**
     * Save source to database
     */
    public function save(): bool
    {
        global $wpdb;
        $table = self::tableName();

        $data = [
            'title'       => $this->name,
            'description' => $this->description,
            'type'        => $this->sourceType,
            'updated_at'  => current_time('mysql'),
        ];

        if ($this->id > 0) {
            return $wpdb->update($table, $data, ['id' => $this->id]) !== false;
        }

        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);

        if ($result) {
            $this->id = $wpdb->insert_id;
            return true;
        }

        return false;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'source_type' => $this->sourceType,
            'is_active'   => $this->isActive,
            'config'      => $this->config,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }

    /**
     * Get folder path for this source's files
     * Uses wp-content/uploads/swc-knowledge/<source_id>/
     */
    public function getFolderPath(): string
    {
        $uploadDir = wp_upload_dir();
        $basePath = $uploadDir['basedir'] . '/swc-knowledge/' . $this->id;

        if (!is_dir($basePath)) {
            wp_mkdir_p($basePath);
        }

        return $basePath;
    }

    /**
     * Update document count (no-op for current schema)
     */
    public function updateDocCount(): void
    {
        // In current schema, each document IS a source
        // No separate doc count needed
    }
}
