<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Cycle;

final class LazyGhostReflectionRegistry
{
    /**
     * @var array<class-string, \ReflectionClass<object>>
     */
    private array $reflectionCache = [];

    /**
     * @param class-string $class
     * @return \ReflectionClass<object>
     */
    public function reflection(string $class): \ReflectionClass
    {
        return $this->reflectionCache[$class] ??= new \ReflectionClass($class);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    public function property(\ReflectionClass $reflection, string $name): \ReflectionProperty|null
    {
        $currentReflection = $reflection;

        while (true) {
            if ($currentReflection->hasProperty($name)) {
                $reflectedProperty = $currentReflection->getProperty($name);

                return $reflectedProperty->isStatic() ? null : $reflectedProperty;
            }

            $parentReflection = $currentReflection->getParentClass();

            if ($parentReflection === false) {
                return null;
            }

            $currentReflection = $parentReflection;
        }
    }

    /**
     * @param class-string $class
     * @return list<\ReflectionProperty>
     */
    public function extractableProperties(string $class): array
    {
        /** @var list<\ReflectionProperty> $properties */
        $properties = [];

        foreach ($this->reflection($class)->getProperties() as $reflectedProperty) {
            if (!$reflectedProperty->isStatic()) {
                $properties[] = $reflectedProperty;
            }
        }

        return $properties;
    }
}
