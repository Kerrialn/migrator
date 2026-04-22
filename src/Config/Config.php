<?php

namespace KerrialNewham\Migrator\Config;
final class Config
{
    /**
     * @param string[] $exclude
     * @param string[] $legacyDirs Directories containing legacy framework code being migrated away from.
     *                             Files here are analysed separately so coupling scores reflect the new
     *                             code layer independently.
     */
    public function __construct(
        private string $path,
        private array $exclude = [],
        private readonly array $legacyDirs = [],
        private ?DatabaseConfig $database = null,
    )
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /** @return string[] */
    public function getExclude(): array
    {
        return $this->exclude;
    }

    /** @param string[] $exclude */
    public function setExclude(array $exclude): void
    {
        $this->exclude = $exclude;
    }

    /** @return string[] */
    public function getLegacyDirs(): array
    {
        return $this->legacyDirs;
    }

    public function getDatabase(): ?DatabaseConfig
    {
        return $this->database;
    }

    public function setDatabase(?DatabaseConfig $database): void
    {
        $this->database = $database;
    }

}
