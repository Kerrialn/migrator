<?php

namespace KerrialNewham\Migrator\Config;
final class Config
{
    public function __construct(
        private string $path,
        private array $exclude = [],
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

    public function getExclude(): array
    {
        return $this->exclude;
    }

    public function setExclude(array $exclude): void
    {
        $this->exclude = $exclude;
    }

}
