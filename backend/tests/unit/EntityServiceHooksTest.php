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
require_once BASE_PATH . '/src/core/HookDispatcher.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/src/controllers/ExtensionPluginDataStore.php';
require_once BASE_PATH . '/src/plugins/schema/ReverseRelationTabResolver.php';
require_once BASE_PATH . '/src/services/EntityOptionLabelBuilder.php';
require_once BASE_PATH . '/src/services/EntityService.php';

use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\exceptions\HookException;
use Xestify\exceptions\RepositoryException;
use Xestify\exceptions\ValidationException;
use Xestify\core\HookDispatcher;
use Xestify\plugins\schema\ReverseRelationTabResolver;
use Xestify\repositories\GenericRepository;
use Xestify\services\EntityService;
use Xestify\services\ValidationService;

const BLOCKED_BY_HOOK_MESSAGE = 'Blocked by hook';

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

/**
 * PdoStub — returns a fake schema row without touching the database.
 */
final class PdoStub extends PDO
{
    private bool $inTransaction = false;

    public function __construct()
    {
        // Intentionally skip parent::__construct to avoid real DB connection.
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false // NOSONAR
    {
        return new PdoStatementStub();
    }

    public function beginTransaction(): bool
    {
        $this->inTransaction = true;
        return true;
    }

    public function commit(): bool
    {
        return $this->endTransaction();
    }

    public function rollBack(): bool
    {
        return $this->endTransaction();
    }

    private function endTransaction(): bool
    {
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
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

    public bool $deleteCalled = false;

    /** @var array<string, mixed>|null Row returned by find(); defaults to a fake 'widgets' record. */
    public ?array $findResult = ['id' => 'fake-uuid', 'entity_slug' => 'widgets', 'content' => []];

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

    public function find(string $id): ?array
    {
        return $this->findResult;
    }

    public function delete(string $id): void
    {
        $this->deleteCalled = true;
    }

    /** @var array<int, array<string, mixed>> Rows returned by findByFieldValue(); defaults to none. */
    public array $dependentRows = [];

    public function findByFieldValue(string $entitySlug, string $field, string $value): array
    {
        return $this->dependentRows;
    }
}

/**
 * ExtensionDataStoreStub — records deleteByRecord() calls without touching a database.
 */
final class ExtensionDataStoreStub extends ExtensionPluginDataStore
{
    public bool $deleteByRecordCalled = false;

    public function __construct()
    {
        // Skip parent constructor (requires PDO).
    }

    public function deleteByRecord(string $entitySlug, string $recordId): int
    {
        $this->deleteByRecordCalled = true;
        return 0;
    }
}

/**
 * ReverseRelationResolverStub — returns a configurable, fixed list of
 * dependent relations instead of querying the plugins table.
 */
final class ReverseRelationResolverStub extends ReverseRelationTabResolver
{
    /** @var array<int, array{source_entity: string, key: string}> */
    public array $relations = [];

    public function __construct()
    {
        // Skip parent constructor (requires PluginRepository).
    }

    public function resolve(string $targetEntitySlug): array
    {
        return $this->relations;
    }
}

// ---------------------------------------------------------------------------

/**
 * Build EntityService with stub dependencies and optional HookDispatcher.
 */
function buildHooksService(?HookDispatcher $hooks = null): array
{
    $pdo       = new PdoStub();
    $repo      = new RepositoryStub();
    $extension = new ExtensionDataStoreStub();
    $relations = new ReverseRelationResolverStub();
    $service   = new EntityService($repo, new ValidationService(), $pdo, $extension, $relations, $hooks);
    return [$service, $repo, $extension, $relations];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo str_repeat('-', 40) . "\n";

TestSuite::run('createRecord() works without HookDispatcher (hooks=null)', function (): void {
    [$svc] = buildHooksService(null);
    $record = $svc->createRecord('widgets', ['name' => 'Alice']);
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
    $svc->createRecord('widgets', ['name' => 'Alice']);

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
    $svc->createRecord('widgets', ['name' => 'Alice']);

    assertTrue($afterCalled, 'afterSave must be called during createRecord');
});

TestSuite::run('createRecord() beforeSave can mutate data before persistence', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('beforeSave', static function (array $ctx): array {
        $ctx['data']['name'] = strtoupper((string) $ctx['data']['name']);
        return $ctx;
    });

    [, $repo] = buildHooksService($hooks);
    $svc = new EntityService(
        $repo,
        new ValidationService(),
        new PdoStub(),
        new ExtensionDataStoreStub(),
        new ReverseRelationResolverStub(),
        $hooks
    );
    $svc->createRecord('widgets', ['name' => 'alice']);

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
        $svc->createRecord('widgets', ['name' => 'Alice']);
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
    $svc->updateRecord('some-id', 'widgets', ['name' => 'Bob']);

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
    $svc->updateRecord('some-id', 'widgets', ['name' => 'Bob']);

    assertTrue($afterCalled, 'afterSave must be called during updateRecord');
});

TestSuite::run('updateRecord() passes record id to beforeSave context', function (): void {
    $hooks = new HookDispatcher();
    $capturedId = '';

    $hooks->register('beforeSave', static function (array $ctx) use (&$capturedId): array {
        $capturedId = (string) ($ctx['id'] ?? '');
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->updateRecord('record-123', 'widgets', ['name' => 'Bob']);

    assertEquals('record-123', $capturedId, 'updateRecord must pass record id into beforeSave context');
});

TestSuite::run('updateRecord() beforeSave blocking throws HookException', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('beforeSave', static function (array $ctx): array { // NOSONAR
        throw new HookException(BLOCKED_BY_HOOK_MESSAGE);
    });

    [$svc, $repo] = buildHooksService($hooks);
    $threw = false;

    try {
        $svc->updateRecord('x', 'widgets', ['name' => 'Bob']);
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
        $record = $svc->createRecord('widgets', ['name' => 'Alice']);
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
    $svc->createRecord('widgets', ['name' => 'Alice']);

    assertEquals('widgets', $capturedSlug, 'context must contain entity slug');
    assertEquals('Alice', $capturedData['name'] ?? '', 'context must contain data');
});

TestSuite::run('deleteRecord() dispatches beforeDelete before repository->delete()', function (): void {
    $hooks = new HookDispatcher();
    $beforeCalled = false;

    $hooks->register('beforeDelete', static function (array $ctx) use (&$beforeCalled): array {
        $beforeCalled = true;
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->deleteRecord('fake-uuid');

    assertTrue($beforeCalled, 'beforeDelete must be called during deleteRecord');
});

TestSuite::run('deleteRecord() dispatches afterDelete after repository->delete()', function (): void {
    $hooks = new HookDispatcher();
    $afterCalled = false;

    $hooks->register('afterDelete', static function (array $ctx) use (&$afterCalled): array {
        $afterCalled = true;
        return $ctx;
    });

    [$svc] = buildHooksService($hooks);
    $svc->deleteRecord('fake-uuid');

    assertTrue($afterCalled, 'afterDelete must be called during deleteRecord');
});

TestSuite::run('deleteRecord() beforeDelete throwing HookException blocks operation', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('beforeDelete', static function (array $ctx): array { // NOSONAR
        throw new HookException(BLOCKED_BY_HOOK_MESSAGE);
    });

    [$svc, $repo, $extension] = buildHooksService($hooks);
    $threw = false;

    try {
        $svc->deleteRecord('fake-uuid');
    } catch (HookException $e) {
        $threw = true;
        assertEquals(BLOCKED_BY_HOOK_MESSAGE, $e->getMessage(), 'HookException message must propagate');
    }

    assertTrue($threw, 'HookException from beforeDelete must propagate');
    assertTrue(!$repo->deleteCalled, 'repository->delete must NOT be called when beforeDelete blocks');
    assertTrue(!$extension->deleteByRecordCalled, 'extension data cleanup must NOT run when beforeDelete blocks');
});

TestSuite::run('deleteRecord() calls extensionDataStore->deleteByRecord() after soft-deleting', function (): void {
    [$svc, $repo, $extension] = buildHooksService(null);
    $svc->deleteRecord('fake-uuid');

    assertTrue($repo->deleteCalled, 'repository->delete must be called');
    assertTrue($extension->deleteByRecordCalled, 'extensionDataStore->deleteByRecord must be called');
});

TestSuite::run('deleteRecord() afterDelete failure does NOT propagate (non-blocking)', function (): void {
    $hooks = new HookDispatcher();
    $hooks->register('afterDelete', static function (array $ctx): array { // NOSONAR
        throw new HookException('side effect failed');
    });

    [$svc, $repo] = buildHooksService($hooks);
    $threw = false;

    try {
        $svc->deleteRecord('fake-uuid');
    } catch (\Throwable) {
        $threw = true;
    }

    assertTrue(!$threw, 'afterDelete failure must not propagate to caller');
    assertTrue($repo->deleteCalled, 'record must still be deleted despite afterDelete failure');
});

TestSuite::run('deleteRecord() throws RepositoryException when the record does not exist', function (): void {
    [$svc, $repo] = buildHooksService(null);
    $repo->findResult = null;
    $threw = false;

    try {
        $svc->deleteRecord('missing-id');
    } catch (RepositoryException) {
        $threw = true;
    }

    assertTrue($threw, 'RepositoryException must be thrown when find() returns null');
});

TestSuite::run('deleteRecord() throws HookException and skips delete when a dependent record exists', function (): void {
    [$svc, $repo, $extension, $relations] = buildHooksService(null);
    $relations->relations = [['source_entity' => 'orders', 'key' => 'person_id']];
    $repo->dependentRows = [['id' => 'dependent-uuid']];
    $threw = false;

    try {
        $svc->deleteRecord('fake-uuid');
    } catch (HookException $e) {
        $threw = true;
        assertTrue(str_contains($e->getMessage(), 'orders'), 'message must name the dependent entity');
    }

    assertTrue($threw, 'HookException must be thrown when a dependent record exists');
    assertTrue(!$repo->deleteCalled, 'repository->delete must NOT be called when a dependent record blocks deletion');
    assertTrue(!$extension->deleteByRecordCalled, 'extension data cleanup must NOT run when a dependent record blocks deletion');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

