<?php
declare(strict_types=1);
/**
 * KnowledgeManager
 *
 * Loads knowledge documents from the database and provides context summaries for prompts.
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

class KnowledgeManager
{
    /**
     * Get knowledge summaries for system prompt (progressive disclosure)
     * 
     * Returns a compact catalog of knowledge documents (titles and descriptions).
     * The AI uses `read_knowledge(title)` to fetch full content on-demand.
     * 
     * @return string Formatted knowledge summaries for system prompt
     */
    public static function getSummariesForPrompt(): string
    {
        $items = KnowledgeDocument::all(false); // Do not load full content
        
        if (empty($items)) {
            return '';
        }
        
        $summaries = ["## Available Knowledge Base\n"];
        $summaries[] = "You have access to the following documents. Use `read_knowledge(title)` to read the full content of a specific document.\n";
        
        foreach ($items as $item) {
            $desc = !empty($item->description) ? ": {$item->description}" : '';
            $summaries[] = "- **{$item->title}**{$desc}";
        }
        
        return implode("\n", $summaries);
    }
    
    /**
     * Get knowledge summaries filtered for a specific agent
     * 
     * @param \Quarksol\SmartChatbot\Config\AgentConfig $config Agent configuration
     * @return string Formatted knowledge summaries
     */
    public static function getSummariesForAgent(\Quarksol\SmartChatbot\Config\AgentConfig $config): string
    {
        // Add specific config filtering if needed in the future
        return self::getSummariesForPrompt();
    }

    /**
     * Get always-on knowledge content (full text for critical items)
     * 
     * Returns the full content of documents marked as "always on",
     * which should be injected into every system prompt.
     * 
     * Gracefully handles cases where the always_on column doesn't exist yet
     * (e.g., before a schema migration has been run).
     * 
     * @return string Formatted always-on knowledge content, or empty string
     */
    public static function getAlwaysOnContent(): string
    {
        global $wpdb;
        $table = KnowledgeDocument::tableName();

        // Check if the always_on column exists in the table
        // This prevents fatal SQL errors if the schema hasn't been updated yet
        $columnExists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'always_on'",
                $table
            )
        );

        if (!$columnExists) {
            return '';
        }

        // Fetch documents marked as always_on with their full content
        $rows = $wpdb->get_results(
            "SELECT title, content FROM {$table} WHERE always_on = 1 ORDER BY title ASC",
            ARRAY_A
        );

        if (empty($rows)) {
            return '';
        }

        $sections = ["## 📚 Core Knowledge (Always Active)\n"];
        $sections[] = "The following knowledge is critical and always available:\n";

        foreach ($rows as $row) {
            $title = $row['title'] ?? '';
            $content = $row['content'] ?? '';
            if (!empty($content)) {
                $sections[] = "### {$title}\n{$content}\n";
            }
        }

        return implode("\n", $sections);
    }
}
