<?php
declare(strict_types=1);
/**
 * Module Lifecycle Interface
 *
 * Optional lifecycle hooks for enable/disable events.
 *
 * @package Quarksol\SmartChatbot\Modules
 */

namespace Quarksol\SmartChatbot\Modules;

if (!defined('ABSPATH')) {
    exit;
}

interface ModuleLifecycleInterface
{
    public function activate(): void;

    public function deactivate(): void;
}
