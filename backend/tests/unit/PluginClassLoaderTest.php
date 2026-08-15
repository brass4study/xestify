<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/src/plugins/contracts/PluginLifecycleInterface.php';
require_once BASE_PATH . '/src/plugins/lifecycle/PluginClassLoader.php';

use Xestify\plugins\lifecycle\PluginClassLoader;

const CLASS_LOADER_TEST_MANIFEST_BASE = [
    'label' => 'ClassLoader fixture',
    'version' => '1.0.0',
    'type' => 'extension',
    'core_version' => '1.0.0',
];

echo str_repeat('-', 40) . "\n";

TestSuite::run('instantiateLifecycle() injects PDO when the constructor declares it, same as instantiateHooks()', function (): void {
    $slug = 'lifecycle_with_pdo';
    $lifecycleSource = "<?php\ndeclare(strict_types=1);\nnamespace Xestify\\plugins\\{$slug};\nuse PDO;\nuse Xestify\\plugins\\contracts\\PluginLifecycleInterface;\nfinal class Lifecycle implements PluginLifecycleInterface {\n"
        . "    public PDO \$pdo;\n"
        . "    public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }\n"
        . "    public function onInstall(): void {}\n"
        . "    public function onActivate(): void {}\n"
        . "    public function onDeactivate(): void {}\n"
        . "}\n";

    $root = createPluginFixture(
        ['name' => $slug] + CLASS_LOADER_TEST_MANIFEST_BASE,
        false,
        false,
        null,
        $lifecycleSource
    );

    try {
        // No real driver needed: PluginClassLoader never queries $pdo here, it only
        // injects it by reference into the plugin constructor (checked via identity below).
        $pdo = (new \ReflectionClass(\PDO::class))->newInstanceWithoutConstructor(); // NOSONAR - inert placeholder, only its object identity is asserted, no query ever runs against it
        $loader = new PluginClassLoader($root, $pdo);
        $lifecycle = $loader->instantiateLifecycle($slug);

        assertTrue($lifecycle !== null, 'Lifecycle must be instantiated');
        assertTrue($lifecycle->pdo === $pdo, 'PDO must be injected when the constructor declares a PDO parameter'); // @phpstan-ignore-line
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run(
    'instantiateLifecycle() fails the same way instantiateHooks() does for a constructor requiring an unrelated parameter, not a bare PDO TypeError',
    function (): void {
        $slug = 'mismatched_ctor';

        $buildClassSource = static function (string $className, bool $isLifecycle) use ($slug): string {
            $implements = $isLifecycle ? ' implements PluginLifecycleInterface' : '';
            $extraMethods = $isLifecycle
                ? "    public function onInstall(): void {}\n    public function onActivate(): void {}\n    public function onDeactivate(): void {}\n"
                : "    public function register(\$dispatcher): void {}\n";

            return "<?php\ndeclare(strict_types=1);\nnamespace Xestify\\plugins\\{$slug};\nuse Xestify\\plugins\\contracts\\PluginLifecycleInterface;\nfinal class {$className}{$implements} {\n"
                . "    public function __construct(private string \$config) {}\n"
                . $extraMethods
                . "}\n";
        };

        $root = createPluginFixture(
            ['name' => $slug] + CLASS_LOADER_TEST_MANIFEST_BASE,
            true,
            false,
            null,
            $buildClassSource('Lifecycle', true),
            $buildClassSource('Hooks', false)
        );

        try {
            // No real driver needed: PluginClassLoader never queries $pdo here, it only
        // injects it by reference into the plugin constructor (checked via identity below).
        $pdo = (new \ReflectionClass(\PDO::class))->newInstanceWithoutConstructor(); // NOSONAR - inert placeholder, only its object identity is asserted, no query ever runs against it
            $loader = new PluginClassLoader($root, $pdo);

            $hooksExceptionClass = null;
            try {
                $loader->instantiateHooks($slug);
            } catch (\Throwable $e) {
                $hooksExceptionClass = $e::class;
            }

            $lifecycleExceptionClass = null;
            try {
                $loader->instantiateLifecycle($slug);
            } catch (\Throwable $e) {
                $lifecycleExceptionClass = $e::class;
            }

            assertEquals(
                \ArgumentCountError::class,
                $hooksExceptionClass,
                'baseline: instantiateHooks() already falls back to a no-arg instantiation when no constructor parameter is PDO-typed'
            );
            assertEquals(
                $hooksExceptionClass,
                $lifecycleExceptionClass,
                'instantiateLifecycle() must resolve constructor arguments the same way instantiateHooks() does '
                    . '(both ArgumentCountError from the shared reflective fallback), instead of instantiateLifecycle() '
                    . 'blindly forcing PDO into an unrelated parameter and raising a bare TypeError'
            );
        } finally {
            removePluginFixture($root);
        }
    }
);

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
