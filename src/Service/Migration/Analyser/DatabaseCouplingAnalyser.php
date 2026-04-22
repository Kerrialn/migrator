<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Migration\Analyser;

use Doctrine\Common\Collections\Collection;
use KerrialNewham\Migrator\Service\Migration\Analyser\Contract\MigrationAnalyserInterface;
use Symfony\Component\Finder\SplFileInfo;

final class DatabaseCouplingAnalyser implements MigrationAnalyserInterface
{
    private bool $databaseLayerDetected = false;

    /** @param Collection<int, SplFileInfo> $files */
    public function __construct(private readonly Collection $files)
    {
    }

    public function isDatabaseLayerDetected(): bool
    {
        return $this->databaseLayerDetected;
    }

    public function analyse(): float
    {
        if ($this->files->isEmpty()) {
            return 100.0;
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

        if ($eloquentModels === 0 && $doctrineEntities === 0 && $rawQueryFiles === 0) {
            return 100.0;
        }

        $this->databaseLayerDetected = true;

        // Doctrine Data Mapper is the most portable ORM pattern
        if ($doctrineEntities > 0 && $eloquentModels === 0 && $rawQueryFiles === 0) {
            return 70.0;
        }

        // Raw queries scattered throughout the codebase — no abstraction at all
        if ($rawQueryFiles > 0 && $eloquentModels === 0 && $doctrineEntities === 0) {
            $rawRatio = $rawQueryFiles / $totalFiles;
            return round(max(20.0, 80.0 - ($rawRatio * 60.0)), 2);
        }

        // Eloquent ActiveRecord couples business logic to the DB schema
        $eloquentRatio = $eloquentModels / $totalFiles;
        $rawRatio = $rawQueryFiles / $totalFiles;
        $score = 50.0 - ($eloquentRatio * 35.0) - ($rawRatio * 15.0);

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
            || str_contains($content, 'PDO::')
            || str_contains($content, '$wpdb->')
            || str_contains($content, 'mysqli_query(');
    }
}
