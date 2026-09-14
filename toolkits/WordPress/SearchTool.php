<?php

/**
 * Search Tool
 * 
 * Global WordPress content search.
 * 
 * @package Toolkits\WordPress
 */

namespace Quarksol\AgentFlowAI\Toolkits\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class SearchTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'wp_search',
            description: 'Search across WordPress content: find posts, pages, media files, users, or comments by keyword. Returns matching results with titles, excerpts, and URLs.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Search query',
                required: true
            ),
            new ToolProperty(
                name: 'type',
                type: PropertyType::STRING,
                description: 'Content type: all, post, page, media, user, comment, any',
                required: false
            ),
            new ToolProperty(
                name: 'limit',
                type: PropertyType::STRING,
                description: 'Max results (default: 20)',
                required: false
            ),
        ];
    }
    
    public function __invoke(
        string $query,
        ?string $type = 'all',
        string|int|null $limit = 20
    ): string {
        $results = [
            'query' => $query,
            'type' => $type,
            'results' => []
        ];
        
        $max = min($limit ?? 20, 100);
        
        if ($type === 'all' || $type === 'post' || $type === 'page' || $type === 'any') {
            $posts = $this->searchPosts($query, $type, $max);
            $results['results']['posts'] = $posts;
        }
        
        if ($type === 'all' || $type === 'media') {
            $media = $this->searchMedia($query, $max);
            $results['results']['media'] = $media;
        }
        
        if ($type === 'all' || $type === 'user') {
            $users = $this->searchUsers($query, $max);
            $results['results']['users'] = $users;
        }
        
        if ($type === 'all' || $type === 'comment') {
            $comments = $this->searchComments($query, $max);
            $results['results']['comments'] = $comments;
        }
        
        // Calculate totals
        $total = 0;
        foreach ($results['results'] as $type => $items) {
            $total += count($items);
        }
        $results['total'] = $total;
        $results['success'] = true;
        
        return json_encode($results);
    }
    
    private function searchPosts(string $query, string $type, string|int $limit): array
    {
        $post_types = ['post', 'page'];
        if ($type === 'post') $post_types = ['post'];
        if ($type === 'page') $post_types = ['page'];
        if ($type === 'any') $post_types = get_post_types(['public' => true]);
        
        $args = [
            's' => $query,
            'post_type' => $post_types,
            'post_status' => 'any',
            'posts_per_page' => $limit,
            'orderby' => 'relevance'
        ];
        
        $posts = get_posts($args);
        
        return array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'excerpt' => wp_trim_words(strip_tags($post->post_content), 20),
                'url' => get_permalink($post->ID),
                'edit_url' => get_edit_post_link($post->ID, 'raw')
            ];
        }, $posts);
    }
    
    private function searchMedia(string $query, string|int $limit): array
    {
        $args = [
            's' => $query,
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit
        ];
        
        $media = get_posts($args);
        
        return array_map(function($item) {
            return [
                'id' => $item->ID,
                'title' => $item->post_title,
                'mime_type' => $item->post_mime_type,
                'date' => $item->post_date,
                'url' => wp_get_attachment_url($item->ID),
                'thumbnail' => wp_get_attachment_thumb_url($item->ID)
            ];
        }, $media);
    }
    
    private function searchUsers(string $query, string|int $limit): array
    {
        $args = [
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_email', 'user_nicename', 'display_name'],
            'number' => $limit
        ];
        
        $users = get_users($args);
        
        return array_map(function($user) {
            return [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles
            ];
        }, $users);
    }
    
    private function searchComments(string $query, string|int $limit): array
    {
        global $wpdb;
        
        $query_safe = esc_sql($wpdb->esc_like($query));
        
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->comments} 
             WHERE comment_content LIKE %s 
                OR comment_author LIKE %s
             ORDER BY comment_date DESC
             LIMIT %d",
            "%{$query}%",
            "%{$query}%",
            $limit
        ));
        
        return array_map(function($comment) {
            return [
                'id' => $comment->comment_ID,
                'author' => $comment->comment_author,
                'email' => $comment->comment_author_email,
                'date' => $comment->comment_date,
                'content' => wp_trim_words(strip_tags($comment->comment_content), 20),
                'post_id' => $comment->comment_post_ID,
                'status' => wp_get_comment_status($comment)
            ];
        }, $comments);
    }
}
