<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Enum;

enum MigrationCalculationWeightEnum: int
{
    case FRAMEWORK_COUPLING = 35;
    case DATABASE_COUPLING = 25;
    case DEPENDENCY_COMPATIBILITY = 20;
    case ARCHITECTURE = 15;
    case TEST_COVERAGE = 5;

    /** @return array<string, int> */
    public static function getWeights(): array
    {
        return [
            self::FRAMEWORK_COUPLING->name => self::FRAMEWORK_COUPLING->value,
            self::DATABASE_COUPLING->name => self::DATABASE_COUPLING->value,
            self::DEPENDENCY_COMPATIBILITY->name => self::DEPENDENCY_COMPATIBILITY->value,
            self::ARCHITECTURE->name => self::ARCHITECTURE->value,
            self::TEST_COVERAGE->name => self::TEST_COVERAGE->value,
        ];
    }
}
