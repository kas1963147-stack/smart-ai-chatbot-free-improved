<?php
declare(strict_types=1);
/**
 * Hook Payload Base Class
 * 
 * Base class for typed hook payloads. Provides common functionality
 * for all hook data transfer objects.
 * 
 * @package Quarksol\SmartChatbot\Hooks
 */

namespace Quarksol\SmartChatbot\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Hook Payload
 * 
 * All hook payloads extend this class to provide consistent
 * structure and serialization.
 */
abstract class HookPayload implements \JsonSerializable
{

    /**
     * Timestamp when payload was created
     */
    public readonly float $timestamp;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->timestamp = microtime(true);
    }

    /**
     * Convert payload to array
     * 
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * JSON serialization
     * 
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge($this->toArray(), [
            'timestamp' => $this->timestamp,
            'payload_type' => static::class,
        ]);
    }

    /**
     * Get a specific property
     * 
     * @param string $key Property name
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->toArray()[$key] ?? $default;
    }

    /**
     * Check if a property exists
     * 
     * @param string $key Property name
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->toArray());
    }
}
