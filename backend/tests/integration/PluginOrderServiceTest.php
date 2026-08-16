<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_services.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginOrderServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const ORDER_QUERY = 'SELECT sort_order FROM plugins WHERE slug = :slug';
const ORDER_PLUGIN_NAME = 'Order Plugin';
const ORDER_PLUGIN_VERSION = '1.0.0';

function insertOrderTestPlugin(PDO $pdo, string $slug, int $sortOrder, string $type = 'entity'): void
{
    $manifest = json_encode([
        'name' => $slug,
        'label' => ORDER_PLUGIN_NAME,
        'version' => ORDER_PLUGIN_VERSION,
        'type' => $type,
        'core_version' => ORDER_PLUGIN_VERSION,
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $pdo->prepare(
        'INSERT INTO plugins (slug, status, manifest_json, sort_order)
         VALUES (:slug, :status, CAST(:manifest AS jsonb), :sort_order)'
    )->execute([
        ':slug' => $slug,
        ':status' => 'active',
        ':manifest' => $manifest,
        ':sort_order' => $sortOrder,
    ]);
}

function readSortOrder(PDO $pdo, string $slug): int
{
    $stmt = $pdo->prepare(ORDER_QUERY);
    $stmt->execute([':slug' => $slug]);
    return (int) $stmt->fetchColumn();
}

/**
 * Other suites (and manual dev data) leave real rows in `plugins`, so fixed
 * sort_order values would risk landing next to — or between — rows this
 * test doesn't own. Every helper below reads the table's current bounds
 * right before inserting, so its fixtures are always strictly outside
 * (below the current min / above the current max) whatever else exists.
 */
function currentMinSortOrder(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COALESCE(MIN(sort_order), 0) FROM plugins')->fetchColumn();
}

function currentMaxSortOrder(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM plugins')->fetchColumn();
}

/**
 * Three plugins with contiguous sort_order, all placed above the table's
 * current max — guaranteed no other row (from this suite or real data) can
 * sit between them, so lockNeighborBySortOrder() only ever sees each other.
 *
 * @return array{0: string, 1: string, 2: string} [$first, $middle, $last] slugs
 */
function makeOrderedTriplet(PDO $pdo): array
{
    $base = currentMaxSortOrder($pdo);
    $suffix = bin2hex(random_bytes(3));
    $first = "order_a_{$suffix}";
    $middle = "order_b_{$suffix}";
    $last = "order_c_{$suffix}";

    insertOrderTestPlugin($pdo, $first, $base + 10);
    insertOrderTestPlugin($pdo, $middle, $base + 20);
    insertOrderTestPlugin($pdo, $last, $base + 30);

    return [$first, $middle, $last];
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('moveDown() swaps sort_order with the next plugin', function () use ($pdo): void {
    [$first, $middle, $last] = makeOrderedTriplet($pdo);
    $firstOrder = readSortOrder($pdo, $first);
    $middleOrder = readSortOrder($pdo, $middle);

    try {
        $service = buildPluginOrderService($pdo);
        $service->moveDown($first);

        assertEquals($middleOrder, readSortOrder($pdo, $first), 'first plugin should take the middle sort_order');
        assertEquals($firstOrder, readSortOrder($pdo, $middle), 'middle plugin should take the first sort_order');
    } finally {
        cleanupPluginRecord($pdo, $first);
        cleanupPluginRecord($pdo, $middle);
        cleanupPluginRecord($pdo, $last);
    }
});

TestSuite::run('moveUp() swaps sort_order with the previous plugin', function () use ($pdo): void {
    [$first, $middle, $last] = makeOrderedTriplet($pdo);
    $middleOrder = readSortOrder($pdo, $middle);
    $lastOrder = readSortOrder($pdo, $last);

    try {
        $service = buildPluginOrderService($pdo);
        $service->moveUp($last);

        assertEquals($middleOrder, readSortOrder($pdo, $last), 'last plugin should take the middle sort_order');
        assertEquals($lastOrder, readSortOrder($pdo, $middle), 'middle plugin should take the last sort_order');
    } finally {
        cleanupPluginRecord($pdo, $first);
        cleanupPluginRecord($pdo, $middle);
        cleanupPluginRecord($pdo, $last);
    }
});

TestSuite::run('moveUp() on the globally first plugin is a no-op', function () use ($pdo): void {
    $slug = 'order_min_' . bin2hex(random_bytes(3));
    insertOrderTestPlugin($pdo, $slug, currentMinSortOrder($pdo) - 10);
    $before = readSortOrder($pdo, $slug);

    try {
        $service = buildPluginOrderService($pdo);
        $result = $service->moveUp($slug);

        assertEquals($before, readSortOrder($pdo, $slug), 'sort_order must stay unchanged for the global first plugin');
        assertEquals($before, (int) ($result['sort_order'] ?? -1), 'returned row must reflect the unchanged sort_order');
    } finally {
        cleanupPluginRecord($pdo, $slug);
    }
});

TestSuite::run('moveDown() on the globally last plugin is a no-op', function () use ($pdo): void {
    $slug = 'order_max_' . bin2hex(random_bytes(3));
    insertOrderTestPlugin($pdo, $slug, currentMaxSortOrder($pdo) + 10);
    $before = readSortOrder($pdo, $slug);

    try {
        $service = buildPluginOrderService($pdo);
        $result = $service->moveDown($slug);

        assertEquals($before, readSortOrder($pdo, $slug), 'sort_order must stay unchanged for the global last plugin');
        assertEquals($before, (int) ($result['sort_order'] ?? -1), 'returned row must reflect the unchanged sort_order');
    } finally {
        cleanupPluginRecord($pdo, $slug);
    }
});

TestSuite::run('moveDown() only considers plugins of the same type — an extension in between is skipped and left untouched', function () use ($pdo): void {
    $base = currentMaxSortOrder($pdo);
    $suffix = bin2hex(random_bytes(3));
    $entityFirst = "order_entity_a_{$suffix}";
    $entitySecond = "order_entity_b_{$suffix}";
    $extensionBetween = "order_ext_between_{$suffix}";

    insertOrderTestPlugin($pdo, $entityFirst, $base + 10, 'entity');
    insertOrderTestPlugin($pdo, $extensionBetween, $base + 15, 'extension');
    insertOrderTestPlugin($pdo, $entitySecond, $base + 20, 'entity');

    try {
        $service = buildPluginOrderService($pdo);
        $service->moveDown($entityFirst);

        assertEquals($base + 20, readSortOrder($pdo, $entityFirst), 'entity plugin should swap with the next entity, skipping over the extension in between');
        assertEquals($base + 10, readSortOrder($pdo, $entitySecond), 'the other entity should take the moved plugin\'s old sort_order');
        assertEquals($base + 15, readSortOrder($pdo, $extensionBetween), 'the interloping extension plugin must be left untouched');
    } finally {
        cleanupPluginRecord($pdo, $entityFirst);
        cleanupPluginRecord($pdo, $entitySecond);
        cleanupPluginRecord($pdo, $extensionBetween);
    }
});

TestSuite::run('moveUp() throws OutOfBoundsException for a missing slug', function () use ($pdo): void {
    $service = buildPluginOrderService($pdo);
    $threw = false;
    try {
        $service->moveUp('missing_' . bin2hex(random_bytes(3)));
    } catch (OutOfBoundsException) {
        $threw = true;
    }

    assertTrue($threw, 'moveUp() should throw OutOfBoundsException for an unknown slug');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
