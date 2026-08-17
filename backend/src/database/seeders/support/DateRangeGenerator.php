<?php

declare(strict_types=1);

namespace Xestify\database\seeders\support;

use DateTimeImmutable;

/**
 * Generates dates/timestamps uniformly distributed within a range, for
 * STORY 10.6's business data seeder.
 */
final class DateRangeGenerator
{
    private function __construct()
    {
        // Clase estatica, instanciacion no permitida.
    }

    public static function dateBetween(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        return (new DateTimeImmutable('@' . self::timestampBetween($start, $end)))->format('Y-m-d');
    }

    public static function dateTimeIso(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        return (new DateTimeImmutable('@' . self::timestampBetween($start, $end)))->format('c');
    }

    private static function timestampBetween(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $startTs = $start->getTimestamp();
        $endTs = max($startTs, $end->getTimestamp());

        return RandomPrimitives::intBetween($startTs, $endTs);
    }
}
