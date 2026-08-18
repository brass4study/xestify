<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cli-helpers.php';

use Xestify\core\Database;
use Xestify\database\seeders\AdminUserCreator;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\SeederException;

/*
 * Creates a real administrator (is_seed = false) on an existing installation:
 * first admin after a manual install, an extra admin, or access recovery. The
 * application has no user-creation UI/endpoint yet (backlog STORY A1.8).
 * CLI only (bootstrap.php guard); password via prompt or XESTIFY_ADMIN_PASSWORD.
 */

const CREATE_ADMIN_FLAGS_BOOL = ['help', 'non-interactive'];
const CREATE_ADMIN_FLAGS_VALUE = ['email', 'name'];

function createAdminUsage(): string
{
    return <<<TXT
Uso: php tools/setup/create-admin-user.php [--email=EMAIL] [--name=NOMBRE] [--non-interactive]

Crea un usuario administrador real (is_seed=false, rol admin). Los valores que
falten se preguntan por consola; la contrasena nunca se acepta como flag:
  XESTIFY_ADMIN_PASSWORD  contrasena (min. 12 caracteres); si no, prompt.
  XESTIFY_ADMIN_EMAIL / XESTIFY_ADMIN_NAME  alternativa a --email / --name.

Codigos de salida: 0 ok, 1 fallo, 2 uso incorrecto.

TXT;
}

$options = cliParseOptions($argv, CREATE_ADMIN_FLAGS_BOOL, CREATE_ADMIN_FLAGS_VALUE, createAdminUsage());
if ($options['help'] === true) {
    echo createAdminUsage();
    exit(CLI_EXIT_OK);
}

$interactive = $options['non-interactive'] !== true;

$envEmail = (string) getenv(CLI_ENV_ADMIN_EMAIL);
$envName = (string) getenv(CLI_ENV_ADMIN_NAME);
$email = (string) $options['email'] !== ''
    ? (string) $options['email']
    : cliPrompt('Email del administrador', $envEmail, $interactive);
$name = (string) $options['name'] !== ''
    ? (string) $options['name']
    : cliPrompt('Nombre del administrador', $envName !== '' ? $envName : 'Administrador', $interactive);
$password = cliRequireSecret(
    cliPromptSecret(
        'Contrasena (min. ' . AdminUserCreator::MIN_PASSWORD_LENGTH . ' caracteres)',
        CLI_ENV_ADMIN_PASSWORD,
        $interactive
    ),
    CLI_ENV_ADMIN_PASSWORD,
    'la contrasena del administrador'
);

try {
    $created = (new AdminUserCreator(Database::connection()))->create($email, $name, $password);
} catch (DatabaseException | SeederException $e) {
    cliFail($e->getMessage());
}

echo "Administrador creado: {$created['email']} ({$created['name']}, id {$created['id']}).\n";
exit(CLI_EXIT_OK);
