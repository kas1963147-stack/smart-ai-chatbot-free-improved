<?php
declare(strict_types=1);
/**
 * Repository Interface
 *
 * Defines common CRUD operations for data stores.
 *
 * @package Quarksol\SmartChatbot\Foundation\Data
 */

namespace Quarksol\SmartChatbot\Foundation\Data;

if (!defined('ABSPATH')) {
    exit;
}

interface RepositoryInterface
{
    public function find(int|string $id): ?array;

    public function findAll(array $args = []): array;

    public function create(array $data): int;

    public function update(int|string $id, array $data): bool;

    public function delete(int|string $id): bool;
}
