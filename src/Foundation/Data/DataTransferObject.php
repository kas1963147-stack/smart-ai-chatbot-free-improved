<?php
declare(strict_types=1);
/**
 * Data Transfer Object
 *
 * Lightweight DTO base for repositories and services.
 *
 * @package Quarksol\SmartChatbot\Foundation\Data
 */

namespace Quarksol\SmartChatbot\Foundation\Data;

if (!defined('ABSPATH')) {
    exit;
}

abstract class DataTransferObject
{
    /**
     * Populate DTO from array.
     */
    public static function fromArray(array $data): static
    {
        $dto = new static();
        $dto->fill($data);
        return $dto;
    }

    /**
     * Fill DTO properties from array.
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Export DTO as array.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
