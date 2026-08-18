<?php

/**
 * ToolsCliGuardTest — Static guard over every PHP script under tools/.
 *
 * The scripts in tools/ (setup, dev) ship in the release artifact inside the
 * web DocumentRoot, so they must be impossible to execute through the web
 * server regardless of the .htaccess layers. The convention (CONTRIBUTING.md,
 * "Herramientas CLI") is:
 *
 *   - tools/setup/bootstrap.php starts with the SAPI guard
 *     (`if (PHP_SAPI !== 'cli') { ... exit }`) before any other statement;
 *   - every other tools/**\/*.php file has, as its very first statement after
 *     `declare(strict_types=1)`, a `require_once` of that bootstrap.
 *
 * This test tokenizes each file and fails when a script skips the guard, so a
 * new tool cannot be added unprotected by accident.
 *
 * Run:
 *   php backend/tests/unit/ToolsCliGuardTest.php
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const TOOLS_DIR = __DIR__ . '/../../../tools';
const BOOTSTRAP_RELATIVE = 'setup/bootstrap.php';
const IGNORED_TOKENS = [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

/**
 * @return list<string> absolute paths of every .php file under tools/
 */
function listToolScripts(): array
{
    $root = realpath(TOOLS_DIR);
    if ($root === false) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if ($entry->isFile() && strtolower($entry->getExtension()) === 'php') {
            $files[] = str_replace('\\', '/', $entry->getPathname());
        }
    }
    sort($files);

    return $files;
}

/**
 * Splits a file into top-level statements (token arrays) after dropping the
 * open tag, whitespace, comments and the leading declare(strict_types=1).
 *
 * @return list<list<array{0:int|string,1:string}>>
 */
function leadingStatements(string $path, int $limit): array
{
    $source = file_get_contents($path);
    $tokens = $source === false ? [] : token_get_all($source);

    $statements = [];
    $current = [];
    $depth = 0;
    foreach ($tokens as $token) {
        $normalized = is_array($token) ? [$token[0], $token[1]] : [$token, $token];
        if (is_array($token) && in_array($token[0], IGNORED_TOKENS, true)) {
            continue;
        }
        $current[] = $normalized;
        if ($normalized[1] === '{') {
            $depth++;
        } elseif ($normalized[1] === '}') {
            $depth--;
        }
        $endsStatement = $depth === 0 && ($normalized[1] === ';' || $normalized[1] === '}');
        if ($endsStatement) {
            $statements[] = $current;
            $current = [];
            if (count($statements) >= $limit) {
                break;
            }
        }
    }

    return $statements;
}

/**
 * @param list<array{0:int|string,1:string}> $statement
 */
function statementText(array $statement): string
{
    return implode('', array_column($statement, 1));
}

/**
 * @param list<array{0:int|string,1:string}> $statement
 */
function isStrictTypesDeclare(array $statement): bool
{
    return $statement !== [] && $statement[0][0] === T_DECLARE && str_contains(statementText($statement), 'strict_types');
}

/**
 * @param list<array{0:int|string,1:string}> $statement
 */
function isBootstrapRequire(array $statement, bool $isInsideSetupDir): bool
{
    if ($statement === [] || $statement[0][0] !== T_REQUIRE_ONCE) {
        return false;
    }
    $text = statementText($statement);
    $expectedSuffix = $isInsideSetupDir ? "'/bootstrap.php'" : "'/" . BOOTSTRAP_RELATIVE . "'";

    return str_contains($text, $expectedSuffix) && str_contains($text, '__DIR__');
}

$scripts = listToolScripts();
$bootstrapPath = str_replace('\\', '/', (string) realpath(TOOLS_DIR . '/' . BOOTSTRAP_RELATIVE));

TestSuite::run('tools/ contains scripts to protect and the bootstrap exists', function () use ($scripts, $bootstrapPath): void {
    assertTrue(count($scripts) >= 5, 'Expected at least the setup scripts under tools/');
    assertTrue(in_array($bootstrapPath, $scripts, true), 'tools/setup/bootstrap.php must exist');
});

TestSuite::run('bootstrap.php starts with the CLI SAPI guard before any other statement', function () use ($bootstrapPath): void {
    $statements = leadingStatements($bootstrapPath, 2);
    assertTrue(isset($statements[0]) && isStrictTypesDeclare($statements[0]), 'bootstrap.php must start with declare(strict_types=1)');
    assertTrue(isset($statements[1]), 'bootstrap.php must have a statement after declare');

    $guard = statementText($statements[1]);
    assertTrue(str_starts_with($guard, 'if('), 'Second statement must be the guard: ' . $guard);
    assertTrue(str_contains($guard, 'PHP_SAPI') && str_contains($guard, "'cli'"), 'Guard must test PHP_SAPI against cli');
    assertTrue(str_contains($guard, 'exit'), 'Guard must exit for non-CLI SAPIs');
    assertFalse(str_contains($guard, 'define('), 'Guard must run before define(BASE_PATH)');
});

TestSuite::run('every other tools/**/*.php requires the bootstrap as its first statement', function () use ($scripts, $bootstrapPath): void {
    $setupDir = dirname($bootstrapPath);
    $offenders = [];
    foreach ($scripts as $script) {
        if ($script === $bootstrapPath) {
            continue;
        }
        $statements = leadingStatements($script, 2);
        $first = $statements[0] ?? [];
        $requireStatement = isStrictTypesDeclare($first) ? ($statements[1] ?? []) : $first;
        if (!isBootstrapRequire($requireStatement, dirname($script) === $setupDir)) {
            $offenders[] = substr($script, strlen(dirname($setupDir)) + 1);
        }
    }
    assertEquals([], $offenders, 'Scripts without the CLI guard bootstrap as first statement: ' . implode(', ', $offenders));
});

TestSuite::run('no tools/**/*.php accepts a password through a command-line flag', function () use ($scripts): void {
    $offenders = [];
    foreach ($scripts as $script) {
        $source = (string) file_get_contents($script);
        if (preg_match('/--[a-z-]*pass(word)?=/i', $source) === 1) {
            $offenders[] = basename($script);
        }
    }
    assertEquals([], $offenders, 'Secrets must come from prompts or XESTIFY_* env vars, not flags: ' . implode(', ', $offenders));
});

TestSuite::summary();
exit(TestSuite::exitCode());
