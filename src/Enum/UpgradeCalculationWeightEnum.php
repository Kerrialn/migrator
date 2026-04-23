<?php

namespace KerrialNewham\Migrator\Enum;

enum UpgradeCalculationWeightEnum: int
{
    case FRAMEWORK_VERSION = 35;
    case DEPENDENCIES = 30;
    case PHP_VERSION = 20;
    case CODEBASE_SIZE = 15;

    /** @return array<string, int> */
    public static function getWeights(): array
    {
        return [
            self::FRAMEWORK_VERSION->name => self::FRAMEWORK_VERSION->value,
            self::DEPENDENCIES->name => self::DEPENDENCIES->value,
            self::PHP_VERSION->name => self::PHP_VERSION->value,
            self::CODEBASE_SIZE->name => self::CODEBASE_SIZE->value,
        ];
    }
}
