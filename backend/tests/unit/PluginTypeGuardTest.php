<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/plugins/application/PluginTypeGuard.php';

use Xestify\plugins\application\PluginTypeGuard;

$guard = new PluginTypeGuard();

echo str_repeat('-', 40) . "\n";

TestSuite::run('assertTypeUnchanged() allows a manifest with the same plugin_type', function () use ($guard): void {
    $threw = false;
    try {
        $guard->assertTypeUnchanged(
            ['plugin_type' => 'entity'],
            ['slug' => 'clients', 'type' => 'entity']
        );
    } catch (\DomainException) {
        $threw = true;
    }

    assertTrue(!$threw, 'Same plugin_type must not raise');
});

TestSuite::run('assertTypeUnchanged() rejects a manifest that changes plugin_type', function () use ($guard): void {
    $threw = false;
    $message = '';
    try {
        $guard->assertTypeUnchanged(
            ['plugin_type' => 'entity'],
            ['slug' => 'clients', 'type' => 'extension']
        );
    } catch (\DomainException $e) {
        $threw = true;
        $message = $e->getMessage();
    }

    assertTrue($threw, 'Changing plugin_type must raise DomainException');
    assertTrue(
        str_contains($message, "cannot change type from 'entity' to 'extension'"),
        'Exception message must name the slug and both types: ' . $message
    );
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
