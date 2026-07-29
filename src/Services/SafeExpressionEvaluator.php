<?php
declare(strict_types=1);
/**
 * Safe Expression Evaluator
 * 
 * Securely evaluates mathematical and string expressions WITHOUT using eval().
 * Uses a token-based parser with strict whitelist enforcement.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Safe Expression Evaluator
 * 
 * Replaces dangerous eval() with a secure expression parser.
 */
class SafeExpressionEvaluator
{
    /**
     * Whitelisted functions that can be called
     */
    private const ALLOWED_FUNCTIONS = [
        // Math functions
        'abs', 'ceil', 'floor', 'round', 'pow', 'sqrt', 'log', 'log10',
        'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'sinh', 'cosh', 'tanh',
        'min', 'max', 'exp', 'fmod', 'intdiv', 'hypot', 'deg2rad', 'rad2deg',
        
        // String functions
        'strlen', 'substr', 'strpos', 'strtolower', 'strtoupper', 'trim',
        'ltrim', 'rtrim', 'str_replace', 'ucfirst', 'ucwords', 'lcfirst',
        'str_repeat', 'str_pad', 'strrev', 'str_contains', 'str_starts_with', 'str_ends_with',
        
        // Array functions
        'count', 'array_sum', 'array_product', 'array_keys', 'array_values',
        'array_reverse', 'array_unique', 'in_array', 'array_search', 'array_merge',
        'array_slice', 'range', 'array_key_exists', 'array_pop', 'array_shift',
        
        // Type functions
        'intval', 'floatval', 'strval', 'boolval', 'is_array', 'is_string',
        'is_int', 'is_float', 'is_bool', 'is_null', 'is_numeric', 'gettype',
        
        // JSON
        'json_encode', 'json_decode',
        
        // Date (limited)
        'time', 'date', 'strtotime',
    ];
    
    /**
     * Maximum allowed expression length
     */
    private const MAX_LENGTH = 2000;
    
    /**
     * Maximum nesting depth
     */
    private const MAX_DEPTH = 10;
    
    /**
     * Evaluate an expression safely
     * 
     * @param string $expression The expression to evaluate
     * @param array $variables Variables to make available
     * @return mixed The result of the expression
     * @throws \InvalidArgumentException If expression is invalid
     * @throws \RuntimeException If evaluation fails
     */
    public function evaluate(string $expression, array $variables = []): mixed
    {
        // Basic security checks
        $this->validateExpression($expression);
        
        // Tokenize and parse
        $tokens = $this->tokenize($expression);
        
        // Build AST and evaluate
        return $this->evaluateTokens($tokens, $variables);
    }
    
    /**
     * Validate expression for security issues
     */
    private function validateExpression(string $expression): void
    {
        // Check length
        if (strlen($expression) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Expression too long (max ' . self::MAX_LENGTH . ' chars)');
        }
        
        // Forbidden patterns - these should never appear
        $forbidden = [
            '/\$_[A-Z]+/',           // Superglobals
            '/\$GLOBALS/',           // Global access
            '/\-\>/',                // Object access
            '/\:\:/',                // Static access
            '/\$\$/',                // Variable variables
            '/`/',                   // Shell execution
            '/\beval\s*\(/i',        // Eval
            '/\bexec\s*\(/i',        // Exec
            '/\bsystem\s*\(/i',      // System
            '/\bshell_exec\s*\(/i',  // Shell exec
            '/\bpassthru\s*\(/i',    // Passthru
            '/\bpopen\s*\(/i',       // Popen
            '/\bproc_open\s*\(/i',   // Proc open
            '/\bfile_get_contents\s*\(/i',  // File read
            '/\bfile_put_contents\s*\(/i',  // File write
            '/\bfopen\s*\(/i',       // File open
            '/\bunlink\s*\(/i',      // File delete
            '/\binclude/i',          // Include
            '/\brequire/i',          // Require
            '/\bclass\s+\w/i',       // Class definition
            '/\bfunction\s+\w/i',    // Function definition
            '/\bnew\s+\w/i',         // Object instantiation
        ];
        
        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $expression)) {
                throw new \InvalidArgumentException('Expression contains forbidden pattern');
            }
        }
    }
    
    /**
     * Tokenize the expression
     * 
     * @return array Array of tokens
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $pos = 0;
        
        while ($pos < $length) {
            $char = $expression[$pos];
            
            // Skip whitespace
            if (ctype_space($char)) {
                $pos++;
                continue;
            }
            
            // Numbers (including floats)
            if (ctype_digit($char) || ($char === '.' && $pos + 1 < $length && ctype_digit($expression[$pos + 1]))) {
                $number = '';
                while ($pos < $length && (ctype_digit($expression[$pos]) || $expression[$pos] === '.')) {
                    $number .= $expression[$pos++];
                }
                $tokens[] = ['type' => 'number', 'value' => floatval($number)];
                continue;
            }
            
            // Strings
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $pos++;
                $string = '';
                while ($pos < $length && $expression[$pos] !== $quote) {
                    if ($expression[$pos] === '\\' && $pos + 1 < $length) {
                        $pos++;
                        $string .= $expression[$pos++];
                    } else {
                        $string .= $expression[$pos++];
                    }
                }
                $pos++; // Skip closing quote
                $tokens[] = ['type' => 'string', 'value' => $string];
                continue;
            }
            
            // Identifiers and functions
            if (ctype_alpha($char) || $char === '_') {
                $identifier = '';
                while ($pos < $length && (ctype_alnum($expression[$pos]) || $expression[$pos] === '_')) {
                    $identifier .= $expression[$pos++];
                }
                
                // Check for boolean/null literals
                $lower = strtolower($identifier);
                if ($lower === 'true') {
                    $tokens[] = ['type' => 'boolean', 'value' => true];
                } elseif ($lower === 'false') {
                    $tokens[] = ['type' => 'boolean', 'value' => false];
                } elseif ($lower === 'null') {
                    $tokens[] = ['type' => 'null', 'value' => null];
                } else {
                    $tokens[] = ['type' => 'identifier', 'value' => $identifier];
                }
                continue;
            }
            
            // Variables ($ prefix)
            if ($char === '$') {
                $pos++;
                $varName = '';
                while ($pos < $length && (ctype_alnum($expression[$pos]) || $expression[$pos] === '_')) {
                    $varName .= $expression[$pos++];
                }
                if ($varName === '') {
                    throw new \InvalidArgumentException('Invalid variable name');
                }
                $tokens[] = ['type' => 'variable', 'value' => $varName];
                continue;
            }
            
            // Operators
            $operators = [
                // 3-char operators
                '===' => 'operator', '!==' => 'operator', '<=>' => 'operator',
                // 2-char operators
                '==' => 'operator', '!=' => 'operator', '<=' => 'operator', '>=' => 'operator',
                '&&' => 'operator', '||' => 'operator', '**' => 'operator', '??' => 'operator',
                // 1-char operators
                '+' => 'operator', '-' => 'operator', '*' => 'operator', '/' => 'operator',
                '%' => 'operator', '^' => 'operator', '<' => 'operator', '>' => 'operator',
                '!' => 'operator', '?' => 'operator', ':' => 'operator',
            ];
            
            $found = false;
            foreach ([3, 2, 1] as $opLen) {
                if ($pos + $opLen <= $length) {
                    $op = substr($expression, $pos, $opLen);
                    if (isset($operators[$op])) {
                        $tokens[] = ['type' => 'operator', 'value' => $op];
                        $pos += $opLen;
                        $found = true;
                        break;
                    }
                }
            }
            if ($found) continue;
            
            // Brackets and separators
            $brackets = ['(' => 'lparen', ')' => 'rparen', '[' => 'lbracket', ']' => 'rbracket', ',' => 'comma'];
            if (isset($brackets[$char])) {
                $tokens[] = ['type' => $brackets[$char], 'value' => $char];
                $pos++;
                continue;
            }
            
            throw new \InvalidArgumentException("Unexpected character: $char at position $pos");
        }
        
        return $tokens;
    }
    
    /**
     * Evaluate tokenized expression
     */
    private function evaluateTokens(array $tokens, array $variables, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \RuntimeException('Maximum nesting depth exceeded');
        }
        
        if (empty($tokens)) {
            return null;
        }
        
        // Simple case: single value
        if (count($tokens) === 1) {
            return $this->resolveToken($tokens[0], $variables);
        }
        
        // Handle function calls
        if ($tokens[0]['type'] === 'identifier' && isset($tokens[1]) && $tokens[1]['type'] === 'lparen') {
            return $this->evaluateFunctionCall($tokens, $variables, $depth);
        }
        
        // Handle arrays
        if ($tokens[0]['type'] === 'lbracket') {
            return $this->evaluateArray($tokens, $variables, $depth);
        }
        
        // Handle simple binary expressions
        return $this->evaluateBinaryExpression($tokens, $variables, $depth);
    }
    
    /**
     * Resolve a single token to its value
     */
    private function resolveToken(array $token, array $variables): mixed
    {
        switch ($token['type']) {
            case 'number':
            case 'string':
            case 'boolean':
            case 'null':
                return $token['value'];
            case 'variable':
                $name = $token['value'];
                if (!array_key_exists($name, $variables)) {
                    throw new \InvalidArgumentException("Undefined variable: \$$name");
                }
                return $variables[$name];
            default:
                throw new \InvalidArgumentException("Cannot resolve token type: {$token['type']}");
        }
    }
    
    /**
     * Evaluate a function call
     */
    private function evaluateFunctionCall(array $tokens, array $variables, int $depth): mixed
    {
        $funcName = strtolower($tokens[0]['value']);
        
        // Verify function is whitelisted
        if (!in_array($funcName, self::ALLOWED_FUNCTIONS, true)) {
            throw new \InvalidArgumentException("Function not allowed: $funcName");
        }
        
        // Find matching parentheses
        $parenDepth = 0;
        $argsStart = 2;
        $argsEnd = count($tokens) - 1;
        
        for ($i = 1; $i < count($tokens); $i++) {
            if ($tokens[$i]['type'] === 'lparen') $parenDepth++;
            if ($tokens[$i]['type'] === 'rparen') $parenDepth--;
            if ($parenDepth === 0) {
                $argsEnd = $i - 1;
                break;
            }
        }
        
        // Parse arguments
        $args = [];
        $currentArg = [];
        $argDepth = 0;
        
        for ($i = $argsStart; $i <= $argsEnd; $i++) {
            $token = $tokens[$i];
            
            if ($token['type'] === 'lparen' || $token['type'] === 'lbracket') $argDepth++;
            if ($token['type'] === 'rparen' || $token['type'] === 'rbracket') $argDepth--;
            
            if ($token['type'] === 'comma' && $argDepth === 0) {
                if (!empty($currentArg)) {
                    $args[] = $this->evaluateTokens($currentArg, $variables, $depth + 1);
                    $currentArg = [];
                }
            } else {
                $currentArg[] = $token;
            }
        }
        
        if (!empty($currentArg)) {
            $args[] = $this->evaluateTokens($currentArg, $variables, $depth + 1);
        }
        
        // Call the function
        if (!function_exists($funcName)) {
            throw new \RuntimeException("Function does not exist: $funcName");
        }
        
        return $funcName(...$args);
    }
    
    /**
     * Evaluate an array literal
     */
    private function evaluateArray(array $tokens, array $variables, int $depth): array
    {
        $result = [];
        $currentItem = [];
        $bracketDepth = 0;
        
        for ($i = 1; $i < count($tokens) - 1; $i++) {
            $token = $tokens[$i];
            
            if ($token['type'] === 'lbracket') $bracketDepth++;
            if ($token['type'] === 'rbracket') $bracketDepth--;
            
            if ($token['type'] === 'comma' && $bracketDepth === 0) {
                if (!empty($currentItem)) {
                    $result[] = $this->evaluateTokens($currentItem, $variables, $depth + 1);
                    $currentItem = [];
                }
            } else {
                $currentItem[] = $token;
            }
        }
        
        if (!empty($currentItem)) {
            $result[] = $this->evaluateTokens($currentItem, $variables, $depth + 1);
        }
        
        return $result;
    }
    
    /**
     * Evaluate a binary expression
     */
    private function evaluateBinaryExpression(array $tokens, array $variables, int $depth): mixed
    {
        // Find the lowest precedence operator (evaluated last = root of expression tree)
        $operatorPrecedence = [
            '||' => 1, '??' => 1,
            '&&' => 2,
            '==' => 3, '!=' => 3, '===' => 3, '!==' => 3,
            '<' => 4, '>' => 4, '<=' => 4, '>=' => 4, '<=>' => 4,
            '+' => 5, '-' => 5,
            '*' => 6, '/' => 6, '%' => 6,
            '**' => 7, '^' => 7,
        ];
        
        $lowestPrecedence = PHP_INT_MAX;
        $operatorIndex = -1;
        $parenDepth = 0;
        
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            
            if ($token['type'] === 'rparen' || $token['type'] === 'rbracket') $parenDepth++;
            if ($token['type'] === 'lparen' || $token['type'] === 'lbracket') $parenDepth--;
            
            if ($parenDepth === 0 && $token['type'] === 'operator') {
                $op = $token['value'];
                if (isset($operatorPrecedence[$op]) && $operatorPrecedence[$op] <= $lowestPrecedence) {
                    $lowestPrecedence = $operatorPrecedence[$op];
                    $operatorIndex = $i;
                }
            }
        }
        
        if ($operatorIndex === -1) {
            // No operator found, might be a parenthesized expression
            if ($tokens[0]['type'] === 'lparen' && $tokens[count($tokens) - 1]['type'] === 'rparen') {
                return $this->evaluateTokens(array_slice($tokens, 1, -1), $variables, $depth + 1);
            }
            
            // Single value or function call
            if (count($tokens) === 1) {
                return $this->resolveToken($tokens[0], $variables);
            }
            
            // Function call
            if ($tokens[0]['type'] === 'identifier') {
                return $this->evaluateFunctionCall($tokens, $variables, $depth);
            }
            
            throw new \InvalidArgumentException('Cannot evaluate expression');
        }
        
        // Split into left and right operands
        $left = array_slice($tokens, 0, $operatorIndex);
        $right = array_slice($tokens, $operatorIndex + 1);
        $operator = $tokens[$operatorIndex]['value'];
        
        if (empty($left) || empty($right)) {
            throw new \InvalidArgumentException('Invalid binary expression');
        }
        
        $leftValue = $this->evaluateTokens($left, $variables, $depth + 1);
        $rightValue = $this->evaluateTokens($right, $variables, $depth + 1);
        
        return match ($operator) {
            '+' => $leftValue + $rightValue,
            '-' => $leftValue - $rightValue,
            '*' => $leftValue * $rightValue,
            '/' => $rightValue != 0 ? $leftValue / $rightValue : throw new \RuntimeException('Division by zero'),
            '%' => $leftValue % $rightValue,
            '**', '^' => pow($leftValue, $rightValue),
            '==' => $leftValue == $rightValue,
            '===' => $leftValue === $rightValue,
            '!=' => $leftValue != $rightValue,
            '!==' => $leftValue !== $rightValue,
            '<' => $leftValue < $rightValue,
            '>' => $leftValue > $rightValue,
            '<=' => $leftValue <= $rightValue,
            '>=' => $leftValue >= $rightValue,
            '<=>' => $leftValue <=> $rightValue,
            '&&' => $leftValue && $rightValue,
            '||' => $leftValue || $rightValue,
            '??' => $leftValue ?? $rightValue,
            default => throw new \InvalidArgumentException("Unknown operator: $operator"),
        };
    }
    
    /**
     * Check if a function is allowed
     */
    public function isFunctionAllowed(string $name): bool
    {
        return in_array(strtolower($name), self::ALLOWED_FUNCTIONS, true);
    }
    
    /**
     * Get list of allowed functions
     */
    public function getAllowedFunctions(): array
    {
        return self::ALLOWED_FUNCTIONS;
    }
}
