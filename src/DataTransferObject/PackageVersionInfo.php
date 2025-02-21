<?php

namespace KerrialNewham\Migrator\DataTransferObject;

final class PackageVersionInfo
{
    private string $packageName;
    private string $currentVersion;
    private string $latestVersion;
    private int $differenceBetweenVersions;

    public function __construct(string $packageName, string $currentVersion, string $latestVersion, int $differenceBetweenVersions)
    {
        $this->packageName = $packageName;
        $this->currentVersion = $currentVersion;
        $this->latestVersion = $latestVersion;
        $this->differenceBetweenVersions = $differenceBetweenVersions;
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
