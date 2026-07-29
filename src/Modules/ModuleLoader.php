<?php
declare(strict_types=1);


/**
 * Module Loader
 * 
 * Auto-detects available plugins and loads corresponding modules.
 * Enhanced to support admin-controlled enable/disable toggles.
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automatically loads modules based on available plugins
 * 
 * Supports:
 * - Admin-controlled enable/disable via WordPress options
 * - Core modules that cannot be disabled
 * - REST API for module management
 */
class ModuleLoader {
    
    /** WordPress option key for enabled modules */
    private const ENABLED_OPTION = 'swc_enabled_modules';
    
    /** Registered module classes */
    private array $moduleClasses = [];
    
    /** Loaded module instances */
    private array $modules = [];

    /** Module manifests */
    private array $moduleManifests = [];

    /** Cached module instances (pre-boot) */
    private array $moduleCache = [];

    /** Modules skipped during load with reasons */
    private array $skippedModules = [];
    
    /** Plugin detection map: module_slug => class_to_check */
    private array $detectors = [
        'woocommerce' => 'WooCommerce',
        'contact_form_7' => 'WPCF7',
        'gravity_forms' => 'GFAPI',
        'elementor' => 'Elementor\\Plugin',
        'acf' => 'ACF',
        'wpforms' => 'WPForms',
        'yoast' => 'WPSEO_Options',
    ];
    
    /** Core modules that cannot be disabled */
    private array $coreModules = ['wordpress'];
    
    /** Singleton instance */
    private static ?self $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register a module class
     */
    public function register(string $slug, string $moduleClass): self {
        if (!is_subclass_of($moduleClass, ModuleInterface::class)) {
            throw new \InvalidArgumentException(
                "Module class must implement ModuleInterface: $moduleClass"
            );
        }
        $this->moduleClasses[$slug] = $moduleClass;
        unset($this->moduleManifests[$slug], $this->moduleCache[$slug]);
        return $this;
    }
    
    /**
     * Detect and load available modules
     */
    public function detectAndLoad(): array {
        $this->skippedModules = [];
        $candidates = [];

        foreach ($this->moduleClasses as $slug => $class) {
            if (!$this->canLoad($slug)) {
                $this->skippedModules[$slug] = 'missing_dependency';
                continue;
            }

            if (!$this->isEnabled($slug)) {
                continue;
            }

            $module = $this->moduleCache[$slug] ?? new $class();
            $this->moduleCache[$slug] = $module;

            if (!$module->isAvailable()) {
                $this->skippedModules[$slug] = 'not_available';
                continue;
            }

            $this->moduleManifests[$slug] = $this->getManifest($slug, $module);
            $candidates[$slug] = $module;
        }

        $loadOrder = $this->resolveLoadOrder(array_keys($candidates));

        foreach ($loadOrder as $slug) {
            if (!isset($candidates[$slug])) {
                continue;
            }

            if (!$this->dependenciesSatisfied($slug)) {
                continue;
            }

            $module = $candidates[$slug];
            $this->modules[$slug] = $module;
            $module->boot();
        }

        return $this->modules;
    }
    
    /**
     * Check if a module's dependencies are available
     */
    private function canLoad(string $slug): bool {
        // If we have a detector for this module, check if class exists
        if (isset($this->detectors[$slug])) {
            return class_exists($this->detectors[$slug]);
        }
        
        // No detector = always loadable (core modules)
        return true;
    }
    
    /**
     * Check if a module is enabled (via admin settings)
     */
    public function isEnabled(string $slug): bool {
        // Core modules are always enabled
        if (in_array($slug, $this->coreModules, true)) {
            return true;
        }
        
        $enabled = get_option(self::ENABLED_OPTION, []);
        
        // Default: enable all available modules on first run
        if (empty($enabled)) {
            return true;
        }
        
        return in_array($slug, $enabled, true);
    }
    
    /**
     * Enable or disable a module
     */
    public function setModuleEnabled(string $slug, bool $enabled): bool {
        // Cannot disable core modules
        if (in_array($slug, $this->coreModules, true)) {
            return false;
        }
        
        // Check if module exists
        if (!isset($this->moduleClasses[$slug])) {
            return false;
        }
        
        $enabledModules = get_option(self::ENABLED_OPTION, []);
        
        if ($enabled && !in_array($slug, $enabledModules, true)) {
            $enabledModules[] = $slug;
        } elseif (!$enabled) {
            $enabledModules = array_filter($enabledModules, fn($m) => $m !== $slug);
        }
        
        update_option(self::ENABLED_OPTION, array_values($enabledModules));

        $module = $this->modules[$slug] ?? $this->moduleCache[$slug] ?? null;
        if ($module instanceof ModuleLifecycleInterface) {
            try {
                if ($enabled) {
                    $module->activate();
                } else {
                    $module->deactivate();
                }
            } catch (\Throwable $e) {
                // Keep enable/disable changes even if hook fails.
            }
        }

        return true;
    }
    
    /**
     * Get all loaded modules
     */
    public function getModules(): array {
        return $this->modules;
    }
    
    /**
     * Get a specific module
     */
    public function getModule(string $slug): ?ModuleInterface {
        return $this->modules[$slug] ?? null;
    }
    
    /**
     * Check if a module is loaded
     */
    public function hasModule(string $slug): bool {
        return isset($this->modules[$slug]);
    }
    
    /**
     * Get all tools from all loaded modules
     */
    public function getAllTools(): array {
        $tools = [];
        foreach ($this->modules as $module) {
            $tools = array_merge($tools, $module->getTools());
        }
        return $tools;
    }
    
    /**
     * Get all skills from all loaded modules
     */
    public function getAllSkills(): array {
        $skills = [];
        foreach ($this->modules as $module) {
            $skills = array_merge($skills, $module->getSkills());
        }
        return array_unique($skills);
    }
    
    /**
     * Get combined prompt additions from all modules
     */
    public function getPromptAdditions(): string {
        $additions = [];
        foreach ($this->modules as $module) {
            $addition = $module->getPromptAdditions();
            if (!empty($addition)) {
                $additions[] = "## {$module->getName()} Capabilities\n{$addition}";
            }
        }
        return implode("\n\n", $additions);
    }
    
    /**
     * Get module status for admin display
     * Returns ALL registered modules with availability and enabled status
     */
    public function getModuleStatus(): array {
        $status = [];
        
        foreach ($this->moduleClasses as $slug => $class) {
            $available = $this->canLoad($slug);
            $enabled = $this->isEnabled($slug);
            $loaded = isset($this->modules[$slug]);
            $isCore = in_array($slug, $this->coreModules, true);
            $manifest = $this->moduleManifests[$slug] ?? $this->ensureManifest($slug);
            
            if ($loaded) {
                $module = $this->modules[$slug];
                $status[$slug] = [
                    'slug' => $slug,
                    'name' => $module->getName(),
                    'description' => $module->getDescription(),
                    'icon' => $module->getIcon(),
                    'version' => $manifest?->version ?? (defined('SWC_CHATBOT_VERSION') ? SWC_CHATBOT_VERSION : '0.0.0'),
                    'requires' => $manifest?->getRequiredModules() ?? [],
                    'available' => true,
                    'enabled' => $enabled,
                    'loaded' => true,
                    'isCore' => $isCore,
                    'tools_count' => count($module->getTools()),
                    'skills_count' => count($module->getSkills()),
                ];
            } else {
                // Module not loaded - either unavailable or disabled
                $status[$slug] = [
                    'slug' => $slug,
                    'name' => ucfirst(str_replace('_', ' ', $slug)),
                    'description' => '',
                    'icon' => $available ? '' : '',
                    'version' => $manifest?->version ?? (defined('SWC_CHATBOT_VERSION') ? SWC_CHATBOT_VERSION : '0.0.0'),
                    'requires' => $manifest?->getRequiredModules() ?? [],
                    'available' => $available,
                    'enabled' => $enabled,
                    'loaded' => false,
                    'isCore' => $isCore,
                    'tools_count' => 0,
                    'skills_count' => 0,
                    'missing' => $available ? null : ($this->detectors[$slug] ?? 'Unknown'),
                    'skipped_reason' => $this->skippedModules[$slug] ?? null,
                ];
            }
        }
        
        return $status;
    }

    /**
     * Ensure a manifest exists for a registered module.
     */
    private function ensureManifest(string $slug): ?ModuleManifest
    {
        if (isset($this->moduleManifests[$slug])) {
            return $this->moduleManifests[$slug];
        }

        $class = $this->moduleClasses[$slug] ?? null;
        if (!$class || !class_exists($class)) {
            return null;
        }

        $module = $this->moduleCache[$slug] ?? new $class();
        $this->moduleCache[$slug] = $module;

        $manifest = $this->getManifest($slug, $module);
        $this->moduleManifests[$slug] = $manifest;

        return $manifest;
    }
    
    /**
     * Get list of all available module slugs
     */
    public function getAvailableModuleSlugs(): array {
        return array_keys(array_filter(
            $this->moduleClasses,
            fn($_, $slug) => $this->canLoad($slug),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Build manifest for a module.
     */
    private function getManifest(string $slug, ModuleInterface $module): ModuleManifest
    {
        if ($module instanceof ModuleManifestProvider) {
            return $module->getManifest();
        }

        return ModuleManifest::fromModule($module, [
            'slug' => $slug,
            'core' => in_array($slug, $this->coreModules, true),
        ]);
    }

    /**
     * Resolve module load order based on declared dependencies.
     */
    private function resolveLoadOrder(array $slugs): array
    {
        $graph = [];
        $dependents = [];
        $inDegree = [];

        foreach ($slugs as $slug) {
            $manifest = $this->moduleManifests[$slug] ?? null;
            $deps = $manifest?->getRequiredModules() ?? [];
            $deps = array_keys($deps);
            $deps = array_values(array_intersect($deps, $slugs));
            $graph[$slug] = $deps;
            $inDegree[$slug] = count($deps);
            foreach ($deps as $dep) {
                $dependents[$dep][] = $slug;
            }
        }

        $queue = array_keys(array_filter($inDegree, fn($count) => $count === 0));
        $order = [];

        while (!empty($queue)) {
            $node = array_shift($queue);
            $order[] = $node;

            foreach ($dependents[$node] ?? [] as $child) {
                $inDegree[$child]--;
                if ($inDegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        foreach ($inDegree as $slug => $count) {
            if ($count > 0) {
                $this->skippedModules[$slug] = 'dependency_cycle';
            }
        }

        return $order;
    }

    /**
     * Check dependency availability and version constraints.
     */
    private function dependenciesSatisfied(string $slug): bool
    {
        $manifest = $this->moduleManifests[$slug] ?? null;
        if (!$manifest) {
            return true;
        }

        foreach ($manifest->getRequiredModules() as $depSlug => $constraint) {
            if (!isset($this->modules[$depSlug])) {
                $this->skippedModules[$slug] = 'missing_dependency';
                return false;
            }

            $depManifest = $this->moduleManifests[$depSlug] ?? null;
            $depVersion = $depManifest?->version ?? (defined('SWC_CHATBOT_VERSION') ? SWC_CHATBOT_VERSION : '0.0.0');

            if (!empty($constraint) && !VersionConstraints::satisfies($depVersion, (string) $constraint)) {
                $this->skippedModules[$slug] = 'dependency_version_mismatch';
                return false;
            }
        }

        return true;
    }
}
