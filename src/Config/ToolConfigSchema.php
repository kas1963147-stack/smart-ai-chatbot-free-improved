<?php
declare(strict_types=1);


/**
 * Tool Configuration Schema
 * 
 * Defines the configuration requirements for tools that need external inputs.
 * 
 * @package Quarksol\SmartChatbot\Config
 */

namespace Quarksol\SmartChatbot\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tool Configuration Schema
 * 
 * Declares what configuration inputs a tool requires.
 */
class ToolConfigSchema
{
    /**
     * Configuration field types
     */
    const TYPE_TEXT = 'text';
    const TYPE_PASSWORD = 'password';
    const TYPE_OAUTH = 'oauth';
    const TYPE_SELECT = 'select';
    const TYPE_TOGGLE = 'toggle';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_NUMBER = 'number';
    const TYPE_URL = 'url';
    const TYPE_CONNECTION = 'connection';
    
    /**
     * Create a text field configuration
     */
    public static function text(string $id, string $label, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_TEXT,
            'required' => false,
            'default' => '',
            'description' => null,
            'placeholder' => null,
        ], $options);
    }
    
    /**
     * Create a password/secret field configuration
     */
    public static function password(string $id, string $label, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_PASSWORD,
            'required' => false,
            'default' => '',
            'description' => null,
            'placeholder' => null,
            'encrypted' => true,
        ], $options);
    }
    
    /**
     * Create an OAuth connection field
     */
    public static function oauth(string $id, string $label, string $provider, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_OAUTH,
            'provider' => $provider,
            'required' => false,
            'description' => null,
            'scopes' => [],
        ], $options);
    }
    
    /**
     * Create a select dropdown field
     */
    public static function select(string $id, string $label, array $choices, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_SELECT,
            'choices' => $choices,
            'required' => false,
            'default' => null,
            'description' => null,
            'multiple' => false,
        ], $options);
    }
    
    /**
     * Create a toggle/boolean field
     */
    public static function toggle(string $id, string $label, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_TOGGLE,
            'required' => false,
            'default' => false,
            'description' => null,
        ], $options);
    }
    
    /**
     * Create a textarea field
     */
    public static function textarea(string $id, string $label, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_TEXTAREA,
            'required' => false,
            'default' => '',
            'description' => null,
            'placeholder' => null,
            'rows' => 4,
        ], $options);
    }
    
    /**
     * Create a number field
     */
    public static function number(string $id, string $label, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_NUMBER,
            'required' => false,
            'default' => 0,
            'description' => null,
            'min' => null,
            'max' => null,
            'step' => 1,
        ], $options);
    }
    
    /**
     * Create a URL field
     */
    public static function url(string $id, string $label, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_URL,
            'required' => false,
            'default' => '',
            'description' => null,
            'placeholder' => 'https://',
        ], $options);
    }
    
    /**
     * Create an integration connection selector
     */
    public static function connection(string $id, string $label, array $options = []): array
    {
        return array_merge([
            'id' => $id,
            'label' => $label,
            'type' => self::TYPE_CONNECTION,
            'required' => false,
            'description' => null,
            'integration_filter' => null, // Filter by integration_id
            'multiple' => false,
        ], $options);
    }
    
    /**
     * Validate a configuration value against its schema
     */
    public static function validate(array $schema, $value): array
    {
        $errors = [];
        
        // Check required
        if (!empty($schema['required']) && empty($value)) {
            $errors[] = sprintf('%s is required', $schema['label']);
        }
        
        // Type-specific validation
        switch ($schema['type']) {
            case self::TYPE_URL:
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = sprintf('%s must be a valid URL', $schema['label']);
                }
                break;
                
            case self::TYPE_NUMBER:
                if (!empty($value)) {
                    if (!is_numeric($value)) {
                        $errors[] = sprintf('%s must be a number', $schema['label']);
                    } else {
                        if (isset($schema['min']) && $value < $schema['min']) {
                            $errors[] = sprintf('%s must be at least %s', $schema['label'], $schema['min']);
                        }
                        if (isset($schema['max']) && $value > $schema['max']) {
                            $errors[] = sprintf('%s must be at most %s', $schema['label'], $schema['max']);
                        }
                    }
                }
                break;
                
            case self::TYPE_SELECT:
                if (!empty($value) && !empty($schema['choices'])) {
                    $validChoices = array_keys($schema['choices']);
                    if (!in_array($value, $validChoices)) {
                        $errors[] = sprintf('%s has an invalid selection', $schema['label']);
                    }
                }
                break;
        }
        
        return $errors;
    }
    
    /**
     * Sanitize a configuration value based on its type
     */
    public static function sanitize(array $schema, $value): mixed
    {
        switch ($schema['type']) {
            case self::TYPE_TEXT:
            case self::TYPE_PASSWORD:
            case self::TYPE_TEXTAREA:
                return sanitize_text_field($value);
                
            case self::TYPE_URL:
                return esc_url_raw($value);
                
            case self::TYPE_NUMBER:
                return is_numeric($value) ? floatval($value) : 0;
                
            case self::TYPE_TOGGLE:
                return (bool)$value;
                
            case self::TYPE_SELECT:
            case self::TYPE_CONNECTION:
                return sanitize_key($value);
                
            default:
                return $value;
        }
    }
}
