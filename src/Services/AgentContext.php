<?php
declare(strict_types=1);


/**
 * Agent Context
 *
 * Stores the current agent configuration for tool/skill/document enforcement.
 * This avoids coupling to the NeuronAI framework while enabling per-agent control.
 *
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Config\AgentConfig;

class AgentContext
{
    private static ?AgentConfig $config = null;
    private static ?int $agentDbId = null;
    private static ?string $agentSlug = null;
    private static ?string $sessionId = null;

    public static function set(?AgentConfig $config, ?int $agentDbId = null, ?string $agentSlug = null, ?string $sessionId = null): void
    {
        self::$config = $config;
        self::$agentDbId = $agentDbId;
        self::$agentSlug = $agentSlug;
        if ($sessionId !== null) {
            self::$sessionId = $sessionId;
        }
    }

    public static function clear(): void
    {
        self::$config = null;
        self::$agentDbId = null;
        self::$agentSlug = null;
        self::$sessionId = null;
    }

    public static function getConfig(): ?AgentConfig
    {
        return self::$config;
    }

    public static function getAgentDbId(): ?int
    {
        return self::$agentDbId;
    }

    public static function getAgentSlug(): ?string
    {
        return self::$agentSlug;
    }

    public static function getSessionId(): ?string
    {
        return self::$sessionId;
    }

    /**
     * Set session ID separately (for callers that set context before knowing the session).
     */
    public static function setSessionId(?string $sessionId): void
    {
        self::$sessionId = $sessionId;
    }

    /**
     * Get allowed document sections for current agent.
     *
     * @return array|null Null means unrestricted; empty array means none.
     */
    public static function getAllowedSections(): ?array
    {
        if (!self::$config) {
            return null;
        }

        if (!self::$config->sectionsConfigured) {
            return null;
        }

        return array_values(self::$config->enabledSections ?? []);
    }

    public static function isKnowledgeEnabled(): bool
    {
        if (!self::$config) {
            return true;
        }

        return (bool) self::$config->knowledgeEnabled;
    }

    /**
     * Get allowed knowledge source IDs for current agent.
     *
     * @return array|null Null means unrestricted; empty array means none.
     */
    public static function getAllowedKnowledgeSources(): ?array
    {
        if (!self::$config) {
            return null;
        }

        if (!self::$config->knowledgeSourcesConfigured) {
            return null;
        }

        return array_values(self::$config->enabledKnowledgeSources ?? []);
    }
}
