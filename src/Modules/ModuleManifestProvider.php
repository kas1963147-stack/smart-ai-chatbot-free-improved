<?php
declare(strict_types=1);
/**
 * Module Manifest Provider
 *
 * Optional interface for modules to expose richer metadata.
 *
 * @package Quarksol\SmartChatbot\Modules
 */

namespace Quarksol\SmartChatbot\Modules;

if (!defined('ABSPATH')) {
    exit;
}

interface ModuleManifestProvider
{
    public function getManifest(): ModuleManifest;
}
