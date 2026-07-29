<?php
declare(strict_types=1);
/**
 * Migration Interface
 *
 * Defines database migration behavior.
 *
 * @package Quarksol\SmartChatbot\Foundation\Migrations
 */

namespace Quarksol\SmartChatbot\Foundation\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

interface MigrationInterface
{
    public function getId(): string;

    public function up(\wpdb $db): void;

    public function down(\wpdb $db): void;
}
