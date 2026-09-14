<?php
declare(strict_types=1);
/**
 * WooCommerce Module
 * 
 * Provides WooCommerce e-commerce capabilities to the AI Agent.
 * This is an OPTIONAL module that requires WooCommerce plugin.
 * 
 * @package Quarksol\SmartChatbot\Modules\WooCommerce
 */

namespace Quarksol\SmartChatbot\Modules\WooCommerce;

use Quarksol\SmartChatbot\Modules\ModuleInterface;
use Quarksol\SmartChatbot\Modules\ModuleManifest;
use Quarksol\SmartChatbot\Modules\ModuleManifestProvider;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Module
 * 
 * Provides tools for:
 * - Product search and display
 * - Cart management
 * - Order tracking
 * - Store information
 */
class WooCommerceModule implements ModuleInterface, ModuleManifestProvider
{
    /**
     * Get unique module identifier
     */
    public function getSlug(): string
    {
        return 'woocommerce';
    }
    
    /**
     * Get human-readable module name
     */
    public function getName(): string
    {
        return 'WooCommerce';
    }
    
    /**
     * Get module description
     */
    public function getDescription(): string
    {
        return 'E-commerce capabilities: products, cart, orders, and store management';
    }
    
    /**
     * Get module icon
     */
    public function getIcon(): string
    {
        return '';
    }
    
    /**
     * Check if WooCommerce is available
     */
    public function isAvailable(): bool
    {
        return class_exists('WooCommerce');
    }
    
    /**
     * Get tools this module provides
     */
    public function getTools(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        
        $tools = [];
        
        // Load WooCommerce tools from toolkits directory
        $toolkitPath = SWC_PLUGIN_PATH . 'toolkits/WooCommerce/';
        
        if (is_dir($toolkitPath)) {
            $toolFiles = glob($toolkitPath . '*Tool.php');
            foreach ($toolFiles as $file) {
                require_once $file;
                $className = '\\Toolkits\\WooCommerce\\' . basename($file, '.php');
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
            'sales-agent',
            'product-expert',
            'order-support',
        ];
    }
    
    /**
     * Get AI prompt additions for WooCommerce
     */
    public function getPromptAdditions(): string
    {
        $storeName = get_bloginfo('name');
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
        
        return "This is an e-commerce store using WooCommerce.
- Currency: {$currency}
- You can search products, manage cart, track orders
- When showing products, use the woo_search_products tool
- When the user wants to add to cart, use woo_cart with action 'add'
- For order tracking, use woo_order_track
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
            'requires' => [
                'modules' => [
                    'wordpress' => '^1.0.0',
                ],
            ],
            'provides' => [],
            'core' => false,
        ]);
    }
    
    /**
     * Get UI signals for WooCommerce tools
     * Maps tool names to rich UI component configurations
     */
    public static function getUISignals(): array
    {
        return [
            'woo_search_products' => [
                'type' => 'products',
                'component' => 'ProductCarousel',
            ],
            'woo_cart' => [
                'type' => 'cart',
                'component' => 'CartView',
            ],
            'woo_store_info' => [
                'type' => 'store_info',
                'component' => 'StoreInfo',
            ],
            'woo_order_track' => [
                'type' => 'order',
                'component' => 'OrderStatus',
            ],
        ];
    }
    
    /**
     * Initialize module
     */
    public function boot(): void
    {
        // WooCommerce module initialization
        // Register WooCommerce-specific hooks if needed
    }
}

