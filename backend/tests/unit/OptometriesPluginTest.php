<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BACKEND_PATH', dirname(__DIR__, 2));
define('BASE_PATH', dirname(BACKEND_PATH));

require_once __DIR__ . '/helpers.php';
require_once BACKEND_PATH . '/src/exceptions/HookException.php';
require_once BACKEND_PATH . '/src/core/HookDispatcher.php';

// Explicitly require plugin files (not in autoload path)
require_once BASE_PATH . '/plugins/optometries/Hooks.php';

use Xestify\plugins\optometries\Hooks;
use Xestify\core\HookDispatcher;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

/**
 * PDO stub for Hooks::allowedInstances()/resolvePriority(). Both queries
 * flow through the same stub statement regardless of SQL text: fetchColumn()
 * (priority) returns null (falls back to the hook's DEFAULT_PRIORITY, not
 * under test here); fetchAll() (allowedInstances) returns the configured
 * $rows, letting each test control which plugin instances "exist".
 */
final class OptometriesPdoStub extends PDO
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function __construct()
    {
        // intentionally no parent call
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new OptometriesStmtStub($this);
    }
}

final class OptometriesStmtStub extends \PDOStatement
{
    public function __construct(private OptometriesPdoStub $pdoStub)
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchAll(int $mode = PDO::FETCH_ASSOC, mixed ...$args): array
    {
        return $this->pdoStub->rows;
    }
}

/** @return array<string, mixed> a fake active 'optometries' instance row */
function optometriesInstanceRow(string $slug = 'optometries', string $targetEntity = 'clients'): array
{
    return [
        'slug' => $slug,
        'manifest_json' => json_encode([
            'name' => 'optometries',
            'type' => 'extension',
            'target_entity' => $targetEntity,
        ], JSON_UNESCAPED_UNICODE),
        'schema_json' => json_encode([
            'fields' => ['date' => ['type' => 'date', 'required' => true]],
            'relations' => [
                [
                    'key' => 'ophthalmologist', 'type' => 'belongs_to', 'target_entity' => 'ophthalmologists',
                    'target_field' => 'id', 'required' => false, 'label' => 'Oftalmólogo', 'layer' => 'general',
                ],
                [
                    'key' => 'optometrist', 'type' => 'belongs_to', 'target_entity' => 'ophthalmologists',
                    'target_field' => 'id', 'required' => false, 'label' => 'Optometrista', 'layer' => 'general',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE),
    ];
}

// ---------------------------------------------------------------------------
// PLUGIN_DIR constant for structure tests
// ---------------------------------------------------------------------------
define('OPTOMETRIES_PLUGIN_DIR', BASE_PATH . '/plugins/optometries');

/** @return array<string, mixed> decoded plugins/optometries/manifest.json */
function optometriesManifestData(): array
{
    return json_decode((string) file_get_contents(OPTOMETRIES_PLUGIN_DIR . '/manifest.json'), true);
}

/** @return array<string, mixed> decoded plugins/optometries/schema.json */
function optometriesSchemaData(): array
{
    return json_decode((string) file_get_contents(OPTOMETRIES_PLUGIN_DIR . '/schema.json'), true);
}

const OPTOMETRIES_EXPECTED_FIELD_KEYS = [
    'date',
    'od_distance_sphere', 'od_distance_cylinder', 'od_distance_axis',
    'od_constant_sphere', 'od_constant_cylinder', 'od_constant_axis',
    'od_near_sphere', 'od_near_cylinder', 'od_near_axis',
    'od_contact_lens_sphere', 'od_contact_lens_cylinder', 'od_contact_lens_axis',
    'od_addition',
    'os_distance_sphere', 'os_distance_cylinder', 'os_distance_axis',
    'os_constant_sphere', 'os_constant_cylinder', 'os_constant_axis',
    'os_near_sphere', 'os_near_cylinder', 'os_near_axis',
    'os_contact_lens_sphere', 'os_contact_lens_cylinder', 'os_contact_lens_axis',
    'os_addition',
    'pupillary_distance',
    'notes',
];

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin optometries - manifest.json existe', function (): void {
    $path = OPTOMETRIES_PLUGIN_DIR . '/manifest.json';
    assertTrue(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin optometries - manifest.json campos requeridos', function (): void {
    $data = optometriesManifestData();
    assertTrue(is_array($data), 'manifest.json must be a JSON object');
    foreach (['name', 'label', 'version', 'type', 'core_version'] as $field) {
        assertTrue(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assertTrue($data['name'] === 'optometries', 'name must be optometries');
    assertTrue($data['type'] === 'extension', 'type must be extension');
    assertTrue($data['target_entity'] === 'clients', 'target_entity must be clients (the real live slug, not the backlog literal "persons")');
});

TestSuite::run('Plugin optometries - manifest.json declara el catálogo de layers (STORY 10.5, no va en schema.json)', function (): void {
    $data = optometriesManifestData();

    assertTrue(isset($data['layers']) && is_array($data['layers']), 'manifest.json must declare a layers catalog');
    assertTrue(count($data['layers']) === 4, 'layers catalog must have exactly 4 entries');

    $byKey = [];
    foreach ($data['layers'] as $layer) {
        $byKey[$layer['key']] = $layer['label'];
    }
    assertEquals(['top', 'od', 'os', 'general'], array_keys($byKey), 'layers catalog keys must be top/od/os/general in that order');
    assertEquals('Arriba', $byKey['top'] ?? null, 'top layer label');
    assertEquals('Ojo derecho', $byKey['od'] ?? null, 'od layer label');
    assertEquals('Ojo izquierdo', $byKey['os'] ?? null, 'os layer label');
    assertEquals('General', $byKey['general'] ?? null, 'general layer label');
});

TestSuite::run('Plugin optometries - schema.json existe', function (): void {
    $path = OPTOMETRIES_PLUGIN_DIR . '/schema.json';
    assertTrue(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin optometries - schema.json solo declara fields y relations (patron de extension)', function (): void {
    $data = optometriesSchemaData();
    assertTrue(is_array($data), 'schema.json must be a JSON object');

    $keys = array_keys($data);
    sort($keys);
    assertTrue($keys === ['fields', 'relations'], 'extension schema.json must declare exactly fields + relations, no identities/custom_fields');
});

TestSuite::run('Plugin optometries - schema.json fields coincide exactamente con el set esperado', function (): void {
    $data = optometriesSchemaData();

    $fieldKeys = array_keys($data['fields']);
    $expected = OPTOMETRIES_EXPECTED_FIELD_KEYS;
    sort($fieldKeys);
    sort($expected);
    assertTrue($fieldKeys === $expected, 'schema.json fields must match the expected set exactly');
});

TestSuite::run('Plugin optometries - schema.json spot-check de tipos, required y min/max', function (): void {
    $data = optometriesSchemaData();
    $fields = $data['fields'];

    assertTrue($fields['date']['type'] === 'date', 'date must be type date');
    assertTrue($fields['date']['required'] === true, 'date must be required');

    assertTrue($fields['od_distance_sphere']['type'] === 'number', 'od_distance_sphere must be number');
    assertTrue($fields['od_distance_sphere']['min'] === -20 && $fields['od_distance_sphere']['max'] === 20, 'sphere bounds must be -20..20');

    assertTrue($fields['od_distance_cylinder']['min'] === -10 && $fields['od_distance_cylinder']['max'] === 10, 'cylinder bounds must be -10..10');
    assertTrue($fields['od_distance_axis']['min'] === 0 && $fields['od_distance_axis']['max'] === 180, 'axis bounds must be 0..180');
    assertTrue($fields['od_addition']['min'] === 0 && $fields['od_addition']['max'] === 4, 'addition bounds must be 0..4');
    assertTrue($fields['pupillary_distance']['min'] === 40 && $fields['pupillary_distance']['max'] === 80, 'pupillary_distance bounds must be 40..80');

    foreach (OPTOMETRIES_EXPECTED_FIELD_KEYS as $key) {
        assertTrue($key !== 'ophthalmologist' && $key !== 'optometrist', "{$key} must not be declared as a field — it is a relation");
    }
});

TestSuite::run('Plugin optometries - los campos del grid OD/OI son resortable=false (posiciones fijas), pupillary_distance y notes son reordenables', function (): void {
    $data = optometriesSchemaData();
    $fields = $data['fields'];
    $reorderableKeys = ['pupillary_distance', 'notes'];

    foreach (OPTOMETRIES_EXPECTED_FIELD_KEYS as $key) {
        if (in_array($key, $reorderableKeys, true)) {
            continue;
        }

        assertTrue(
            ($fields[$key]['resortable'] ?? true) === false,
            "{$key} must declare resortable=false — plugin.js hardcodes every field's grid position, reordering has no visual effect"
        );
    }

    foreach ($reorderableKeys as $key) {
        assertTrue(
            ($fields[$key]['resortable'] ?? true) === true,
            "{$key} must not declare resortable=false — it is not part of the fixed OD/OI grid"
        );
    }
});

TestSuite::run('Plugin optometries - schema.json asigna layer por campo (top/od/os/general), sin catálogo de nivel superior', function (): void {
    $data = optometriesSchemaData();
    $fields = $data['fields'];

    assertTrue(!array_key_exists('layers', $data), 'schema.json must NOT declare a top-level layers catalog — it lives in manifest.json (STORY 10.5)');

    assertEquals('top', $fields['date']['layer'] ?? null, 'date must be in the top layer');
    assertEquals('general', $fields['pupillary_distance']['layer'] ?? null, 'pupillary_distance must be in the general layer');
    assertEquals('general', $fields['notes']['layer'] ?? null, 'notes must be in the general layer');

    foreach (OPTOMETRIES_EXPECTED_FIELD_KEYS as $key) {
        if (str_starts_with($key, 'od_')) {
            assertEquals('od', $fields[$key]['layer'] ?? null, "{$key} must be in the od layer");
        } elseif (str_starts_with($key, 'os_')) {
            assertEquals('os', $fields[$key]['layer'] ?? null, "{$key} must be in the os layer");
        }
    }
});

TestSuite::run('Plugin optometries - schema.json relations coinciden exactamente con el set esperado', function (): void {
    $data = optometriesSchemaData();
    $relations = $data['relations'];

    assertTrue(count($relations) === 2, 'optometries must declare exactly 2 relations');

    $byKey = [];
    foreach ($relations as $relation) {
        $byKey[$relation['key']] = $relation;
    }

    foreach (['ophthalmologist', 'optometrist'] as $key) {
        assertTrue(isset($byKey[$key]), "relation {$key} must exist");
        assertTrue($byKey[$key]['type'] === 'belongs_to', "{$key} must be belongs_to");
        assertTrue($byKey[$key]['target_entity'] === 'ophthalmologists', "{$key} must target ophthalmologists");
        assertTrue($byKey[$key]['target_field'] === 'id', "{$key} target_field must be id");
        assertTrue($byKey[$key]['required'] === false, "{$key} must be optional");
        assertTrue($byKey[$key]['layer'] === 'general', "{$key} layer must be general (below both eye tables)");
    }
});

TestSuite::run('Plugin optometries - Hooks.php existe', function (): void {
    assertTrue(file_exists(OPTOMETRIES_PLUGIN_DIR . '/Hooks.php'), 'Hooks.php not found');
});

TestSuite::run('Hooks - registerTabs inyecta tab con relations embebidas cuando la entidad coincide', function (): void {
    $pdo = new OptometriesPdoStub();
    $pdo->rows = [optometriesInstanceRow('optometries', 'clients')];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);

    assertTrue(count($tabs) === 1, 'must inject exactly one tab');
    $tab = $tabs[0];
    assertEquals('optometries', $tab['id'], 'tab id must be the instance slug');
    assertEquals('Optometrías', $tab['label'], 'tab label must be Optometrías');
    assertEquals('fa-eye', $tab['icon'], 'tab icon must be fa-eye');
    assertEquals('optometries', $tab['plugin_name'], 'tab plugin_name must be optometries');
    assertTrue(str_contains($tab['endpoint'], '/plugins/optometries/clients/{id}'), 'endpoint must target the clients entity');
    assertEquals('clients', $tab['entity'], 'tab must carry the entity slug (PluginItemEdit.js needs it to call GET /entities/{entity}/tabs)');

    assertTrue(count($tab['relations']) === 2, 'tab must embed both relations');
    $keys = array_column($tab['relations'], 'key');
    sort($keys);
    assertEquals(['ophthalmologist', 'optometrist'], $keys, 'embedded relation keys must match schema.json');

    $ophthalmologist = array_values(array_filter($tab['relations'], fn(array $r): bool => $r['key'] === 'ophthalmologist'))[0];
    assertEquals('ophthalmologists', $ophthalmologist['target_entity'], 'embedded relation must carry target_entity');
    assertEquals('general', $ophthalmologist['layer'], 'embedded relation must carry layer');

    assertEquals([], $tab['fields'], 'the fixture\'s only field (date) has no origin (base, like all 29 real fields) — fields must stay empty, only additional fields are embedded');
});

TestSuite::run('Hooks - registerTabs embebe solo campos origin:additional en fields, no los base (STORY 10.5 follow-up: "Añadir campo" debe verse en la ficha)', function (): void {
    $pdo = new OptometriesPdoStub();
    $pdo->rows = [[
        'slug' => 'optometries',
        'manifest_json' => json_encode([
            'name' => 'optometries',
            'type' => 'extension',
            'target_entity' => 'clients',
        ], JSON_UNESCAPED_UNICODE),
        'schema_json' => json_encode([
            'fields' => [
                'date' => ['type' => 'date', 'required' => true],
                'warnings' => [
                    'type' => 'text', 'required' => false, 'label' => 'Avisos',
                    'layer' => 'general', 'origin' => 'additional',
                ],
            ],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE),
    ]];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);

    assertTrue(count($tabs) === 1, 'must inject exactly one tab');
    $tab = $tabs[0];

    assertEquals(1, count($tab['fields']), 'only the additional field must be embedded, not the base "date" field');
    $warnings = $tab['fields'][0];
    assertEquals('warnings', $warnings['key'], 'embedded field key must be warnings');
    assertEquals('text', $warnings['type'], 'embedded field must carry its type');
    assertEquals('Avisos', $warnings['label'], 'embedded field must carry its label');
    assertEquals('general', $warnings['layer'], 'embedded field must carry its layer');
    assertTrue($warnings['required'] === false, 'embedded field must carry required');
});

TestSuite::run('Hooks - registerTabs embebe summary_fields con campos base + additional, respetando summaryView (STORY tabla dinamica)', function (): void {
    $pdo = new OptometriesPdoStub();
    $pdo->rows = [[
        'slug' => 'optometries',
        'manifest_json' => json_encode([
            'name' => 'optometries',
            'type' => 'extension',
            'target_entity' => 'clients',
        ], JSON_UNESCAPED_UNICODE),
        'schema_json' => json_encode([
            'fields' => [
                'date' => ['type' => 'date', 'required' => true, 'label' => 'Fecha'],
                'od_distance_sphere' => ['type' => 'number', 'required' => false, 'label' => 'OD · Para lejos · Esfera', 'summaryView' => false],
                'notes' => ['type' => 'text', 'required' => false, 'label' => 'Notas', 'summaryView' => true],
                'warnings' => [
                    'type' => 'text', 'required' => false, 'label' => 'Avisos',
                    'layer' => 'general', 'origin' => 'additional', 'summaryView' => true,
                ],
            ],
            'relations' => [],
            'ui_field_order' => ['date', 'od_distance_sphere', 'notes', 'warnings'],
        ], JSON_UNESCAPED_UNICODE),
    ]];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);

    assertTrue(count($tabs) === 1, 'must inject exactly one tab');
    $tab = $tabs[0];

    assertEquals(4, count($tab['summary_fields']), 'summary_fields must include base fields too, unlike fields (which only has "warnings")');
    $byKey = [];
    foreach ($tab['summary_fields'] as $field) {
        $byKey[$field['key']] = $field;
    }
    assertEquals(['date', 'od_distance_sphere', 'notes', 'warnings'], array_keys($byKey), 'summary_fields must preserve schema_json.fields declaration order');
    assertTrue($byKey['date']['summaryView'] === true, 'date has no explicit summaryView -> defaults to true');
    assertTrue($byKey['od_distance_sphere']['summaryView'] === false, 'od_distance_sphere summaryView must be honored as false');
    assertTrue($byKey['notes']['summaryView'] === true, 'notes summaryView must be honored as true');
    assertTrue($byKey['warnings']['summaryView'] === true, 'additional field summaryView must also be embedded');

    assertEquals(['date', 'od_distance_sphere', 'notes', 'warnings'], $tab['ui_field_order'], 'ui_field_order must be embedded verbatim from schema_json');
});

TestSuite::run('Hooks - registerTabs deja ui_field_order vacio cuando schema_json no lo declara', function (): void {
    $pdo = new OptometriesPdoStub();
    $pdo->rows = [optometriesInstanceRow('optometries', 'clients')];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);

    assertEquals([], $tabs[0]['ui_field_order'], 'ui_field_order must be [] when schema_json does not declare it');
});

TestSuite::run('Hooks - registerTabs no inyecta tab para una entidad distinta de target_entity', function (): void {
    $pdo = new OptometriesPdoStub();
    $pdo->rows = [optometriesInstanceRow('optometries', 'clients')];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'products']);

    assertTrue($tabs === [], 'must not inject a tab for a non-matching entity');
});

TestSuite::run('Hooks - registerTabs no hace nada sin entidad en los args', function (): void {
    $pdo = new OptometriesPdoStub();
    $pdo->rows = [optometriesInstanceRow('optometries', 'clients')];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], []);

    assertTrue($tabs === [], 'must return the tabs list untouched when entity is missing');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
