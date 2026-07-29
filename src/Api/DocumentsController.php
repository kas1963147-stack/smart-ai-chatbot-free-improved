<?php
declare(strict_types=1);


/**
 * Documents API Controller
 * 
 * WordPress REST API endpoints for document and section management.
 * Mirrors SkillsController.php for consistency.
 * 
 * @package Quarksol\SmartChatbot\Api
 */

namespace Quarksol\SmartChatbot\Api;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Documents\DocumentRegistry;
use Quarksol\SmartChatbot\Documents\DocumentManager;
use Quarksol\SmartChatbot\Documents\DocumentSection;
use Quarksol\SmartChatbot\Documents\DocumentValidationException;

/**
 * DocumentsController - REST API for documents
 */
class DocumentsController
{
    const NAMESPACE = 'smart-ai-chatbot/v1';
    
    /**
     * Register routes
     */
    public static function register(): void
    {
        // ============================================
        // Document Section Endpoints
        // ============================================
        
        // List all sections with documents
        register_rest_route(self::NAMESPACE, '/documents', [
            'methods' => 'GET',
            'callback' => [self::class, 'list'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // List sections only
        register_rest_route(self::NAMESPACE, '/documents/sections', [
            'methods' => 'GET',
            'callback' => [self::class, 'listSections'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // Create section
        register_rest_route(self::NAMESPACE, '/documents/sections', [
            'methods' => 'POST',
            'callback' => [self::class, 'createSection'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // Get section
        register_rest_route(self::NAMESPACE, '/documents/sections/(?P<id>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSection'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // Update section
        register_rest_route(self::NAMESPACE, '/documents/sections/(?P<id>[a-z0-9-]+)', [
            'methods' => 'PUT',
            'callback' => [self::class, 'updateSection'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // Delete section
        register_rest_route(self::NAMESPACE, '/documents/sections/(?P<id>[a-z0-9-]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'deleteSection'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // Upload document to section
        register_rest_route(self::NAMESPACE, '/documents/sections/(?P<id>[a-z0-9-]+)/upload', [
            'methods' => 'POST',
            'callback' => [self::class, 'uploadDocument'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // ============================================
        // Document Endpoints
        // ============================================
        
        // Get document metadata
        register_rest_route(self::NAMESPACE, '/documents/(?P<id>[a-z0-9-/]+)/info', [
            'methods' => 'GET',
            'callback' => [self::class, 'getDocument'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // Get document content
        register_rest_route(self::NAMESPACE, '/documents/(?P<id>[a-z0-9-/]+)/content', [
            'methods' => 'GET',
            'callback' => [self::class, 'getDocumentContent'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
        
        // Delete document
        register_rest_route(self::NAMESPACE, '/documents/(?P<id>[a-z0-9-/]+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'deleteDocument'],
            'permission_callback' => [self::class, 'canManageDocuments'],
        ]);
    }
    
    /**
     * Permission check
     */
    public static function canManageDocuments(): bool
    {
        return current_user_can('manage_options');
    }
    
    // ============================================
    // Section Handlers
    // ============================================
    
    /**
     * List all sections with documents
     */
    public static function list(\WP_REST_Request $request): \WP_REST_Response
    {
        $sections = DocumentSection::getAll();
        $sectionData = [];
        
        foreach ($sections as $section) {
            $documents = DocumentRegistry::getDocuments($section->id);
            
            $sectionData[] = [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
                'document_count' => count($documents),
                'max_file_size' => $section->maxFileSize,
                'allowed_types' => $section->allowedTypes,
                'documents' => array_map(fn($doc) => $doc->toArray(), array_values($documents)),
            ];
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'sections' => $sectionData,
                'total_sections' => count($sections),
                'total_documents' => count(DocumentRegistry::discover()),
            ],
        ], 200);
    }
    
    /**
     * List sections only (no documents)
     */
    public static function listSections(\WP_REST_Request $request): \WP_REST_Response
    {
        $sections = DocumentSection::getAll();
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => array_map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'document_count' => $s->getDocumentCount(),
                'max_file_size' => $s->maxFileSize,
                'allowed_types' => $s->allowedTypes,
                'order' => $s->order,
            ], $sections),
        ], 200);
    }
    
    /**
     * Get single section
     */
    public static function getSection(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $section = DocumentSection::get($id);
        
        if (!$section) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => "Section not found: {$id}"],
            ], 404);
        }
        
        $documents = DocumentRegistry::getDocuments($id);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                ...$section->toArray(),
                'documents' => array_map(fn($doc) => $doc->toArray(), array_values($documents)),
            ],
        ], 200);
    }
    
    /**
     * Create section
     */
    public static function createSection(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        
        try {
            $section = DocumentManager::createSection($data);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $section->id,
                    'message' => 'Section created successfully',
                ],
            ], 201);
            
        } catch (DocumentValidationException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
    
    /**
     * Update section
     */
    public static function updateSection(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        
        try {
            $section = DocumentManager::updateSection($id, $data);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $section->id,
                    'message' => 'Section updated successfully',
                ],
            ], 200);
            
        } catch (DocumentValidationException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
    
    /**
     * Delete section
     */
    public static function deleteSection(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        
        $deleted = DocumentManager::deleteSection($id);
        
        if ($deleted) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => ['message' => 'Section deleted successfully'],
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'DELETE_FAILED', 'message' => 'Failed to delete section'],
        ], 400);
    }
    
    /**
     * Upload document to section
     */
    public static function uploadDocument(\WP_REST_Request $request): \WP_REST_Response
    {
        $sectionId = $request->get_param('id');
        
        // Handle both file upload and JSON content upload
        $files = $request->get_file_params();
        $jsonParams = $request->get_json_params();
        
        try {
            if (!empty($files['file'])) {
                // Standard file upload
                $doc = DocumentManager::uploadDocument($sectionId, $files['file']);
            } elseif (!empty($jsonParams['name']) && isset($jsonParams['content'])) {
                // JSON content upload
                $doc = DocumentManager::uploadDocument($sectionId, [
                    'name' => $jsonParams['name'],
                    'content' => $jsonParams['content'],
                ]);
            } else {
                throw new DocumentValidationException('No file or content provided');
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'title' => $doc->title,
                    'message' => 'Document uploaded successfully',
                ],
            ], 201);
            
        } catch (DocumentValidationException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => [
                    'code' => 'UPLOAD_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
    
    // ============================================
    // Document Handlers
    // ============================================
    
    /**
     * Get document metadata
     */
    public static function getDocument(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $doc = DocumentRegistry::getDocument($id);
        
        if (!$doc) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => "Document not found: {$id}"],
            ], 404);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $doc->toArray(),
        ], 200);
    }
    
    /**
     * Get document content
     */
    public static function getDocumentContent(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $doc = DocumentRegistry::loadDocument($id);
        
        if (!$doc) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => "Document not found: {$id}"],
            ], 404);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                ...$doc->toArray(),
                'content' => $doc->getContent(),
            ],
        ], 200);
    }
    
    /**
     * Delete document
     */
    public static function deleteDocument(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        
        $deleted = DocumentManager::deleteDocument($id);
        
        if ($deleted) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => ['message' => 'Document deleted successfully'],
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'error' => ['code' => 'DELETE_FAILED', 'message' => 'Failed to delete document'],
        ], 400);
    }
}
