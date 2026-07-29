<?php
declare(strict_types=1);
/**
 * Version Constraints
 *
 * Basic semver constraint matcher for module dependencies.
 *
 * @package Quarksol\SmartChatbot\Modules
 */

namespace Quarksol\SmartChatbot\Modules;

if (!defined('ABSPATH')) {
    exit;
}

final class VersionConstraints
{
    public static function satisfies(string $version, string $constraints): bool
    {
        $constraints = trim($constraints);
        if ($constraints === '' || $constraints === '*') {
            return true;
        }

        $parts = preg_split('/\s*,\s*/', $constraints) ?: [];
        foreach ($parts as $constraint) {
            if (!self::checkConstraint($version, $constraint)) {
                return false;
            }
        }

        return true;
    }

    private static function checkConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        if (str_starts_with($constraint, '^')) {
            return self::checkCaret($version, substr($constraint, 1));
        }

        if (str_starts_with($constraint, '~')) {
            return self::checkTilde($version, substr($constraint, 1));
        }

        if (preg_match('/^(>=|<=|>|<|==|=|!=)\s*(.+)$/', $constraint, $matches)) {
            $operator = $matches[1] === '=' ? '==' : $matches[1];
            return version_compare($version, $matches[2], $operator);
        }

        return version_compare($version, $constraint, '==');
    }

    private static function checkCaret(string $version, string $base): bool
    {
        $base = trim($base);
        if ($base === '') {
            return true;
        }

        $parts = array_map('intval', explode('.', $base));
        $major = $parts[0] ?? 0;
        $upper = ($major + 1) . '.0.0';

        return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
    }

    private static function checkTilde(string $version, string $base): bool
    {
        $base = trim($base);
        if ($base === '') {
            return true;
        }

        $parts = array_map('intval', explode('.', $base));
        $major = $parts[0] ?? 0;
        $minor = $parts[1] ?? 0;
        $upper = $major . '.' . ($minor + 1) . '.0';

        return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
    }
}
