<?php

declare(strict_types=1);

use Xestify\plugins\lifecycle\PluginOrderService;
use Xestify\plugins\lifecycle\PluginOutdatedService;
use Xestify\plugins\lifecycle\PluginRollbackService;
use Xestify\plugins\schema\InstalledPluginSchemaValidator;
use Xestify\plugins\schema\PluginSchemaMergeService;
use Xestify\plugins\lifecycle\PluginStatusService;
use Xestify\plugins\lifecycle\PluginSyncService;
use Xestify\plugins\lifecycle\PluginUpdateService;
use Xestify\plugins\lifecycle\PluginClassLoader;
use Xestify\plugins\guards\PluginCompatibilityValidator;
use Xestify\plugins\guards\PluginDependencyValidator;
use Xestify\plugins\discovery\PluginDiscoveryService;
use Xestify\plugins\discovery\PluginManifestReader;
use Xestify\plugins\discovery\PluginSchemaCodec;
use Xestify\plugins\discovery\PluginSchemaReader;
use Xestify\plugins\discovery\PluginSourceService;
use Xestify\plugins\lifecycle\PluginDeletionService;
use Xestify\plugins\lifecycle\PluginIdentityService;
use Xestify\plugins\lifecycle\PluginLifecycleInvoker;
use Xestify\plugins\schema\ExtensionPluginConfigService;
use Xestify\plugins\schema\PluginConfigFieldNormalizer;
use Xestify\plugins\schema\PluginConfigService;
use Xestify\plugins\schema\RelationsPayloadCompiler;
use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\repositories\GenericRepository;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginUpdateHistoryRepository;
use Xestify\repositories\PluginWriteRepository;
use Xestify\services\PluginAdministrationService;

function buildPluginRepository(\PDO $pdo): PluginRepository
{
    return new PluginRepository($pdo, new PluginSchemaCodec());
}

function buildPluginWriteRepository(\PDO $pdo): PluginWriteRepository
{
    return new PluginWriteRepository($pdo, new PluginSchemaCodec());
}

function buildPluginSourceService(string $root, \PDO $pdo): PluginSourceService
{
    $repository = buildPluginRepository($pdo);

    return new PluginSourceService(
        new PluginDiscoveryService($root),
        new PluginManifestReader($root),
        new PluginSchemaReader($root),
        new PluginCompatibilityValidator(),
        new PluginDependencyValidator($repository)
    );
}

function buildPluginLifecycleInvoker(string $root, \PDO $pdo): PluginLifecycleInvoker
{
    return new PluginLifecycleInvoker(new PluginClassLoader($root, $pdo));
}

function buildPluginSyncService(string $root, \PDO $pdo): PluginSyncService
{
    return new PluginSyncService(
        $pdo,
        buildPluginSourceService($root, $pdo),
        buildPluginRepository($pdo),
        buildPluginWriteRepository($pdo),
        buildPluginLifecycleInvoker($root, $pdo),
        new PluginSchemaCodec(),
        new InstalledPluginSchemaValidator()
    );
}

function buildPluginUpdateService(string $root, \PDO $pdo): PluginUpdateService
{
    return new PluginUpdateService(
        $pdo,
        buildPluginSourceService($root, $pdo),
        buildPluginRepository($pdo),
        buildPluginWriteRepository($pdo),
        new PluginUpdateHistoryRepository($pdo, new PluginSchemaCodec()),
        new PluginSchemaCodec(),
        new PluginSchemaMergeService(),
        buildPluginLifecycleInvoker($root, $pdo),
        new InstalledPluginSchemaValidator()
    );
}

function buildPluginRollbackService(string $root, \PDO $pdo): PluginRollbackService
{
    return new PluginRollbackService(
        $pdo,
        buildPluginRepository($pdo),
        buildPluginWriteRepository($pdo),
        new PluginUpdateHistoryRepository($pdo, new PluginSchemaCodec()),
        buildPluginLifecycleInvoker($root, $pdo)
    );
}

function buildPluginOutdatedService(string $root, \PDO $pdo): PluginOutdatedService
{
    return new PluginOutdatedService(
        buildPluginSourceService($root, $pdo),
        buildPluginRepository($pdo)
    );
}

function buildPluginStatusService(string $root, \PDO $pdo): PluginStatusService
{
    return new PluginStatusService(
        $pdo,
        buildPluginWriteRepository($pdo),
        buildPluginLifecycleInvoker($root, $pdo)
    );
}

function buildPluginOrderService(\PDO $pdo): PluginOrderService
{
    return new PluginOrderService(
        $pdo,
        buildPluginRepository($pdo),
        buildPluginWriteRepository($pdo)
    );
}

function buildPluginIdentityService(\PDO $pdo): PluginIdentityService
{
    return new PluginIdentityService(
        buildPluginRepository($pdo),
        buildPluginWriteRepository($pdo),
        new GenericRepository($pdo),
        new ExtensionPluginDataStore($pdo),
        new PluginUpdateHistoryRepository($pdo, new PluginSchemaCodec())
    );
}

function buildPluginConfigService(string $root, \PDO $pdo): PluginConfigService
{
    $fieldNormalizer = new PluginConfigFieldNormalizer();
    $relationsCompiler = new RelationsPayloadCompiler(buildPluginRepository($pdo));

    return new PluginConfigService(
        buildPluginWriteRepository($pdo),
        new ExtensionPluginConfigService(buildPluginRepository($pdo), $fieldNormalizer, $relationsCompiler),
        $fieldNormalizer,
        buildPluginSourceService($root, $pdo),
        $relationsCompiler
    );
}

function buildPluginDeletionService(string $root, \PDO $pdo): PluginDeletionService
{
    return new PluginDeletionService(
        buildPluginWriteRepository($pdo),
        new GenericRepository($pdo),
        new ExtensionPluginDataStore($pdo),
        new PluginUpdateHistoryRepository($pdo, new PluginSchemaCodec()),
        buildPluginLifecycleInvoker($root, $pdo)
    );
}

function buildPluginAdministrationService(string $root, \PDO $pdo): PluginAdministrationService
{
    return new PluginAdministrationService(
        $pdo,
        buildPluginRepository($pdo),
        buildPluginSyncService($root, $pdo),
        buildPluginOutdatedService($root, $pdo),
        buildPluginUpdateService($root, $pdo),
        buildPluginRollbackService($root, $pdo),
        buildPluginStatusService($root, $pdo),
        buildPluginConfigService($root, $pdo),
        buildPluginIdentityService($pdo),
        buildPluginDeletionService($root, $pdo),
        buildPluginSourceService($root, $pdo),
        buildPluginOrderService($pdo)
    );
}
