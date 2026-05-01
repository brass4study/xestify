<?php

declare(strict_types=1);

use Xestify\Core\Container;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/Core/Container.php';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo "\nContainerTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('register() y get() devuelven instancia nueva cada vez', function () {
    $c = new Container();
    $c->register('foo', fn() => new stdClass());

    $a = $c->get('foo');
    $b = $c->get('foo');

    assertTrue($a !== $b, 'register() debe crear instancia nueva en cada llamada');
});

TestSuite::run('singleton() devuelve siempre la misma instancia', function () {
    $c = new Container();
    $c->singleton('bar', fn() => new stdClass());

    $a = $c->get('bar');
    $b = $c->get('bar');

    assertTrue($a === $b, 'singleton() debe devolver la misma instancia');
});

TestSuite::run('get() con factory callable que recibe el container', function () {
    $c = new Container();
    $c->register('dep', fn() => 'dependency_value');
    $c->register('service', fn(Container $c) => 'resolved:' . $c->get('dep'));

    assertEquals('resolved:dependency_value', $c->get('service'));
});

TestSuite::run('has() retorna true cuando el servicio está registrado', function () {
    $c = new Container();
    $c->register('exists', fn() => 'x');

    assertTrue($c->has('exists'));
});

TestSuite::run('has() retorna false cuando el servicio NO está registrado', function () {
    $c = new Container();

    assertFalse($c->has('not_registered'));
});

TestSuite::run('get() lanza InvalidArgumentException si el servicio no existe', function () {
    $c = new Container();

    try {
        $c->get('missing');
        throw new \AssertionError('Debería haber lanzado excepción');
    } catch (InvalidArgumentException $e) {
        assertTrue(str_contains($e->getMessage(), 'missing'));
    }
});

TestSuite::run('register() sobreescribe un binding previo', function () {
    $c = new Container();
    $c->register('val', fn() => 'original');
    $c->register('val', fn() => 'overwritten');

    assertEquals('overwritten', $c->get('val'));
});

TestSuite::run('singleton() no reinicia si se re-registra bajo el mismo id', function () {
    $c = new Container();
    $c->singleton('svc', fn() => new stdClass());
    $first = $c->get('svc');
    $c->singleton('svc', fn() => new stdClass());
    $second = $c->get('svc');

    assertTrue($first === $second, 'singleton() debe mantener la instancia original');
});

TestSuite::summary();
exit(TestSuite::exitCode());

