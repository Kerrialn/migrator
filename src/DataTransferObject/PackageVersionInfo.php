<?php

namespace KerrialNewham\Migrator\DataTransferObject;

final readonly class PackageVersionInfo
{
    public function __construct(private string $packageName, private string $currentVersion, private string $latestVersion, private int $differenceBetweenVersions)
    {
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function isOutdated(): bool
    {
        return version_compare($this->currentVersion, $this->latestVersion, '<');
    }

    public function getDifferenceBetweenVersions(): int
    {
        return $this->differenceBetweenVersions;
    }
}
