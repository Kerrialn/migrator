<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Enum;

enum MigrationCalculationWeightEnum
{
    case FRAMEWORK_COUPLING;
    case DATABASE_COUPLING;
    case DEPENDENCY_COMPATIBILITY;
    case ARCHITECTURE;
    case TEST_COVERAGE;
    case CODEBASE_SIZE;
    case TEMPLATING;

    /** @return array<string, int> */
    public static function getWeights(bool $includeTemplating = false): array
    {
        if ($includeTemplating) {
            return [
                self::FRAMEWORK_COUPLING->name     => 30,
                self::DATABASE_COUPLING->name       => 20,
                self::DEPENDENCY_COMPATIBILITY->name => 10,
                self::ARCHITECTURE->name            => 20,
                self::TEST_COVERAGE->name           => 5,
                self::CODEBASE_SIZE->name           => 5,
                self::TEMPLATING->name              => 10,
            ];
        }

        return [
            self::FRAMEWORK_COUPLING->name     => 30,
            self::DATABASE_COUPLING->name       => 20,
            self::DEPENDENCY_COMPATIBILITY->name => 10,
            self::ARCHITECTURE->name            => 25,
            self::TEST_COVERAGE->name           => 5,
            self::CODEBASE_SIZE->name           => 10,
        ];
    }
}
