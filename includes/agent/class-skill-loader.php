<?php
/**
 * Agent Skill Loader
 * 
 * Loads behavior skills from Markdown files.
 * Skills define how the chatbot should behave (Shopping, Appointments, etc.)
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Skill_Loader {
    
    private static $instance = null;
    private $skills_dir;
    private $skills = [];
    private $active_skills = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->skills_dir = SWC_CHATBOT_PATH . 'skills/';
        $this->ensure_skills_directory();
        $this->load_skills();
        $this->load_active_skills();
    }
    
    /**
     * Ensure skills directory exists with default skills
     */
    private function ensure_skills_directory() {
        if (!file_exists($this->skills_dir)) {
            wp_mkdir_p($this->skills_dir);
            $this->create_default_skills();
        }
    }
    
    /**
     * Create default skill files
     */
    private function create_default_skills() {
        // Shopping Assistant Skill
        $shopping_skill = "---
name: Shopping Assistant
slug: shopping_assistant
description: Helps users find products, manage cart, and track orders
icon: 
tools_required: [woocommerce]
always_on: false
---

# Shopping Assistant Behavior

## Core Responsibilities
- Help users find products they're looking for
- Show product details, prices, and availability
- Assist with cart management
- Track order status

## Response Style
- Be friendly and helpful
- Use emojis to make responses engaging
- Always show product images when available
- Highlight prices and discounts

## When user asks about products:
1. Search for matching products
2. Show top results with images
3. Offer to filter by category or price

## When user wants to track order:
1. Ask for order ID or email
2. Look up order status
3. Provide shipping information
";
        
        file_put_contents($this->skills_dir . 'shopping_assistant.md', $shopping_skill);
        
        // Appointment Booker Skill
        $booking_skill = "---
name: Appointment Booker
slug: appointment_booker
description: Helps users book appointments and manage schedules
icon: 
tools_required: [calendar, email]
always_on: false
---

# Appointment Booking Behavior

## Core Responsibilities
- Help users book appointments
- Check availability for requested times
- Send confirmation emails
- Handle rescheduling and cancellations

## Booking Flow
1. Ask what service they need
2. Ask for preferred date and time
3. Check availability using calendar tool
4. If available, confirm booking details
5. Save appointment and send confirmation

## When slot is unavailable:
- Offer alternative times on same day
- Suggest next available day
- Never double-book

## Required Information
- Name
- Email
- Phone (optional)
- Preferred date and time
- Service type
";
        
        file_put_contents($this->skills_dir . 'appointment_booker.md', $booking_skill);
        
        // Support Agent Skill
        $support_skill = "---
name: Support Agent
slug: support_agent
description: Provides customer support and answers common questions
icon: 
tools_required: [faq, email]
always_on: false
---

# Support Agent Behavior

## Core Responsibilities
- Answer frequently asked questions
- Provide store policies information
- Escalate complex issues to human support
- Collect feedback

## Response Priority
1. Check FAQ database first
2. Provide relevant policy information
3. If cannot help, offer to connect with human

## Escalation Triggers
- User explicitly asks for human
- Issue involves payment disputes
- Technical problems beyond scope
- User frustration detected

## When escalating:
- Collect user's email
- Summarize the issue
- Send notification to admin
- Assure user they'll be contacted
";
        
        file_put_contents($this->skills_dir . 'support_agent.md', $support_skill);
        
        // Lead Generator Skill
        $lead_skill = "---
name: Lead Generator
slug: lead_generator
description: Collects user information and saves leads to database
icon: 
tools_required: [sql, email]
always_on: false
---

# Lead Generation Behavior

## Core Responsibilities
- Engage visitors in conversation
- Collect contact information naturally
- Save leads to database
- Notify admin of new leads

## Information to Collect
- Name (required)
- Email (required)
- Phone (optional)
- Interest/Need
- Source page

## Conversation Flow
1. Greet and offer help
2. Understand their needs through questions
3. Provide relevant information
4. Naturally request contact info
5. Save to database
6. Send thank you message

## Do NOT:
- Be pushy about information
- Ask for sensitive data
- Make promises about callbacks
";
        
        file_put_contents($this->skills_dir . 'lead_generator.md', $lead_skill);
    }
    
    /**
     * Load all skills from directory
     */
    private function load_skills() {
        $files = glob($this->skills_dir . '*.md');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $skill = $this->parse_skill_file($content, basename($file, '.md'));
            if ($skill) {
                $this->skills[$skill['slug']] = $skill;
            }
        }
    }
    
    /**
     * Parse a skill Markdown file
     */
    private function parse_skill_file($content, $filename) {
        // Extract YAML frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontmatter = $this->parse_yaml($matches[1]);
            $body = trim($matches[2]);
            
            return [
                'slug' => $frontmatter['slug'] ?? $filename,
                'name' => $frontmatter['name'] ?? ucfirst($filename),
                'description' => $frontmatter['description'] ?? '',
                'icon' => $frontmatter['icon'] ?? '',
                'tools_required' => $frontmatter['tools_required'] ?? [],
                'always_on' => $frontmatter['always_on'] ?? false,
                'content' => $body,
                'file' => $filename . '.md'
            ];
        }
        
        return null;
    }
    
    /**
     * Simple YAML parser for frontmatter
     */
    private function parse_yaml($yaml) {
        $result = [];
        $lines = explode("\n", $yaml);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);
                
                // Handle arrays
                if (preg_match('/^\[(.*)\]$/', $value, $arr_matches)) {
                    $items = explode(',', $arr_matches[1]);
                    $value = array_map(function($item) {
                        return trim($item, " \t\n\r\0\x0B\"'");
                    }, $items);
                }
                // Handle booleans
                elseif ($value === 'true') {
                    $value = true;
                }
                elseif ($value === 'false') {
                    $value = false;
                }
                
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Load active skills from settings
     */
    private function load_active_skills() {
        $this->active_skills = get_option('swc_active_skills', ['shopping_assistant']);
    }
    
    /**
     * Get all available skills
     */
    public function get_all_skills() {
        return $this->skills;
    }
    
    /**
     * Get active skills
     */
    public function get_active_skills() {
        $active = [];
        foreach ($this->active_skills as $slug) {
            if (isset($this->skills[$slug])) {
                $active[$slug] = $this->skills[$slug];
            }
        }
        return $active;
    }
    
    /**
     * Check if a skill is active
     */
    public function is_skill_active($slug) {
        return in_array($slug, $this->active_skills);
    }
    
    /**
     * Set active skills
     */
    public function set_active_skills($slugs) {
        $this->active_skills = $slugs;
        update_option('swc_active_skills', $slugs);
    }
    
    /**
     * Get skill content for AI prompt
     */
    public function get_skill_content($slug) {
        return $this->skills[$slug]['content'] ?? '';
    }
    
    /**
     * Build combined instructions from active skills
     */
    public function build_skill_instructions() {
        $instructions = "";
        $activeSkills = $this->get_active_skills();
        
        error_log('[SWC Skills] ========== SKILL LOADING ==========');
        error_log('[SWC Skills] Active skills count: ' . count($activeSkills));
        
        foreach ($activeSkills as $slug => $skill) {
            error_log('[SWC Skills] ✅ Loaded: ' . $slug . ' (' . $skill['name'] . ')');
            $instructions .= "\n\n## {$skill['icon']} {$skill['name']}\n\n";
            $instructions .= $skill['content'];
        }
        
        if (empty($activeSkills)) {
            error_log('[SWC Skills] ⚠️ No active skills found for this agent');
        }
        
        error_log('[SWC Skills] Total instruction length: ' . strlen($instructions) . ' chars');
        error_log('[SWC Skills] ====================================');
        
        return $instructions;
    }
    
    /**
     * Get required tools for active skills
     */
    public function get_required_tools() {
        $tools = [];
        foreach ($this->get_active_skills() as $skill) {
            $tools = array_merge($tools, $skill['tools_required']);
        }
        return array_unique($tools);
    }
    
    /**
     * Add a custom skill from content
     */
    public function add_skill($content, $filename) {
        $filepath = $this->skills_dir . sanitize_file_name($filename);
        if (!str_ends_with($filepath, '.md')) {
            $filepath .= '.md';
        }
        
        file_put_contents($filepath, $content);
        
        // Reload skills
        $this->skills = [];
        $this->load_skills();
        
        return true;
    }
    
    /**
     * Delete a skill
     */
    public function delete_skill($slug) {
        if (isset($this->skills[$slug])) {
            $file = $this->skills_dir . $this->skills[$slug]['file'];
            if (file_exists($file)) {
                unlink($file);
            }
            unset($this->skills[$slug]);
            
            // Remove from active if it was active
            $this->active_skills = array_diff($this->active_skills, [$slug]);
            $this->set_active_skills($this->active_skills);
            
            return true;
        }
        return false;
    }
}
