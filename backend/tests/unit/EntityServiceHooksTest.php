<?php

/**
 * EntityServiceHooksTest — Unit tests for beforeSave/afterSave hook integration
 * in EntityService.
 *
 * Uses hand-rolled stubs for GenericRepository, ValidationService, and PDO
 * so no PostgreSQL connection is required.
 *
 * Run:
 *   php backend/tests/unit/EntityServiceHooksTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/plugins/HookDispatcher.php';
require_once BASE_PATH . '/src/services/ValidationService.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/src/services/EntityService.php';

use Xestify\exceptions\HookException;
use Xestify\exceptions\ValidationException;
use Xestify\plugins\HookDispatcher;
use Xestify\repositories\GenericRepository;
use Xestify\services\EntityService;
use Xestify\services\ValidationService;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

/**
 * PdoStub — returns a fake schema row without touching the database.
 */
final class PdoStub extends PDO
{
    public function __construct()
    {
        // Intentionally skip parent::__construct to avoid real DB connection.
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false // NOSONAR
    {
        return new PdoStatementStub();
    }
}

/**
 * PdoStatementStub — always returns one row with a trivial schema JSON.
 */
final class PdoStatementStub extends \PDOStatement
{
    private bool $fetched = false;

    public function execute(?array $params = null): bool
    {
        $this->fetched = false;
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->fetched) {
            return false;
        }
        $this->fetched = true;
        return ['schema_json' => '{"fields":{"name":{"type":"string","required":true}}}'];
    }
}

/**
 * RepositoryStub — records calls and returns predictable data.
 */
final class RepositoryStub extends GenericRepository
{
    /** @var array<string, mixed>|null */
    public ?array $lastCreateData = null;

    /** @var array<string, mixed>|null */
    public ?array $lastUpdateData = null;

    public function __construct()
    {
        // Skip parent constructor (requires PDO).
    }

    public function create(string $entitySlug, array $data, ?string $ownerId = null): array
    {
        $this->lastCreateData = $data;
        return ['id' => 'fake-uuid', 'entity_slug' => $entitySlug, 'content' => $data];
    }

    public function update(string $id, array $data): array
    {
        $this->lastUpdateData = $data;
        return ['id' => $id, 'entity_slug' => 'test', 'content' => $data];
    }
}

// ---------------------------------------------------------------------------

/**
 * Build EntityService with stub dependencies and optional HookDispatcher.
 */
function buildHooksService(?HookDispatcher $hooks = null): array
{
    $pdo     = new PdoStub();
    $repo    = new RepositoryStub();
    $service = new EntityService($repo, new ValidationService(), $pdo, $hooks);
    return [$service, $repo];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo str_repeat('-', 40) . "\n";

TestSuite::run('createRecord() works without HookDispatcher (hooks=null)', function (): void {
    [$svc] = buildHooksService(null);
    $record = $svc->createRecord('client', ['name' => 'Alice']);
    assertTrue(isset($record['id']), 'record must have id');
});

TestSuite::run('createRecord() dispatches beforeSave before persisting', function (): void {
    $hooks = new HookDispatcher();
    $beforeCalled = false;

    $hooks->register('beforeSave', static function (array $ctx) use (&$beforeCalled): array {
        $beforeCalled = true;
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->createRecord('client', ['name' => 'Alice']);

    assertTrue($beforeCalled, 'beforeSave must be called during createRecord');
});

TestSuite::run('createRecord() dispatches afterSave after persisting', function (): void {
    $hooks = new HookDispatcher();
    $afterCalled = false;

    $hooks->register('afterSave', static function (array $ctx) use (&$afterCalled): array {
        $afterCalled = true;
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->createRecord('client', ['name' => 'Alice']);

    assertTrue($afterCalled, 'afterSave must be called during createRecord');
});

TestSuite::run('createRecord() beforeSave can mutate data before persistence', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('beforeSave', static function (array $ctx): array {
        $ctx['data']['name'] = strtoupper((string) $ctx['data']['name']);
        return $ctx;
    });

    [, $repo] = buildHooksService($hooks);
    $svc = new EntityService($repo, new ValidationService(), new PdoStub(), $hooks);
    $svc->createRecord('client', ['name' => 'alice']);

    assertEquals('ALICE', $repo->lastCreateData['name'] ?? '', 'beforeSave mutation must reach repository');
});

TestSuite::run('createRecord() beforeSave throwing HookException blocks operation', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('beforeSave', static function (array $ctx): array { // NOSONAR
        throw new HookException('Email already exists');
    });

    [$svc, $repo] = buildHooksService($hooks);
    $threw = false;

    try {
        $svc->createRecord('client', ['name' => 'Alice']);
    } catch (HookException $e) {
        $threw = true;
        assertEquals('Email already exists', $e->getMessage(), 'HookException message must propagate');
    }

    assertTrue($threw, 'HookException from beforeSave must propagate');
    assertTrue($repo->lastCreateData === null, 'repository->create must NOT be called when beforeSave blocks');
});

TestSuite::run('updateRecord() dispatches beforeSave before persisting', function (): void {
    $hooks = new HookDispatcher();
    $beforeCalled = false;

    $hooks->register('beforeSave', static function (array $ctx) use (&$beforeCalled): array {
        $beforeCalled = true;
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->updateRecord('some-id', 'client', ['name' => 'Bob']);

    assertTrue($beforeCalled, 'beforeSave must be called during updateRecord');
});

TestSuite::run('updateRecord() dispatches afterSave after persisting', function (): void {
    $hooks = new HookDispatcher();
    $afterCalled = false;

    $hooks->register('afterSave', static function (array $ctx) use (&$afterCalled): array {
        $afterCalled = true;
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->updateRecord('some-id', 'client', ['name' => 'Bob']);

    assertTrue($afterCalled, 'afterSave must be called during updateRecord');
});

TestSuite::run('updateRecord() beforeSave blocking throws HookException', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('beforeSave', static function (array $ctx): array { // NOSONAR
        throw new HookException('Blocked by hook');
    });

    [$svc, $repo] = buildHooksService($hooks);
    $threw = false;

    try {
        $svc->updateRecord('x', 'client', ['name' => 'Bob']);
    } catch (HookException) {
        $threw = true;
    }

    assertTrue($threw, 'HookException from beforeSave must propagate on update');
    assertTrue($repo->lastUpdateData === null, 'repository->update must NOT be called when beforeSave blocks');
});

TestSuite::run('afterSave failure does NOT propagate (non-blocking)', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('afterSave', static function (array $ctx): array { // NOSONAR
        throw new HookException('side effect failed');
    });

    [$svc] = buildHooksService($hooks);
    $threw = false;

    try {
        $record = $svc->createRecord('client', ['name' => 'Alice']);
    } catch (\Throwable) {
        $threw = true;
    }

    assertTrue(!$threw, 'afterSave failure must not propagate to caller');
    assertTrue(isset($record) && $record['id'] === 'fake-uuid', 'record must be returned despite afterSave failure');
});

TestSuite::run('context passed to beforeSave contains slug and data keys', function (): void {
    $hooks = new HookDispatcher();
    $capturedSlug = '';
    $capturedData = [];

    $hooks->register('beforeSave', static function (array $ctx) use (&$capturedSlug, &$capturedData): array {
        $capturedSlug = (string) ($ctx['slug'] ?? '');
        $capturedData = (array) ($ctx['data'] ?? []);
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->createRecord('client', ['name' => 'Alice']);

    assertEquals('client', $capturedSlug, 'context must contain entity slug');
    assertEquals('Alice', $capturedData['name'] ?? '', 'context must contain data');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

