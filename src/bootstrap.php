<?php
declare(strict_types=1);
/**
 * Module System Bootstrap
 * 
 * Loads all module system components with proper autoloading.
 * Iclude this file to use the new modular architecture.
 * 
 * @package App
 */

if (!defined('ABSPATH')) {
    exit;
}

// Base path for src/
$srcPath = dirname(__FILE__);

// Initialize analytics endpoint
\Quarksol\SmartChatbot\Analytics\AnalyticsEndpoint::register();

// Load Scheduler
\Quarksol\SmartChatbot\Analytics\AnalyticsScheduler::init();

// Retention cleanup scheduler
if (class_exists('\Quarksol\SmartChatbot\Scheduler\RetentionScheduler')) {
    \Quarksol\SmartChatbot\Scheduler\RetentionScheduler::init();
}

// Load helpers (functions are not PSR-4 autoloaded)
if (file_exists($srcPath . '/helpers.php')) { require_once $srcPath . '/helpers.php'; }

// =====================================================
// Hook System Initialization
// =====================================================
// HookDefinitions contains plain PHP functions (not classes), so it needs explicit loading
if (file_exists($srcPath . '/Hooks/HookDefinitions.php')) { require_once $srcPath . '/Hooks/HookDefinitions.php'; }

// Register all plugin hooks with documentation
\Quarksol\SmartChatbot\Hooks\register_all_hooks();

// Enable hook tracing in debug mode
if (defined('WP_DEBUG') && \WP_DEBUG && defined('SWC_TRACE_HOOKS') && SWC_TRACE_HOOKS) {
    \Quarksol\SmartChatbot\Hooks\HookRegistry::enableTracing();
}


// Register core settings schema defaults and sanitizers.
if (class_exists('\Quarksol\SmartChatbot\Foundation\Settings\\CoreSettingsSchema')) {
    \Quarksol\SmartChatbot\Foundation\Settings\CoreSettingsSchema::register();
}

// Load Toolkit Functions (get_all_toolkit_tools, get_toolkits_status, etc.)
// This must be loaded before agents try to bootstrap tools
$toolkitLoaderPath = dirname($srcPath) . '/toolkits/loader.php';
if (file_exists($toolkitLoaderPath)) {
    require_once $toolkitLoaderPath;
}

// Load API factory functions (buildApiHandler, etc.)
if (file_exists($srcPath . '/Api/index.php')) { require_once $srcPath . '/Api/index.php'; }

// =====================================================
// Module System Initialization
// =====================================================

// Register modules with the ModuleLoader
$moduleLoader = \Quarksol\SmartChatbot\Modules\ModuleLoader::getInstance();

// Register WordPress Core Module (always enabled)
$moduleLoader->register('wordpress', \Quarksol\SmartChatbot\Modules\WordPress\WordPressModule::class);

// Register WooCommerce Module (optional, requires WooCommerce)
$moduleLoader->register('woocommerce', \Quarksol\SmartChatbot\Modules\WooCommerce\WooCommerceModule::class);

// Detect and load available modules
$moduleLoader->detectAndLoad();

// Run pending migrations once WordPress is initialized.
add_action('init', function () {
    if (class_exists('\Quarksol\SmartChatbot\Foundation\Migrations\\MigrationRegistry')) {
        \Quarksol\SmartChatbot\Foundation\Migrations\MigrationRegistry::runPending();
    }
});


// Register Modules REST API endpoints
\Quarksol\SmartChatbot\Api\Controllers\ModulesController::register();




// Register Knowledge REST API (documents, sources, converters)
add_action('rest_api_init', [\Quarksol\SmartChatbot\Knowledge\KnowledgeController::class, 'register']);


