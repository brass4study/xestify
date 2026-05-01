<?php

declare(strict_types=1);

/**
 * Helpers compartidos para todos los tests standalone.
 * Sin dependencias externas. Sin PHPUnit.
 *
 * Uso: require_once __DIR__ . '/helpers.php';
 */

// ---------------------------------------------------------------------------
// Estado global encapsulado en clase estática (evita global $passed, $failed)
// ---------------------------------------------------------------------------

final class TestSuite
{
    private static int $passed = 0;
    private static int $failed = 0;

    public static function run(string $label, callable $fn): void
    {
        try {
            $fn();
            echo "  ✅ {$label}\n";
            self::$passed++;
        } catch (Throwable $e) {
            echo "  ❌ {$label}\n     → {$e->getMessage()}\n";
            self::$failed++;
        }
    }

    public static function summary(): void
    {
        echo str_repeat('-', 40) . "\n";
        echo 'Resultado: ' . self::$passed . ' passed, ' . self::$failed . " failed\n\n";
    }

    public static function exitCode(): int
    {
        return self::$failed > 0 ? 1 : 0;
    }
}

// ---------------------------------------------------------------------------
// Aserciones
// ---------------------------------------------------------------------------

function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new \AssertionError(
            $msg ?: 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrue(mixed $value, string $msg = 'Expected true'): void
{
    if ($value !== true) {
        throw new \AssertionError($msg);
    }
}

function assertFalse(mixed $value, string $msg = 'Expected false'): void
{
    if ($value !== false) {
        throw new \AssertionError($msg);
    }
}

function assertNull(mixed $value, string $msg = 'Expected null'): void
{
    if ($value !== null) {
        throw new \AssertionError($msg);
    }
}
