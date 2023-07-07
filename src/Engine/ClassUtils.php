<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

abstract class ClassUtils
{
    /**
     * @template T
     * @param class-string<T> $className
     * @return array<class-string<T>>
     */
    public static function getLocalChildClassNames(string $className): array
    {
        $class = new \ReflectionClass($className);
        $fileName = $class->getFileName();
        $childClassNames = [];
        foreach (glob(dirname($fileName) . '/*.php') as $file) {
            $name = basename($file, '.php');
            $childClassName = $class->getNamespaceName() . '\\' . $name;
            if ($childClassName === $className || ! is_subclass_of($childClassName, $className)) {
                continue;
            }

            $childClassNames[] = $childClassName;
        }

        return $childClassNames;
    }
}