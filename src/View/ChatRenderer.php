<?php
namespace Quarksol\SmartChatbot\View;

defined('ABSPATH') || exit;

use Quarksol\SmartChatbot\Models\ChatWidget;

/**
 * Class ChatRenderer
 * 
 * Handles HTML rendering and asset enqueuing for the chatbot.
 * Supports both legacy jQuery-based widget and new React-based widget.
 * 
 * @package Quarksol\SmartChatbot\View
 */
class ChatRenderer
{

    /**
     * @var array Widget settings
     */
    private $settings;

    /**
     * @var ChatWidget|null Primary widget model
     */
    private $widget = null;

    /**
     * @var array All available widgets for this page
     */
    private $availableWidgets = [];

    /**
     * @var bool Whether to use React-based widget
     */
    private $useReactWidget = true;

    /**
     * ChatRenderer constructor.
     * 
     * @param array $settings
     * @param ChatWidget|null $widget Primary widget
     * @param array $availableWidgets All widgets available for this page
     */
    public function __construct($settings, $widget = null, $availableWidgets = [])
    {
        // ==========================================
        // Hook: swc/chat/widget_settings (filter)
        // Modify widget settings before rendering
        // ==========================================
        $this->settings = \Quarksol\SmartChatbot\Hooks\Hooks::filter('swc/chat/widget_settings', $settings);
        $this->widget = $widget;
        $this->availableWidgets = $availableWidgets;
        
        // Check if React widget is available (check for built file)
        $reactBuildFile = SWC_CHATBOT_PATH . 'assets/admin-build/chatbot-widget.js';
        $this->useReactWidget = file_exists($reactBuildFile);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueueScripts()
    {
        if ($this->useReactWidget) {
            $this->enqueueReactWidget();
        } else {
            $this->enqueueLegacyWidget();
        }
        
        // ==========================================
        // Hook: swc/chat/widget_scripts (action)
        // Fired after widget scripts are enqueued
        // ==========================================
        \Quarksol\SmartChatbot\Hooks\Hooks::action('swc/chat/widget_scripts', $this->settings);
    }

    /**
     * Enqueue React-based widget assets
     */
    private function enqueueReactWidget()
    {
        $buildPath = SWC_CHATBOT_PATH . 'assets/admin-build/';
        $buildUrl = SWC_CHATBOT_URL . 'assets/admin-build/';

        // Widget styles - check if CSS was generated
        $cssFile = $buildPath . 'chatbot-widget.css';
        if (file_exists($cssFile)) {
            wp_enqueue_style(
                'smart-ai-chatbot-widget-css', 
                $buildUrl . 'chatbot-widget.css', 
                [], 
                SWC_CHATBOT_VERSION
            );
            // Add inline CSS variables
            wp_add_inline_style('smart-ai-chatbot-widget-css', $this->generateAppearanceStyles());
        } else {
            // Fallback to legacy CSS if React CSS not built
            wp_enqueue_style('smart-ai-chatbot-css', SWC_CHATBOT_URL . 'assets/css/chatbot.css', [], SWC_CHATBOT_VERSION);
            wp_add_inline_style('smart-ai-chatbot-css', $this->generateAppearanceStyles());
        }

        // Dependencies for the widget script
        $widgetDeps = [];

        // Runtime chunk (always required for code splitting)
        $runtimeFile = $buildPath . 'runtime.js';
        if (file_exists($runtimeFile)) {
            $runtimeAsset = file_exists($buildPath . 'runtime.asset.php')
                ? include $buildPath . 'runtime.asset.php'
                : ['dependencies' => [], 'version' => SWC_CHATBOT_VERSION];
            
            wp_enqueue_script(
                'swc-runtime',
                $buildUrl . 'runtime.js',
                $runtimeAsset['dependencies'] ?? [],
                $runtimeAsset['version'] ?? SWC_CHATBOT_VERSION,
                true
            );
            $widgetDeps[] = 'swc-runtime';
        }

        // Vendors chunk (shared dependencies)
        $vendorsFile = $buildPath . 'vendors.js';
        if (file_exists($vendorsFile)) {
            wp_enqueue_script(
                'swc-vendors',
                $buildUrl . 'vendors.js',
                $widgetDeps,
                SWC_CHATBOT_VERSION,
                true
            );
            $widgetDeps[] = 'swc-vendors';
        }

        // React vendor chunk (if separate)
        $reactVendorFile = $buildPath . 'react-vendor.js';
        if (file_exists($reactVendorFile)) {
            wp_enqueue_script(
                'swc-react-vendor',
                $buildUrl . 'react-vendor.js',
                $widgetDeps,
                SWC_CHATBOT_VERSION,
                true
            );
            $widgetDeps[] = 'swc-react-vendor';
        }

        // Widget script with asset file dependencies
        $widgetAsset = file_exists($buildPath . 'chatbot-widget.asset.php')
            ? include $buildPath . 'chatbot-widget.asset.php'
            : ['dependencies' => [], 'version' => SWC_CHATBOT_VERSION];

        // Merge dependencies
        $finalDeps = array_unique(array_merge($widgetDeps, $widgetAsset['dependencies'] ?? []));

        wp_enqueue_script(
            'smart-ai-chatbot-widget',
            $buildUrl . 'chatbot-widget.js',
            $finalDeps,
            $widgetAsset['version'] ?? SWC_CHATBOT_VERSION,
            true
        );

        // Build widget configuration
        $config = $this->buildWidgetConfig();
        
        // Inject configuration as global variable
        wp_add_inline_script(
            'smart-ai-chatbot-widget',
            'window.swcChatbotConfig = ' . wp_json_encode($config) . ';',
            'before'
        );
    }

    /**
     * Enqueue legacy jQuery-based widget assets
     */
    private function enqueueLegacyWidget()
    {
        wp_enqueue_style('smart-ai-chatbot-css', SWC_CHATBOT_URL . 'assets/css/chatbot.css', [], SWC_CHATBOT_VERSION);

        // Add Google Font if custom font selected
        $fontFamily = $this->settings['font_family'] ?? 'system';
        if ($fontFamily !== 'system') {
            $fontName = $this->extractFontName($fontFamily);
            if ($fontName) {
                $fontUrl = 'https://fonts.googleapis.com/css2?family=' . urlencode($fontName) . ':wght@400;500;600;700&display=swap';
                wp_enqueue_style('swc-google-font', $fontUrl, [], null);
            }
        }

        // Generate and add inline appearance styles
        wp_add_inline_style('smart-ai-chatbot-css', $this->generateAppearanceStyles());

        wp_enqueue_script('smart-ai-chatbot-js', SWC_CHATBOT_URL . 'assets/js/chatbot.js', ['jquery'], SWC_CHATBOT_VERSION, true);

        // Localize legacy script
        wp_localize_script('smart-ai-chatbot-js', 'swcChatbot', $this->buildLegacyConfig());
    }

    /**
     * Build widget configuration for React widget
     */
    private function buildWidgetConfig(): array
    {
        $appearance = $this->widget 
            ? $this->widget->appearance 
            : $this->extractAppearanceFromSettings();
            
        $behavior = $this->widget 
            ? $this->widget->behavior 
            : $this->extractBehaviorFromSettings();
            
        $triggers = $this->widget 
            ? $this->widget->triggers 
            : $this->extractTriggersFromSettings();
            
        $engagement = $this->widget 
            ? $this->widget->engagement 
            : [];

        // Only set pageId on singular pages (posts, pages, products) — NOT archives
        // On archives, get_queried_object_id() returns a term ID, not a post ID
        $pageId = (is_singular() && function_exists('get_queried_object_id')) ? (int) get_queried_object_id() : 0;
        $postType = $pageId ? get_post_type($pageId) : '';
        $isCart = function_exists('is_cart') && is_cart();
        $isCheckout = function_exists('is_checkout') && is_checkout();

        // Build available widgets array for multi-widget selection
        $availableWidgetsData = [];
        foreach ($this->availableWidgets as $w) {
            // Resolve agent DB ID for this widget
            $widgetAgentDbId = 0;
            $widgetAgent = $w->getAgent();
            if ($widgetAgent) {
                $widgetAgentDbId = $widgetAgent->id;
            }
            
            $availableWidgetsData[] = [
                'id' => $w->id,
                'name' => $w->name,
                'displayName' => $w->displayName,
                'description' => $w->description ?? '',
                'appearance' => $w->appearance,
                'behavior' => $w->behavior,
                'triggers' => $w->triggers,
                'engagement' => $w->engagement,
                'avatar' => $w->appearance['avatar'] ?? '',
                'agentDbId' => $widgetAgentDbId,
            ];
        }

        // Resolve the widget's assigned agent DB ID
        $agentDbId = 0;
        if ($this->widget) {
            $agent = $this->widget->getAgent();
            if ($agent) {
                $agentDbId = $agent->id;
            }
        }

        return [
            'widgetId' => $this->widget ? $this->widget->id : 0,
            'agentDbId' => $agentDbId,
            'displayName' => $this->widget 
                ? $this->widget->displayName 
                : ($this->settings['bot_name'] ?? 'Shopping Assistant'),
            'appearance' => $appearance,
            'behavior' => $behavior,
            'triggers' => $triggers,
            'engagement' => $engagement,
            'apiUrl' => rest_url('quark-agentflow-ai/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'availableWidgets' => $availableWidgetsData,
            'hasMultipleWidgets' => count($availableWidgetsData) > 1,
            'settings' => [
                'pageId' => $pageId,
                'postType' => $postType,
                'isCart' => $isCart,
                'isCheckout' => $isCheckout,
                'isLoggedIn' => is_user_logged_in(),
                'userId' => get_current_user_id(),
            ],
        ];
    }

    /**
     * Build legacy config for jQuery widget
     */
    private function buildLegacyConfig(): array
    {
        // Only set pageId on singular pages (posts, pages, products) — NOT archives
        $pageId = (is_singular() && function_exists('get_queried_object_id')) ? (int) get_queried_object_id() : 0;
        $postType = $pageId ? get_post_type($pageId) : '';
        $isCart = function_exists('is_cart') && is_cart();
        $isCheckout = function_exists('is_checkout') && is_checkout();
        $isAccount = function_exists('is_account_page') && is_account_page();
        
        $enableStreaming = isset($this->settings['enable_streaming'])
            ? (bool) $this->settings['enable_streaming']
            : (bool) ($this->settings['streaming_enabled'] ?? true);
        $enableProactive = isset($this->settings['enable_proactive'])
            ? (bool) $this->settings['enable_proactive']
            : (bool) ($this->settings['proactive_enabled'] ?? false);
        $proactiveDelay = isset($this->settings['proactive_delay'])
            ? (int) $this->settings['proactive_delay']
            : (int) ($this->settings['proactiveDelay'] ?? 30);

        $fontFamily = $this->settings['font_family'] ?? 'system';

        return [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('swc_chatbot_nonce'),
            'restUrl' => rest_url('quark-agentflow-ai/v1'),
            'settings' => [
                'botName' => $this->settings['bot_name'] ?? 'Shopping Assistant',
                'welcomeMessage' => $this->settings['welcome_message'] ?? 'Hi! How can I help you today?',
                'primaryColor' => $this->settings['color_primary'] ?? $this->settings['primary_color'] ?? '#6366f1',
                'position' => $this->settings['position'] ?? 'right',
                'enable_streaming' => $enableStreaming,
                'enable_proactive' => $enableProactive,
                'proactive_delay' => $proactiveDelay,
            ],
            'appearance' => [
                'colorPrimary' => $this->settings['color_primary'] ?? '#6366f1',
                'colorPrimaryHover' => $this->settings['color_primary_hover'] ?? '#4f46e5',
                'colorBgMain' => $this->settings['color_bg_main'] ?? '#ffffff',
                'colorBgLight' => $this->settings['color_bg_light'] ?? '#f8fafc',
                'colorBgMessageBot' => $this->settings['color_bg_message_bot'] ?? '#ffffff',
                'colorTextPrimary' => $this->settings['color_text_primary'] ?? '#1e293b',
                'colorTextSecondary' => $this->settings['color_text_secondary'] ?? '#64748b',
                'colorTextMessageBot' => $this->settings['color_text_message_bot'] ?? '#1e293b',
                'colorBorder' => $this->settings['color_border'] ?? '#e2e8f0',
                'fontFamily' => $fontFamily,
                'fontSizeBase' => intval($this->settings['font_size_base'] ?? 14),
                'lineHeight' => floatval($this->settings['line_height'] ?? 1.5),
                'windowWidth' => intval($this->settings['window_width'] ?? 380),
                'windowHeight' => intval($this->settings['window_height'] ?? 550),
                'borderRadius' => intval($this->settings['border_radius'] ?? 16),
                'bubbleRadius' => intval($this->settings['bubble_radius'] ?? 18),
                'toggleSize' => intval($this->settings['toggle_size'] ?? 60),
                'shadowIntensity' => $this->settings['shadow_intensity'] ?? 'medium',
                'animationSpeed' => $this->settings['animation_speed'] ?? 'normal',
                'enableHoverEffects' => $this->settings['enable_hover_effects'] ?? true,
                'enableGlassmorphism' => !empty($this->settings['enable_glassmorphism']),
                'enableSounds' => !empty($this->settings['enable_sounds']),
                'colorBgMessageBot' => $this->settings['color_bg_message_bot'] ?? '#ffffff',
                'colorTextMessageBot' => $this->settings['color_text_message_bot'] ?? '#1e293b',
                'template' => $this->settings['appearance_template'] ?? 'modern-minimal',
            ],
            'controls' => [
                'header' => $this->settings['controls_header'] ?? ['close'],
                'footer' => $this->settings['controls_footer'] ?? ['voice'],
                'message' => $this->settings['controls_message'] ?? [],
            ],
            'pageId' => $pageId,
            'postType' => $postType,
            'isCart' => $isCart,
            'isCheckout' => $isCheckout,
            'isAccount' => $isAccount,
            'isLoggedIn' => is_user_logged_in(),
            'cartUrl' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
            'checkoutUrl' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
        ];
    }

    /**
     * Extract appearance settings from flat settings array
     */
    private function extractAppearanceFromSettings(): array
    {
        return [
            'template' => $this->settings['appearance_template'] ?? 'default',
            'position' => $this->settings['position'] ?? 'right',
            'color_primary' => $this->settings['color_primary'] ?? $this->settings['primary_color'] ?? '#6366f1',
            'color_primary_hover' => $this->settings['color_primary_hover'] ?? $this->settings['primary_color_hover'] ?? '#4f46e5',
            'color_bg_main' => $this->settings['color_bg_main'] ?? $this->settings['bg_color'] ?? '#ffffff',
            'color_bg_light' => $this->settings['color_bg_light'] ?? '#f8fafc',
            'color_bg_message_bot' => $this->settings['color_bg_message_bot'] ?? '#ffffff',
            'color_text_primary' => $this->settings['color_text_primary'] ?? '#1e293b',
            'color_text_secondary' => $this->settings['color_text_secondary'] ?? '#64748b',
            'color_text_message_bot' => $this->settings['color_text_message_bot'] ?? '#1e293b',
            'color_border' => $this->settings['color_border'] ?? '#e2e8f0',
            'font_family' => $this->settings['font_family'] ?? 'system',
            'font_size_base' => intval($this->settings['font_size_base'] ?? 14),
            'line_height' => floatval($this->settings['line_height'] ?? 1.5),
            'window_width' => intval($this->settings['window_width'] ?? 380),
            'window_height' => intval($this->settings['window_height'] ?? 550),
            'border_radius' => intval($this->settings['border_radius'] ?? 16),
            'bubble_radius' => intval($this->settings['bubble_radius'] ?? 18),
            'toggle_size' => intval($this->settings['toggle_size'] ?? 60),
            'avatar' => $this->settings['avatar'] ?? '',
        ];
    }

    /**
     * Extract behavior settings from flat settings array
     */
    private function extractBehaviorFromSettings(): array
    {
        return [
            'greeting_message' => $this->settings['welcome_message'] ?? 'Hi! How can I help you today?',
            'placeholder_text' => $this->settings['placeholder_text'] ?? 'Type your message...',
        ];
    }

    /**
     * Extract trigger settings from flat settings array
     */
    private function extractTriggersFromSettings(): array
    {
        return [
            'auto_open_enabled' => !empty($this->settings['enable_proactive']) || !empty($this->settings['proactive_enabled']),
            'auto_open_delay' => intval($this->settings['proactive_delay'] ?? $this->settings['proactiveDelay'] ?? 30),
            'exit_intent_enabled' => !empty($this->settings['exit_intent_enabled']),
            'exit_intent_message' => $this->settings['exit_intent_message'] ?? '',
        ];
    }

    /**
     * Extract font name from CSS font-family declaration
     * 
     * @param string $fontFamily
     * @return string|null
     */
    private function extractFontName(string $fontFamily): ?string
    {
        if (preg_match('/"([^"]+)"/', $fontFamily, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Generate inline CSS for appearance settings
     * 
     * Uses widget appearance settings when available, falling back to global settings.
     * 
     * @return string
     */
    private function generateAppearanceStyles(): string
    {
        // When a widget is available, merge its appearance settings on top of global settings
        // so widget-specific values (font_size_base, colors, etc.) take priority.
        $s = $this->settings;
        if ($this->widget) {
            $widgetAppearance = $this->widget->appearance;
            if (is_array($widgetAppearance)) {
                $s = array_merge($s, $widgetAppearance);
            }
        }

        $colorPrimary = esc_attr($s['color_primary'] ?? $s['primary_color'] ?? '#6366f1');
        $colorPrimaryHover = esc_attr($s['color_primary_hover'] ?? $s['primary_color_hover'] ?? '#4f46e5');
        $colorBgMain = esc_attr($s['color_bg_main'] ?? $s['bg_color'] ?? '#ffffff');
        $colorBgLight = esc_attr($s['color_bg_light'] ?? '#f8fafc');
        $colorBgMessageBot = esc_attr($s['color_bg_message_bot'] ?? '#ffffff');
        $colorTextPrimary = esc_attr($s['color_text_primary'] ?? '#1e293b');
        $colorTextSecondary = esc_attr($s['color_text_secondary'] ?? '#64748b');
        $colorTextMessageBot = esc_attr($s['color_text_message_bot'] ?? '#1e293b');
        $colorBorder = esc_attr($s['color_border'] ?? '#e2e8f0');

        $fontFamily = $s['font_family'] ?? 'system';
        if ($fontFamily === 'system') {
            $fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif';
        }
        $fontFamily = esc_attr($fontFamily);

        $fontSize = intval($s['font_size_base'] ?? 14);
        $lineHeight = floatval($s['line_height'] ?? 1.5);
        $windowWidth = intval($s['window_width'] ?? 380);
        $windowHeight = intval($s['window_height'] ?? 550);
        $borderRadius = intval($s['border_radius'] ?? 16);
        $bubbleRadius = intval($s['bubble_radius'] ?? 18);
        $toggleSize = intval($s['toggle_size'] ?? 60);

        // Shadow intensity mapping
        $shadowMap = [
            'none' => '0 0 0 rgba(0, 0, 0, 0)',
            'light' => '0 5px 20px rgba(0, 0, 0, 0.08)',
            'medium' => '0 10px 40px rgba(0, 0, 0, 0.15)',
            'heavy' => '0 20px 60px rgba(0, 0, 0, 0.25)',
        ];
        $shadowIntensity = $s['shadow_intensity'] ?? 'medium';
        $shadow = $shadowMap[$shadowIntensity] ?? $shadowMap['medium'];

        // Animation speed mapping
        $animationMap = [
            'none' => '0s',
            'fast' => '0.15s',
            'normal' => '0.3s',
            'slow' => '0.5s',
        ];
        $animationSpeed = $s['animation_speed'] ?? 'normal';
        $transition = $animationMap[$animationSpeed] ?? $animationMap['normal'];

        return "
:root {
    --swc-primary: {$colorPrimary};
    --swc-primary-hover: {$colorPrimaryHover};
    --swc-bg-main: {$colorBgMain};
    --swc-bg-light: {$colorBgLight};
    --swc-bg-message-bot: {$colorBgMessageBot};
    --swc-text-primary: {$colorTextPrimary};
    --swc-text-secondary: {$colorTextSecondary};
    --swc-text-message-bot: {$colorTextMessageBot};
    --swc-border: {$colorBorder};
    --swc-font-family: {$fontFamily};
    --swc-font-size-base: {$fontSize}px;
    --swc-line-height: {$lineHeight};
    --swc-window-width: {$windowWidth}px;
    --swc-window-height: {$windowHeight}px;
    --swc-radius: {$borderRadius}px;
    --swc-radius-bubble: {$bubbleRadius}px;
    --swc-toggle-size: {$toggleSize}px;
    --swc-shadow: {$shadow};
    --swc-transition: {$transition} ease;
}

.smart-ai-chatbot {
    --swc-primary: {$colorPrimary};
    --swc-primary-hover: {$colorPrimaryHover};
    --swc-bg: {$colorBgMain};
    --swc-bg-light: {$colorBgLight};
    --swc-bg-message-bot: {$colorBgMessageBot};
    --swc-text-primary: {$colorTextPrimary};
    --swc-text-secondary: {$colorTextSecondary};
    --swc-text-message-bot: {$colorTextMessageBot};
    --swc-border-color: {$colorBorder};
    --swc-font-family: {$fontFamily};
    --swc-font-size-base: {$fontSize}px;
    --swc-line-height: {$lineHeight};
    --swc-widget-width: {$windowWidth}px;
    --swc-widget-height: {$windowHeight}px;
    --swc-radius-lg: {$borderRadius}px;
    --swc-radius-bubble: {$bubbleRadius}px;
    --swc-toggle-size: {$toggleSize}px;
    --swc-shadow-md: {$shadow};
    --swc-transition-normal: {$transition} ease;
}
";
    }


    /**
     * Render the chatbot HTML
     * For React widget, only renders a container div
     * For legacy widget, renders full HTML structure
     */
    public function render()
    {
        if ($this->useReactWidget) {
            // React widget creates its own DOM
            echo '<div id="smart-ai-chatbot-root"></div>';
        } else {
            // Render legacy HTML structure
            $this->renderLegacyWidget();
        }

        // ==========================================
        // Hook: swc/chat/widget_render (action)
        // Fired after widget HTML is rendered
        // ==========================================
        \Quarksol\SmartChatbot\Hooks\Hooks::action('swc/chat/widget_render', $this->settings, $this->settings['position'] ?? 'right');
    }

    /**
     * Render legacy widget HTML
     */
    private function renderLegacyWidget()
    {
        $position = $this->settings['position'] ?? 'right';
        $color = $this->settings['primary_color'] ?? '#6366f1';
        ?>
        <div id="quark-agentflow-ai" class="smart-ai-chatbot swc-position-<?php echo esc_attr($position); ?>"
            style="--swc-primary: <?php echo esc_attr($color); ?>">
            <!-- Chat Toggle Button -->
            <button class="swc-toggle" aria-label="Open chat">
                <svg class="swc-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <svg class="swc-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- Chat Window -->
            <div class="swc-window" role="dialog" aria-labelledby="swc-dialog-title" aria-modal="true">
                <div class="swc-header">
                    <div class="swc-header-info">
                        <div class="swc-avatar" aria-hidden="true"></div>
                        <div>
                            <div class="swc-bot-name" id="swc-dialog-title">
                                <?php echo esc_html($this->settings['bot_name'] ?? 'Shopping Assistant'); ?></div>
                            <div class="swc-status" aria-hidden="true">Online</div>
                        </div>
                    </div>
                    <button class="swc-close" aria-label="Close chat">×</button>
                </div>

                <div class="swc-messages" role="log" aria-live="polite" aria-label="Chat messages"></div>

                <div class="swc-quick-actions">
                    <button class="swc-quick-btn" data-action="products">Products</button>
                    <button class="swc-quick-btn" data-action="bestsellers">Best Sellers</button>
                    <button class="swc-quick-btn" data-action="sale">On Sale</button>
                    <button class="swc-quick-btn" data-action="track">Track Order</button>
                    <button class="swc-quick-btn" data-action="delivery">Delivery</button>
                </div>

                <div class="swc-input-area">
                    <textarea class="swc-input" placeholder="" rows="1" autocomplete="off"></textarea>
                    <button class="swc-voice-btn" aria-label="Voice input" title="Speak your message"></button>
                    <button class="swc-send" aria-label="Send message">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
