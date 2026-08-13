<?php

declare(strict_types=1);

namespace Xestify\plugins\discovery;

use Xestify\plugins\guards\PluginCompatibilityValidator;
use Xestify\plugins\guards\PluginDependencyValidator;

final class PluginSourceService
{
    public function __construct(
        private PluginDiscoveryService $discovery,
        private PluginManifestReader $manifestReader,
        private PluginSchemaReader $schemaReader,
        private PluginCompatibilityValidator $compatibilityValidator,
        private PluginDependencyValidator $dependencyValidator
    ) {
    }

    /**
     * @return list<string>
     */
    public function discover(): array
    {
        return $this->discovery->discover();
    }

    /**
     * @return array<string, mixed>
     */
    public function readManifest(string $slug): array
    {
        return $this->manifestReader->read($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function readValidatedManifest(string $slug): array
    {
        $manifest = $this->manifestReader->read($slug);
        $this->compatibilityValidator->validate($manifest);
        $this->dependencyValidator->validate($manifest);

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>|null
     */
    public function readSchema(array $manifest): ?array
    {
        return $this->schemaReader->read($manifest);
    }
}
