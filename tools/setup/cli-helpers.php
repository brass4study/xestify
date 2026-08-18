<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/*
 * Shared console helpers for the CLI scripts under tools/setup/: prompts,
 * secret input (hidden where the terminal allows it, environment variables
 * otherwise) and uniform failure/exit handling. Secrets are never accepted as
 * command-line flags anywhere in tools/ (shell history, process list).
 */

const CLI_EXIT_OK = 0;
const CLI_EXIT_FAILURE = 1;
const CLI_EXIT_USAGE = 2;

const CLI_ENV_DB_PASSWORD = 'XESTIFY_DB_PASSWORD';
const CLI_ENV_MAINT_PASSWORD = 'XESTIFY_MAINT_PASSWORD';
const CLI_ENV_ADMIN_PASSWORD = 'XESTIFY_ADMIN_PASSWORD';
const CLI_ENV_ADMIN_EMAIL = 'XESTIFY_ADMIN_EMAIL';
const CLI_ENV_ADMIN_NAME = 'XESTIFY_ADMIN_NAME';

function cliFail(string $message, int $code = CLI_EXIT_FAILURE): never
{
    fwrite(STDERR, "\nERROR: {$message}\n");
    exit($code);
}

function cliNote(string $line): void
{
    echo "  - {$line}\n";
}

function cliReadLine(): ?string
{
    $line = fgets(STDIN);

    return $line === false ? null : rtrim($line, "\r\n");
}

/**
 * Plain (visible) prompt. Returns $default when not interactive, on EOF or on
 * an empty answer.
 */
function cliPrompt(string $label, string $default, bool $interactive): string
{
    if (!$interactive) {
        return $default;
    }

    $suffix = $default !== '' ? " [{$default}]" : '';
    echo "  {$label}{$suffix}: ";
    $answer = cliReadLine();

    return ($answer === null || $answer === '') ? $default : $answer;
}

/**
 * Secret input: the environment variable wins; otherwise an interactive prompt,
 * hidden through `stty -echo` when STDIN is a real TTY outside Windows and
 * visible (with a warning) elsewhere. Returns null when no value is available.
 */
function cliPromptSecret(string $label, string $envVar, bool $interactive): ?string
{
    $fromEnv = getenv($envVar);
    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }

    if (!$interactive) {
        return null;
    }

    $canHide = PHP_OS_FAMILY !== 'Windows' && stream_isatty(STDIN);
    echo "  {$label}" . ($canHide ? '' : ' (se mostrara en pantalla)') . ': ';

    if ($canHide) {
        system('stty -echo');
    }
    $answer = cliReadLine();
    if ($canHide) {
        system('stty echo');
        echo "\n";
    }

    return $answer === null || $answer === '' ? null : $answer;
}

/**
 * Exits with the usage code when a mandatory secret could not be obtained,
 * naming the environment variable that provides it in non-interactive mode.
 */
function cliRequireSecret(?string $value, string $envVar, string $what): string
{
    if ($value === null) {
        cliFail(
            "Falta {$what}. Define la variable de entorno {$envVar} o ejecuta sin --non-interactive.",
            CLI_EXIT_USAGE
        );
    }

    return $value;
}

/**
 * Minimal long-option parser shared by the setup scripts: `--flag` for
 * booleans, `--name=value` for values. Anything else is a usage error.
 *
 * @param list<string> $argv
 * @param list<string> $boolFlags
 * @param list<string> $valueFlags
 * @return array<string, string|bool>
 */
function cliParseOptions(array $argv, array $boolFlags, array $valueFlags, string $usage): array
{
    $options = [];
    foreach ($boolFlags as $flag) {
        $options[$flag] = false;
    }
    foreach ($valueFlags as $flag) {
        $options[$flag] = '';
    }

    foreach (array_slice($argv, 1) as $arg) {
        $hasValue = str_contains($arg, '=');
        $name = ltrim($hasValue ? (string) substr($arg, 0, (int) strpos($arg, '=')) : $arg, '-');
        $value = $hasValue ? (string) substr($arg, (int) strpos($arg, '=') + 1) : '';

        if (in_array($name, $boolFlags, true) && !$hasValue) {
            $options[$name] = true;
        } elseif (in_array($name, $valueFlags, true) && $hasValue) {
            $options[$name] = $value;
        } else {
            cliFail("Opcion no reconocida: {$arg}\n\n{$usage}", CLI_EXIT_USAGE);
        }
    }

    return $options;
}
