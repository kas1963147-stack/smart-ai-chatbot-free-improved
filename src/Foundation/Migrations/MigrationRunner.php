<?php
declare(strict_types=1);
/**
 * Migration Runner
 *
 * Applies pending migrations and records execution state.
 *
 * @package Quarksol\SmartChatbot\Foundation\Migrations
 */

namespace Quarksol\SmartChatbot\Foundation\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrationRunner
{
    private const OPTION_KEY = 'swc_migrations';

    /**
     * @param MigrationInterface[] $migrations
     * @return array<string, string> Results keyed by migration id
     */
    public function runPending(array $migrations): array
    {
        global $wpdb;
        $results = [];
        $applied = $this->getApplied();

        foreach ($migrations as $migration) {
            $id = $migration->getId();
            if (isset($applied[$id])) {
                continue;
            }

            try {
                $migration->up($wpdb);
                $applied[$id] = gmdate('c');
                $results[$id] = 'applied';
            } catch (\Throwable $e) {
                $results[$id] = 'failed: ' . $e->getMessage();
                break;
            }
        }

        $this->persistApplied($applied);

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function getApplied(): array
    {
        $applied = get_option(self::OPTION_KEY, []);
        return is_array($applied) ? $applied : [];
    }

    private function persistApplied(array $applied): void
    {
        update_option(self::OPTION_KEY, $applied, false);
    }
}
