<?php

/**
 * ACF (Advanced Custom Fields) Extension
 *
 * Provides custom field tools when ACF plugin is active.
 */

namespace Quarksol\SmartChatbot\MCP\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

class AcfExtension implements McpExtension
{
    /**
     * Check if ACF is active.
     */
    public static function isActive(): bool
    {
        return class_exists('ACF') || function_exists('get_fields');
    }

    /**
     * Get category metadata.
     */
    public static function getCategory(): array
    {
        return [
            'id' => 'custom_fields_acf',
            'label' => 'Custom Fields (ACF)',
            'icon' => 'Database',
        ];
    }

    /**
     * Get tool definitions.
     */
    public static function getTools(): array
    {
        return [
            self::tool('acf_get_fields', 'Get all ACF field values for a post', [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('acf_get_field', 'Get a single ACF field value', [
                'field_name' => ['type' => 'string', 'description' => 'Field name or key', 'required' => true],
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('acf_update_field', 'Update an ACF field value', [
                'field_name' => ['type' => 'string', 'description' => 'Field name or key', 'required' => true],
                'value' => ['type' => 'string', 'description' => 'New field value', 'required' => true],
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], false),

            self::tool('acf_get_field_groups', 'List all ACF field groups', [], true),

            self::tool('acf_get_group_fields', 'Get field definitions for a field group', [
                'group_id' => ['type' => 'integer', 'description' => 'Field group ID', 'required' => true],
            ], true),

            self::tool('acf_get_options', 'Get ACF options page values', [
                'option_name' => ['type' => 'string', 'description' => 'Option field name (optional)'],
            ], true),

            self::tool('acf_update_option', 'Update an ACF options page field', [
                'field_name' => ['type' => 'string', 'description' => 'Field name', 'required' => true],
                'value' => ['type' => 'string', 'description' => 'New value', 'required' => true],
            ], false),

            self::tool('acf_get_repeater', 'Get repeater field rows', [
                'field_name' => ['type' => 'string', 'description' => 'Repeater field name', 'required' => true],
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('acf_add_repeater_row', 'Add a row to a repeater field', [
                'field_name' => ['type' => 'string', 'description' => 'Repeater field name', 'required' => true],
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
                'row_data' => ['type' => 'object', 'description' => 'Row data as key-value pairs', 'required' => true],
            ], false),

            self::tool('acf_get_relationship', 'Get relationship/post object field values', [
                'field_name' => ['type' => 'string', 'description' => 'Relationship field name', 'required' => true],
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),

            self::tool('acf_search_by_field', 'Search posts by ACF field value', [
                'field_name' => ['type' => 'string', 'description' => 'Field name to search', 'required' => true],
                'value' => ['type' => 'string', 'description' => 'Value to match', 'required' => true],
                'post_type' => ['type' => 'string', 'description' => 'Post type (default: any)'],
            ], true),

            self::tool('acf_get_flexible_content', 'Get flexible content field layouts', [
                'field_name' => ['type' => 'string', 'description' => 'Flexible content field name', 'required' => true],
                'post_id' => ['type' => 'integer', 'description' => 'Post ID', 'required' => true],
            ], true),
        ];
    }

    /**
     * Execute a tool.
     */
    public static function executeTool(string $toolName, array $params): mixed
    {
        return match ($toolName) {
            'acf_get_fields' => self::getFields($params['post_id']),
            'acf_get_field' => self::getField($params['field_name'], $params['post_id']),
            'acf_update_field' => self::updateField($params),
            'acf_get_field_groups' => self::getFieldGroups(),
            'acf_get_group_fields' => self::getGroupFields($params['group_id']),
            'acf_get_options' => self::getOptions($params['option_name'] ?? null),
            'acf_update_option' => self::updateOption($params),
            'acf_get_repeater' => self::getRepeater($params['field_name'], $params['post_id']),
            'acf_add_repeater_row' => self::addRepeaterRow($params),
            'acf_get_relationship' => self::getRelationship($params['field_name'], $params['post_id']),
            'acf_search_by_field' => self::searchByField($params),
            'acf_get_flexible_content' => self::getFlexibleContent($params['field_name'], $params['post_id']),
            default => throw new \Exception("Unknown tool: $toolName"),
        };
    }

    // ========== Tool Implementations ==========

    private static function getFields(int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        if (!function_exists('get_fields')) {
            throw new \Exception("ACF function get_fields not available");
        }

        $fields = get_fields($postId);

        return [
            'post_id' => $postId,
            'post_title' => $post->post_title,
            'fields' => $fields ?: [],
            'field_count' => $fields ? count($fields) : 0,
        ];
    }

    private static function getField(string $fieldName, int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        if (!function_exists('get_field')) {
            throw new \Exception("ACF function get_field not available");
        }

        $value = get_field($fieldName, $postId);
        $fieldObject = get_field_object($fieldName, $postId);

        return [
            'post_id' => $postId,
            'field_name' => $fieldName,
            'value' => $value,
            'field_type' => $fieldObject['type'] ?? 'unknown',
            'field_label' => $fieldObject['label'] ?? $fieldName,
        ];
    }

    private static function updateField(array $params): array
    {
        $postId = $params['post_id'];
        $fieldName = $params['field_name'];
        $value = $params['value'];

        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        if (!function_exists('update_field')) {
            throw new \Exception("ACF function update_field not available");
        }

        $result = update_field($fieldName, $value, $postId);

        return [
            'success' => (bool) $result,
            'post_id' => $postId,
            'field_name' => $fieldName,
            'new_value' => $value,
        ];
    }

    private static function getFieldGroups(): array
    {
        if (!function_exists('acf_get_field_groups')) {
            throw new \Exception("ACF function acf_get_field_groups not available");
        }

        $groups = acf_get_field_groups();
        $result = [];

        foreach ($groups as $group) {
            $result[] = [
                'id' => $group['ID'],
                'key' => $group['key'],
                'title' => $group['title'],
                'active' => $group['active'],
                'style' => $group['style'] ?? 'default',
            ];
        }

        return [
            'field_groups' => $result,
            'count' => count($result),
        ];
    }

    private static function getGroupFields(int $groupId): array
    {
        if (!function_exists('acf_get_fields')) {
            throw new \Exception("ACF function acf_get_fields not available");
        }

        $fields = acf_get_fields($groupId);
        $result = [];

        if ($fields) {
            foreach ($fields as $field) {
                $result[] = [
                    'key' => $field['key'],
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'required' => $field['required'] ?? false,
                    'instructions' => $field['instructions'] ?? '',
                ];
            }
        }

        return [
            'group_id' => $groupId,
            'fields' => $result,
            'count' => count($result),
        ];
    }

    private static function getOptions(?string $optionName): array
    {
        if (!function_exists('get_field')) {
            throw new \Exception("ACF function get_field not available");
        }

        if ($optionName) {
            $value = get_field($optionName, 'option');
            return [
                'option_name' => $optionName,
                'value' => $value,
            ];
        }

        // Get all options fields
        if (function_exists('get_fields')) {
            $options = get_fields('option');
            return [
                'options' => $options ?: [],
                'count' => $options ? count($options) : 0,
            ];
        }

        return ['options' => [], 'count' => 0];
    }

    private static function updateOption(array $params): array
    {
        if (!function_exists('update_field')) {
            throw new \Exception("ACF function update_field not available");
        }

        $result = update_field($params['field_name'], $params['value'], 'option');

        return [
            'success' => (bool) $result,
            'field_name' => $params['field_name'],
            'new_value' => $params['value'],
        ];
    }

    private static function getRepeater(string $fieldName, int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        if (!function_exists('have_rows') || !function_exists('get_sub_field')) {
            throw new \Exception("ACF repeater functions not available");
        }

        $rows = [];
        if (have_rows($fieldName, $postId)) {
            $index = 0;
            while (have_rows($fieldName, $postId)) {
                the_row();
                $row = get_row();
                $rows[] = [
                    'index' => $index++,
                    'data' => $row,
                ];
            }
        }

        return [
            'post_id' => $postId,
            'field_name' => $fieldName,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    private static function addRepeaterRow(array $params): array
    {
        if (!function_exists('add_row')) {
            throw new \Exception("ACF function add_row not available");
        }

        $result = add_row($params['field_name'], $params['row_data'], $params['post_id']);

        return [
            'success' => $result !== false,
            'post_id' => $params['post_id'],
            'field_name' => $params['field_name'],
            'new_row_index' => $result,
        ];
    }

    private static function getRelationship(string $fieldName, int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        if (!function_exists('get_field')) {
            throw new \Exception("ACF function get_field not available");
        }

        $related = get_field($fieldName, $postId);
        $result = [];

        if ($related) {
            foreach ((array) $related as $relatedPost) {
                if (is_object($relatedPost)) {
                    $result[] = [
                        'ID' => $relatedPost->ID,
                        'title' => $relatedPost->post_title,
                        'type' => $relatedPost->post_type,
                        'status' => $relatedPost->post_status,
                    ];
                } elseif (is_numeric($relatedPost)) {
                    $p = get_post($relatedPost);
                    if ($p) {
                        $result[] = [
                            'ID' => $p->ID,
                            'title' => $p->post_title,
                            'type' => $p->post_type,
                            'status' => $p->post_status,
                        ];
                    }
                }
            }
        }

        return [
            'post_id' => $postId,
            'field_name' => $fieldName,
            'related_posts' => $result,
            'count' => count($result),
        ];
    }

    private static function searchByField(array $params): array
    {
        global $wpdb;

        $fieldName = $params['field_name'];
        $value = $params['value'];
        $postType = $params['post_type'] ?? 'any';

        $args = [
            'post_type' => $postType,
            'posts_per_page' => 50,
            'meta_query' => [
                [
                    'key' => $fieldName,
                    'value' => $value,
                    'compare' => 'LIKE',
                ],
            ],
        ];

        $query = new \WP_Query($args);
        $results = [];

        foreach ($query->posts as $post) {
            $results[] = [
                'ID' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'field_value' => get_field($fieldName, $post->ID),
            ];
        }

        return [
            'search_field' => $fieldName,
            'search_value' => $value,
            'post_type' => $postType,
            'results' => $results,
            'count' => count($results),
        ];
    }

    private static function getFlexibleContent(string $fieldName, int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception("Post not found: $postId");
        }

        if (!function_exists('have_rows')) {
            throw new \Exception("ACF function have_rows not available");
        }

        $layouts = [];
        if (have_rows($fieldName, $postId)) {
            while (have_rows($fieldName, $postId)) {
                the_row();
                $layouts[] = [
                    'layout' => get_row_layout(),
                    'data' => get_row(),
                ];
            }
        }

        return [
            'post_id' => $postId,
            'field_name' => $fieldName,
            'layouts' => $layouts,
            'layout_count' => count($layouts),
        ];
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
                'idempotentHint' => !$readOnly,
            ],
        ];
    }
}
