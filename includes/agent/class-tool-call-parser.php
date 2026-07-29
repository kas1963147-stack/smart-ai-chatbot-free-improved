<?php
/**
 * Tool Call Parser
 * 
 * Parses AI responses to extract and execute tool calls.
 * Handles both JSON function call format and XML-style tool calls.
 * 
 * @package SWC_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SWC_Tool_Call_Parser
 * 
 * Parses AI responses for tool invocations and executes them via the Tool Registry.
 */
class SWC_Tool_Call_Parser {

    /**
     * Registry instance
     * @var SWC_Tool_Registry
     */
    private $registry;

    /**
     * Constructor
     */
    public function __construct() {
        require_once SWC_CHATBOT_PATH . 'includes/agent/class-tool-registry.php';
        $this->registry = SWC_Tool_Registry::get_instance();
    }

    /**
     * Parse AI response for tool calls and execute them
     * 
     * @param string $ai_response The raw AI response
     * @return array Parsed result with tool outputs
     */
    public function parse_and_execute($ai_response) {
        $result = [
            'has_tool_calls' => false,
            'tool_results' => [],
            'modified_response' => $ai_response,
            'original_response' => $ai_response
        ];

        // Try JSON function call format first (OpenAI-style)
        $json_calls = $this->extract_json_tool_calls($ai_response);
        if (!empty($json_calls)) {
            $result['has_tool_calls'] = true;
            $result['tool_results'] = $this->execute_tool_calls($json_calls);
            $result['modified_response'] = $this->inject_results($ai_response, $result['tool_results'], 'json');
            return $result;
        }

        // Try XML-style tool calls (Anthropic/Claude style)
        $xml_calls = $this->extract_xml_tool_calls($ai_response);
        if (!empty($xml_calls)) {
            $result['has_tool_calls'] = true;
            $result['tool_results'] = $this->execute_tool_calls($xml_calls);
            $result['modified_response'] = $this->inject_results($ai_response, $result['tool_results'], 'xml');
            return $result;
        }

        // Try markdown code block format (generic)
        $md_calls = $this->extract_markdown_tool_calls($ai_response);
        if (!empty($md_calls)) {
            $result['has_tool_calls'] = true;
            $result['tool_results'] = $this->execute_tool_calls($md_calls);
            $result['modified_response'] = $this->inject_results($ai_response, $result['tool_results'], 'markdown');
            return $result;
        }

        return $result;
    }

    /**
     * Extract JSON-format tool calls (OpenAI function calling style)
     * 
     * Looks for: {"tool": "tool_name", "action": "action_name", "params": {...}}
     * 
     * @param string $response
     * @return array
     */
    private function extract_json_tool_calls($response) {
        $calls = [];
        
        // Pattern for JSON tool calls in various formats
        $patterns = [
            // Standard format: {"tool": "...", "action": "...", "params": {...}}
            '/\{[\s]*"tool"[\s]*:[\s]*"([^"]+)"[\s]*,[\s]*"action"[\s]*:[\s]*"([^"]+)"[\s]*,[\s]*"params"[\s]*:[\s]*(\{[^}]*\}|\[[^\]]*\])[\s]*\}/s',
            // Simplified: {"tool": "...", "params": {...}}
            '/\{[\s]*"tool"[\s]*:[\s]*"([^"]+)"[\s]*,[\s]*"params"[\s]*:[\s]*(\{[^}]*\})[\s]*\}/s',
            // Function call style: {"function": "tool.action", "arguments": {...}}
            '/\{[\s]*"function"[\s]*:[\s]*"([^"]+)"[\s]*,[\s]*"arguments"[\s]*:[\s]*(\{[^}]*\})[\s]*\}/s',
            // OpenAI-style: {"function_call":{"name":"tool_name","arguments":{...}}}
            '/\{[\s]*"function_call"[\s]*:[\s]*\{[\s]*"name"[\s]*:[\s]*"([^"]+)"[\s]*,[\s]*"arguments"[\s]*:[\s]*(\{[^}]*\})[\s]*\}[\s]*\}/s'
        ];

        foreach ($patterns as $idx => $pattern) {
            if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if ($idx === 0) {
                        // Standard format
                        $calls[] = [
                            'tool' => $match[1],
                            'action' => $match[2],
                            'params' => json_decode($match[3], true) ?? [],
                            'raw' => $match[0]
                        ];
                    } elseif ($idx === 1) {
                        // Simplified format - action defaults to 'execute'
                        $calls[] = [
                            'tool' => $match[1],
                            'action' => 'execute',
                            'params' => json_decode($match[2], true) ?? [],
                            'raw' => $match[0]
                        ];
                    } elseif ($idx === 2) {
                        // Function call style - parse tool.action from function name
                        $parts = explode('.', $match[1]);
                        $calls[] = [
                            'tool' => $parts[0],
                            'action' => $parts[1] ?? 'execute',
                            'params' => json_decode($match[2], true) ?? [],
                            'raw' => $match[0]
                        ];
                    } else {
                        // OpenAI function_call style - tool name is the function name
                        $calls[] = [
                            'tool' => $match[1],
                            'action' => 'execute',
                            'params' => json_decode($match[2], true) ?? [],
                            'raw' => $match[0]
                        ];
                    }
                }
            }
        }

        return $calls;
    }

    /**
     * Extract XML-style tool calls (Anthropic/Claude style)
     * 
     * Looks for: <tool_use name="tool_name">...</tool_use>
     * 
     * @param string $response
     * @return array
     */
    private function extract_xml_tool_calls($response) {
        $calls = [];

        // Pattern for XML tool tags
        $patterns = [
            // <tool_use name="tool" action="action">params</tool_use>
            '/<tool_use[\s]+name=["\']([^"\']+)["\'][\s]*action=["\']([^"\']+)["\'][\s]*>(.*?)<\/tool_use>/s',
            // <tool name="tool">params</tool>
            '/<tool[\s]+name=["\']([^"\']+)["\'][\s]*>(.*?)<\/tool>/s',
            // <use_tool>{"tool": "...", ...}</use_tool>
            '/<use_tool>(.*?)<\/use_tool>/s'
        ];

        foreach ($patterns as $idx => $pattern) {
            if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if ($idx === 0) {
                        // Full XML format with action
                        $params = $this->parse_xml_params($match[3]);
                        $calls[] = [
                            'tool' => $match[1],
                            'action' => $match[2],
                            'params' => $params,
                            'raw' => $match[0]
                        ];
                    } elseif ($idx === 1) {
                        // Simple XML format
                        $params = $this->parse_xml_params($match[2]);
                        $tool_parts = explode('.', $match[1]);
                        $calls[] = [
                            'tool' => $tool_parts[0],
                            'action' => $tool_parts[1] ?? 'execute',
                            'params' => $params,
                            'raw' => $match[0]
                        ];
                    } else {
                        // JSON inside XML tag
                        $json = json_decode(trim($match[1]), true);
                        if ($json && isset($json['tool'])) {
                            $calls[] = [
                                'tool' => $json['tool'],
                                'action' => $json['action'] ?? 'execute',
                                'params' => $json['params'] ?? [],
                                'raw' => $match[0]
                            ];
                        }
                    }
                }
            }
        }

        return $calls;
    }

    /**
     * Extract markdown code block tool calls
     * 
     * Looks for: ```tool:tool_name\n{...}\n```
     * 
     * @param string $response
     * @return array
     */
    private function extract_markdown_tool_calls($response) {
        $calls = [];

        // Pattern for markdown code blocks with tool prefix
        $pattern = '/```(?:tool|function):?[\s]*([^\n]+)\n(.*?)```/s';
        
        if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tool_spec = trim($match[1]);
                $content = trim($match[2]);
                
                // Parse tool.action format
                $parts = explode('.', $tool_spec);
                $tool = $parts[0];
                $action = $parts[1] ?? 'execute';
                
                // Try to parse content as JSON
                $params = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // If not JSON, try to parse as key=value pairs
                    $params = $this->parse_key_value_params($content);
                }

                $calls[] = [
                    'tool' => $tool,
                    'action' => $action,
                    'params' => $params ?? [],
                    'raw' => $match[0]
                ];
            }
        }

        return $calls;
    }

    /**
     * Parse XML content for parameters
     * 
     * @param string $content
     * @return array
     */
    private function parse_xml_params($content) {
        $content = trim($content);
        
        // Try JSON first
        $json = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        // Try XML param tags: <param name="key">value</param>
        $params = [];
        if (preg_match_all('/<param[\s]+name=["\']([^"\']+)["\'][\s]*>(.*?)<\/param>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = $match[2];
            }
            return $params;
        }

        // Try key=value format
        return $this->parse_key_value_params($content);
    }

    /**
     * Parse key=value style parameters
     * 
     * @param string $content
     * @return array
     */
    private function parse_key_value_params($content) {
        $params = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)[\s]*[:=][\s]*(.*)$/', $line, $match)) {
                $key = $match[1];
                $value = trim($match[2], '"\'');
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Execute parsed tool calls
     * 
     * @param array $calls
     * @return array
     */
    private function execute_tool_calls($calls) {
        $results = [];

        foreach ($calls as $call) {
            $tool = $call['tool'];
            $action = $call['action'];
            $params = $call['params'];

            // Execute via registry
            $startTime = microtime(true);
            $result = $this->registry->execute_tool($tool, $action, $params);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            // Track analytics if available
            $success = ($result['success'] ?? false) === true;

            // Track analytics if available
            if (class_exists('\Quarksol\SmartChatbot\Analytics\AnalyticsService')) {
                $toolName = "{$tool}.{$action}";
                $errorMessage = !$success ? ($result['error'] ?? 'Unknown error') : null;
                
                \Quarksol\SmartChatbot\Analytics\AnalyticsService::trackToolCall(
                    $toolName,
                    $success,
                    $durationMs,
                    $errorMessage
                );
            }

            // [Phase 1: UI Bridge] Inject UI signal for specific tools
            // This allows the Chatbot to interrupt the text stream and send a rich UI JSON packet
            if ($success && isset($result['data'])) {
                if ($tool === 'woo_search_products') {
                    $result['__ui_signal'] = [
                        'type' => 'products',
                        'products' => $result['data']['products'] ?? [],
                        'message' => 'Here are the products I found:'
                    ];
                } elseif ($tool === 'woo_cart') {
                     $result['__ui_signal'] = [
                        'type' => 'cart',
                        'cart' => $result['data'] ?? [],
                        'message' => 'Here is your cart:'
                    ];
                } elseif ($tool === 'woo_store_info' && isset($result['data']['policies'])) {
                    // Only trigger if policies/stats are returned
                     $result['__ui_signal'] = [
                        'type' => 'text', // Frontend handles this as markdown anyway, but structured
                        'message' => "Here is the store info...",
                        // We might add specific fields here later
                    ];
                }
            }
            
            $results[] = [
                'tool' => $tool,
                'action' => $action,
                'params' => $params,
                'result' => $result,
                'raw' => $call['raw']
            ];
        }

        return $results;
    }

    /**
     * Inject tool results back into the response
     * 
     * @param string $response
     * @param array $results
     * @param string $format
     * @return string
     */
    private function inject_results($response, $results, $format) {
        $modified = $response;

        foreach ($results as $result) {
            $raw = $result['raw'];
            $tool_result = $result['result'];
            
            // Format result for display
            $formatted = $this->format_result($result['tool'], $result['action'], $tool_result);
            
            // Replace the tool call with the result
            $modified = str_replace($raw, $formatted, $modified);
        }

        return $modified;
    }

    /**
     * Format a tool result for display
     * 
     * @param string $tool
     * @param string $action
     * @param array $result
     * @return string
     */
    private function format_result($tool, $action, $result) {
        if (!$result['success']) {
            return "️ Tool error ({$tool}.{$action}): " . ($result['error'] ?? 'Unknown error');
        }

        $data = $result['data'] ?? '';
        
        // If data is an array, format it nicely
        if (is_array($data)) {
            // Check for common result patterns
            if (isset($data['message'])) {
                return $data['message'];
            }
            if (isset($data['html'])) {
                return $data['html'];
            }
            if (isset($data['text'])) {
                return $data['text'];
            }
            // Convert to JSON for complex data
            return "```json\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n```";
        }

        return (string) $data;
    }

    /**
     * Check if response contains tool calls
     * 
     * @param string $response
     * @return bool
     */
    public function has_tool_calls($response) {
        // Quick check for common patterns
        $patterns = [
            '{"tool"',
            '<tool_use',
            '<tool ',
            '<use_tool>',
            '```tool:',
            '```function:',
            '"function_call"'  // OpenAI-style function call format
        ];

        foreach ($patterns as $pattern) {
            if (stripos($response, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get tool definitions formatted for AI prompt injection
     * 
     * @return string
     */
    public function get_tool_prompt_section() {
        $definitions = $this->registry->get_tool_definitions_for_ai();
        
        if (empty($definitions)) {
            return '';
        }

        $prompt = "\n## Available Tools\n";
        $prompt .= "You can use the following tools by outputting a JSON tool call:\n\n";
        $prompt .= "Format: `{\"tool\": \"tool_name\", \"action\": \"action_name\", \"params\": {...}}`\n\n";

        foreach ($definitions as $slug => $definition) {
            $prompt .= "### {$slug}\n";
            if (is_array($definition)) {
                $prompt .= $definition['description'] ?? '';
                if (!empty($definition['actions'])) {
                    $prompt .= "\nActions:\n";
                    foreach ($definition['actions'] as $action => $desc) {
                        $prompt .= "- `{$action}`: {$desc}\n";
                    }
                }
            } else {
                $prompt .= $definition;
            }
            $prompt .= "\n";
        }

        return $prompt;
    }
}
