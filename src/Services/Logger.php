<?php
declare(strict_types=1);
/**
 * Logger Service
 * 
 * Centralized logging for the multi-agent chat system.
 * Supports file-based logging with log levels and rotation.
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger
 */
class Logger {
    
    /** Log level constants */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    
    /** Log level priorities (lower = more verbose) */
    const LEVEL_PRIORITY = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
    ];
    
    /** Default log filename */
    const LOG_FILE = 'smart-ai-chatbot.log';
    
    /** Max log file size in bytes (5MB) */
    const MAX_FILE_SIZE = 5242880;
    
    /** Number of rotated logs to keep */
    const MAX_ROTATIONS = 3;
    
    /** Cached minimum log level */
    protected static ?string $minLevel = null;

    /** Cached logging enabled flag */
    protected static ?bool $loggingEnabled = null;
    
    public static function getLogDir(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/smart-ai-chatbot/logs/';
    }
    
    /**
     * Get log file path
     */
    public static function getLogPath(): string {
        return self::getLogDir() . self::LOG_FILE;
    }
    
    /**
     * Get minimum log level from settings
     */
    protected static function getMinLevel(): string {
        if (self::$minLevel === null) {
            self::$minLevel = get_option('swc_chatbot_log_level', self::LEVEL_INFO);
        }
        return self::$minLevel;
    }

    /**
     * Check if logging is enabled in settings.
     */
    protected static function isLoggingEnabled(): bool {
        if (self::$loggingEnabled === null) {
            $enabled = false;
            if (class_exists('\Quarksol\SmartChatbot\Config\SettingsManager')) {
                $enabled = (bool) \Quarksol\SmartChatbot\Config\SettingsManager::get('enable_logging', false);
            } else {
                $settings = get_option('swc_chatbot_settings', []);
                $enabled = !empty($settings['enable_logging']);
            }
            self::$loggingEnabled = $enabled;
        }

        return self::$loggingEnabled;
    }
    
    /**
     * Check if level should be logged
     */
    protected static function shouldLog(string $level): bool {
        if (!self::isLoggingEnabled() && $level !== self::LEVEL_ERROR) {
            return false;
        }

        $minPriority = self::LEVEL_PRIORITY[self::getMinLevel()] ?? 1;
        $levelPriority = self::LEVEL_PRIORITY[$level] ?? 1;
        
        return $levelPriority >= $minPriority;
    }
    
    /**
     * Log a debug message
     */
    public static function debug(string $message, array $context = []): void {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log an info message
     */
    public static function info(string $message, array $context = []): void {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public static function warning(string $message, array $context = []): void {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an error message
     */
    public static function error(string $message, array $context = []): void {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Main log method
     */
    public static function log(string $level, string $message, array $context = []): void {
        if (!self::shouldLog($level)) {
            return;
        }
        
        // Ensure log directory exists
        $logDir = self::getLogDir();
        if (!is_dir($logDir)) {
            wp_mkdir_p($logDir);
            
            // Add .htaccess to prevent direct access
            file_put_contents($logDir . '.htaccess', 'deny from all');
        }
        
        // Check for log rotation
        self::rotateIfNeeded();
        
        // Build log entry
        $timestamp = current_time('c');
        $contextStr = !empty($context) ? ' ' . wp_json_encode($context) : '';
        $entry = sprintf("[%s] [%s] %s%s\n", $timestamp, $level, $message, $contextStr);
        
        // Write to file
        file_put_contents(self::getLogPath(), $entry, FILE_APPEND | LOCK_EX);
        
        // Also log to database for ERROR level
        if ($level === self::LEVEL_ERROR) {
            self::logToDatabase($level, $message, $context);
        }
    }
    
    /**
     * Log to database (for errors)
     */
    protected static function logToDatabase(string $level, string $message, array $context): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'swc_error_logs';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        
        $wpdb->insert(
            $table,
            [
                'level' => $level,
                'message' => $message,
                'context' => wp_json_encode($context),
            ],
            ['%s', '%s', '%s']
        );
    }
    
    /**
     * Rotate log file if too large
     */
    protected static function rotateIfNeeded(): void {
        $logPath = self::getLogPath();
        
        if (!file_exists($logPath)) {
            return;
        }
        
        if (filesize($logPath) < self::MAX_FILE_SIZE) {
            return;
        }
        
        // Rotate logs
        for ($i = self::MAX_ROTATIONS; $i >= 1; $i--) {
            $old = $logPath . '.' . $i;
            $new = $logPath . '.' . ($i + 1);
            
            if (file_exists($old)) {
                if ($i === self::MAX_ROTATIONS) {
                    unlink($old);
                } else {
                    rename($old, $new);
                }
            }
        }
        
        rename($logPath, $logPath . '.1');
    }
    
    /**
     * Get recent log entries
     */
    public static function getRecentLogs(int $limit = 100): array {
        $logPath = self::getLogPath();
        
        if (!file_exists($logPath)) {
            return [];
        }
        
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$lines) {
            return [];
        }
        
        // Get last N lines
        $lines = array_slice($lines, -$limit);
        
        // Parse log entries
        $entries = [];
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/^\[(.+?)\] \[(.+?)\] (.+)$/', $line, $matches)) {
                $entries[] = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3],
                ];
            }
        }
        
        return $entries;
    }
    
    /**
     * Get error logs from database
     */
    public static function getErrorLogs(int $limit = 50): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'swc_error_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        return $rows ?: [];
    }
    
    /**
     * Clear old log entries from database
     */
    public static function cleanupOldLogs(int $daysToKeep = 30): int {
        global $wpdb;
        
        $table = $wpdb->prefix . 'swc_error_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $daysToKeep
            )
        );
        
        return $deleted ?: 0;
    }
    
    /**
     * Clear all logs
     */
    public static function clearLogs(): void {
        // Clear file log
        $logPath = self::getLogPath();
        if (file_exists($logPath)) {
            unlink($logPath);
        }
        
        // Clear rotated logs
        for ($i = 1; $i <= self::MAX_ROTATIONS; $i++) {
            $rotated = $logPath . '.' . $i;
            if (file_exists($rotated)) {
                unlink($rotated);
            }
        }
        
        // Clear database logs
        global $wpdb;
        $table = $wpdb->prefix . 'swc_error_logs';
        
        // Only truncate if table exists and name is valid
        $tableName = esc_sql($table);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return;
        }
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
            $wpdb->query("TRUNCATE TABLE `{$tableName}`");
        }
    }
}
