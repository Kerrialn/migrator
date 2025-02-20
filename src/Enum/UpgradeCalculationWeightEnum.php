<?php

namespace KerrialNewham\Migrator\Enum;

enum UpgradeCalculationWeightEnum: int
{
    case FRAMEWORK_VERSION = 25;
    case CUSTOM_CODE = 20;
    case DEPENDENCIES = 15;
    case CODEBASE_SIZE = 12;
    case LEGACY_PATTERNS = 10;
    case DATABASE_ORM = 8;
    case PHP_VERSION = 7;
    case TESTING_CI = 3;

    public static function getWeights() : array
    {
        return [
            self::FRAMEWORK_VERSION->name => self::FRAMEWORK_VERSION->value,
            self::CUSTOM_CODE->name => self::CUSTOM_CODE->value,
            self::DEPENDENCIES->name => self::DEPENDENCIES->value,
            self::CODEBASE_SIZE->name => self::CODEBASE_SIZE->value,
            self::LEGACY_PATTERNS->name => self::LEGACY_PATTERNS->value,
            self::TESTING_CI->name => self::TESTING_CI->value,
        ];
    }
}
