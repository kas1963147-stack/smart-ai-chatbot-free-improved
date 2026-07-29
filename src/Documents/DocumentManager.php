<?php
declare(strict_types=1);


/**
 * Document Manager
 * 
 * Central class for document and section CRUD operations.
 * Manages file system operations and validation.
 * 
 * @package Quarksol\SmartChatbot\Documents
 */

namespace Quarksol\SmartChatbot\Documents;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DocumentManager - CRUD operations for documents
 */
class DocumentManager
{
    /** Documents base path */
    protected static ?string $basePath = null;
    
    /**
     * Set the base path for documents
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = $path;
    }
    
    /**
     * Get the base path for documents
     */
    public static function getBasePath(): string
    {
        if (self::$basePath === null) {
            self::$basePath = dirname(__DIR__, 2) . '/documents';
        }
        return self::$basePath;
    }
    
    // =============================================
    // Section CRUD
    // =============================================
    
    /**
     * Create a new section
     * 
     * @param array $data Section data (name, description required)
     * @return DocumentSection
     * @throws DocumentValidationException
     */
    public static function createSection(array $data): DocumentSection
    {
        // Validate required fields
        if (empty($data['name'])) {
            throw new DocumentValidationException('Section name is required');
        }
        
        if (empty($data['description'])) {
            throw new DocumentValidationException('Section description is required');
        }
        
        // Generate ID
        $id = $data['id'] ?? DocumentSection::generateId($data['name']);
        
        // Check for duplicate
        if (DocumentSection::exists($id)) {
            throw new DocumentValidationException("Section already exists: {$id}");
        }
        
        // Create section folder
        $path = self::createSectionFolder($id);
        
        // Create section record
        $section = DocumentSection::fromArray([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'],
            'path' => $path,
            'max_file_size' => $data['max_file_size'] ?? 102400,
            'allowed_types' => $data['allowed_types'] ?? ['md', 'txt', 'html'],
            'order' => $data['order'] ?? 0,
        ]);
        
        if (!$section->save()) {
            throw new DocumentValidationException('Failed to save section');
        }
        
        return $section;
    }
    
    /**
     * Update an existing section
     */
    public static function updateSection(string $id, array $data): DocumentSection
    {
        $section = DocumentSection::get($id);
        
        if (!$section) {
            throw new DocumentValidationException("Section not found: {$id}");
        }
        
        // Update fields
        if (isset($data['name'])) {
            $section->name = $data['name'];
        }
        
        if (isset($data['description'])) {
            $section->description = $data['description'];
        }
        
        if (isset($data['max_file_size'])) {
            $section->maxFileSize = (int)$data['max_file_size'];
        }
        
        if (isset($data['allowed_types'])) {
            $section->allowedTypes = $data['allowed_types'];
        }
        
        if (isset($data['order'])) {
            $section->order = (int)$data['order'];
        }
        
        if (!$section->save()) {
            throw new DocumentValidationException('Failed to update section');
        }
        
        DocumentRegistry::refresh();
        return $section;
    }
    
    /**
     * Delete a section and all its documents
     */
    public static function deleteSection(string $id): bool
    {
        $section = DocumentSection::get($id);
        
        if (!$section) {
            return false;
        }
        
        // Delete folder and contents
        $path = $section->getDocumentsPath();
        if (is_dir($path)) {
            self::deleteDirectory($path);
        }
        
        // Delete section record
        $deleted = $section->delete();
        
        if ($deleted) {
            DocumentRegistry::refresh();
        }
        
        return $deleted;
    }
    
    // =============================================
    // Document CRUD
    // =============================================
    
    /**
     * Upload a document to a section
     * 
     * @param string $sectionId Section to upload to
     * @param array $file File data (from $_FILES or with 'content' and 'name')
     * @return Document
     * @throws DocumentValidationException
     */
    public static function uploadDocument(string $sectionId, array $file): Document
    {
        $section = DocumentSection::get($sectionId);
        
        if (!$section) {
            throw new DocumentValidationException("Section not found: {$sectionId}");
        }
        
        // Determine filename and content
        $filename = '';
        $content = '';
        
        if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            // Standard file upload
            $filename = sanitize_file_name($file['name']);
            $content = file_get_contents($file['tmp_name']);
        } elseif (isset($file['content']) && isset($file['name'])) {
            // Direct content upload (from API)
            $filename = sanitize_file_name($file['name']);
            $content = $file['content'];
        } else {
            throw new DocumentValidationException('Invalid file data');
        }
        
        // Validate extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $section->allowedTypes)) {
            throw new DocumentValidationException(
                "File type not allowed: .{$ext}. Allowed: " . implode(', ', $section->allowedTypes)
            );
        }
        
        // Validate size
        $size = strlen($content);
        if ($size > $section->maxFileSize) {
            $maxKb = round($section->maxFileSize / 1024);
            $sizeKb = round($size / 1024);
            throw new DocumentValidationException(
                "File too large: {$sizeKb}KB. Maximum: {$maxKb}KB"
            );
        }
        
        // Ensure folder exists
        $folderPath = $section->getDocumentsPath();
        if (!is_dir($folderPath)) {
            self::createSectionFolder($sectionId);
        }
        
        // Write file
        $filePath = $folderPath . '/' . $filename;
        
        if (file_put_contents($filePath, $content) === false) {
            throw new DocumentValidationException('Failed to save file');
        }
        
        // Create document object
        $doc = Document::fromFile($filePath, $sectionId);
        
        if (!$doc) {
            throw new DocumentValidationException('Failed to create document record');
        }
        
        DocumentRegistry::refresh();
        return $doc;
    }
    
    /**
     * Delete a document
     */
    public static function deleteDocument(string $docId): bool
    {
        $doc = DocumentRegistry::getDocument($docId);
        
        if (!$doc) {
            return false;
        }
        
        if (file_exists($doc->path)) {
            $deleted = unlink($doc->path);
            
            if ($deleted) {
                DocumentRegistry::refresh();
            }
            
            return $deleted;
        }
        
        return false;
    }
    
    // =============================================
    // Helper Methods
    // =============================================
    
    /**
     * Create section folder
     */
    protected static function createSectionFolder(string $sectionId): string
    {
        $basePath = self::getBasePath();
        $sectionPath = $basePath . '/' . $sectionId;
        
        // Create base directory if needed
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
        
        // Create section directory
        if (!is_dir($sectionPath)) {
            mkdir($sectionPath, 0755, true);
        }
        
        return $sectionPath;
    }
    
    /**
     * Delete directory recursively
     */
    protected static function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Get available file types
     */
    public static function getDefaultAllowedTypes(): array
    {
        return ['md', 'txt', 'html'];
    }
    
    /**
     * Get default max file size
     */
    public static function getDefaultMaxFileSize(): int
    {
        return 102400; // 100KB
    }
}

/**
 * Document Validation Exception
 */
class DocumentValidationException extends \Exception
{
    protected array $errors;
    
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}
