<?php
declare(strict_types=1);


/**
 * Load Skill Reference Tool
 * 
 * Neuron AI tool for loading reference documents within a skill.
 * Used for detailed documentation like policies, procedures, etc.
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
 * Tool for loading skill reference documents
 */
class LoadSkillReferenceTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'load_skill_reference',
            description: 'Load a reference document from a skill (e.g., detailed policy, procedure guide). Only use after loading the main skill.'
        );
    }
    
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'skill_name',
                type: PropertyType::STRING,
                description: 'Name of the skill containing the reference',
                required: true
            ),
            new ToolProperty(
                name: 'reference_file',
                type: PropertyType::STRING,
                description: 'Name of the reference file to load',
                required: true
            ),
        ];
    }
    
    public function __invoke(string $skill_name, string $reference_file): string
    {
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
            return json_encode([
                'error' => "Skill not enabled for this agent: {$matchedSkill->name}",
                'available_skills' => $available,
            ]);
        }
        
        $skill = $matchedSkill ?: SkillRegistry::getSkill($skill_name);
        
        if (!$skill) {
            $available = $config ? array_keys(SkillRegistry::getSkillsForAgent($config)) : array_keys(SkillRegistry::getAll());
            return json_encode([
                'error' => "Skill not found: {$skill_name}",
                'available_skills' => $available,
            ]);
        }
        
        $content = $skill->getReference($reference_file);
        
        if ($content === null) {
            $available = $skill->listReferences();
            if (empty($available)) {
                return json_encode([
                    'error' => "This skill does not have any reference documents.",
                    'instruction' => 'STOP calling load_skill_reference for this skill. Proceed with the information you have.',
                    'available_references' => []
                ]);
            }
            return json_encode([
                'error' => "Reference not found: {$reference_file}",
                'available_references' => $available,
                'instruction' => 'You MUST pick a reference file exactly from the available_references list. Do not make up names.'
            ]);
        }
        
        return json_encode([
            'skill' => $skill_name,
            'reference' => $reference_file,
            'content' => $content,
        ]);
    }
}
