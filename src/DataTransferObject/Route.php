<?php

namespace KerrialNewham\Migrator\DataTransferObject;

final readonly class Route
{


    public function __construct(
        private string $route,
        private string $path
    )
    {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getPath(): string
    {
        return $this->path;
    }

}
