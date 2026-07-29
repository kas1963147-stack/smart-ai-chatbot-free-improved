<?php
declare(strict_types=1);


/**
 * Load Skill Tool
 * 
 * Neuron AI tool that allows agents to load skill content on-demand.
 * This is the key to progressive disclosure - agents call this tool
 * when they detect a user request that matches a skill.
 * 
 * @package Quarksol\SmartChatbot\Skills
 */

namespace Quarksol\SmartChatbot\Skills;

if (!defined('ABSPATH')) {
    exit;
}

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;
use Quarksol\SmartChatbot\Services\AgentContext;

/**
 * Tool for loading skill content on-demand
 */
class LoadSkillTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'load_skill',
            description: 'Load detailed instructions for a skill when the user\'s request matches it. Use this to get full skill content before following specialized procedures.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'skill_name',
                type: PropertyType::STRING,
                description: 'Name of the skill to load (from the Available Skills list)',
                required: true
            ),
        ];
    }
    
    public function __invoke(string $skill_name): string
    {
        error_log('[SWC Skills] ========== SKILL LOAD REQUEST ==========');
        error_log('[SWC Skills] Requested skill: ' . $skill_name);
        
        $config = AgentContext::getConfig();
        $skills = SkillRegistry::getAll();
        $matchedSkill = null;
        
        foreach ($skills as $skill) {
            if (strtolower($skill->name) === strtolower($skill_name)) {
                $matchedSkill = $skill;
                break;
            }
        }
        
        if ($config && $matchedSkill && !$matchedSkill->alwaysOn && !$config->isSkillEnabled($matchedSkill->name)) {
            $available = array_keys(SkillRegistry::getSkillsForAgent($config));
            error_log('[SWC Skills]  BLOCKED - Skill not enabled for this agent: ' . $matchedSkill->name);
            error_log('[SWC Skills] Available: ' . implode(', ', $available));
            return json_encode([
                'error' => "Skill not enabled for this agent: {$matchedSkill->name}",
                'available_skills' => $available,
            ]);
        }
        
        $skill = SkillRegistry::loadSkill($matchedSkill?->name ?? $skill_name);
        
        if (!$skill) {
            $available = $config ? array_keys(SkillRegistry::getSkillsForAgent($config)) : array_keys(SkillRegistry::getAll());
            error_log('[SWC Skills]  NOT FOUND: ' . $skill_name);
            error_log('[SWC Skills] Available: ' . implode(', ', $available));
            return json_encode([
                'error' => "Skill not found: {$skill_name}",
                'available_skills' => $available,
            ]);
        }
        
        error_log('[SWC Skills]  LOADED: ' . $skill->name);
        error_log('[SWC Skills] Content length: ' . strlen($skill->getBody()) . ' chars');
        error_log('[SWC Skills] Has references: ' . ($skill->hasReferences() ? 'YES' : 'NO'));
        error_log('[SWC Skills] ============================================');
        
        $result = [
            'skill' => $skill->name,
            'instructions' => $skill->getBody(),
        ];
        
        // Include reference files list if available
        if ($skill->hasReferences()) {
            $result['available_references'] = $skill->listReferences();
            $result['note'] = 'Use load_skill_reference to load specific reference documents if needed.';
        }
        
        return json_encode($result);
    }
}
