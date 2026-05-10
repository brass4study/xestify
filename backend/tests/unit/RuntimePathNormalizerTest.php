<?php

declare(strict_types=1);

use Xestify\core\RuntimePathNormalizer;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/core/RuntimePathNormalizer.php';

echo "\nRuntimePathNormalizerTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('normaliza raiz y paths directos de runtime', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/', $normalizer->normalize(''));
    assertEquals('/', $normalizer->normalize('/'));
    assertEquals('/api', $normalizer->normalize('/api'));
    assertEquals('/api/v1/entities', $normalizer->normalize('/api/v1/entities'));
    assertEquals('/health', $normalizer->normalize('/health'));
});

TestSuite::run('recorta alias antes de /api y /health', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/api/v1/entities', $normalizer->normalize('/xestify/api/v1/entities'));
    assertEquals('/health', $normalizer->normalize('/xestify/health'));
    assertEquals('/health/check', $normalizer->normalize('/xestify/health/check'));
});

TestSuite::run('mantiene rutas no runtime sin cambios', function (): void {
    $normalizer = new RuntimePathNormalizer();

    assertEquals('/xestify/plugins/comments/plugin.js', $normalizer->normalize('/xestify/plugins/comments/plugin.js'));
});

TestSuite::summary();
exit(TestSuite::exitCode());
