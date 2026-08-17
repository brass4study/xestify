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
require_once BASE_PATH . '/plugins/contact_lenses/Hooks.php';

use Xestify\plugins\contact_lenses\Hooks;
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
final class ContactLensesPdoStub extends PDO
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function __construct()
    {
        // intentionally no parent call
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new ContactLensesStmtStub($this);
    }
}

final class ContactLensesStmtStub extends \PDOStatement
{
    public function __construct(private ContactLensesPdoStub $pdoStub)
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

/** @return array<string, mixed> a fake active 'contact_lenses' instance row */
function contactLensesInstanceRow(string $slug = 'contact_lenses', string $targetEntity = 'clients'): array
{
    return [
        'slug' => $slug,
        'manifest_json' => json_encode([
            'name' => 'contact_lenses',
            'type' => 'extension',
            'target_entity' => $targetEntity,
        ], JSON_UNESCAPED_UNICODE),
        'schema_json' => json_encode([
            'fields' => ['date' => ['type' => 'date', 'required' => true]],
            'relations' => [
                [
                    'key' => 'od_brand', 'type' => 'belongs_to', 'target_entity' => 'brands',
                    'target_field' => 'id', 'required' => false, 'label' => 'Marca', 'layer' => 'od',
                ],
                [
                    'key' => 'os_brand', 'type' => 'belongs_to', 'target_entity' => 'brands',
                    'target_field' => 'id', 'required' => false, 'label' => 'Marca', 'layer' => 'os',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE),
    ];
}

// ---------------------------------------------------------------------------
// PLUGIN_DIR constant for structure tests
// ---------------------------------------------------------------------------
define('CONTACT_LENSES_PLUGIN_DIR', BASE_PATH . '/plugins/contact_lenses');

/** @return array<string, mixed> decoded plugins/contact_lenses/manifest.json */
function contactLensesManifestData(): array
{
    return json_decode((string) file_get_contents(CONTACT_LENSES_PLUGIN_DIR . '/manifest.json'), true);
}

/** @return array<string, mixed> decoded plugins/contact_lenses/schema.json */
function contactLensesSchemaData(): array
{
    return json_decode((string) file_get_contents(CONTACT_LENSES_PLUGIN_DIR . '/schema.json'), true);
}

const CONTACT_LENSES_EXPECTED_FIELD_KEYS = [
    'date',
    'od_lens_sphere', 'od_lens_cylinder', 'od_lens_axis', 'od_lens_addition',
    'od_keratometry_sphere', 'od_keratometry_cylinder', 'od_keratometry_axis', 'od_keratometry_addition',
    'od_base_curve', 'od_diameter', 'od_replacement_schedule', 'od_pack',
    'os_lens_sphere', 'os_lens_cylinder', 'os_lens_axis', 'os_lens_addition',
    'os_keratometry_sphere', 'os_keratometry_cylinder', 'os_keratometry_axis', 'os_keratometry_addition',
    'os_base_curve', 'os_diameter', 'os_replacement_schedule', 'os_pack',
    'notes',
];

const CONTACT_LENSES_EXPECTED_RELATION_TARGETS = [
    'od_brand' => ['target_entity' => 'brands', 'layer' => 'od'],
    'os_brand' => ['target_entity' => 'brands', 'layer' => 'os'],
    'od_manufacturer' => ['target_entity' => 'manufacturers', 'layer' => 'od'],
    'os_manufacturer' => ['target_entity' => 'manufacturers', 'layer' => 'os'],
    'od_distributor' => ['target_entity' => 'distributors', 'layer' => 'od'],
    'os_distributor' => ['target_entity' => 'distributors', 'layer' => 'os'],
];

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin contact_lenses - manifest.json existe', function (): void {
    $path = CONTACT_LENSES_PLUGIN_DIR . '/manifest.json';
    assertTrue(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin contact_lenses - manifest.json campos requeridos', function (): void {
    $data = contactLensesManifestData();
    assertTrue(is_array($data), 'manifest.json must be a JSON object');
    foreach (['name', 'label', 'version', 'type', 'core_version'] as $field) {
        assertTrue(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assertTrue($data['name'] === 'contact_lenses', 'name must be contact_lenses (underscore — a hyphen breaks the PHP namespace and PluginIdentityService::SLUG_PATTERN)');
    assertTrue($data['label'] === 'Lentillas', 'label must be Lentillas');
    assertTrue($data['type'] === 'extension', 'type must be extension');
    assertTrue($data['target_entity'] === 'clients', 'target_entity must be clients (the real live slug, not the backlog literal "persons")');
});

TestSuite::run('Plugin contact_lenses - manifest.json declara el catálogo de layers (STORY 10.5, no va en schema.json)', function (): void {
    $data = contactLensesManifestData();

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

TestSuite::run('Plugin contact_lenses - schema.json existe', function (): void {
    $path = CONTACT_LENSES_PLUGIN_DIR . '/schema.json';
    assertTrue(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin contact_lenses - schema.json solo declara fields y relations (patron de extension)', function (): void {
    $data = contactLensesSchemaData();
    assertTrue(is_array($data), 'schema.json must be a JSON object');

    $keys = array_keys($data);
    sort($keys);
    assertTrue($keys === ['fields', 'relations'], 'extension schema.json must declare exactly fields + relations, no identities/custom_fields');
});

TestSuite::run('Plugin contact_lenses - schema.json fields coincide exactamente con el set esperado (26 campos)', function (): void {
    $data = contactLensesSchemaData();

    $fieldKeys = array_keys($data['fields']);
    $expected = CONTACT_LENSES_EXPECTED_FIELD_KEYS;
    assertTrue(count($expected) === 26, 'expected field set fixture must have exactly 26 keys');
    sort($fieldKeys);
    sort($expected);
    assertTrue($fieldKeys === $expected, 'schema.json fields must match the expected set exactly');
});

TestSuite::run('Plugin contact_lenses - schema.json spot-check de tipos, required y min/max', function (): void {
    $data = contactLensesSchemaData();
    $fields = $data['fields'];

    assertTrue($fields['date']['type'] === 'date', 'date must be type date');
    assertTrue($fields['date']['required'] === true, 'date must be required');

    assertTrue($fields['od_lens_sphere']['type'] === 'number', 'od_lens_sphere must be number');
    assertTrue($fields['od_lens_sphere']['min'] === -20 && $fields['od_lens_sphere']['max'] === 20, 'sphere bounds must be -20..20');
    assertTrue($fields['od_lens_cylinder']['min'] === -10 && $fields['od_lens_cylinder']['max'] === 10, 'cylinder bounds must be -10..10');
    assertTrue($fields['od_lens_axis']['min'] === 0 && $fields['od_lens_axis']['max'] === 180, 'axis bounds must be 0..180');
    assertTrue($fields['od_lens_addition']['min'] === 0 && $fields['od_lens_addition']['max'] === 4, 'addition bounds must be 0..4');
    assertTrue($fields['od_base_curve']['min'] === 6 && $fields['od_base_curve']['max'] === 10, 'base_curve bounds must be 6..10');
    assertTrue($fields['od_diameter']['min'] === 12 && $fields['od_diameter']['max'] === 16, 'diameter bounds must be 12..16');

    assertTrue($fields['od_replacement_schedule']['type'] === 'select', 'od_replacement_schedule must be select');
    assertTrue(count($fields['od_replacement_schedule']['options']) === 7, 'od_replacement_schedule must have 7 options (Ninguno + 6 schedules)');
    assertTrue($fields['od_pack']['type'] === 'select', 'od_pack must be select');
    assertTrue(count($fields['od_pack']['options']) === 7, 'od_pack must have 7 options (Ninguno + 6 pack sizes)');

    foreach (CONTACT_LENSES_EXPECTED_FIELD_KEYS as $key) {
        assertTrue(!array_key_exists($key, CONTACT_LENSES_EXPECTED_RELATION_TARGETS), "{$key} must not collide with a relation key");
    }
});

TestSuite::run('Plugin contact_lenses - los 25 campos od_/os_/date son resortable=false (posiciones fijas), notes es reordenable', function (): void {
    $data = contactLensesSchemaData();
    $fields = $data['fields'];
    $reorderableKeys = ['notes'];

    foreach (CONTACT_LENSES_EXPECTED_FIELD_KEYS as $key) {
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

TestSuite::run('Plugin contact_lenses - schema.json asigna layer por campo (top/od/os/general), sin catálogo de nivel superior', function (): void {
    $data = contactLensesSchemaData();
    $fields = $data['fields'];

    assertTrue(!array_key_exists('layers', $data), 'schema.json must NOT declare a top-level layers catalog — it lives in manifest.json (STORY 10.5)');

    assertEquals('top', $fields['date']['layer'] ?? null, 'date must be in the top layer');
    assertEquals('general', $fields['notes']['layer'] ?? null, 'notes must be in the general layer');

    foreach (CONTACT_LENSES_EXPECTED_FIELD_KEYS as $key) {
        if (str_starts_with($key, 'od_')) {
            assertEquals('od', $fields[$key]['layer'] ?? null, "{$key} must be in the od layer");
        } elseif (str_starts_with($key, 'os_')) {
            assertEquals('os', $fields[$key]['layer'] ?? null, "{$key} must be in the os layer");
        }
    }
});

TestSuite::run('Plugin contact_lenses - schema.json relations coinciden exactamente con el set esperado (6 relaciones)', function (): void {
    $data = contactLensesSchemaData();
    $relations = $data['relations'];

    assertTrue(count($relations) === 6, 'contact_lenses must declare exactly 6 relations');

    $byKey = [];
    foreach ($relations as $relation) {
        $byKey[$relation['key']] = $relation;
    }

    foreach (CONTACT_LENSES_EXPECTED_RELATION_TARGETS as $key => $expected) {
        assertTrue(isset($byKey[$key]), "relation {$key} must exist");
        assertTrue($byKey[$key]['type'] === 'belongs_to', "{$key} must be belongs_to");
        assertTrue($byKey[$key]['target_entity'] === $expected['target_entity'], "{$key} must target {$expected['target_entity']}");
        assertTrue($byKey[$key]['target_field'] === 'id', "{$key} target_field must be id");
        assertTrue($byKey[$key]['required'] === false, "{$key} must be optional");
        assertTrue($byKey[$key]['layer'] === $expected['layer'], "{$key} layer must be {$expected['layer']}");
    }
});

TestSuite::run('Plugin contact_lenses - Hooks.php existe', function (): void {
    assertTrue(file_exists(CONTACT_LENSES_PLUGIN_DIR . '/Hooks.php'), 'Hooks.php not found');
});

TestSuite::run('Hooks - registerTabs inyecta tab con relations embebidas cuando la entidad coincide', function (): void {
    $pdo = new ContactLensesPdoStub();
    $pdo->rows = [contactLensesInstanceRow('contact_lenses', 'clients')];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);

    assertTrue(count($tabs) === 1, 'must inject exactly one tab');
    $tab = $tabs[0];
    assertEquals('contact_lenses', $tab['id'], 'tab id must be the instance slug');
    assertEquals('Lentillas', $tab['label'], 'tab label must be Lentillas');
    assertEquals('fa-glasses', $tab['icon'], 'tab icon must be fa-glasses');
    assertEquals('contact_lenses', $tab['plugin_name'], 'tab plugin_name must be contact_lenses');
    assertTrue(str_contains($tab['endpoint'], '/plugins/contact_lenses/clients/{id}'), 'endpoint must target the clients entity');
    assertEquals('clients', $tab['entity'], 'tab must carry the entity slug (PluginItemEdit.js needs it to call GET /entities/{entity}/tabs)');

    assertTrue(count($tab['relations']) === 2, 'tab must embed both relations from the fixture');
    $keys = array_column($tab['relations'], 'key');
    sort($keys);
    assertEquals(['od_brand', 'os_brand'], $keys, 'embedded relation keys must match schema.json');

    $odBrand = array_values(array_filter($tab['relations'], fn(array $r): bool => $r['key'] === 'od_brand'))[0];
    assertEquals('brands', $odBrand['target_entity'], 'embedded relation must carry target_entity');
    assertEquals('od', $odBrand['layer'], 'embedded relation must carry layer');

    assertEquals([], $tab['fields'], 'the fixture\'s only field (date) has no origin (base), so fields must stay empty — only additional fields are embedded');
});

TestSuite::run('Hooks - registerTabs embebe solo campos origin:additional en fields, no los base', function (): void {
    $pdo = new ContactLensesPdoStub();
    $pdo->rows = [[
        'slug' => 'contact_lenses',
        'manifest_json' => json_encode([
            'name' => 'contact_lenses',
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

TestSuite::run('Hooks - registerTabs no inyecta tab para una entidad distinta de target_entity', function (): void {
    $pdo = new ContactLensesPdoStub();
    $pdo->rows = [contactLensesInstanceRow('contact_lenses', 'clients')];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'products']);

    assertTrue($tabs === [], 'must not inject a tab for a non-matching entity');
});

TestSuite::run('Hooks - registerTabs no hace nada sin entidad en los args', function (): void {
    $pdo = new ContactLensesPdoStub();
    $pdo->rows = [contactLensesInstanceRow('contact_lenses', 'clients')];

    $hooks = new Hooks($pdo);
    $dispatcher = new HookDispatcher();
    $hooks->register($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], []);

    assertTrue($tabs === [], 'must return the tabs list untouched when entity is missing');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
