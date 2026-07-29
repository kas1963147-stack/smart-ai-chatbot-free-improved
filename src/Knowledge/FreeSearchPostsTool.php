<?php
declare(strict_types=1);
/**
 * Free Search Posts Tool (NeuronAI)
 *
 * Hidden free-tier tool: WordPress blog post/article search.
 * Always available to agents — no admin configuration needed.
 *
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

if (!defined('ABSPATH')) {
    exit;
}

class FreeSearchPostsTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'search_posts',
            description: 'Search blog posts and articles on the site by keyword. Returns titles, excerpts, dates, and links.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'keyword',
                type: PropertyType::STRING,
                description: 'Search keyword or topic (e.g. "gardening tips", "best practices")',
                required: true
            ),
            new ToolProperty(
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Maximum number of results to return (default: 5)',
                required: false
            ),
        ];
    }

    public function __invoke(string $keyword, ?int $limit = 5): string
    {
        error_log('[SWC Free Tool] search_posts called: ' . $keyword);

        $limit = $limit ?? 5;

        $query = new \WP_Query([
            's'              => sanitize_text_field($keyword),
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'relevance',
        ]);

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return json_encode([
                'message' => 'No articles or blog posts found matching "' . $keyword . '".',
                'posts' => [],
            ]);
        }

        $results = [];
        while ($query->have_posts()) {
            $query->the_post();
            $post = get_post();
            $results[] = [
                'id'      => $post->ID,
                'title'   => get_the_title(),
                'excerpt' => wp_trim_words(get_the_excerpt(), 30, '...'),
                'url'     => get_permalink(),
                'date'    => get_the_date('M j, Y'),
                'type'    => $post->post_type === 'page' ? 'Page' : 'Blog Post',
            ];
        }
        wp_reset_postdata();

        return json_encode([
            'posts' => $results,
            'total_found' => $query->found_posts,
        ]);
    }
}
