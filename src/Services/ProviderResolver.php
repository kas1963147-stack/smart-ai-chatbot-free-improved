<?php
declare(strict_types=1);


/**
 * Provider Resolver
 * 
 * Resolves the correct AI provider for a given agent.
 * Resolution order:
 *   1. Agent-specific provider instance (if set in agent config)
 *   2. Default provider instance (if marked as default in Provider Hub)
 *   3. Fallback to legacy global settings (backward compatibility)
 * 
 * @package Quarksol\SmartChatbot\Services
 */

namespace Quarksol\SmartChatbot\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Bridge\ProviderBridge;
use Quarksol\SmartChatbot\Api\Providers\BaseProvider;
use Quarksol\SmartChatbot\Config\AgentConfig;
use Quarksol\SmartChatbot\Config\ChatbotConfig;

class ProviderResolver
{
    /** WordPress option key for provider instances */
    const OPTION_KEY = 'swc_provider_instances';
    const CREDENTIAL_GROUP_PREFIX = 'credential_';

    /**
     * Resolve the provider for a specific agent
     * 
     * @param AgentConfig|null $agentConfig Agent config (may contain provider_instance_id)
     * @return BaseProvider The resolved provider
     */
    public static function resolveForAgent(?AgentConfig $agentConfig = null): BaseProvider
    {
        // 1. Check agent-specific provider instance
        if ($agentConfig) {
            $instanceId = $agentConfig->providerInstanceId ?? null;
            
            Logger::info('ProviderResolver: Resolving for agent', [
                'agent_id' => $agentConfig->agentId,
                'requested_instance_id' => $instanceId ?? 'NULL',
            ]);
            error_log("[ProviderResolver] Resolving for agent '{$agentConfig->agentId}', instance_id = '" . ($instanceId ?? 'NULL') . "'");
            
            if (!empty($instanceId)) {
                $instance = self::getInstance($instanceId);
                if ($instance) {
                    Logger::info('ProviderResolver: Using agent-specific provider instance', [
                        'agent_id' => $agentConfig->agentId,
                        'instance_id' => $instanceId,
                        'provider' => $instance['provider'] ?? 'unknown',
                        'display_name' => $instance['display_name'] ?? 'unknown',
                    ]);
                    error_log("[ProviderResolver]  Using AGENT-SPECIFIC instance '{$instanceId}' → provider: " . ($instance['provider'] ?? 'unknown') . ", name: " . ($instance['display_name'] ?? ''));
                    return self::createFromInstance($instance);
                }
            }
        }

        // 2. Check for default provider instance
        $defaultInstance = self::getDefaultInstance();
        if ($defaultInstance) {
            Logger::debug('ProviderResolver: Using default provider instance', [
                'instance_id' => $defaultInstance['id'] ?? 'unknown',
                'provider' => $defaultInstance['provider'] ?? 'unknown',
            ]);
            error_log("[ProviderResolver]  Using DEFAULT instance → provider: " . ($defaultInstance['provider'] ?? 'unknown'));
            return self::createFromInstance($defaultInstance);
        }

        // 3. Fall back to legacy global provider (backward compatibility)
        Logger::debug('ProviderResolver: No Provider Hub instance found, falling back to legacy global settings', [
            'provider' => ChatbotConfig::settings()['ai_provider'] ?? 'unknown',
        ]);
        error_log("[ProviderResolver]  Falling back to LEGACY GLOBAL settings → provider: " . (ChatbotConfig::settings()['ai_provider'] ?? 'unknown'));
        return ProviderBridge::fromSettings();
    }

    /**
     * Create a BaseProvider from a provider instance configuration
     */
    public static function createFromInstance(array $instance): BaseProvider
    {
        return ProviderBridge::fromOptions([
            'provider' => $instance['provider'] ?? '',
            'api_key' => $instance['api_key'] ?? '',
            'model' => $instance['model'] ?? '',
            'base_url' => $instance['base_url'] ?? '',
            'extra' => $instance['extra'] ?? [],
        ]);
    }

    /**
     * Get all provider instances
     * 
     * @return array[] List of provider instance configurations
     */
    public static function getAllInstances(): array
    {
        $instances = get_option(self::OPTION_KEY, []);
        return is_array($instances) ? $instances : [];
    }

    /**
     * Ensure every stored provider instance has a credential group.
     *
     * Older installs duplicated the same API key/base URL onto each model
     * instance. This groups those legacy rows by identical provider credentials
     * so one API edit can update every model that uses that credential set.
     */
    public static function ensureCredentialGroups(): array
    {
        $instances = self::getAllInstances();
        $changed = false;
        $signatureToGroup = [];

        foreach ($instances as $instance) {
            $groupId = $instance['credential_group_id'] ?? '';
            if (empty($groupId)) {
                continue;
            }

            $signature = self::credentialSignature($instance);
            if (!isset($signatureToGroup[$signature])) {
                $signatureToGroup[$signature] = $groupId;
            }
        }

        foreach ($instances as $index => $instance) {
            if (!empty($instance['credential_group_id'])) {
                continue;
            }

            $signature = self::credentialSignature($instance);
            if (!isset($signatureToGroup[$signature])) {
                $signatureToGroup[$signature] = self::newCredentialGroupId();
            }

            $instances[$index]['credential_group_id'] = $signatureToGroup[$signature];
            $changed = true;
        }

        if ($changed) {
            update_option(self::OPTION_KEY, $instances);
        }

        return $instances;
    }

    /**
     * Get a specific provider instance by ID
     */
    public static function getInstance(string $id): ?array
    {
        $instances = self::getAllInstances();
        
        foreach ($instances as $instance) {
            if (($instance['id'] ?? '') === $id) {
                return $instance;
            }
        }
        
        return null;
    }

    /**
     * Get the default provider instance
     */
    public static function getDefaultInstance(): ?array
    {
        $instances = self::getAllInstances();
        
        foreach ($instances as $instance) {
            if (!empty($instance['is_default'])) {
                return $instance;
            }
        }
        
        return null;
    }

    /**
     * Save a provider instance (create or update)
     * 
     * @param array $instanceData Instance configuration
     * @return string The instance ID
     */
    public static function saveInstance(array $instanceData): string
    {
        $instances = self::getAllInstances();
        $id = !empty($instanceData['id']) ? $instanceData['id'] : ('provider_' . wp_generate_uuid4());
        $instanceData['id'] = $id;
        $instanceData['updated_at'] = current_time('mysql');

        $existingInstance = null;
        foreach ($instances as $existing) {
            if (($existing['id'] ?? '') === $id) {
                $existingInstance = $existing;
                break;
            }
        }

        if (empty($instanceData['credential_group_id'])) {
            $instanceData['credential_group_id'] = $existingInstance['credential_group_id'] ?? self::newCredentialGroupId();
        }

        // If marking as default, unset other defaults
        if (!empty($instanceData['is_default'])) {
            foreach ($instances as &$existing) {
                $existing['is_default'] = false;
            }
            unset($existing);
        }

        // Find and update existing, or append new
        $found = false;
        foreach ($instances as $index => $existing) {
            if (($existing['id'] ?? '') === $id) {
                $instances[$index] = array_merge($existing, $instanceData);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $instanceData['created_at'] = current_time('mysql');
            $instances[] = $instanceData;
        }

        update_option(self::OPTION_KEY, $instances);
        
        return $id;
    }

    /**
     * Update API credentials for every model instance in a credential group.
     *
     * @return array{updated:int,instances:array}
     */
    public static function updateCredentialGroup(string $provider, string $groupId, ?string $apiKey, ?string $baseUrl): array
    {
        $instances = self::ensureCredentialGroups();
        $updated = 0;

        foreach ($instances as $index => $instance) {
            if (($instance['provider'] ?? '') !== $provider) {
                continue;
            }

            if (($instance['credential_group_id'] ?? '') !== $groupId) {
                continue;
            }

            if ($apiKey !== null && $apiKey !== '') {
                $instances[$index]['api_key'] = $apiKey;
            }

            if ($baseUrl !== null) {
                $instances[$index]['base_url'] = $baseUrl;
            }

            $instances[$index]['updated_at'] = current_time('mysql');
            $updated++;
        }

        if ($updated > 0) {
            update_option(self::OPTION_KEY, $instances);
        }

        return [
            'updated' => $updated,
            'instances' => $instances,
        ];
    }

    /**
     * Delete a provider instance
     */
    public static function deleteInstance(string $id): bool
    {
        $instances = self::getAllInstances();
        $filtered = array_filter($instances, fn($inst) => ($inst['id'] ?? '') !== $id);
        
        if (count($filtered) === count($instances)) {
            return false; // Not found
        }

        update_option(self::OPTION_KEY, array_values($filtered));
        return true;
    }

    /**
     * Test a provider instance connection
     * 
     * @param array $config Provider config to test
     * @return array Test result ['success' => bool, 'message' => string, 'response' => string]
     */
    public static function testInstance(array $config): array
    {
        try {
            $provider = ProviderBridge::fromOptions([
                'provider' => $config['provider'] ?? '',
                'api_key' => $config['api_key'] ?? '',
                'model' => $config['model'] ?? '',
                'base_url' => $config['base_url'] ?? '',
                'extra' => $config['extra'] ?? [],
            ]);

            $response = $provider->completePrompt('Say "Hello, I am working!" in 5 words or less.');
            
            return [
                'success' => !empty($response),
                'message' => !empty($response) ? 'Connection successful' : 'Empty response',
                'response' => $response,
                'model' => $provider->getModel(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response' => '',
            ];
        }
    }

    /** WordPress option key for migration flag */
    const MIGRATION_FLAG = 'swc_provider_migration_done';

    /**
     * Migrate legacy Settings-based provider config to a Provider Hub instance.
     * 
     * Called once on plugin load. If the user has a provider configured in
     * swc_chatbot_settings but no Provider Hub instances exist yet, this
     * creates a Provider Hub instance from those legacy settings.
     */
    public static function migrateFromLegacySettings(): void
    {
        // Skip if already migrated
        if (get_option(self::MIGRATION_FLAG, false)) {
            return;
        }

        $instances = self::getAllInstances();

        // Only migrate if no Provider Hub instances exist
        if (!empty($instances)) {
            update_option(self::MIGRATION_FLAG, true);
            return;
        }

        $settings = ChatbotConfig::settings();
        $activeProvider = $settings['ai_provider'] ?? '';
        $apiKey = $settings['ai_api_key'] ?? '';

        // Check provider_configs first, then flat settings
        $providerConfigs = $settings['provider_configs'] ?? [];
        $activeConfig = $providerConfigs[$activeProvider] ?? [];
        
        // Use per-provider config if available, otherwise flat settings
        $finalApiKey = $activeConfig['api_key'] ?? $apiKey;
        $finalModel = $activeConfig['model'] ?? ($settings['ai_model'] ?? '');
        $finalBaseUrl = $activeConfig['base_url'] ?? ($settings['ai_base_url'] ?? '');

        if (empty($activeProvider) || empty($finalApiKey)) {
            // Nothing to migrate
            update_option(self::MIGRATION_FLAG, true);
            return;
        }

        $providerName = ucfirst(str_replace(['_', '-'], ' ', $activeProvider));
        $displayName = $providerName;
        if (!empty($finalModel)) {
            $displayName .= ' (' . $finalModel . ')';
        }

        $instanceData = [
            'display_name' => $displayName,
            'provider'     => $activeProvider,
            'api_key'      => $finalApiKey,
            'model'        => $finalModel,
            'base_url'     => $finalBaseUrl,
            'is_default'   => true,
            'extra'        => $activeConfig['extra'] ?? [],
        ];

        self::saveInstance($instanceData);
        update_option(self::MIGRATION_FLAG, true);

        error_log("[ProviderResolver]  Migrated legacy settings to Provider Hub instance: {$displayName}");
    }

    /**
     * Get provider instances formatted for dropdown selection
     * 
     * @return array [['id' => ..., 'label' => ...], ...]
     */
    public static function getInstancesForDropdown(): array
    {
        $instances = self::ensureCredentialGroups();
        $options = [
            ['id' => '', 'label' => '-- Use Default Provider --'],
        ];

        foreach ($instances as $instance) {
            $label = $instance['display_name'] ?? $instance['provider'] ?? 'Unknown';
            // Clean up legacy "(Global Settings)" suffix
            $label = str_replace(' (Global Settings)', '', $label);
            if (!empty($instance['model'])) {
                $label .= ' (' . $instance['model'] . ')';
            }
            $credentialLabel = self::credentialLabel($instance);
            if (!empty($credentialLabel)) {
                $label .= ' - ' . $credentialLabel;
            }
            if (!empty($instance['is_default'])) {
                $label .= ' [Default]';
            }
            
            $options[] = [
                'id' => $instance['id'],
                'label' => $label,
            ];
        }

        return $options;
    }

    public static function maskApiKey(string $key): string
    {
        if ($key === '') {
            return '';
        }

        return strlen($key) > 8 ? substr($key, 0, 4) . '...' . substr($key, -4) : '***';
    }

    private static function credentialSignature(array $instance): string
    {
        $provider = strtolower((string) ($instance['provider'] ?? ''));
        $apiKey = (string) ($instance['api_key'] ?? '');
        $baseUrl = rtrim((string) ($instance['base_url'] ?? ''), '/');

        return hash('sha256', $provider . '|' . $apiKey . '|' . $baseUrl);
    }

    private static function newCredentialGroupId(): string
    {
        return self::CREDENTIAL_GROUP_PREFIX . wp_generate_uuid4();
    }

    private static function credentialLabel(array $instance): string
    {
        if (!empty($instance['api_key'])) {
            return 'Key ' . self::maskApiKey((string) $instance['api_key']);
        }

        if (!empty($instance['base_url'])) {
            $host = parse_url((string) $instance['base_url'], PHP_URL_HOST);
            return $host ? 'Endpoint ' . $host : 'Custom endpoint';
        }

        return '';
    }
}
