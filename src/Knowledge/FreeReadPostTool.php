<?php
declare(strict_types=1);
/**
 * Free Read Post Tool (NeuronAI)
 *
 * Hidden free-tier tool: Read full content of a WordPress post/article.
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

class FreeReadPostTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'read_post',
            description: 'Read the full content of a specific blog post or page by its ID. Use search_posts first to find the post ID.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'post_id',
                type: PropertyType::INTEGER,
                description: 'The ID of the post to read',
                required: true
            ),
        ];
    }

    public function __invoke(int $post_id): string
    {
        error_log('[SWC Free Tool] read_post called for ID: ' . $post_id);

        $post = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return json_encode([
                'error' => 'Post not found or not published.',
            ]);
        }

        // Strip HTML and convert to clean readable text
        $content = wp_strip_all_tags($post->post_content);
        $content = wp_trim_words($content, 500, '...');

        return json_encode([
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $content,
            'url'     => get_permalink($post->ID),
            'date'    => get_the_date('M j, Y', $post),
            'author'  => get_the_author_meta('display_name', $post->post_author),
            'type'    => $post->post_type === 'page' ? 'Page' : 'Blog Post',
        ]);
    }
}
