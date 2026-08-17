<?php

declare(strict_types=1);

namespace Xestify\database\seeders;

use DateTimeImmutable;
use PDO;
use Xestify\database\seeders\support\AbstractGroupSeeder;
use Xestify\database\seeders\support\DateRangeGenerator;
use Xestify\database\seeders\support\FakeDataGenerator;
use Xestify\database\seeders\support\RandomPrimitives;

/**
 * Seeds the 3 `plugin_extension_data` groups for STORY 10.6 (optometries,
 * contact_lenses, comments), all attached to `clients` records already
 * seeded by CoreEntitySeeder. Assigns each client a single "activity tier"
 * (alto/medio/bajo, 40/30/30) shared across the 3 groups, so the same
 * subset of "VIP" clients concentrates the higher counts in all of them
 * (decision confirmed with the user, not 3 independent shuffles).
 */
final class ExtensionDataSeeder extends AbstractGroupSeeder
{
    private const OPTIONAL_RELATION_PERCENT = 75;
    private const TIER_ALTO_RATIO = 0.4;
    private const TIER_MEDIO_RATIO = 0.3;

    private const EYES = ['od', 'os'];

    /** @var array<string, array{0: float, 1: float, 2: float}> [min, max, step] */
    private const METRIC_RANGES = [
        'sphere' => [-20.0, 20.0, 0.25],
        'cylinder' => [-10.0, 10.0, 0.25],
        'axis' => [0.0, 180.0, 1.0],
        'addition' => [0.0, 4.0, 0.25],
    ];

    /** @var array<string, array{0: int, 1: int}> fichas por tier */
    private const FICHA_TIER_RANGES = [
        'alto' => [3, 4],
        'medio' => [2, 3],
        'bajo' => [1, 2],
    ];

    /** @var array<string, array{0: int, 1: int}> comentarios por tier */
    private const COMMENT_TIER_RANGES = [
        'alto' => [2, 3],
        'medio' => [1, 2],
        'bajo' => [0, 1],
    ];

    /**
     * @param array{
     *     clients: list<string>,
     *     ophthalmologists: list<string>,
     *     brands: list<string>,
     *     manufacturers: list<string>,
     *     distributors: list<string>
     * } $catalogIds
     */
    public function __construct(PDO $pdo, string $adminId, private array $catalogIds)
    {
        parent::__construct($pdo, $adminId);
    }

    public function run(DateTimeImmutable $now, DateTimeImmutable $rangeStart): void
    {
        $tiers = $this->buildClientTiers($this->catalogIds['clients']);

        $this->seedOptometries($now, $rangeStart, $tiers);
        $this->seedContactLenses($now, $rangeStart, $tiers);
        $this->seedComments($now, $rangeStart, $tiers);
    }

    private function seedOptometries(DateTimeImmutable $now, DateTimeImmutable $rangeStart, array $tiers): void
    {
        $existingCount = $this->countExtensionRows('optometries');
        if ($existingCount > 0) {
            $this->recordSummary('optometries', 'skipped', $existingCount);
            return;
        }

        $inserted = $this->runInTransaction(function () use ($now, $rangeStart, $tiers): int {
            $stmt = $this->prepareExtensionInsert();
            $count = 0;
            foreach ($this->catalogIds['clients'] as $clientId) {
                $fichas = self::countForTier($tiers[$clientId], self::FICHA_TIER_RANGES);
                for ($i = 0; $i < $fichas; $i++) {
                    $content = $this->buildOptometryContent($now, $rangeStart);
                    $this->insertExtensionRow($stmt, 'optometries', 'clients', $clientId, $content);
                    $count++;
                }
            }
            return $count;
        });
        $this->recordSummary('optometries', 'seeded', $inserted);
    }

    private function seedContactLenses(DateTimeImmutable $now, DateTimeImmutable $rangeStart, array $tiers): void
    {
        $existingCount = $this->countExtensionRows('contact_lenses');
        if ($existingCount > 0) {
            $this->recordSummary('contact_lenses', 'skipped', $existingCount);
            return;
        }

        $inserted = $this->runInTransaction(function () use ($now, $rangeStart, $tiers): int {
            $stmt = $this->prepareExtensionInsert();
            $count = 0;
            foreach ($this->catalogIds['clients'] as $clientId) {
                $fichas = self::countForTier($tiers[$clientId], self::FICHA_TIER_RANGES);
                for ($i = 0; $i < $fichas; $i++) {
                    $content = $this->buildContactLensContent($now, $rangeStart);
                    $this->insertExtensionRow($stmt, 'contact_lenses', 'clients', $clientId, $content);
                    $count++;
                }
            }
            return $count;
        });
        $this->recordSummary('contact_lenses', 'seeded', $inserted);
    }

    private function seedComments(DateTimeImmutable $now, DateTimeImmutable $rangeStart, array $tiers): void
    {
        $existingCount = $this->countExtensionRows('comments');
        if ($existingCount > 0) {
            $this->recordSummary('comments', 'skipped', $existingCount);
            return;
        }

        $inserted = $this->runInTransaction(function () use ($now, $rangeStart, $tiers): int {
            $stmt = $this->prepareExtensionInsert();
            $count = 0;
            foreach ($this->catalogIds['clients'] as $clientId) {
                $total = self::countForTier($tiers[$clientId], self::COMMENT_TIER_RANGES);
                for ($i = 0; $i < $total; $i++) {
                    $content = $this->buildCommentContent($now, $rangeStart);
                    $this->insertExtensionRow($stmt, 'comments', 'clients', $clientId, $content);
                    $count++;
                }
            }
            return $count;
        });
        $this->recordSummary('comments', 'seeded', $inserted);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptometryContent(DateTimeImmutable $now, DateTimeImmutable $rangeStart): array
    {
        $content = $this->buildEyeSectionMetrics(
            ['distance', 'constant', 'near', 'contact_lens'],
            ['sphere', 'cylinder', 'axis']
        );

        foreach (self::EYES as $eye) {
            $content["{$eye}_addition"] = RandomPrimitives::steppedFloatBetween(0.0, 4.0, 0.25);
        }

        $content['date'] = DateRangeGenerator::dateBetween($rangeStart, $now);
        $content['pupillary_distance'] = RandomPrimitives::steppedFloatBetween(40.0, 80.0, 0.5);
        $content['notes'] = FakeDataGenerator::noteFor('optometry');

        $this->maybeAssignRelation($content, 'ophthalmologist', $this->catalogIds['ophthalmologists']);
        $this->maybeAssignRelation($content, 'optometrist', $this->catalogIds['ophthalmologists']);

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactLensContent(DateTimeImmutable $now, DateTimeImmutable $rangeStart): array
    {
        $content = $this->buildEyeSectionMetrics(
            ['lens', 'keratometry'],
            ['sphere', 'cylinder', 'axis', 'addition']
        );

        foreach (self::EYES as $eye) {
            $content["{$eye}_base_curve"] = RandomPrimitives::steppedFloatBetween(6.0, 10.0, 0.25);
            $content["{$eye}_diameter"] = RandomPrimitives::steppedFloatBetween(12.0, 16.0, 0.5);
            $content["{$eye}_replacement_schedule"] = FakeDataGenerator::replacementSchedule();
            $content["{$eye}_pack"] = FakeDataGenerator::packSize();
        }

        $content['date'] = DateRangeGenerator::dateBetween($rangeStart, $now);
        $content['notes'] = FakeDataGenerator::noteFor('contact_lens');

        $this->maybeAssignRelation($content, 'od_brand', $this->catalogIds['brands']);
        $this->maybeAssignRelation($content, 'os_brand', $this->catalogIds['brands']);
        $this->maybeAssignRelation($content, 'od_manufacturer', $this->catalogIds['manufacturers']);
        $this->maybeAssignRelation($content, 'os_manufacturer', $this->catalogIds['manufacturers']);
        $this->maybeAssignRelation($content, 'od_distributor', $this->catalogIds['distributors']);
        $this->maybeAssignRelation($content, 'os_distributor', $this->catalogIds['distributors']);

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCommentContent(DateTimeImmutable $now, DateTimeImmutable $rangeStart): array
    {
        return [
            'body' => FakeDataGenerator::noteFor('comment'),
            'stamp' => DateRangeGenerator::dateTimeIso($rangeStart, $now),
            'author_id' => $this->adminId,
        ];
    }

    /**
     * @param list<string> $sections
     * @param list<string> $metrics
     * @return array<string, float>
     */
    private function buildEyeSectionMetrics(array $sections, array $metrics): array
    {
        $content = [];
        foreach (self::EYES as $eye) {
            foreach ($sections as $section) {
                foreach ($metrics as $metric) {
                    [$min, $max, $step] = self::METRIC_RANGES[$metric];
                    $content["{$eye}_{$section}_{$metric}"] = RandomPrimitives::steppedFloatBetween($min, $max, $step);
                }
            }
        }
        return $content;
    }

    /**
     * @param array<string, mixed> $content
     * @param list<string> $pool
     */
    private function maybeAssignRelation(array &$content, string $key, array $pool): void
    {
        if ($pool === [] || !RandomPrimitives::boolWithProbability(self::OPTIONAL_RELATION_PERCENT)) {
            return;
        }
        $content[$key] = RandomPrimitives::pick($pool);
    }

    /**
     * Baraja los clientes una sola vez y les asigna un tier de actividad
     * (40% alto / 30% medio / 30% bajo) reutilizado por los 3 grupos.
     *
     * @param list<string> $clientIds
     * @return array<string, string>
     */
    private function buildClientTiers(array $clientIds): array
    {
        $shuffled = $clientIds;
        shuffle($shuffled);

        $total = count($shuffled);
        $altoCount = (int) round($total * self::TIER_ALTO_RATIO);
        $medioCount = (int) round($total * self::TIER_MEDIO_RATIO);

        $tiers = [];
        foreach ($shuffled as $index => $clientId) {
            $tiers[$clientId] = self::tierForIndex($index, $altoCount, $medioCount);
        }

        return $tiers;
    }

    private static function tierForIndex(int $index, int $altoCount, int $medioCount): string
    {
        if ($index < $altoCount) {
            return 'alto';
        }
        if ($index < $altoCount + $medioCount) {
            return 'medio';
        }
        return 'bajo';
    }

    /**
     * @param array<string, array{0: int, 1: int}> $ranges
     */
    private static function countForTier(string $tier, array $ranges): int
    {
        [$min, $max] = $ranges[$tier];

        return RandomPrimitives::intBetween($min, $max);
    }
}
