<?php
/**
 * Agent Manager Admin Page
 * 
 * Registers the main WordPress admin page and mounts the React application.
 * This is the SINGLE admin entry point for Smart AI Chatbot.
 * 
 * @package SWC\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Manager Admin
 * 
 * Handles all admin menu registration and React app mounting.
 */
class SWC_Chatbot_Manager_Admin
{

    /** Admin page slug - this is the MAIN menu */
    const PAGE_SLUG = 'agentflow-ai';

    /** Script handle */
    const SCRIPT_HANDLE = 'smart-ai-chatbot-manager';

    /**
     * Constructor
     */
    public function __construct()
    {
        // High priority to prevent other hooks from conflicting
        add_action('admin_menu', [$this, 'add_menu_page'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Add main admin menu page
     * 
     * This creates a single, unified admin interface.
     */
    public function add_menu_page()
    {
        // Main menu page - React app
        add_menu_page(
            __('Quarksol AI Chatbot & Agent Workflows', 'agentflow-ai'),
            __('Quarksol AI Chatbot', 'agentflow-ai'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-format-chat',
            56
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook)
    {
        // Only load on our admin page (main or any subpages)
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG && strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        $build_dir = SWC_CHATBOT_PATH . 'assets/admin-build/';
        $build_url = SWC_CHATBOT_URL . 'assets/admin-build/';

        // Check if build exists
        $build_path = $build_dir . 'index.js';
        $asset_file = $build_dir . 'index.asset.php';

        // Use build files if they exist (production)
        if (file_exists($build_path) && file_exists($asset_file)) {
            $asset = require $asset_file;
            $version = $asset['version'] . '.' . time(); // Force cache bust

            // Enqueue runtime chunk first (if exists - for code splitting)
            if (file_exists($build_dir . 'runtime.js')) {
                $runtime_asset = file_exists($build_dir . 'runtime.asset.php')
                    ? require $build_dir . 'runtime.asset.php'
                    : ['dependencies' => [], 'version' => $version];
                wp_enqueue_script(
                    self::SCRIPT_HANDLE . '-runtime',
                    $build_url . 'runtime.js',
                    $runtime_asset['dependencies'],
                    $runtime_asset['version'],
                    true
                );
            }

            // Enqueue vendors chunk (if exists)
            if (file_exists($build_dir . 'vendors.js')) {
                $vendors_asset = file_exists($build_dir . 'vendors.asset.php')
                    ? require $build_dir . 'vendors.asset.php'
                    : ['dependencies' => [], 'version' => $version];
                wp_enqueue_script(
                    self::SCRIPT_HANDLE . '-vendors',
                    $build_url . 'vendors.js',
                    array_merge($vendors_asset['dependencies'], [self::SCRIPT_HANDLE . '-runtime']),
                    $vendors_asset['version'],
                    true
                );
            }

            // Enqueue Mantine chunk (if exists - large UI library)
            if (file_exists($build_dir . 'mantine.js')) {
                $mantine_asset = file_exists($build_dir . 'mantine.asset.php')
                    ? require $build_dir . 'mantine.asset.php'
                    : ['dependencies' => [], 'version' => $version];
                wp_enqueue_script(
                    self::SCRIPT_HANDLE . '-mantine',
                    $build_url . 'mantine.js',
                    array_merge($mantine_asset['dependencies'], [self::SCRIPT_HANDLE . '-vendors']),
                    $mantine_asset['version'],
                    true
                );

                // Mantine CSS
                if (file_exists($build_dir . 'mantine.css')) {
                    wp_enqueue_style(
                        self::SCRIPT_HANDLE . '-mantine',
                        $build_url . 'mantine.css',
                        [],
                        $version
                    );
                }
            }

            // Main app dependencies
            $main_deps = $asset['dependencies'];
            if (file_exists($build_dir . 'mantine.js')) {
                $main_deps[] = self::SCRIPT_HANDLE . '-mantine';
            } elseif (file_exists($build_dir . 'vendors.js')) {
                $main_deps[] = self::SCRIPT_HANDLE . '-vendors';
            }

            wp_enqueue_script(
                self::SCRIPT_HANDLE,
                $build_url . 'index.js',
                $main_deps,
                $version,
                true
            );

            if (file_exists($build_dir . 'index.css')) {
                wp_enqueue_style(
                    self::SCRIPT_HANDLE,
                    $build_url . 'index.css',
                    ['wp-components'],
                    $version
                );
            }
        }
        // WordPress components styles
        wp_enqueue_style('swc-admin-loader', plugins_url('assets/css/admin-loader.css', dirname(__DIR__, 2) . '/agentflow-ai.php'), [], '1.0.0');
        wp_enqueue_style('wp-components');

        // Get current settings for the React app
        $settings = get_option('swc_chatbot_settings', []);

        // Determine initial tab for submenu pages
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $initialTab = '';
        if ($page === self::PAGE_SLUG . '-chats') {
            $initialTab = 'chats';
        } elseif ($page === self::PAGE_SLUG . '-workflows') {
            $initialTab = 'workflows';
        }

        // Get provider instances for dropdown (available immediately, no API call needed)
        $providerDropdown = [];
        try {
            $providerDropdown = \Quarksol\SmartChatbot\Services\ProviderResolver::getInstancesForDropdown();
            error_log('[SWC] Provider dropdown localized: ' . count($providerDropdown) . ' items');
        } catch (\Throwable $e) {
            error_log('[SWC] Provider dropdown ERROR: ' . $e->getMessage());
        }

        // Localize script with API info and settings
        wp_localize_script(self::SCRIPT_HANDLE, 'swcChatbot', [
            'apiUrl' => rest_url('agentflow-ai/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'siteUrl' => site_url(),
            'settings' => $settings,
            'initialTab' => $initialTab,
            'providerInstances' => $providerDropdown,
            'isPro' => false,

            'logoUrl' => SWC_CHATBOT_URL . 'assets/images/logo.png',
        ]);

        // Legacy support — prefixed to avoid naming collisions with other plugins
        wp_localize_script(self::SCRIPT_HANDLE, 'qafaiLegacyData', [
            'restUrl'  => rest_url('quark-agentflow-ai/v1'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
        ]);

        // CRITICAL: Explicitly configure wp.apiFetch nonce middleware.
        // WordPress's @wordpress/api-fetch depends on wpApiSettings.nonce for
        // authentication, but this can fail to auto-configure in certain setups
        // (e.g. when the wp-api script isn't enqueued, or WP core skips the
        // nonce middleware registration). This inline script guarantees the nonce
        // is always set, preventing 403 Forbidden on all REST API calls.
        $nonce = wp_create_nonce('wp_rest');
        wp_add_inline_script(
            'wp-api-fetch',
            sprintf(
                'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( "%s" ) );',
                esc_js($nonce)
            )
        );

        // Inject dynamic theme variables into admin dashboard
        $primaryColor = $settings['color_primary'] ?? $settings['primary_color'] ?? '#1B84FF';
        $secondaryColor = $settings['color_secondary'] ?? $settings['secondary_color'] ?? '#4B5675';
        
        // Generate lighter/darker variations
        $primaryHover = $this->adjust_brightness($primaryColor, -15);
        $primaryLight = $this->adjust_brightness($primaryColor, 90);
        $primaryMuted = $this->adjust_brightness($primaryColor, 85);
        $ringColor = $this->hex2rgba($primaryColor, 0.15);
        $shadowPrimary = $this->hex2rgba($primaryColor, 0.20);

        $custom_css = "
            :root {
                --swc-primary-500: {$primaryColor} !important;
                --swc-primary: {$primaryColor} !important;
                --swc-primary-600: {$primaryHover} !important;
                --swc-primary-hover: {$primaryHover} !important;
                --swc-primary-50: {$primaryLight} !important;
                --swc-primary-light: {$primaryLight} !important;
                --swc-primary-100: {$primaryMuted} !important;
                --swc-primary-muted: {$primaryMuted} !important;
                
                /* Admin Specific Overrides */
                --wp-admin-theme-color: {$primaryColor} !important;
                --swc-ring-color: {$ringColor} !important;
                --swc-shadow-primary: 0 4px 12px 0 {$shadowPrimary} !important;
            }
            #smart-ai-chatbot-manager-root .bg-primary { background-color: {$primaryColor} !important; }
            #smart-ai-chatbot-manager-root .text-primary { color: {$primaryColor} !important; }
            #smart-ai-chatbot-manager-root .border-primary { border-color: {$primaryColor} !important; }
            .swc-admin nav button.bg-primary { 
                background-color: {$primaryLight} !important; 
                color: {$primaryColor} !important; 
            }
        ";

        wp_add_inline_style(self::SCRIPT_HANDLE, $custom_css);
    }

    /**
     * Helper to adjust brightness of hex color
     */
    private function adjust_brightness($hex, $steps)
    {
        $steps = max(-255, min(255, $steps));
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

        return '#' . $r_hex . $g_hex . $b_hex;
    }

    /**
     * Convert Hex to RGBA
     */
    private function hex2rgba($color, $opacity = false)
    {
        $default = 'rgb(0,0,0)';
        if (empty($color)) return $default;
        if ($color[0] == '#') $color = substr($color, 1);
        if (strlen($color) == 6) $hex = [$color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]];
        elseif (strlen($color) == 3) $hex = [$color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]];
        else return $default;
        $rgb = array_map('hexdec', $hex);
        if ($opacity !== false) {
            if (abs($opacity) > 1) $opacity = 1.0;
            return 'rgba(' . implode(",", $rgb) . ',' . $opacity . ')';
        } else {
            return 'rgb(' . implode(",", $rgb) . ')';
        }
    }

    /**
     * Render admin page
     */
    public function render_page()
    {
        ?>
        <div
            class="wrap swc-admin-wrapper bg-slate-50 -ml-5 p-0 min-h-[calc(100vh-32px)] overflow-visible max-[782px]:min-h-[calc(100vh-46px)]">
            <div id="smart-ai-chatbot-manager-root">
                <!-- React app mounts here -->
                <div class="swc-initial-loader">
                    <div class="swc-initial-loader__spinner">
                        <svg class="swc-initial-loader__orbit" width="72" height="72" viewBox="0 0 72 72">
                            <defs>
                                <linearGradient id="swc-init-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="var(--wp-admin-theme-color, #3b82f6)" stop-opacity="1" />
                                    <stop offset="50%" stop-color="var(--wp-admin-theme-color, #3b82f6)" stop-opacity="0.4" />
                                    <stop offset="100%" stop-color="var(--wp-admin-theme-color, #3b82f6)" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <circle cx="36" cy="36" r="31" fill="none" stroke="url(#swc-init-grad)" stroke-width="5"
                                stroke-linecap="round" stroke-dasharray="73 122" />
                        </svg>
                        <div class="swc-initial-loader__core"></div>
                        <div class="swc-initial-loader__particle swc-initial-loader__particle--1"></div>
                        <div class="swc-initial-loader__particle swc-initial-loader__particle--2"></div>
                        <div class="swc-initial-loader__particle swc-initial-loader__particle--3"></div>
                    </div>
                    <p class="swc-initial-loader__text"><?php esc_html_e('Loading Smart Chatbot…', 'agentflow-ai'); ?></p>
                </div>
                
            </div>
        </div>
        <?php
    }
}

// Initialize
new SWC_Chatbot_Manager_Admin();
