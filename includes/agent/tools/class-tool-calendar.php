<?php
/**
 * Calendar Tool
 * 
 * Allows the AI to book appointments and check availability.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Tool_Calendar extends SWC_Tool_Base {
    
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct($config = []) {
        parent::__construct($config);
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'swc_appointments';
        $this->ensure_table_exists();
    }
    
    /**
     * Ensure appointments table exists
     */
    private function ensure_table_exists() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table supports both full appointments AND simple booking requests
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50),
            service VARCHAR(255),
            purpose TEXT,
            appointment_date DATE,
            appointment_time TIME,
            duration INT DEFAULT 30,
            status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_date (appointment_date),
            INDEX idx_status (status),
            INDEX idx_email (customer_email)
        ) $charset_collate;";
        
        require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get AI definition
     */
    public function get_ai_definition() {
        return "Use the Calendar tool to manage appointments and booking requests.

**Available Actions:**

1. **submit_booking_request** - Submit a simple booking request (PREFERRED)
   - customer_name: Customer's name (required)
   - customer_email: Customer's email (required)
   - purpose: Purpose/reason for the appointment (required)
   Use this when you've collected name, email, and purpose from the user.
   The admin will contact them to schedule the actual date/time.

2. **book_appointment** - Book a full appointment with date/time
   - customer_name: Customer's name (required)
   - customer_email: Customer's email (required)
   - date: Date in YYYY-MM-DD format (required)
   - time: Time in HH:MM format (required)
   - notes: Additional notes (optional)

3. **get_appointments** - Get appointments (admin use)
   - date: Date in YYYY-MM-DD format
   - status: Filter by status

**IMPORTANT:** For the Appointment Booker skill, use submit_booking_request 
after collecting the user's name, email, and purpose.
";
    }
    
    /**
     * Get available actions
     */
    public function get_actions() {
        return ['submit_booking_request', 'book_appointment', 'get_appointments', 'cancel_appointment', 'check_availability', 'get_available_slots'];
    }
    
    /**
     * Submit a simple booking request (name, email, purpose only)
     * Admin will contact to schedule actual date/time
     */
    public function submit_booking_request($params) {
        global $wpdb;
        
        $this->validate_params($params, ['customer_name', 'customer_email', 'purpose']);
        
        $name = sanitize_text_field($params['customer_name']);
        $email = sanitize_email($params['customer_email']);
        $purpose = sanitize_textarea_field($params['purpose']);
        
        if (!is_email($email)) {
            return [
                'success' => false,
                'message' => 'Please provide a valid email address.'
            ];
        }
        
        // Insert booking request
        $result = $wpdb->insert(
            $this->table_name,
            [
                'customer_name' => $name,
                'customer_email' => $email,
                'purpose' => $purpose,
                'status' => 'pending',
                'notes' => 'Submitted via chatbot - admin to schedule'
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Sorry, there was an error saving your request. Please try again.'
            ];
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Send notification email to admin
        $admin_email = get_option('admin_email');
        $store_name = get_bloginfo('name');
        $subject = "New Booking Request from {$name}";
        $message = "A new booking request has been submitted:\n\n";
        $message .= "Name: {$name}\n";
        $message .= "Email: {$email}\n";
        $message .= "Purpose: {$purpose}\n\n";
        $message .= "Please contact them to schedule a date and time.\n";
        $message .= "Booking ID: #{$booking_id}";
        
        wp_mail($admin_email, $subject, $message);
        
        // Sync to Google Calendar if configured
        $calendar_synced = false;
        if (class_exists('SWC_Chatbot_Google_Calendar')) {
            $google_cal = new SWC_Chatbot_Google_Calendar();
            if ($google_cal->is_configured()) {
                $event_id = $google_cal->create_booking_event([
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'purpose' => $purpose
                ]);
                if ($event_id) {
                    $calendar_synced = true;
                    // Update booking with calendar event ID
                    $wpdb->update(
                        $this->table_name,
                        ['notes' => 'Submitted via chatbot - synced to Google Calendar (Event: ' . $event_id . ')'],
                        ['id' => $booking_id]
                    );
                }
            }
        }
        
        $sync_note = $calendar_synced ? ' (Also added to calendar)' : '';
        
        return [
            'success' => true,
            'booking_id' => $booking_id,
            'calendar_synced' => $calendar_synced,
            'message' => "Your booking request has been submitted. We'll contact you at {$email} to schedule a convenient time.{$sync_note}"
        ];
    }
    
    /**
     * Check if a time slot is available
     */
    public function check_availability($params) {
        $this->validate_params($params, ['date']);
        
        $date = sanitize_text_field($params['date']);
        $time = isset($params['time']) ? sanitize_text_field($params['time']) : null;
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format. Use YYYY-MM-DD');
        }
        
        // Check if date is in the past
        if (strtotime($date) < strtotime('today')) {
            return [
                'available' => false,
                'reason' => 'Cannot book appointments in the past'
            ];
        }
        
        // Check if it's a working day
        $day_name = date('D', strtotime($date));
        $working_days = $this->config['working_days'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        
        if (!in_array($day_name, $working_days)) {
            return [
                'available' => false,
                'reason' => "We are closed on {$day_name}. Working days: " . implode(', ', $working_days)
            ];
        }
        
        if ($time) {
            // Check specific time slot
            if (!$this->is_within_working_hours($time)) {
                $start = $this->config['working_hours_start'] ?? '09:00';
                $end = $this->config['working_hours_end'] ?? '17:00';
                return [
                    'available' => false,
                    'reason' => "Time is outside working hours ({$start} - {$end})"
                ];
            }
            
            $is_booked = $this->is_slot_booked($date, $time);
            
            return [
                'available' => !$is_booked,
                'date' => $date,
                'time' => $time,
                'reason' => $is_booked ? 'This slot is already booked' : 'Slot is available'
            ];
        }
        
        // Return all available slots for the day
        $available_slots = $this->get_available_slots(['date' => $date]);
        
        return [
            'available' => count($available_slots['slots']) > 0,
            'date' => $date,
            'available_slots' => $available_slots['slots']
        ];
    }
    
    /**
     * Get available time slots for a date
     */
    public function get_available_slots($params) {
        $this->validate_params($params, ['date']);
        
        $date = sanitize_text_field($params['date']);
        $start = $this->config['working_hours_start'] ?? '09:00';
        $end = $this->config['working_hours_end'] ?? '17:00';
        $duration = intval($this->config['slot_duration'] ?? 30);
        
        // Get all booked slots
        $booked = $this->get_booked_times($date);
        
        // Generate all possible slots
        $slots = [];
        $current = strtotime($start);
        $end_time = strtotime($end);
        
        while ($current < $end_time) {
            $time_str = date('H:i', $current);
            if (!in_array($time_str, $booked)) {
                $slots[] = $time_str;
            }
            $current += $duration * 60;
        }
        
        return [
            'date' => $date,
            'slots' => $slots,
            'count' => count($slots)
        ];
    }
    
    /**
     * Book an appointment
     */
    public function book_appointment($params) {
        global $wpdb;
        
        $this->validate_params($params, ['customer_name', 'customer_email', 'date', 'time']);
        
        $date = sanitize_text_field($params['date']);
        $time = sanitize_text_field($params['time']);
        
        // Check availability first
        $availability = $this->check_availability(['date' => $date, 'time' => $time]);
        if (!$availability['available']) {
            throw new Exception($availability['reason']);
        }
        
        $data = [
            'customer_name' => sanitize_text_field($params['customer_name']),
            'customer_email' => sanitize_email($params['customer_email']),
            'customer_phone' => sanitize_text_field($params['customer_phone'] ?? ''),
            'service' => sanitize_text_field($params['service'] ?? ''),
            'appointment_date' => $date,
            'appointment_time' => $time,
            'duration' => intval($this->config['slot_duration'] ?? 30),
            'status' => 'confirmed',
            'notes' => sanitize_textarea_field($params['notes'] ?? '')
        ];
        
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result === false) {
            throw new Exception('Failed to book appointment: ' . $wpdb->last_error);
        }
        
        $appointment_id = $wpdb->insert_id;
        
        $this->log('book_appointment', $params, $appointment_id);
        
        // Format for display
        $formatted_date = date('l, F j, Y', strtotime($date));
        $formatted_time = date('g:i A', strtotime($time));
        
        return [
            'success' => true,
            'appointment_id' => $appointment_id,
            'message' => "Appointment booked successfully!",
            'details' => [
                'date' => $formatted_date,
                'time' => $formatted_time,
                'customer' => $data['customer_name'],
                'email' => $data['customer_email'],
                'service' => $data['service']
            ]
        ];
    }
    
    /**
     * Get appointments
     */
    public function get_appointments($params) {
        global $wpdb;
        
        $date = isset($params['date']) ? sanitize_text_field($params['date']) : null;
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : null;
        $email = isset($params['email']) ? sanitize_email($params['email']) : null;
        
        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $args = [];
        
        if ($date) {
            $sql .= " AND appointment_date = %s";
            $args[] = $date;
        }
        
        if ($status) {
            $sql .= " AND status = %s";
            $args[] = $status;
        }
        
        if ($email) {
            $sql .= " AND customer_email = %s";
            $args[] = $email;
        }
        
        $sql .= " ORDER BY appointment_date ASC, appointment_time ASC LIMIT 50";
        
        if (!empty($args)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        } else {
            $results = $wpdb->get_results($sql, ARRAY_A);
        }
        
        // Format dates for display
        foreach ($results as &$row) {
            $row['formatted_date'] = date('l, F j, Y', strtotime($row['appointment_date']));
            $row['formatted_time'] = date('g:i A', strtotime($row['appointment_time']));
        }
        
        return [
            'success' => true,
            'count' => count($results),
            'appointments' => $results
        ];
    }
    
    /**
     * Cancel an appointment
     */
    public function cancel_appointment($params) {
        global $wpdb;
        
        $this->validate_params($params, ['appointment_id']);
        
        $result = $wpdb->update(
            $this->table_name,
            ['status' => 'cancelled'],
            ['id' => intval($params['appointment_id'])]
        );
        
        if ($result === false) {
            throw new Exception('Failed to cancel appointment');
        }
        
        $this->log('cancel_appointment', $params, $result);
        
        return [
            'success' => true,
            'message' => 'Appointment cancelled successfully'
        ];
    }
    
    /**
     * Check if time is within working hours
     */
    private function is_within_working_hours($time) {
        $start = $this->config['working_hours_start'] ?? '09:00';
        $end = $this->config['working_hours_end'] ?? '17:00';
        
        $time_ts = strtotime($time);
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        
        return $time_ts >= $start_ts && $time_ts < $end_ts;
    }
    
    /**
     * Check if a specific slot is booked
     */
    private function is_slot_booked($date, $time) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE appointment_date = %s 
             AND appointment_time = %s 
             AND status IN ('pending', 'confirmed')",
            $date,
            $time
        ));
        
        return $count > 0;
    }
    
    /**
     * Get all booked times for a date
     */
    private function get_booked_times($date) {
        global $wpdb;
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT TIME_FORMAT(appointment_time, '%%H:%%i') 
             FROM {$this->table_name} 
             WHERE appointment_date = %s 
             AND status IN ('pending', 'confirmed')",
            $date
        ));
        
        return $results ?: [];
    }
}
