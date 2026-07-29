<?php
declare(strict_types=1);
/**
 * Migration Registry
 *
 * Tracks all registered migrations for execution.
 *
 * @package Quarksol\SmartChatbot\Foundation\Migrations
 */

namespace Quarksol\SmartChatbot\Foundation\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrationRegistry
{
    /**
     * @var MigrationInterface[]
     */
    private static array $migrations = [];

    public static function register(MigrationInterface $migration): void
    {
        self::$migrations[$migration->getId()] = $migration;
    }

    /**
     * @return MigrationInterface[]
     */
    public static function all(): array
    {
        ksort(self::$migrations);
        return array_values(self::$migrations);
    }

    /**
     * Run pending migrations.
     */
    public static function runPending(): array
    {
        $runner = new MigrationRunner();
        return $runner->runPending(self::all());
    }
}
