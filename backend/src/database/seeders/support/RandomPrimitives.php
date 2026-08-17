<?php

declare(strict_types=1);

namespace Xestify\database\seeders\support;

/**
 * Low-level pseudo-random helpers shared by the STORY 10.6 data generators.
 * `mt_rand` is intentional here: this is demo/seed data generation, not a
 * security-sensitive context (no tokens, no secrets).
 */
final class RandomPrimitives
{
    private function __construct()
    {
        // Clase estatica, instanciacion no permitida.
    }

    public static function intBetween(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public static function floatBetween(float $min, float $max, int $decimals = 2): float
    {
        $factor = 10 ** $decimals;
        $scaledMin = (int) round($min * $factor);
        $scaledMax = (int) round($max * $factor);

        return mt_rand($scaledMin, $scaledMax) / $factor;
    }

    /**
     * Genera un valor dentro de [min, max] redondeado al multiplo de $step mas
     * cercano — simula la granularidad real de una medida optica (p.ej. 0.25 D).
     */
    public static function steppedFloatBetween(float $min, float $max, float $step): float
    {
        $stepsCount = (int) round(($max - $min) / $step);
        $stepIndex = self::intBetween(0, $stepsCount);

        return round($min + ($stepIndex * $step), 2);
    }

    public static function boolWithProbability(int $percent): bool
    {
        return self::intBetween(1, 100) <= $percent;
    }

    /**
     * @param list<mixed> $pool
     */
    public static function pick(array $pool): mixed
    {
        return $pool[array_rand($pool)];
    }

    /**
     * @param list<string> $pool
     * @return list<string> $count elementos distintos, sin repetidos
     */
    public static function sampleWithoutReplacement(array $pool, int $count): array
    {
        $shuffled = $pool;
        shuffle($shuffled);

        return array_slice($shuffled, 0, $count);
    }
}
