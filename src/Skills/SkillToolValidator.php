<?php
declare(strict_types=1);


/**
 * Skill-Tool Validator
 * 
 * Validates that skills have access to required tools for an agent configuration.
 * Provides warnings when skills require tools that aren't assigned to the agent.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Config\ToolRegistry;

/**
 * SkillToolValidator - Validates skill-tool dependencies
 */
class SkillToolValidator
{
    /**
     * Validation result structure
     */
    public const RESULT_COMPATIBLE = 'compatible';
    public const RESULT_MISSING_TOOLS = 'missing_tools';
    public const RESULT_NO_REQUIREMENTS = 'no_requirements';
    
    /**
     * Validate all skills against agent's enabled tools
     * 
     * @param AgentConfig $config Agent configuration
     * @return array<string, array{skill: Skill, status: string, missing_tools: array, required_tools: array}>
     */
    public static function validateAll(AgentConfig $config): array
    {
        $skills = SkillRegistry::getSkillsForAgent($config);
        $enabledTools = self::getEnabledToolIds($config);
        
        $results = [];
        
        foreach ($skills as $skill) {
            $results[$skill->name] = self::validateSkill($skill, $enabledTools);
        }
        
        return $results;
    }
    
    /**
     * Validate a single skill against enabled tools
     * 
     * @param Skill $skill The skill to validate
     * @param array $enabledTools List of enabled tool IDs
     * @return array{skill: Skill, status: string, missing_tools: array, required_tools: array}
     */
    public static function validateSkill(Skill $skill, array $enabledTools): array
    {
        $requiredTools = $skill->toolsRequired;
        
        // No requirements = always compatible
        if (empty($requiredTools)) {
            return [
                'skill' => $skill,
                'status' => self::RESULT_NO_REQUIREMENTS,
                'missing_tools' => [],
                'required_tools' => [],
            ];
        }
        
        // Find missing tools
        $missingTools = array_diff($requiredTools, $enabledTools);
        
        if (empty($missingTools)) {
            return [
                'skill' => $skill,
                'status' => self::RESULT_COMPATIBLE,
                'missing_tools' => [],
                'required_tools' => $requiredTools,
            ];
        }
        
        return [
            'skill' => $skill,
            'status' => self::RESULT_MISSING_TOOLS,
            'missing_tools' => array_values($missingTools),
            'required_tools' => $requiredTools,
        ];
    }
    
    /**
     * Check if a skill is compatible with the given tools
     * 
     * @param Skill $skill The skill to check
     * @param array $enabledTools List of enabled tool IDs
     * @return bool True if compatible (or no requirements)
     */
    public static function isSkillCompatible(Skill $skill, array $enabledTools): bool
    {
        $result = self::validateSkill($skill, $enabledTools);
        return $result['status'] !== self::RESULT_MISSING_TOOLS;
    }
    
    /**
     * Get only skills with missing tool requirements
     * 
     * @param AgentConfig $config Agent configuration
     * @return array<string, array{skill: Skill, missing_tools: array}>
     */
    public static function getSkillsWithMissingTools(AgentConfig $config): array
    {
        $allResults = self::validateAll($config);
        
        return array_filter($allResults, function($result) {
            return $result['status'] === self::RESULT_MISSING_TOOLS;
        });
    }
    
    /**
     * Generate warning message for UI display
     * 
     * @param array $validationResults Results from validateAll()
     * @return string|null Warning message or null if no issues
     */
    public static function getWarningMessage(array $validationResults): ?string
    {
        $problemSkills = array_filter($validationResults, function($result) {
            return $result['status'] === self::RESULT_MISSING_TOOLS;
        });
        
        if (empty($problemSkills)) {
            return null;
        }
        
        $lines = ["️ The following skills require tools that are not enabled for this agent:\n"];
        
        foreach ($problemSkills as $skillName => $result) {
            $missing = implode(', ', $result['missing_tools']);
            $lines[] = sprintf("• **%s** requires: %s", $skillName, $missing);
        }
        
        $lines[] = "\nThese skills may not function correctly until the required tools are enabled.";
        
        return implode("\n", $lines);
    }
    
    /**
     * Generate HTML warning for admin panel
     * 
     * @param array $validationResults Results from validateAll()
     * @return string HTML warning or empty string
     */
    public static function getHtmlWarning(array $validationResults): string
    {
        $problemSkills = array_filter($validationResults, function($result) {
            return $result['status'] === self::RESULT_MISSING_TOOLS;
        });
        
        if (empty($problemSkills)) {
            return '';
        }
        
        $html = '<div class="notice notice-warning skill-tool-warning">';
        $html .= '<p><strong>️ Skill-Tool Compatibility Warning</strong></p>';
        $html .= '<p>The following skills require tools that are not enabled for this agent:</p>';
        $html .= '<ul>';
        
        foreach ($problemSkills as $skillName => $result) {
            $missing = esc_html(implode(', ', $result['missing_tools']));
            $html .= sprintf(
                '<li><strong>%s</strong> — missing: <code>%s</code></li>',
                esc_html($skillName),
                $missing
            );
        }
        
        $html .= '</ul>';
        $html .= '<p><em>These skills may not function correctly until the required tools are enabled.</em></p>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get summary statistics for validation results
     * 
     * @param array $validationResults Results from validateAll()
     * @return array{total: int, compatible: int, missing_tools: int, no_requirements: int}
     */
    public static function getSummary(array $validationResults): array
    {
        $summary = [
            'total' => count($validationResults),
            'compatible' => 0,
            'missing_tools' => 0,
            'no_requirements' => 0,
        ];
        
        foreach ($validationResults as $result) {
            switch ($result['status']) {
                case self::RESULT_COMPATIBLE:
                    $summary['compatible']++;
                    break;
                case self::RESULT_MISSING_TOOLS:
                    $summary['missing_tools']++;
                    break;
                case self::RESULT_NO_REQUIREMENTS:
                    $summary['no_requirements']++;
                    break;
            }
        }
        
        return $summary;
    }
    
    /**
     * Get list of all unique missing tools across all skills
     * 
     * @param array $validationResults Results from validateAll()
     * @return array List of unique missing tool IDs
     */
    public static function getAllMissingTools(array $validationResults): array
    {
        $allMissing = [];
        
        foreach ($validationResults as $result) {
            if ($result['status'] === self::RESULT_MISSING_TOOLS) {
                $allMissing = array_merge($allMissing, $result['missing_tools']);
            }
        }
        
        return array_unique($allMissing);
    }
    
    /**
     * Suggest tools to enable for full skill compatibility
     * 
     * @param AgentConfig $config Agent configuration
     * @return array List of tool IDs to enable
     */
    public static function getSuggestedTools(AgentConfig $config): array
    {
        $results = self::validateAll($config);
        return self::getAllMissingTools($results);
    }
    
    /**
     * Get enabled tool IDs from agent config
     * 
     * @param AgentConfig $config Agent configuration
     * @return array List of enabled tool IDs
     */
    protected static function getEnabledToolIds(AgentConfig $config): array
    {
        $enabledTools = [];
        
        // Get all tool IDs from enabled toolkits
        foreach (ToolRegistry::getToolkits() as $toolkitId => $toolkit) {
            if ($config->isToolkitEnabled($toolkitId)) {
                foreach ($toolkit['categories'] as $category) {
                    foreach ($category['tools'] as $toolId => $description) {
                        if ($config->isToolEnabled($toolId)) {
                            $enabledTools[] = $toolId;
                        }
                    }
                }
            }
        }
        
        return $enabledTools;
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
        $summary = self::getSummary($results);
        
        $skills = [];
        foreach ($results as $skillName => $result) {
            $skills[] = [
                'name' => $skillName,
                'description' => $result['skill']->description,
                'status' => $result['status'],
                'required_tools' => $result['required_tools'],
                'missing_tools' => $result['missing_tools'],
            ];
        }
        
        return [
            'summary' => $summary,
            'skills' => $skills,
            'has_warnings' => $summary['missing_tools'] > 0,
            'suggested_tools' => self::getAllMissingTools($results),
        ];
    }
}
