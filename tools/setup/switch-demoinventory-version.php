<?php

declare(strict_types=1);

$version = $argv[1] ?? '';

if (!in_array($version, ['1', '2', 'v1', 'v2', '1.0.0', '2.0.0'], true)) {
    fwrite(STDERR, "Uso: php tools/setup/switch-demoinventory-version.php <1|2>\n");
    exit(1);
}

$normalized = in_array($version, ['1', 'v1', '1.0.0'], true) ? 'v1' : 'v2';
$root = dirname(__DIR__, 2);
$pluginDir = $root . '/plugins/demoinventory';
$versionsDir = $pluginDir . '/versions';

$manifestSource = $versionsDir . '/manifest.' . $normalized . '.json';
$schemaSource = $versionsDir . '/schema.' . $normalized . '.json';
$manifestTarget = $pluginDir . '/manifest.json';
$schemaTarget = $pluginDir . '/schema.json';

if (!is_file($manifestSource) || !is_file($schemaSource)) {
    fwrite(STDERR, "No se encuentran los archivos de version para {$normalized}.\n");
    exit(1);
}

$manifestContent = file_get_contents($manifestSource);
$schemaContent = file_get_contents($schemaSource);

if ($manifestContent === false || $schemaContent === false) {
    fwrite(STDERR, "No se pudieron leer los archivos fuente de {$normalized}.\n");
    exit(1);
}

if (file_put_contents($manifestTarget, $manifestContent) === false) {
    fwrite(STDERR, "No se pudo escribir manifest.json.\n");
    exit(1);
}

if (file_put_contents($schemaTarget, $schemaContent) === false) {
    fwrite(STDERR, "No se pudo escribir schema.json.\n");
    exit(1);
}

$decodedManifest = json_decode($manifestContent, true);
$versionLabel = is_array($decodedManifest) && isset($decodedManifest['version'])
    ? (string) $decodedManifest['version']
    : $normalized;

echo "demoinventory cambiado a {$normalized} ({$versionLabel}).\n";
