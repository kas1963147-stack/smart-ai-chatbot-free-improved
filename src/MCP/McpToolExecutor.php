<?php
/**
 * MCP Tool Executor
 * 
 * Executes MCP tools by bridging to existing WordPress/toolkit functionality.
 * Maps MCP tool calls to the appropriate WordPress/WooCommerce APIs.
 * 
 * @package Quarksol\SmartChatbot\MCP
 */

namespace Quarksol\SmartChatbot\MCP;

if (!defined('ABSPATH')) { exit; }

if (!defined('ABSPATH')) { exit; }

/**
 * MCP Tool Executor
 * 
 * Bridges MCP tool calls to WordPress functionality.
 * Reuses existing toolkit implementations where possible.
 */
class McpToolExecutor
{
    /**
     * Current agent config context (set before tool execution)
     * Allows tool handlers to check agent-level settings like enabled skills.
     */
    private static ?\Quarksol\SmartChatbot\Config\AgentConfig $currentAgentConfig = null;

    /**
     * Set the agent config context for skill-aware tool execution
     */
    public static function setAgentContext(?\Quarksol\SmartChatbot\Config\AgentConfig $config): void
    {
        self::$currentAgentConfig = $config;
    }

    /**
     * Get the current agent config context
     */
    public static function getAgentContext(): ?\Quarksol\SmartChatbot\Config\AgentConfig
    {
        return self::$currentAgentConfig;
    }

    /**
     * Check if a specific skill is active on the current agent
     */
    private static function isSkillActive(string $skillName): bool
    {
        $config = self::$currentAgentConfig;
        if (!$config) {
            // No context = legacy call, allow by default
            return true;
        }

        // Delegate to AgentConfig's comprehensive skill check
        // which handles all modes: all, whitelist, blacklist, none
        return $config->isSkillEnabled($skillName);
    }

    /**
     * Execute a tool by name with given arguments
     */
    public static function execute(string $toolName, array $args): mixed
    {
        // Route to specific handler
        return match ($toolName) {
            // Core
            'mcp_ping' => self::ping(),
            'wp_search_content' => self::searchContent($args),
            'wp_get_site_info' => self::getSiteInfo($args),
            'request_human_support' => self::requestHumanSupport($args),

            // Posts
            'wp_get_posts' => self::getPosts($args),
            'wp_get_post' => self::getPost($args),
            'wp_create_post' => self::createPost($args),
            'wp_update_post' => self::updatePost($args),
            'wp_delete_post' => self::deletePost($args),
            'wp_get_post_meta' => self::getPostMeta($args),
            'wp_update_post_meta' => self::updatePostMeta($args),
            'wp_delete_post_meta' => self::deletePostMeta($args),
            'wp_get_revisions' => self::getRevisions($args),
            'wp_restore_revision' => self::restoreRevision($args),
            'wp_count_posts' => self::countPosts($args),
            'wp_get_post_types' => self::getPostTypes($args),

            // Pages
            'wp_get_pages' => self::getPages($args),
            'wp_create_page' => self::createPage($args),
            'wp_update_page' => self::updatePage($args),
            'wp_delete_page' => self::deletePage($args),

            // Comments
            'wp_get_comments' => self::getComments($args),
            'wp_create_comment' => self::createComment($args),
            'wp_update_comment' => self::updateComment($args),
            'wp_delete_comment' => self::deleteComment($args),

            // Users
            'wp_get_users' => self::getUsers($args),
            'wp_get_user' => self::getUser($args),
            'wp_get_current_user' => self::getCurrentUser($args),
            'wp_create_user_DISABLED' => self::createUser($args),
            'wp_update_user' => self::updateUser($args),
            'wp_get_user_meta' => self::getUserMeta($args),
            'wp_update_user_meta' => self::updateUserMeta($args),

            // Media
            'wp_get_media' => self::getMedia($args),
            'wp_get_media_item' => self::getMediaItem($args),
            'wp_upload_media' => self::uploadMedia($args),
            'wp_update_media' => self::updateMedia($args),
            'wp_delete_media' => self::deleteMedia($args),
            'wp_set_featured_image' => self::setFeaturedImage($args),

            // Taxonomies
            'wp_get_taxonomies' => self::getTaxonomies($args),
            'wp_get_terms' => self::getTerms($args),
            'wp_create_term' => self::createTerm($args),
            'wp_update_term' => self::updateTerm($args),
            'wp_delete_term' => self::deleteTerm($args),
            'wp_set_post_terms' => self::setPostTerms($args),
            'wp_get_categories' => self::getCategories($args),
            'wp_get_tags' => self::getTags($args),

            // Options
            'wp_get_option' => self::getOption($args),
            'wp_update_option' => self::updateOption($args),
            'wp_delete_option' => self::deleteOption($args),
            'wp_get_settings' => self::getSettings($args),
            'wp_update_settings' => self::updateSettings($args),

            // Plugins
            'wp_list_plugins' => self::listPlugins($args),
            'wp_activate_plugin' => self::activatePlugin($args),
            'wp_deactivate_plugin' => self::deactivatePlugin($args),

            // Themes
            'wp_get_themes' => self::getThemes($args),

            // Menus
            'wp_get_menus' => self::getMenus($args),
            'wp_get_menu_items' => self::getMenuItems($args),
            'wp_create_menu' => self::createMenu($args),
            'wp_add_menu_item' => self::addMenuItem($args),

            // System
            'wp_get_site_health' => self::getSiteHealth($args),
            'wp_flush_cache' => self::flushCache($args),
            'wp_get_transient' => self::getTransient($args),
            'wp_set_transient' => self::setTransient($args),
            'wp_delete_transient' => self::deleteTransient($args),
            'wp_get_cron_events' => self::getCronEvents($args),

            // WooCommerce Products
            'wc_get_products' => self::wcGetProducts($args),
            'wc_get_product' => self::wcGetProduct($args),
            'wc_create_product' => self::wcCreateProduct($args),
            'wc_update_product' => self::wcUpdateProduct($args),
            'wc_delete_product' => self::wcDeleteProduct($args),
            'wc_batch_update_products' => self::wcBatchUpdateProducts($args),
            'wc_get_product_categories' => self::wcGetProductCategories($args),
            'wc_create_product_category' => self::wcCreateProductCategory($args),
            'wc_get_product_tags' => self::wcGetProductTags($args),
            'wc_get_variations' => self::wcGetVariations($args),
            'wc_create_variation' => self::wcCreateVariation($args),
            'wc_get_reviews' => self::wcGetReviews($args),
            'wc_update_stock' => self::wcUpdateStock($args),
            'wc_get_low_stock' => self::wcGetLowStock($args),

            // WooCommerce Orders
            'wc_get_orders' => self::wcGetOrders($args),
            'wc_get_order' => self::wcGetOrder($args),
            'wc_create_order' => self::wcCreateOrder($args),
            'wc_update_order' => self::wcUpdateOrder($args),
            'wc_delete_order' => self::wcDeleteOrder($args),
            'wc_get_order_notes' => self::wcGetOrderNotes($args),
            'wc_add_order_note' => self::wcAddOrderNote($args),
            'wc_get_refunds' => self::wcGetRefunds($args),
            'wc_create_refund' => self::wcCreateRefund($args),

            // WooCommerce Customers
            'wc_get_customers' => self::wcGetCustomers($args),
            'wc_get_customer' => self::wcGetCustomer($args),
            'wc_create_customer' => self::wcCreateCustomer($args),
            'wc_update_customer' => self::wcUpdateCustomer($args),

            // WooCommerce Coupons
            'wc_get_coupons' => self::wcGetCoupons($args),
            'wc_get_coupon' => self::wcGetCoupon($args),
            'wc_create_coupon' => self::wcCreateCoupon($args),
            'wc_update_coupon' => self::wcUpdateCoupon($args),
            'wc_delete_coupon' => self::wcDeleteCoupon($args),

            // WooCommerce Shipping
            'wc_get_shipping_zones' => self::wcGetShippingZones($args),
            'wc_get_shipping_methods' => self::wcGetShippingMethods($args),
            'wc_create_shipping_zone' => self::wcCreateShippingZone($args),
            'wc_update_shipping_zone' => self::wcUpdateShippingZone($args),

            // WooCommerce Tax
            'wc_get_tax_classes' => self::wcGetTaxClasses($args),
            'wc_get_tax_rates' => self::wcGetTaxRates($args),
            'wc_create_tax_rate' => self::wcCreateTaxRate($args),
            'wc_update_tax_rate' => self::wcUpdateTaxRate($args),
            'wc_delete_tax_rate' => self::wcDeleteTaxRate($args),

            // WooCommerce Reports
            'wc_get_sales_report' => self::wcGetSalesReport($args),
            'wc_get_top_sellers' => self::wcGetTopSellers($args),
            'wc_get_orders_totals' => self::wcGetOrdersTotals($args),

            // WooCommerce Settings
            'wc_get_settings' => self::wcGetSettings($args),
            'wc_update_setting' => self::wcUpdateSetting($args),
            'wc_get_payment_gateways' => self::wcGetPaymentGateways($args),
            'wc_update_payment_gateway' => self::wcUpdatePaymentGateway($args),
            'wc_get_system_status' => self::wcGetSystemStatus($args),

            // Appointments, Leads, & Forms
            'appointment_booker' => self::appointmentBooker($args),
            'availability_checker' => self::availabilityChecker($args),
            'lead_collector' => self::leadCollector($args),
            'form_filler' => self::formFiller($args),

            // Extension tools (auto-detected plugins)
            default => self::executeExtensionTool($toolName, $args),
        };
    }

    /**
     * Execute an extension tool (Yoast, ACF, etc.)
     */
    private static function executeExtensionTool(string $toolName, array $args): mixed
    {
        // Check if this is an extension tool
        if (class_exists(\Quarksol\SmartChatbot\MCP\Extensions\McpExtensionRegistry::class)) {
            if (\Quarksol\SmartChatbot\MCP\Extensions\McpExtensionRegistry::isExtensionTool($toolName)) {
                return \Quarksol\SmartChatbot\MCP\Extensions\McpExtensionRegistry::executeTool($toolName, $args);
            }
        }

        throw new \Exception('Unknown tool: ' . $toolName);
    }

    // =========================================================================
    // APPOINTMENTS & LEADS
    // =========================================================================

    private static function appointmentBooker(array $args): mixed
    {
        if (!class_exists(\Toolkits\AppointmentBooking\AppointmentBookerTool::class)) {
            throw new \Exception('Appointment Booking Toolkit is not available');
        }

        $action = $args['action'] ?? '';
        $agentId = self::$currentAgentConfig?->agentId ?? 'unknown';
        $enabledSkills = self::$currentAgentConfig?->enabledSkills ?? [];
        $skillMode = self::$currentAgentConfig?->skillMode ?? 'unknown';
        $isActive = self::isSkillActive('appointment-booking');

        // === DETAILED LOGGING ===
        error_log('=== [SKILL_TRACE] APPOINTMENT BOOKER ===');
        error_log("[SKILL_TRACE]  Tool: appointment_booker | Action: {$action}");
        error_log("[SKILL_TRACE]  Agent: {$agentId} | Skill Mode: {$skillMode}");
        error_log("[SKILL_TRACE]  Enabled Skills: " . implode(', ', $enabledSkills));
        error_log("[SKILL_TRACE]  Skill 'appointment-booking' active: " . ($isActive ? 'YES' : 'NO'));
        error_log("[SKILL_TRACE]  Customer: " . ($args['customer_name'] ?? 'N/A') . " | Email: " . ($args['customer_email'] ?? 'N/A'));

        // Skill-active check: only allow booking if appointment-booking skill is active
        if ($action === 'book' && !$isActive) {
            error_log("[SKILL_TRACE] ❌ BLOCKED — redirecting to lead_collector");
            error_log('=== [SKILL_TRACE] END ===');
            return [
                'error' => 'Appointment booking is not enabled for this agent. '
                    . 'DO NOT try to book an appointment. Instead, use the lead_collector tool with action "capture" '
                    . 'to save this person as a lead so the admin team can follow up with them. '
                    . 'Collect their name, email, and a summary of what they need, then call lead_collector.',
                'redirect_to' => 'lead_collector',
                'suggestion' => 'Use lead_collector tool with action "capture" to save the customer contact info as a lead.',
            ];
        }

        error_log("[SKILL_TRACE] ✅ ALLOWED — booking appointment in swc_appointments table");
        error_log('=== [SKILL_TRACE] END ===');

        $tool = new \Toolkits\AppointmentBooking\AppointmentBookerTool();
        $result = $tool->__invoke(
            $action,
            isset($args['appointment_id']) ? (int)$args['appointment_id'] : null,
            $args['customer_name'] ?? null,
            $args['customer_email'] ?? null,
            $args['customer_phone'] ?? null,
            $args['appointment_type'] ?? null,
            $args['date'] ?? null,
            $args['time'] ?? null,
            isset($args['duration_minutes']) ? (int)$args['duration_minutes'] : null,
            $args['notes'] ?? null,
            $args['status'] ?? null,
            $args['date_from'] ?? null,
            $args['date_to'] ?? null
        );
        return json_decode($result, true) ?? ['error' => 'Invalid JSON returned from tool', 'raw' => $result];
    }

    private static function availabilityChecker(array $args): mixed
    {
        if (!class_exists(\Toolkits\AppointmentBooking\AvailabilityCheckerTool::class)) {
            throw new \Exception('Appointment Booking Toolkit is not available');
        }

        $tool = new \Toolkits\AppointmentBooking\AvailabilityCheckerTool();
        $result = $tool->__invoke(
            $args['action'] ?? '',
            $args['date'] ?? null,
            $args['time'] ?? null,
            $args['appointment_type'] ?? null,
            isset($args['duration_minutes']) ? (int)$args['duration_minutes'] : null
        );
        return json_decode($result, true) ?? ['error' => 'Invalid JSON returned from tool', 'raw' => $result];
    }

    private static function formFiller(array $args): mixed
    {
        if (!class_exists(\Toolkits\FormBuilder\FormFillerTool::class)) {
            throw new \Exception('Form Builder Toolkit is not available');
        }

        $tool = new \Toolkits\FormBuilder\FormFillerTool();

        // ── CRITICAL: Inject conversation/agent context ──────────────
        // Without this, every submit_field creates a NEW submission row
        // because getConversationId() returns null and getInProgress() fails.
        $agentSlug = null;
        $sessionId = null;
        if (class_exists(\Quarksol\SmartChatbot\Services\AgentContext::class)) {
            $agentSlug = \Quarksol\SmartChatbot\Services\AgentContext::getAgentSlug();
            $sessionId = \Quarksol\SmartChatbot\Services\AgentContext::getSessionId();
            $tool->setContext($agentSlug, $sessionId);
        }

        error_log("[FormFiller] === TOOL CALL === action={$args['action']} | agentSlug={$agentSlug} | sessionId={$sessionId} | form_id=" . ($args['form_id'] ?? 'null') . " | field_id=" . ($args['field_id'] ?? 'null') . " | submission_id=" . ($args['submission_id'] ?? 'null'));

        $result = $tool->__invoke(
            $args['action'] ?? '',
            isset($args['form_id']) ? (int)$args['form_id'] : null,
            $args['field_id'] ?? null,
            $args['value'] ?? null,
            isset($args['submission_id']) ? (int)$args['submission_id'] : null
        );

        error_log("[FormFiller] === RESULT === " . substr($result, 0, 300));

        return json_decode($result, true) ?? ['error' => 'Invalid JSON returned from tool', 'raw' => $result];
    }

    private static function leadCollector(array $args): mixed
    {
        if (!class_exists(\Toolkits\LeadCollection\LeadCollectorTool::class)) {
            throw new \Exception('Lead Collection Toolkit is not available');
        }

        $action = $args['action'] ?? '';
        $agentId = self::$currentAgentConfig?->agentId ?? 'unknown';
        $enabledSkills = self::$currentAgentConfig?->enabledSkills ?? [];
        $skillMode = self::$currentAgentConfig?->skillMode ?? 'unknown';

        // === DETAILED LOGGING ===
        error_log('=== [SKILL_TRACE] LEAD COLLECTOR ===');
        error_log("[SKILL_TRACE]  Tool: lead_collector | Action: {$action}");
        error_log("[SKILL_TRACE]  Agent: {$agentId} | Skill Mode: {$skillMode}");
        error_log("[SKILL_TRACE]  Enabled Skills: " . implode(', ', $enabledSkills));
        error_log("[SKILL_TRACE]  Customer: " . ($args['customer_name'] ?? 'N/A') . " | Email: " . ($args['customer_email'] ?? 'N/A'));
        error_log("[SKILL_TRACE] ✅ Saving to swc_leads table");
        error_log('=== [SKILL_TRACE] END ===');

        $tool = new \Toolkits\LeadCollection\LeadCollectorTool();
        $result = $tool->__invoke(
            $action,
            isset($args['lead_id']) ? (int)$args['lead_id'] : null,
            $args['customer_name'] ?? null,
            $args['customer_email'] ?? null,
            $args['customer_phone'] ?? null,
            $args['company'] ?? null,
            $args['request_summary'] ?? null,
            $args['lead_type'] ?? null,
            $args['priority'] ?? null,
            $args['notes'] ?? null,
            $args['status'] ?? null
        );
        return json_decode($result, true) ?? ['error' => 'Invalid JSON returned from tool', 'raw' => $result];
    }

    // =========================================================================
    // CORE
    // =========================================================================

    private static function ping(): array
    {
        return [
            'site_name' => get_bloginfo('name'),
            'gmt_time' => gmdate('c'),
            'wp_version' => get_bloginfo('version'),
            'mcp_version' => '1.0.0',
        ];
    }

    private static function requestHumanSupport(array $args): array
    {
        $rawNumber = trim($args['whatsapp_number'] ?? '');
        $number = preg_replace('/[^0-9]/', '', $rawNumber);
        
        // If they provided a placeholder like "web", "none", or "no" instead of a number, allow it to pass for Web Widget handoffs
        if (empty($number) && !preg_match('/^(web|none|no|n\/a|placeholder)$/i', $rawNumber)) {
            return [
                'error' => 'Please ask the user for a valid WhatsApp phone number before calling this tool. If they refuse or just want to use the web chat, pass "web" as the whatsapp_number.',
            ];
        }

        return [
            'status' => 'whatsapp_handoff',
            'whatsapp_number' => !empty($number) ? $number : 'web',
            'message' => 'Handoff triggered successfully. Do not output anything else. Tell the user they are being transferred.',
        ];
    }

    // =========================================================================
    // POSTS
    // =========================================================================

    private static function getPosts(array $args): array
    {
        $query = [
            'post_type' => $args['post_type'] ?? 'post',
            'post_status' => $args['post_status'] ?? 'publish',
            'posts_per_page' => min((int) ($args['limit'] ?? 10), 100),
            'offset' => (int) ($args['offset'] ?? 0),
        ];

        if (!empty($args['search'])) {
            $query['s'] = $args['search'];
        }

        if (!empty($args['after'])) {
            $query['date_query'][] = ['after' => $args['after']];
        }

        if (!empty($args['before'])) {
            $query['date_query'][] = ['before' => $args['before']];
        }

        $posts = get_posts($query);

        return [
            'total' => count($posts),
            'posts' => array_map(function ($post) {
                return [
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'excerpt' => wp_trim_words($post->post_content, 30),
                    'link' => get_permalink($post->ID),
                ];
            }, $posts),
        ];
    }

    private static function getPost(array $args): array
    {
        $id = (int) ($args['ID'] ?? 0);
        if (!$id) {
            throw new \Exception('ID is required');
        }

        $post = get_post($id);
        if (!$post) {
            throw new \Exception('Post not found');
        }

        return [
            'ID' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'author' => (int) $post->post_author,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'link' => get_permalink($post->ID),
            'meta' => get_post_meta($post->ID),
        ];
    }

    private static function createPost(array $args): array
    {
        if (!current_user_can('publish_posts')) {
            throw new \Exception('Permission denied');
        }

        $postTitle = trim((string) ($args['post_title'] ?? $args['title'] ?? ''));
        $postContent = (string) ($args['post_content'] ?? $args['content'] ?? '');
        $postStatus = (string) ($args['post_status'] ?? $args['status'] ?? 'draft');

        if ($postTitle === '') {
            throw new \Exception('post_title is required');
        }

        $postData = [
            'post_title' => sanitize_text_field($postTitle),
            'post_content' => wp_kses_post($postContent),
            'post_status' => sanitize_key($postStatus),
            'post_type' => sanitize_key($args['post_type'] ?? 'post'),
            'post_excerpt' => sanitize_textarea_field($args['post_excerpt'] ?? ''),
        ];

        if (!empty($args['meta_input']) && is_array($args['meta_input'])) {
            $postData['meta_input'] = $args['meta_input'];
        }

        $id = wp_insert_post($postData, true);

        if (is_wp_error($id)) {
            throw new \Exception($id->get_error_message());
        }

        return [
            'success' => true,
            'ID' => $id,
            'link' => get_permalink($id),
        ];
    }

    private static function updatePost(array $args): array
    {
        $id = (int) ($args['ID'] ?? 0);
        if (!$id) {
            throw new \Exception('ID is required');
        }

        if (!current_user_can('edit_post', $id)) {
            throw new \Exception('Permission denied');
        }

        $fields = $args['fields'] ?? [];
        $postData = ['ID' => $id];

        $allowedFields = ['post_title', 'post_content', 'post_status', 'post_excerpt'];
        foreach ($allowedFields as $field) {
            if (isset($fields[$field])) {
                $postData[$field] = $fields[$field];
            }
        }

        if (!empty($args['meta_input']) && is_array($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($id, $key, $value);
            }
        }

        $result = wp_update_post($postData, true);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        return [
            'success' => true,
            'ID' => $id,
        ];
    }

    private static function deletePost(array $args): array
    {
        $id = (int) ($args['ID'] ?? 0);
        if (!$id) {
            throw new \Exception('ID is required');
        }

        if (!current_user_can('delete_post', $id)) {
            throw new \Exception('Permission denied');
        }

        $force = (bool) ($args['force'] ?? false);
        $result = wp_delete_post($id, $force);

        if (!$result) {
            throw new \Exception('Failed to delete post');
        }

        return [
            'success' => true,
            'ID' => $id,
            'permanently_deleted' => $force,
        ];
    }

    // =========================================================================
    // PAGES
    // =========================================================================

    private static function getPages(array $args): array
    {
        $args['post_type'] = 'page';
        return self::getPosts($args);
    }

    private static function createPage(array $args): array
    {
        $args['post_type'] = 'page';

        if (!empty($args['post_parent'])) {
            $args['fields']['post_parent'] = (int) $args['post_parent'];
        }

        return self::createPost($args);
    }

    // =========================================================================
    // COMMENTS
    // =========================================================================

    private static function getComments(array $args): array
    {
        $query = [
            'number' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['post_id'])) {
            $query['post_id'] = (int) $args['post_id'];
        }

        if (!empty($args['status'])) {
            $query['status'] = $args['status'];
        }

        if (!empty($args['search'])) {
            $query['search'] = $args['search'];
        }

        $comments = get_comments($query);

        return [
            'total' => count($comments),
            'comments' => array_map(function ($comment) {
                return [
                    'comment_ID' => $comment->comment_ID,
                    'post_id' => $comment->comment_post_ID,
                    'author' => $comment->comment_author,
                    'author_email' => $comment->comment_author_email,
                    'content' => $comment->comment_content,
                    'date' => $comment->comment_date,
                    'approved' => $comment->comment_approved,
                ];
            }, $comments),
        ];
    }

    private static function createComment(array $args): array
    {
        if (!current_user_can('moderate_comments')) {
            throw new \Exception('Permission denied');
        }

        $commentData = [
            'comment_post_ID' => (int) ($args['post_id'] ?? 0),
            'comment_content' => sanitize_textarea_field($args['comment_content'] ?? ''),
            'comment_author' => sanitize_text_field($args['comment_author'] ?? ''),
            'comment_author_email' => sanitize_email($args['comment_author_email'] ?? ''),
            'comment_approved' => 1,
        ];

        $id = wp_insert_comment($commentData);

        if (!$id) {
            throw new \Exception('Failed to create comment');
        }

        return [
            'success' => true,
            'comment_ID' => $id,
        ];
    }

    // =========================================================================
    // USERS
    // =========================================================================

    private static function getUsers(array $args): array
    {
        if (!current_user_can('list_users')) {
            throw new \Exception('Permission denied');
        }

        $query = [
            'number' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['role'])) {
            $query['role'] = $args['role'];
        }

        if (!empty($args['search'])) {
            $query['search'] = '*' . $args['search'] . '*';
        }

        $users = get_users($query);

        return [
            'total' => count($users),
            'users' => array_map(function ($user) {
                return [
                    'ID' => $user->ID,
                    'login' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'roles' => $user->roles,
                    'registered' => $user->user_registered,
                ];
            }, $users),
        ];
    }

    // =========================================================================
    // MEDIA
    // =========================================================================

    private static function getMedia(array $args): array
    {
        $query = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['mime_type'])) {
            $query['post_mime_type'] = $args['mime_type'];
        }

        if (!empty($args['search'])) {
            $query['s'] = $args['search'];
        }

        $attachments = get_posts($query);

        return [
            'total' => count($attachments),
            'media' => array_map(function ($attachment) {
                return [
                    'ID' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'url' => wp_get_attachment_url($attachment->ID),
                    'mime_type' => $attachment->post_mime_type,
                    'date' => $attachment->post_date,
                ];
            }, $attachments),
        ];
    }

    private static function uploadMedia(array $args): array
    {
        if (!current_user_can('upload_files')) {
            throw new \Exception('Permission denied');
        }

        // For now, just return a placeholder - full implementation requires more work
        return [
            'error' => 'Media upload via URL/base64 not yet implemented',
        ];
    }

    // =========================================================================
    // TAXONOMIES
    // =========================================================================

    private static function getTerms(array $args): array
    {
        $taxonomy = $args['taxonomy'] ?? '';
        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            throw new \Exception('Invalid taxonomy');
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => (bool) ($args['hide_empty'] ?? false),
            'number' => min((int) ($args['limit'] ?? 50), 200),
            'search' => $args['search'] ?? '',
        ]);

        if (is_wp_error($terms)) {
            throw new \Exception($terms->get_error_message());
        }

        return [
            'total' => count($terms),
            'terms' => array_map(function ($term) {
                return [
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'parent' => $term->parent,
                    'count' => $term->count,
                ];
            }, $terms),
        ];
    }

    private static function createTerm(array $args): array
    {
        $taxonomy = $args['taxonomy'] ?? '';
        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            throw new \Exception('Invalid taxonomy');
        }

        if (!current_user_can('manage_categories')) {
            throw new \Exception('Permission denied');
        }

        $result = wp_insert_term(
            sanitize_text_field($args['name'] ?? ''),
            $taxonomy,
            [
                'slug' => sanitize_title($args['slug'] ?? ''),
                'description' => sanitize_textarea_field($args['description'] ?? ''),
                'parent' => (int) ($args['parent'] ?? 0),
            ]
        );

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        return [
            'success' => true,
            'term_id' => $result['term_id'],
        ];
    }

    // =========================================================================
    // OPTIONS
    // =========================================================================

    private static function getOption(array $args): array
    {
        $key = $args['key'] ?? '';
        if (!$key) {
            throw new \Exception('Key is required');
        }

        // Block sensitive options
        $blocked = ['admin_password', 'db_password', 'secret_key'];
        if (in_array(strtolower($key), $blocked)) {
            throw new \Exception('Access to this option is blocked');
        }

        $value = get_option($key);

        return [
            'key' => $key,
            'value' => $value,
            'exists' => $value !== false,
        ];
    }

    private static function updateOption(array $args): array
    {
        $key = $args['key'] ?? '';
        if (!$key) {
            throw new \Exception('Key is required');
        }

        if (!current_user_can('manage_options')) {
            throw new \Exception('Permission denied');
        }

        // Block dangerous options
        $blocked = ['admin_email', 'siteurl', 'home', 'users_can_register'];
        if (in_array(strtolower($key), $blocked)) {
            throw new \Exception('Modification of this option is blocked');
        }

        $result = update_option($key, $args['value']);

        return [
            'success' => $result,
            'key' => $key,
        ];
    }

    // =========================================================================
    // PLUGINS
    // =========================================================================

    private static function listPlugins(array $args): array
    {
        if (!function_exists('get_plugins')) {
            require_once \ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active = get_option('active_plugins', []);

        $result = [];
        foreach ($plugins as $file => $data) {
            $isActive = in_array($file, $active);

            // Apply filter
            if (isset($args['status'])) {
                if ($args['status'] === 'active' && !$isActive)
                    continue;
                if ($args['status'] === 'inactive' && $isActive)
                    continue;
            }

            // Apply search
            if (!empty($args['search'])) {
                $search = strtolower($args['search']);
                if (stripos($data['Name'], $search) === false && stripos($file, $search) === false) {
                    continue;
                }
            }

            $result[] = [
                'file' => $file,
                'name' => $data['Name'],
                'version' => $data['Version'],
                'author' => strip_tags($data['Author']),
                'active' => $isActive,
            ];
        }

        return [
            'total' => count($result),
            'plugins' => $result,
        ];
    }

    // =========================================================================
    // THEMES
    // =========================================================================

    private static function getThemes(array $args): array
    {
        $themes = wp_get_themes();
        $activeTheme = get_stylesheet();

        return [
            'active' => $activeTheme,
            'total' => count($themes),
            'themes' => array_map(function ($theme) use ($activeTheme) {
                return [
                    'name' => $theme->get('Name'),
                    'slug' => $theme->get_stylesheet(),
                    'version' => $theme->get('Version'),
                    'author' => $theme->get('Author'),
                    'active' => $theme->get_stylesheet() === $activeTheme,
                ];
            }, array_values($themes)),
        ];
    }

    // =========================================================================
    // SYSTEM
    // =========================================================================

    private static function getSiteHealth(array $args): array
    {
        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'is_multisite' => is_multisite(),
            'active_theme' => get_stylesheet(),
            'active_plugins_count' => count(get_option('active_plugins', [])),
            'permalink_structure' => get_option('permalink_structure'),
        ];
    }

    // =========================================================================
    // WOOCOMMERCE
    // =========================================================================

    private static function wcGetProducts(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $query = [
            'post_type' => 'product',
            'post_status' => $args['status'] ?? 'publish',
            'posts_per_page' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['search'])) {
            $query['s'] = $args['search'];
        }

        $products = get_posts($query);

        return [
            'total' => count($products),
            'products' => array_map(function ($product) {
                $wc_product = wc_get_product($product->ID);
                return [
                    'ID' => $product->ID,
                    'name' => $product->post_title,
                    'price' => $wc_product ? $wc_product->get_price() : null,
                    'regular_price' => $wc_product ? $wc_product->get_regular_price() : null,
                    'sku' => $wc_product ? $wc_product->get_sku() : null,
                    'stock_status' => $wc_product ? $wc_product->get_stock_status() : null,
                    'type' => $wc_product ? $wc_product->get_type() : null,
                    'link' => get_permalink($product->ID),
                ];
            }, $products),
        ];
    }

    private static function wcCreateProduct(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        if (!current_user_can('publish_products')) {
            throw new \Exception('Permission denied');
        }

        $product = new \WC_Product_Simple();
        $product->set_name(sanitize_text_field($args['name'] ?? ''));
        $product->set_regular_price($args['regular_price'] ?? '');
        $product->set_description(wp_kses_post($args['description'] ?? ''));
        $product->set_short_description(wp_kses_post($args['short_description'] ?? ''));

        if (!empty($args['sku'])) {
            $product->set_sku(sanitize_text_field($args['sku']));
        }

        $id = $product->save();

        return [
            'success' => true,
            'ID' => $id,
            'link' => get_permalink($id),
        ];
    }

    private static function wcGetOrders(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $query = [
            'limit' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['status'])) {
            $query['status'] = $args['status'];
        }

        if (!empty($args['customer_id'])) {
            $query['customer_id'] = (int) $args['customer_id'];
        }

        $orders = wc_get_orders($query);

        return [
            'total' => count($orders),
            'orders' => array_map(function ($order) {
                return [
                    'ID' => $order->get_id(),
                    'status' => $order->get_status(),
                    'total' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'customer_id' => $order->get_customer_id(),
                    'date_created' => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
                    'items_count' => $order->get_item_count(),
                ];
            }, $orders),
        ];
    }

    private static function wcUpdateOrder(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $orderId = (int) ($args['order_id'] ?? 0);
        if (!$orderId) {
            throw new \Exception('order_id is required');
        }

        if (!current_user_can('edit_shop_orders')) {
            throw new \Exception('Permission denied');
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            throw new \Exception('Order not found');
        }

        if (!empty($args['status'])) {
            $order->set_status(sanitize_key($args['status']));
        }

        if (!empty($args['note'])) {
            $order->add_order_note(sanitize_textarea_field($args['note']));
        }

        $order->save();

        return [
            'success' => true,
            'ID' => $orderId,
            'status' => $order->get_status(),
        ];
    }

    // =========================================================================
    // ADDITIONAL POST OPERATIONS
    // =========================================================================

    private static function getPostMeta(array $args): array
    {
        $postId = (int) ($args['post_id'] ?? 0);
        if (!$postId) {
            throw new \Exception('post_id is required');
        }

        $key = $args['meta_key'] ?? '';
        if ($key) {
            $value = get_post_meta($postId, $key, true);
            return ['key' => $key, 'value' => $value];
        }

        return ['meta' => get_post_meta($postId)];
    }

    private static function updatePostMeta(array $args): array
    {
        $postId = (int) ($args['post_id'] ?? 0);
        if (!$postId || !current_user_can('edit_post', $postId)) {
            throw new \Exception('Permission denied');
        }

        $key = sanitize_key($args['meta_key'] ?? '');
        $value = $args['meta_value'] ?? '';

        update_post_meta($postId, $key, $value);

        return ['success' => true, 'post_id' => $postId, 'key' => $key];
    }

    private static function getRevisions(array $args): array
    {
        $postId = (int) ($args['post_id'] ?? 0);
        if (!$postId) {
            throw new \Exception('post_id is required');
        }

        $revisions = wp_get_post_revisions($postId, [
            'posts_per_page' => min((int) ($args['limit'] ?? 10), 50),
        ]);

        return [
            'total' => count($revisions),
            'revisions' => array_map(function ($rev) {
                return [
                    'ID' => $rev->ID,
                    'date' => $rev->post_date,
                    'author' => (int) $rev->post_author,
                    'title' => $rev->post_title,
                ];
            }, array_values($revisions)),
        ];
    }

    // =========================================================================
    // ADDITIONAL PAGE OPERATIONS
    // =========================================================================

    private static function updatePage(array $args): array
    {
        $args['fields'] = array_filter([
            'post_title' => $args['post_title'] ?? null,
            'post_content' => $args['post_content'] ?? null,
            'post_status' => $args['post_status'] ?? null,
        ]);
        return self::updatePost($args);
    }

    private static function deletePage(array $args): array
    {
        return self::deletePost($args);
    }

    // =========================================================================
    // ADDITIONAL COMMENT OPERATIONS
    // =========================================================================

    private static function updateComment(array $args): array
    {
        $commentId = (int) ($args['comment_id'] ?? 0);
        if (!$commentId || !current_user_can('moderate_comments')) {
            throw new \Exception('Permission denied');
        }

        $data = ['comment_ID' => $commentId];
        if (isset($args['status'])) {
            wp_set_comment_status($commentId, $args['status']);
        }
        if (isset($args['content'])) {
            $data['comment_content'] = sanitize_textarea_field($args['content']);
        }

        wp_update_comment($data);

        return ['success' => true, 'comment_ID' => $commentId];
    }

    private static function deleteComment(array $args): array
    {
        $commentId = (int) ($args['comment_id'] ?? 0);
        if (!$commentId || !current_user_can('moderate_comments')) {
            throw new \Exception('Permission denied');
        }

        $force = (bool) ($args['force'] ?? false);
        wp_delete_comment($commentId, $force);

        return ['success' => true, 'comment_ID' => $commentId];
    }

    // =========================================================================
    // ADDITIONAL USER OPERATIONS
    // =========================================================================

    private static function getUser(array $args): array
    {
        $userId = (int) ($args['user_id'] ?? 0);
        if (!$userId) {
            throw new \Exception('user_id is required');
        }

        $user = get_userdata($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        return [
            'ID' => $user->ID,
            'login' => $user->user_login,
            'email' => current_user_can('list_users') ? $user->user_email : '[hidden]',
            'display_name' => $user->display_name,
            'roles' => $user->roles,
            'registered' => $user->user_registered,
        ];
    }

    private static function getCurrentUser(array $args): array
    {
        $user = wp_get_current_user();
        if (!$user->ID) {
            return ['logged_in' => false];
        }

        return [
            'logged_in' => true,
            'ID' => $user->ID,
            'login' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
        ];
    }

    // =========================================================================
    // ADDITIONAL MEDIA OPERATIONS
    // =========================================================================

    private static function updateMedia(array $args): array
    {
        $attachmentId = (int) ($args['attachment_id'] ?? 0);
        if (!$attachmentId || !current_user_can('edit_post', $attachmentId)) {
            throw new \Exception('Permission denied');
        }

        $postData = ['ID' => $attachmentId];
        if (isset($args['title'])) {
            $postData['post_title'] = sanitize_text_field($args['title']);
        }
        if (isset($args['caption'])) {
            $postData['post_excerpt'] = sanitize_textarea_field($args['caption']);
        }
        if (isset($args['description'])) {
            $postData['post_content'] = sanitize_textarea_field($args['description']);
        }

        wp_update_post($postData);

        if (isset($args['alt_text'])) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($args['alt_text']));
        }

        return ['success' => true, 'ID' => $attachmentId];
    }

    private static function deleteMedia(array $args): array
    {
        $attachmentId = (int) ($args['attachment_id'] ?? 0);
        if (!$attachmentId || !current_user_can('delete_post', $attachmentId)) {
            throw new \Exception('Permission denied');
        }

        $force = (bool) ($args['force'] ?? false);
        wp_delete_attachment($attachmentId, $force);

        return ['success' => true, 'ID' => $attachmentId];
    }

    // =========================================================================
    // ADDITIONAL TAXONOMY OPERATIONS
    // =========================================================================

    private static function updateTerm(array $args): array
    {
        $termId = (int) ($args['term_id'] ?? 0);
        $taxonomy = $args['taxonomy'] ?? '';

        if (!$termId || !$taxonomy || !current_user_can('manage_categories')) {
            throw new \Exception('Permission denied');
        }

        $data = [];
        if (isset($args['name'])) {
            $data['name'] = sanitize_text_field($args['name']);
        }
        if (isset($args['slug'])) {
            $data['slug'] = sanitize_title($args['slug']);
        }
        if (isset($args['description'])) {
            $data['description'] = sanitize_textarea_field($args['description']);
        }

        $result = wp_update_term($termId, $taxonomy, $data);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        return ['success' => true, 'term_id' => $termId];
    }

    private static function deleteTerm(array $args): array
    {
        $termId = (int) ($args['term_id'] ?? 0);
        $taxonomy = $args['taxonomy'] ?? '';

        if (!$termId || !$taxonomy || !current_user_can('manage_categories')) {
            throw new \Exception('Permission denied');
        }

        $result = wp_delete_term($termId, $taxonomy);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        return ['success' => true, 'term_id' => $termId];
    }

    private static function setPostTerms(array $args): array
    {
        $postId = (int) ($args['post_id'] ?? 0);
        $taxonomy = $args['taxonomy'] ?? '';
        $terms = $args['terms'] ?? [];
        $append = (bool) ($args['append'] ?? false);

        if (!$postId || !current_user_can('edit_post', $postId)) {
            throw new \Exception('Permission denied');
        }

        $result = wp_set_post_terms($postId, $terms, $taxonomy, $append);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        return ['success' => true, 'post_id' => $postId, 'terms_set' => count($result)];
    }

    // =========================================================================
    // ADDITIONAL PLUGIN OPERATIONS
    // =========================================================================

    private static function activatePlugin(array $args): array
    {
        if (!current_user_can('activate_plugins')) {
            throw new \Exception('Permission denied');
        }

        $plugin = $args['plugin'] ?? '';
        if (!$plugin) {
            throw new \Exception('plugin is required');
        }

        $result = activate_plugin($plugin);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        return ['success' => true, 'plugin' => $plugin];
    }

    private static function deactivatePlugin(array $args): array
    {
        if (!current_user_can('deactivate_plugins')) {
            throw new \Exception('Permission denied');
        }

        $plugin = $args['plugin'] ?? '';
        if (!$plugin) {
            throw new \Exception('plugin is required');
        }

        deactivate_plugins($plugin);

        return ['success' => true, 'plugin' => $plugin];
    }

    // =========================================================================
    // MENUS
    // =========================================================================

    private static function getMenus(array $args): array
    {
        $menus = wp_get_nav_menus();

        return [
            'total' => count($menus),
            'menus' => array_map(function ($menu) {
                return [
                    'term_id' => $menu->term_id,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'count' => $menu->count,
                ];
            }, $menus),
        ];
    }

    private static function getMenuItems(array $args): array
    {
        $menuId = (int) ($args['menu_id'] ?? 0);
        if (!$menuId) {
            throw new \Exception('menu_id is required');
        }

        $items = wp_get_nav_menu_items($menuId);
        if (!$items) {
            return ['total' => 0, 'items' => []];
        }

        return [
            'total' => count($items),
            'items' => array_map(function ($item) {
                return [
                    'ID' => $item->ID,
                    'title' => $item->title,
                    'url' => $item->url,
                    'type' => $item->type,
                    'object' => $item->object,
                    'parent' => (int) $item->menu_item_parent,
                    'order' => (int) $item->menu_order,
                ];
            }, $items),
        ];
    }

    // =========================================================================
    // ADDITIONAL SYSTEM OPERATIONS
    // =========================================================================

    private static function getSiteInfo(array $args): array
    {
        return [
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => get_site_url(),
            'home' => get_home_url(),
            'admin_email' => get_option('admin_email'),
            'language' => get_bloginfo('language'),
            'timezone' => get_option('timezone_string') ?: 'UTC',
        ];
    }

    private static function flushCache(array $args): array
    {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Permission denied');
        }

        wp_cache_flush();

        return ['success' => true, 'message' => 'Cache flushed'];
    }

    private static function getTransient(array $args): array
    {
        $key = $args['key'] ?? '';
        if (!$key) {
            throw new \Exception('key is required');
        }

        $value = get_transient($key);

        return [
            'key' => $key,
            'value' => $value,
            'exists' => $value !== false,
        ];
    }

    private static function setTransient(array $args): array
    {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Permission denied');
        }

        $key = $args['key'] ?? '';
        $value = $args['value'] ?? '';
        $expiration = (int) ($args['expiration'] ?? 3600);

        $result = set_transient($key, $value, $expiration);

        return ['success' => $result, 'key' => $key];
    }

    private static function deleteTransient(array $args): array
    {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Permission denied');
        }

        $key = $args['key'] ?? '';
        $result = delete_transient($key);

        return ['success' => $result, 'key' => $key];
    }

    private static function searchContent(array $args): array
    {
        $query = $args['query'] ?? '';
        if (!$query) {
            throw new \Exception('query is required');
        }

        $postTypes = $args['post_types'] ?? ['post', 'page'];
        $limit = min((int) ($args['limit'] ?? 20), 100);

        $posts = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $limit,
        ]);

        return [
            'total' => count($posts),
            'results' => array_map(function ($post) {
                return [
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'excerpt' => wp_trim_words($post->post_content, 20),
                    'link' => get_permalink($post->ID),
                ];
            }, $posts),
        ];
    }

    // =========================================================================
    // ADDITIONAL WOOCOMMERCE OPERATIONS
    // =========================================================================

    private static function wcGetProduct(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $productId = (int) ($args['product_id'] ?? 0);
        if (!$productId) {
            throw new \Exception('product_id is required');
        }

        $product = wc_get_product($productId);
        if (!$product) {
            throw new \Exception('Product not found');
        }

        return [
            'ID' => $product->get_id(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'sku' => $product->get_sku(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'link' => get_permalink($productId),
        ];
    }

    private static function wcUpdateProduct(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $productId = (int) ($args['product_id'] ?? 0);
        if (!$productId || !current_user_can('edit_products')) {
            throw new \Exception('Permission denied');
        }

        $product = wc_get_product($productId);
        if (!$product) {
            throw new \Exception('Product not found');
        }

        if (isset($args['name'])) {
            $product->set_name(sanitize_text_field($args['name']));
        }
        if (isset($args['regular_price'])) {
            $product->set_regular_price($args['regular_price']);
        }
        if (isset($args['sale_price'])) {
            $product->set_sale_price($args['sale_price']);
        }
        if (isset($args['description'])) {
            $product->set_description(wp_kses_post($args['description']));
        }
        if (isset($args['stock_quantity'])) {
            $product->set_stock_quantity((int) $args['stock_quantity']);
        }
        if (isset($args['stock_status'])) {
            $product->set_stock_status($args['stock_status']);
        }

        $product->save();

        return ['success' => true, 'ID' => $productId];
    }

    private static function wcDeleteProduct(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $productId = (int) ($args['product_id'] ?? 0);
        if (!$productId || !current_user_can('delete_products')) {
            throw new \Exception('Permission denied');
        }

        $product = wc_get_product($productId);
        if (!$product) {
            throw new \Exception('Product not found');
        }

        $force = (bool) ($args['force'] ?? false);
        $product->delete($force);

        return ['success' => true, 'ID' => $productId];
    }

    private static function wcGetProductCategories(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => (bool) ($args['hide_empty'] ?? false),
            'parent' => isset($args['parent']) ? (int) $args['parent'] : '',
        ]);

        if (is_wp_error($terms)) {
            throw new \Exception($terms->get_error_message());
        }

        return [
            'total' => count($terms),
            'categories' => array_map(function ($term) {
                return [
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'parent' => $term->parent,
                    'count' => $term->count,
                ];
            }, $terms),
        ];
    }

    private static function wcGetReviews(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $query = [
            'type' => 'review',
            'number' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['product_id'])) {
            $query['post_id'] = (int) $args['product_id'];
        }
        if (!empty($args['status'])) {
            $query['status'] = $args['status'];
        }

        $reviews = get_comments($query);

        return [
            'total' => count($reviews),
            'reviews' => array_map(function ($review) {
                return [
                    'ID' => $review->comment_ID,
                    'product_id' => $review->comment_post_ID,
                    'author' => $review->comment_author,
                    'rating' => (int) get_comment_meta($review->comment_ID, 'rating', true),
                    'content' => $review->comment_content,
                    'date' => $review->comment_date,
                    'approved' => $review->comment_approved,
                ];
            }, $reviews),
        ];
    }

    private static function wcGetOrder(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $orderId = (int) ($args['order_id'] ?? 0);
        if (!$orderId) {
            throw new \Exception('order_id is required');
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            throw new \Exception('Order not found');
        }

        return [
            'ID' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_id' => $order->get_customer_id(),
            'billing' => [
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'items' => array_map(function ($item) {
                return [
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                ];
            }, array_values($order->get_items())),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
        ];
    }

    private static function wcGetCustomers(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        if (!current_user_can('list_users')) {
            throw new \Exception('Permission denied');
        }

        $query = [
            'role' => 'customer',
            'number' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['email'])) {
            $query['search'] = '*' . $args['email'] . '*';
            $query['search_columns'] = ['user_email'];
        }
        if (!empty($args['search'])) {
            $query['search'] = '*' . $args['search'] . '*';
        }

        $users = get_users($query);

        return [
            'total' => count($users),
            'customers' => array_map(function ($user) {
                return [
                    'ID' => $user->ID,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'registered' => $user->user_registered,
                ];
            }, $users),
        ];
    }

    private static function wcGetCoupons(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        $query = [
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'posts_per_page' => min((int) ($args['limit'] ?? 10), 100),
        ];

        if (!empty($args['code'])) {
            $query['s'] = $args['code'];
        }

        $posts = get_posts($query);

        return [
            'total' => count($posts),
            'coupons' => array_map(function ($post) {
                $coupon = new \WC_Coupon($post->ID);
                return [
                    'ID' => $post->ID,
                    'code' => $coupon->get_code(),
                    'amount' => $coupon->get_amount(),
                    'discount_type' => $coupon->get_discount_type(),
                    'usage_count' => $coupon->get_usage_count(),
                    'usage_limit' => $coupon->get_usage_limit(),
                ];
            }, $posts),
        ];
    }

    private static function wcCreateCoupon(array $args): array
    {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }

        if (!current_user_can('publish_shop_coupons')) {
            throw new \Exception('Permission denied');
        }

        $coupon = new \WC_Coupon();
        $coupon->set_code(sanitize_text_field($args['code'] ?? ''));
        $coupon->set_amount($args['amount'] ?? '');
        $coupon->set_discount_type($args['discount_type'] ?? 'percent');

        if (isset($args['description'])) {
            $coupon->set_description(sanitize_textarea_field($args['description']));
        }
        if (isset($args['usage_limit'])) {
            $coupon->set_usage_limit((int) $args['usage_limit']);
        }
        if (isset($args['expiry_date'])) {
            $coupon->set_date_expires($args['expiry_date']);
        }

        $id = $coupon->save();

        return [
            'success' => true,
            'ID' => $id,
            'code' => $coupon->get_code(),
        ];
    }

    // =========================================================================
    // NEW: MISSING WORDPRESS HANDLERS
    // =========================================================================

    private static function deletePostMeta(array $args): array
    {
        $postId = (int) ($args['post_id'] ?? 0);
        $key = $args['meta_key'] ?? '';
        if (!$postId || !$key || !current_user_can('edit_post', $postId)) {
            throw new \Exception('Permission denied or missing parameters');
        }
        delete_post_meta($postId, sanitize_key($key));
        return ['success' => true, 'post_id' => $postId, 'key' => $key];
    }

    private static function restoreRevision(array $args): array
    {
        $revisionId = (int) ($args['revision_id'] ?? 0);
        if (!$revisionId) {
            throw new \Exception('revision_id is required');
        }
        $revision = wp_get_post_revision($revisionId);
        if (!$revision || !current_user_can('edit_post', $revision->post_parent)) {
            throw new \Exception('Permission denied or revision not found');
        }
        $result = wp_restore_post_revision($revisionId);
        if (!$result) {
            throw new \Exception('Failed to restore revision');
        }
        return ['success' => true, 'revision_id' => $revisionId, 'post_id' => $revision->post_parent];
    }

    private static function countPosts(array $args): array
    {
        $postType = $args['post_type'] ?? 'post';
        $counts = wp_count_posts($postType);
        return [
            'post_type' => $postType,
            'counts' => (array) $counts,
            'total' => array_sum((array) $counts),
        ];
    }

    private static function getPostTypes(array $args): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        return [
            'total' => count($postTypes),
            'post_types' => array_map(function ($pt) {
                return [
                    'name' => $pt->name,
                    'label' => $pt->label,
                    'hierarchical' => $pt->hierarchical,
                    'has_archive' => $pt->has_archive,
                    'supports' => get_all_post_type_supports($pt->name),
                ];
            }, array_values($postTypes)),
        ];
    }

    private static function createUser(array $args): array
    {
        if (!current_user_can('create_users')) {
            throw new \Exception('Permission denied');
        }
        $userData = [
            'user_login' => sanitize_user($args['user_login'] ?? ''),
            'user_email' => sanitize_email($args['user_email'] ?? ''),
            'user_pass' => $args['user_pass'] ?? wp_generate_password(),
            'role' => sanitize_key($args['role'] ?? 'subscriber'),
        ];
        if (!empty($args['display_name'])) {
            $userData['display_name'] = sanitize_text_field($args['display_name']);
        }
        if (!empty($args['first_name'])) {
            $userData['first_name'] = sanitize_text_field($args['first_name']);
        }
        if (!empty($args['last_name'])) {
            $userData['last_name'] = sanitize_text_field($args['last_name']);
        }
        $userId = wp_insert_user_DISABLED($userData);
        if (is_wp_error($userId)) {
            throw new \Exception($userId->get_error_message());
        }
        return ['success' => true, 'ID' => $userId];
    }

    private static function updateUser(array $args): array
    {
        $userId = (int) ($args['user_id'] ?? 0);
        if (!$userId || !current_user_can('edit_users')) {
            throw new \Exception('Permission denied');
        }
        $fields = $args['fields'] ?? $args;
        $userData = ['ID' => $userId];
        $allowed = ['display_name', 'first_name', 'last_name', 'user_email', 'user_url', 'description', 'role'];
        foreach ($allowed as $field) {
            if (isset($fields[$field])) {
                $userData[$field] = sanitize_text_field($fields[$field]);
            }
        }
        $result = wp_update_user($userData);
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }
        return ['success' => true, 'ID' => $userId];
    }

    private static function getUserMeta(array $args): array
    {
        $userId = (int) ($args['user_id'] ?? 0);
        if (!$userId) {
            throw new \Exception('user_id is required');
        }
        $key = $args['meta_key'] ?? '';
        if ($key) {
            return ['key' => $key, 'value' => get_user_meta($userId, $key, true)];
        }
        return ['meta' => get_user_meta($userId)];
    }

    private static function updateUserMeta(array $args): array
    {
        $userId = (int) ($args['user_id'] ?? 0);
        $key = sanitize_key($args['meta_key'] ?? '');
        $value = $args['meta_value'] ?? '';
        if (!$userId || !$key || !current_user_can('edit_users')) {
            throw new \Exception('Permission denied or missing parameters');
        }
        update_user_meta($userId, $key, $value);
        return ['success' => true, 'user_id' => $userId, 'key' => $key];
    }

    private static function getMediaItem(array $args): array
    {
        $attachmentId = (int) ($args['attachment_id'] ?? 0);
        if (!$attachmentId) {
            throw new \Exception('attachment_id is required');
        }
        $post = get_post($attachmentId);
        if (!$post || $post->post_type !== 'attachment') {
            throw new \Exception('Media item not found');
        }
        $metadata = wp_get_attachment_metadata($attachmentId);
        return [
            'ID' => $post->ID,
            'title' => $post->post_title,
            'caption' => $post->post_excerpt,
            'description' => $post->post_content,
            'alt_text' => get_post_meta($attachmentId, '_wp_attachment_image_alt', true),
            'url' => wp_get_attachment_url($attachmentId),
            'mime_type' => $post->post_mime_type,
            'date' => $post->post_date,
            'file_size' => filesize(get_attached_file($attachmentId)) ?: null,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'sizes' => isset($metadata['sizes']) ? array_keys($metadata['sizes']) : [],
        ];
    }

    private static function setFeaturedImage(array $args): array
    {
        $postId = (int) ($args['post_id'] ?? 0);
        $attachmentId = (int) ($args['attachment_id'] ?? 0);
        if (!$postId || !$attachmentId || !current_user_can('edit_post', $postId)) {
            throw new \Exception('Permission denied or missing parameters');
        }
        $result = set_post_thumbnail($postId, $attachmentId);
        return ['success' => (bool) $result, 'post_id' => $postId, 'attachment_id' => $attachmentId];
    }

    private static function getTaxonomies(array $args): array
    {
        $filter = [];
        if (!empty($args['post_type'])) {
            $filter['object_type'] = [$args['post_type']];
        }
        $taxonomies = get_taxonomies($filter, 'objects');
        return [
            'total' => count($taxonomies),
            'taxonomies' => array_map(function ($tax) {
                return [
                    'name' => $tax->name,
                    'label' => $tax->label,
                    'hierarchical' => $tax->hierarchical,
                    'public' => $tax->public,
                    'object_type' => $tax->object_type,
                ];
            }, array_values($taxonomies)),
        ];
    }

    private static function getCategories(array $args): array
    {
        $args['taxonomy'] = 'category';
        $args['limit'] = $args['limit'] ?? 50;
        return self::getTerms($args);
    }

    private static function getTags(array $args): array
    {
        $args['taxonomy'] = 'post_tag';
        $args['limit'] = $args['limit'] ?? 50;
        return self::getTerms($args);
    }

    private static function deleteOption(array $args): array
    {
        $key = $args['key'] ?? '';
        if (!$key || !current_user_can('manage_options')) {
            throw new \Exception('Permission denied or key missing');
        }
        $blocked = ['siteurl', 'home', 'admin_email', 'active_plugins'];
        if (in_array(strtolower($key), $blocked)) {
            throw new \Exception('Deletion of this option is blocked');
        }
        $result = delete_option($key);
        return ['success' => $result, 'key' => $key];
    }

    private static function getSettings(array $args): array
    {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Permission denied');
        }
        return [
            'blogname' => get_option('blogname'),
            'blogdescription' => get_option('blogdescription'),
            'siteurl' => get_option('siteurl'),
            'home' => get_option('home'),
            'admin_email' => get_option('admin_email'),
            'users_can_register' => get_option('users_can_register'),
            'default_role' => get_option('default_role'),
            'timezone_string' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'start_of_week' => get_option('start_of_week'),
            'language' => get_option('WPLANG'),
            'permalink_structure' => get_option('permalink_structure'),
            'posts_per_page' => get_option('posts_per_page'),
            'default_comment_status' => get_option('default_comment_status'),
        ];
    }

    private static function updateSettings(array $args): array
    {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Permission denied');
        }
        $settings = $args['settings'] ?? $args;
        $allowed = ['blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'start_of_week', 'posts_per_page', 'default_comment_status', 'default_role'];
        $updated = [];
        foreach ($allowed as $key) {
            if (isset($settings[$key])) {
                update_option($key, sanitize_text_field($settings[$key]));
                $updated[] = $key;
            }
        }
        return ['success' => true, 'updated' => $updated];
    }

    private static function createMenu(array $args): array
    {
        if (!current_user_can('edit_theme_options')) {
            throw new \Exception('Permission denied');
        }
        $name = sanitize_text_field($args['name'] ?? '');
        if (!$name) {
            throw new \Exception('name is required');
        }
        $menuId = wp_create_nav_menu($name);
        if (is_wp_error($menuId)) {
            throw new \Exception($menuId->get_error_message());
        }
        return ['success' => true, 'menu_id' => $menuId, 'name' => $name];
    }

    private static function addMenuItem(array $args): array
    {
        if (!current_user_can('edit_theme_options')) {
            throw new \Exception('Permission denied');
        }
        $menuId = (int) ($args['menu_id'] ?? 0);
        if (!$menuId) {
            throw new \Exception('menu_id is required');
        }
        $itemData = [
            'menu-item-title' => sanitize_text_field($args['title'] ?? ''),
            'menu-item-url' => esc_url_raw($args['url'] ?? ''),
            'menu-item-status' => 'publish',
            'menu-item-type' => 'custom',
        ];
        if (!empty($args['object_id'])) {
            $itemData['menu-item-object-id'] = (int) $args['object_id'];
            $itemData['menu-item-type'] = 'post_type';
            $itemData['menu-item-object'] = 'page';
        }
        if (!empty($args['parent'])) {
            $itemData['menu-item-parent-id'] = (int) $args['parent'];
        }
        $id = wp_update_nav_menu_item($menuId, 0, $itemData);
        if (is_wp_error($id)) {
            throw new \Exception($id->get_error_message());
        }
        return ['success' => true, 'menu_item_id' => $id, 'menu_id' => $menuId];
    }

    private static function getCronEvents(array $args): array
    {
        $crons = _get_cron_array();
        $events = [];
        if (is_array($crons)) {
            foreach ($crons as $timestamp => $cronhooks) {
                foreach ($cronhooks as $hook => $data) {
                    foreach ($data as $key => $info) {
                        $events[] = [
                            'hook' => $hook,
                            'next_run' => gmdate('c', $timestamp),
                            'schedule' => $info['schedule'] ?? 'single',
                            'interval' => $info['interval'] ?? null,
                        ];
                    }
                }
            }
        }
        return ['total' => count($events), 'events' => array_slice($events, 0, 50)];
    }

    // =========================================================================
    // NEW: MISSING WOOCOMMERCE HANDLERS
    // =========================================================================

    private static function wcBatchUpdateProducts(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('edit_products')) throw new \Exception('Permission denied');

        $products = $args['products'] ?? [];
        $results = [];
        foreach ($products as $item) {
            $productId = (int) ($item['id'] ?? 0);
            if (!$productId) continue;
            $product = wc_get_product($productId);
            if (!$product) { $results[] = ['ID' => $productId, 'error' => 'Not found']; continue; }
            if (isset($item['name'])) $product->set_name(sanitize_text_field($item['name']));
            if (isset($item['regular_price'])) $product->set_regular_price($item['regular_price']);
            if (isset($item['sale_price'])) $product->set_sale_price($item['sale_price']);
            if (isset($item['stock_quantity'])) $product->set_stock_quantity((int) $item['stock_quantity']);
            if (isset($item['status'])) $product->set_status(sanitize_key($item['status']));
            $product->save();
            $results[] = ['ID' => $productId, 'success' => true];
        }
        return ['updated' => count($results), 'results' => $results];
    }

    private static function wcCreateProductCategory(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_product_terms')) throw new \Exception('Permission denied');

        $result = wp_insert_term(
            sanitize_text_field($args['name'] ?? ''),
            'product_cat',
            [
                'slug' => sanitize_title($args['slug'] ?? ''),
                'parent' => (int) ($args['parent'] ?? 0),
                'description' => sanitize_textarea_field($args['description'] ?? ''),
            ]
        );
        if (is_wp_error($result)) throw new \Exception($result->get_error_message());
        return ['success' => true, 'term_id' => $result['term_id']];
    }

    private static function wcGetProductTags(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $terms = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false, 'number' => 100]);
        if (is_wp_error($terms)) throw new \Exception($terms->get_error_message());
        return [
            'total' => count($terms),
            'tags' => array_map(fn($t) => ['term_id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count], $terms),
        ];
    }

    private static function wcGetVariations(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $productId = (int) ($args['product_id'] ?? 0);
        if (!$productId) throw new \Exception('product_id is required');
        $product = wc_get_product($productId);
        if (!$product || !$product->is_type('variable')) throw new \Exception('Variable product not found');

        $variations = $product->get_available_variations();
        return [
            'total' => count($variations),
            'variations' => array_map(function ($v) {
                return [
                    'variation_id' => $v['variation_id'],
                    'sku' => $v['sku'],
                    'price' => $v['display_price'],
                    'regular_price' => $v['display_regular_price'],
                    'stock_status' => $v['is_in_stock'] ? 'instock' : 'outofstock',
                    'stock_quantity' => $v['max_qty'] ?? null,
                    'attributes' => $v['attributes'],
                ];
            }, $variations),
        ];
    }

    private static function wcCreateVariation(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('edit_products')) throw new \Exception('Permission denied');

        $productId = (int) ($args['product_id'] ?? 0);
        if (!$productId) throw new \Exception('product_id is required');

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($productId);
        if (isset($args['regular_price'])) $variation->set_regular_price($args['regular_price']);
        if (isset($args['sale_price'])) $variation->set_sale_price($args['sale_price']);
        if (isset($args['sku'])) $variation->set_sku(sanitize_text_field($args['sku']));
        if (isset($args['stock_quantity'])) $variation->set_stock_quantity((int) $args['stock_quantity']);
        if (isset($args['attributes']) && is_array($args['attributes'])) {
            $variation->set_attributes($args['attributes']);
        }
        $id = $variation->save();
        return ['success' => true, 'variation_id' => $id, 'product_id' => $productId];
    }

    private static function wcUpdateStock(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('edit_products')) throw new \Exception('Permission denied');

        $productId = (int) ($args['product_id'] ?? 0);
        $quantity = (int) ($args['quantity'] ?? 0);
        if (!$productId) throw new \Exception('product_id is required');

        $product = wc_get_product($productId);
        if (!$product) throw new \Exception('Product not found');
        $product->set_manage_stock(true);
        $product->set_stock_quantity($quantity);
        $product->save();
        return ['success' => true, 'ID' => $productId, 'stock_quantity' => $quantity];
    }

    private static function wcGetLowStock(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $threshold = (int) ($args['threshold'] ?? 5);

        $products = wc_get_products([
            'limit' => 50,
            'stock_status' => 'instock',
            'manage_stock' => true,
        ]);

        $lowStock = [];
        foreach ($products as $product) {
            $qty = $product->get_stock_quantity();
            if ($qty !== null && $qty <= $threshold) {
                $lowStock[] = [
                    'ID' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'stock_quantity' => $qty,
                    'price' => $product->get_price(),
                ];
            }
        }
        return ['threshold' => $threshold, 'total' => count($lowStock), 'products' => $lowStock];
    }

    private static function wcCreateOrder(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('edit_shop_orders')) throw new \Exception('Permission denied');

        $order = wc_create_order([
            'customer_id' => (int) ($args['customer_id'] ?? 0),
            'status' => sanitize_key($args['status'] ?? 'pending'),
        ]);
        if (is_wp_error($order)) throw new \Exception($order->get_error_message());

        if (!empty($args['line_items']) && is_array($args['line_items'])) {
            foreach ($args['line_items'] as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 1);
                if ($productId) {
                    $product = wc_get_product($productId);
                    if ($product) $order->add_product($product, $quantity);
                }
            }
        }
        $order->calculate_totals();
        $order->save();
        return ['success' => true, 'ID' => $order->get_id(), 'total' => $order->get_total()];
    }

    private static function wcDeleteOrder(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $orderId = (int) ($args['order_id'] ?? 0);
        if (!$orderId || !current_user_can('delete_shop_orders')) throw new \Exception('Permission denied');
        $order = wc_get_order($orderId);
        if (!$order) throw new \Exception('Order not found');
        $force = (bool) ($args['force'] ?? false);
        $order->delete($force);
        return ['success' => true, 'ID' => $orderId, 'permanently_deleted' => $force];
    }

    private static function wcGetOrderNotes(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $orderId = (int) ($args['order_id'] ?? 0);
        if (!$orderId) throw new \Exception('order_id is required');

        $notes = wc_get_order_notes(['order_id' => $orderId, 'limit' => 20]);
        return [
            'total' => count($notes),
            'notes' => array_map(function ($note) {
                return [
                    'id' => $note->id,
                    'content' => $note->content,
                    'date_created' => $note->date_created->format('c'),
                    'customer_note' => $note->customer_note,
                    'added_by' => $note->added_by,
                ];
            }, $notes),
        ];
    }

    private static function wcAddOrderNote(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $orderId = (int) ($args['order_id'] ?? 0);
        if (!$orderId || !current_user_can('edit_shop_orders')) throw new \Exception('Permission denied');
        $order = wc_get_order($orderId);
        if (!$order) throw new \Exception('Order not found');

        $noteId = $order->add_order_note(
            sanitize_textarea_field($args['note'] ?? ''),
            (bool) ($args['customer_note'] ?? false)
        );
        return ['success' => true, 'note_id' => $noteId, 'order_id' => $orderId];
    }

    private static function wcGetRefunds(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $orderId = (int) ($args['order_id'] ?? 0);

        if ($orderId) {
            $order = wc_get_order($orderId);
            if (!$order) throw new \Exception('Order not found');
            $refunds = $order->get_refunds();
        } else {
            $refunds = wc_get_orders(['type' => 'shop_order_refund', 'limit' => 20]);
        }

        return [
            'total' => count($refunds),
            'refunds' => array_map(function ($refund) {
                return [
                    'ID' => $refund->get_id(),
                    'amount' => $refund->get_amount(),
                    'reason' => $refund->get_reason(),
                    'date_created' => $refund->get_date_created() ? $refund->get_date_created()->format('c') : null,
                    'refunded_by' => $refund->get_refunded_by(),
                ];
            }, $refunds),
        ];
    }

    private static function wcCreateRefund(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $orderId = (int) ($args['order_id'] ?? 0);
        if (!$orderId || !current_user_can('edit_shop_orders')) throw new \Exception('Permission denied');

        $refund = wc_create_refund([
            'order_id' => $orderId,
            'amount' => $args['amount'] ?? null,
            'reason' => sanitize_textarea_field($args['reason'] ?? ''),
        ]);
        if (is_wp_error($refund)) throw new \Exception($refund->get_error_message());
        return ['success' => true, 'refund_id' => $refund->get_id(), 'amount' => $refund->get_amount()];
    }

    private static function wcGetCustomer(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $customerId = (int) ($args['customer_id'] ?? 0);
        if (!$customerId) throw new \Exception('customer_id is required');

        $customer = new \WC_Customer($customerId);
        if (!$customer->get_id()) throw new \Exception('Customer not found');

        return [
            'ID' => $customer->get_id(),
            'email' => $customer->get_email(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'display_name' => $customer->get_display_name(),
            'billing' => $customer->get_billing(),
            'shipping' => $customer->get_shipping(),
            'orders_count' => $customer->get_order_count(),
            'total_spent' => $customer->get_total_spent(),
            'date_created' => $customer->get_date_created() ? $customer->get_date_created()->format('c') : null,
        ];
    }

    private static function wcCreateCustomer(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('create_users')) throw new \Exception('Permission denied');

        $customer = new \WC_Customer();
        $customer->set_email(sanitize_email($args['email'] ?? ''));
        if (!empty($args['first_name'])) $customer->set_first_name(sanitize_text_field($args['first_name']));
        if (!empty($args['last_name'])) $customer->set_last_name(sanitize_text_field($args['last_name']));
        $customer->set_username(sanitize_user($args['username'] ?? $args['email'] ?? ''));
        $customer->set_password($args['password'] ?? wp_generate_password());
        $id = $customer->save();
        if (!$id) throw new \Exception('Failed to create customer');
        return ['success' => true, 'ID' => $id];
    }

    private static function wcUpdateCustomer(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $customerId = (int) ($args['customer_id'] ?? 0);
        if (!$customerId || !current_user_can('edit_users')) throw new \Exception('Permission denied');

        $customer = new \WC_Customer($customerId);
        if (!$customer->get_id()) throw new \Exception('Customer not found');

        $fields = $args['fields'] ?? $args;
        if (isset($fields['first_name'])) $customer->set_first_name(sanitize_text_field($fields['first_name']));
        if (isset($fields['last_name'])) $customer->set_last_name(sanitize_text_field($fields['last_name']));
        if (isset($fields['email'])) $customer->set_email(sanitize_email($fields['email']));
        $customer->save();
        return ['success' => true, 'ID' => $customerId];
    }

    private static function wcGetCoupon(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $couponId = (int) ($args['coupon_id'] ?? 0);
        if (!$couponId) throw new \Exception('coupon_id is required');
        $coupon = new \WC_Coupon($couponId);
        if (!$coupon->get_id()) throw new \Exception('Coupon not found');
        return [
            'ID' => $coupon->get_id(),
            'code' => $coupon->get_code(),
            'amount' => $coupon->get_amount(),
            'discount_type' => $coupon->get_discount_type(),
            'description' => $coupon->get_description(),
            'usage_count' => $coupon->get_usage_count(),
            'usage_limit' => $coupon->get_usage_limit(),
            'date_expires' => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('c') : null,
            'free_shipping' => $coupon->get_free_shipping(),
        ];
    }

    private static function wcUpdateCoupon(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $couponId = (int) ($args['coupon_id'] ?? 0);
        if (!$couponId || !current_user_can('edit_shop_coupons')) throw new \Exception('Permission denied');
        $coupon = new \WC_Coupon($couponId);
        if (!$coupon->get_id()) throw new \Exception('Coupon not found');

        $fields = $args['fields'] ?? $args;
        if (isset($fields['amount'])) $coupon->set_amount($fields['amount']);
        if (isset($fields['discount_type'])) $coupon->set_discount_type($fields['discount_type']);
        if (isset($fields['description'])) $coupon->set_description(sanitize_textarea_field($fields['description']));
        if (isset($fields['usage_limit'])) $coupon->set_usage_limit((int) $fields['usage_limit']);
        $coupon->save();
        return ['success' => true, 'ID' => $couponId];
    }

    private static function wcDeleteCoupon(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $couponId = (int) ($args['coupon_id'] ?? 0);
        if (!$couponId || !current_user_can('delete_shop_coupons')) throw new \Exception('Permission denied');
        $coupon = new \WC_Coupon($couponId);
        if (!$coupon->get_id()) throw new \Exception('Coupon not found');
        $coupon->delete(true);
        return ['success' => true, 'ID' => $couponId];
    }

    private static function wcGetShippingZones(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $zones = \WC_Shipping_Zones::get_zones();
        $rest = new \WC_Shipping_Zone(0); // Rest of the World
        $result = [['zone_id' => 0, 'name' => $rest->get_zone_name(), 'order' => 0]];
        foreach ($zones as $zone) {
            $result[] = [
                'zone_id' => $zone['zone_id'],
                'name' => $zone['zone_name'],
                'order' => $zone['zone_order'],
                'locations' => count($zone['zone_locations'] ?? []),
                'methods' => count($zone['shipping_methods'] ?? []),
            ];
        }
        return ['total' => count($result), 'zones' => $result];
    }

    private static function wcGetShippingMethods(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $zoneId = (int) ($args['zone_id'] ?? 0);
        $zone = new \WC_Shipping_Zone($zoneId);
        $methods = $zone->get_shipping_methods();
        return [
            'zone_id' => $zoneId,
            'total' => count($methods),
            'methods' => array_map(function ($method) {
                return [
                    'instance_id' => $method->get_instance_id(),
                    'id' => $method->id,
                    'title' => $method->get_title(),
                    'enabled' => $method->is_enabled(),
                    'method_title' => $method->get_method_title(),
                ];
            }, array_values($methods)),
        ];
    }

    private static function wcCreateShippingZone(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $zone = new \WC_Shipping_Zone();
        $zone->set_zone_name(sanitize_text_field($args['name'] ?? ''));
        if (isset($args['order'])) $zone->set_zone_order((int) $args['order']);
        $zone->save();
        return ['success' => true, 'zone_id' => $zone->get_id(), 'name' => $zone->get_zone_name()];
    }

    private static function wcUpdateShippingZone(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $zoneId = (int) ($args['zone_id'] ?? 0);
        $zone = new \WC_Shipping_Zone($zoneId);
        if (isset($args['name'])) $zone->set_zone_name(sanitize_text_field($args['name']));
        if (isset($args['order'])) $zone->set_zone_order((int) $args['order']);
        $zone->save();
        return ['success' => true, 'zone_id' => $zoneId];
    }

    private static function wcGetTaxClasses(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $classes = \WC_Tax::get_tax_classes();
        $result = [['name' => 'Standard', 'slug' => '']];
        foreach ($classes as $class) {
            $result[] = ['name' => $class, 'slug' => sanitize_title($class)];
        }
        return ['total' => count($result), 'tax_classes' => $result];
    }

    private static function wcGetTaxRates(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        global $wpdb;
        $class = $args['class'] ?? '';
        $where = $class ? $wpdb->prepare(" WHERE tax_rate_class = %s", $class) : '';
        $rates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates{$where} LIMIT 100");
        return [
            'total' => count($rates),
            'rates' => array_map(function ($rate) {
                return [
                    'tax_rate_id' => (int) $rate->tax_rate_id,
                    'country' => $rate->tax_rate_country,
                    'state' => $rate->tax_rate_state,
                    'rate' => $rate->tax_rate,
                    'name' => $rate->tax_rate_name,
                    'priority' => (int) $rate->tax_rate_priority,
                    'class' => $rate->tax_rate_class,
                ];
            }, $rates),
        ];
    }

    private static function wcCreateTaxRate(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $rateData = [
            'tax_rate_country' => strtoupper(sanitize_text_field($args['country'] ?? '')),
            'tax_rate_state' => sanitize_text_field($args['state'] ?? ''),
            'tax_rate' => sanitize_text_field($args['rate'] ?? ''),
            'tax_rate_name' => sanitize_text_field($args['name'] ?? 'Tax'),
            'tax_rate_class' => sanitize_key($args['class'] ?? ''),
            'tax_rate_priority' => (int) ($args['priority'] ?? 1),
            'tax_rate_shipping' => (int) ($args['shipping'] ?? 1),
        ];
        $id = \WC_Tax::_insert_tax_rate($rateData);
        return ['success' => true, 'tax_rate_id' => $id];
    }

    private static function wcUpdateTaxRate(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $taxId = (int) ($args['tax_id'] ?? 0);
        if (!$taxId) throw new \Exception('tax_id is required');
        $data = [];
        if (isset($args['rate'])) $data['tax_rate'] = sanitize_text_field($args['rate']);
        if (isset($args['name'])) $data['tax_rate_name'] = sanitize_text_field($args['name']);
        if (isset($args['country'])) $data['tax_rate_country'] = strtoupper(sanitize_text_field($args['country']));
        \WC_Tax::_update_tax_rate($taxId, $data);
        return ['success' => true, 'tax_rate_id' => $taxId];
    }

    private static function wcDeleteTaxRate(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $taxId = (int) ($args['tax_id'] ?? 0);
        if (!$taxId) throw new \Exception('tax_id is required');
        \WC_Tax::_delete_tax_rate($taxId);
        return ['success' => true, 'tax_rate_id' => $taxId];
    }

    private static function wcGetSalesReport(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $period = $args['period'] ?? 'week';
        $dateMin = $args['date_min'] ?? date('Y-m-d', strtotime("-1 {$period}"));
        $dateMax = $args['date_max'] ?? date('Y-m-d');

        $orders = wc_get_orders([
            'status' => ['completed', 'processing'],
            'date_created' => $dateMin . '...' . $dateMax,
            'limit' => -1,
        ]);

        $totalSales = 0;
        $totalOrders = count($orders);
        $totalItems = 0;
        foreach ($orders as $order) {
            $totalSales += (float) $order->get_total();
            $totalItems += $order->get_item_count();
        }

        return [
            'period' => $period,
            'date_min' => $dateMin,
            'date_max' => $dateMax,
            'total_sales' => round($totalSales, 2),
            'total_orders' => $totalOrders,
            'total_items' => $totalItems,
            'average_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
            'currency' => get_woocommerce_currency(),
        ];
    }

    private static function wcGetTopSellers(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        global $wpdb;
        $limit = min((int) ($args['limit'] ?? 10), 50);
        $period = $args['period'] ?? 'month';
        $dateFrom = date('Y-m-d', strtotime("-1 {$period}"));

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT oi.order_item_name as name, 
                   SUM(oim.meta_value) as qty,
                   oim2.meta_value as product_id
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_qty'
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_product_id'
            INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID AND p.post_status IN ('wc-completed', 'wc-processing')
            WHERE p.post_date >= %s
            GROUP BY oim2.meta_value
            ORDER BY qty DESC
            LIMIT %d
        ", $dateFrom, $limit));

        return [
            'period' => $period,
            'total' => count($results),
            'products' => array_map(fn($r) => [
                'product_id' => (int) $r->product_id,
                'name' => $r->name,
                'quantity_sold' => (int) $r->qty,
            ], $results),
        ];
    }

    private static function wcGetOrdersTotals(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $statuses = wc_get_order_statuses();
        $totals = [];
        foreach ($statuses as $slug => $label) {
            $count = wc_orders_count($slug);
            $totals[] = ['status' => $slug, 'label' => $label, 'count' => $count];
        }
        return ['totals' => $totals];
    }

    private static function wcGetSettings(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $group = $args['group'] ?? 'general';

        $settings = [];
        if ($group === 'general') {
            $settings = [
                'store_address' => get_option('woocommerce_store_address'),
                'store_city' => get_option('woocommerce_store_city'),
                'store_postcode' => get_option('woocommerce_store_postcode'),
                'default_country' => get_option('woocommerce_default_country'),
                'currency' => get_option('woocommerce_currency'),
                'currency_pos' => get_option('woocommerce_currency_pos'),
                'price_num_decimals' => get_option('woocommerce_price_num_decimals'),
                'calc_taxes' => get_option('woocommerce_calc_taxes'),
            ];
        } elseif ($group === 'products') {
            $settings = [
                'weight_unit' => get_option('woocommerce_weight_unit'),
                'dimension_unit' => get_option('woocommerce_dimension_unit'),
                'manage_stock' => get_option('woocommerce_manage_stock'),
                'notify_low_stock' => get_option('woocommerce_notify_low_stock_amount'),
            ];
        } elseif ($group === 'shipping') {
            $settings = [
                'enable_shipping_calc' => get_option('woocommerce_enable_shipping_calc'),
                'ship_to_destination' => get_option('woocommerce_ship_to_destination'),
            ];
        }

        return ['group' => $group, 'settings' => $settings];
    }

    private static function wcUpdateSetting(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $id = $args['id'] ?? '';
        $value = $args['value'] ?? '';
        if (!$id) throw new \Exception('id is required');

        // Only allow woocommerce_ prefixed options
        $optionKey = str_starts_with($id, 'woocommerce_') ? $id : 'woocommerce_' . $id;
        $blocked = ['woocommerce_stripe_settings', 'woocommerce_paypal_settings'];
        if (in_array($optionKey, $blocked)) throw new \Exception('Cannot modify this setting');

        update_option($optionKey, sanitize_text_field($value));
        return ['success' => true, 'id' => $id, 'option_key' => $optionKey];
    }

    private static function wcGetPaymentGateways(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        $gateways = WC()->payment_gateways()->payment_gateways();
        return [
            'total' => count($gateways),
            'gateways' => array_map(function ($gw) {
                return [
                    'id' => $gw->id,
                    'title' => $gw->get_title(),
                    'description' => $gw->get_description(),
                    'enabled' => $gw->enabled === 'yes',
                    'method_title' => $gw->get_method_title(),
                    'supports' => $gw->supports ?? [],
                ];
            }, array_values($gateways)),
        ];
    }

    private static function wcUpdatePaymentGateway(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        if (!current_user_can('manage_woocommerce')) throw new \Exception('Permission denied');
        $gatewayId = $args['gateway_id'] ?? '';
        if (!$gatewayId) throw new \Exception('gateway_id is required');

        $gateways = WC()->payment_gateways()->payment_gateways();
        if (!isset($gateways[$gatewayId])) throw new \Exception('Gateway not found');

        $gw = $gateways[$gatewayId];
        if (isset($args['enabled'])) {
            $gw->enabled = $args['enabled'] ? 'yes' : 'no';
            $gw->update_option('enabled', $gw->enabled);
        }
        if (isset($args['title'])) {
            $gw->update_option('title', sanitize_text_field($args['title']));
        }
        return ['success' => true, 'gateway_id' => $gatewayId, 'enabled' => $gw->enabled === 'yes'];
    }

    private static function wcGetSystemStatus(array $args): array
    {
        if (!class_exists('WooCommerce')) throw new \Exception('WooCommerce is not active');
        return [
            'wc_version' => WC()->version,
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'max_upload_size' => wp_max_upload_size(),
            'default_timezone' => date_default_timezone_get(),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'dimension_unit' => get_option('woocommerce_dimension_unit'),
            'weight_unit' => get_option('woocommerce_weight_unit'),
            'store_address' => get_option('woocommerce_store_address'),
            'store_city' => get_option('woocommerce_store_city'),
            'store_country' => get_option('woocommerce_default_country'),
            'calc_taxes' => get_option('woocommerce_calc_taxes'),
            'theme' => get_stylesheet(),
            'active_plugins' => count(get_option('active_plugins', [])),
        ];
    }
}

