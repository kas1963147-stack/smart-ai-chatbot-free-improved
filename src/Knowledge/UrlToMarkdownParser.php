<?php
declare(strict_types=1);
/**
 * URL to Markdown Parser
 * 
 * Fetches HTML from a URL and extracts the main content,
 * converting it to clean Markdown format.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

class UrlToMarkdownParser {
    
    /**
     * Fetch URL content and convert to Markdown
     * 
     * @param string $url The URL to fetch
     * @return array [ 'title' => string, 'content' => string ]
     * @throws \Exception If fetch fails
     */
    public function parse(string $url): array {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception("Invalid URL provided.");
        }

        // Fetch HTML
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user-agent' => 'SmartWooChatbot/1.0 (WordPress AI Agent)',
        ]);

        if (is_wp_error($response)) {
            throw new \Exception("Failed to fetch URL: " . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            throw new \Exception("HTTP Error {$code} when fetching URL.");
        }

        $html = wp_remote_retrieve_body($response);
        
        return $this->htmlToMarkdown($html);
    }

    /**
     * Strip HTML down to core text and format as Markdown
     */
    private function htmlToMarkdown(string $html): array {
        // Extract title
        $title = 'Extracted Document';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
            $title = trim(strip_tags($matches[1]));
        }

        // Try to extract just the main content if possible (article, main, element with 'content' class/id)
        $body = $html;
        
        // Remove head, script, style, nav, footer, header tags
        $body = preg_replace('/<head\b[^>]*>(.*?)<\/head>/is', '', $body);
        $body = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $body);
        $body = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $body);
        $body = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $body);
        $body = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $body);
        $body = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $body);

        // Convert structured tags to markdown
        // Replace <br> with newline
        $body = preg_replace('/<br\s*\/?>/is', "\n", $body);
        
        // Replace <p> blocks
        $body = preg_replace('/<p\b[^>]*>(.*?)<\/p>/is', "\n$1\n\n", $body);
        
        // Replace lists
        $body = preg_replace('/<li\b[^>]*>(.*?)<\/li>/is', "\n- $1", $body);
        
        // Replace headings
        for ($i = 1; $i <= 6; $i++) {
            $hashes = str_repeat('#', $i);
            $body = preg_replace("/<h{$i}\b[^>]*>(.*?)<\/h{$i}>/is", "\n{$hashes} $1\n\n", $body);
        }
        
        // Replace bold and italic
        $body = preg_replace('/<strong\b[^>]*>(.*?)<\/strong>/is', "**$1**", $body);
        $body = preg_replace('/<b\b[^>]*>(.*?)<\/b>/is', "**$1**", $body);
        $body = preg_replace('/<em\b[^>]*>(.*?)<\/em>/is', "*$1*", $body);
        $body = preg_replace('/<i\b[^>]*>(.*?)<\/i>/is', "*$1*", $body);
        
        // Replace links
        $body = preg_replace('/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', "[$3]($2)", $body);

        // Strip all remaining HTML tags
        $text = strip_tags($body);
        
        // Decode HTML entities (e.g., &amp;, &quot;)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove excessive empty lines
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

        return [
            'title' => $title,
            'content' => $text,
        ];
    }
}
