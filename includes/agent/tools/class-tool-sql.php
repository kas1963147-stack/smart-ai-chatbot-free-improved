<?php
/**
 * SQL Tool
 * 
 * Allows the AI to save and query data in custom database tables.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Tool_SQL extends SWC_Tool_Base {
    
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct($config = []) {
        parent::__construct($config);
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'swc_custom_data';
        $this->ensure_table_exists();
    }
    
    /**
     * Ensure the custom data table exists
     */
    private function ensure_table_exists() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            data_type VARCHAR(100) NOT NULL,
            data_key VARCHAR(255),
            data_value LONGTEXT,
            metadata JSON,
            user_id BIGINT DEFAULT NULL,
            session_id VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_data_type (data_type),
            INDEX idx_data_key (data_key),
            INDEX idx_user_id (user_id),
            INDEX idx_session_id (session_id)
        ) $charset_collate;";
        
        require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get AI definition
     */
    public function get_ai_definition() {
        return "Use the SQL tool to save and retrieve custom data.

**Available Actions:**

1. **save_data** - Save information to database
   - data_type: Category of data (e.g., \"lead\", \"feedback\", \"appointment\")
   - data_key: Unique identifier (e.g., email, name)
   - data_value: The main data to save
   - metadata: Additional JSON data

2. **get_data** - Retrieve saved data
   - data_type: Category to search
   - data_key: (optional) Specific key to find

3. **search_data** - Search across data
   - search_term: Text to search for
   - data_type: (optional) Limit to specific type

**Example Usage:**
To save a lead: save_data with data_type=\"lead\", data_key=email, data_value=name
To find all leads: get_data with data_type=\"lead\"
";
    }
    
    /**
     * Get available actions
     */
    public function get_actions() {
        return ['save_data', 'get_data', 'search_data', 'delete_data'];
    }
    
    /**
     * Save data
     */
    public function save_data($params) {
        global $wpdb;
        
        $this->validate_params($params, ['data_type', 'data_value']);
        
        $scope = $this->getAccessScope();
        if (!$scope['is_admin'] && !$scope['user_id'] && !$scope['session_id']) {
            throw new Exception('Permission denied: missing user/session context');
        }

        $data = [
            'data_type' => sanitize_text_field($params['data_type']),
            'data_key' => sanitize_text_field($params['data_key'] ?? ''),
            'data_value' => wp_kses_post($params['data_value']),
            'metadata' => isset($params['metadata']) ? wp_json_encode($params['metadata']) : null,
            'user_id' => $scope['user_id'],
            'session_id' => $scope['session_id'],
        ];

        if ($scope['is_admin']) {
            if (isset($params['user_id'])) {
                $data['user_id'] = intval($params['user_id']);
            }
            if (isset($params['session_id'])) {
                $data['session_id'] = sanitize_text_field($params['session_id']);
            }
        }
        
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result === false) {
            throw new Exception('Failed to save data: ' . $wpdb->last_error);
        }
        
        $this->log('save_data', $params, $wpdb->insert_id);
        
        return [
            'success' => true,
            'id' => $wpdb->insert_id,
            'message' => "Data saved successfully with ID: {$wpdb->insert_id}"
        ];
    }
    
    /**
     * Get data
     */
    public function get_data($params) {
        global $wpdb;
        
        $this->validate_params($params, ['data_type']);
        
        $scope = $this->getAccessScope();
        if (!$scope['is_admin'] && !$scope['user_id'] && !$scope['session_id']) {
            return [
                'success' => false,
                'error' => 'Permission denied: missing user/session context'
            ];
        }

        $data_type = sanitize_text_field($params['data_type']);
        $data_key = isset($params['data_key']) ? sanitize_text_field($params['data_key']) : null;
        
        $sql = "SELECT * FROM {$this->table_name} WHERE data_type = %s";
        $args = [$data_type];
        
        if ($data_key) {
            $sql .= " AND data_key = %s";
            $args[] = $data_key;
        }

        if (!$scope['is_admin']) {
            if (!empty($scope['user_id'])) {
                $sql .= " AND user_id = %d";
                $args[] = $scope['user_id'];
            } else {
                $sql .= " AND session_id = %s";
                $args[] = $scope['session_id'];
            }
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        
        // Parse metadata JSON
        foreach ($results as &$row) {
            if (!empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }
        
        $this->log('get_data', $params, count($results) . ' results');
        
        return [
            'success' => true,
            'count' => count($results),
            'data' => $results
        ];
    }
    
    /**
     * Search data
     */
    public function search_data($params) {
        global $wpdb;
        
        $this->validate_params($params, ['search_term']);
        
        $scope = $this->getAccessScope();
        if (!$scope['is_admin'] && !$scope['user_id'] && !$scope['session_id']) {
            return [
                'success' => false,
                'error' => 'Permission denied: missing user/session context'
            ];
        }

        $search = '%' . $wpdb->esc_like($params['search_term']) . '%';
        $data_type = isset($params['data_type']) ? sanitize_text_field($params['data_type']) : null;
        
        $sql = "SELECT * FROM {$this->table_name} WHERE (data_key LIKE %s OR data_value LIKE %s)";
        $args = [$search, $search];
        
        if ($data_type) {
            $sql .= " AND data_type = %s";
            $args[] = $data_type;
        }

        if (!$scope['is_admin']) {
            if (!empty($scope['user_id'])) {
                $sql .= " AND user_id = %d";
                $args[] = $scope['user_id'];
            } else {
                $sql .= " AND session_id = %s";
                $args[] = $scope['session_id'];
            }
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 20";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        
        $this->log('search_data', $params, count($results) . ' results');
        
        return [
            'success' => true,
            'count' => count($results),
            'data' => $results
        ];
    }
    
    /**
     * Delete data
     */
    public function delete_data($params) {
        global $wpdb;
        
        $this->validate_params($params, ['id']);
        
        $scope = $this->getAccessScope();
        if (!$scope['is_admin'] && !$scope['user_id'] && !$scope['session_id']) {
            return [
                'success' => false,
                'error' => 'Permission denied: missing user/session context'
            ];
        }

        $where = ['id' => intval($params['id'])];
        $whereFormat = ['%d'];

        if (!$scope['is_admin']) {
            if (!empty($scope['user_id'])) {
                $where['user_id'] = $scope['user_id'];
                $whereFormat[] = '%d';
            } else {
                $where['session_id'] = $scope['session_id'];
                $whereFormat[] = '%s';
            }
        }

        $result = $wpdb->delete($this->table_name, $where, $whereFormat);
        
        if ($result === false) {
            throw new Exception('Failed to delete data');
        }
        
        $this->log('delete_data', $params, $result);
        
        return [
            'success' => true,
            'message' => 'Data deleted successfully'
        ];
    }

    /**
     * Resolve access scope for data operations.
     */
    private function getAccessScope(): array {
        $isAdmin = function_exists('current_user_can') && current_user_can('manage_options');
        $userId = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        $sessionId = null;

        if (!$userId) {
            if (class_exists('\Quarksol\SmartChatbot\Services\SecurityMiddleware')) {
                $sessionId = \Quarksol\SmartChatbot\Services\SecurityMiddleware::getVisitorId();
            } elseif (function_exists('session_id')) {
                $sessionId = session_id();
            }
        }

        return [
            'is_admin' => $isAdmin,
            'user_id' => $userId ?: null,
            'session_id' => $sessionId ?: null,
        ];
    }
}
