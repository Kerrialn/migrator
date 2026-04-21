<?php

namespace KerrialNewham\Migrator\Config;
final class Config
{
    /** @param string[] $exclude */
    public function __construct(
        private string $path,
        private array $exclude = [],
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

    public function getDatabase(): ?DatabaseConfig
    {
        return $this->database;
    }

    public function setDatabase(?DatabaseConfig $database): void
    {
        $this->database = $database;
    }

}
