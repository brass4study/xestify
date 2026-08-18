<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cli-helpers.php';

use Xestify\core\AppDebug;
use Xestify\core\Container;
use Xestify\core\Database;
use Xestify\database\DatabaseProvisioner;
use Xestify\database\SchemaInstaller;
use Xestify\database\seeders\AdminUserCreator;
use Xestify\database\seeders\BusinessDataSeeder;
use Xestify\database\seeders\UserSeeder;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\SeederException;
use Xestify\plugins\lifecycle\PluginSyncService;

/*
 * Xestify installer (CLI only — bootstrap.php refuses any other SAPI).
 *
 * Steps: requirements -> backend/.env -> [--create-db] -> connection -> schema
 * -> real admin -> [seed users] -> plugins -> [demo data]. Every step is
 * idempotent, so the script can be re-run on an existing installation.
 *
 * Secrets are NEVER accepted as flags: they come from an interactive prompt or
 * from the XESTIFY_* environment variables (see --help / cli-helpers.php).
 */

const INSTALL_TOTAL_STEPS = 9;
const INSTALL_ROOT = __DIR__ . '/../..';
const INSTALL_APP_ENVS = ['development', 'production'];
const INSTALL_ENV_FILE_MODE = 0640;
const INSTALL_JWT_SECRET_BYTES = 32;
const INSTALL_DEFAULT_MAINT_USER = 'postgres';
const INSTALL_DEFAULT_MAINT_DB = 'postgres';

const INSTALL_FLAGS_BOOL = [
    'help', 'non-interactive', 'create-db', 'skip-admin', 'with-seed-users', 'skip-plugins', 'seed-business-data',
];
const INSTALL_FLAGS_VALUE = [
    'db-host', 'db-port', 'db-name', 'db-user', 'app-env', 'maint-db', 'maint-user', 'admin-email', 'admin-name',
];

function installUsage(): string
{
    return <<<TXT
Uso: php tools/setup/install.php [opciones]

Instala Xestify sobre PostgreSQL: comprueba requisitos, crea backend/.env si no
existe, (opcionalmente) crea rol y base de datos, aplica el esquema base
(backend/database/schema/*.sql), crea el administrador real, siembra los
usuarios de demostracion solo en modo debug, registra los plugins de disco y,
si se pide, siembra datos de negocio de demostracion. Es idempotente.

Opciones generales:
  --help                 Muestra esta ayuda.
  --non-interactive      No pregunta nada: usa flags, variables de entorno y
                         valores por defecto. Falla (codigo 2) si falta un
                         secreto obligatorio.

Configuracion (solo se usan si backend/.env NO existe todavia):
  --db-host=HOST         Por defecto 127.0.0.1
  --db-port=PORT         Por defecto 5432
  --db-name=NAME         Por defecto xestify_dev
  --db-user=USER         Por defecto postgres
  --app-env=ENV          development (por defecto) | production
                         (APP_DEBUG se deriva: true solo en development)

Base de datos:
  --create-db            Crea el rol DB_USER y la base de datos DB_NAME si no
                         existen, conectando a la BD de mantenimiento con
                         credenciales de mantenimiento (superusuario).
  --maint-db=NAME        BD de mantenimiento (por defecto postgres).
  --maint-user=USER      Usuario de mantenimiento (por defecto postgres).

Usuarios:
  --admin-email=EMAIL    Email del administrador real (o prompt).
  --admin-name=NAME      Nombre del administrador real (o prompt).
  --skip-admin           No crear administrador real.
  --with-seed-users      Crear admin@xestify.local / usuario@xestify.local
                         (contrasenas conocidas) aunque APP_DEBUG=false.

Plugins y datos:
  --skip-plugins         No sincronizar plugins de disco a BD.
  --seed-business-data   Sembrar datos de negocio de demostracion (requiere
                         las instancias de plugin activas; ver INSTALL.md).

Secretos (nunca como flag; historial de shell y lista de procesos):
  XESTIFY_DB_PASSWORD     Contrasena de DB_USER (si no, prompt).
  XESTIFY_MAINT_PASSWORD  Contrasena del usuario de mantenimiento (--create-db).
  XESTIFY_ADMIN_PASSWORD  Contrasena del administrador real (min. 12 chars).
  XESTIFY_ADMIN_EMAIL / XESTIFY_ADMIN_NAME  Alternativa a los flags.

Codigos de salida: 0 ok, 1 fallo, 2 uso incorrecto.

TXT;
}

function installStep(int $number, string $title): void
{
    echo "\n[{$number}/" . INSTALL_TOTAL_STEPS . "] {$title}\n";
}

// ---------------------------------------------------------------------------
// Steps
// ---------------------------------------------------------------------------

function installCheckRequirements(): void
{
    installStep(1, 'Requisitos');

    $problems = [];
    if (PHP_VERSION_ID < 80100) {
        $problems[] = 'PHP 8.1 o superior (actual: ' . PHP_VERSION . ')';
    }
    foreach (['pdo_pgsql', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            $problems[] = "extension PHP '{$extension}' no cargada";
        }
    }

    if ($problems !== []) {
        cliFail(
            "Requisitos no cumplidos:\n  - " . implode("\n  - ", $problems)
            . "\nRevisa INSTALL.md seccion 2 (en Windows con Apache+PHP puede hacer falta `php -c <php.ini>`)."
        );
    }

    cliNote('PHP ' . PHP_VERSION . ' con pdo_pgsql y mbstring: OK');
}

/**
 * @return array<string, string>
 */
function installTemplateDefaults(string $templatePath): array
{
    $defaults = [];
    $lines = file($templatePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines === false ? [] : $lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $defaults[trim($key)] = trim($value);
    }

    $defaults['DB_HOST'] = '127.0.0.1';
    $defaults['DB_PASSWORD'] = '';
    $defaults['APP_ENV'] = INSTALL_APP_ENVS[0];

    return $defaults;
}

/**
 * @param array<string, string|bool> $options
 * @param array<string, string> $defaults
 * @return array<string, string>
 */
function installCollectEnvValues(array $options, array $defaults, bool $interactive): array
{
    $flagMap = ['DB_HOST' => 'db-host', 'DB_PORT' => 'db-port', 'DB_NAME' => 'db-name', 'DB_USER' => 'db-user'];
    $labels = [
        'DB_HOST' => 'Host PostgreSQL',
        'DB_PORT' => 'Puerto',
        'DB_NAME' => 'Base de datos',
        'DB_USER' => 'Usuario BD',
    ];

    $values = [];
    foreach ($flagMap as $key => $flag) {
        $values[$key] = (string) $options[$flag] !== ''
            ? (string) $options[$flag]
            : cliPrompt($labels[$key], $defaults[$key], $interactive);
    }

    $values['DB_PASSWORD'] = cliPromptSecret('Contrasena BD', CLI_ENV_DB_PASSWORD, $interactive) ?? '';

    $appEnv = (string) $options['app-env'] !== ''
        ? (string) $options['app-env']
        : cliPrompt('APP_ENV (development|production)', $defaults['APP_ENV'], $interactive);
    if (!in_array($appEnv, INSTALL_APP_ENVS, true)) {
        cliFail("APP_ENV no valido: '{$appEnv}' (development|production).", CLI_EXIT_USAGE);
    }
    $values['APP_ENV'] = $appEnv;
    $values['APP_DEBUG'] = $appEnv === 'development' ? 'true' : 'false';
    $values['JWT_SECRET'] = bin2hex(random_bytes(INSTALL_JWT_SECRET_BYTES));
    $values['JWT_EXPIRY'] = $defaults['JWT_EXPIRY'] ?? '3600';

    return $values;
}

/**
 * Writes backend/.env from the .env.example template, replacing values line by
 * line (comments and ordering preserved) and appending any missing key.
 *
 * @param array<string, string> $values
 */
function installWriteEnvFile(string $templatePath, string $target, array $values): void
{
    $lines = file($templatePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        cliFail("No se puede leer la plantilla {$templatePath}.");
    }

    $pending = $values;
    $output = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            $output[] = $line;
            continue;
        }
        $key = trim((string) strtok($trimmed, '='));
        if (array_key_exists($key, $pending)) {
            $output[] = $key . '=' . $pending[$key];
            unset($pending[$key]);
        } else {
            $output[] = $line;
        }
    }
    foreach ($pending as $key => $value) {
        $output[] = $key . '=' . $value;
    }

    if (file_put_contents($target, implode("\n", $output) . "\n") === false) {
        cliFail("No se pudo escribir {$target}.");
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        chmod($target, INSTALL_ENV_FILE_MODE);
    }
}

/**
 * @param array<string, string> $values
 */
function installExportEnv(array $values): void
{
    foreach ($values as $key => $value) {
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

/**
 * @param array<string, string|bool> $options
 * @return bool true when backend/.env was created by this run
 */
function installEnsureEnvFile(array $options, bool $interactive): bool
{
    installStep(2, 'Configuracion backend/.env');

    $target = BASE_PATH . '/.env';
    $template = BASE_PATH . '/.env.example';

    if (is_file($target)) {
        cliNote('backend/.env ya existe: se usan sus valores (los flags --db-*/--app-env se ignoran).');
        return false;
    }
    if (!is_file($template)) {
        cliFail("Falta la plantilla {$template}.");
    }

    $values = installCollectEnvValues($options, installTemplateDefaults($template), $interactive);
    installWriteEnvFile($template, $target, $values);
    installExportEnv($values);

    cliNote("backend/.env creado (APP_ENV={$values['APP_ENV']}, DB {$values['DB_USER']}@{$values['DB_HOST']}:"
        . "{$values['DB_PORT']}/{$values['DB_NAME']}, JWT_SECRET aleatorio).");
    if (PHP_OS_FAMILY !== 'Windows') {
        cliNote('Permisos 0640 aplicados; asegurate de que el usuario de Apache pueda leerlo (ver resumen final).');
    }

    return true;
}

/**
 * @param array<string, string|bool> $options
 */
function installCreateDatabase(array $options, bool $interactive): void
{
    installStep(3, 'Rol y base de datos (--create-db)');

    if ($options['create-db'] !== true) {
        cliNote('Omitido (sin --create-db): se asume que rol y base de datos ya existen.');
        return;
    }

    $maintUser = (string) $options['maint-user'] !== '' ? (string) $options['maint-user'] : INSTALL_DEFAULT_MAINT_USER;
    $maintDb = (string) $options['maint-db'] !== '' ? (string) $options['maint-db'] : INSTALL_DEFAULT_MAINT_DB;
    $maintPassword = cliRequireSecret(
        cliPromptSecret("Contrasena de mantenimiento ({$maintUser})", CLI_ENV_MAINT_PASSWORD, $interactive),
        CLI_ENV_MAINT_PASSWORD,
        'la contrasena del usuario de mantenimiento'
    );

    $dbName = (string) ($_ENV['DB_NAME'] ?? '');
    $dbUser = (string) ($_ENV['DB_USER'] ?? '');

    try {
        $provisioner = new DatabaseProvisioner([
            'host' => (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'),
            'port' => (string) ($_ENV['DB_PORT'] ?? '5432'),
            'user' => $maintUser,
            'password' => $maintPassword,
            'maintenance_db' => $maintDb,
        ]);
        $roleCreated = $provisioner->ensureRole($dbUser, (string) ($_ENV['DB_PASSWORD'] ?? ''));
        $dbCreated = $provisioner->ensureDatabase($dbName, $dbUser);
    } catch (DatabaseException $e) {
        cliFail(
            $e->getMessage() . "\nCrea rol y base de datos a mano (INSTALL.md seccion 3):\n"
            . "  CREATE ROLE {$dbUser} LOGIN PASSWORD '<contrasena>';\n"
            . "  CREATE DATABASE {$dbName} OWNER {$dbUser};"
        );
    }

    cliNote($roleCreated ? "Rol '{$dbUser}' creado." : "Rol '{$dbUser}' ya existia.");
    cliNote($dbCreated ? "Base de datos '{$dbName}' creada." : "Base de datos '{$dbName}' ya existia.");
}

function installConnect(): PDO
{
    installStep(4, 'Conexion a PostgreSQL');

    try {
        $pdo = Database::connection();
    } catch (DatabaseException $e) {
        cliFail(
            $e->getMessage() . "\nComprueba DB_* en backend/.env, que la extension pdo_pgsql este cargada y que la "
            . 'base de datos exista (puedes crearla con --create-db).'
        );
    }

    cliNote('Conectado a ' . ($_ENV['DB_NAME'] ?? '') . ' como ' . ($_ENV['DB_USER'] ?? '') . '.');

    return $pdo;
}

function installApplySchema(PDO $pdo): void
{
    installStep(5, 'Esquema base (backend/database/schema)');

    try {
        $report = (new SchemaInstaller($pdo, BASE_PATH . '/database/schema'))->apply();
    } catch (DatabaseException $e) {
        cliFail($e->getMessage());
    }

    foreach ($report as $entry) {
        cliNote("{$entry['file']}: {$entry['status']} ({$entry['duration_ms']} ms)");
    }
}

/**
 * @param array<string, string|bool> $options
 * @return string|null email of the admin created in this run
 */
function installEnsureAdmin(PDO $pdo, array $options, bool $interactive): ?string
{
    installStep(6, 'Administrador real');

    if ($options['skip-admin'] === true) {
        cliNote('Omitido (--skip-admin).');
        return null;
    }

    $creator = new AdminUserCreator($pdo);
    if ($creator->hasRealAdmin()) {
        cliNote('Ya existe un administrador real: no se crea otro (usa tools/setup/create-admin-user.php).');
        return null;
    }

    $envEmail = (string) getenv(CLI_ENV_ADMIN_EMAIL);
    $envName = (string) getenv(CLI_ENV_ADMIN_NAME);
    $email = (string) $options['admin-email'] !== ''
        ? (string) $options['admin-email']
        : cliPrompt('Email del administrador', $envEmail, $interactive);
    $name = (string) $options['admin-name'] !== ''
        ? (string) $options['admin-name']
        : cliPrompt('Nombre del administrador', $envName !== '' ? $envName : 'Administrador', $interactive);
    $password = cliRequireSecret(
        cliPromptSecret(
            'Contrasena del administrador (min. ' . AdminUserCreator::MIN_PASSWORD_LENGTH . ' caracteres)',
            CLI_ENV_ADMIN_PASSWORD,
            $interactive
        ),
        CLI_ENV_ADMIN_PASSWORD,
        'la contrasena del administrador'
    );

    try {
        $created = $creator->create($email, $name, $password);
    } catch (SeederException $e) {
        cliFail($e->getMessage());
    }

    cliNote("Administrador '{$created['email']}' creado.");

    return $created['email'];
}

/**
 * @param array<string, string|bool> $options
 */
function installSeedUsers(array $options): bool
{
    installStep(7, 'Usuarios de demostracion (seed)');

    if (!AppDebug::enabled() && $options['with-seed-users'] !== true) {
        cliNote('Omitido: APP_DEBUG=false. Los usuarios seed (contrasenas conocidas) no podrian iniciar sesion.');
        cliNote('Usa --with-seed-users para crearlos igualmente.');
        return false;
    }

    UserSeeder::seedIfEmpty();
    cliNote('admin@xestify.local / admin123 y usuario@xestify.local / usuario123 asegurados (is_seed=true).');

    return true;
}

/**
 * @param array<string, string|bool> $options
 */
function installSyncPlugins(array $options): void
{
    installStep(8, 'Plugins (sincronizacion disco -> BD)');

    if ($options['skip-plugins'] === true) {
        cliNote('Omitido (--skip-plugins).');
        return;
    }

    // Declares the xestifyRegister* wiring helpers only: no $container is
    // defined in this scope, so config/app.php returns before booting anything.
    require_once BASE_PATH . '/src/config/app.php';

    $container = new Container();
    xestifyRegisterCoreHttpServices($container);
    xestifyRegisterPluginServices($container, realpath(INSTALL_ROOT) . '/plugins');

    try {
        $result = $container->get(PluginSyncService::class)->syncAll();
    } catch (Throwable $e) {
        cliFail('Fallo la sincronizacion de plugins: ' . $e->getMessage());
    }

    $summary = $result['summary'];
    cliNote("Descubiertos {$summary['discovered']}, registrados {$summary['registered']}, sin cambios "
        . "{$summary['unchanged']}, con update {$summary['outdated']}, errores {$summary['errors']}.");
    foreach ($result['plugins'] as $pluginName => $instances) {
        foreach ($instances as $instance) {
            $message = isset($instance['message']) ? ' ' . (string) $instance['message'] : '';
            cliNote("{$pluginName} [{$instance['slug']}]: {$instance['result']}{$message}");
        }
    }
    cliNote('Los plugins nuevos quedan inactivos: activalos (o crea instancias) desde PluginManager.');
}

/**
 * @param array<string, string|bool> $options
 */
function installSeedBusinessData(PDO $pdo, array $options): void
{
    installStep(9, 'Datos de negocio de demostracion');

    if ($options['seed-business-data'] !== true) {
        cliNote('Omitido (sin --seed-business-data).');
        return;
    }

    try {
        $result = (new BusinessDataSeeder($pdo))->run();
    } catch (SeederException $e) {
        cliFail('Business data seeder abortado: ' . $e->getMessage());
    }

    foreach ($result['groups'] as $group) {
        cliNote(sprintf('%-18s %-8s %6d filas', $group['group'], $group['status'], $group['count']));
    }
}

function installPrintSummary(bool $envCreated, ?string $adminEmail, bool $seedUsers): void
{
    echo "\nInstalacion completada.\n\nSiguientes pasos:\n";
    echo "  1. Configura Apache con DocumentRoot en la raiz del proyecto y AllowOverride All (INSTALL.md seccion 5).\n";
    echo "  2. Verifica la exposicion web: php tools/setup/check-install.php --url=http://<host>/\n";
    if ($envCreated && PHP_OS_FAMILY !== 'Windows') {
        echo "  3. Da acceso de lectura a backend/.env al usuario de Apache, p. ej.:\n";
        echo "       sudo chgrp www-data backend/.env && sudo chmod 0640 backend/.env\n";
    }
    if ($adminEmail !== null) {
        echo "  - Inicia sesion con {$adminEmail}.\n";
    }
    if ($seedUsers) {
        echo "  - Los usuarios seed tienen contrasena conocida: usalos solo en desarrollo/demo.\n";
    }
    echo "  - Alta de mas usuarios: php tools/setup/create-admin-user.php\n";
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$options = cliParseOptions($argv, INSTALL_FLAGS_BOOL, INSTALL_FLAGS_VALUE, installUsage());
if ($options['help'] === true) {
    echo installUsage();
    exit(CLI_EXIT_OK);
}
if ($options['app-env'] !== '' && !in_array($options['app-env'], INSTALL_APP_ENVS, true)) {
    cliFail('--app-env debe ser development o production.', CLI_EXIT_USAGE);
}

$interactive = $options['non-interactive'] !== true;

echo "=== Instalador de Xestify ===\n";
installCheckRequirements();
$envCreated = installEnsureEnvFile($options, $interactive);
installCreateDatabase($options, $interactive);
$pdo = installConnect();
installApplySchema($pdo);
$adminEmail = installEnsureAdmin($pdo, $options, $interactive);
$seedUsers = installSeedUsers($options);
installSyncPlugins($options);
installSeedBusinessData($pdo, $options);
installPrintSummary($envCreated, $adminEmail, $seedUsers);

exit(CLI_EXIT_OK);
