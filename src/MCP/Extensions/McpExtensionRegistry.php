<?php

/**
 * MCP Extension Registry
 *
 * Discovers and manages plugin extensions.
 * Auto-loads extensions when their plugins are active.
 */

namespace Quarksol\SmartChatbot\MCP\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

class McpExtensionRegistry
{
    /**
     * Built-in extension classes
     */
    private static array $builtInExtensions = [
        YoastSeoExtension::class,
        AcfExtension::class,
        // Future: ContactForm7Extension::class,
        // Future: GravityFormsExtension::class,
    ];

    /**
     * External extensions registered via filter
     */
    private static ?array $externalExtensions = null;

    /**
     * Get all registered extension classes.
     */
    public static function getAllExtensions(): array
    {
        if (self::$externalExtensions === null) {
            self::$externalExtensions = apply_filters('swc_mcp_register_extension', []);
        }
        return array_merge(self::$builtInExtensions, self::$externalExtensions);
    }

    /**
     * Get only active extensions (plugin is installed and active).
     * 
     * When \WP_DEBUG and SWC_TEST_EXTENSIONS are both true, all extensions
     * are forced active for manual testing without premium plugins.
     */
    public static function getActiveExtensions(): array
    {
        $forceAll = defined('WP_DEBUG') && \WP_DEBUG
                 && defined('SWC_TEST_EXTENSIONS') && SWC_TEST_EXTENSIONS;

        $active = [];
        foreach (self::getAllExtensions() as $extensionClass) {
            if (class_exists($extensionClass) && ($forceAll || $extensionClass::isActive())) {
                $active[] = $extensionClass;
            }
        }
        return $active;
    }

    /**
     * Get categories from all active extensions.
     * @return array ['extension_id' => ['label' => ..., 'icon' => ..., 'tools' => ...]]
     */
    public static function getActiveCategories(): array
    {
        $categories = [];
        foreach (self::getActiveExtensions() as $extensionClass) {
            $category = $extensionClass::getCategory();
            $categories[$category['id']] = [
                'label' => $category['label'],
                'icon' => $category['icon'],
                'extension' => true,
                'extensionClass' => $extensionClass,
                'tools' => $extensionClass::getTools(),
            ];
        }
        return $categories;
    }

    /**
     * Get all tools from active extensions.
     */
    public static function getActiveTools(): array
    {
        $tools = [];
        foreach (self::getActiveExtensions() as $extensionClass) {
            $extensionTools = $extensionClass::getTools();
            foreach ($extensionTools as $tool) {
                $tool['extension'] = $extensionClass;
                $tools[] = $tool;
            }
        }
        return $tools;
    }

    /**
     * Execute a tool from an extension.
     */
    public static function executeTool(string $toolName, array $params): mixed
    {
        foreach (self::getActiveExtensions() as $extensionClass) {
            $tools = $extensionClass::getTools();
            foreach ($tools as $tool) {
                if ($tool['name'] === $toolName) {
                    return $extensionClass::executeTool($toolName, $params);
                }
            }
        }
        throw new \Exception("Tool '$toolName' not found in any active extension");
    }

    /**
     * Check if a tool belongs to an extension.
     */
    public static function isExtensionTool(string $toolName): bool
    {
        foreach (self::getActiveExtensions() as $extensionClass) {
            $tools = $extensionClass::getTools();
            foreach ($tools as $tool) {
                if ($tool['name'] === $toolName) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get stats about extensions.
     */
    public static function getStats(): array
    {
        $activeExtensions = self::getActiveExtensions();
        $totalTools = 0;
        foreach ($activeExtensions as $ext) {
            $totalTools += count($ext::getTools());
        }

        return [
            'active_extensions' => count($activeExtensions),
            'extension_tools' => $totalTools,
            'available_extensions' => count(self::getAllExtensions()),
        ];
    }
}
