<?php
declare(strict_types=1);


/**
 * Unified Skill Validator
 * 
 * Facade that combines tool validation and dependency validation
 * for comprehensive skill validation in a single call.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\AgentConfig;

/**
 * SkillValidator - Unified validation facade
 * 
 * Combines SkillToolValidator and SkillDependencyValidator
 * for production-ready skill validation.
 */
class SkillValidator
{
    /**
     * Overall validation status
     */
    public const STATUS_VALID = 'valid';
    public const STATUS_WARNINGS = 'warnings';
    public const STATUS_ERRORS = 'errors';
    
    /**
     * Perform comprehensive validation of all skills for an agent
     * 
     * @param AgentConfig $config Agent configuration
     * @return array Complete validation results
     */
    public static function validateAll(AgentConfig $config): array
    {
        $toolResults = SkillToolValidator::validateAll($config);
        $dependencyResults = SkillDependencyValidator::validateAll($config);
        
        // Combine results per skill
        $skills = [];
        $allSkillNames = array_unique(array_merge(
            array_keys($toolResults),
            array_keys($dependencyResults)
        ));
        
        $totalErrors = 0;
        $totalWarnings = 0;
        
        foreach ($allSkillNames as $skillName) {
            $toolResult = $toolResults[$skillName] ?? null;
            $depResult = $dependencyResults[$skillName] ?? null;
            
            $hasToolIssue = $toolResult && 
                $toolResult['status'] === SkillToolValidator::RESULT_MISSING_TOOLS;
            $hasDepIssue = $depResult && 
                $depResult['status'] !== SkillDependencyValidator::STATUS_OK;
            
            if ($hasToolIssue || $hasDepIssue) {
                $totalErrors++;
            }
            
            $skills[$skillName] = [
                'name' => $skillName,
                'tool_validation' => $toolResult,
                'dependency_validation' => $depResult,
                'has_issues' => $hasToolIssue || $hasDepIssue,
                'issues' => self::combineIssues($toolResult, $depResult),
            ];
        }
        
        // Calculate overall status
        $status = self::STATUS_VALID;
        if ($totalErrors > 0) {
            $status = self::STATUS_ERRORS;
        } elseif ($totalWarnings > 0) {
            $status = self::STATUS_WARNINGS;
        }
        
        return [
            'status' => $status,
            'summary' => [
                'total_skills' => count($skills),
                'skills_with_issues' => $totalErrors,
                'missing_tools' => SkillToolValidator::getAllMissingTools($toolResults),
                'suggested_skills' => SkillDependencyValidator::getSuggestedSkills($config),
            ],
            'skills' => $skills,
            'load_order' => SkillDependencyValidator::getLoadOrder(
                SkillRegistry::getSkillsForAgent($config)
            ),
        ];
    }
    
    /**
     * Quick check if configuration is valid
     * 
     * @param AgentConfig $config Agent configuration
     * @return bool True if no errors
     */
    public static function isValid(AgentConfig $config): bool
    {
        $results = self::validateAll($config);
        return $results['status'] === self::STATUS_VALID;
    }
    
    /**
     * Get combined warning message
     * 
     * @param AgentConfig $config Agent configuration
     * @return string|null Warning message or null
     */
    public static function getWarningMessage(AgentConfig $config): ?string
    {
        $results = self::validateAll($config);
        
        if ($results['status'] === self::STATUS_VALID) {
            return null;
        }
        
        $lines = [];
        
        foreach ($results['skills'] as $skillName => $skillResult) {
            if (!$skillResult['has_issues']) {
                continue;
            }
            
            foreach ($skillResult['issues'] as $issue) {
                $lines[] = sprintf("• **%s**: %s", $skillName, $issue['message']);
            }
        }
        
        if (empty($lines)) {
            return null;
        }
        
        array_unshift($lines, "️ Skill configuration issues:\n");
        
        return implode("\n", $lines);
    }
    
    /**
     * Get HTML warning for admin panel
     * 
     * @param AgentConfig $config Agent configuration
     * @return string HTML warning or empty
     */
    public static function getHtmlWarning(AgentConfig $config): string
    {
        $results = self::validateAll($config);
        
        if ($results['status'] === self::STATUS_VALID) {
            return '';
        }
        
        $html = '<div class="notice notice-warning skill-validation-warning">';
        $html .= '<p><strong>️ Skill Configuration Issues</strong></p>';
        $html .= '<ul>';
        
        foreach ($results['skills'] as $skillName => $skillResult) {
            if (!$skillResult['has_issues']) {
                continue;
            }
            
            foreach ($skillResult['issues'] as $issue) {
                $icon = match($issue['type']) {
                    'missing_tools' => '',
                    SkillDependencyValidator::STATUS_MISSING_REQUIRED => '',
                    SkillDependencyValidator::STATUS_CONFLICT => '',
                    SkillDependencyValidator::STATUS_CIRCULAR => '',
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
        
        $html .= '</ul>';
        
        // Add suggestions
        if (!empty($results['summary']['missing_tools'])) {
            $html .= '<p><strong>Suggested tools to enable:</strong> <code>';
            $html .= esc_html(implode('</code>, <code>', $results['summary']['missing_tools']));
            $html .= '</code></p>';
        }
        
        if (!empty($results['summary']['suggested_skills'])) {
            $html .= '<p><strong>Suggested skills:</strong> ';
            $html .= esc_html(implode(', ', $results['summary']['suggested_skills']));
            $html .= '</p>';
        }
        
        $html .= '</div>';
        
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
        
        // Simplify for API
        $skills = [];
        foreach ($results['skills'] as $skillName => $skillResult) {
            $skills[] = [
                'name' => $skillName,
                'valid' => !$skillResult['has_issues'],
                'issues' => array_map(fn($i) => [
                    'type' => $i['type'],
                    'message' => $i['message'],
                    'details' => $i['details'] ?? [],
                ], $skillResult['issues']),
            ];
        }
        
        return [
            'valid' => $results['status'] === self::STATUS_VALID,
            'status' => $results['status'],
            'summary' => $results['summary'],
            'skills' => $skills,
            'load_order' => $results['load_order'],
        ];
    }
    
    /**
     * Combine issues from tool and dependency validation
     */
    protected static function combineIssues(?array $toolResult, ?array $depResult): array
    {
        $issues = [];
        
        // Tool issues
        if ($toolResult && $toolResult['status'] === SkillToolValidator::RESULT_MISSING_TOOLS) {
            $issues[] = [
                'type' => 'missing_tools',
                'message' => sprintf(
                    'Missing required tools: %s',
                    implode(', ', $toolResult['missing_tools'])
                ),
                'details' => [
                    'required' => $toolResult['required_tools'],
                    'missing' => $toolResult['missing_tools'],
                ],
            ];
        }
        
        // Dependency issues
        if ($depResult && !empty($depResult['issues'])) {
            foreach ($depResult['issues'] as $issue) {
                $issues[] = [
                    'type' => $issue['type'],
                    'message' => $issue['message'],
                    'details' => [
                        'skills' => $issue['skills'],
                    ],
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Get skills that can be safely enabled (no missing dependencies)
     * 
     * @param AgentConfig $config Agent configuration
     * @param array $candidateSkills Skills to check
     * @return array Skills that can be safely added
     */
    public static function getSafeToEnable(AgentConfig $config, array $candidateSkills): array
    {
        $enabledSkills = SkillRegistry::getSkillsForAgent($config);
        $enabledNames = array_keys($enabledSkills);
        $allSkills = SkillRegistry::getAll();
        
        $safe = [];
        
        foreach ($candidateSkills as $skillName) {
            if (!isset($allSkills[$skillName])) {
                continue;
            }
            
            $skill = $allSkills[$skillName];
            
            // Check if requirements are met
            $requirementsMet = true;
            foreach ($skill->requires as $required) {
                if (!in_array($required, $enabledNames, true)) {
                    $requirementsMet = false;
                    break;
                }
            }
            
            // Check for conflicts
            $hasConflicts = false;
            foreach ($skill->conflicts as $conflict) {
                if (in_array($conflict, $enabledNames, true)) {
                    $hasConflicts = true;
                    break;
                }
            }
            
            if ($requirementsMet && !$hasConflicts) {
                $safe[] = $skillName;
            }
        }
        
        return $safe;
    }
}
