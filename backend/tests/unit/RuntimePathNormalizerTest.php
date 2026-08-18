<?php

declare(strict_types=1);

use Xestify\core\RuntimePathNormalizer;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/core/RuntimePathNormalizer.php';

const ROUTE_API_ENTITIES = '/api/v1/entities';
const ROUTE_HEALTH = '/health';

echo "\nRuntimePathNormalizerTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('normaliza raiz y paths directos de runtime', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/', $normalizer->normalize(''));
    assertEquals('/', $normalizer->normalize('/'));
    assertEquals('/api', $normalizer->normalize('/api'));
    assertEquals(ROUTE_API_ENTITIES, $normalizer->normalize(ROUTE_API_ENTITIES));
    assertEquals(ROUTE_HEALTH, $normalizer->normalize(ROUTE_HEALTH));
});

TestSuite::run('recorta alias antes de /api y /health', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals(ROUTE_API_ENTITIES, $normalizer->normalize('/xestify/api/v1/entities'));
    assertEquals(ROUTE_HEALTH, $normalizer->normalize('/xestify/health'));
    assertEquals('/health/check', $normalizer->normalize('/xestify/health/check'));
});

TestSuite::run('mantiene rutas no runtime sin cambios', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/xestify/plugins/comments/plugin.js', $normalizer->normalize('/xestify/plugins/comments/plugin.js'));
});

TestSuite::run('recorta el alias de /api aunque no tenga subruta, igual que /health', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/api', $normalizer->normalize('/xestify/api'));
});

TestSuite::run('reintenta tras una primera aparicion del marcador que no cae en limite de segmento', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/api/v1/x', $normalizer->normalize('/apiary/api/v1/x'));
    assertEquals('/health/x', $normalizer->normalize('/healthy/health/x'));
});

TestSuite::run('no recorta cuando el marcador solo aparece como prefijo de otra palabra', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/apiary', $normalizer->normalize('/apiary'));
    assertEquals('/healthy', $normalizer->normalize('/healthy'));
});

TestSuite::summary();
exit(TestSuite::exitCode());
