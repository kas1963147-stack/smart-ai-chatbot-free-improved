<?php
declare(strict_types=1);
/**
 * WordPress Core Module
 * 
 * Provides core WordPress capabilities to the AI Agent.
 * This is a CORE module that cannot be disabled.
 * 
 * @package Quarksol\SmartChatbot\Modules\WordPress
 */

namespace Quarksol\SmartChatbot\Modules\WordPress;

use Quarksol\SmartChatbot\Modules\ModuleInterface;
use Quarksol\SmartChatbot\Modules\ModuleManifest;
use Quarksol\SmartChatbot\Modules\ModuleManifestProvider;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Core Module
 * 
 * Provides tools for:
 * - Searching WordPress content (posts, pages)
 * - Reading post content
 * - Site information
 * - User information
 */
class WordPressModule implements ModuleInterface, ModuleManifestProvider
{
    /**
     * Get unique module identifier
     */
    public function getSlug(): string
    {
        return 'wordpress';
    }
    
    /**
     * Get human-readable module name
     */
    public function getName(): string
    {
        return 'WordPress Core';
    }
    
    /**
     * Get module description
     */
    public function getDescription(): string
    {
        return 'Core WordPress content and site management capabilities';
    }
    
    /**
     * Get module icon
     */
    public function getIcon(): string
    {
        return '';
    }
    
    /**
     * Check if module dependencies are available
     * WordPress Core is always available
     */
    public function isAvailable(): bool
    {
        return true;
    }
    
    /**
     * Get tools this module provides
     */
    public function getTools(): array
    {
        $tools = [];
        
        // Load WordPress Core tools from toolkits directory
        $toolkitPath = SWC_PLUGIN_PATH . 'toolkits/WordPressCore/';
        
        if (is_dir($toolkitPath)) {
            $toolFiles = glob($toolkitPath . '*Tool.php');
            foreach ($toolFiles as $file) {
                require_once $file;
                $className = '\\Toolkits\\WordPressCore\\' . basename($file, '.php');
                if (class_exists($className)) {
                    $tools[] = new $className();
                }
            }
        }
        
        return $tools;
    }
    
    /**
     * Get skill slugs this module enables
     */
    public function getSkills(): array
    {
        return [
            'wordpress-admin',
            'content-generation',
        ];
    }
    
    /**
     * Get AI prompt additions for this module
     */
    public function getPromptAdditions(): string
    {
        $siteName = get_bloginfo('name');
        $siteUrl = get_site_url();
        
        return "You are an AI assistant for the WordPress site \"{$siteName}\" ({$siteUrl}).

You can help with:
- Searching and displaying WordPress content (posts, pages)
- Providing site information
- Creating, editing, and managing content
- User-related queries
";
    }

    /**
     * Get manifest metadata for dependency resolution.
     */
    public function getManifest(): ModuleManifest
    {
        $version = defined('SWC_CHATBOT_VERSION') ? SWC_CHATBOT_VERSION : '0.0.0';

        return new ModuleManifest([
            'slug' => $this->getSlug(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'version' => $version,
            'icon' => $this->getIcon(),
            'requires' => [],
            'provides' => [],
            'core' => true,
        ]);
    }
    
    /**
     * Initialize module
     */
    public function boot(): void
    {
        // WordPress Core module initialization
        // This runs when the module is loaded
    }
}

