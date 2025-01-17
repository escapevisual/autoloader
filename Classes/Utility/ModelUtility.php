<?php

/**
 * Utility to interact with the Model.
 */
declare(strict_types=1);

namespace HDNET\Autoloader\Utility;

use Doctrine\Common\Annotations\AnnotationReader;
use HDNET\Autoloader\Annotation\DatabaseTable;
use HDNET\Autoloader\Annotation\SmartExclude;
use HDNET\Autoloader\Service\SmartObjectInformationService;
use HDNET\Autoloader\SmartObjectRegister;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;

/**
 * Utility to interact with the Model.
 */
class ModelUtility
{
    /**
     * Get the table name by either reflection or model name.
     *
     * @param $modelClassName
     *
     * @return string
     */
    public static function getTableName($modelClassName)
    {
        $reflectionName = self::getTableNameByModelReflectionAnnotation($modelClassName);

        return '' !== $reflectionName ? $reflectionName : self::getTableNameByModelName($modelClassName);
    }

    /**
     * Get the table name by reflection.
     *
     * @param string $modelClassName
     *
     * @return string
     */
    public static function getTableNameByModelReflectionAnnotation($modelClassName)
    {
        /** @var AnnotationReader $annotationReader */
        $annotationReader = GeneralUtility::makeInstance(AnnotationReader::class);
        /** @var DatabaseTable $classAnnotation */
        $classAnnotation = $annotationReader->getClassAnnotation(new \ReflectionClass($modelClassName), DatabaseTable::class);

        if (null === $classAnnotation) {
            return '';
        }

        return (string) $classAnnotation->tableName;
    }

    /**
     * Resolve the table name for the given class name
     * Original method from extbase core to create the table name.
     *
     * @param string $className
     *
     * @return string The table name
     *
     * @see DataMapFactory->resolveTableName
     */
    public static function getTableNameByModelName($className)
    {
        $className = ltrim($className, '\\');
        if (false !== mb_strpos($className, '\\')) {
            $classNameParts = explode('\\', $className);
            // Skip vendor and product name for core classes
            if (0 === mb_strpos($className, 'TYPO3\\CMS\\')) {
                $classPartsToSkip = 2;
            } else {
                $classPartsToSkip = 1;
            }
            $classNameParts = \array_slice($classNameParts, $classPartsToSkip);
            $classNameParts = explode('\\', implode('\\', $classNameParts), 4);
            $tableName = 'tx_'.str_replace('\\', '_', mb_strtolower(implode('_', $classNameParts)));
        } else {
            $tableName = mb_strtolower($className);
        }

        return $tableName;
    }

    /**
     * get the smart exclude values e.g. language, workspace,
     * enableFields from the given model.
     */
    public static function getSmartExcludesByModelName(string $name): array
    {
        /** @var AnnotationReader $annotationReader */
        $annotationReader = GeneralUtility::makeInstance(AnnotationReader::class);

        $reflectionClass = new \ReflectionClass($name);

        $smartExcludes = $annotationReader->getClassAnnotation($reflectionClass, SmartExclude::class);

        return null === $smartExcludes ? [] : $smartExcludes->excludes;
    }

    /**
     * Get the base TCA for the given Model.
     *
     * @param string $modelClassName
     *
     * @return array
     */
    public static function getTcaInformation($modelClassName)
    {
        $informationService = SmartObjectInformationService::getInstance();

        return $informationService->getTcaInformation($modelClassName);
    }

    /**
     * Get the default TCA incl. smart object fields.
     * Add missing fields to the existing TCA structure.
     *
     * @param string $extensionKey
     * @param string $tableName
     *
     * @return array
     */
    public static function getTcaOverrideInformation($extensionKey, $tableName)
    {
        $return = $GLOBALS['TCA'][$tableName] ?? [];
        $classNames = SmartObjectRegister::getRegister();
        $informationService = SmartObjectInformationService::getInstance();

        foreach ($classNames as $className) {
            if (ClassNamingUtility::getExtensionKeyByModel($className) !== $extensionKey) {
                continue;
            }
            if (self::getTableNameByModelReflectionAnnotation($className) === $tableName) {
                $additionalTca = $informationService->getCustomModelFieldTca($className);
                foreach ($additionalTca as $fieldName => $configuration) {
                    if (!isset($return['columns'][$fieldName])) {
                        $return['columns'][$fieldName] = $configuration;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Get the target model.
     *
     * @param string $modelName
     * @param array  $data
     *
     * @return object|\TYPO3\CMS\Extbase\DomainObject\AbstractEntity
     */
    public static function getModel($modelName, $data)
    {
        // Base query
        $query = ExtendedUtility::getQuery($modelName);
        $settings = $query->getQuerySettings();
        $settings->setRespectStoragePage(false);
        $settings->setRespectSysLanguage(false);
        $query->matching($query->equals('uid', $data['uid']));

        if (TYPO3_MODE === 'BE') {
            GeneralUtility::makeInstance(Session::class)->destroy();
            $settings->setIgnoreEnableFields(true);

            if (isset($data['sys_language_uid']) && (int) $data['sys_language_uid'] > 0) {
                $_GET['L'] = (int) $data['sys_language_uid'];

                if (isset($data['l18n_parent']) && $data['l18n_parent'] > 0) {
                    $settings->setLanguageOverlayMode(false);
                    $settings->setLanguageMode(null);
                    $settings->setRespectSysLanguage(true);
                    $settings->setLanguageUid((int) $data['sys_language_uid']);
                }

                return $query->execute()->getFirst();
            }
        }

        $query->matching($query->equals('uid', $data['uid']));

        return $query->execute()
            ->getFirst()
        ;
    }
}
