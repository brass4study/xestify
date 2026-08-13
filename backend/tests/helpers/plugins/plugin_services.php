<?php

declare(strict_types=1);

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
use Xestify\plugins\lifecycle\PluginLifecycleInvoker;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginUpdateHistoryRepository;

function buildPluginRepository(\PDO $pdo): PluginRepository
{
    return new PluginRepository($pdo, new PluginSchemaCodec());
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
        new PluginUpdateHistoryRepository($pdo),
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
        new PluginUpdateHistoryRepository($pdo),
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
        buildPluginRepository($pdo),
        buildPluginLifecycleInvoker($root, $pdo)
    );
}
