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

    public function weight(): int
    {
        return match ($this) {
            self::FRAMEWORK_COUPLING => 30,
            self::DATABASE_COUPLING => 20,
            self::DEPENDENCY_COMPATIBILITY => 10,
            self::ARCHITECTURE => 25,
            self::TEST_COVERAGE => 5,
            self::CODEBASE_SIZE => 10,
        };
    }

    /** @return array<string, int> */
    public static function getWeights(): array
    {
        return [
            self::FRAMEWORK_COUPLING->name => self::FRAMEWORK_COUPLING->weight(),
            self::DATABASE_COUPLING->name => self::DATABASE_COUPLING->weight(),
            self::DEPENDENCY_COMPATIBILITY->name => self::DEPENDENCY_COMPATIBILITY->weight(),
            self::ARCHITECTURE->name => self::ARCHITECTURE->weight(),
            self::TEST_COVERAGE->name => self::TEST_COVERAGE->weight(),
            self::CODEBASE_SIZE->name => self::CODEBASE_SIZE->weight(),
        ];
    }
}
