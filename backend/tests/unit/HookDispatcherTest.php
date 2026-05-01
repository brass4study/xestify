<?php

/**
 * HookDispatcherTest — Unit tests for HookDispatcher.
 *
 * Run:
 *   php backend/tests/unit/HookDispatcherTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/plugins/HookDispatcher.php';

use Xestify\exceptions\HookException;
use Xestify\plugins\HookDispatcher;

echo str_repeat('-', 40) . "\n";

// ---------------------------------------------------------------------------

TestSuite::run('execute() returns unchanged context when no callbacks registered', function (): void {
    $d = new HookDispatcher();
    $result = $d->execute('beforeSave', ['entity' => 'client']);
    assertEquals(['entity' => 'client'], $result, 'Context must be unchanged');
});

TestSuite::run('execute() calls registered callback and returns its result', function (): void {
    $d = new HookDispatcher();
    $d->register('beforeSave', static function (array $ctx): array {
        $ctx['touched'] = true;
        return $ctx;
    });
    $result = $d->execute('beforeSave', ['entity' => 'client']);
    assertTrue($result['touched'] === true, 'Callback should set touched=true');
});

TestSuite::run('execute() calls multiple callbacks in ascending priority order', function (): void {
    $d = new HookDispatcher();
    $order = [];

    $d->register('afterSave', static function (array $ctx) use (&$order): array {
        $order[] = 'second';
        return $ctx;
    }, 20);

    $d->register('afterSave', static function (array $ctx) use (&$order): array {
        $order[] = 'first';
        return $ctx;
    }, 5);

    $d->execute('afterSave', []);
    assertEquals(['first', 'second'], $order, 'Lower priority value must execute first');
});

TestSuite::run('execute() callbacks at same priority run in registration order', function (): void {
    $d = new HookDispatcher();
    $order = [];

    $d->register('afterSave', static function (array $ctx) use (&$order): array {
        $order[] = 'A';
        return $ctx;
    }, 10);

    $d->register('afterSave', static function (array $ctx) use (&$order): array {
        $order[] = 'B';
        return $ctx;
    }, 10);

    $d->execute('afterSave', []);
    assertEquals(['A', 'B'], $order, 'Same priority must preserve registration order');
});

TestSuite::run('execute() before* callback throwing HookException blocks operation', function (): void {
    $d = new HookDispatcher();
    $d->register('beforeSave', static function (array $ctx): array { // NOSONAR - $ctx required by callable signature
        throw new HookException('Validation failed');
    });

    $threw = false;
    try {
        $d->execute('beforeSave', []);
    } catch (HookException $e) {
        $threw = true;
        assertEquals('Validation failed', $e->getMessage(), 'Exception message should propagate');
    }

    assertTrue($threw, 'before* callback throwing HookException must propagate');
});

TestSuite::run('execute() before* callback throwing generic Throwable wraps in HookException', function (): void {
    $d = new HookDispatcher();
    $d->register('beforeSave', static function (array $ctx): array { // NOSONAR - $ctx required by callable signature
        throw new \InvalidArgumentException('bad input');
    });

    $threw = false;
    try {
        $d->execute('beforeSave', []);
    } catch (HookException $e) {
        $threw = true;
        assertTrue(str_contains($e->getMessage(), 'bad input'), 'Wrapped message should contain original');
    }

    assertTrue($threw, 'before* generic Throwable must be wrapped in HookException');
});

TestSuite::run('execute() after* callback throwing does NOT propagate (logs warning)', function (): void {
    $d = new HookDispatcher();
    $d->register('afterSave', static function (array $ctx): array { // NOSONAR - $ctx required by callable signature
        throw new HookException('side effect failure');
    });

    $threw = false;
    try {
        $result = $d->execute('afterSave', ['ok' => true]);
    } catch (\Throwable) {
        $threw = true;
    }

    assertTrue(!$threw, 'after* failure must NOT propagate');
    assertTrue(isset($result) && $result['ok'] === true, 'Context must be unchanged after after* failure');
});

TestSuite::run('execute() after* generic Throwable does NOT propagate', function (): void {
    $d = new HookDispatcher();
    $d->register('afterSave', static function (array $ctx): array { // NOSONAR - $ctx required by callable signature
        throw new \OverflowException('db timeout');
    });

    $threw = false;
    try {
        $d->execute('afterSave', []);
    } catch (\Throwable) {
        $threw = true;
    }

    assertTrue(!$threw, 'after* generic Throwable must not propagate');
});

TestSuite::run('execute() multiple before* callbacks: second not called when first throws', function (): void {
    $d = new HookDispatcher();
    $secondCalled = false;

    $d->register('beforeSave', static function (array $ctx): array { // NOSONAR - $ctx required by callable signature
        throw new HookException('first fails');
    }, 5);

    $d->register('beforeSave', static function (array $ctx) use (&$secondCalled): array {
        $secondCalled = true;
        return $ctx;
    }, 10);

    try {
        $d->execute('beforeSave', []);
    } catch (HookException) {
        return; // expected: before* hook must propagate and stop execution
    }

    assertTrue(!$secondCalled, 'Second before* callback must not be called after first throws');
});

TestSuite::run('execute() callback returning non-array keeps previous context', function (): void {
    $d = new HookDispatcher();
    $d->register('afterSave', static fn(array $ctx): mixed => null); // NOSONAR - $ctx required by callable signature

    $result = $d->execute('afterSave', ['keep' => 'me']);
    assertEquals(['keep' => 'me'], $result, 'Non-array return must preserve previous context');
});

TestSuite::run('register() same hook name with different hooks keeps them separate', function (): void {
    $d = new HookDispatcher();
    $beforeCalled = false;
    $afterCalled = false;

    $d->register('beforeSave', static function (array $ctx) use (&$beforeCalled): array {
        $beforeCalled = true;
        return $ctx;
    });

    $d->register('afterSave', static function (array $ctx) use (&$afterCalled): array {
        $afterCalled = true;
        return $ctx;
    });

    $d->execute('beforeSave', []);
    assertTrue($beforeCalled, 'beforeSave callback should be called');
    assertTrue(!$afterCalled, 'afterSave callback must NOT be called when executing beforeSave');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

