<?php

/**
 * Post Read Tool
 * 
 * Search, query, and read posts, pages, and custom post types.
 * 
 * @package Toolkits\WordPressContent
 */

namespace Quarksol\AgentFlowAI\Toolkits\WordPressContent;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class PostReadTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'wp_read_posts',
            description: 'Search and read posts, pages, or custom post types. Get single post by ID, search by keyword, filter by author, category, date, status, etc.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'action',
                type: PropertyType::STRING,
                description: 'Action: get (by ID), search, list, count',
                required: true
            ),
            new ToolProperty(
                name: 'post_id',
                type: PropertyType::STRING,
                description: 'Post ID (for get action)',
                required: false
            ),
            new ToolProperty(
                name: 'search',
                type: PropertyType::STRING,
                description: 'Search keyword',
                required: false
            ),
            new ToolProperty(
                name: 'post_type',
                type: PropertyType::STRING,
                description: 'Post type: post, page, any, or custom type. Default: any',
                required: false
            ),
            new ToolProperty(
                name: 'status',
                type: PropertyType::STRING,
                description: 'Status: publish, draft, pending, private, trash, any',
                required: false
            ),
            new ToolProperty(
                name: 'author_id',
                type: PropertyType::STRING,
                description: 'Filter by author ID',
                required: false
            ),
            new ToolProperty(
                name: 'category',
                type: PropertyType::STRING,
                description: 'Category name or ID',
                required: false
            ),
            new ToolProperty(
                name: 'tag',
                type: PropertyType::STRING,
                description: 'Tag slug',
                required: false
            ),
            new ToolProperty(
                name: 'orderby',
                type: PropertyType::STRING,
                description: 'Order by: date, title, modified, rand, menu_order',
                required: false
            ),
            new ToolProperty(
                name: 'order',
                type: PropertyType::STRING,
                description: 'Order: ASC or DESC',
                required: false
            ),
            new ToolProperty(
                name: 'limit',
                type: PropertyType::STRING,
                description: 'Max results (default: 10)',
                required: false
            ),
            new ToolProperty(
                name: 'page',
                type: PropertyType::STRING,
                description: 'Page number for pagination',
                required: false
            ),
            new ToolProperty(
                name: 'include_content',
                type: PropertyType::STRING,
                description: 'Include full content? yes/no (default: no for list, yes for get)',
                required: false
            ),
        ];
    }
    
    public function __invoke(
        string $action,
        string|int|null $post_id = null,
        ?string $search = null,
        ?string $post_type = 'any',
        ?string $status = 'publish',
        string|int|null $author_id = null,
        ?string $category = null,
        ?string $tag = null,
        ?string $orderby = 'date',
        ?string $order = 'DESC',
        string|int|null $limit = 10,
        string|int|null $page = 1,
        ?string $include_content = 'no'
    ): string {
        // Coerce types
        $post_id = $post_id !== null ? (int) $post_id : null;
        $author_id = $author_id !== null ? (int) $author_id : null;
        $limit = (int) ($limit ?? 10);
        $page = (int) ($page ?? 1);
        
        switch ($action) {
            case 'get':
                return $this->getPost($post_id, $include_content === 'yes');
                
            case 'search':
            case 'list':
                return $this->queryPosts([
                    's' => $search,
                    'post_type' => $post_type,
                    'post_status' => $status,
                    'author' => $author_id,
                    'category_name' => $category,
                    'tag' => $tag,
                    'orderby' => $orderby,
                    'order' => $order,
                    'posts_per_page' => min($limit ?? 10, 50),
                    'paged' => $page ?? 1,
                ], $include_content === 'yes');
                
            case 'count':
                return $this->countPosts($post_type, $status);
                
            default:
                return json_encode(['error' => 'Invalid action. Use: get, search, list, count']);
        }
    }
    
    private function getPost(string|int|null $post_id, bool $includeContent = true): string
    {
        if (!$post_id) {
            return json_encode(['error' => 'post_id required for get action']);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            return json_encode(['error' => 'Post not found']);
        }
        
        return json_encode([
            'success' => true,
            'post' => $this->formatPost($post, $includeContent)
        ]);
    }
    
    private function queryPosts(array $args, bool $includeContent = false): string
    {
        // Clean up null values
        $args = array_filter($args, fn($v) => $v !== null && $v !== '');
        
        $query = new \WP_Query($args);
        
        $posts = [];
        foreach ($query->posts as $post) {
            $posts[] = $this->formatPost($post, $includeContent);
        }
        
        return json_encode([
            'success' => true,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $args['paged'] ?? 1,
            'count' => count($posts),
            'posts' => $posts
        ]);
    }
    
    private function countPosts(?string $postType, ?string $status): string
    {
        $counts = wp_count_posts($postType ?? 'post');
        
        if ($status && isset($counts->$status)) {
            return json_encode([
                'success' => true,
                'post_type' => $postType,
                'status' => $status,
                'count' => $counts->$status
            ]);
        }
        
        return json_encode([
            'success' => true,
            'post_type' => $postType,
            'counts' => (array) $counts
        ]);
    }
    
    private function formatPost(\WP_Post $post, bool $includeContent = false): array
    {
        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'type' => $post->post_type,
            'status' => $post->post_status,
            'author_id' => $post->post_author,
            'author_name' => get_the_author_meta('display_name', $post->post_author),
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'url' => get_permalink($post),
            'edit_url' => admin_url("post.php?post={$post->ID}&action=edit"),
            'excerpt' => $post->post_excerpt ?: wp_trim_words($post->post_content, 30),
        ];
        
        // Add featured image
        $thumbnail_id = get_post_thumbnail_id($post);
        if ($thumbnail_id) {
            $data['featured_image'] = [
                'id' => $thumbnail_id,
                'url' => wp_get_attachment_image_url($thumbnail_id, 'large')
            ];
        }
        
        // Add categories and tags (for posts)
        if ($post->post_type === 'post') {
            $categories = get_the_category($post->ID);
            $data['categories'] = array_map(fn($c) => ['id' => $c->term_id, 'name' => $c->name], $categories);
            
            $tags = get_the_tags($post->ID);
            $data['tags'] = $tags ? array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name], $tags) : [];
        }
        
        // Add parent (for pages)
        if ($post->post_type === 'page' && $post->post_parent) {
            $parent = get_post($post->post_parent);
            $data['parent'] = [
                'id' => $parent->ID,
                'title' => $parent->post_title
            ];
        }
        
        // Include full content if requested
        if ($includeContent) {
            $data['content'] = $post->post_content;
            $data['content_rendered'] = apply_filters('the_content', $post->post_content);
        }
        
        return $data;
    }
}
