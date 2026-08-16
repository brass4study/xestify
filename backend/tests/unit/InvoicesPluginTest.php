<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BACKEND_PATH', dirname(__DIR__, 2));
define('BASE_PATH', dirname(BACKEND_PATH));

require_once BACKEND_PATH . '/src/exceptions/HookException.php';
require_once BACKEND_PATH . '/src/core/HookDispatcher.php';
require_once BACKEND_PATH . '/src/plugins/contracts/AbstractUniqueFieldHook.php';

// Explicitly require plugin files (not in autoload path)
require_once BASE_PATH . '/plugins/invoices/Hooks.php';

require_once __DIR__ . '/helpers.php';

use Xestify\plugins\invoices\Hooks;
use Xestify\core\HookDispatcher;
use Xestify\exceptions\HookException;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

/**
 * PDO stub that records prepared statements and simulates fetchColumn().
 * Avoid calling parent::__construct (requires a real DSN).
 */
class InvoicesPdoStub extends PDO
{
    public array $executedSqls    = [];
    public array $executedParams  = [];
    public int   $fetchColumnReturn = 0;

    public function __construct()
    {
        // intentionally no parent call
    }

    public function setFetchColumnReturn(int $value): void
    {
        $this->fetchColumnReturn = $value;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new InvoicesStmtStub($this, $query);
    }
}

class InvoicesStmtStub extends \PDOStatement
{
    private InvoicesPdoStub $pdoStub;
    private string $sql;

    public function __construct(InvoicesPdoStub $pdoStub, string $sql)
    {
        $this->pdoStub = $pdoStub;
        $this->sql     = $sql;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdoStub->executedSqls[]   = $this->sql;
        $this->pdoStub->executedParams[] = $params ?? [];
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->pdoStub->fetchColumnReturn;
    }
}

// ---------------------------------------------------------------------------
// PLUGIN_DIR constant for structure tests
// ---------------------------------------------------------------------------
define('INVOICES_PLUGIN_DIR', BASE_PATH . '/plugins/invoices');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin invoices - manifest.json existe', function (): void {
    $path = INVOICES_PLUGIN_DIR . '/manifest.json';
    assertTrue(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin invoices - manifest.json campos requeridos', function (): void {
    $data = json_decode((string) file_get_contents(INVOICES_PLUGIN_DIR . '/manifest.json'), true);
    assertTrue(is_array($data), 'manifest.json must be a JSON object');
    foreach (['name', 'label', 'version', 'type', 'core_version'] as $field) {
        assertTrue(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assertTrue($data['name'] === 'invoices', 'name must be invoices');
    assertTrue($data['type'] === 'entity', 'type must be entity');
});

TestSuite::run('Plugin invoices - schema.json existe', function (): void {
    $path = INVOICES_PLUGIN_DIR . '/schema.json';
    assertTrue(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin invoices - schema.json respeta contrato identities/fields/custom_fields/relations', function (): void {
    $data = json_decode((string) file_get_contents(INVOICES_PLUGIN_DIR . '/schema.json'), true);
    assertTrue(is_array($data), 'schema.json must be a JSON object');
    assertTrue(isset($data['identities']) && is_array($data['identities']), 'schema.json must have "identities"');
    assertTrue(isset($data['fields']) && is_array($data['fields']), 'schema.json must have "fields"');
    assertTrue(isset($data['custom_fields']) && is_array($data['custom_fields']), 'schema.json must have "custom_fields"');
    assertTrue(isset($data['relations']) && is_array($data['relations']), 'schema.json must have "relations"');

    assertTrue(isset($data['identities']['id']), 'schema.json must define identity "id"');
    assertTrue(($data['identities']['id']['type'] ?? '') === 'uuid', 'identity id must be uuid');

    $fieldKeys = array_keys($data['fields']);
    $expectedFieldKeys = ['invoice_number', 'issue_date', 'amount', 'payment_status'];
    sort($fieldKeys);
    sort($expectedFieldKeys);
    assertTrue($fieldKeys === $expectedFieldKeys, 'schema.json fields must match expected set exactly');

    assertTrue(($data['fields']['invoice_number']['type'] ?? '') === 'string', 'invoice_number must be string');
    assertTrue(($data['fields']['invoice_number']['required'] ?? false) === true, 'invoice_number must be required');

    assertTrue(($data['fields']['issue_date']['type'] ?? '') === 'date', 'issue_date must be date');
    assertTrue(($data['fields']['issue_date']['required'] ?? false) === true, 'issue_date must be required');

    assertTrue(($data['fields']['amount']['type'] ?? '') === 'number', 'amount must be number');
    assertTrue(($data['fields']['amount']['required'] ?? false) === true, 'amount must be required');

    assertTrue(($data['fields']['payment_status']['type'] ?? '') === 'select', 'payment_status must be select');
    assertTrue(($data['fields']['payment_status']['required'] ?? false) === true, 'payment_status must be required');
    $paymentValues = array_map(
        static fn (array $option): mixed => $option['value'] ?? null,
        $data['fields']['payment_status']['options'] ?? []
    );
    $expectedPaymentValues = ['pendiente', 'pagada'];
    sort($paymentValues);
    sort($expectedPaymentValues);
    assertTrue($paymentValues === $expectedPaymentValues, 'payment_status options must match expected set exactly');

    assertTrue($data['custom_fields'] === [], 'invoices must not declare custom_fields');

    assertTrue(count($data['relations']) === 1, 'invoices must declare exactly one relation');
    $relation = $data['relations'][0];
    assertTrue(($relation['key'] ?? '') === 'id_order', 'relation key must be id_order');
    assertTrue(($relation['type'] ?? '') === 'belongs_to', 'relation type must be belongs_to');
    assertTrue(($relation['target_entity'] ?? '') === 'orders', 'relation target_entity must be orders');
    assertTrue(($relation['target_field'] ?? '') === 'id', 'relation target_field must be id');
    assertTrue(($relation['required'] ?? false) === true, 'id_order relation must be required (an invoice always has an order)');
});

TestSuite::run('Plugin invoices - Hooks.php existe', function (): void {
    assertTrue(file_exists(INVOICES_PLUGIN_DIR . '/Hooks.php'), 'Hooks.php not found');
});

TestSuite::run('Hooks - plugin_name no coincide no hace nada', function (): void {
    $pdo   = new InvoicesPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'other_entity', 'plugin_name' => 'other_plugin', 'data' => ['invoice_number' => 'F-1']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $result = $dispatcher->execute('beforeSave', $ctx);
    assertTrue($result['slug'] === 'other_entity', 'ctx should pass through unchanged');
    assertTrue(count($pdo->executedSqls) === 0, 'no SQL should be executed for other plugin');
});

TestSuite::run('Hooks - invoice_number vacío no ejecuta consulta', function (): void {
    $pdo   = new InvoicesPdoStub();
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'invoices', 'plugin_name' => 'invoices', 'data' => ['invoice_number' => '']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assertTrue($result['data']['invoice_number'] === '', 'invoice_number should remain empty');
    assertTrue(count($pdo->executedSqls) === 0, 'no SQL for empty invoice_number');
});

TestSuite::run('Hooks - invoice_number único permite guardar', function (): void {
    $pdo = new InvoicesPdoStub();
    $pdo->setFetchColumnReturn(0); // no duplicates
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'invoices', 'plugin_name' => 'invoices', 'data' => ['invoice_number' => 'F-NEW']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $result = $dispatcher->execute('beforeSave', $ctx);

    assertTrue($result['data']['invoice_number'] === 'F-NEW', 'context preserved');
    assertTrue(count($pdo->executedSqls) === 1, 'exactly one query executed');
});

TestSuite::run('Hooks - invoice_number duplicado lanza HookException', function (): void {
    $pdo = new InvoicesPdoStub();
    $pdo->setFetchColumnReturn(1); // duplicate found
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'invoices', 'plugin_name' => 'invoices', 'data' => ['invoice_number' => 'F-DUP']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $thrown = false;
    try {
        $dispatcher->execute('beforeSave', $ctx);
    } catch (HookException $e) {
        $thrown = true;
        assertTrue(str_contains($e->getMessage(), 'F-DUP'), 'message should contain the invoice number');
    }
    assertTrue($thrown, 'HookException must be thrown for duplicate invoice_number');
});

TestSuite::run('Hooks - invoice_number único en update excluye el propio registro', function (): void {
    $pdo = new InvoicesPdoStub();
    $pdo->setFetchColumnReturn(0);
    $hooks = new Hooks($pdo);
    $ctx   = ['slug' => 'invoices', 'plugin_name' => 'invoices', 'data' => ['id' => 'uuid-789', 'invoice_number' => 'F-SAME']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);
    $dispatcher->execute('beforeSave', $ctx);

    $executedParams = $pdo->executedParams[0] ?? [];
    assertTrue(isset($executedParams[':id']), 'id param must be bound when id is present');
    assertTrue($executedParams[':id'] === 'uuid-789', 'id param value is correct');
});

TestSuite::run('Hooks - sigue aplicando la unicidad si el slug fue renombrado (STORY 10.3)', function (): void {
    $pdo = new InvoicesPdoStub();
    $pdo->setFetchColumnReturn(1); // duplicate found
    $hooks = new Hooks($pdo);
    // plugin_name still 'invoices' (fixed identity) even though slug was
    // renamed away from the folder name via PluginConfig.
    $ctx   = ['slug' => 'facturas_2026', 'plugin_name' => 'invoices', 'data' => ['invoice_number' => 'F-DUP']];

    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $thrown = false;
    try {
        $dispatcher->execute('beforeSave', $ctx);
    } catch (HookException) {
        $thrown = true;
    }

    assertTrue($thrown, 'Uniqueness must still be enforced after a slug rename');
    $executedParams = $pdo->executedParams[0] ?? [];
    assertTrue(($executedParams[':slug'] ?? '') === 'facturas_2026', 'query must use the live (renamed) slug');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
