<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use Doctrine\Common\Collections\Collection;
use KerrialNewham\Migrator\Service\Migration\Analyser\Contract\MigrationAnalyserInterface;
use Symfony\Component\Finder\SplFileInfo;

final readonly class OrmCouplingAnalyser implements MigrationAnalyserInterface
{
    /** @param Collection<int, SplFileInfo> $files */
    public function __construct(private Collection $files)
    {
    }

    public function analyse(): float
    {
        if ($this->files->isEmpty()) {
            return 90.0;
        }

        $eloquentModels = 0;
        $doctrineEntities = 0;
        $rawQueryFiles = 0;
        $totalFiles = $this->files->count();

        foreach ($this->files as $file) {
            $content = $file->getContents();

            if ($this->isEloquentModel($content)) {
                $eloquentModels++;
            } elseif ($this->isDoctrineEntity($content)) {
                $doctrineEntities++;
            } elseif ($this->usesRawQueries($content)) {
                $rawQueryFiles++;
            }
        }

        if ($eloquentModels === 0 && $doctrineEntities === 0) {
            return 90.0;
        }

        if ($doctrineEntities > 0 && $eloquentModels === 0) {
            // Doctrine entities are portable (Data Mapper pattern)
            return 70.0;
        }

        // Eloquent ActiveRecord is tightly coupled — more models = harder migration
        $eloquentRatio = $eloquentModels / $totalFiles;
        $score = 50.0 - ($eloquentRatio * 40.0);

        return round(max(10.0, $score), 2);
    }

    private function isEloquentModel(string $content): bool
    {
        return str_contains($content, 'extends Model')
            && (
                str_contains($content, 'Illuminate\Database\Eloquent')
                || str_contains($content, 'use Illuminate\\')
            );
    }

    private function isDoctrineEntity(string $content): bool
    {
        return str_contains($content, 'Doctrine\ORM\Mapping')
            || str_contains($content, '#[ORM\\')
            || str_contains($content, '@ORM\\');
    }

    private function usesRawQueries(string $content): bool
    {
        return str_contains($content, '->prepare(')
            || str_contains($content, '->query(')
            || str_contains($content, 'PDO::');
    }
}
