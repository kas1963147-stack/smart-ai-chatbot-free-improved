<?php
declare(strict_types=1);
/**
 * Abstract Repository
 *
 * Basic $wpdb-backed repository with minimal CRUD helpers.
 *
 * @package Quarksol\SmartChatbot\Foundation\Data
 */

namespace Quarksol\SmartChatbot\Foundation\Data;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractRepository implements RepositoryInterface
{
    protected string $tableName = '';
    protected string $primaryKey = 'id';
    protected array $columns = [];

    protected function getTable(): string
    {
        global $wpdb;

        if (str_starts_with($this->tableName, $wpdb->prefix)) {
            return $this->tableName;
        }

        return $wpdb->prefix . $this->tableName;
    }

    protected function filterColumns(array $data): array
    {
        if (empty($this->columns)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->columns));
    }

    public function find(int|string $id): ?array
    {
        global $wpdb;
        $table = $this->getTable();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE {$this->primaryKey} = %s", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function findAll(array $args = []): array
    {
        global $wpdb;
        $table = $this->getTable();
        $limit = isset($args['limit']) ? (int) $args['limit'] : 100;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} LIMIT %d OFFSET %d", $limit, $offset),
            ARRAY_A
        );

        return $rows ?: [];
    }

    public function create(array $data): int
    {
        global $wpdb;
        $table = $this->getTable();
        $data = $this->filterColumns($data);

        $result = $wpdb->insert($table, $data);
        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update(int|string $id, array $data): bool
    {
        global $wpdb;
        $table = $this->getTable();
        $data = $this->filterColumns($data);

        $result = $wpdb->update($table, $data, [$this->primaryKey => $id]);
        return $result !== false;
    }

    public function delete(int|string $id): bool
    {
        global $wpdb;
        $table = $this->getTable();

        $result = $wpdb->delete($table, [$this->primaryKey => $id]);
        return $result !== false;
    }
}
