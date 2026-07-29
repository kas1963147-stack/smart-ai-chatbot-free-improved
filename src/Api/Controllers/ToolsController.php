<?php
declare(strict_types=1);


/**
 * Tools Configuration Controller
 * 
 * REST API endpoints for managing external tool API keys and configurations.
 * 
 * @package Quarksol\SmartChatbot\Api\Controllers
 */

namespace Quarksol\SmartChatbot\Api\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ToolsController
{
    /**
     * Register route handlers
     */
    public static function register(): void
    {
        $handler = new self();
        add_action('rest_api_init', [$handler, 'registerRoutes']);
    }

    /**
     * Tool configuration registry
     * Maps tool IDs to their option keys and metadata
     */
    private static function getToolRegistry(): array
    {
        return [
            // Search Providers
            'brave' => [
                'name' => 'Brave Search',
                'category' => 'search',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_brave_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://brave.com/search/api/',
            ],
            'exa' => [
                'name' => 'Exa Search',
                'category' => 'search',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_exa_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://exa.ai/',
            ],
            'serper' => [
                'name' => 'Serper',
                'category' => 'search',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_serper_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://serper.dev/',
            ],
            'tavily' => [
                'name' => 'Tavily',
                'category' => 'search',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_tavily_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://tavily.com/',
            ],
            'linkup' => [
                'name' => 'Linkup',
                'category' => 'search',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_linkup_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
            ],

            // Web Scraping
            'firecrawl' => [
                'name' => 'Firecrawl',
                'category' => 'scraping',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_firecrawl_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://firecrawl.dev/',
            ],
            'jina' => [
                'name' => 'Jina AI',
                'category' => 'scraping',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_jina_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://jina.ai/',
            ],
            'spider' => [
                'name' => 'Spider',
                'category' => 'scraping',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_spider_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
            ],
            'apify' => [
                'name' => 'Apify',
                'category' => 'scraping',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_apify_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://apify.com/',
            ],
            'oxylabs' => [
                'name' => 'Oxylabs',
                'category' => 'scraping',
                'fields' => [
                    'username' => ['key' => 'swc_external_oxylabs_username', 'type' => 'text', 'label' => 'Username'],
                    'password' => ['key' => 'swc_external_oxylabs_password', 'type' => 'password', 'label' => 'Password'],
                ],
                'docs_url' => 'https://oxylabs.io/',
            ],
            'brightdata' => [
                'name' => 'Bright Data',
                'category' => 'scraping',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_brightdata_api_key', 'type' => 'password', 'label' => 'API Key'],
                    'zone' => ['key' => 'swc_external_brightdata_zone', 'type' => 'text', 'label' => 'Zone'],
                ],
                'docs_url' => 'https://brightdata.com/',
            ],
            'scrapfly' => [
                'name' => 'Scrapfly',
                'category' => 'scraping',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_scrapfly_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
            ],
            'scrapegraph' => [
                'name' => 'ScrapeGraph',
                'category' => 'scraping',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_scrapegraph_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
            ],
            'selenium' => [
                'name' => 'Selenium',
                'category' => 'scraping',
                'fields' => [
                    'hub_url' => ['key' => 'swc_external_selenium_hub_url', 'type' => 'text', 'label' => 'Hub URL'],
                ],
            ],

            // Databases
            'mongodb' => [
                'name' => 'MongoDB',
                'category' => 'database',
                'fields' => [
                    'url' => ['key' => 'swc_external_mongodb_url', 'type' => 'text', 'label' => 'Connection URL'],
                    'database' => ['key' => 'swc_external_mongodb_database', 'type' => 'text', 'label' => 'Database Name'],
                ],
            ],
            'postgres' => [
                'name' => 'PostgreSQL',
                'category' => 'database',
                'fields' => [
                    'host' => ['key' => 'swc_external_postgres_host', 'type' => 'text', 'label' => 'Host'],
                    'port' => ['key' => 'swc_external_postgres_port', 'type' => 'text', 'label' => 'Port'],
                    'database' => ['key' => 'swc_external_postgres_database', 'type' => 'text', 'label' => 'Database'],
                    'user' => ['key' => 'swc_external_postgres_user', 'type' => 'text', 'label' => 'Username'],
                    'password' => ['key' => 'swc_external_postgres_password', 'type' => 'password', 'label' => 'Password'],
                ],
            ],
            'qdrant' => [
                'name' => 'Qdrant',
                'category' => 'database',
                'fields' => [
                    'url' => ['key' => 'swc_external_qdrant_url', 'type' => 'text', 'label' => 'URL'],
                    'api_key' => ['key' => 'swc_external_qdrant_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://qdrant.tech/',
            ],
            'weaviate' => [
                'name' => 'Weaviate',
                'category' => 'database',
                'fields' => [
                    'url' => ['key' => 'swc_external_weaviate_url', 'type' => 'text', 'label' => 'URL'],
                    'api_key' => ['key' => 'swc_external_weaviate_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://weaviate.io/',
            ],
            'singlestore' => [
                'name' => 'SingleStore',
                'category' => 'database',
                'fields' => [
                    'url' => ['key' => 'swc_external_singlestore_url', 'type' => 'text', 'label' => 'URL'],
                    'api_key' => ['key' => 'swc_external_singlestore_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
            ],
            'snowflake' => [
                'name' => 'Snowflake',
                'category' => 'database',
                'fields' => [
                    'account' => ['key' => 'swc_external_snowflake_account', 'type' => 'text', 'label' => 'Account'],
                    'user' => ['key' => 'swc_external_snowflake_user', 'type' => 'text', 'label' => 'Username'],
                    'password' => ['key' => 'swc_external_snowflake_password', 'type' => 'password', 'label' => 'Password'],
                ],
            ],
            'couchbase' => [
                'name' => 'Couchbase',
                'category' => 'database',
                'fields' => [
                    'url' => ['key' => 'swc_external_couchbase_url', 'type' => 'text', 'label' => 'URL'],
                    'user' => ['key' => 'swc_external_couchbase_user', 'type' => 'text', 'label' => 'Username'],
                    'password' => ['key' => 'swc_external_couchbase_password', 'type' => 'password', 'label' => 'Password'],
                ],
            ],
            'databricks' => [
                'name' => 'Databricks',
                'category' => 'database',
                'fields' => [
                    'host' => ['key' => 'swc_external_databricks_host', 'type' => 'text', 'label' => 'Host'],
                    'token' => ['key' => 'swc_external_databricks_token', 'type' => 'password', 'label' => 'Token'],
                    'warehouse_id' => ['key' => 'swc_external_databricks_warehouse_id', 'type' => 'text', 'label' => 'Warehouse ID'],
                ],
            ],

            // AI Services
            'openai' => [
                'name' => 'OpenAI',
                'category' => 'ai',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_openai_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://platform.openai.com/',
            ],

            // Integrations
            'github' => [
                'name' => 'GitHub',
                'category' => 'integration',
                'fields' => [
                    'token' => ['key' => 'swc_external_github_token', 'type' => 'password', 'label' => 'Personal Access Token'],
                ],
                'docs_url' => 'https://github.com/settings/tokens',
            ],
            'slack' => [
                'name' => 'Slack',
                'category' => 'integration',
                'fields' => [
                    'bot_token' => ['key' => 'swc_external_slack_bot_token', 'type' => 'password', 'label' => 'Bot Token'],
                ],
                'docs_url' => 'https://api.slack.com/',
            ],
            'notion' => [
                'name' => 'Notion',
                'category' => 'integration',
                'fields' => [
                    'token' => ['key' => 'swc_external_notion_token', 'type' => 'password', 'label' => 'Integration Token'],
                ],
                'docs_url' => 'https://developers.notion.com/',
            ],
            'zapier' => [
                'name' => 'Zapier',
                'category' => 'integration',
                'fields' => [
                    'webhook_url' => ['key' => 'swc_external_zapier_webhook_url', 'type' => 'text', 'label' => 'Webhook URL'],
                ],
                'docs_url' => 'https://zapier.com/',
            ],
            'composio' => [
                'name' => 'Composio',
                'category' => 'integration',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_composio_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://composio.dev/',
            ],
            'e2b' => [
                'name' => 'E2B Sandbox',
                'category' => 'integration',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_e2b_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://e2b.dev/',
            ],

            // YouTube (optional - for API mode)
            'youtube' => [
                'name' => 'YouTube Data API',
                'category' => 'integration',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_youtube_api_key', 'type' => 'password', 'label' => 'API Key (optional - free mode available)'],
                ],
                'docs_url' => 'https://console.cloud.google.com/',
            ],

            // ============================================
            // GOOGLE WORKSPACE SERVICES (OAuth 2.0)
            // ============================================
            'google_calendar' => [
                'name' => 'Google Calendar',
                'category' => 'google_workspace',
                'fields' => [
                    'client_id' => ['key' => 'swc_external_google_client_id', 'type' => 'text', 'label' => 'OAuth Client ID'],
                    'client_secret' => ['key' => 'swc_external_google_client_secret', 'type' => 'password', 'label' => 'OAuth Client Secret'],
                ],
                'docs_url' => 'https://console.cloud.google.com/apis/credentials',
            ],
            'gmail' => [
                'name' => 'Gmail',
                'category' => 'google_workspace',
                'fields' => [
                    'client_id' => ['key' => 'swc_external_google_client_id', 'type' => 'text', 'label' => 'OAuth Client ID (shared with Calendar)'],
                    'client_secret' => ['key' => 'swc_external_google_client_secret', 'type' => 'password', 'label' => 'OAuth Client Secret (shared)'],
                ],
                'docs_url' => 'https://console.cloud.google.com/apis/credentials',
            ],
            'google_drive' => [
                'name' => 'Google Drive',
                'category' => 'google_workspace',
                'fields' => [
                    'client_id' => ['key' => 'swc_external_google_client_id', 'type' => 'text', 'label' => 'OAuth Client ID (shared with Calendar)'],
                    'client_secret' => ['key' => 'swc_external_google_client_secret', 'type' => 'password', 'label' => 'OAuth Client Secret (shared)'],
                ],
                'docs_url' => 'https://console.cloud.google.com/apis/credentials',
            ],

            // ============================================
            // PRODUCTIVITY SERVICES
            // ============================================
            'todoist' => [
                'name' => 'Todoist',
                'category' => 'productivity',
                'fields' => [
                    'api_token' => ['key' => 'swc_external_todoist_api_token', 'type' => 'password', 'label' => 'API Token'],
                ],
                'docs_url' => 'https://developer.todoist.com/',
            ],
            'trello' => [
                'name' => 'Trello',
                'category' => 'productivity',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_trello_api_key', 'type' => 'password', 'label' => 'API Key'],
                    'token' => ['key' => 'swc_external_trello_token', 'type' => 'password', 'label' => 'Token'],
                ],
                'docs_url' => 'https://developer.atlassian.com/cloud/trello/',
            ],
            'asana' => [
                'name' => 'Asana',
                'category' => 'productivity',
                'fields' => [
                    'access_token' => ['key' => 'swc_external_asana_access_token', 'type' => 'password', 'label' => 'Personal Access Token'],
                ],
                'docs_url' => 'https://developers.asana.com/',
            ],
            'jira' => [
                'name' => 'Jira',
                'category' => 'productivity',
                'fields' => [
                    'domain' => ['key' => 'swc_external_jira_domain', 'type' => 'text', 'label' => 'Jira Domain (e.g. yoursite.atlassian.net)'],
                    'email' => ['key' => 'swc_external_jira_email', 'type' => 'text', 'label' => 'Email Address'],
                    'api_token' => ['key' => 'swc_external_jira_api_token', 'type' => 'password', 'label' => 'API Token'],
                ],
                'docs_url' => 'https://id.atlassian.com/manage-profile/security/api-tokens',
            ],

            // ============================================
            // AI/RESEARCH SERVICES
            // ============================================
            'perplexity' => [
                'name' => 'Perplexity AI',
                'category' => 'ai',
                'fields' => [
                    'api_key' => ['key' => 'swc_external_perplexity_api_key', 'type' => 'password', 'label' => 'API Key'],
                ],
                'docs_url' => 'https://docs.perplexity.ai/',
            ],
        ];
    }

    /**
     * Register API routes
     */
    public function registerRoutes(): void
    {
        // Get tool registry (metadata only)
        register_rest_route('smart-ai-chatbot/v1', '/tools/registry', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getRegistry'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Get tool configs (values)
        register_rest_route('smart-ai-chatbot/v1', '/tools/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getConfig'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Update tool config
        register_rest_route('smart-ai-chatbot/v1', '/tools/config', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'updateConfig'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Google OAuth status
        register_rest_route('smart-ai-chatbot/v1', '/tools/google/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getGoogleStatus'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Google OAuth connect (get auth URL)
        register_rest_route('smart-ai-chatbot/v1', '/tools/google/connect', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'connectGoogle'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Google OAuth disconnect
        register_rest_route('smart-ai-chatbot/v1', '/tools/google/disconnect', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'disconnectGoogle'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    /**
     * Check admin permission
     */
    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get tool registry (metadata) with caching
     */
    public function getRegistry(): WP_REST_Response
    {
        // Check cache (30 minute cache - registry rarely changes)
        $cacheKey = 'swc_tools_registry_v4';
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }
        
        $registry = self::getToolRegistry();

        // Group by category
        $grouped = [
            'search' => ['label' => 'Search Providers', 'tools' => []],
            'scraping' => ['label' => 'Web Scraping', 'tools' => []],
            'database' => ['label' => 'Databases', 'tools' => []],
            'ai' => ['label' => 'AI Services', 'tools' => []],
            'integration' => ['label' => 'Integrations', 'tools' => []],
            'google_workspace' => ['label' => 'Google Workspace', 'tools' => []],
            'productivity' => ['label' => 'Productivity & Project Management', 'tools' => []],
        ];

        foreach ($registry as $id => $tool) {
            $category = $tool['category'] ?? 'integration';
            if (isset($grouped[$category])) {
                $grouped[$category]['tools'][$id] = [
                    'id' => $id,
                    'name' => $tool['name'],
                    'fields' => $tool['fields'],
                    'docs_url' => $tool['docs_url'] ?? null,
                ];
            }
        }

        // Cache for 30 minutes
        set_transient($cacheKey, $grouped, 30 * MINUTE_IN_SECONDS);

        return new WP_REST_Response($grouped, 200);
    }

    /**
     * Get tool configurations (values masked for passwords)
     */
    public function getConfig(): WP_REST_Response
    {
        $registry = self::getToolRegistry();
        $config = [];

        foreach ($registry as $toolId => $tool) {
            $config[$toolId] = [];
            foreach ($tool['fields'] as $fieldId => $field) {
                $value = get_option($field['key'], '');
                // Mask password fields (show only if configured)
                if ($field['type'] === 'password' && !empty($value)) {
                    $config[$toolId][$fieldId] = '••••••••';
                    $config[$toolId][$fieldId . '_configured'] = true;
                } else {
                    $config[$toolId][$fieldId] = $value;
                    $config[$toolId][$fieldId . '_configured'] = !empty($value);
                }
            }
        }

        return new WP_REST_Response($config, 200);
    }

    /**
     * Update tool configuration
     */
    public function updateConfig(WP_REST_Request $request): WP_REST_Response
    {
        $registry = self::getToolRegistry();
        $data = $request->get_json_params();

        $updated = [];

        foreach ($data as $toolId => $fields) {
            if (!isset($registry[$toolId])) {
                continue;
            }

            $toolDef = $registry[$toolId];

            foreach ($fields as $fieldId => $value) {
                if (!isset($toolDef['fields'][$fieldId])) {
                    continue;
                }

                $fieldDef = $toolDef['fields'][$fieldId];

                // Skip masked password values (user didn't change it)
                if ($fieldDef['type'] === 'password' && $value === '••••••••') {
                    continue;
                }

                // Sanitize based on type
                $sanitized = $fieldDef['type'] === 'password'
                    ? sanitize_text_field($value)
                    : sanitize_text_field($value);

                update_option($fieldDef['key'], $sanitized);
                $updated[] = "{$toolId}.{$fieldId}";
            }
        }

        // Trigger agent activation check if we updated configurations
        if (!empty($updated)) {
            if (class_exists('\Quarksol\SmartChatbot\Services\AgentSyncService')) {
                \Quarksol\SmartChatbot\Services\AgentSyncService::checkAndActivateAgents();
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'updated' => $updated,
        ], 200);
    }

    /**
     * Get Google OAuth connection status
     */
    public function getGoogleStatus(): WP_REST_Response
    {
        if (!class_exists('Toolkits\GoogleWorkspace\GoogleOAuthHandler')) {
            return new WP_REST_Response(['configured' => false, 'connected' => false], 200);
        }

        return new WP_REST_Response(
            \Toolkits\GoogleWorkspace\GoogleOAuthHandler::getStatus(),
            200
        );
    }

    /**
     * Start Google OAuth connect flow - returns auth URL
     * @return WP_REST_Response|\WP_Error
     */
    public function connectGoogle()
    {
        if (!class_exists('Toolkits\GoogleWorkspace\GoogleOAuthHandler')) {
            return new \WP_Error('google_not_available', 'Google OAuth handler not available', ['status' => 500]);
        }

        if (!\Toolkits\GoogleWorkspace\GoogleOAuthHandler::isConfigured()) {
            return new \WP_Error(
                'google_not_configured',
                'Please enter your Google Client ID and Client Secret first, save them, then click Connect.',
                ['status' => 400]
            );
        }

        $auth_url = \Toolkits\GoogleWorkspace\GoogleOAuthHandler::getAuthUrl();
        // Append nonce to the auth URL as state if not present
        if (strpos($auth_url, 'state=') === false) {
            $nonce = wp_create_nonce('google_oauth');
            $auth_url .= (strpos($auth_url, '?') !== false ? '&' : '?') . 'state=' . urlencode($nonce);
        }

        return new WP_REST_Response([
            'auth_url' => $auth_url,
        ], 200);
    }

    /**
     * Disconnect Google OAuth
     */
    public function disconnectGoogle(): WP_REST_Response
    {
        if (class_exists('Toolkits\GoogleWorkspace\GoogleOAuthHandler')) {
            \Toolkits\GoogleWorkspace\GoogleOAuthHandler::disconnect();
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Google account disconnected.',
        ], 200);
    }

    /**
     * Handle Google OAuth callback (called via admin_init hook)
     */
    public static function handleGoogleCallback(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'swc_chatbot') {
            return;
        }
        if (!isset($_GET['google_callback']) || !isset($_GET['code'])) {
            return;
        }

        // If not logged in, redirect to login page with redirect back here
        if (!current_user_can('manage_options')) {
            $currentUrl = (is_ssl() ? 'https' : 'http') . '://' . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) . sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
            wp_safe_redirect(wp_login_url($currentUrl));
            exit;
        }

        if (!class_exists('Toolkits\GoogleWorkspace\GoogleOAuthHandler')) {
            return;
        }

        $code = sanitize_text_field($_GET['code']);

        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
        
        // Nonce check validating input origin
        if (empty($state) || !wp_verify_nonce($state, 'google_oauth')) {
            wp_safe_redirect(admin_url('admin.php?page=swc_chatbot&tab=tools&google_error=Security+check+failed'));
            exit;
        }

        // Exchange code for tokens 
        $result = \Toolkits\GoogleWorkspace\GoogleOAuthHandler::handleCallback($code, $state);

        // Redirect back to tools tab with success/error message
        $redirectUrl = admin_url('admin.php?page=swc_chatbot&tab=tools');
        if (!empty($result['success'])) {
            $redirectUrl .= '&google_connected=1';
            
            // Trigger agent activation check
            if (class_exists('\Quarksol\SmartChatbot\Services\AgentSyncService')) {
                \Quarksol\SmartChatbot\Services\AgentSyncService::checkAndActivateAgents();
            }
        } else {
            $redirectUrl .= '&google_error=' . urlencode($result['error'] ?? 'Unknown error');
        }

        wp_safe_redirect($redirectUrl);
        exit;
    }
}
