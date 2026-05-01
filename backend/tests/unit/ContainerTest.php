<?php

declare(strict_types=1);

use Xestify\Core\Container;

require_once dirname(__DIR__, 2) . '/src/Core/Container.php';

// ---------------------------------------------------------------------------
// Utilidad de test minimalista (sin PHPUnit — zero dependencias)
// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function test(string $label, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "  ✅ {$label}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  ❌ {$label}\n     → {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_equals(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $msg ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true)
        );
    }
}

function assert_true(mixed $value, string $msg = 'Expected true'): void
{
    if ($value !== true) {
        throw new RuntimeException($msg);
    }
}

function assert_false(mixed $value, string $msg = 'Expected false'): void
{
    if ($value !== false) {
        throw new RuntimeException($msg);
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo "\nContainerTest\n";
echo str_repeat('-', 40) . "\n";

test('register() y get() devuelven instancia nueva cada vez', function () {
    $c = new Container();
    $c->register('foo', fn() => new stdClass());

    $a = $c->get('foo');
    $b = $c->get('foo');

    assert_true($a !== $b, 'register() debe crear instancia nueva en cada llamada');
});

test('singleton() devuelve siempre la misma instancia', function () {
    $c = new Container();
    $c->singleton('bar', fn() => new stdClass());

    $a = $c->get('bar');
    $b = $c->get('bar');

    assert_true($a === $b, 'singleton() debe devolver la misma instancia');
});

test('get() con factory callable que recibe el container', function () {
    $c = new Container();
    $c->register('dep', fn() => 'dependency_value');
    $c->register('service', fn(Container $c) => 'resolved:' . $c->get('dep'));

    assert_equals('resolved:dependency_value', $c->get('service'));
});

test('has() retorna true cuando el servicio está registrado', function () {
    $c = new Container();
    $c->register('exists', fn() => 'x');

    assert_true($c->has('exists'));
});

test('has() retorna false cuando el servicio NO está registrado', function () {
    $c = new Container();

    assert_false($c->has('not_registered'));
});

test('get() lanza InvalidArgumentException si el servicio no existe', function () {
    $c = new Container();

    try {
        $c->get('missing');
        throw new RuntimeException('Debería haber lanzado excepción');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'missing'));
    }
});

test('register() sobreescribe un binding previo', function () {
    $c = new Container();
    $c->register('val', fn() => 'original');
    $c->register('val', fn() => 'overwritten');

    assert_equals('overwritten', $c->get('val'));
});

test('singleton() no reinicia si se re-registra bajo el mismo id', function () {
    $c = new Container();
    $count = 0;
    $c->singleton('counter', function () use (&$count) {
        $count++;
        return new stdClass();
    });

    $c->get('counter');
    $c->get('counter');

    assert_equals(1, $count, 'Factory singleton debe ejecutarse solo una vez');
});

// ---------------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------------

echo str_repeat('-', 40) . "\n";
echo "Resultado: {$passed} passed, {$failed} failed\n\n";

exit($failed > 0 ? 1 : 0);
