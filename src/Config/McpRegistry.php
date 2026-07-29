<?php
declare(strict_types=1);



namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MCP Registry
 * 
 * Central registry for available Model Context Protocol (MCP) servers.
 * Only includes MCPs with verified official servers maintained by
 * the service providers themselves (listed on modelcontextprotocol/servers).
 * 
 * STRATEGY: Remote-Only (SSE/HTTP) for WordPress compatibility.
 * All default URLs point to official remote endpoints hosted by
 * the service providers themselves — zero third-party proxies.
 * 
 * @package Quarksol\SmartChatbot\Config
 */
class McpRegistry
{
    /**
     * Get list of available/example MCPs
     * 
     * Returns 22 verified official MCP servers organized by category.
     * All servers use SSE or HTTP transport for maximum compatibility.
     * 
     * @return array
     */
    public static function getExamples(): array
    {
        return [
            // ========================================
            // PRODUCTIVITY & COLLABORATION (5)
            // ========================================
            'slack' => [
                'id' => 'slack',
                'name' => 'Slack',
                'description' => 'Search messages, read channels, send messages, and manage Slack workspaces.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.slack.com/mcp',
                ],
                'icon' => 'admin-comments',
                'category' => 'productivity',
                'api_key_field' => 'SLACK_BOT_TOKEN',
                'capabilities' => ['Search messages', 'Read channels', 'Send messages'],
                'docs_url' => 'https://github.com/modelcontextprotocol/servers-archived/tree/main/src/slack',
            ],
            'notion' => [
                'id' => 'notion',
                'name' => 'Notion',
                'description' => 'Manage Notion pages, databases, and workspaces via the official Notion MCP.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.notion.com/mcp',
                ],
                'icon' => 'editor-table',
                'category' => 'productivity',
                'api_key_field' => 'NOTION_API_KEY',
                'capabilities' => ['Manage pages', 'Search databases', 'Append blocks'],
                'docs_url' => 'https://github.com/makenotion/notion-mcp-server',
            ],
            'linear' => [
                'id' => 'linear',
                'name' => 'Linear',
                'description' => 'Search, create, and update Linear issues, projects, and comments.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.linear.app/mcp',
                ],
                'icon' => 'align-left',
                'category' => 'productivity',
                'api_key_field' => 'LINEAR_API_KEY',
                'docs_url' => 'https://linear.app/docs/mcp',
            ],
            'todoist' => [
                'id' => 'todoist',
                'name' => 'Todoist',
                'description' => 'Search, add, and update Todoist tasks, projects, sections, and comments.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://ai.todoist.net/mcp',
                ],
                'icon' => 'yes-alt',
                'category' => 'productivity',
                'api_key_field' => 'TODOIST_API_TOKEN',
                'docs_url' => 'https://github.com/doist/todoist-ai',
            ],
            'atlassian' => [
                'id' => 'atlassian',
                'name' => 'Atlassian (Jira & Confluence)',
                'description' => 'Interact with Jira work items and Confluence pages, and search across both.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.atlassian.com/v1/sse',
                ],
                'icon' => 'tickets-alt',
                'category' => 'productivity',
                'api_key_field' => 'ATLASSIAN_API_TOKEN',
                'docs_url' => 'https://www.atlassian.com/platform/remote-mcp-server',
            ],

            // ========================================
            // DEVELOPMENT & CODE (7)
            // ========================================
            'github' => [
                'id' => 'github',
                'name' => 'GitHub',
                'description' => 'GitHub\'s official MCP — manage repositories, issues, PRs, and workflows.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://api.githubcopilot.com/mcp/',
                ],
                'icon' => 'randomize',
                'category' => 'development',
                'api_key_field' => 'GITHUB_TOKEN',
                'capabilities' => ['Manage repositories', 'Search issues', 'Create PRs'],
                'docs_url' => 'https://github.com/github/github-mcp-server',
            ],
            'gitlab' => [
                'id' => 'gitlab',
                'name' => 'GitLab',
                'description' => 'GitLab\'s official MCP — manage projects, issues, merge requests via OAuth 2.0.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.gitlab.com/sse',
                ],
                'icon' => 'rest-api',
                'category' => 'development',
                'api_key_field' => 'GITLAB_TOKEN',
                'docs_url' => 'https://docs.gitlab.com/user/gitlab_duo/model_context_protocol/mcp_server/',
            ],
            'supabase' => [
                'id' => 'supabase',
                'name' => 'Supabase',
                'description' => 'Interact with Supabase: create tables, query data, deploy edge functions, and more.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.supabase.com/mcp',
                ],
                'icon' => 'database',
                'category' => 'development',
                'api_key_field' => 'SUPABASE_ACCESS_TOKEN',
                'docs_url' => 'https://github.com/supabase-community/supabase-mcp',
            ],
            'cloudflare' => [
                'id' => 'cloudflare',
                'name' => 'Cloudflare',
                'description' => 'Deploy and configure Workers, KV, R2, D1, and interrogate Cloudflare resources.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.cloudflare.com/mcp',
                ],
                'icon' => 'cloud-saved',
                'category' => 'development',
                'api_key_field' => 'CLOUDFLARE_API_TOKEN',
                'docs_url' => 'https://github.com/cloudflare/mcp-server-cloudflare',
            ],
            'vercel' => [
                'id' => 'vercel',
                'name' => 'Vercel',
                'description' => 'Access logs, search docs, manage projects and deployments on Vercel.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://vercel.com/api/mcp',
                ],
                'icon' => 'arrow-up-alt',
                'category' => 'development',
                'api_key_field' => 'VERCEL_TOKEN',
                'docs_url' => 'https://vercel.com/docs/mcp/vercel-mcp',
            ],
            'e2b' => [
                'id' => 'e2b',
                'name' => 'E2B Code Sandbox',
                'description' => 'Execute code in secure cloud sandboxes with E2B.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.e2b.dev',
                ],
                'icon' => 'media-code',
                'category' => 'development',
                'api_key_field' => 'E2B_API_KEY',
                'docs_url' => 'https://e2b.dev',
            ],
            'context7' => [
                'id' => 'context7',
                'name' => 'Context7',
                'description' => 'Access up-to-date library documentation and examples by Upstash.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://context7.com/mcp',
                ],
                'icon' => 'text-page',
                'category' => 'development',
                'api_key_field' => null,
                'docs_url' => 'https://github.com/upstash/mcp-server',
            ],

            // ========================================
            // RESEARCH & WEB (6)
            // ========================================
            'tavily' => [
                'id' => 'tavily',
                'name' => 'Tavily Search',
                'description' => 'AI-optimized search engine for agents — search, extract, crawl, and map.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.tavily.com/mcp',
                ],
                'icon' => 'search',
                'category' => 'research',
                'api_key_field' => 'TAVILY_API_KEY',
                'capabilities' => ['Web search', 'Extract content', 'Crawl sites'],
                'docs_url' => 'https://github.com/tavily-ai/tavily-mcp',
            ],
            'exa-search' => [
                'id' => 'exa-search',
                'name' => 'Exa Search',
                'description' => 'AI-powered search engine made for AIs by Exa.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.exa.ai/mcp',
                ],
                'icon' => 'search',
                'category' => 'research',
                'api_key_field' => 'EXA_API_KEY',
                'docs_url' => 'https://github.com/exa-labs/exa-mcp-server',
            ],
            'perplexity' => [
                'id' => 'perplexity',
                'name' => 'Perplexity',
                'description' => 'Real-time web-wide research via Perplexity\'s Sonar API.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://api.perplexity.ai/mcp',
                ],
                'icon' => 'lightbulb',
                'category' => 'research',
                'api_key_field' => 'PERPLEXITY_API_KEY',
                'docs_url' => 'https://github.com/ppl-ai/modelcontextprotocol',
            ],
            'firecrawl' => [
                'id' => 'firecrawl',
                'name' => 'Firecrawl',
                'description' => 'Extract web data with powerful scraping, crawling, and JS rendering.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.firecrawl.dev/v2/mcp',
                ],
                'icon' => 'admin-site',
                'category' => 'research',
                'api_key_field' => 'FIRECRAWL_API_KEY',
                'docs_url' => 'https://github.com/firecrawl/firecrawl-mcp-server',
            ],
            'brightdata' => [
                'id' => 'brightdata',
                'name' => 'Bright Data',
                'description' => 'Discover, extract, and interact with the web — automated access across the internet.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.brightdata.com/mcp',
                ],
                'icon' => 'visibility',
                'category' => 'research',
                'api_key_field' => 'BRIGHTDATA_API_KEY',
                'docs_url' => 'https://github.com/luminati-io/brightdata-mcp',
            ],
            'browserbase' => [
                'id' => 'browserbase',
                'name' => 'Browserbase',
                'description' => 'Automate browser interactions in the cloud — navigation, extraction, form filling.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.browserbase.com/mcp',
                ],
                'icon' => 'desktop',
                'category' => 'research',
                'api_key_field' => 'BROWSERBASE_API_KEY',
                'docs_url' => 'https://github.com/browserbase/mcp-server-browserbase',
            ],

            // ========================================
            // DATA & DATABASES (3)
            // ========================================
            'alpha-vantage' => [
                'id' => 'alpha-vantage',
                'name' => 'Alpha Vantage',
                'description' => 'Access 100+ APIs for financial market data — stocks, fundamentals, and more.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.alphavantage.co/',
                ],
                'icon' => 'chart-line',
                'category' => 'data',
                'api_key_field' => 'ALPHA_VANTAGE_API_KEY',
                'docs_url' => 'https://mcp.alphavantage.co/',
            ],
            'pinecone' => [
                'id' => 'pinecone',
                'name' => 'Pinecone',
                'description' => 'Pinecone\'s developer MCP — search docs and manage vector data.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.pinecone.io/mcp',
                ],
                'icon' => 'database-view',
                'category' => 'data',
                'api_key_field' => 'PINECONE_API_KEY',
                'docs_url' => 'https://github.com/pinecone-io/pinecone-mcp',
            ],

            // ========================================
            // AI & UTILITIES (3)
            // ========================================
            'composio' => [
                'id' => 'composio',
                'name' => 'Composio',
                'description' => 'Connect 100+ tools — zero setup, auth built-in. Made for agents.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.composio.dev/mcp',
                ],
                'icon' => 'plugins-checked',
                'category' => 'ai',
                'api_key_field' => 'COMPOSIO_API_KEY',
                'docs_url' => 'https://docs.composio.dev/docs/mcp-overview',
            ],
            'google-maps' => [
                'id' => 'google-maps',
                'name' => 'Google Maps',
                'description' => 'Official Google Maps Platform — geocoding, directions, places, and route data.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.googleapis.com/maps/mcp',
                ],
                'icon' => 'location',
                'category' => 'ai',
                'api_key_field' => 'GOOGLE_MAPS_API_KEY',
                'capabilities' => ['Geocoding', 'Directions', 'Place details'],
                'docs_url' => 'https://github.com/googlemaps/platform-ai/tree/main/packages/code-assist',
            ],
            // ========================================
            // REGIONAL & LOCAL (NEW)
            // ========================================
            'india-cities' => [
                'id' => 'india-cities',
                'name' => 'India Cities Knowledge',
                'description' => 'Comprehensive data about Indian cities, populations, and landmarks.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.quarksol.com/india-cities',
                ],
                'icon' => 'location',
                'category' => 'research',
                'api_key_field' => null,
                'capabilities' => ['List cities', 'Get city details', 'Population stats'],
                'docs_url' => null,
            ],
            'weather' => [
                'id' => 'weather',
                'name' => 'Weather Updates',
                'description' => 'Real-time weather data and forecasts for any location.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://mcp.quarksol.com/weather',
                ],
                'icon' => 'cloud-saved',
                'category' => 'research',
                'api_key_field' => 'WEATHER_API_KEY',
                'capabilities' => ['Current weather', '7-day forecast', 'Alerts'],
                'docs_url' => null,
            ],

            // ========================================
            // CUSTOM (1)
            // ========================================
            'custom-sse' => [
                'id' => 'custom-sse',
                'name' => 'Custom Remote MCP',
                'description' => 'Connect to any remote MCP server using Server-Sent Events (SSE) or HTTP.',
                'type' => 'sse',
                'default_config' => [
                    'url' => 'https://your-mcp-server.com/sse',
                ],
                'icon' => 'globe',
                'category' => 'custom',
                'api_key_field' => null,
                'docs_url' => null,
            ],
        ];
    }

    /**
     * Get categories for grouping
     */
    public static function getCategories(): array
    {
        return [
            'productivity' => 'Productivity & Collaboration',
            'development' => 'Development & Code',
            'research' => 'Research & Web',
            'data' => 'Data & Databases',
            'ai' => 'AI & Utilities',
            'custom' => 'Custom',
        ];
    }

    /**
     * Get icon mappings for frontend
     * 
     * Maps icon keys to Dashicons or wp-icons
     */
    public static function getIconMappings(): array
    {
        return [
            'admin-comments' => 'admin-comments',
            'editor-table' => 'editor-table',
            'align-left' => 'align-left',
            'yes-alt' => 'yes-alt',
            'tickets-alt' => 'tickets-alt',
            'randomize' => 'randomize',
            'rest-api' => 'rest-api',
            'database' => 'database',
            'cloud-saved' => 'cloud-saved',
            'arrow-up-alt' => 'arrow-up-alt',
            'media-code' => 'media-code',
            'text-page' => 'text-page',
            'search' => 'search',
            'lightbulb' => 'lightbulb',
            'admin-site' => 'admin-site',
            'visibility' => 'visibility',
            'desktop' => 'desktop',
            'chart-line' => 'chart-line',
            'database-view' => 'database-view',
            'plugins-checked' => 'plugins-checked',
            'location' => 'location',
            'globe' => 'globe',
        ];
    }
}
