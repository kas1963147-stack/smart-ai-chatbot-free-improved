<?php
declare(strict_types=1);
/**
 * Module Manifest
 *
 * Captures module metadata, dependencies, and versioning.
 *
 * @package Quarksol\SmartChatbot\Modules
 */

namespace Quarksol\SmartChatbot\Modules;

if (!defined('ABSPATH')) {
    exit;
}

class ModuleManifest
{
    public string $slug;
    public string $name;
    public string $description;
    public string $version;
    public string $icon;
    public array $requires;
    public array $provides;
    public bool $core;

    public function __construct(array $data)
    {
        $this->slug = (string) ($data['slug'] ?? '');
        $this->name = (string) ($data['name'] ?? '');
        $this->description = (string) ($data['description'] ?? '');
        $this->version = (string) ($data['version'] ?? '0.0.0');
        $this->icon = (string) ($data['icon'] ?? '');
        $this->requires = is_array($data['requires'] ?? null) ? $data['requires'] : [];
        $this->provides = is_array($data['provides'] ?? null) ? $data['provides'] : [];
        $this->core = (bool) ($data['core'] ?? false);
    }

    public static function fromModule(ModuleInterface $module, array $overrides = []): self
    {
        $version = defined('SWC_CHATBOT_VERSION') ? SWC_CHATBOT_VERSION : '0.0.0';

        return new self(array_merge([
            'slug' => $module->getSlug(),
            'name' => $module->getName(),
            'description' => $module->getDescription(),
            'version' => $version,
            'icon' => $module->getIcon(),
            'requires' => [],
            'provides' => [],
            'core' => false,
        ], $overrides));
    }

    /**
     * Get module dependency constraints (slug => constraint).
     */
    public function getRequiredModules(): array
    {
        $modules = $this->requires['modules'] ?? $this->requires;
        return is_array($modules) ? $modules : [];
    }
}
