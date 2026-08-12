<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\ConfigurationController;
use Xestify\controllers\EntityController;
use Xestify\controllers\ExtensionPluginContentService;
use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\controllers\PluginExtensionController;
use Xestify\controllers\PluginManagerController;
use Xestify\controllers\UserController;
use Xestify\core\Container;
use Xestify\core\Database;
use Xestify\core\RequestFactory;
use Xestify\core\RuntimePathNormalizer;
use Xestify\middleware\AuthMiddleware;
use Xestify\plugins\HookDispatcher;
use Xestify\repositories\GenericRepository;
use Xestify\repositories\ConfigurationRepository;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginUpdateHistoryRepository;
use Xestify\repositories\UserRepository;
use Xestify\plugins\application\InstalledPluginSchemaValidator;
use Xestify\plugins\application\ExtensionPluginConfigService;
use Xestify\plugins\application\PluginAdministrationService;
use Xestify\plugins\application\PluginOutdatedService;
use Xestify\plugins\application\PluginRollbackService;
use Xestify\plugins\application\PluginSchemaMergeService;
use Xestify\plugins\application\PluginStatusService;
use Xestify\plugins\application\PluginSyncService;
use Xestify\plugins\application\PluginUpdateService;
use Xestify\plugins\infrastructure\PluginClassLoader;
use Xestify\plugins\infrastructure\PluginCompatibilityValidator;
use Xestify\plugins\infrastructure\PluginDependencyValidator;
use Xestify\plugins\infrastructure\PluginDiscoveryService;
use Xestify\plugins\infrastructure\PluginManifestReader;
use Xestify\plugins\infrastructure\PluginSchemaCodec;
use Xestify\plugins\infrastructure\PluginSchemaReader;
use Xestify\plugins\infrastructure\PluginSourceService;
use Xestify\plugins\runtime\PluginHookRegistrar;
use Xestify\plugins\runtime\PluginLifecycleInvoker;
use Xestify\services\EntityService;
use Xestify\services\JwtService;
use Xestify\services\ProfileSecretVerifier;
use Xestify\services\ValidationService;
use Xestify\validation\FieldValidatorRegistry;
use Xestify\validation\schema\SchemaFieldExtractor;
use Xestify\validation\support\ValidationRules;
use Xestify\validation\validators\BooleanFieldValidator;
use Xestify\validation\validators\DateFieldValidator;
use Xestify\validation\validators\EmailFieldValidator;
use Xestify\validation\validators\NumberFieldValidator;
use Xestify\validation\validators\SelectFieldValidator;
use Xestify\validation\validators\StringFieldValidator;
use Xestify\validation\validators\TimestampFieldValidator;

if (!function_exists('xestifyRegisterCoreHttpServices')) {
    function xestifyRegisterCoreHttpServices(Container $container): void
    {
        $container->singleton(Database::class, fn() => Database::connection());

        $container->singleton(JwtService::class, fn() => new JwtService(
            $_ENV['JWT_SECRET'] ?? 'changeme',
            (int) ($_ENV['JWT_EXPIRY'] ?? 3600)
        ));

        $container->singleton(AuthMiddleware::class, fn() => new AuthMiddleware(
            $container->get(JwtService::class)
        ));

        $container->singleton(RuntimePathNormalizer::class, fn() => new RuntimePathNormalizer());
        $container->singleton(RequestFactory::class, fn() => new RequestFactory(
            $container->get(RuntimePathNormalizer::class)
        ));
    }
}

if (!function_exists('xestifyRegisterEntityServices')) {
    function xestifyRegisterEntityServices(Container $container): void
    {
        $container->singleton(HookDispatcher::class, fn() => new HookDispatcher());
        $container->singleton(SchemaFieldExtractor::class, fn() => new SchemaFieldExtractor());
        $container->singleton(ValidationRules::class, fn() => new ValidationRules());
        $container->singleton(FieldValidatorRegistry::class, fn() => new FieldValidatorRegistry([
            'string' => new StringFieldValidator(),
            'text' => new StringFieldValidator('text'),
            'number' => new NumberFieldValidator(),
            'boolean' => new BooleanFieldValidator(),
            'date' => new DateFieldValidator(),
            'timestamp' => new TimestampFieldValidator(),
            'email' => new EmailFieldValidator(),
            'select' => new SelectFieldValidator(),
        ]));
        $container->singleton(ValidationService::class, fn() => new ValidationService(
            $container->get(SchemaFieldExtractor::class),
            $container->get(FieldValidatorRegistry::class),
            $container->get(ValidationRules::class)
        ));
        $container->singleton(GenericRepository::class, fn() => new GenericRepository(
            $container->get(Database::class)
        ));
        $container->singleton(UserRepository::class, fn() => new UserRepository(
            $container->get(Database::class)
        ));
        $container->singleton(ProfileSecretVerifier::class, fn() => new ProfileSecretVerifier());
        $container->singleton(ConfigurationRepository::class, fn() => new ConfigurationRepository(
            $container->get(Database::class)
        ));
        $container->singleton(EntityService::class, fn() => new EntityService(
            $container->get(GenericRepository::class),
            $container->get(ValidationService::class),
            $container->get(Database::class),
            $container->get(HookDispatcher::class)
        ));
    }
}

if (!function_exists('xestifyRegisterPluginServices')) {
    function xestifyRegisterPluginServices(Container $container, string $pluginsDir): void
    {
        $container->singleton(PluginSchemaCodec::class, fn() => new PluginSchemaCodec());
        $container->singleton(PluginRepository::class, fn() => new PluginRepository(
            $container->get(Database::class),
            $container->get(PluginSchemaCodec::class)
        ));
        $container->singleton(PluginUpdateHistoryRepository::class, fn() => new PluginUpdateHistoryRepository(
            $container->get(Database::class)
        ));
        $container->singleton(PluginDiscoveryService::class, fn() => new PluginDiscoveryService($pluginsDir));
        $container->singleton(PluginManifestReader::class, fn() => new PluginManifestReader($pluginsDir));
        $container->singleton(PluginSchemaReader::class, fn() => new PluginSchemaReader($pluginsDir));
        $container->singleton(PluginCompatibilityValidator::class, fn() => new PluginCompatibilityValidator());
        $container->singleton(PluginDependencyValidator::class, fn() => new PluginDependencyValidator(
            $container->get(PluginRepository::class)
        ));
        $container->singleton(PluginSourceService::class, fn() => new PluginSourceService(
            $container->get(PluginDiscoveryService::class),
            $container->get(PluginManifestReader::class),
            $container->get(PluginSchemaReader::class),
            $container->get(PluginCompatibilityValidator::class),
            $container->get(PluginDependencyValidator::class)
        ));
        $container->singleton(PluginClassLoader::class, fn() => new PluginClassLoader(
            $pluginsDir,
            $container->get(Database::class)
        ));
        $container->singleton(PluginLifecycleInvoker::class, fn() => new PluginLifecycleInvoker(
            $container->get(PluginClassLoader::class)
        ));
        $container->singleton(PluginHookRegistrar::class, fn() => new PluginHookRegistrar(
            $container->get(PluginRepository::class),
            $container->get(PluginClassLoader::class)
        ));
        $container->singleton(PluginSchemaMergeService::class, fn() => new PluginSchemaMergeService());
        $container->singleton(InstalledPluginSchemaValidator::class, fn() => new InstalledPluginSchemaValidator());
        $container->singleton(PluginSyncService::class, fn() => new PluginSyncService(
            $container->get(PluginSourceService::class),
            $container->get(PluginRepository::class),
            $container->get(PluginLifecycleInvoker::class),
            $container->get(PluginSchemaCodec::class),
            $container->get(InstalledPluginSchemaValidator::class)
        ));
        $container->singleton(PluginUpdateService::class, fn() => new PluginUpdateService(
            $container->get(Database::class),
            $container->get(PluginSourceService::class),
            $container->get(PluginRepository::class),
            $container->get(PluginUpdateHistoryRepository::class),
            $container->get(PluginSchemaCodec::class),
            $container->get(PluginSchemaMergeService::class),
            $container->get(PluginLifecycleInvoker::class),
            $container->get(InstalledPluginSchemaValidator::class)
        ));
        $container->singleton(PluginRollbackService::class, fn() => new PluginRollbackService(
            $container->get(Database::class),
            $container->get(PluginRepository::class),
            $container->get(PluginUpdateHistoryRepository::class),
            $container->get(PluginLifecycleInvoker::class)
        ));
        $container->singleton(PluginStatusService::class, fn() => new PluginStatusService(
            $container->get(PluginRepository::class),
            $container->get(PluginLifecycleInvoker::class)
        ));
        $container->singleton(PluginOutdatedService::class, fn() => new PluginOutdatedService(
            $container->get(PluginSourceService::class),
            $container->get(PluginRepository::class)
        ));
        $container->singleton(ExtensionPluginConfigService::class, fn() => new ExtensionPluginConfigService(
            $container->get(PluginRepository::class)
        ));
        $container->singleton(PluginAdministrationService::class, fn() => new PluginAdministrationService(
            $container->get(PluginRepository::class),
            $container->get(PluginSyncService::class),
            $container->get(PluginOutdatedService::class),
            $container->get(PluginUpdateService::class),
            $container->get(PluginRollbackService::class),
            $container->get(PluginStatusService::class),
            $container->get(ExtensionPluginConfigService::class)
        ));
    }
}

if (!function_exists('xestifyBootPluginHooks')) {
    function xestifyBootPluginHooks(Container $container): void
    {
        $container->get(PluginHookRegistrar::class)->registerActiveHooks($container->get(HookDispatcher::class));
    }
}

if (!function_exists('xestifyRegisterControllers')) {
    function xestifyRegisterControllers(Container $container): void
    {
        $container->singleton(AuthController::class, fn() => new AuthController(
            $container->get(JwtService::class),
            $container->get(UserRepository::class),
            $container->get(RequestFactory::class)
        ));

        $container->singleton(ConfigurationController::class, fn() => new ConfigurationController(
            $container->get(ConfigurationRepository::class),
            $container->get(RequestFactory::class)
        ));

        $container->singleton(EntityController::class, fn() => new EntityController(
            $container->get(EntityService::class),
            $container->get(Database::class),
            $container->get(HookDispatcher::class),
            $container->get(RequestFactory::class)
        ));

        $container->singleton(ExtensionPluginContentService::class, fn() => new ExtensionPluginContentService(
            $container->get(Database::class)
        ));

        $container->singleton(ExtensionPluginDataStore::class, fn() => new ExtensionPluginDataStore(
            $container->get(Database::class)
        ));

        $container->singleton(PluginExtensionController::class, fn() => new PluginExtensionController(
            $container->get(Database::class),
            $container->get(RequestFactory::class),
            $container->get(ExtensionPluginContentService::class),
            $container->get(ExtensionPluginDataStore::class)
        ));

        $container->singleton(PluginManagerController::class, fn() => new PluginManagerController(
            $container->get(PluginAdministrationService::class),
            $container->get(RequestFactory::class)
        ));

        $container->singleton(UserController::class, fn() => new UserController(
            $container->get(UserRepository::class),
            $container->get(ProfileSecretVerifier::class)
        ));
    }
}

$container = isset($container) ? $container : null;
if (!($container instanceof Container)) {
    return;
}

/** @var Container $container injected by bootstrap.php */
$pluginsDir = dirname(BASE_PATH) . '/plugins';
xestifyRegisterCoreHttpServices($container);
xestifyRegisterEntityServices($container);
xestifyRegisterPluginServices($container, $pluginsDir);

// Runtime boot relies on the plugins already registered in DB. Disk-to-DB
// synchronization is an explicit setup/admin operation, not part of every
// request.
xestifyBootPluginHooks($container);
xestifyRegisterControllers($container);
