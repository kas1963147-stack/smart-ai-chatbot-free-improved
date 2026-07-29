<?php
/**
 * PHP Code Obfuscation Tool for Plugin Distribution
 * 
 * Level 1: Strip comments, minimize whitespace (all PHP files)
 * Level 2: Level 1 + variable renaming + string encoding (pro feature files)
 * 
 * Usage: php obfuscate-php.php <source_dir> <output_dir> [options]
 *   --critical=file1,file2       Files for integrity checksums
 *   --heavy=subdir1,subdir2      Subdirectories for Level 2 obfuscation
 *   --heavy-files=f1.php,f2.php  Specific files for Level 2 obfuscation
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$sourceDir  = $argv[1] ?? null;
$outputDir  = $argv[2] ?? null;
$criticalArg = '';
$heavyDirsArg = '';
$heavyFilesArg = '';

foreach ($argv as $arg) {
    if (strpos($arg, '--critical=') === 0) {
        $criticalArg = substr($arg, strlen('--critical='));
    }
    if (strpos($arg, '--heavy=') === 0) {
        $heavyDirsArg = substr($arg, strlen('--heavy='));
    }
    if (strpos($arg, '--heavy-files=') === 0) {
        $heavyFilesArg = substr($arg, strlen('--heavy-files='));
    }
}

if (!$sourceDir || !$outputDir) {
    echo "Usage: php obfuscate-php.php <source_dir> <output_dir> [options]\n";
    echo "  --critical=file1,file2       Files for integrity checksums\n";
    echo "  --heavy=subdir1,subdir2      Subdirs for Level 2 (variable rename + string encode)\n";
    echo "  --heavy-files=f1.php,f2.php  Specific files for Level 2\n";
    exit(1);
}

$sourceDir = realpath($sourceDir);
if (!$sourceDir) {
    echo "Error: Source directory not found.\n";
    exit(1);
}

$criticalFiles = $criticalArg ? explode(',', $criticalArg) : [
    'LicenseManager.php',
    'ChatbotConfig.php',
    'EncryptedSettings.php',
    'class-chatbot.php',
];

$heavyDirs = $heavyDirsArg ? explode(',', $heavyDirsArg) : [];
$heavyFiles = $heavyFilesArg ? explode(',', $heavyFilesArg) : [];

$stats = [
    'processed' => 0, 'skipped' => 0, 'critical' => 0, 'heavy' => 0,
    'total_size_before' => 0, 'total_size_after' => 0,
    'vars_renamed' => 0, 'strings_encoded' => 0,
];

// ============================================================
// Protected variable names — NEVER rename these
// ============================================================
$PROTECTED_VARS = [
    '$this', '$wpdb', '$wp', '$wp_query', '$wp_rewrite', '$wp_filesystem',
    '$_POST', '$_GET', '$_SERVER', '$_SESSION', '$_COOKIE', '$_FILES',
    '$_REQUEST', '$_ENV', '$GLOBALS', '$argc', '$argv',
    '$php_errormsg', '$http_response_header',
];

// ============================================================
// LEVEL 1: Strip comments + minimize whitespace
// ============================================================
function stripComments(string $code): string {
    $tokens = token_get_all($code);
    $output = '';
    $isFirstDocComment = true;

    foreach ($tokens as $token) {
        if (is_array($token)) {
            [$type, $content] = $token;

            switch ($type) {
                case T_COMMENT:
                case T_DOC_COMMENT:
                    // Preserve WordPress plugin header
                    if ($type === T_DOC_COMMENT && $isFirstDocComment &&
                        (strpos($content, 'Plugin Name:') !== false ||
                         strpos($content, 'Plugin URI:') !== false)) {
                        $output .= $content;
                        $isFirstDocComment = false;
                        break;
                    }
                    $isFirstDocComment = false;
                    $newlines = substr_count($content, "\n");
                    $output .= str_repeat("\n", $newlines);
                    break;

                case T_WHITESPACE:
                    $newlines = substr_count($content, "\n");
                    $output .= $newlines > 1 ? "\n" : ($newlines > 0 ? "\n" : ' ');
                    break;

                default:
                    $output .= $content;
                    break;
            }
        } else {
            $output .= $token;
        }
    }

    return preg_replace('/\n{3,}/', "\n\n", $output);
}

// ============================================================
// LEVEL 2A: Rename local variables
// ============================================================
function renameVariables(string $code, array &$stats): string {
    global $PROTECTED_VARS;

    // Safety: skip files using dynamic variable features
    if (preg_match('/\$\$\w/', $code) || strpos($code, 'compact(') !== false || strpos($code, 'extract(') !== false) {
        return $code;
    }

    $tokens = token_get_all($code);

    // Pass 1: Collect class property names to protect them
    $propertyNames = [];
    $prevMeaningful = null;
    foreach ($tokens as $i => $token) {
        if (!is_array($token)) { $prevMeaningful = $token; continue; }
        if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) continue;

        if ($token[0] === T_VARIABLE && $prevMeaningful !== null &&
            (is_array($prevMeaningful) && in_array($prevMeaningful[0], [
                T_PUBLIC, T_PRIVATE, T_PROTECTED, T_VAR, T_STATIC, T_READONLY,
            ]))) {
            $propertyNames[] = $token[1];
        }
        $prevMeaningful = $token;
    }

    // Also protect promoted constructor properties
    // Scan for patterns like: public string $foo in function params
    $inConstructorParams = false;
    $parenDepth = 0;
    foreach ($tokens as $i => $token) {
        if (!is_array($token)) {
            if ($token === '(' && $inConstructorParams) $parenDepth++;
            if ($token === '(') { if ($inConstructorParams) $parenDepth++; }
            if ($token === ')') { if ($inConstructorParams) { $parenDepth--; if ($parenDepth <= 0) $inConstructorParams = false; } }
            continue;
        }
        if ($token[0] === T_STRING && strtolower($token[1]) === '__construct') {
            $inConstructorParams = true;
            $parenDepth = 0;
        }
        if ($inConstructorParams && $token[0] === T_VARIABLE &&
            isset($tokens[$i - 2]) && is_array($tokens[$i - 2]) &&
            in_array($tokens[$i - 2][0], [T_PUBLIC, T_PRIVATE, T_PROTECTED, T_READONLY])) {
            $propertyNames[] = $token[1];
        }
    }

    // Also protect inherited/parent properties accessed via $this->prop or self::$prop
    // These may come from parent classes (different files) and MUST keep their original names
    foreach ($tokens as $i => $token) {
        if (!is_array($token)) continue;
        
        // Match $this->propertyName — the T_STRING after -> is the property name
        if ($token[0] === T_OBJECT_OPERATOR && isset($tokens[$i + 1]) && is_array($tokens[$i + 1])) {
            $next = $tokens[$i + 1];
            if ($next[0] === T_STRING) {
                // Add as "$propertyName" to match T_VARIABLE format
                $propertyNames[] = '$' . $next[1];
            }
        }
        
        // Match self::$staticProp or static::$staticProp
        if ($token[0] === T_DOUBLE_COLON && isset($tokens[$i + 1]) && is_array($tokens[$i + 1])) {
            if ($tokens[$i + 1][0] === T_VARIABLE) {
                $propertyNames[] = $tokens[$i + 1][1];
            }
        }
    }

    $propertyNames = array_unique($propertyNames);

    // Also protect ALL function/method parameter names
    // PHP 8 named arguments require parameter names to stay unchanged
    // e.g., NeuronAI calls tools via $this->__invoke(...$parameters) where keys = param names
    $funcParamVars = [];
    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) continue;
        
        // Look for 'function' keyword
        if ($token[0] !== T_FUNCTION && $token[0] !== T_FN) continue;
        
        // Find the opening parenthesis after 'function [name]'
        $j = $i + 1;
        while ($j < count($tokens)) {
            $t = $tokens[$j];
            if (!is_array($t) && $t === '(') break;
            if (!is_array($t) && $t === '{') break; // safety
            $j++;
        }
        if ($j >= count($tokens) || $tokens[$j] !== '(') continue;
        
        // Now scan inside the parentheses for all T_VARIABLE tokens
        $depth = 1;
        $j++;
        while ($j < count($tokens) && $depth > 0) {
            $t = $tokens[$j];
            if (!is_array($t)) {
                if ($t === '(') $depth++;
                if ($t === ')') $depth--;
            } elseif ($t[0] === T_VARIABLE && $depth > 0) {
                $funcParamVars[] = $t[1];
            }
            $j++;
        }
    }
    $propertyNames = array_unique(array_merge($propertyNames, $funcParamVars));

    // Pass 2: Build rename map
    $varMap = [];
    $counter = 0;
    foreach ($tokens as $token) {
        if (!is_array($token) || $token[0] !== T_VARIABLE) continue;
        $name = $token[1];
        if (in_array($name, $PROTECTED_VARS)) continue;
        if (in_array($name, $propertyNames)) continue;
        if (isset($varMap[$name])) continue;

        $varMap[$name] = '$_0x' . str_pad(dechex(0xa0 + $counter), 2, '0', STR_PAD_LEFT);
        $counter++;
    }

    $stats['vars_renamed'] += count($varMap);

    // Pass 3: Rebuild with renamed variables
    $output = '';
    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_VARIABLE && isset($varMap[$token[1]])) {
            $output .= $varMap[$token[1]];
        } elseif (is_array($token) && $token[0] === T_STRING_VARNAME) {
            // Handle ${varName} inside double-quoted strings
            $lookup = '$' . $token[1];
            if (isset($varMap[$lookup])) {
                $output .= substr($varMap[$lookup], 1); // Remove leading $
            } else {
                $output .= $token[1];
            }
        } else {
            $output .= is_array($token) ? $token[1] : $token;
        }
    }

    return $output;
}

// ============================================================
// LEVEL 2B: Encode single-quoted string literals with base64
// ============================================================
function encodeStringLiterals(string $code, array &$stats): string {
    $tokens = token_get_all($code);
    $output = '';

    // Context tracking to avoid encoding strings in const-expression contexts
    $inConstDecl = false;        // after T_CONST until ;
    $inFuncParams = false;       // function parameter defaults
    $funcParamParenDepth = 0;
    $afterFuncKeyword = false;
    $inPropertyDefault = false;
    $lastWasVisibility = false;
    $lastWasPropertyVar = false;
    $braceDepth = 0;
    $classBodyDepths = [];       // stack of brace depths for class bodies
    $inMatchArms = false;

    foreach ($tokens as $i => $token) {
        // --- Handle non-array tokens (operators, braces) ---
        if (!is_array($token)) {
            $char = $token;

            if ($char === '{') {
                $braceDepth++;
                $afterFuncKeyword = false;
                $inPropertyDefault = false;
                $lastWasPropertyVar = false;
            }
            if ($char === '}') {
                $braceDepth--;
                if (!empty($classBodyDepths) && $braceDepth === end($classBodyDepths)) {
                    array_pop($classBodyDepths);
                }
            }
            if ($char === '(') {
                if ($afterFuncKeyword) {
                    $inFuncParams = true;
                    $funcParamParenDepth = 1;
                    $afterFuncKeyword = false;
                } elseif ($inFuncParams) {
                    $funcParamParenDepth++;
                }
            }
            if ($char === ')' && $inFuncParams) {
                $funcParamParenDepth--;
                if ($funcParamParenDepth <= 0) $inFuncParams = false;
            }
            if ($char === ';') {
                $inConstDecl = false;
                $inPropertyDefault = false;
                $lastWasVisibility = false;
                $lastWasPropertyVar = false;
            }
            if ($char === '=' && $lastWasPropertyVar) {
                $inPropertyDefault = true;
            }

            $output .= $char;
            continue;
        }

        // --- Handle array tokens ---
        [$type, $content] = $token;

        // Track contexts
        if ($type === T_CONST) $inConstDecl = true;
        if ($type === T_FUNCTION || $type === T_FN) $afterFuncKeyword = true;
        if ($type === T_MATCH) $inMatchArms = true;
        if (in_array($type, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
            $classBodyDepths[] = $braceDepth;
        }
        if (in_array($type, [T_PUBLIC, T_PRIVATE, T_PROTECTED, T_VAR, T_READONLY, T_STATIC])) {
            if (!empty($classBodyDepths) && $braceDepth === end($classBodyDepths) + 1) {
                $lastWasVisibility = true;
            }
        }
        if ($type === T_VARIABLE && $lastWasVisibility) {
            $lastWasPropertyVar = true;
        }

        // Determine if encoding is safe
        $unsafeContext = $inConstDecl || $inFuncParams || $inPropertyDefault;

        // Encode single-quoted strings in safe contexts
        if ($type === T_CONSTANT_ENCAPSED_STRING && !$unsafeContext) {
            $raw = $content;
            $quote = $raw[0];

            // Only encode single-quoted strings (double-quoted have complex escapes)
            if ($quote === "'") {
                $inner = substr($raw, 1, -1);

                // Skip conditions
                $skip = false;
                if (strlen($inner) < 2) $skip = true;                         // Too short
                if (strpos($inner, '\\') !== false &&
                    preg_match('/\\\\[A-Z]/', $inner)) $skip = true;           // Namespace ref like \\App
                if (preg_match('/^[\d.]+$/', $inner)) $skip = true;            // Numeric string
                if ($inner === '') $skip = true;                                // Empty

                if (!$skip) {
                    // Decode single-quoted string escape sequences
                    $decoded = decodeSingleQuoted($inner);
                    $encoded = base64_encode($decoded);
                    $output .= "base64_decode('" . $encoded . "')";
                    $stats['strings_encoded']++;
                    continue;
                }
            }
        }

        $output .= $content;
    }

    return $output;
}

/**
 * Decode PHP single-quoted string escape sequences.
 * Only \\ and \' are valid escapes in single-quoted strings.
 */
function decodeSingleQuoted(string $inner): string {
    $result = '';
    $len = strlen($inner);
    for ($i = 0; $i < $len; $i++) {
        if ($inner[$i] === '\\' && $i + 1 < $len) {
            $next = $inner[$i + 1];
            if ($next === '\\') {
                $result .= '\\';
                $i++;
            } elseif ($next === "'") {
                $result .= "'";
                $i++;
            } else {
                $result .= $inner[$i]; // Literal backslash
            }
        } else {
            $result .= $inner[$i];
        }
    }
    return $result;
}

// ============================================================
// LEVEL 2: Full heavy obfuscation pipeline
// ============================================================
function heavyObfuscatePhpFile(string $source, array &$stats): string {
    $code = file_get_contents($source);
    if ($code === false) return '';

    // Step 1: Strip comments
    $code = stripComments($code);

    // Step 2: Rename local variables
    $code = renameVariables($code, $stats);

    // Step 3: Encode string literals
    $code = encodeStringLiterals($code, $stats);

    // Final cleanup
    $code = preg_replace('/\n{3,}/', "\n\n", $code);

    return $code;
}

// ============================================================
// LEVEL 1: Light obfuscation (comment strip only)
// ============================================================
function obfuscatePhpFile(string $source, bool $isCritical = false): string {
    $code = file_get_contents($source);
    if ($code === false) return '';

    $code = stripComments($code);
    $code = preg_replace('/\n{3,}/', "\n\n", $code);

    if ($isCritical) {
        $code = addIntegrityCheck($code, basename($source));
    }

    return $code;
}

// ============================================================
// Integrity check for critical files
// ============================================================
function addIntegrityCheck(string $code, string $filename): string {
    $checksum = hash('sha256', $code);
    $timestamp = time();

    // Find safe insertion point AFTER: <?php, declare(), namespace, and use statements
    // PHP requires namespace to be the first statement (after declare), so we must insert after it
    $pattern = '/^(<\?php\s*'                          // <?php
        . '(?:declare\s*\([^)]+\)\s*;\s*)?'            // optional declare(strict_types=1);
        . '(?:namespace\s+[^;]+;\s*)?'                 // optional namespace App\...; 
        . '(?:(?:use\s+[^;]+;\s*)*)'                   // optional use statements
        . ')/s';
    
    $insertPos = 0;
    if (preg_match($pattern, $code, $m)) {
        $insertPos = strlen($m[1]);
    }

    $integrityBlock = "\n"
        . "if(!defined('ABSPATH')){exit;}\n"
        . "\$_swc_h='" . $checksum . "';\n"
        . "\$_swc_t=" . $timestamp . ";\n";

    return substr($code, 0, $insertPos) . $integrityBlock . substr($code, $insertPos);
}

// ============================================================
// Check if a file should get heavy obfuscation
// ============================================================
function shouldHeavyObfuscate(string $filePath, string $srcDir, array $heavyDirs, array $heavyFiles): bool {
    $basename = basename($filePath);

    if (in_array($basename, $heavyFiles)) {
        return true;
    }

    $relativePath = substr($filePath, strlen($srcDir) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    foreach ($heavyDirs as $dir) {
        $dir = trim($dir);
        if ($dir === '' || $dir === 'none') continue;
        if ($dir === '.') return true; // All files in this source dir
        if (strpos($relativePath, $dir . '/') === 0) {
            return true;
        }
    }

    return false;
}

// ============================================================
// Process directory recursively
// ============================================================
function processDirectory(string $srcDir, string $outDir, array $criticalFiles, array $heavyDirs, array $heavyFiles, array &$stats): void {
    if (!is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($srcDir) + 1);
        $destPath = $outDir . DIRECTORY_SEPARATOR . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($destPath)) mkdir($destPath, 0755, true);
            continue;
        }

        $ext = strtolower($item->getExtension());

        if ($ext === 'php') {
            $isCritical = in_array($item->getBasename(), $criticalFiles);
            $isHeavy = shouldHeavyObfuscate($item->getPathname(), $srcDir, $heavyDirs, $heavyFiles);
            $originalSize = $item->getSize();

            // Choose obfuscation level
            if ($isHeavy) {
                $obfuscated = heavyObfuscatePhpFile($item->getPathname(), $stats);
                if ($isCritical) {
                    $obfuscated = addIntegrityCheck($obfuscated, $item->getBasename());
                }
                $stats['heavy']++;
                $label = 'HEAVY';
            } else {
                $obfuscated = obfuscatePhpFile($item->getPathname(), $isCritical);
                $label = $isCritical ? 'CRITICAL' : 'LIGHT';
            }

            $destDir = dirname($destPath);
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);

            file_put_contents($destPath, $obfuscated);
            $newSize = strlen($obfuscated);

            $stats['processed']++;
            $stats['total_size_before'] += $originalSize;
            $stats['total_size_after'] += $newSize;

            if ($isCritical) $stats['critical']++;

            $reduction = $originalSize > 0 ? round((1 - $newSize / $originalSize) * 100, 1) : 0;

            if ($isHeavy || $isCritical) {
                echo "  [{$label}] {$relativePath} ({$reduction}% reduced)\n";
            }
        } else {
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            copy($item->getPathname(), $destPath);
            $stats['skipped']++;
        }
    }
}

// ============================================================
// Main Execution
// ============================================================
echo "=== PHP Obfuscation Tool (Level 1 + Level 2) ===\n";
echo "Source: {$sourceDir}\n";
echo "Output: {$outputDir}\n";
echo "Critical files: " . (empty($criticalFiles) ? 'none' : implode(', ', $criticalFiles)) . "\n";
echo "Heavy dirs: " . (empty($heavyDirs) ? 'none' : implode(', ', $heavyDirs)) . "\n";
echo "Heavy files: " . (empty($heavyFiles) ? 'none' : implode(', ', $heavyFiles)) . "\n\n";

processDirectory($sourceDir, $outputDir, $criticalFiles, $heavyDirs, $heavyFiles, $stats);

$reduction = $stats['total_size_before'] > 0
    ? round((1 - $stats['total_size_after'] / $stats['total_size_before']) * 100, 1)
    : 0;

echo "\n=== Results ===\n";
echo "PHP files processed: {$stats['processed']}\n";
echo "  Level 1 (light):  " . ($stats['processed'] - $stats['heavy']) . " files\n";
echo "  Level 2 (heavy):  {$stats['heavy']} files\n";
echo "  Critical:         {$stats['critical']} files\n";
echo "Variables renamed:  {$stats['vars_renamed']}\n";
echo "Strings encoded:    {$stats['strings_encoded']}\n";
echo "Non-PHP files:      {$stats['skipped']}\n";
echo "Size reduction:     {$reduction}% ({$stats['total_size_before']} -> {$stats['total_size_after']} bytes)\n";
