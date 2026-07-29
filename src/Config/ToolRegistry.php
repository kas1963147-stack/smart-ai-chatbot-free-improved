<?php
declare(strict_types=1);


/**
 * Tool Registry
 * 
 * Central registry of all available tools and toolkits.
 * Provides metadata for admin UI and tool filtering.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Services\Logger;
use Quarksol\SmartChatbot\Services\ToolAccessPolicy;

/**
 * Tool Registry
 * 
 * Central registry for discovering and filtering tools.
 */
class ToolRegistry
{
    /** Cached toolkit definitions */
    protected static ?array $toolkits = null;

    /** Cached tool instances */
    protected static ?array $tools = null;

    /**
     * Get all toolkit definitions with metadata
     */
    public static function getToolkits(): array
    {
        if (self::$toolkits !== null) {
            return self::$toolkits;
        }

        self::$toolkits = [
            'woocommerce' => [
                'id' => 'woocommerce',
                'name' => 'WooCommerce',
                'icon' => '',
                'description' => 'Complete e-commerce toolkit with 22 tools',
                'available' => class_exists('WooCommerce'),
                'tool_count' => 22,
                'categories' => [
                    'products' => [
                        'name' => 'Products',
                        'icon' => '',
                        'tools' => [
                            'woo_search_products' => 'Search for products',
                            'woo_product_details' => 'Get product details',
                            'woo_product_manage' => 'Create/update/delete products',
                            'woo_variations' => 'Manage product variations',
                            'woo_attributes' => 'Manage product attributes',
                        ],
                    ],
                    'categories' => [
                        'name' => 'Categories',
                        'icon' => '',
                        'tools' => [
                            'woo_categories' => 'Browse product categories',
                        ],
                    ],
                    'cart' => [
                        'name' => 'Cart & Checkout',
                        'icon' => '',
                        'tools' => [
                            'woo_cart' => 'Basic cart operations',
                            'woo_cart_enhanced' => 'Advanced cart management',
                            'woo_coupons' => 'List available coupons',
                            'woo_coupons_manage' => 'Create/manage coupons',
                        ],
                    ],
                    'orders' => [
                        'name' => 'Orders',
                        'icon' => '',
                        'tools' => [
                            'woo_order_track' => 'Track order status',
                            'woo_order_manage' => 'Manage orders (status, notes, refunds)',
                        ],
                    ],
                    'customers' => [
                        'name' => 'Customers',
                        'icon' => '',
                        'tools' => [
                            'woo_customers' => 'Customer management',
                        ],
                    ],
                    'reviews' => [
                        'name' => 'Reviews',
                        'icon' => '',
                        'tools' => [
                            'woo_reviews' => 'Product reviews management',
                        ],
                    ],
                    'store' => [
                        'name' => 'Store Info',
                        'icon' => '',
                        'tools' => [
                            'woo_store_info' => 'Store information',
                            'woo_shipping' => 'Shipping methods',
                            'woo_shipping_zones' => 'Shipping zones configuration',
                        ],
                    ],
                    'config' => [
                        'name' => 'Configuration',
                        'icon' => '',
                        'tools' => [
                            'woo_settings' => 'Store settings',
                            'woo_tax' => 'Tax rates management',
                            'woo_payment_gateways' => 'Payment gateways',
                        ],
                    ],
                    'analytics' => [
                        'name' => 'Analytics',
                        'icon' => '',
                        'tools' => [
                            'woo_reports' => 'Sales and analytics reports',
                        ],
                    ],
                    'integrations' => [
                        'name' => 'Integrations',
                        'icon' => '',
                        'tools' => [
                            'woo_webhooks' => 'Webhook management',
                        ],
                    ],
                ],
            ],
            'wordpress_content' => [
                'id' => 'wordpress_content',
                'name' => 'WordPress Content',
                'icon' => '',
                'description' => 'Full CMS content management with 13 tools',
                'available' => true,
                'tool_count' => 13,
                'categories' => [
                    'posts' => [
                        'name' => 'Posts & Pages',
                        'icon' => '',
                        'tools' => [
                            'wp_create_post' => 'Create posts/pages',
                            'wp_read_posts' => 'Read and search content',
                            'wp_update_post' => 'Update existing content',
                            'wp_delete_post' => 'Delete content',
                            'wp_revisions' => 'Manage revisions',
                        ],
                    ],
                    'media' => [
                        'name' => 'Media',
                        'icon' => '',
                        'tools' => [
                            'wp_media' => 'Media library management',
                        ],
                    ],
                    'taxonomy' => [
                        'name' => 'Taxonomy',
                        'icon' => '',
                        'tools' => [
                            'wp_taxonomy' => 'Categories and tags',
                            'wp_meta' => 'Custom fields and metadata',
                        ],
                    ],
                    'structure' => [
                        'name' => 'Structure',
                        'icon' => '',
                        'tools' => [
                            'wp_build_blocks' => 'Block editor content',
                            'wp_menus' => 'Navigation menus',
                        ],
                    ],
                    'users' => [
                        'name' => 'Users & Comments',
                        'icon' => '',
                        'tools' => [
                            'wp_users' => 'User management',
                            'wp_comments' => 'Comment moderation',
                        ],
                    ],
                    'settings' => [
                        'name' => 'Settings',
                        'icon' => '',
                        'tools' => [
                            'wp_options' => 'WordPress options',
                        ],
                    ],
                ],
            ],
            'wordpress_core' => [
                'id' => 'wordpress_core',
                'name' => 'WordPress Core',
                'icon' => '',
                'description' => 'Core utilities: database, calendar, email, plugins, themes',
                'available' => true,
                'tool_count' => 13,
                'categories' => [
                    'utilities' => [
                        'name' => 'Utilities',
                        'icon' => '',
                        'tools' => [
                            'database' => 'Direct database queries',
                            'calendar' => 'Calendar and scheduling',
                            'email' => 'Send emails',
                        ],
                    ],
                    'admin' => [
                        'name' => 'Admin',
                        'icon' => '',
                        'tools' => [
                            'wp_plugins' => 'Plugin management',
                            'wp_themes' => 'Theme management',
                            'wp_roles' => 'Role and capabilities',
                            'wp_cron' => 'Scheduled tasks',
                            'wp_transients' => 'Transient cache',
                            'wp_site_health' => 'Site health checks',
                            'wp_rewrite' => 'URL rewrite rules',
                            'wp_widgets' => 'Widget management',
                            'wp_search' => 'Advanced search',
                            'wp_http' => 'HTTP requests',
                        ],
                    ],
                ],
            ],

            // ============================================
            // NEW TOOLKITS (Phase 2)
            // ============================================

            'file_access' => [
                'id' => 'file_access',
                'name' => 'File Access',
                'icon' => '',
                'description' => 'Secure file operations for WordPress',
                'available' => true,
                'tool_count' => 10,
                'categories' => [
                    'read' => [
                        'name' => 'Read Operations',
                        'icon' => '',
                        'tools' => [
                            'file_read' => 'Read file contents',
                            'file_info' => 'Get file metadata',
                            'file_search' => 'Search files',
                            'file_directory' => 'List directories',
                        ],
                    ],
                    'write' => [
                        'name' => 'Write Operations',
                        'icon' => '',
                        'tools' => [
                            'file_write' => 'Write file contents',
                            'file_edit' => 'Edit files in place',
                            'file_copy_move' => 'Copy or move files',
                            'file_delete' => 'Delete files',
                        ],
                    ],
                    'formats' => [
                        'name' => 'Format Helpers',
                        'icon' => '',
                        'tools' => [
                            'file_markdown' => 'Markdown operations',
                            'file_json' => 'JSON operations',
                        ],
                    ],
                ],
            ],

            'integrations' => [
                'id' => 'integrations',
                'name' => 'Integrations',
                'icon' => '',
                'description' => 'Connect to external services (Google, Slack, etc.)',
                'available' => true,
                'tool_count' => 8,
                'categories' => [
                    'discovery' => [
                        'name' => 'Discovery',
                        'icon' => '',
                        'tools' => [
                            'integration_registry' => 'List available integrations',
                            'integration_search' => 'Search integrations',
                            'integration_actions' => 'Discover available actions',
                        ],
                    ],
                    'connection' => [
                        'name' => 'Connection',
                        'icon' => '',
                        'tools' => [
                            'integration_connections' => 'Manage connections',
                            'integration_oauth' => 'OAuth flow handler',
                            'integration_test' => 'Test connections',
                        ],
                    ],
                    'execution' => [
                        'name' => 'Execution',
                        'icon' => '',
                        'tools' => [
                            'integration_api' => 'Generic API calls',
                            'integration_webhooks' => 'Webhook receiver',
                        ],
                    ],
                ],
            ],

            'custom_fields' => [
                'id' => 'custom_fields',
                'name' => 'Custom Fields',
                'icon' => '',
                'description' => 'ACF and native custom field management',
                'available' => true,
                'tool_count' => 6,
                'categories' => [
                    'fields' => [
                        'name' => 'Field Operations',
                        'icon' => '',
                        'tools' => [
                            'cf_fields' => 'Read/write custom fields',
                            'cf_metadata' => 'Metadata management',
                            'cf_options' => 'Options page fields',
                        ],
                    ],
                    'acf' => [
                        'name' => 'ACF Specific',
                        'icon' => '',
                        'tools' => [
                            'cf_acf_groups' => 'ACF field groups',
                            'cf_repeater' => 'Repeater fields',
                            'cf_flexible' => 'Flexible content',
                        ],
                    ],
                ],
            ],

            'seo' => [
                'id' => 'seo',
                'name' => 'SEO',
                'icon' => '',
                'description' => 'Complete SEO management (Yoast/RankMath compatible)',
                'available' => true,
                'tool_count' => 10,
                'categories' => [
                    'core' => [
                        'name' => 'Core SEO',
                        'icon' => '',
                        'tools' => [
                            'seo_meta' => 'Meta tag management',
                            'seo_schema' => 'Structured data (JSON-LD)',
                            'seo_sitemap' => 'XML sitemap management',
                            'seo_redirects' => '301/302 redirects',
                        ],
                    ],
                    'content' => [
                        'name' => 'Content & Social',
                        'icon' => '',
                        'tools' => [
                            'seo_social' => 'OpenGraph/Twitter cards',
                            'seo_analyze' => 'Content SEO analysis',
                        ],
                    ],
                    'technical' => [
                        'name' => 'Technical SEO',
                        'icon' => '',
                        'tools' => [
                            'seo_robots' => 'Robots.txt management',
                            'seo_keywords' => 'Keyword tracking',
                            'seo_links' => 'Link analysis',
                            'seo_webmaster' => 'Webmaster verification',
                        ],
                    ],
                ],
            ],

            'security' => [
                'id' => 'security',
                'name' => 'Security',
                'icon' => '',
                'description' => 'Complete security hardening and monitoring',
                'available' => true,
                'tool_count' => 10,
                'categories' => [
                    'scanning' => [
                        'name' => 'Scanning',
                        'icon' => '',
                        'tools' => [
                            'security_scan' => 'Security vulnerability scan',
                            'security_audit' => 'Security audit log',
                        ],
                    ],
                    'protection' => [
                        'name' => 'Protection',
                        'icon' => '',
                        'tools' => [
                            'security_firewall' => 'Firewall rules',
                            'security_login' => 'Login protection',
                            'security_hardening' => 'Hardening options',
                            'security_ssl' => 'SSL/TLS management',
                        ],
                    ],
                    'users' => [
                        'name' => 'User Security',
                        'icon' => '',
                        'tools' => [
                            'security_nonce' => 'Nonce verification',
                            'security_passwords' => 'Password policies',
                            'security_user' => 'User security checks',
                            'security_backup' => 'Backup management',
                        ],
                    ],
                ],
            ],

            'performance' => [
                'id' => 'performance',
                'name' => 'Performance',
                'icon' => '',
                'description' => 'Complete performance optimization and monitoring',
                'available' => true,
                'tool_count' => 10,
                'categories' => [
                    'caching' => [
                        'name' => 'Caching',
                        'icon' => '',
                        'tools' => [
                            'perf_cache' => 'Object cache management',
                            'perf_transients' => 'Enhanced transients',
                        ],
                    ],
                    'database' => [
                        'name' => 'Database',
                        'icon' => '',
                        'tools' => [
                            'perf_db_optimize' => 'Database optimization',
                            'perf_query_analysis' => 'Query performance analysis',
                        ],
                    ],
                    'assets' => [
                        'name' => 'Assets',
                        'icon' => '',
                        'tools' => [
                            'perf_assets' => 'CSS/JS optimization',
                            'perf_images' => 'Image optimization',
                        ],
                    ],
                    'advanced' => [
                        'name' => 'Advanced',
                        'icon' => '',
                        'tools' => [
                            'perf_cron' => 'Cron optimization',
                            'perf_http' => 'HTTP optimization',
                            'perf_autoload' => 'Autoload optimization',
                            'perf_monitor' => 'Performance monitoring',
                        ],
                    ],
                ],
            ],

            // ============================================
            // ADDITIONAL TOOLKITS (Phase 3)
            // ============================================

            'cli' => [
                'id' => 'cli',
                'name' => 'CLI',
                'icon' => '',
                'description' => 'WP-CLI command execution (10 tools)',
                'available' => defined('WP_CLI'),
                'tool_count' => 10,
                'categories' => [
                    'commands' => [
                        'name' => 'Commands',
                        'icon' => '',
                        'tools' => [
                            'cli_execute' => 'Execute WP-CLI command',
                            'cli_core' => 'Core commands',
                            'cli_plugin' => 'Plugin commands',
                            'cli_theme' => 'Theme commands',
                            'cli_db' => 'Database commands',
                            'cli_cache' => 'Cache commands',
                            'cli_cron' => 'Cron commands',
                            'cli_rewrite' => 'Rewrite commands',
                            'cli_search_replace' => 'Search replace',
                            'cli_export_import' => 'Export/import',
                        ],
                    ],
                ],
            ],

            'expressions' => [
                'id' => 'expressions',
                'name' => 'Expressions',
                'icon' => '',
                'description' => 'Sandboxed PHP evaluation and code generation (10 tools)',
                'available' => true,
                'tool_count' => 10,
                'categories' => [
                    'evaluation' => [
                        'name' => 'Evaluation',
                        'icon' => '',
                        'tools' => [
                            'expr_eval' => 'Evaluate expression',
                            'expr_math' => 'Math operations',
                            'expr_string' => 'String operations',
                            'expr_array' => 'Array operations',
                            'expr_date' => 'Date operations',
                        ],
                    ],
                    'generation' => [
                        'name' => 'Generation',
                        'icon' => '',
                        'tools' => [
                            'expr_generate' => 'Generate code',
                            'expr_transform' => 'Transform data',
                            'expr_validate' => 'Validate code',
                            'expr_format' => 'Format code',
                            'expr_template' => 'Template rendering',
                        ],
                    ],
                ],
            ],

            'forms' => [
                'id' => 'forms',
                'name' => 'Forms',
                'icon' => '',
                'description' => 'Form submissions across all plugins (12 tools)',
                'available' => true,
                'tool_count' => 12,
                'categories' => [
                    'submissions' => [
                        'name' => 'Submissions',
                        'icon' => '',
                        'tools' => [
                            'form_list' => 'List forms',
                            'form_entries' => 'Get form entries',
                            'form_submit' => 'Submit form data',
                            'form_validate' => 'Validate form input',
                        ],
                    ],
                    'management' => [
                        'name' => 'Management',
                        'icon' => '',
                        'tools' => [
                            'form_create' => 'Create forms',
                            'form_update' => 'Update forms',
                            'form_delete' => 'Delete forms',
                            'form_fields' => 'Manage fields',
                        ],
                    ],
                    'integrations' => [
                        'name' => 'Integrations',
                        'icon' => '',
                        'tools' => [
                            'form_cf7' => 'Contact Form 7',
                            'form_gravity' => 'Gravity Forms',
                            'form_wpforms' => 'WPForms',
                            'form_ninja' => 'Ninja Forms',
                        ],
                    ],
                ],
            ],

            'appointment_booking' => [
                'id' => 'appointment_booking',
                'name' => 'Appointment Booking',
                'icon' => '📅',
                'description' => 'Internal scheduling and appointment management (2 tools)',
                'available' => true,
                'tool_count' => 2,
                'categories' => [
                    'scheduling' => [
                        'name' => 'Scheduling',
                        'icon' => '',
                        'tools' => [
                            'availability_checker' => 'Check available time slots',
                            'appointment_booker' => 'Book an appointment',
                        ],
                    ],
                ],
            ],

            'LeadCollection' => [
                'id' => 'LeadCollection',
                'name' => 'Lead Collection',
                'icon' => '👥',
                'description' => 'CRM-lite lead capture and management (1 tool)',
                'available' => true,
                'tool_count' => 1,
                'categories' => [
                    'leads' => [
                        'name' => 'Leads',
                        'icon' => '',
                        'tools' => [
                            'lead_collector' => 'Capture and manage leads',
                        ],
                    ],
                ],
            ],

            'web_research' => [
                'id' => 'web_research',
                'name' => 'Web Research',
                'icon' => '',
                'description' => 'Web search, AI search, content extraction (16 tools)',
                'available' => true,
                'tool_count' => 16,
                'categories' => [
                    'search' => [
                        'name' => 'Search',
                        'icon' => '',
                        'tools' => [
                            'web_search' => 'Web search',
                            'web_ai_search' => 'AI-powered search',
                            'web_news' => 'News search',
                            'web_images' => 'Image search',
                        ],
                    ],
                    'extraction' => [
                        'name' => 'Extraction',
                        'icon' => '',
                        'tools' => [
                            'web_fetch' => 'Fetch URL content',
                            'web_scrape' => 'Scrape page data',
                            'web_links' => 'Extract links',
                            'web_meta' => 'Extract metadata',
                            'web_text' => 'Extract text content',
                            'web_structured' => 'Extract structured data',
                        ],
                    ],
                    'analysis' => [
                        'name' => 'Analysis',
                        'icon' => '',
                        'tools' => [
                            'web_summarize' => 'Summarize content',
                            'web_translate' => 'Translate content',
                            'web_sentiment' => 'Sentiment analysis',
                            'web_entities' => 'Entity extraction',
                            'web_keywords' => 'Keyword extraction',
                            'web_compare' => 'Compare pages',
                        ],
                    ],
                ],
            ],

            'workflows' => [
                'id' => 'workflows',
                'name' => 'Workflows',
                'icon' => '',
                'description' => 'Dynamic workflow automation',
                'available' => true,
                'tool_count' => 5,
                'categories' => [
                    'automation' => [
                        'name' => 'Automation',
                        'icon' => '',
                        'tools' => [
                            'workflow_create' => 'Create workflow',
                            'workflow_execute' => 'Execute workflow',
                            'workflow_schedule' => 'Schedule workflow',
                            'workflow_list' => 'List workflows',
                            'workflow_delete' => 'Delete workflow',
                        ],
                    ],
                ],
            ],

            // ============================================
            // GUTENBERG TOOLKIT (Block Editor)
            // ============================================

            'gutenberg' => [
                'id' => 'gutenberg',
                'name' => 'Gutenberg',
                'icon' => '',
                'description' => 'Complete WordPress block editor control (6 tools)',
                'available' => true,
                'tool_count' => 6,
                'categories' => [
                    'blocks' => [
                        'name' => 'Blocks',
                        'icon' => '',
                        'tools' => [
                            'block_discovery' => 'List all registered blocks and attributes',
                            'block_parser' => 'Parse blocks from post content',
                            'reusable_block' => 'Manage synced patterns (wp_block CPT)',
                        ],
                    ],
                    'patterns' => [
                        'name' => 'Patterns & Templates',
                        'icon' => '',
                        'tools' => [
                            'block_pattern' => 'Register and manage block patterns',
                            'fse_template' => 'Full Site Editing template management',
                            'theme_json' => 'Global styles and settings control',
                        ],
                    ],
                ],
            ],

            // ============================================
            // SEARCH PROVIDERS TOOLKIT (8 Tools)
            // ============================================

            'search_providers' => [
                'id' => 'search_providers',
                'name' => 'Search Providers',
                'icon' => '',
                'description' => 'Web search APIs: Brave, Tavily, Serper, SerpAPI, Exa (8 tools)',
                'available' => true,
                'tool_count' => 8,
                'categories' => [
                    'search' => [
                        'name' => 'Search APIs',
                        'icon' => '',
                        'tools' => [
                            'brave_search' => 'Brave Search API',
                            'tavily_search' => 'Tavily AI Search',
                            'serper_dev' => 'Serper.dev Google Search',
                            'serp_api' => 'SerpAPI multi-engine search',
                            'exa_search' => 'Exa neural search',
                            'linkup' => 'Linkup search',
                            'serply' => 'Serply search API',
                            'github_search' => 'GitHub code/repo search',
                        ],
                    ],
                ],
            ],

            // ============================================
            // WEB SCRAPING TOOLKIT (19 Tools)
            // ============================================

            'web_scraping' => [
                'id' => 'web_scraping',
                'name' => 'Web Scraping',
                'icon' => '',
                'description' => 'Website scraping: Firecrawl, Jina, Oxylabs, Apify (19 tools)',
                'available' => true,
                'tool_count' => 19,
                'categories' => [
                    'basic' => [
                        'name' => 'Basic Scraping',
                        'icon' => '',
                        'tools' => [
                            'scrape_website' => 'Basic website scraping',
                            'scrape_element' => 'CSS selector scraping',
                            'website_search' => 'Website content search',
                            'selenium_scraping' => 'Selenium browser scraping',
                        ],
                    ],
                    'premium' => [
                        'name' => 'Premium Services',
                        'icon' => '',
                        'tools' => [
                            'jina_scrape' => 'Jina AI scraping',
                            'firecrawl_scrape' => 'Firecrawl single page',
                            'firecrawl_crawl' => 'Firecrawl multi-page crawl',
                            'firecrawl_search' => 'Firecrawl search',
                            'serper_scrape' => 'Serper website scrape',
                            'scrapfly_scrape' => 'Scrapfly scraping',
                            'spider_tool' => 'Spider.cloud scraping',
                            'scrapegraph' => 'ScrapeGraph AI',
                            'tavily_extractor' => 'Tavily content extractor',
                        ],
                    ],
                    'ecommerce' => [
                        'name' => 'E-commerce & Enterprise',
                        'icon' => '',
                        'tools' => [
                            'oxylabs_amazon_product' => 'Oxylabs Amazon product',
                            'oxylabs_amazon_search' => 'Oxylabs Amazon search',
                            'oxylabs_google_search' => 'Oxylabs Google SERP',
                            'oxylabs_universal' => 'Oxylabs universal scraper',
                            'bright_data' => 'Bright Data scraping',
                            'apify_actors' => 'Apify actor execution',
                        ],
                    ],
                ],
            ],

            // ============================================
            // DATABASE CONNECTORS TOOLKIT (10 Tools)
            // ============================================

            'database_connectors' => [
                'id' => 'database_connectors',
                'name' => 'Database Connectors',
                'icon' => '',
                'description' => 'Database queries: MySQL, Postgres, vector DBs, data warehouses (10 tools)',
                'available' => true,
                'tool_count' => 10,
                'categories' => [
                    'sql' => [
                        'name' => 'SQL Databases',
                        'icon' => '',
                        'tools' => [
                            'mysql_search' => 'MySQL database queries',
                            'postgres_search' => 'PostgreSQL queries',
                            'nl2sql' => 'Natural language to SQL',
                        ],
                    ],
                    'vector' => [
                        'name' => 'Vector Databases',
                        'icon' => '',
                        'tools' => [
                            'weaviate' => 'Weaviate vector DB',
                            'qdrant' => 'Qdrant vector search',
                            'mongodb_vector' => 'MongoDB vector search',
                        ],
                    ],
                    'warehouse' => [
                        'name' => 'Data Warehouses',
                        'icon' => '',
                        'tools' => [
                            'snowflake_search' => 'Snowflake data warehouse',
                            'singlestore_search' => 'SingleStore queries',
                            'couchbase' => 'Couchbase NoSQL',
                            'databricks_query' => 'Databricks SQL queries',
                        ],
                    ],
                ],
            ],

            // ============================================
            // AI SERVICES TOOLKIT (7 Tools)
            // ============================================

            'ai_services' => [
                'id' => 'ai_services',
                'name' => 'AI Services',
                'icon' => '',
                'description' => 'AI-powered tools: DALL-E, OCR, Vision, RAG, Code Interpreter (7 tools)',
                'available' => true,
                'tool_count' => 7,
                'categories' => [
                    'generation' => [
                        'name' => 'Generation',
                        'icon' => '',
                        'tools' => [
                            'dalle' => 'DALL-E image generation',
                            'code_interpreter' => 'Code execution sandbox',
                        ],
                    ],
                    'analysis' => [
                        'name' => 'Analysis',
                        'icon' => '',
                        'tools' => [
                            'ocr' => 'Optical character recognition',
                            'vision' => 'Image analysis/vision',
                            'rag' => 'Retrieval augmented generation',
                            'patronus_eval' => 'Patronus AI evaluation',
                            'ai_mind' => 'AI Mind tool',
                        ],
                    ],
                ],
            ],

            // ============================================
            // BROWSER AUTOMATION TOOLKIT (4 Tools)
            // ============================================

            'browser_automation' => [
                'id' => 'browser_automation',
                'name' => 'Browser Automation',
                'icon' => '',
                'description' => 'Browser control: Browserbase, Stagehand, MultiOn (4 tools)',
                'available' => true,
                'tool_count' => 4,
                'categories' => [
                    'browsers' => [
                        'name' => 'Browser Control',
                        'icon' => '',
                        'tools' => [
                            'browserbase_load' => 'Browserbase page loading',
                            'hyperbrowser_load' => 'Hyperbrowser automation',
                            'stagehand' => 'Stagehand browser control',
                            'multion' => 'MultiOn web agent',
                        ],
                    ],
                ],
            ],

            // ============================================
            // EXTERNAL INTEGRATIONS TOOLKIT (11 Tools)
            // ============================================

            'external_integrations' => [
                'id' => 'external_integrations',
                'name' => 'External Integrations',
                'icon' => '',
                'description' => 'Third-party services: GitHub, Slack, Notion, YouTube, Zapier (11 tools)',
                'available' => true,
                'tool_count' => 11,
                'categories' => [
                    'productivity' => [
                        'name' => 'Productivity',
                        'icon' => '',
                        'tools' => [
                            'github' => 'GitHub API operations',
                            'slack' => 'Slack messaging',
                            'notion' => 'Notion workspace',
                            'zapier_action' => 'Zapier automation',
                        ],
                    ],
                    'content' => [
                        'name' => 'Content & Research',
                        'icon' => '',
                        'tools' => [
                            'youtube_channel_search' => 'YouTube channel search',
                            'youtube_video_search' => 'YouTube video search',
                            'arxiv_paper' => 'ArXiv paper search',
                            'code_docs_search' => 'Code documentation search',
                        ],
                    ],
                    'execution' => [
                        'name' => 'Execution',
                        'icon' => '',
                        'tools' => [
                            'e2b_sandbox' => 'E2B code sandbox',
                            'composio' => 'Composio integrations',
                            'parallel_tools' => 'Parallel tool execution',
                        ],
                    ],
                ],
            ],

            // ============================================
            // GOOGLE WORKSPACE TOOLKIT (9 Tools)
            // ============================================

            'google_workspace' => [
                'id' => 'google_workspace',
                'name' => 'Google Workspace',
                'icon' => '',
                'description' => 'Google Calendar, Gmail, Drive - direct REST API (9 tools)',
                'available' => true,
                'tool_count' => 9,
                'categories' => [
                    'calendar' => [
                        'name' => 'Google Calendar',
                        'icon' => '',
                        'tools' => [
                            'gcal_list_events' => 'List calendar events',
                            'gcal_create_event' => 'Create calendar event',
                            'gcal_update_event' => 'Update calendar event',
                        ],
                    ],
                    'gmail' => [
                        'name' => 'Gmail',
                        'icon' => '',
                        'tools' => [
                            'gmail_list_messages' => 'List/search emails',
                            'gmail_read_message' => 'Read email content',
                            'gmail_send_message' => 'Send email',
                        ],
                    ],
                    'drive' => [
                        'name' => 'Google Drive',
                        'icon' => '',
                        'tools' => [
                            'gdrive_list_files' => 'List/search files',
                            'gdrive_read_file' => 'Read file content',
                            'gdrive_upload_file' => 'Upload file',
                        ],
                    ],
                ],
            ],

            // ============================================
            // PRODUCTIVITY SERVICES TOOLKIT (12 Tools)
            // ============================================

            'productivity_services' => [
                'id' => 'productivity_services',
                'name' => 'Productivity Services',
                'icon' => '',
                'description' => 'Todoist, Trello, Asana, Jira, Perplexity - direct REST API (12 tools)',
                'available' => true,
                'tool_count' => 12,
                'categories' => [
                    'todoist' => [
                        'name' => 'Todoist',
                        'icon' => '',
                        'tools' => [
                            'todoist_list_tasks' => 'List tasks and projects',
                            'todoist_create_task' => 'Create task',
                            'todoist_update_task' => 'Update/complete task',
                        ],
                    ],
                    'trello' => [
                        'name' => 'Trello',
                        'icon' => '',
                        'tools' => [
                            'trello_list_boards' => 'List boards and cards',
                            'trello_create_card' => 'Create card',
                            'trello_update_card' => 'Update/move card',
                        ],
                    ],
                    'asana' => [
                        'name' => 'Asana',
                        'icon' => '',
                        'tools' => [
                            'asana_list_tasks' => 'List tasks and projects',
                            'asana_create_task' => 'Create task',
                        ],
                    ],
                    'jira' => [
                        'name' => 'Jira',
                        'icon' => '',
                        'tools' => [
                            'jira_search_issues' => 'Search issues (JQL)',
                            'jira_create_issue' => 'Create issue',
                        ],
                    ],
                    'perplexity' => [
                        'name' => 'Perplexity AI',
                        'icon' => '',
                        'tools' => [
                            'perplexity_search' => 'AI-powered research and answers',
                            'perplexity_ask' => 'Ask a research question',
                        ],
                    ],
                ],
            ],
        ];

        return self::$toolkits;
    }

    /**
     * Get all available tool IDs
     */
    public static function getAllToolIds(): array
    {
        $toolIds = [];

        foreach (self::getToolkits() as $toolkit) {
            foreach ($toolkit['categories'] as $category) {
                foreach ($category['tools'] as $toolId => $description) {
                    $toolIds[] = $toolId;
                }
            }
        }

        return $toolIds;
    }

    /**
     * Get tool metadata
     */
    public static function getToolInfo(string $toolId): ?array
    {
        foreach (self::getToolkits() as $toolkitId => $toolkit) {
            foreach ($toolkit['categories'] as $categoryId => $category) {
                if (isset($category['tools'][$toolId])) {
                    return [
                        'id' => $toolId,
                        'name' => $toolId,
                        'description' => $category['tools'][$toolId],
                        'toolkit_id' => $toolkitId,
                        'toolkit_name' => $toolkit['name'],
                        'category_id' => $categoryId,
                        'category_name' => $category['name'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get tools filtered by configuration
     */
    public static function getToolsForConfig(AgentConfig $config): array
    {
        // Get all tools from the toolkit loader
        if (!function_exists('get_all_toolkit_tools')) {
            return [];
        }

        $allTools = \get_all_toolkit_tools();
        Logger::debug('ToolRegistry filtering tools', [
            'tools_total' => count($allTools),
            'enabled_toolkits' => $config->enabledToolkits,
            'toolkits_configured' => $config->toolkitsConfigured,
        ]);

        $filtered = array_filter($allTools, function ($tool) use ($config) {
            $toolId = $tool->getName();
            $toolkitId = null;

            // 1. Primary: Read injected toolkit_id (from loader.php inject_toolkit_id)
            if (function_exists('get_toolkit_id')) {
                $toolkitId = \get_toolkit_id($tool);
            }

            // 2. Fallback: Registry lookup
            if (!$toolkitId) {
                $toolInfo = self::getToolInfo($toolId);
                if ($toolInfo) {
                    $toolkitId = $toolInfo['toolkit_id'];
                }
            }

            // 3. Fallback: Prefix inference
            if (!$toolkitId) {
                $toolkitId = self::inferToolkitFromToolName($toolId);
            }

            // 4. If we can determine the toolkit, check if it's enabled
            if ($toolkitId) {
                if (!$config->isToolkitEnabled($toolkitId)) {
                    return false;
                }
                return $config->isToolEnabled($toolId);
            }

            // 5. Unknown toolkit: reject when toolkits are configured
            //    (prevents mystery tools from leaking through)
            if ($config->toolkitsConfigured) {
                Logger::debug('ToolRegistry: rejecting tool with unknown toolkit', [
                    'tool_id' => $toolId,
                ]);
                return false;
            }

            // 6. If toolkits NOT configured (no admin customization), allow by default
            return $config->isToolEnabled($toolId);
        });

        $filtered = ToolAccessPolicy::filterTools(array_values($filtered));

        Logger::info('ToolRegistry tools filtered', [
            'tools_before' => count($allTools),
            'tools_after' => count($filtered),
        ]);

        return $filtered;
    }

    /**
     * Try to infer toolkit from tool name prefix
     */
    protected static function inferToolkitFromToolName(string $toolId): ?string
    {
        // Map tool prefixes to toolkit IDs
        $prefixMap = [
            'woo_' => 'woocommerce',
            'wp_' => 'wordpress_core',
            'cli_' => 'cli',
            'seo_' => 'seo',
            'security_' => 'security',
            'perf_' => 'performance',
            'file_' => 'file_access',
            'forms_' => 'forms',
            'expr_' => 'expressions',
            'research_' => 'web_research',
            'acf_' => 'custom_fields',
            'integration_' => 'integrations',
            'gcal_' => 'google_workspace',
            'gmail_' => 'google_workspace',
            'gdrive_' => 'google_workspace',
            'availability_checker' => 'appointment_booking',
            'appointment_booker' => 'appointment_booking',
            'lead_collector' => 'LeadCollection',
        ];

        foreach ($prefixMap as $prefix => $toolkit) {
            if (str_starts_with($toolId, $prefix)) {
                return $toolkit;
            }
        }

        return null;
    }

    /**
     * Get tools for a specific toolkit
     */
    public static function getToolkitTools(string $toolkitId): array
    {
        $toolkit = self::getToolkits()[$toolkitId] ?? null;

        if (!$toolkit) {
            return [];
        }

        $toolIds = [];
        foreach ($toolkit['categories'] as $category) {
            $toolIds = array_merge($toolIds, array_keys($category['tools']));
        }

        return $toolIds;
    }

    /**
     * Get default tool configuration for an agent type
     */
    public static function getDefaultConfig(string $agentType): array
    {
        switch ($agentType) {
            case 'shopping':
                return [
                    'enabled_toolkits' => ['woocommerce'],
                    'disabled_tools' => [
                        'woo_settings',      // Admin only
                        'woo_webhooks',      // Admin only
                        'woo_tax',           // Admin only
                        'woo_payment_gateways', // Admin only
                    ],
                ];

            case 'support':
                return [
                    'enabled_toolkits' => ['woocommerce', 'wordpress_core'],
                    'disabled_tools' => [
                        'woo_product_manage', // No write access
                        'woo_webhooks',
                        'woo_settings',
                        'database',           // Too powerful
                    ],
                ];

            case 'content':
                return [
                    'enabled_toolkits' => ['wordpress_content'],
                    'disabled_tools' => [],
                ];

            default:
                return [
                    'enabled_toolkits' => [],
                    'disabled_tools' => [],
                ];
        }
    }

    /**
     * Get summary for admin display
     */
    public static function getSummary(): array
    {
        $toolkits = self::getToolkits();
        $totalTools = 0;
        $availableTools = 0;

        foreach ($toolkits as $toolkit) {
            $totalTools += $toolkit['tool_count'];
            if ($toolkit['available']) {
                $availableTools += $toolkit['tool_count'];
            }
        }

        return [
            'toolkit_count' => count($toolkits),
            'total_tools' => $totalTools,
            'available_tools' => $availableTools,
            'toolkits' => array_map(fn($t) => [
                'id' => $t['id'],
                'name' => $t['name'],
                'icon' => $t['icon'],
                'available' => $t['available'],
                'tool_count' => $t['tool_count'],
            ], $toolkits),
        ];
    }
}
