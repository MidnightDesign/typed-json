<?php

declare(strict_types=1);

namespace TypedJson;

use ReflectionClass;
use RuntimeException;
use function array_key_exists;

/**
 * @phpstan-type JsonNode object | string | bool | int | float | null | list<mixed>
 */
final class ObjectFactory
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, JsonNode> $members
     * @return T
     */
    public static function create(string $class, array $members): object
    {
        $object = self::construct($class, $members);
        if ($members === []) {
            return $object;
        }
        $reflection = new ReflectionClass($class);
        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (!array_key_exists($name, $members)) {
                continue;
            }
            $property->setValue($object, $members[$name]);
            unset($members[$name]);
        }
        return $object;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, JsonNode> $members
     * @return T
     */
    private static function construct(string $class, array &$members): object
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            return new $class();
        }
        if (!$constructor->isPublic()) {
            throw new RuntimeException(sprintf("Can't construct %s because its constructor is not public", $class));
        }
        $parameters = $constructor->getParameters();
        $arguments = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            if (!array_key_exists($name, $members) && !$parameter->isOptional()) {
                throw new RuntimeException(
                    sprintf(
                        'The constructor of %s requires a parameter named "%s", but none was provided. The provided members are: %s',
                        $class,
                        $name,
                        implode(', ', array_keys($members)),
                    ),
                );
            }
            $arguments[$name] = $members[$name];
            unset($members[$name]);
        }
        return new $class(...$arguments);
    }
}
