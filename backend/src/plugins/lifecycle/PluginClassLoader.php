<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use PDO;
use ReflectionClass;
use ReflectionNamedType;
use Xestify\plugins\contracts\PluginLifecycleInterface;

final class PluginClassLoader
{
    private const PLUGIN_NAMESPACE_PREFIX = 'Xestify\\plugins\\';

    public function __construct(private string $pluginsDir, private PDO $pdo)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
    }

    /**
     * @return object|null
     */
    public function instantiateHooks(string $pluginName): ?object
    {
        $this->requirePluginFile($pluginName, 'Hooks.php');

        $class = self::PLUGIN_NAMESPACE_PREFIX . $pluginName . '\\Hooks';
        if (!class_exists($class)) {
            return null;
        }

        return $this->instantiateWithOptionalPdo($class);
    }

    public function instantiateLifecycle(string $pluginName): ?PluginLifecycleInterface
    {
        $this->requirePluginFile($pluginName, 'Lifecycle.php');

        $class = self::PLUGIN_NAMESPACE_PREFIX . $pluginName . '\\Lifecycle';
        if (!class_exists($class)) {
            return null;
        }

        return $this->instantiateWithOptionalPdo($class);
    }

    private function requirePluginFile(string $pluginName, string $filename): void
    {
        $path = $this->pluginsDir . '/' . $pluginName . '/' . $filename;

        if (file_exists($path)) {
            require_once $path;
        }
    }

    /**
     * @return object
     */
    private function instantiateWithOptionalPdo(string $class): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && $type->getName() === PDO::class) {
                    return new $class($this->pdo); // NOSONAR - plugin hook convention
                }
            }
        }

        return new $class(); // NOSONAR - plugin hook convention
    }
}
