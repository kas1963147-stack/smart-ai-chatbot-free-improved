<?php
declare(strict_types=1);
/**
 * Agent Resolver Service
 * 
 * Determines which agent(s) should be available on a given page context.
 * Handles priority-based resolution and multi-agent scenarios.
 * 
 * @package SWC\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Models\ChatAgent;
use Quarksol\SmartChatbot\Models\ChatAssignment;
use SWC\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Page Context
 * 
 * Represents the current page/request context for agent resolution.
 */
class PageContext {
    
    /** Current page/post ID */
    public ?int $pageId = null;
    
    /** Current post type */
    public ?string $postType = null;
    
    /** Current URL path */
    public string $url = '';
    
    /** Taxonomy terms (term_id => taxonomy) */
    public array $taxonomies = [];
    
    /** Is WooCommerce cart page */
    public bool $isCart = false;
    
    /** Is WooCommerce checkout page */
    public bool $isCheckout = false;
    
    /** Is WooCommerce my account page */
    public bool $isAccount = false;
    
    /** Is single product */
    public bool $isProduct = false;
    
    /** Is shop/archive */
    public bool $isShop = false;
    
    /**
     * Create context from current WordPress request
     */
    public static function fromRequest(): self {
        $context = new self();
        
        // Get current post/page info
        $queried_object = get_queried_object();
        
        if ($queried_object instanceof \WP_Post) {
            $context->pageId = $queried_object->ID;
            $context->postType = $queried_object->post_type;
        }
        
        // Get URL
        $context->url = isset($_SERVER['REQUEST_URI']) 
            ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '/';
        
        // WooCommerce special pages
        if (function_exists('is_cart') && is_cart()) {
            $context->isCart = true;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            $context->isCheckout = true;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            $context->isAccount = true;
        }
        if (function_exists('is_product') && is_product()) {
            $context->isProduct = true;
            $context->postType = 'product';
        }
        if (function_exists('is_shop') && is_shop()) {
            $context->isShop = true;
        }
        
        // Get taxonomy terms
        if ($context->pageId) {
            $taxonomies = get_object_taxonomies($context->postType ?: 'post');
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_object_terms($context->pageId, $taxonomy, ['fields' => 'ids']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $term_id) {
                        $context->taxonomies[$term_id] = $taxonomy;
                    }
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Create context from array data (for API requests)
     */
    public static function fromArray(array $data): self {
        $context = new self();
        
        $context->pageId = !empty($data['page_id']) ? (int) $data['page_id'] : null;
        $context->postType = $data['post_type'] ?? null;
        $context->url = $data['url'] ?? '/';
        $context->isCart = !empty($data['is_cart']);
        $context->isCheckout = !empty($data['is_checkout']);
        $context->isAccount = !empty($data['is_account']);
        $context->isProduct = !empty($data['is_product']);
        $context->isShop = !empty($data['is_shop']);
        $context->taxonomies = $data['taxonomies'] ?? [];
        
        return $context;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'page_id' => $this->pageId,
            'post_type' => $this->postType,
            'url' => $this->url,
            'is_cart' => $this->isCart,
            'is_checkout' => $this->isCheckout,
            'is_account' => $this->isAccount,
            'is_product' => $this->isProduct,
            'is_shop' => $this->isShop,
            'taxonomies' => $this->taxonomies,
        ];
    }
}

/**
 * Agent Resolver
 * 
 * Resolves which agent(s) should handle chat for a given page context.
 */
class AgentResolver {
    
    /**
     * Get all agents available for the given context
     * 
     * Returns agents sorted by priority (highest first).
     * Multiple agents may be returned if assigned to the same location.
     * 
     * @param PageContext $context
     * @return ChatAgent[]
     */
    public function getAgentsForContext(PageContext $context): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        $matchingAssignments = $this->findMatchingAssignments($context);
        
        if (empty($matchingAssignments)) {
            // Fall back to default agent
            $default = ChatAgent::getDefault();
            return $default ? [$default] : [];
        }
        
        // Collect agents from both direct assignments and groups
        $agents = [];
        // Collect IDs to batch load
        $agentIdsToLoad = [];
        foreach ($matchingAssignments as $assignment) {
            if (!$assignment->isGroup() && $assignment->agentDbId) {
                $agentIdsToLoad[] = $assignment->agentDbId;
            }
        }
        
        // Batch load agents
        $loadedAgents = !empty($agentIdsToLoad) 
            ? ChatAgent::findMultiple(array_unique($agentIdsToLoad)) 
            : [];
        
        // Collect agents from both direct assignments and groups
        $agents = [];
        $seenAgentIds = [];
        
        foreach ($matchingAssignments as $assignment) {
            if ($assignment->isGroup()) {
                // Get agents from the group
                $group = $assignment->getGroup();
                if ($group) {
                    foreach ($group->getMemberAgents() as $agent) {
                        if (!in_array($agent->id, $seenAgentIds) && $agent->isActive) {
                            $agents[] = $agent;
                            $seenAgentIds[] = $agent->id;
                        }
                    }
                }
            } else {
                // Direct agent assignment
                if ($assignment->agentDbId && !in_array($assignment->agentDbId, $seenAgentIds)) {
                    // Use preloaded agent
                    $agent = $loadedAgents[$assignment->agentDbId] ?? null;
                    if ($agent && $agent->isActive) {
                        $agents[] = $agent;
                        $seenAgentIds[] = $assignment->agentDbId;
                    }
                }
            }
        }
        
        return $agents;
    }
    
    /**
     * Resolve orchestration for context
     * 
     * Returns detailed resolution info including whether this is a group,
     * the orchestration mode, and the selected agent(s).
     * 
     * @param PageContext $context
     * @return array Resolution details
     */
    public function resolveOrchestration(PageContext $context): array {
        $matchingAssignments = $this->findMatchingAssignments($context);
        
        if (empty($matchingAssignments)) {
            $default = ChatAgent::getDefault();
            return [
                'type' => 'single',
                'mode' => 'single',
                'agent' => $default,
                'agents' => $default ? [$default] : [],
                'group' => null,
            ];
        }
        
        // Get highest priority assignment
        $primary = $matchingAssignments[0];
        
        if ($primary->isGroup()) {
            $group = $primary->getGroup();
            if ($group) {
                return [
                    'type' => 'group',
                    'mode' => $group->orchestrationMode,
                    'agent' => $group->getPrimaryAgent(),
                    'agents' => $group->getMemberAgents(),
                    'group' => $group,
                ];
            }
        }
        
        // Single agent
        $agent = ChatAgent::find($primary->agentDbId);
        return [
            'type' => 'single',
            'mode' => 'single',
            'agent' => $agent,
            'agents' => $agent ? [$agent] : [],
            'group' => null,
        ];
    }
    
    /**
     * Get the primary (highest priority) agent for context
     */
    public function getPrimaryAgent(PageContext $context): ?ChatAgent {
        $agents = $this->getAgentsForContext($context);
        return $agents[0] ?? null;
    }
    
    /**
     * Check if multiple agents are available (requires user selection)
     */
    public function requiresSelection(PageContext $context): bool {
        return count($this->getAgentsForContext($context)) > 1;
    }
    
    /**
     * Find matching assignments for context
     * 
     * Priority order (highest to lowest):
     * 1. Specific page ID
     * 2. WooCommerce special pages (cart, checkout, account)
     * 3. Post type
     * 4. URL pattern
     * 5. Taxonomy
     * 6. Global
     * 
     * @return ChatAssignment[]
     */
    protected function findMatchingAssignments(PageContext $context): array {
        $matches = [];
        
        // 1. Check specific page ID (highest priority)
        if ($context->pageId) {
            $pageMatches = ChatAssignment::forLocation(
                ChatAssignment::TYPE_PAGE_ID,
                (string) $context->pageId
            );
            $matches = array_merge($matches, $pageMatches);
        }
        
        // 2. Check WooCommerce special pages
        if ($context->isCart) {
            $cartMatches = ChatAssignment::forLocation(ChatAssignment::TYPE_CART);
            $matches = array_merge($matches, $cartMatches);
        }
        
        if ($context->isCheckout) {
            $checkoutMatches = ChatAssignment::forLocation(ChatAssignment::TYPE_CHECKOUT);
            $matches = array_merge($matches, $checkoutMatches);
        }
        
        if ($context->isAccount) {
            $accountMatches = ChatAssignment::forLocation(ChatAssignment::TYPE_ACCOUNT);
            $matches = array_merge($matches, $accountMatches);
        }
        
        // 3. Check post type
        if ($context->postType) {
            $typeMatches = ChatAssignment::forLocation(
                ChatAssignment::TYPE_POST_TYPE,
                $context->postType
            );
            $matches = array_merge($matches, $typeMatches);
        }
        
        // 4. Check URL patterns
        $urlPatternMatches = $this->findUrlPatternMatches($context->url);
        $matches = array_merge($matches, $urlPatternMatches);
        
        // 5. Check taxonomy terms (Batch optimized)
        $taxValues = [];
        foreach ($context->taxonomies as $termId => $taxonomy) {
            $taxValues[] = "{$taxonomy}:{$termId}";
        }
        
        if (!empty($taxValues)) {
            $taxMatches = ChatAssignment::forLocationValues(
                ChatAssignment::TYPE_TAXONOMY,
                $taxValues
            );
            $matches = array_merge($matches, $taxMatches);
        }
        
        // 6. If no specific matches, use global assignments
        if (empty($matches)) {
            $globalMatches = ChatAssignment::forLocation(ChatAssignment::TYPE_GLOBAL);
            $matches = array_merge($matches, $globalMatches);
        }
        
        // Sort by priority (highest first)
        usort($matches, fn($a, $b) => $b->priority <=> $a->priority);
        
        return $matches;
    }
    
    /**
     * Find URL pattern matches
     */
    protected function findUrlPatternMatches(string $url): array {
        global $wpdb;
        $tables = Schema::getTableNames();
        
        // Get all URL pattern assignments
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.* FROM {$tables['assignments']} a
                 JOIN {$tables['agents']} ag ON a.agent_db_id = ag.id
                 WHERE a.location_type = %s AND a.is_active = 1 AND ag.is_active = 1",
                ChatAssignment::TYPE_URL_PATTERN
            ),
            ARRAY_A
        );
        
        $matches = [];
        
        foreach ($rows ?: [] as $row) {
            $pattern = $row['location_value'] ?? '';
            
            // Convert simple wildcard pattern to regex
            // e.g., /contact* becomes /contact.*
            $regex = '/^' . str_replace(
                ['*', '/'],
                ['.*', '\/'],
                preg_quote($pattern, '/')
            ) . '$/i';
            
            // Remove double escaping of wildcards
            $regex = str_replace('\\.\\*', '.*', $regex);
            
            if (@preg_match($regex, $url)) {
                $matches[] = new ChatAssignment($row);
            }
        }
        
        return $matches;
    }
}
