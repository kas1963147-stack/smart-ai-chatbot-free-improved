<?php
declare(strict_types=1);
/**
 * Search Service Interface
 * 
 * @package Quarksol\SmartChatbot\Contracts
 */

namespace Quarksol\SmartChatbot\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for search services
 */
interface SearchServiceInterface {
    /**
     * Search for items matching query
     * 
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array Search results
     */
    public function search(string $query, int $limit = 10): array;
}
