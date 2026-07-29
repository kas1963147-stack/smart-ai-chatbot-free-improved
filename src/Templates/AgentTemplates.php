<?php
declare(strict_types=1);
/**
 * Agent Templates
 * 
 * Pre-built agent configurations for common use cases.
 * 12 production-ready visitor-facing templates.
 * 
 * @package SWC_Chatbot
 */

namespace Quarksol\SmartChatbot\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AgentTemplates
 */
class AgentTemplates {

    /**
     * Category definitions
     */
    private const CATEGORIES = [
        'universal' => [
            'label' => 'Universal',
            'description' => 'Essential agents every site needs',
            'icon' => '',
        ],
        'ecommerce' => [
            'label' => 'E-Commerce',
            'description' => 'Agents for online stores and WooCommerce',
            'icon' => '',
        ],
        'service' => [
            'label' => 'Service & Professional',
            'description' => 'Agents for service businesses and professionals',
            'icon' => '',
        ],
        'sales' => [
            'label' => 'Sales',
            'description' => 'Agents focused on lead generation and sales',
            'icon' => '',
        ],
        'content' => [
            'label' => 'Content & Marketing',
            'description' => 'Agents for content creation, SEO, and marketing',
            'icon' => '',
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Agents for data analysis and reporting',
            'icon' => '',
        ],
        'admin' => [
            'label' => 'Administration',
            'description' => 'Agents for site administration and maintenance',
            'icon' => '',
        ],
    ];

    /**
     * Get all available templates dynamically from default-agents.php
     */
    public static function getAll(): array {
        $defaultsPath = dirname(__DIR__) . '/Config/default-agents.php';
        if (!file_exists($defaultsPath)) {
            return [];
        }
        
        $defaults = include $defaultsPath;
        $templates = [];
        
        foreach ($defaults as $agent) {
            $id = $agent['agent_id'];
            
            // Map toolkits to legacy 'enabled_tools' format for the templates
            $enabledTools = array_map('strtolower', $agent['config']['enabled_toolkits'] ?? []);
            
            $templates[$id] = [
                'id'          => $id,
                'name'        => $agent['name'],
                'description' => $agent['description'],
                'icon'        => $agent['avatar'] ?? '',
                'category'    => $agent['category'] ?? 'universal',
                'config'      => [
                    'agent_id'        => $id,
                    'name'            => $agent['name'],
                    'system_prompt'   => $agent['config']['prompt_sections']['system'] ?? '',
                    'enabled_tools'   => $enabledTools,
                    'enabled_skills'  => $agent['config']['enabled_skills'] ?? [],
                    'mcp_configs'     => $agent['config']['mcp_configs'] ?? [],
                    'welcome_message' => $agent['config']['welcome_message'] ?? '',
                ],
            ];
        }
        
        return $templates;
    }

    /**
     * Get template by ID
     */
    public static function get(string $id): ?array {
        $templates = self::getAll();
        return $templates[$id] ?? null;
    }

    /**
     * Get all category definitions
     */
    public static function getCategories(): array {
        return self::CATEGORIES;
    }

    /**
     * Get templates organized by category
     */
    public static function getByCategory(): array {
        $templates = self::getAll();
        $organized = [];

        foreach (self::CATEGORIES as $catKey => $catInfo) {
            $organized[$catKey] = [
                'label' => $catInfo['label'],
                'description' => $catInfo['description'],
                'icon' => $catInfo['icon'],
                'templates' => [],
            ];
        }

        foreach ($templates as $id => $template) {
            $category = $template['category'] ?? 'universal';
            if (isset($organized[$category])) {
                $organized[$category]['templates'][$id] = $template;
            }
        }

        return $organized;
    }
}
