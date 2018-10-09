<?php

/**
 * Reflection helper.
 */
declare(strict_types=1);

namespace HDNET\Autoloader\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\Reflection\ClassReflection;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;

/**
 * Reflection helper.
 */
class ReflectionUtility
{
    /**
     * Create a new class reflection. Do not use the makeInstance or objectManager
     * because the reflection API is also used in front of the caching framework.
     *
     * @param string $className
     *
     * @return ClassReflection
     *
     * @deprecated
     */
    public static function createReflectionClass($className)
    {
        return new ClassReflection($className);
    }

    /**
     * Check if the given class is instantiable.
     *
     * @param string $className
     *
     * @return bool
     */
    public static function isInstantiable($className): bool
    {
        if (self::is9orHigher()) {
            $reflectionClass = new \ReflectionClass($className);

            return (bool) $reflectionClass->isInstantiable();
        }

        return (bool) self::createReflectionClass($className)
            ->isInstantiable();
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
        if (self::is9orHigher()) {
            $reflectionClass = new \ReflectionClass($className);

            return $reflectionClass->getParentClass()->getName();
        }

        return self::createReflectionClass($className)
            ->getParentClass()
            ->getName();
    }

    /**
     * Get all properties that are tagged with the given tag.
     *
     * @param string $className
     * @param string $tag
     *
     * @return array
     */
    public static function getPropertyNamesTaggedWith($className, $tag): array
    {
        $properties = self::getPropertyNames($className);
        $return = [];
        foreach ($properties as $property) {
            $config = self::getTagConfigurationForProperty($className, $property, [$tag]);
            if (!empty($config[$tag])) {
                $return[] = $property;
            }
        }

        return $return;
    }

    /**
     * Get first class tag information.
     * The trimmed value if the tag exists and FALSE if the tag do not exists.
     *
     * @param string $className
     * @param string $tag
     *
     * @return string|bool
     */
    public static function getFirstTagValue(string $className, string $tag)
    {
        if (self::is9orHigher()) {
            $rc = new \ReflectionClass($className);
            if (strpos($rc->getDocComment(), '@' . $tag) === false) {
                return false;
            }

            $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
            $values = $reflectionService->getClassTagValues($className, $tag);
        } else {
            $classReflection = self::createReflectionClass($className);
            if (!$classReflection->isTaggedWith($tag)) {
                return false;
            }
            $values = $classReflection->getTagValues($tag);
        }

        if (\is_array($values)) {
            return \trim((string) $values[0]);
        }

        return false;
    }

    /**
     * Get the tag configuration from this method and respect multiple line and space configuration.
     *
     * @param string $className
     * @param        $methodName
     * @param array  $tagNames
     *
     * @return array
     */
    public static function getTagConfigurationForMethod($className, $methodName, array $tagNames): array
    {
        $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
        $tags = $reflectionService->getMethodTagsValues($className, $methodName);

        $configuration = [];
        foreach ($tagNames as $tagName) {
            $configuration[$tagName] = [];
            if (!\is_array($tags[$tagName])) {
                continue;
            }
            foreach ($tags[$tagName] as $c) {
                $configuration[$tagName] = \array_merge(
                    $configuration[$tagName],
                    GeneralUtility::trimExplode(' ', $c, true)
                );
            }
        }

        return $configuration;
    }

    /**
     * Get the tag configuration from this method and respect multiple line and space configuration.
     *
     * @param string $className
     * @param array  $tagNames
     *
     * @return array
     */
    public static function getTagConfigurationForClass(string $className, array $tagNames): array
    {
        $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
        $tags = $reflectionService->getClassTagsValues($className);

        $configuration = [];
        foreach ($tagNames as $tagName) {
            $configuration[$tagName] = [];
            if (!\is_array($tags[$tagName])) {
                continue;
            }
            foreach ($tags[$tagName] as $c) {
                $configuration[$tagName] = \array_merge(
                    $configuration[$tagName],
                    GeneralUtility::trimExplode(' ', $c, true)
                );
            }
        }

        return $configuration;
    }

    /**
     * Get the tag configuration from this method and respect multiple line and space configuration.
     *
     * @param string $className
     * @param string $property
     * @param array  $tagNames
     *
     * @return array
     */
    public static function getTagConfigurationForProperty(string $className, string $property, array $tagNames): array
    {
        $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
        $tags = $reflectionService->getClassSchema($className)->getProperty($property)['tags'];

        $configuration = [];
        foreach ($tagNames as $tagName) {
            $configuration[$tagName] = [];
            if (!\is_array($tags[$tagName])) {
                continue;
            }
            foreach ($tags[$tagName] as $c) {
                $configuration[$tagName] = \array_merge(
                    $configuration[$tagName],
                    GeneralUtility::trimExplode(' ', $c, true)
                );
            }
        }

        return $configuration;
    }

    /**
     * Get public method names.
     *
     * @param string $className
     *
     * @return array
     */
    public static function getPropertyNames(string $className): array
    {
        $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);

        return \array_keys($reflectionService->getClassSchema($className)->getProperties());
    }

    /**
     * @param string $className
     * @param string $tag
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function getPropertiesTaggedWith(string $className, string $tag): array
    {
        $classReflection = new \ReflectionClass($className);
        $props = $classReflection->getProperties();
        $result = [];
        foreach ($props as $prop) {
            /** @var $prop \ReflectionProperty */
            if (strpos($prop->getDocComment(), '@' . $tag) !== false) {
                $result[] = $prop->getName();
            }

        }
        return $result;
    }

    /**
     * Get public method names.
     *
     * @param string $className
     *
     * @return array
     */
    public static function getPublicMethodNames(string $className): array
    {
        $methodNames = [];

        if (self::is9orHigher()) {
            $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
            $schema = $reflectionService->getClassSchema($className);
            $methods = $schema->getMethods();
            foreach ($methods as $key => $method) {
                if ($method['public']) {
                    $methodNames[] = $key;
                }
            }

            return $methodNames;
        }

        $methods = self::createReflectionClass($className)
            ->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodNames[] = $method->getName();
        }

        return $methodNames;
    }

    /**
     * Get properties of the given class, that are als declared in the given class.
     *
     * @param string $className
     *
     * @return array
     */
    public static function getDeclaringProperties($className)
    {
        $classReflection = new \ReflectionClass($className);
        $own = \array_filter($classReflection->getProperties(), function ($property) use ($className) {
            return $property->class === $className;
        });

        return \array_map(function ($item) {
            return $item->name;
        }, $own);
    }

    /**
     * Is 9 or higher.
     *
     * @return bool
     */
    public static function is9orHigher(): bool
    {
        return VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) >= VersionNumberUtility::convertVersionNumberToInteger('9.0');
    }
}
