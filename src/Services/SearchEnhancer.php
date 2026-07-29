<?php
declare(strict_types=1);
/**
 * Search Enhancer Service
 * 
 * Uses the hidden Search Agent to expand WordPress search queries with
 * AI-generated related keywords, improving search result relevance.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

use Quarksol\SmartChatbot\Agent\NeuronAgent;
use Quarksol\SmartChatbot\Models\ChatAgent;
use NeuronAI\Chat\Messages\UserMessage;

if (!defined('ABSPATH')) {
    exit;
}

class SearchEnhancer
{
    /** Option key for search enhancement settings */
    private const OPTION_KEY = 'swc_search_enhancement';
    
    /** Cache key prefix for search results */
    private const CACHE_PREFIX = 'swc_search_keywords_';
    
    /** Default cache duration in seconds (1 hour) */
    private const CACHE_DURATION = 3600;
    
    /** Default maximum keywords to add */
    private const DEFAULT_MAX_KEYWORDS = 5;
    
    /** Minimum search query length to enhance */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Initialize search enhancement hooks
     */
    public static function init(): void
    {
        $settings = self::getSettings();
        
        error_log('[SearchEnhancer] init() called - enabled: ' . ($settings['enabled'] ? 'YES' : 'NO'));
        
        if (!$settings['enabled']) {
            return;
        }
        
        error_log('[SearchEnhancer] Hooks registered - AI search enhancement is ACTIVE');
        
        // Hook into WordPress search query modification
        // This is broad enough to catch standard Woo searches too via the main WP_Query or custom loops
        add_filter('posts_search', [self::class, 'enhanceSearchQuery'], 10, 2);
        
        // Enqueue frontend scripts for visual enhancement
        add_action('wp_enqueue_scripts', [self::class, 'enqueueScripts']);
    }

    /**
     * Get search enhancement settings
     */
    public static function getSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'max_keywords' => self::DEFAULT_MAX_KEYWORDS,
            'cache_duration' => self::CACHE_DURATION,
            'min_query_length' => self::MIN_QUERY_LENGTH,
        ];
        
        $specificSettings = get_option(self::OPTION_KEY, []);
        
        // Also check global plugin settings
        $globalSettings = get_option('swc_chatbot_settings', []);
        if (!empty($globalSettings['search_enhancement_enabled'])) {
            $specificSettings['enabled'] = true;
            if (!empty($globalSettings['search_max_keywords'])) {
                $specificSettings['max_keywords'] = (int) $globalSettings['search_max_keywords'];
            }
            if (!empty($globalSettings['search_cache_duration'])) {
                $specificSettings['cache_duration'] = (int) $globalSettings['search_cache_duration'];
            }
        }
        
        return wp_parse_args($specificSettings, $defaults);
    }

    /**
     * Update search enhancement settings
     */
    public static function updateSettings(array $settings): bool
    {
        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Enhance WordPress search query with AI-generated keywords
     * 
     * @param string $search The current search SQL
     * @param \WP_Query $query The query object
     * @return string Modified search SQL
     */
    public static function enhanceSearchQuery(string $search, \WP_Query $query): string
    {
        // Get search term from query first
        $searchTerm = $query->get('s');
        
        // Skip if no search term or not a search query condition
        // Note: We deliberately allow non-main queries to support widgets, sidebars, AJAX searches
        if (empty($searchTerm) || !$query->is_search()) {
            return $search;
        }
        
        // Exclude admin searches (unless explicitly desired, but usually annoying for admin lists)
        if (is_admin()) {
            return $search;
        }
        
        return self::generateEnhancedSql($search, $searchTerm);
    }

    /**
     * Helper to generate enhanced SQL
     */
    private static function generateEnhancedSql(string $searchSql, string $searchTerm): string 
    {
        error_log('[SearchEnhancer] Processing search for: ' . $searchTerm);
        
        // Get additional keywords
        $keywords = self::getExpandedKeywords($searchTerm);
        
        if (empty($keywords)) {
            error_log('[SearchEnhancer] No keywords found (or all filtered out). Returning original SQL.');
            return $searchSql;
        }
        
        global $wpdb;
        
        // Build additional search conditions for each keyword
        $additionalConditions = [];
        foreach ($keywords as $keyword) {
            $like = '%' . $wpdb->esc_like($keyword) . '%';
            $additionalConditions[] = $wpdb->prepare(
                "({$wpdb->posts}.post_title LIKE %s) OR ({$wpdb->posts}.post_content LIKE %s)",
                $like,
                $like
            );
        }
        
        if (empty($additionalConditions)) {
            return $searchSql;
        }
        
        // Append additional conditions to existing search
        $additionalSql = ' OR (' . implode(' OR ', $additionalConditions) . ')';
        
        // Insert before the closing parenthesis of the search clause
        // If the search string ends with ), insert inside it.
        // Standard WP search usually ends like "AND (((post_title LIKE...) OR (...)))"
        if (preg_match('/\)\s*$/', $searchSql)) {
            $enhancedSql = preg_replace('/\)\s*$/', $additionalSql . ')', $searchSql);
        } else {
            // Fallback if structure is unexpected
            $enhancedSql = $searchSql . $additionalSql;
        }
        
        Logger::debug('SearchEnhancer: Enhanced search query', [
            'original_term' => $searchTerm,
            'additional_keywords' => $keywords,
        ]);
        
        return $enhancedSql;
    }

    /**
     * Enqueue frontend scripts
     */
    public static function enqueueScripts(): void
    {
        $settings = self::getSettings();
        if (!$settings['enabled']) {
            return;
        }
        
        if (defined('SWC_CHATBOT_URL')) {
            $url = SWC_CHATBOT_URL . 'assets/js/swc-search-enhancement.js';
        } else {
            // Fallback if constant not available
            $url = plugins_url('assets/js/swc-search-enhancement.js', dirname(__DIR__, 2) . '/index.php');
        }
        
        wp_enqueue_script(
            'swc-search-enhancement', 
            $url, 
            [], 
            '1.0.0', 
            true
        );
        
        wp_localize_script('swc-search-enhancement', 'swcSearchEnhancement', [
            'enabled' => true
        ]);
        
        // Enqueue styles
        if (defined('SWC_CHATBOT_URL')) {
            $cssUrl = SWC_CHATBOT_URL . 'assets/css/swc-search.css';
        } else {
            $cssUrl = plugins_url('assets/css/swc-search.css', dirname(__DIR__, 2) . '/index.php');
        }
        
        wp_enqueue_style(
            'swc-search-enhancement-css',
            $cssUrl,
            [],
            '1.0.0'
        );
    }

    /**
     * Get expanded keywords for a search term
     * 
     * @param string $searchTerm Original search term
     * @return array List of related keywords
     */
    public static function getExpandedKeywords(string $searchTerm): array
    {
        $settings = self::getSettings();
        
        // Skip short queries
        if (strlen($searchTerm) < $settings['min_query_length']) {
            return [];
        }
        
        $keywords = [];
        
        // 1. Add custom synonyms (always applied, no AI call needed)
        $synonymKeywords = self::getSynonymKeywords($searchTerm);
        if (!empty($synonymKeywords)) {
            $keywords = array_merge($keywords, $synonymKeywords);
        }
        
        // 2. Check cache for AI-generated keywords
        $cacheKey = self::CACHE_PREFIX . md5($searchTerm);
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            error_log('[SearchEnhancer] Cache HIT for "' . $searchTerm . '": ' . json_encode($cached));
            $keywords = array_merge($keywords, $cached);
            return array_unique($keywords);
        }
        
        error_log('[SearchEnhancer] Cache MISS for "' . $searchTerm . '" - calling AI...');
        
        // 3. Call Search Agent for AI-generated keywords
        try {
            $aiKeywords = self::callSearchAgent($searchTerm, $settings['max_keywords']);
            
            // Cache AI results
            set_transient($cacheKey, $aiKeywords, $settings['cache_duration']);
            
            $keywords = array_merge($keywords, $aiKeywords);
            return array_unique($keywords);
        } catch (\Throwable $e) {
            error_log('[SearchEnhancer] EXCEPTION: ' . $e->getMessage());
            error_log('[SearchEnhancer] Stack trace: ' . $e->getTraceAsString());
            
            // Cache empty result to avoid repeated failures
            set_transient($cacheKey, [], 300); // 5 minute cache for failures
            
            // Still return synonyms even if AI fails
            return array_unique($keywords);
        }
    }

    /**
     * Get synonym keywords for a search term
     * 
     * @param string $searchTerm The search term
     * @return array Matching synonym keywords
     */
    private static function getSynonymKeywords(string $searchTerm): array
    {
        $synonyms = get_option('swc_search_synonyms', []);
        if (!is_array($synonyms) || empty($synonyms)) {
            return [];
        }
        
        $searchTermLower = strtolower(trim($searchTerm));
        $keywords = [];
        
        foreach ($synonyms as $entry) {
            if (!is_array($entry) || empty($entry['term']) || empty($entry['synonyms'])) {
                continue;
            }
            
            $term = strtolower(trim($entry['term']));
            
            // Check if the search term matches or contains the synonym term
            if ($term === $searchTermLower || strpos($searchTermLower, $term) !== false) {
                $keywords = array_merge($keywords, (array) $entry['synonyms']);
            }
        }
        
        return $keywords;
    }


    /**
     * Call the Search Agent to generate related keywords
     * 
     * @param string $searchTerm The search term to expand
     * @param int $maxKeywords Maximum keywords to return
     * @return array List of related keywords
     */
    private static function callSearchAgent(string $searchTerm, int $maxKeywords): array
    {
        error_log('[SearchEnhancer] callSearchAgent called for: ' . $searchTerm);
        
        // Verify Search Agent exists
        $agent = ChatAgent::findBySlug('search_agent');
        
        if (!$agent) {
            error_log('[SearchEnhancer] ERROR: search_agent not found in database!');
            return [];
        }
        
        error_log('[SearchEnhancer] Found search_agent in DB, ID: ' . $agent->id);
        
        // Create NeuronAgent instance
        $neuronAgent = NeuronAgent::withConfigId('search_agent');
        
        // Check if a specific provider was selected for search enhancement
        $globalSettings = get_option('swc_chatbot_settings', []);
        $searchProviderId = $globalSettings['search_provider_instance_id'] ?? '';
        if (!empty($searchProviderId)) {
            $agentConfig = $neuronAgent->getConfig();
            if ($agentConfig) {
                $agentConfig->providerInstanceId = $searchProviderId;
                $neuronAgent->withConfig($agentConfig);
                error_log('[SearchEnhancer] Using search-specific provider: ' . $searchProviderId);
            }
        }
        
        // Build prompt - be explicit about output format
        $prompt = sprintf(
            "Generate %d search keywords related to \"%s\". Return ONLY the keywords, one per line, numbered like:\n1. keyword\n2. keyword\nNo explanations, no extra text.",
            $maxKeywords,
            $searchTerm
        );
        
        error_log('[SearchEnhancer] Sending prompt to AI: ' . $prompt);
        
        // Execute agent (synchronous, lightweight call)
        $response = $neuronAgent->chat(new UserMessage($prompt));
        
        // Extract content from AssistantMessage
        $responseText = '';
        if (is_object($response) && method_exists($response, 'getContent')) {
            $responseText = $response->getContent();
        } elseif (is_object($response) && method_exists($response, 'content')) {
            $responseText = $response->content();
        } elseif (is_string($response)) {
            $responseText = $response;
        }
        
        error_log('[SearchEnhancer] AI response: ' . substr($responseText, 0, 500));
        
        // Parse response
        $keywords = self::parseKeywordResponse($responseText, $maxKeywords);
        
        error_log('[SearchEnhancer] Parsed keywords: ' . json_encode($keywords));
        
        return $keywords;
    }

    /**
     * Parse the agent response into keywords array
     * 
     * Expected format:
     * 1-keyword1
     * 2-keyword2
     * 3-keyword3
     * 
     * @param string $response The agent's response
     * @param int $maxKeywords Maximum keywords to extract
     * @return array Parsed keywords
     */
    private static function parseKeywordResponse(string $response, int $maxKeywords): array
    {
        $keywords = [];
        
        error_log('[SearchEnhancer] Raw response to parse: ' . $response);
        
        // Strategy 1: Match numbered lists — "1. keyword", "1- keyword", "1) keyword", "1: keyword"
        preg_match_all('/^\s*\d+[\.\)\-–—:\s]+\s*(.+)$/m', $response, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $keyword) {
                $keyword = trim($keyword, " \t\n\r\0\x0B*•·\"'`");
                if (strlen($keyword) >= 2 && strlen($keyword) <= 100) {
                    $keywords[] = strtolower(preg_replace('/\s+/', ' ', $keyword));
                }
                if (count($keywords) >= $maxKeywords) break;
            }
        }
        
        // Strategy 2: Match bullet points — "- keyword", "* keyword", "• keyword"
        if (empty($keywords)) {
            preg_match_all('/^\s*[\-\*•·]\s+(.+)$/m', $response, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $keyword) {
                    $keyword = trim($keyword, " \t\n\r\0\x0B*•·\"'`");
                    if (strlen($keyword) >= 2 && strlen($keyword) <= 100) {
                        $keywords[] = strtolower(preg_replace('/\s+/', ' ', $keyword));
                    }
                    if (count($keywords) >= $maxKeywords) break;
                }
            }
        }
        
        // Strategy 3: Comma-separated or newline-separated fallback
        if (empty($keywords)) {
            // Try comma separation
            $parts = preg_split('/[,\n]+/', $response);
            foreach ($parts as $part) {
                $part = trim($part, " \t\n\r\0\x0B*•·\"'`1234567890.-):>");
                $part = trim($part);
                if (strlen($part) >= 2 && strlen($part) <= 100) {
                    $keywords[] = strtolower(preg_replace('/\s+/', ' ', $part));
                }
                if (count($keywords) >= $maxKeywords) break;
            }
        }
        
        error_log('[SearchEnhancer] Parsed ' . count($keywords) . ' keywords: ' . json_encode($keywords));
        
        return array_slice(array_unique($keywords), 0, $maxKeywords);
    }

    /**
     * Clear cached keywords (useful for testing or after agent updates)
     * 
     * @param string|null $searchTerm Specific term to clear, or null for all
     */
    public static function clearCache(?string $searchTerm = null): void
    {
        if ($searchTerm !== null) {
            delete_transient(self::CACHE_PREFIX . md5($searchTerm));
            return;
        }
        
        // Clear all search keyword caches (requires DB query)
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );
    }

    /**
     * Test the search enhancement with a sample query
     * 
     * @param string $searchTerm Term to test
     * @return array Test results with timing and keywords
     */
    public static function test(string $searchTerm): array
    {
        $startTime = microtime(true);
        
        // Clear cache for fresh test
        self::clearCache($searchTerm);
        
        $keywords = self::getExpandedKeywords($searchTerm);
        
        $duration = microtime(true) - $startTime;
        
        return [
            'search_term' => $searchTerm,
            'keywords' => $keywords,
            'keyword_count' => count($keywords),
            'duration_ms' => round($duration * 1000, 2),
            'cached' => false,
        ];
    }
}
