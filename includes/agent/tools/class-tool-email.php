<?php
/**
 * Email Tool
 * 
 * Allows the AI to send emails to admin or users.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Tool_Email extends SWC_Tool_Base {
    
    /**
     * Get AI definition
     */
    public function get_ai_definition() {
        return "Use the Email tool to send notifications and confirmations.

**Available Actions:**

1. **send_to_admin** - Send email to store admin
   - subject: Email subject (required)
   - message: Email body (required)
   - reply_to: (optional) User's email for replies

2. **send_to_user** - Send email to a user
   - to_email: Recipient email (required)
   - subject: Email subject (required)
   - message: Email body (required)

3. **send_confirmation** - Send appointment/order confirmation
   - to_email: Recipient email (required)
   - type: 'appointment' or 'order'
   - details: Object with relevant details

**Guidelines:**
- Always get explicit consent before collecting email
- Use clear, professional language
- Include all relevant details in confirmations
";
    }
    
    /**
     * Get available actions
     */
    public function get_actions() {
        return ['send_to_admin', 'send_to_user', 'send_confirmation'];
    }
    
    /**
     * Send email to admin
     */
    public function send_to_admin($params) {
        $this->validate_params($params, ['subject', 'message']);
        
        $admin_email = $this->config['admin_email'] ?? get_option('admin_email');
        $from_name = $this->config['email_from_name'] ?? get_bloginfo('name');
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <" . get_option('admin_email') . ">"
        ];
        
        if (!empty($params['reply_to'])) {
            $headers[] = 'Reply-To: ' . sanitize_email($params['reply_to']);
        }
        
        $subject = sanitize_text_field($params['subject']);
        $message = $this->format_email_body($params['message']);
        
        $result = wp_mail($admin_email, $subject, $message, $headers);
        
        $this->log('send_to_admin', ['subject' => $subject], $result);
        
        return [
            'success' => $result,
            'message' => $result ? 'Email sent to admin successfully' : 'Failed to send email'
        ];
    }
    
    /**
     * Send email to user
     */
    public function send_to_user($params) {
        $this->validate_params($params, ['to_email', 'subject', 'message']);
        
        $to_email = sanitize_email($params['to_email']);
        
        if (!is_email($to_email)) {
            throw new Exception('Invalid email address');
        }
        
        $from_name = $this->config['email_from_name'] ?? get_bloginfo('name');
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <" . get_option('admin_email') . ">"
        ];
        
        $subject = sanitize_text_field($params['subject']);
        $message = $this->format_email_body($params['message']);
        
        $result = wp_mail($to_email, $subject, $message, $headers);
        
        $this->log('send_to_user', ['to' => $to_email, 'subject' => $subject], $result);
        
        return [
            'success' => $result,
            'message' => $result ? 'Email sent successfully' : 'Failed to send email'
        ];
    }
    
    /**
     * Send confirmation email
     */
    public function send_confirmation($params) {
        $this->validate_params($params, ['to_email', 'type', 'details']);
        
        $to_email = sanitize_email($params['to_email']);
        $type = sanitize_text_field($params['type']);
        $details = $params['details'];
        
        $store_name = get_bloginfo('name');
        
        switch ($type) {
            case 'appointment':
                $subject = "Appointment Confirmation - {$store_name}";
                $message = $this->build_appointment_confirmation($details);
                break;
                
            case 'order':
                $subject = "Order Confirmation - {$store_name}";
                $message = $this->build_order_confirmation($details);
                break;
                
            case 'lead':
                $subject = "Thank You for Contacting Us - {$store_name}";
                $message = $this->build_lead_confirmation($details);
                break;
                
            default:
                $subject = "Confirmation - {$store_name}";
                $message = $this->build_generic_confirmation($details);
        }
        
        return $this->send_to_user([
            'to_email' => $to_email,
            'subject' => $subject,
            'message' => $message
        ]);
    }
    
    /**
     * Format email body with styling
     */
    private function format_email_body($content) {
        $store_name = get_bloginfo('name');
        
        return "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6366f1; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"header\">
            <h1>{$store_name}</h1>
        </div>
        <div class=\"content\">
            {$content}
        </div>
        <div class=\"footer\">
            <p>This email was sent from {$store_name}</p>
        </div>
    </div>
</body>
</html>
";
    }
    
    /**
     * Build appointment confirmation content
     */
    private function build_appointment_confirmation($details) {
        $name = esc_html($details['customer_name'] ?? 'Customer');
        $date = esc_html($details['date'] ?? '');
        $time = esc_html($details['time'] ?? '');
        $service = esc_html($details['service'] ?? 'General');
        
        return "<h2>Appointment Confirmed! </h2>
<p>Dear {$name},</p>
<p>Your appointment has been successfully booked.</p>

<table style=\"width: 100%; border-collapse: collapse; margin: 20px 0;\">
    <tr>
        <td style=\"padding: 10px; border-bottom: 1px solid #ddd;\"><strong>Date:</strong></td>
        <td style=\"padding: 10px; border-bottom: 1px solid #ddd;\">{$date}</td>
    </tr>
    <tr>
        <td style=\"padding: 10px; border-bottom: 1px solid #ddd;\"><strong>Time:</strong></td>
        <td style=\"padding: 10px; border-bottom: 1px solid #ddd;\">{$time}</td>
    </tr>
    <tr>
        <td style=\"padding: 10px; border-bottom: 1px solid #ddd;\"><strong>Service:</strong></td>
        <td style=\"padding: 10px; border-bottom: 1px solid #ddd;\">{$service}</td>
    </tr>
</table>

<p>If you need to reschedule or cancel, please contact us.</p>
<p>We look forward to seeing you!</p>
";
    }
    
    /**
     * Build order confirmation content
     */
    private function build_order_confirmation($details) {
        $order_id = esc_html($details['order_id'] ?? '');
        $total = esc_html($details['total'] ?? '');
        
        return "<h2>Order Confirmation </h2>
<p>Thank you for your order!</p>

<p><strong>Order Number:</strong> {$order_id}</p>
<p><strong>Total:</strong> {$total}</p>

<p>We'll send you tracking information once your order ships.</p>
";
    }
    
    /**
     * Build lead confirmation content
     */
    private function build_lead_confirmation($details) {
        $name = esc_html($details['name'] ?? 'there');
        
        return "<h2>Thank You for Reaching Out! </h2>
<p>Hi {$name},</p>
<p>We've received your inquiry and will get back to you as soon as possible.</p>
<p>Our team typically responds within 24 hours.</p>
<p>In the meantime, feel free to browse our website for more information.</p>
";
    }
    
    /**
     * Build generic confirmation content
     */
    private function build_generic_confirmation($details) {
        $message = esc_html($details['message'] ?? 'Your request has been received.');
        
        return "<h2>Confirmation </h2>
<p>{$message}</p>
<p>Thank you for contacting us!</p>
";
    }
}
