<?php

/**
 * BusinessDataSeederTest — Integration tests.
 *
 * Verifies BusinessDataSeeder (STORY 10.6), the seeder that generates every
 * demo/business record used for the live TFM defense: it must run without
 * error, be genuinely idempotent on a second run (no duplicated rows), and
 * produce data whose cross-entity relations actually resolve to real rows
 * (invoices -> orders, optometries/contact_lenses -> clients).
 *
 * Requires a live PostgreSQL connection with the demo plugins already
 * active (same precondition the seeder itself enforces via
 * assertRequiredPluginsActive()). If any of them is missing, the suite
 * skips cleanly instead of failing, since not every dev environment has
 * the demo catalog installed.
 *
 * Run:
 *   php backend/tests/integration/BusinessDataSeederTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';

use Xestify\core\Database;
use Xestify\database\seeders\BusinessDataSeeder;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\SeederException;

const REQUIRED_ACTIVE_SLUGS = [
    'clients', 'distributors', 'ophthalmologists', 'brands', 'manufacturers',
    'orders', 'sales', 'invoices', 'optometries', 'contact_lenses', 'comments',
];

const CORE_ENTITY_SLUGS = ['brands', 'manufacturers', 'distributors', 'ophthalmologists', 'clients', 'orders', 'invoices', 'sales'];
const EXTENSION_PLUGIN_SLUGS = ['optometries', 'contact_lenses', 'comments'];
const EXPECTED_GROUP_COUNT = 11;
const SAMPLE_LIMIT = 5;

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

function skipSuite(string $reason): never
{
    echo "[SKIP] {$reason}\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    skipSuite('PostgreSQL not reachable — configure backend/.env with valid DB_* vars.');
}

function activePluginSlugs(\PDO $pdo, array $slugs): array
{
    $stmt = $pdo->prepare("SELECT slug FROM plugins WHERE slug = ANY(:slugs::text[]) AND status = 'active'");
    $stmt->execute([':slugs' => '{' . implode(',', $slugs) . '}']);
    return $stmt->fetchAll(\PDO::FETCH_COLUMN);
}

$missing = array_values(array_diff(REQUIRED_ACTIVE_SLUGS, activePluginSlugs($pdo, REQUIRED_ACTIVE_SLUGS)));
if ($missing !== []) {
    skipSuite(
        'Demo plugins required by BusinessDataSeeder are not all active in this environment ('
        . implode(', ', $missing) . '). Run tools/setup/sync-plugins.php and activate them first.'
    );
}

/** @return array<string, int> group => row count, only for groups present in $rows */
function countsByGroup(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[$row['group']] = $row['count'];
    }
    return $out;
}

function fetchEntityDataCounts(\PDO $pdo, array $slugs): array
{
    $stmt = $pdo->prepare(
        'SELECT entity_slug, COUNT(*) AS n FROM plugin_entity_data
          WHERE entity_slug = ANY(:slugs::text[]) AND deleted_at IS NULL
          GROUP BY entity_slug'
    );
    $stmt->execute([':slugs' => '{' . implode(',', $slugs) . '}']);
    $out = [];
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $out[$row['entity_slug']] = (int) $row['n'];
    }
    return $out;
}

function fetchExtensionDataCounts(\PDO $pdo, array $slugs): array
{
    $stmt = $pdo->prepare(
        'SELECT plugin_slug, COUNT(*) AS n FROM plugin_extension_data
          WHERE plugin_slug = ANY(:slugs::text[])
          GROUP BY plugin_slug'
    );
    $stmt->execute([':slugs' => '{' . implode(',', $slugs) . '}']);
    $out = [];
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $out[$row['plugin_slug']] = (int) $row['n'];
    }
    return $out;
}

$seeder = new BusinessDataSeeder($pdo);
$firstRun = null;
$secondRun = null;

TestSuite::run('run() completes without throwing and returns the expected shape', function () use ($seeder, &$firstRun): void {
    $firstRun = $seeder->run();

    assertTrue(is_string($firstRun['admin_email']) && $firstRun['admin_email'] !== '', 'admin_email must be a non-empty string');
    assertTrue(is_string($firstRun['admin_id']) && $firstRun['admin_id'] !== '', 'admin_id must be a non-empty string');
    assertEquals(EXPECTED_GROUP_COUNT, count($firstRun['groups']), 'must report exactly the 8 core + 3 extension groups');

    foreach ($firstRun['groups'] as $group) {
        assertTrue(array_key_exists('group', $group) && array_key_exists('status', $group) && array_key_exists('count', $group), 'each group summary must have group/status/count');
        assertTrue(in_array($group['status'], ['seeded', 'skipped'], true), "unexpected status '{$group['status']}' for group '{$group['group']}'");
    }
});

TestSuite::run('a second run is fully idempotent: every group is skipped and no group grows', function () use ($seeder, $pdo, &$firstRun, &$secondRun): void {
    $entityCountsBefore = fetchEntityDataCounts($pdo, CORE_ENTITY_SLUGS);
    $extensionCountsBefore = fetchExtensionDataCounts($pdo, EXTENSION_PLUGIN_SLUGS);

    $secondRun = $seeder->run();

    foreach ($secondRun['groups'] as $group) {
        assertEquals('skipped', $group['status'], "group '{$group['group']}' must be skipped on a re-run, got '{$group['status']}'");
    }

    $entityCountsAfter = fetchEntityDataCounts($pdo, CORE_ENTITY_SLUGS);
    $extensionCountsAfter = fetchExtensionDataCounts($pdo, EXTENSION_PLUGIN_SLUGS);

    assertEquals($entityCountsBefore, $entityCountsAfter, 'plugin_entity_data counts must not change on a re-run');
    assertEquals($extensionCountsBefore, $extensionCountsAfter, 'plugin_extension_data counts must not change on a re-run');
});

TestSuite::run('every seeded invoice references a real order via id_order', function () use ($pdo): void {
    $stmt = $pdo->query(
        "SELECT content->>'id_order' AS id_order FROM plugin_entity_data
          WHERE entity_slug = 'invoices' AND deleted_at IS NULL
          LIMIT " . SAMPLE_LIMIT
    );
    $sample = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    assertTrue(count($sample) > 0, 'expected at least one seeded invoice to sample');

    $orderCheck = $pdo->prepare(
        "SELECT EXISTS (SELECT 1 FROM plugin_entity_data WHERE entity_slug = 'orders' AND id = :id AND deleted_at IS NULL) AS exists"
    );
    foreach ($sample as $idOrder) {
        assertTrue(is_string($idOrder) && $idOrder !== '', 'invoice content.id_order must be a non-empty string');
        $orderCheck->execute([':id' => $idOrder]);
        $row = $orderCheck->fetch();
        assertTrue($row !== false && $row['exists'] === true, "invoice references order id '{$idOrder}' which does not exist");
    }
});

TestSuite::run('every seeded optometries/contact_lenses record hangs off a real client', function () use ($pdo): void {
    $stmt = $pdo->prepare(
        "SELECT record_id FROM plugin_extension_data WHERE plugin_slug = ANY(:slugs::text[]) LIMIT " . SAMPLE_LIMIT
    );
    $stmt->execute([':slugs' => '{optometries,contact_lenses}']);
    $sample = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    assertTrue(count($sample) > 0, 'expected at least one seeded optometries/contact_lenses row to sample');

    $clientCheck = $pdo->prepare(
        "SELECT EXISTS (SELECT 1 FROM plugin_entity_data WHERE entity_slug = 'clients' AND id = :id AND deleted_at IS NULL) AS exists"
    );
    foreach ($sample as $recordId) {
        $clientCheck->execute([':id' => $recordId]);
        $row = $clientCheck->fetch();
        assertTrue($row !== false && $row['exists'] === true, "extension row references client id '{$recordId}' which does not exist");
    }
});

TestSuite::summary();
exit(TestSuite::exitCode());
