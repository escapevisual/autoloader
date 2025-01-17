<?php

/**
 * Reflection helper.
 */
declare(strict_types=1);

namespace HDNET\Autoloader\Utility;

/**
 * Reflection helper.
 */
class ReflectionUtility
{
    /**
     * Check if the given class is instantiable.
     *
     * @param string $className
     */
    public static function isInstantiable($className): bool
    {
        $reflectionClass = new \ReflectionClass($className);

        return (bool) $reflectionClass->isInstantiable();
    }

    /**
     * Get the name of the parent class.
     *
     * @param string $className
     *
     * @return string
     */
    public static function getParentClassName($className)
    {
        $reflectionClass = new \ReflectionClass($className);

        return $reflectionClass->getParentClass()->getName();
    }

    /**
     * Check if the first class is found in the Hierarchy of the second.
     */
    public static function isClassInOtherClassHierarchy(string $searchClass, string $checkedClass): bool
    {
        $searchClass = trim($searchClass, '\\');
        if (!class_exists($searchClass)) {
            return false;
        }
        $checked = trim($checkedClass, '\\');

        try {
            if ($searchClass === $checked) {
                return true;
            }
            $reflection = new \ReflectionClass($searchClass);
            while ($reflection = $reflection->getParentClass()) {
                if ($checked === trim($reflection->getName(), '\\')) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Get properties of the given class, that are als declared in the given class.
     *
     * @return array
     */
    public static function getDeclaringProperties(string $className)
    {
        $classReflection = new \ReflectionClass($className);
        $own = array_filter($classReflection->getProperties(), function ($property) use ($className) {
            return trim((string) $property->class, '\\') === trim($className, '\\');
        });

        return array_map(function ($item) {
            return (string) $item->name;
        }, $own);
    }
}
