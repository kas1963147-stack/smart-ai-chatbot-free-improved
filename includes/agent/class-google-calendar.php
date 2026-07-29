<?php
/**
 * Google Calendar Integration
 * 
 * Syncs booking requests to Google Calendar using Service Account credentials.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Google_Calendar {
    
    private $settings;
    private $access_token;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('swc_calendar_settings', []);
    }
    
    /**
     * Check if calendar integration is configured
     */
    public function is_configured() {
        return !empty($this->settings['calendar_id']) && !empty($this->settings['service_account']);
    }
    
    /**
     * Get access token using Service Account
     */
    private function get_access_token() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        $service_account = json_decode($this->settings['service_account'], true);
        if (!$service_account || !isset($service_account['private_key'])) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Google Calendar: Invalid service account JSON');
            }
            return false;
        }
        
        // Create JWT
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        
        $now = time();
        $claims = [
            'iss' => $service_account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ];
        $payload = base64_encode(json_encode($claims));
        
        // Sign with private key
        $signature = '';
        $private_key = openssl_pkey_get_private($service_account['private_key']);
        if (!$private_key) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Google Calendar: Failed to load private key');
            }
            return false;
        }
        
        openssl_sign($header . '.' . $payload, $signature, $private_key, 'SHA256');
        $jwt = $header . '.' . $payload . '.' . $this->base64url_encode($signature);
        
        // Exchange JWT for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]
        ]);
        
        if (is_wp_error($response)) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Google Calendar: Token request failed', ['error' => $response->get_error_message()]);
            }
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            return $this->access_token;
        }
        
        if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::error('Google Calendar: No access token in response', ['response' => $body]);
        }
        return false;
    }
    
    /**
     * URL-safe base64 encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Create a calendar event for a booking request
     */
    public function create_booking_event($booking) {
        if (!$this->is_configured()) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::warning('Google Calendar: Not configured');
            }
            return false;
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $calendar_id = $this->settings['calendar_id'];
        
        // Create all-day event for booking request (no specific time)
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $event = [
            'summary' => ' Booking Request: ' . $booking['customer_name'],
            'description' => "Booking Request from Chatbot\n\n" .
                           "Name: " . $booking['customer_name'] . "\n" .
                           "Email: " . $booking['customer_email'] . "\n" .
                           "Purpose: " . ($booking['purpose'] ?? 'Not specified') . "\n\n" .
                           "Please contact to schedule a specific time.",
            'start' => [
                'date' => $tomorrow
            ],
            'end' => [
                'date' => $tomorrow
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ]
        ];
        
        $response = wp_remote_post(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($event)
            ]
        );
        
        if (is_wp_error($response)) {
            if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::error('Google Calendar: Failed to create event', ['error' => $response->get_error_message()]);
            }
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['id'])) {
            if (defined('WP_DEBUG') && \WP_DEBUG && class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
                \Quarksol\SmartChatbot\Services\Logger::debug('Google Calendar: Event created', ['event_id' => $body['id']]);
            }
            return $body['id'];
        }
        
        if (class_exists('\Quarksol\SmartChatbot\Services\Logger')) {
            \Quarksol\SmartChatbot\Services\Logger::error('Google Calendar: Event creation failed', ['response' => $body]);
        }
        return false;
    }
}
