<?php

declare(strict_types=1);

namespace Xestify\Core;

use InvalidArgumentException;

/**
 * Contenedor de inyección de dependencias minimalista.
 * Zero dependencias externas. Sin autowiring — registro explícito.
 */
class Container
{
    /** @var array<string, callable> Factories registradas */
    private array $bindings = [];

    /** @var array<string, mixed> Instancias singleton resueltas */
    private array $instances = [];

    /**
     * Registra un factory callable. Se ejecuta cada vez que se llama a get().
     *
     * @param string   $id      Identificador del servicio
     * @param callable $factory Factory que recibe el Container y devuelve la instancia
     */
    public function register(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    /**
     * Registra un factory que solo se ejecuta una vez.
     * Las llamadas posteriores devuelven la misma instancia.
     *
     * @param string   $id      Identificador del servicio
     * @param callable $factory Factory que recibe el Container y devuelve la instancia
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = function (Container $c) use ($id, $factory): mixed {
            if (!array_key_exists($id, $this->instances)) {
                $this->instances[$id] = $factory($c);
            }
            return $this->instances[$id];
        };
    }

    /**
     * Resuelve y devuelve el servicio registrado bajo $id.
     *
     * @throws InvalidArgumentException Si el servicio no está registrado
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new InvalidArgumentException("Service '{$id}' not registered in container.");
        }

        return ($this->bindings[$id])($this);
    }

    /**
     * Indica si el servicio está registrado.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }
}
