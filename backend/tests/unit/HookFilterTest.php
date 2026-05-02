<?php

/**
 * HookFilterTest — Unit tests for HookDispatcher::applyFilter().
 *
 * Tests STORY 6.2: registerTabs and registerActions filter hooks.
 *
 * Run:
 *   php backend/tests/unit/HookFilterTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/plugins/HookDispatcher.php';

use Xestify\plugins\HookDispatcher;

echo str_repeat('-', 40) . "\n";

// ---------------------------------------------------------------------------

TestSuite::run('applyFilter() returns empty array when no callbacks registered', function (): void {
    $d = new HookDispatcher();
    $result = $d->applyFilter('registerTabs', [], ['entity' => 'client']);
    assertEquals([], $result, 'Should return initial empty array');
});

TestSuite::run('applyFilter() accumulates items from a single callback', function (): void {
    $d = new HookDispatcher();
    $d->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'info', 'label' => 'Info'];
        return $tabs;
    });
    $result = $d->applyFilter('registerTabs', [], ['entity' => 'client']);
    assertEquals(1, count($result), 'Should have 1 tab');
    assertEquals('info', $result[0]['id'], 'Tab id should be info');
    assertEquals('Info', $result[0]['label'], 'Tab label should be Info');
});

TestSuite::run('applyFilter() accumulates items from multiple callbacks in priority order', function (): void {
    $d = new HookDispatcher();

    $d->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'second', 'label' => 'Second'];
        return $tabs;
    }, 20);

    $d->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'first', 'label' => 'First'];
        return $tabs;
    }, 5);

    $result = $d->applyFilter('registerTabs', [], []);
    assertEquals(2, count($result), 'Should have 2 tabs');
    assertEquals('first', $result[0]['id'], 'Lower priority executes first');
    assertEquals('second', $result[1]['id'], 'Higher priority executes second');
});

TestSuite::run('applyFilter() passes args as read-only context to callbacks', function (): void {
    $d = new HookDispatcher();
    $receivedArgs = [];

    $d->register('registerTabs', static function (array $tabs, array $args) use (&$receivedArgs): array {
        $receivedArgs = $args;
        $tabs[] = ['id' => 'tab', 'label' => 'Tab'];
        return $tabs;
    });

    $d->applyFilter('registerTabs', [], ['entity' => 'product', 'record_id' => 42]);
    assertEquals('product', $receivedArgs['entity'], 'Args entity should be product');
    assertEquals(42, $receivedArgs['record_id'], 'Args record_id should be 42');
});

TestSuite::run('applyFilter() registerActions accumulates action buttons', function (): void {
    $d = new HookDispatcher();

    $d->register('registerActions', static function (array $actions, array $args): array {
        $actions[] = ['id' => 'view', 'label' => 'Ver', 'icon' => 'fa-eye'];
        return $actions;
    });

    $d->register('registerActions', static function (array $actions, array $args): array {
        $actions[] = ['id' => 'archive', 'label' => 'Archivar', 'icon' => 'fa-archive'];
        return $actions;
    });

    $result = $d->applyFilter('registerActions', [], ['entity' => 'client']);
    assertEquals(2, count($result), 'Should have 2 actions');
    assertEquals('view', $result[0]['id'], 'First action id');
    assertEquals('archive', $result[1]['id'], 'Second action id');
});

TestSuite::run('applyFilter() skips failing callbacks and continues accumulating', function (): void {
    $d = new HookDispatcher();

    $d->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'ok', 'label' => 'OK'];
        return $tabs;
    }, 5);

    $d->register('registerTabs', static function (array $tabs, array $args): array {
        throw new \RuntimeException('Plugin failure');
    }, 10);

    $d->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'also-ok', 'label' => 'Also OK'];
        return $tabs;
    }, 15);

    $result = $d->applyFilter('registerTabs', [], []);
    assertEquals(2, count($result), 'Failing callback should be skipped, others continue');
    assertEquals('ok', $result[0]['id'], 'First tab still present');
    assertEquals('also-ok', $result[1]['id'], 'Third tab still present');
});

TestSuite::run('execute() and applyFilter() coexist independently on same hook name', function (): void {
    $d = new HookDispatcher();

    $d->register('beforeSave', static function (array $ctx): array {
        $ctx['validated'] = true;
        return $ctx;
    });

    $d->register('registerTabs', static function (array $tabs, array $args): array {
        $tabs[] = ['id' => 'x', 'label' => 'X'];
        return $tabs;
    });

    $execResult   = $d->execute('beforeSave', ['entity' => 'client']);
    $filterResult = $d->applyFilter('registerTabs', [], []);

    assertTrue($execResult['validated'] === true, 'execute() should still work');
    assertEquals(1, count($filterResult), 'applyFilter() should still work');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
