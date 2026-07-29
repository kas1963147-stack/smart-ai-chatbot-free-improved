<?php
declare(strict_types=1);


/**
 * Skill Dependency Validator
 * 
 * Validates skill dependency chains: requires, suggests, conflicts.
 * Ensures proper skill loading order and detects circular dependencies.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\AgentConfig;

/**
 * SkillDependencyValidator - Validates skill inter-dependencies
 */
class SkillDependencyValidator
{
    /**
     * Validation status constants
     */
    public const STATUS_OK = 'ok';
    public const STATUS_MISSING_REQUIRED = 'missing_required';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_CIRCULAR = 'circular_dependency';
    
    /**
     * Validate all enabled skills for an agent
     * 
     * @param AgentConfig $config Agent configuration
     * @return array Validation results
     */
    public static function validateAll(AgentConfig $config): array
    {
        $enabledSkills = SkillRegistry::getSkillsForAgent($config);
        $enabledNames = array_keys($enabledSkills);
        
        $results = [];
        
        foreach ($enabledSkills as $skillName => $skill) {
            $results[$skillName] = self::validateSkill($skill, $enabledNames, $enabledSkills);
        }
        
        return $results;
    }
    
    /**
     * Validate a single skill's dependencies
     * 
     * @param Skill $skill The skill to validate
     * @param array $enabledSkillNames List of enabled skill names
     * @param array $allSkills All skill instances for circular check
     * @return array Validation result
     */
    public static function validateSkill(Skill $skill, array $enabledSkillNames, array $allSkills = []): array
    {
        $issues = [];
        
        // Check required skills
        $missingRequired = [];
        foreach ($skill->requires as $requiredSkill) {
            if (!in_array($requiredSkill, $enabledSkillNames, true)) {
                $missingRequired[] = $requiredSkill;
            }
        }
        
        if (!empty($missingRequired)) {
            $issues[] = [
                'type' => self::STATUS_MISSING_REQUIRED,
                'skills' => $missingRequired,
                'message' => sprintf(
                    'Missing required skills: %s',
                    implode(', ', $missingRequired)
                ),
            ];
        }
        
        // Check conflicts
        $activeConflicts = [];
        foreach ($skill->conflicts as $conflictSkill) {
            if (in_array($conflictSkill, $enabledSkillNames, true)) {
                $activeConflicts[] = $conflictSkill;
            }
        }
        
        if (!empty($activeConflicts)) {
            $issues[] = [
                'type' => self::STATUS_CONFLICT,
                'skills' => $activeConflicts,
                'message' => sprintf(
                    'Conflicts with enabled skills: %s',
                    implode(', ', $activeConflicts)
                ),
            ];
        }
        
        // Check for circular dependencies
        if (!empty($allSkills)) {
            $circular = self::detectCircularDependency($skill, $allSkills);
            if (!empty($circular)) {
                $issues[] = [
                    'type' => self::STATUS_CIRCULAR,
                    'skills' => $circular,
                    'message' => sprintf(
                        'Circular dependency detected: %s',
                        implode(' → ', $circular)
                    ),
                ];
            }
        }
        
        return [
            'skill' => $skill,
            'status' => empty($issues) ? self::STATUS_OK : $issues[0]['type'],
            'issues' => $issues,
            'requires' => $skill->requires,
            'suggests' => $skill->suggests,
            'conflicts' => $skill->conflicts,
            'missing_required' => $missingRequired,
            'active_conflicts' => $activeConflicts,
        ];
    }
    
    /**
     * Detect circular dependency chain
     * 
     * @param Skill $skill Starting skill
     * @param array $allSkills All available skills
     * @param array $visited Already visited skills (for recursion)
     * @return array Circular chain if detected, empty otherwise
     */
    protected static function detectCircularDependency(
        Skill $skill, 
        array $allSkills, 
        array $visited = []
    ): array {
        if (in_array($skill->name, $visited, true)) {
            $visited[] = $skill->name;
            return $visited;
        }
        
        $visited[] = $skill->name;
        
        foreach ($skill->requires as $requiredName) {
            if (isset($allSkills[$requiredName])) {
                $result = self::detectCircularDependency(
                    $allSkills[$requiredName], 
                    $allSkills, 
                    $visited
                );
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        
        return [];
    }
    
    /**
     * Get skills with any dependency issues
     * 
     * @param AgentConfig $config Agent configuration
     * @return array Skills with issues
     */
    public static function getSkillsWithIssues(AgentConfig $config): array
    {
        $results = self::validateAll($config);
        
        return array_filter($results, function($result) {
            return $result['status'] !== self::STATUS_OK;
        });
    }
    
    /**
     * Get the proper load order for skills based on dependencies
     * 
     * @param array $skills Skills to sort
     * @return array Sorted skill names in load order
     */
    public static function getLoadOrder(array $skills): array
    {
        $order = [];
        $remaining = array_keys($skills);
        $resolved = [];
        
        $maxIterations = count($skills) * count($skills); // Prevent infinite loops
        $iterations = 0;
        
        while (!empty($remaining) && $iterations < $maxIterations) {
            $iterations++;
            
            foreach ($remaining as $key => $skillName) {
                $skill = $skills[$skillName];
                
                // Check if all requirements are resolved
                $canLoad = true;
                foreach ($skill->requires as $required) {
                    if (!in_array($required, $resolved, true)) {
                        $canLoad = false;
                        break;
                    }
                }
                
                if ($canLoad) {
                    $order[] = $skillName;
                    $resolved[] = $skillName;
                    unset($remaining[$key]);
                }
            }
            
            $remaining = array_values($remaining);
        }
        
        // Add any remaining (circular or unresolved) at the end
        foreach ($remaining as $skillName) {
            $order[] = $skillName;
        }
        
        return $order;
    }
    
    /**
     * Get suggested skills that aren't enabled
     * 
     * @param AgentConfig $config Agent configuration
     * @return array Suggested skill names not currently enabled
     */
    public static function getSuggestedSkills(AgentConfig $config): array
    {
        $enabledSkills = SkillRegistry::getSkillsForAgent($config);
        $enabledNames = array_keys($enabledSkills);
        
        $suggested = [];
        
        foreach ($enabledSkills as $skill) {
            foreach ($skill->suggests as $suggestedName) {
                if (!in_array($suggestedName, $enabledNames, true) && 
                    !in_array($suggestedName, $suggested, true)) {
                    $suggested[] = $suggestedName;
                }
            }
        }
        
        return $suggested;
    }
    
    /**
     * Generate warning message for dependency issues
     * 
     * @param array $results Validation results
     * @return string|null Warning message or null
     */
    public static function getWarningMessage(array $results): ?string
    {
        $issues = array_filter($results, fn($r) => $r['status'] !== self::STATUS_OK);
        
        if (empty($issues)) {
            return null;
        }
        
        $lines = ["️ Skill dependency issues detected:\n"];
        
        foreach ($issues as $skillName => $result) {
            foreach ($result['issues'] as $issue) {
                $lines[] = sprintf("• **%s**: %s", $skillName, $issue['message']);
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Generate HTML warning for admin panel
     * 
     * @param array $results Validation results
     * @return string HTML or empty
     */
    public static function getHtmlWarning(array $results): string
    {
        $issues = array_filter($results, fn($r) => $r['status'] !== self::STATUS_OK);
        
        if (empty($issues)) {
            return '';
        }
        
        $html = '<div class="notice notice-warning skill-dependency-warning">';
        $html .= '<p><strong>️ Skill Dependency Issues</strong></p>';
        $html .= '<ul>';
        
        foreach ($issues as $skillName => $result) {
            foreach ($result['issues'] as $issue) {
                $icon = match($issue['type']) {
                    self::STATUS_MISSING_REQUIRED => '',
                    self::STATUS_CONFLICT => '',
                    self::STATUS_CIRCULAR => '',
                    default => '️',
                };
                $html .= sprintf(
                    '<li>%s <strong>%s</strong>: %s</li>',
                    $icon,
                    esc_html($skillName),
                    esc_html($issue['message'])
                );
            }
        }
        
        $html .= '</ul></div>';
        
        return $html;
    }
    
    /**
     * Validate for REST API response
     * 
     * @param AgentConfig $config Agent configuration
     * @return array API-friendly response
     */
    public static function validateForApi(AgentConfig $config): array
    {
        $results = self::validateAll($config);
        
        $skillsWithIssues = [];
        $totalIssues = 0;
        
        foreach ($results as $skillName => $result) {
            if ($result['status'] !== self::STATUS_OK) {
                $totalIssues += count($result['issues']);
                $skillsWithIssues[] = [
                    'name' => $skillName,
                    'status' => $result['status'],
                    'issues' => $result['issues'],
                ];
            }
        }
        
        return [
            'valid' => $totalIssues === 0,
            'total_issues' => $totalIssues,
            'skills_with_issues' => $skillsWithIssues,
            'suggested_skills' => self::getSuggestedSkills($config),
            'load_order' => self::getLoadOrder(SkillRegistry::getSkillsForAgent($config)),
        ];
    }
}
