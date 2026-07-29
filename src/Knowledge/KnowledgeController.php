<?php
declare(strict_types=1);
/**
 * Knowledge REST Controller
 * 
 * Simplified REST API endpoints for managing Markdown Knowledge Documents.
 * Handles CRUD and auto-conversion from PDF/URL.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Controller
 */
class KnowledgeController {
    
    /** API namespace */
    const NAMESPACE = 'smart-ai-chatbot/v1';
    
    /**
     * Register REST routes
     */
    public static function register(): void {
        
        // Documents Collection
        register_rest_route(self::NAMESPACE, '/knowledge/documents', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getDocuments'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createDocument'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);
        
        // Single Document
        register_rest_route(self::NAMESPACE, '/knowledge/documents/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getDocument'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateDocument'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteDocument'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);
        
        // PDF Upload & Convert Endpoint
        register_rest_route(self::NAMESPACE, '/knowledge/convert-pdf', [
            'methods' => 'POST',
            'callback' => [self::class, 'convertPdf'],
            'permission_callback' => [self::class, 'canManage'],
        ]);
        
        // URL Scrape & Convert Endpoint
        register_rest_route(self::NAMESPACE, '/knowledge/convert-url', [
            'methods' => 'POST',
            'callback' => [self::class, 'convertUrl'],
            'permission_callback' => [self::class, 'canManage'],
        ]);
        
        // Knowledge Sources (for Agent Knowledge Assigner)
        register_rest_route(self::NAMESPACE, '/knowledge/sources', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSources'],
            'permission_callback' => [self::class, 'canManage'],
        ]);
    }
    
    /**
     * Get all knowledge sources (for agent assignment UI)
     */
    public static function getSources(WP_REST_Request $request): WP_REST_Response {
        $sources = KnowledgeSource::all();
        
        return new WP_REST_Response([
            'success' => true,
            'data' => array_map(fn($s) => $s->toArray(), $sources),
        ]);
    }
    
    /**
     * Check if user can manage knowledge
     */
    public static function canManage(): bool {
        return current_user_can('manage_options');
    }
    
    // =====================
    // DOCUMENTS CRUD
    // =====================
    
    /**
     * Get all documents (metadata only)
     */
    public static function getDocuments(WP_REST_Request $request): WP_REST_Response {
        $docs = KnowledgeDocument::all(false); // false = don't load full content string
        
        return new WP_REST_Response([
            'success' => true,
            'data' => array_map(fn($d) => $d->toArray(false), $docs),
        ]);
    }
    
    /**
     * Get single document (with full content)
     */
    public static function getDocument(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $doc = KnowledgeDocument::find($id);
        
        if (!$doc) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Document not found',
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $doc->toArray(true),
        ]);
    }
    
    /**
     * Create document
     */
    public static function createDocument(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_params();
        
        $doc = new KnowledgeDocument();
        $doc->title = sanitize_text_field($params['title'] ?? '');
        $doc->description = sanitize_textarea_field($params['description'] ?? '');
        $doc->category = sanitize_text_field($params['category'] ?? 'general');
        $doc->isActive = isset($params['is_active']) ? (bool) $params['is_active'] : true;
        $doc->content = isset($params['content']) ? wp_unslash($params['content']) : '';
        $doc->type = sanitize_text_field($params['type'] ?? 'manual');
        $doc->sourceUrl = sanitize_url($params['source_url'] ?? '');
        
        if (empty($doc->title)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Title is required'], 400);
        }
        
        // Handle PDF file upload
        $files = $request->get_file_params();
        if ($doc->type === 'pdf' && !empty($files['file'])) {
            $file = $files['file'];
            if ($file['type'] !== 'application/pdf') {
                return new WP_REST_Response(['success' => false, 'error' => 'Only PDF files are supported'], 400);
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return new WP_REST_Response(['success' => false, 'error' => 'File upload error: ' . $file['error']], 400);
            }
            try {
                $parser = new PdfToTextParser();
                $doc->content = $parser->parse($file['tmp_name']);
            } catch (\Throwable $e) {
                return new WP_REST_Response(['success' => false, 'error' => 'PDF Extraction failed: ' . $e->getMessage()], 500);
            }
        }
        
        // Check for duplicate title
        if (KnowledgeDocument::findByTitle($doc->title)) {
             return new WP_REST_Response(['success' => false, 'error' => 'A document with this title already exists'], 400);
        }
        
        if ($doc->save()) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $doc->toArray(false),
            ], 201);
        }
        
        return new WP_REST_Response(['success' => false, 'error' => 'Failed to save document'], 500);
    }
    
    /**
     * Update document
     */
    public static function updateDocument(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $doc = KnowledgeDocument::find($id);
        
        if (!$doc) {
            return new WP_REST_Response(['success' => false, 'error' => 'Document not found'], 404);
        }
        
        $params = $request->get_params();
        
        if (isset($params['title'])) {
            $newTitle = sanitize_text_field($params['title']);
            if ($newTitle !== $doc->title && KnowledgeDocument::findByTitle($newTitle)) {
                return new WP_REST_Response(['success' => false, 'error' => 'A document with this title already exists'], 400);
            }
            $doc->title = $newTitle;
        }
        
        if (isset($params['description'])) $doc->description = sanitize_textarea_field($params['description']);
        if (isset($params['category'])) $doc->category = sanitize_text_field($params['category']);
        if (isset($params['is_active'])) $doc->isActive = (bool) $params['is_active'];
        if (isset($params['content'])) $doc->content = wp_unslash($params['content']);
        if (isset($params['type'])) $doc->type = sanitize_text_field($params['type']);
        if (isset($params['source_url'])) $doc->sourceUrl = sanitize_url($params['source_url']);
        
        // Handle PDF file upload for document update
        $files = $request->get_file_params();
        if ($doc->type === 'pdf' && !empty($files['file'])) {
            $file = $files['file'];
            if ($file['type'] !== 'application/pdf') {
                return new WP_REST_Response(['success' => false, 'error' => 'Only PDF files are supported'], 400);
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return new WP_REST_Response(['success' => false, 'error' => 'File upload error: ' . $file['error']], 400);
            }
            try {
                $parser = new PdfToTextParser();
                $doc->content = $parser->parse($file['tmp_name']);
            } catch (\Throwable $e) {
                return new WP_REST_Response(['success' => false, 'error' => 'PDF Extraction failed: ' . $e->getMessage()], 500);
            }
        }
        
        if ($doc->save()) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $doc->toArray(false),
            ]);
        }
        
        return new WP_REST_Response(['success' => false, 'error' => 'Failed to update document'], 500);
    }
    
    /**
     * Delete document
     */
    public static function deleteDocument(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $doc = KnowledgeDocument::find($id);
        
        if (!$doc) {
            return new WP_REST_Response(['success' => false, 'error' => 'Document not found'], 404);
        }
        
        if ($doc->delete()) {
            return new WP_REST_Response(['success' => true, 'message' => 'Document deleted']);
        }
        
        return new WP_REST_Response(['success' => false, 'error' => 'Failed to delete document'], 500);
    }
    
    // =====================
    // FETCHERS & CONVERTERS
    // =====================
    
    /**
     * Handle PDF Upload and text extraction
     */
    public static function convertPdf(WP_REST_Request $request): WP_REST_Response {
        $files = $request->get_file_params();
        
        if (empty($files['file'])) {
            return new WP_REST_Response(['success' => false, 'error' => 'No file uploaded'], 400);
        }
        
        $file = $files['file'];
        
        if ($file['type'] !== 'application/pdf') {
            return new WP_REST_Response(['success' => false, 'error' => 'Only PDF files are supported'], 400);
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_REST_Response(['success' => false, 'error' => 'File upload error: ' . $file['error']], 400);
        }
        
        try {
            $parser = new PdfToTextParser();
            $markdown = $parser->parse($file['tmp_name']);
            
            // Clean up title from filename
            $title = sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME));
            $title = ucwords(str_replace(['-', '_'], ' ', $title));
            
            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'title' => $title,
                    'content' => $markdown,
                    'original_filename' => $file['name']
                ]
            ]);
            
        } catch (\Throwable $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'PDF Extraction failed: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Handle URL scraping and conversion
     */
    public static function convertUrl(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $url = $params['url'] ?? '';
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Valid URL is required'], 400);
        }
        
        try {
            $parser = new UrlToMarkdownParser();
            $result = $parser->parse($url);
            
            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'title' => $result['title'],
                    'content' => $result['content'],
                    'source_url' => $url
                ]
            ]);
            
        } catch (\Throwable $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'URL Scraping failed: ' . $e->getMessage()], 500);
        }
    }
}
