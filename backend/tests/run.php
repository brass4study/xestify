<?php

declare(strict_types=1);

/**
 * Grouped backend test runner.
 *
 * Usage:
 *   php backend/tests/run.php unit
 *   php backend/tests/run.php integration-db
 *   php backend/tests/run.php integration-plugins
 *   php backend/tests/run.php all
 */

$group = $argv[1] ?? 'all';
$root = dirname(__DIR__, 2);

$groups = [
    'unit' => [
        'backend/tests/unit/RouterTest.php',
        'backend/tests/unit/RuntimePathNormalizerTest.php',
        'backend/tests/unit/RequestFactoryTest.php',
        'backend/tests/unit/AuthMiddlewareTest.php',
        'backend/tests/unit/EntityServiceHooksTest.php',
        'backend/tests/unit/ValidationServiceTest.php',
        'backend/tests/unit/FieldValidatorsTest.php',
        'backend/tests/unit/SchemaFieldExtractorTest.php',
        'backend/tests/unit/HookDispatcherTest.php',
        'backend/tests/unit/HookFilterTest.php',
        'backend/tests/unit/JwtServiceTest.php',
        'backend/tests/unit/ProfileSecretVerifierTest.php',
        'backend/tests/unit/ProfileUpdateAuthorizerTest.php',
        'backend/tests/unit/UserAuthorizerTest.php',
        'backend/tests/unit/RequestResponseTest.php',
        'backend/tests/unit/HealthControllerTest.php',
        'backend/tests/unit/ContainerTest.php',
        'backend/tests/unit/PersonsPluginTest.php',
        'backend/tests/unit/ProductsPluginTest.php',
        'backend/tests/unit/PluginDiscoveryServiceTest.php',
        'backend/tests/unit/PluginManifestReaderTest.php',
        'backend/tests/unit/PluginSchemaReaderTest.php',
        'backend/tests/unit/PluginSchemaMergeServiceTest.php',
        'backend/tests/unit/PluginConfigFieldNormalizerTest.php',
        'backend/tests/unit/PluginClassLoaderTest.php',
        'backend/tests/unit/TestRunnerExitCodeTest.php',
    ],
    'integration-db' => [
        'backend/tests/integration/DatabaseTest.php',
        'backend/tests/integration/MigrationIdempotenceTest.php',
        'backend/tests/integration/EntityDataTableTest.php',
        'backend/tests/integration/EntityMetadataTableTest.php',
        'backend/tests/integration/PluginsRegistryTableTest.php',
        'backend/tests/integration/PluginUpdateHistoryTableTest.php',
        'backend/tests/integration/SystemEntitiesTableTest.php',
        'backend/tests/integration/GenericRepositoryTest.php',
        'backend/tests/integration/UserRepositoryTest.php',
        'backend/tests/integration/ConfigurationRepositoryTest.php',
        'backend/tests/integration/ConfigurationControllerTest.php',
        'backend/tests/integration/UserControllerTest.php',
        'backend/tests/integration/AuthControllerTest.php',
        'backend/tests/integration/EntityServiceTest.php',
        'backend/tests/integration/EntityControllerTest.php',
    ],
    'integration-plugins' => [
        'backend/tests/integration/PluginDependencyValidatorTest.php',
        'backend/tests/integration/PluginIdentityServiceTest.php',
        'backend/tests/integration/PluginDeletionServiceTest.php',
        'backend/tests/integration/PluginRegistrationServiceTest.php',
        'backend/tests/integration/PluginRelationsConfigTest.php',
        'backend/tests/integration/ReverseRelationTest.php',
        'backend/tests/integration/PluginSyncServiceTest.php',
        'backend/tests/integration/PluginUpdateServiceTest.php',
        'backend/tests/integration/PluginRollbackServiceTest.php',
        'backend/tests/integration/PluginStatusServiceTest.php',
        'backend/tests/integration/PluginOutdatedServiceTest.php',
        'backend/tests/integration/PluginLifecycleInvokerTest.php',
        'backend/tests/integration/PluginDependenciesTest.php',
        'backend/tests/integration/PluginLifecycleTest.php',
        'backend/tests/integration/PluginBootTest.php',
        'backend/tests/integration/HookFilterApiTest.php',
        'backend/tests/integration/CommentsPluginTest.php',
        'backend/tests/integration/AppWiringTest.php',
        'backend/tests/integration/PluginManagerApiTest.php',
    ],
];

if ($group === 'all') {
    $files = array_merge(...array_values($groups));
} elseif (isset($groups[$group])) {
    $files = $groups[$group];
} else {
    fwrite(STDERR, "Unknown test group: {$group}\n");
    fwrite(STDERR, 'Known groups: ' . implode(', ', array_merge(['all'], array_keys($groups))) . "\n");
    exit(2);
}

$failed = 0;

foreach ($files as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    echo "\n>>> {$file}\n";
    passthru('php ' . escapeshellarg($path), $exitCode);
    if ($exitCode !== 0) {
        $failed++;
    }
}

echo "\nGrouped runner result: " . (count($files) - $failed) . ' passed files, ' . $failed . " failed files\n";
exit($failed > 0 ? 1 : 0);
