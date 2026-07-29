<?php
declare(strict_types=1);
/**
 * ChatWidget Model
 *
 * Represents a chat widget instance with appearance, behavior, triggers,
 * display rules, and engagement settings. Supports both individual agents
 * and teams (agent groups) for multi-agent orchestration.
 *
 * @package SWC\Models
 */

namespace Quarksol\SmartChatbot\Models;

use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class ChatWidget
{
    public int $id = 0;
    public string $name = '';
    public string $displayName = '';
    public string $description = '';
    public array $appearance = [];
    public array $behavior = [];
    public array $triggers = [];
    public array $display = [];
    public array $engagement = [];
    public string $agentId = ''; // Format: 'agent:agent_id' or 'team:group_id'
    public bool $isActive = true;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    // Default appearance settings
    public const DEFAULT_APPEARANCE = [
        'template' => 'default',
        'position' => 'right',
        'color_primary' => '#6366f1',
        'color_primary_hover' => '#4f46e5',
        'color_bg_main' => '#ffffff',
        'color_bg_light' => '#f8fafc',
        'color_bg_message_bot' => '#ffffff',
        'color_text_primary' => '#1e293b',
        'color_text_secondary' => '#64748b',
        'color_text_message_bot' => '#1e293b',
        'color_border' => '#e2e8f0',
        'font_family' => 'system',
        'font_size_base' => 14,
        'window_width' => 380,
        'window_height' => 550,
        'border_radius' => 16,
        'toggle_size' => 60,
        'avatar' => '',
        
        // Presentation Mode
        'presentation_mode' => 'floating',       // floating | embedded | sidebar
        'embedded_target' => '',                  // CSS selector for embedded mode
        'sidebar_width' => 400,                   // Sidebar mode width in px
        
        // Greeting Bubble (teaser tooltip)
        'greeting_bubble_enabled' => false,
        'greeting_bubble_text' => 'Need help? Chat with us!',
        'greeting_bubble_delay' => 5,             // Seconds before showing
        'greeting_bubble_dismissible' => true,
        
        // Notification Badge
        'notification_badge_enabled' => true,
        'notification_badge_color' => '#ef4444',
        'notification_sound_url' => '',            // Custom notification sound URL
        'desktop_notification_enabled' => false,
        
        // Widget Entrance Animation
        'entrance_animation' => 'slide-up',       // none | slide-up | fade | bounce | scale
        
        // Mobile Overrides
        'mobile_toggle_size' => 52,
        'mobile_fullscreen' => false,              // Open chat fullscreen on mobile
        
        // Toggle Button Shape Options
        'toggle_shape' => 'circle',           // circle | pill | rounded_square | card
        'toggle_label' => '',                  // Optional text label (e.g., "Chat with us")
        'toggle_label_position' => 'right',    // left | right (for pill shape with icon+text)
        'toggle_icon' => 'chat',               // chat | message | support | custom
        'toggle_custom_icon' => '',            // Emoji or custom icon when toggle_icon is 'custom'
        
        // Toggle Visual Effects
        'toggle_shadow' => 'medium',           // none | small | medium | large
        'toggle_glow' => false,                // Glowing animation effect
        'toggle_pulse' => false,               // Subtle pulse animation
        'toggle_border' => false,              // Show border around toggle
        'toggle_border_color' => '#ffffff',    // Border color
    ];

    // Default trigger settings
    public const DEFAULT_TRIGGERS = [
        'auto_open_enabled' => false,
        'auto_open_delay' => 10,
        'exit_intent_enabled' => false,
        'exit_intent_message' => '',
        'scroll_depth_enabled' => false,
        'scroll_depth_percent' => 50,
        'time_on_page_enabled' => false,
        'time_on_page_seconds' => 30,
        'cart_abandonment_enabled' => false,
        'cart_abandonment_message' => '',
        'returning_visitor_greeting' => '',
    ];

    // Default display settings
    public const DEFAULT_DISPLAY = [
        'include_urls' => [],
        'exclude_urls' => [],
        'logged_in_only' => false,
        'guest_only' => false,
        'devices' => ['desktop', 'tablet', 'mobile'],
        'schedule_enabled' => false,
        'schedule_start' => '09:00',
        'schedule_end' => '17:00',
        'schedule_days' => [1, 2, 3, 4, 5],
    ];

    // Default engagement settings
    public const DEFAULT_ENGAGEMENT = [
        'quick_replies_enabled' => false,
        'quick_replies' => [],
        'sound_enabled' => false,
        'typing_indicator' => true,
        'session_persistence' => true,
        'powered_by' => false,
        'rate_limiting_enabled' => false,
        'rate_limit_per_minute' => 10,
    ];

    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    protected function hydrate(array $data): void
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->displayName = $data['display_name'] ?? '';
        $this->description = $data['description'] ?? '';
        $rawAppearance = $this->decodeJsonField($data['appearance'] ?? []);

        // Key Normalization for backward compatibility
        if (isset($rawAppearance['primary_color']) && !isset($rawAppearance['color_primary'])) {
            $rawAppearance['color_primary'] = $rawAppearance['primary_color'];
        }
        if (isset($rawAppearance['primary_color_hover']) && !isset($rawAppearance['color_primary_hover'])) {
            $rawAppearance['color_primary_hover'] = $rawAppearance['primary_color_hover'];
        }
        if (isset($rawAppearance['bg_color']) && !isset($rawAppearance['color_bg_main'])) {
            $rawAppearance['color_bg_main'] = $rawAppearance['bg_color'];
        }
        if (isset($rawAppearance['text_color']) && !isset($rawAppearance['color_text_primary'])) {
            $rawAppearance['color_text_primary'] = $rawAppearance['text_color'];
        }

        $this->appearance = array_merge(self::DEFAULT_APPEARANCE, $rawAppearance);
        $this->behavior = $this->decodeJsonField($data['behavior'] ?? []);
        $this->triggers = array_merge(self::DEFAULT_TRIGGERS, $this->decodeJsonField($data['triggers'] ?? []));
        $this->display = array_merge(self::DEFAULT_DISPLAY, $this->decodeJsonField($data['display'] ?? []));
        $this->engagement = array_merge(self::DEFAULT_ENGAGEMENT, $this->decodeJsonField($data['engagement'] ?? []));
        $this->agentId = $data['agent_id'] ?? '';
        $this->isActive = (bool) ($data['is_active'] ?? true);
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    protected function decodeJsonField($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    /**
     * Check if this widget uses a team instead of individual agent
     */
    public function usesTeam(): bool
    {
        return strpos($this->agentId, 'team:') === 0;
    }

    /**
     * Get the raw agent or team identifier (without prefix)
     */
    public function getAgentOrTeamId(): string
    {
        if (strpos($this->agentId, 'team:') === 0) {
            return substr($this->agentId, 5);
        }
        if (strpos($this->agentId, 'agent:') === 0) {
            return substr($this->agentId, 6);
        }
        // Legacy format - return as-is
        return $this->agentId;
    }

    /**
     * Get the associated team (if using team mode)
     */
    public function getTeam(): ?AgentGroup
    {
        if (!$this->usesTeam()) {
            return null;
        }
        
        $teamId = $this->getAgentOrTeamId();
        
        // Try by group_id first
        $team = AgentGroup::findBySlug($teamId);
        if ($team) {
            return $team;
        }
        
        // Try by database ID
        if (is_numeric($teamId)) {
            return AgentGroup::find((int) $teamId);
        }
        
        return null;
    }

    /**
     * Get the associated agent (if using single agent mode)
     */
    public function getAgent(): ?ChatAgent
    {
        if ($this->usesTeam()) {
            return null;
        }
        
        $agentId = $this->getAgentOrTeamId();
        if (empty($agentId)) {
            return null;
        }
        
        // Try by slug first
        $agent = ChatAgent::findBySlug($agentId);
        if ($agent) {
            return $agent;
        }
        
        // Try by numeric DB ID
        if (is_numeric($agentId)) {
            return ChatAgent::find((int) $agentId);
        }
        
        return null;
    }

    /**
     * Get all agents for this widget (single agent or team members)
     */
    public function getAgents(): array
    {
        if ($this->usesTeam()) {
            $team = $this->getTeam();
            return $team ? $team->getMemberAgents() : [];
        }
        
        $agent = $this->getAgent();
        return $agent ? [$agent] : [];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'appearance' => $this->appearance,
            'behavior' => $this->behavior,
            'triggers' => $this->triggers,
            'display' => $this->display,
            'engagement' => $this->engagement,
            'agent_id' => $this->agentId,
            'uses_team' => $this->usesTeam(),
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Get all widgets with optional pagination
     */
    public static function all(int $page = 1, int $perPage = 50): array
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $offset = ($page - 1) * $perPage;
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['chat_widgets']} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return array_map(fn($row) => new self($row), $rows ?: []);
    }

    /**
     * Count total widgets
     */
    public static function count(): int
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['chat_widgets']}");
    }

    public static function find(int $id): ?self
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['chat_widgets']} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    public static function findByName(string $name): ?self
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['chat_widgets']} WHERE name = %s", $name),
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Find active widgets
     */
    public static function findActive(): array
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $rows = $wpdb->get_results(
            "SELECT * FROM {$tables['chat_widgets']} WHERE is_active = 1 ORDER BY created_at DESC",
            ARRAY_A
        );

        return array_map(fn($row) => new self($row), $rows ?: []);
    }

    public function save(): bool
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        $data = [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'appearance' => wp_json_encode($this->appearance),
            'behavior' => wp_json_encode($this->behavior),
            'triggers' => wp_json_encode($this->triggers),
            'display' => wp_json_encode($this->display),
            'engagement' => wp_json_encode($this->engagement),
            'agent_id' => $this->agentId,
            'is_active' => $this->isActive ? 1 : 0,
        ];

        $format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'];

        if ($this->id > 0) {
            $result = $wpdb->update(
                $tables['chat_widgets'],
                $data,
                ['id' => $this->id],
                $format,
                ['%d']
            );
            return $result !== false;
        }

        $result = $wpdb->insert(
            $tables['chat_widgets'],
            $data,
            $format
        );

        if ($result) {
            $this->id = (int) $wpdb->insert_id;
            return true;
        }

        return false;
    }

    public function delete(): bool
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        if ($this->id <= 0) {
            return false;
        }

        // Delete assignments first
        $wpdb->delete($tables['widget_assignments'] ?? 'swc_widget_assignments', ['widget_id' => $this->id], ['%d']);
        
        // Delete widget
        $wpdb->delete($tables['chat_widgets'], ['id' => $this->id], ['%d']);
        return true;
    }

    /**
     * Check if widget should display on current page
     */
    public function shouldDisplay(array $context = []): bool
    {
        $display = $this->display;
        
        // Check if active
        if (!$this->isActive) {
            return false;
        }

        // Check user login status
        if ($display['logged_in_only'] && empty($context['user_id'])) {
            return false;
        }
        if ($display['guest_only'] && !empty($context['user_id'])) {
            return false;
        }

        // Check device
        if (!empty($display['devices']) && !empty($context['device'])) {
            if (!in_array($context['device'], $display['devices'])) {
                return false;
            }
        }

        // Check URL patterns
        $currentUrl = $context['url'] ?? '';
        if (!empty($currentUrl)) {
            // Exclude URLs
            foreach ($display['exclude_urls'] ?? [] as $pattern) {
                if ($this->matchUrlPattern($currentUrl, $pattern)) {
                    return false;
                }
            }
            
            // Include URLs (if set)
            if (!empty($display['include_urls'])) {
                $matched = false;
                foreach ($display['include_urls'] as $pattern) {
                    if ($this->matchUrlPattern($currentUrl, $pattern)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return false;
                }
            }
        }

        // Check schedule
        if ($display['schedule_enabled']) {
            $now = current_time('H:i');
            $dayOfWeek = (int) current_time('w');
            
            if (!in_array($dayOfWeek, $display['schedule_days'] ?? [])) {
                return false;
            }
            
            $start = $display['schedule_start'] ?? '09:00';
            $end = $display['schedule_end'] ?? '17:00';
            
            if ($now < $start || $now > $end) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match URL against pattern (supports wildcards and WooCommerce page types)
     */
    protected function matchUrlPattern(string $url, string $pattern): bool
    {
        $pattern = trim($pattern);
        
        // Check for WooCommerce page type keywords (e.g., "shop", "product", "cart", "checkout")
        $wcKeywords = ['shop', 'product', 'category', 'cart', 'checkout', 'account'];
        $lowerPattern = strtolower($pattern);
        
        // If pattern is a WooCommerce keyword, check against the appended page type
        if (in_array($lowerPattern, $wcKeywords)) {
            return strpos($url, '|wc:' . $lowerPattern) !== false;
        }
        
        // Also check if pattern like "/shop/*" should match the WooCommerce shop page
        if (strpos($lowerPattern, '/shop') === 0 && strpos($url, '|wc:shop') !== false) {
            return true;
        }
        if (strpos($lowerPattern, '/product') === 0 && strpos($url, '|wc:product') !== false) {
            return true;
        }
        if (strpos($lowerPattern, '/cart') === 0 && strpos($url, '|wc:cart') !== false) {
            return true;
        }
        if (strpos($lowerPattern, '/checkout') === 0 && strpos($url, '|wc:checkout') !== false) {
            return true;
        }
        
        // Clean URL for standard pattern matching (remove the WC type suffix)
        $cleanUrl = explode('|', $url)[0];
        
        // Convert wildcard pattern to regex
        $regex = '/^' . str_replace(
            ['*', '/'],
            ['.*', '\\/'],
            preg_quote($pattern, '/')
        ) . '$/i';
        
        return (bool) preg_match($regex, $cleanUrl);
    }
}
