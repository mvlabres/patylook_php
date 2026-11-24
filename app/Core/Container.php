<?php

declare(strict_types=1);

namespace Patylook\Core;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * A small dependency-injection container. Services are registered against an interface (or class)
 * name; classes that are not registered are built by reading their constructor's type hints.
 */
final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, bool> ids registered as shared (one instance per container) */
    private array $shared = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Registers a shared service: the factory runs once and its result is reused.
     *
     * @param Closure(self): mixed|string|null $concrete a factory, a class name, or null for $id itself
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->factories[$id] = $this->factory($id, $concrete);
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    /**
     * Registers a service that is built anew on every request for it.
     *
     * @param Closure(self): mixed|string|null $concrete
     */
    public function bind(string $id, Closure|string|null $concrete = null): void
    {
        $this->factories[$id] = $this->factory($id, $concrete);
        unset($this->shared[$id], $this->instances[$id]);
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            $value = ($this->factories[$id])($this);
            if (isset($this->shared[$id])) {
                $this->instances[$id] = $value;
            }
            return $value;
        }
        return $this->build($id);
    }

    /** @param Closure(self): mixed|string|null $concrete */
    private function factory(string $id, Closure|string|null $concrete): Closure
    {
        if ($concrete instanceof Closure) {
            return $concrete;
        }
        $class = $concrete ?? $id;
        return fn (self $c): mixed => $c->build($class);
    }

    /** Instantiates a class, resolving its constructor dependencies from the container. */
    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Nada registrado para "%s".', $class));
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException(sprintf('"%s" não pode ser instanciada.', $class));
        }
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Não foi possível resolver o parâmetro $%s de "%s": registre a classe no container.',
                    $parameter->getName(),
                    $class,
                ));
            }
        }
        return $reflection->newInstanceArgs($arguments);
    }
}
