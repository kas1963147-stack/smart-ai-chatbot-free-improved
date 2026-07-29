<?php
declare(strict_types=1);


/**
 * Instruction Section
 * 
 * Represents a single instruction section in a skill.
 * Supports bullets, numbered lists, and paragraph content.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * InstructionSection - Instruction block in a skill
 */
class InstructionSection
{
    const TYPE_BULLETS = 'bullets';
    const TYPE_NUMBERED = 'numbered';
    const TYPE_PARAGRAPH = 'paragraph';
    
    /** Unique identifier */
    public string $id;
    
    /** Section heading */
    public string $heading = '';
    
    /** Content type */
    public string $type = self::TYPE_BULLETS;
    
    /** List items (for bullets/numbered) */
    public array $items = [];
    
    /** Paragraph content */
    public string $content = '';
    
    public function __construct()
    {
        $this->id = uniqid('section-');
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        $section = new self();
        
        $section->id = $data['id'] ?? $section->id;
        $section->heading = $data['heading'] ?? '';
        $section->type = $data['type'] ?? self::TYPE_BULLETS;
        $section->items = $data['items'] ?? [];
        $section->content = $data['content'] ?? '';
        
        return $section;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'heading' => $this->heading,
            'type' => $this->type,
            'items' => $this->items,
            'content' => $this->content,
        ];
    }
    
    /**
     * Convert to markdown
     */
    public function toMarkdown(): string
    {
        $lines = [];
        
        if (!empty($this->heading)) {
            $lines[] = "## {$this->heading}";
        }
        
        switch ($this->type) {
            case self::TYPE_BULLETS:
                foreach ($this->items as $item) {
                    if (!empty(trim($item))) {
                        $lines[] = "- {$item}";
                    }
                }
                break;
                
            case self::TYPE_NUMBERED:
                $i = 1;
                foreach ($this->items as $item) {
                    if (!empty(trim($item))) {
                        $lines[] = "{$i}. {$item}";
                        $i++;
                    }
                }
                break;
                
            case self::TYPE_PARAGRAPH:
                if (!empty($this->content)) {
                    $lines[] = $this->content;
                }
                break;
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Parse instruction sections from markdown body
     */
    public static function parseFromMarkdown(string $markdown): array
    {
        $sections = [];
        $currentSection = null;
        
        $lines = explode("\n", $markdown);
        
        foreach ($lines as $line) {
            // Check for heading (## Section Title)
            if (preg_match('/^##\s+(.+)$/', $line, $matches)) {
                // Save previous section
                if ($currentSection !== null) {
                    $sections[] = $currentSection;
                }
                
                // Start new section
                $currentSection = new self();
                $currentSection->heading = trim($matches[1]);
                continue;
            }
            
            if ($currentSection === null) {
                continue;
            }
            
            // Check for bullet point
            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $currentSection->type = self::TYPE_BULLETS;
                $currentSection->items[] = trim($matches[1]);
            }
            // Check for numbered item
            elseif (preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                $currentSection->type = self::TYPE_NUMBERED;
                $currentSection->items[] = trim($matches[1]);
            }
            // Paragraph content
            elseif (!empty(trim($line))) {
                if ($currentSection->type === self::TYPE_PARAGRAPH) {
                    $currentSection->content .= trim($line) . "\n";
                } elseif (empty($currentSection->items)) {
                    $currentSection->type = self::TYPE_PARAGRAPH;
                    $currentSection->content = trim($line) . "\n";
                }
            }
        }
        
        // Save last section
        if ($currentSection !== null) {
            $currentSection->content = trim($currentSection->content);
            $sections[] = $currentSection;
        }
        
        return $sections;
    }
}
