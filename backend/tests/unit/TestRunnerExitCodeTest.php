<?php

/**
 * TestRunnerExitCodeTest — Guards the grouped runner's failure signal.
 *
 * backend/tests/run.php decides "did this file fail?" purely from the child
 * process exit code (passthru + $exitCode !== 0). A test file that never
 * calls exit(TestSuite::exitCode()) always exits 0 (PHP's default), so any
 * failed assertion inside it prints a "false" but the grouped runner still
 * counts it as passed.
 *
 * Scope: only the files fixed by auditoria [20260811][03.02] (HookFilterTest.php,
 * HookFilterApiTest.php; the third file originally fixed alongside them,
 * PluginHookRegistryTableTest.php, was later deleted entirely — see [20260811][03.01]).
 * CommentsPluginTest.php, EntityDataTableTest.php and GenericRepositoryTest.php
 * have the same gap but are explicitly out of scope for that finding — left
 * for a future session instead of being swept in here.
 *
 * Run:
 *   php backend/tests/unit/TestRunnerExitCodeTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';

echo str_repeat('-', 40) . "\n";

TestSuite::run(
    'HookFilterTest/HookFilterApiTest propagate TestSuite::exitCode()',
    function (): void {
        $files = [
            'backend/tests/unit/HookFilterTest.php',
            'backend/tests/integration/HookFilterApiTest.php',
        ];

        $repoRoot = dirname(BASE_PATH);
        $missing = [];

        foreach ($files as $relativePath) {
            $content = file_get_contents($repoRoot . '/' . $relativePath);
            $propagatesExitCode = $content !== false
                && preg_match('/TestSuite::summary\(\);\s*exit\(TestSuite::exitCode\(\)\);\s*\z/', $content) === 1;

            if (!$propagatesExitCode) {
                $missing[] = $relativePath;
            }
        }

        assertEquals(
            [],
            $missing,
            'these files can never fail the grouped runner (missing exit(TestSuite::exitCode()) '
                . 'right after TestSuite::summary()): ' . implode(', ', $missing)
        );
    }
);

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
