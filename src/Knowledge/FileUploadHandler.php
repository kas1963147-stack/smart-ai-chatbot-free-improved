<?php
declare(strict_types=1);
/**
 * File Upload Handler
 * 
 * Handle file uploads to knowledge base.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * File Upload Handler
 */
class FileUploadHandler {
    
    /** Max file size (10MB) */
    const MAX_FILE_SIZE = 10485760;
    
    /**
     * Handle file upload
     */
    public static function handleUpload(array $file, int $sourceId): array {
        // Validate file
        $validation = self::validate($file);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        // Get source
        $source = KnowledgeSource::find($sourceId);
        if (!$source) {
            return ['success' => false, 'error' => 'Source not found'];
        }
        
        // Determine target path
        $targetDir = $source->getFolderPath();
        if (!is_dir($targetDir)) {
            wp_mkdir_p($targetDir);
        }
        
        // Generate safe filename
        $filename = self::sanitizeFilename($file['name']);
        $targetPath = $targetDir . '/' . $filename;
        
        // Handle duplicate names
        $counter = 1;
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        while (file_exists($targetPath)) {
            $filename = $baseName . '-' . $counter . '.' . $extension;
            $targetPath = $targetDir . '/' . $filename;
            $counter++;
        }
        
        // Use WordPress built-in uploader for security checks
        require_once(\ABSPATH . 'wp-admin/includes/file.php');
        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Move from the default WP uploads directory to our custom directory
            if (!rename($movefile['file'], $targetPath)) {
                return ['success' => false, 'error' => 'Failed to save file to target directory'];
            }
        } else {
            return ['success' => false, 'error' => $movefile['error'] ?? 'Upload failed'];
        }
        
        // Register document
        $doc = new KnowledgeDocument();
        $doc->sourceId = $sourceId;
        $doc->title = $baseName;
        $doc->path = $filename;
        $doc->contentType = $extension;
        $doc->wordCount = self::countWords($targetPath);
        $doc->setMetadata('uploaded_by', get_current_user_id());
        $doc->setMetadata('uploaded_at', current_time('mysql'));
        $doc->setMetadata('original_name', $file['name']);
        $doc->save();
        
        // Update source doc count
        $source->updateDocCount();
        
        return [
            'success' => true,
            'document' => $doc->toArray(),
            'path' => $targetPath,
        ];
    }
    
    /**
     * Handle multiple file uploads
     */
    public static function handleMultipleUploads(array $files, int $sourceId): array {
        $results = [];
        
        foreach ($files['name'] as $index => $name) {
            $file = [
                'name' => $name,
                'type' => $files['type'][$index],
                'tmp_name' => $files['tmp_name'][$index],
                'error' => $files['error'][$index],
                'size' => $files['size'][$index],
            ];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $results[] = self::handleUpload($file, $sourceId);
            } else {
                $results[] = [
                    'success' => false,
                    'error' => self::getUploadError($file['error']),
                    'filename' => $name,
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Validate uploaded file
     */
    protected static function validate(array $file): array {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => self::getUploadError($file['error']),
            ];
        }
        
        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return [
                'valid' => false,
                'error' => 'File too large (max 10MB)',
            ];
        }
        
        // Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!KnowledgeConfig::isExtensionAllowed($ext)) {
            return [
                'valid' => false,
                'error' => "File type .{$ext} not allowed",
            ];
        }
        
        // SECURITY: Verify MIME type matches extension
        // This prevents attackers from renaming malicious files
        $allowedMimes = [
            'txt' => ['text/plain'],
            'md' => ['text/plain', 'text/markdown', 'text/x-markdown'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'json' => ['application/json', 'text/plain'],
            'html' => ['text/html'],
            'xml' => ['application/xml', 'text/xml'],
        ];
        
        if (function_exists('finfo_open') && isset($allowedMimes[$ext])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($detectedMime, $allowedMimes[$ext], true)) {
                return [
                    'valid' => false,
                    'error' => 'File content does not match extension',
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Sanitize filename
     */
    protected static function sanitizeFilename(string $filename): string {
        // Remove path traversal
        $filename = basename($filename);
        
        // Sanitize
        $filename = sanitize_file_name($filename);
        
        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);
        
        return $filename;
    }
    
    /**
     * Count words in file
     */
    protected static function countWords(string $path): int {
        $content = file_get_contents($path);
        
        // Strip markdown/HTML
        $content = strip_tags($content);
        $content = preg_replace('/[#*_\[\]()]+/', ' ', $content);
        
        return str_word_count($content);
    }
    
    /**
     * Get upload error message
     */
    protected static function getUploadError(int $errorCode): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Delete uploaded file
     */
    public static function deleteFile(int $docId): bool {
        $doc = KnowledgeDocument::find($docId);
        if (!$doc) {
            return false;
        }
        
        $source = KnowledgeSource::find($doc->sourceId);
        if (!$source) {
            return false;
        }
        
        $path = $source->getFolderPath() . '/' . $doc->path;
        
        // Delete file
        if (file_exists($path)) {
            unlink($path);
        }
        
        // Delete document record
        $doc->delete();
        
        // Update source count
        $source->updateDocCount();
        
        return true;
    }
}
