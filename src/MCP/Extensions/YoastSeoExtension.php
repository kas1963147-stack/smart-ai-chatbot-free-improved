<?php

/**
 * Yoast SEO Extension
 *
 * Provides SEO tools when Yoast SEO plugin is active.
 */

namespace Quarksol\SmartChatbot\MCP\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

class YoastSeoExtension implements McpExtension
{
    /**
     * Check if Yoast SEO is active.
     */
    public static function isActive(): bool
    {
        return defined('WPSEO_VERSION');
    }

    /**
     * Get category metadata.
     */
    public static function getCategory(): array
    {
        return [
            'id' => 'seo_yoast',
            'label' => 'SEO (Yoast)',
            'icon' => 'Search',
        ];
    }

    /**
     * Get tool definitions.
     */
    public static function getTools(): array
    {
        return [
            self::tool('yoast_get_seo_meta', 'Get SEO metadata for a post', [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('yoast_update_seo_meta', 'Update SEO metadata for a post', [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
                'title' => ['type' => 'string', 'description' => 'SEO title'],
                'description' => ['type' => 'string', 'description' => 'Meta description'],
                'focus_keyword' => ['type' => 'string', 'description' => 'Focus keyphrase'],
            ], false),

            self::tool('yoast_get_seo_score', 'Get SEO and readability scores for a post', [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('yoast_analyze_url', 'Get SEO head data for any URL', [
                'url' => ['type' => 'string', 'description' => 'URL to analyze', 'required' => true],
            ], true),

            self::tool('yoast_get_social_meta', 'Get Open Graph and Twitter meta for a post', [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('yoast_update_social_meta', 'Update social media metadata', [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
                'og_title' => ['type' => 'string', 'description' => 'Open Graph title'],
                'og_description' => ['type' => 'string', 'description' => 'Open Graph description'],
                'twitter_title' => ['type' => 'string', 'description' => 'Twitter title'],
                'twitter_description' => ['type' => 'string', 'description' => 'Twitter description'],
            ], false),

            self::tool('yoast_get_schema', 'Get schema.org data for a post', [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('yoast_bulk_get_seo', 'Get SEO data for multiple posts', [
                'post_ids' => ['type' => 'array', 'description' => 'Array of post IDs', 'required' => true],
            ], true),
        ];
    }

    /**
     * Execute a tool.
     */
    public static function executeTool(string $toolName, array $params): mixed
    {
        return match ($toolName) {
            'yoast_get_seo_meta' => self::getSeoMeta($params['post_id']),
            'yoast_update_seo_meta' => self::updateSeoMeta($params),
            'yoast_get_seo_score' => self::getSeoScore($params['post_id']),
            'yoast_analyze_url' => self::analyzeUrl($params['url']),
            'yoast_get_social_meta' => self::getSocialMeta($params['post_id']),
            'yoast_update_social_meta' => self::updateSocialMeta($params),
            'yoast_get_schema' => self::getSchema($params['post_id']),
            'yoast_bulk_get_seo' => self::bulkGetSeo($params['post_ids']),
            default => throw new \Exception("Unknown tool: $toolName"),
        };
    }

    // ========== Tool Implementations ==========

    private static function getSeoMeta(int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        return [
            'post_id' => $postId,
            'title' => get_post_meta($postId, '_yoast_wpseo_title', true) ?: '',
            'description' => get_post_meta($postId, '_yoast_wpseo_metadesc', true) ?: '',
            'focus_keyword' => get_post_meta($postId, '_yoast_wpseo_focuskw', true) ?: '',
            'canonical' => get_post_meta($postId, '_yoast_wpseo_canonical', true) ?: '',
            'robots_noindex' => get_post_meta($postId, '_yoast_wpseo_meta-robots-noindex', true) ?: '0',
            'robots_nofollow' => get_post_meta($postId, '_yoast_wpseo_meta-robots-nofollow', true) ?: '0',
        ];
    }

    private static function updateSeoMeta(array $params): array
    {
        $postId = $params['post_id'];
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        $updated = [];
        $metaMap = [
            'title' => '_yoast_wpseo_title',
            'description' => '_yoast_wpseo_metadesc',
            'focus_keyword' => '_yoast_wpseo_focuskw',
        ];

        foreach ($metaMap as $param => $metaKey) {
            if (isset($params[$param])) {
                update_post_meta($postId, $metaKey, sanitize_text_field($params[$param]));
                $updated[$param] = $params[$param];
            }
        }

        return [
            'success' => true,
            'post_id' => $postId,
            'updated' => $updated,
        ];
    }

    private static function getSeoScore(int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        $seoScore = get_post_meta($postId, '_yoast_wpseo_linkdex', true);
        $readabilityScore = get_post_meta($postId, '_yoast_wpseo_content_score', true);

        return [
            'post_id' => $postId,
            'seo_score' => $seoScore ? (int) $seoScore : null,
            'seo_rating' => self::scoreToRating($seoScore),
            'readability_score' => $readabilityScore ? (int) $readabilityScore : null,
            'readability_rating' => self::scoreToRating($readabilityScore),
            'focus_keyword' => get_post_meta($postId, '_yoast_wpseo_focuskw', true) ?: '',
        ];
    }

    private static function scoreToRating($score): string
    {
        if (!$score)
            return 'not-set';
        $score = (int) $score;
        if ($score >= 70)
            return 'good';
        if ($score >= 40)
            return 'ok';
        return 'needs-improvement';
    }

    private static function analyzeUrl(string $url): array
    {
        // Use Yoast's REST API endpoint
        $apiUrl = rest_url('yoast/v1/get_head') . '?url=' . urlencode($url);

        $response = wp_remote_get($apiUrl, [
            'timeout' => 10,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception("Failed to analyze URL: " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'url' => $url,
            'status' => $body['status'] ?? 'unknown',
            'head_html' => $body['html'] ?? '',
            'json' => $body['json'] ?? [],
        ];
    }

    private static function getSocialMeta(int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        return [
            'post_id' => $postId,
            'opengraph' => [
                'title' => get_post_meta($postId, '_yoast_wpseo_opengraph-title', true) ?: '',
                'description' => get_post_meta($postId, '_yoast_wpseo_opengraph-description', true) ?: '',
                'image' => get_post_meta($postId, '_yoast_wpseo_opengraph-image', true) ?: '',
            ],
            'twitter' => [
                'title' => get_post_meta($postId, '_yoast_wpseo_twitter-title', true) ?: '',
                'description' => get_post_meta($postId, '_yoast_wpseo_twitter-description', true) ?: '',
                'image' => get_post_meta($postId, '_yoast_wpseo_twitter-image', true) ?: '',
            ],
        ];
    }

    private static function updateSocialMeta(array $params): array
    {
        $postId = $params['post_id'];
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        $updated = [];
        $metaMap = [
            'og_title' => '_yoast_wpseo_opengraph-title',
            'og_description' => '_yoast_wpseo_opengraph-description',
            'twitter_title' => '_yoast_wpseo_twitter-title',
            'twitter_description' => '_yoast_wpseo_twitter-description',
        ];

        foreach ($metaMap as $param => $metaKey) {
            if (isset($params[$param])) {
                update_post_meta($postId, $metaKey, sanitize_text_field($params[$param]));
                $updated[$param] = $params[$param];
            }
        }

        return [
            'success' => true,
            'post_id' => $postId,
            'updated' => $updated,
        ];
    }

    private static function getSchema(int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        // Try to get schema from Yoast's API
        $url = get_permalink($postId);
        $result = self::analyzeUrl($url);

        $schema = [];
        if (isset($result['json']['schema'])) {
            $schema = $result['json']['schema'];
        }

        return [
            'post_id' => $postId,
            'url' => $url,
            'schema' => $schema,
        ];
    }

    private static function bulkGetSeo(array $postIds): array
    {
        $results = [];
        foreach ($postIds as $postId) {
            try {
                $results[$postId] = self::getSeoMeta((int) $postId);
            } catch (\Exception $e) {
                $results[$postId] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }

    // ========== Helper ==========

    private static function tool(string $name, string $description, array $params, bool $readOnly): array
    {
        $properties = [];
        $required = [];

        foreach ($params as $paramName => $config) {
            $properties[$paramName] = [
                'type' => $config['type'],
                'description' => $config['description'],
            ];
            if ($config['required'] ?? false) {
                $required[] = $paramName;
            }
        }

        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
            ],
            'default' => false, // Extensions are disabled by default
            'extension' => true, // Mark as extension tool
            'annotations' => [
                'title' => ucwords(str_replace('_', ' ', $name)),
                'readOnlyHint' => $readOnly,
                'destructiveHint' => false,
                'idempotentHint' => true,
            ],
        ];
    }
}
