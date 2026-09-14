<?php
declare(strict_types=1);
/**
 * API Pricing Settings Controller
 * 
 * REST API endpoints for managing API pricing configuration
 * 
 * @package Quarksol\SmartChatbot\Api
 */

namespace Quarksol\SmartChatbot\Api;

use Quarksol\SmartChatbot\Analytics\CostCalculator;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Pricing Settings Controller
 */
class ApiPricingController {
    
    /** Route namespace */
    private const NAMESPACE = 'quark-agentflow-ai/v1';
    
    /** Cost calculator */
    private CostCalculator $calculator;
    
    public function __construct() {
        $this->calculator = new CostCalculator();
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Get all pricing
        register_rest_route(self::NAMESPACE, '/pricing', [
            'methods' => 'GET',
            'callback' => [$this, 'getAllPricing'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
        
        // Save custom pricing
        register_rest_route(self::NAMESPACE, '/pricing', [
            'methods' => 'POST',
            'callback' => [$this, 'savePricing'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'provider' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'model' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'input_price' => [
                    'type' => ['number', 'null'],
                ],
                'output_price' => [
                    'type' => ['number', 'null'],
                ],
                'cache_write_price' => [
                    'type' => ['number', 'null'],
                ],
                'cache_read_price' => [
                    'type' => ['number', 'null'],
                ],
            ],
        ]);
        
        // Delete custom pricing (reset to default)
        register_rest_route(self::NAMESPACE, '/pricing/(?P<provider>[a-z0-9-]+)/(?P<model>[a-z0-9-.:]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deletePricing'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
        
        // Recalculate historical costs
        register_rest_route(self::NAMESPACE, '/pricing/recalculate-costs', [
            'methods' => 'POST',
            'callback' => [$this, 'recalculateHistoricalCosts'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'from_date' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'default' => 1000,
                ],
            ],
        ]);
    }
    
    /**
     * Check if user has permission
     */
    public function checkPermission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Get all pricing (defaults + custom overrides)
     */
    public function getAllPricing(WP_REST_Request $request): WP_REST_Response {
        try {
            $pricing = $this->calculator->getAllPricing();
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $pricing,
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Save custom pricing override
     */
    public function savePricing(WP_REST_Request $request): WP_REST_Response {
        $provider = $request->get_param('provider');
        $model = $request->get_param('model');
        $inputPrice = $request->get_param('input_price');
        $outputPrice = $request->get_param('output_price');
        $cacheWritePrice = $request->get_param('cache_write_price');
        $cacheReadPrice = $request->get_param('cache_read_price');
        
        try {
            $success = $this->calculator->savePricing(
                $provider,
                $model,
                $inputPrice,
                $outputPrice,
                $cacheWritePrice,
                $cacheReadPrice
            );
            
            if ($success) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Pricing saved successfully', 'quark-agentflow-ai'),
                ], 200);
            } else {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Failed to save pricing', 'quark-agentflow-ai'),
                ], 500);
            }
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Delete custom pricing (revert to default)
     */
    public function deletePricing(WP_REST_Request $request): WP_REST_Response {
        $provider = $request->get_param('provider');
        $model = $request->get_param('model');
        
        try {
            $success = $this->calculator->deletePricing($provider, $model);
            
            if ($success) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Custom pricing removed, reverted to default', 'quark-agentflow-ai'),
                ], 200);
            } else {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('No custom pricing found to delete', 'quark-agentflow-ai'),
                ], 404);
            }
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Recalculate historical costs
     */
    public function recalculateHistoricalCosts(WP_REST_Request $request): WP_REST_Response {
        $fromDate = $request->get_param('from_date');
        $limit = $request->get_param('limit') ?? 1000;
        
        try {
            $result = $this->calculator->recalculateHistoricalCosts($fromDate, $limit);
            
            return new WP_REST_Response([
                'success' => $result['success'],
                'data' => $result,
                'message' => $result['message'],
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
