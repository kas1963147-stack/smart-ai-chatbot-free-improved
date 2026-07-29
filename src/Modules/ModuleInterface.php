<?php
declare(strict_types=1);


/**
 * Module Interface
 * 
 * Contract that all optional modules must implement.
 * Modules provide tools and skills that are loaded based on available plugins.
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for optional modules (WooCommerce, CF7, etc.)
 */
interface ModuleInterface {
    
    /**
     * Get unique module identifier
     * Example: 'woocommerce', 'contact_form_7'
     */
    public function getSlug(): string;
    
    /**
     * Get human-readable module name
     * Example: 'WooCommerce', 'Contact Form 7'
     */
    public function getName(): string;
    
    /**
     * Get module description
     */
    public function getDescription(): string;
    
    /**
     * Get module icon (emoji or dashicon)
     */
    public function getIcon(): string;
    
    /**
     * Check if module dependencies are available
     * Should check for required plugins, classes, functions
     */
    public function isAvailable(): bool;
    
    /**
     * Get tools this module provides
     * Returns array of ToolInterface instances
     */
    public function getTools(): array;
    
    /**
     * Get skill slugs this module enables
     * References skills in skills/ directory
     */
    public function getSkills(): array;
    
    /**
     * Get AI prompt additions for this module
     * Added to system prompt when module is active
     */
    public function getPromptAdditions(): string;
    
    /**
     * Initialize module (register hooks, etc.)
     */
    public function boot(): void;
}
