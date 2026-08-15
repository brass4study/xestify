<?php

declare(strict_types=1);

function loadPluginTestEnv(string $basePath): void
{
    $envFile = $basePath . '/.env';
    if (!file_exists($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

/**
 * @param array<string, mixed> $manifest
 * @param array<string, mixed>|null $schema
 */
function createPluginFixture(
    array $manifest,
    bool $withHooks = false,
    bool $invalidJson = false,
    ?array $schema = null,
    ?string $lifecycleSource = null,
    ?string $hooksSource = null
): string {
    $root = sys_get_temp_dir() . '/xestify_plugin_test_' . bin2hex(random_bytes(4));
    $slug = is_string($manifest['name'] ?? null) ? $manifest['name'] : 'test_plugin';
    $pluginDir = $root . '/' . $slug;

    mkdir($pluginDir, 0777, true);

    $manifest['label'] ??= $slug;

    $jsonContent = $invalidJson ? '{bad json' : (string) json_encode($manifest, JSON_PRETTY_PRINT);
    file_put_contents($pluginDir . '/manifest.json', $jsonContent);

    if (($manifest['type'] ?? '') === 'entity' && !$invalidJson) {
        $schema ??= [
            'version' => $manifest['version'] ?? '1.0.0',
            'identities' => [
                'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
            ],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ];
        file_put_contents($pluginDir . '/schema.json', (string) json_encode($schema, JSON_PRETTY_PRINT));
    }

    if ($withHooks) {
        $hooksSource ??= "<?php\ndeclare(strict_types=1);\n// Hooks loaded for test\n";
        file_put_contents($pluginDir . '/Hooks.php', $hooksSource);
    }

    if ($lifecycleSource !== null) {
        file_put_contents($pluginDir . '/Lifecycle.php', $lifecycleSource);
    }

    return $root;
}

function removePluginFixture(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    $items = scandir($root) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $root . '/' . $item;
        if (is_dir($path)) {
            removePluginFixture($path);
        } else {
            unlink($path);
        }
    }

    rmdir($root);
}

/**
 * Cleans up a plugin fixture row and its update-history snapshots.
 *
 * Accepts the plugin_name (stable identity, = fixture folder name). If the
 * fixture's slug was renamed mid-test (STORY 10.3 rename cascade tests),
 * this also cleans up history rows left under the renamed slug, since
 * plugin_update_history.slug tracks the live slug, not plugin_name.
 */
function cleanupPluginRecord(PDO $pdo, string $pluginName): void
{
    $currentSlugStmt = $pdo->prepare("SELECT slug FROM plugins WHERE manifest_json->>'name' = :plugin_name");
    $currentSlugStmt->execute([':plugin_name' => $pluginName]);
    $currentSlug = $currentSlugStmt->fetchColumn();

    $slugsToClean = [$pluginName];
    if (is_string($currentSlug) && $currentSlug !== '' && $currentSlug !== $pluginName) {
        $slugsToClean[] = $currentSlug;
    }

    foreach ($slugsToClean as $slug) {
        $history = $pdo->prepare('DELETE FROM plugin_update_history WHERE slug = :slug');
        $history->execute([':slug' => $slug]);
    }

    $stmt = $pdo->prepare("DELETE FROM plugins WHERE manifest_json->>'name' = :plugin_name");
    $stmt->execute([':plugin_name' => $pluginName]);
}
