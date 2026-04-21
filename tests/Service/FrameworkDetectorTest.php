<?php

declare(strict_types=1);

namespace Test\Service;

use KerrialNewham\ComposerJsonParser\Enum\PackageTypeEnum;
use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\ComposerJsonParser\Model\Package;
use KerrialNewham\ComposerJsonParser\Model\PackageVersion;
use KerrialNewham\Migrator\Enum\FrameworkTypeEnum;
use KerrialNewham\Migrator\Service\FrameworkDetector;
use PHPUnit\Framework\TestCase;

class FrameworkDetectorTest extends TestCase
{
    private FrameworkDetector $detector;
    private string $projectPath;

    protected function setUp(): void
    {
        $this->detector = new FrameworkDetector();
        $this->projectPath = sys_get_temp_dir() . '/migrator_test_' . uniqid();
        mkdir($this->projectPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectPath);
    }

    public function testDetectsSymfonyFromPrimaryPackage(): void
    {
        $composer = $this->buildComposer(['symfony/framework-bundle' => '^7.0']);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(1, $frameworks);
        $this->assertSame(FrameworkTypeEnum::SYMFONY, $frameworks->first()->getFrameworkTypeEnum());
        $this->assertSame(50.0, $frameworks->first()->getCertainty());
    }

    public function testDetectsSymfonyFromSubPackagesOnlyWithoutPrimary(): void
    {
        $composer = $this->buildComposer([
            'symfony/console' => '^7.0',
            'symfony/http-kernel' => '^7.0',
        ]);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(1, $frameworks);
        $this->assertSame(FrameworkTypeEnum::SYMFONY, $frameworks->first()->getFrameworkTypeEnum());
    }

    public function testDetectsLaravelFromPrimaryPackage(): void
    {
        $composer = $this->buildComposer(['laravel/framework' => '^11.0']);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(1, $frameworks);
        $this->assertSame(FrameworkTypeEnum::LARAVEL, $frameworks->first()->getFrameworkTypeEnum());
    }

    public function testDetectsTempestFromPrimaryPackage(): void
    {
        $composer = $this->buildComposer(['tempest/framework' => '^1.0']);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(1, $frameworks);
        $this->assertSame(FrameworkTypeEnum::TEMPEST, $frameworks->first()->getFrameworkTypeEnum());
    }

    public function testDetectsFromRequireDev(): void
    {
        $composer = $this->buildComposer([], ['symfony/framework-bundle' => '^7.0']);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(1, $frameworks);
        $this->assertSame(FrameworkTypeEnum::SYMFONY, $frameworks->first()->getFrameworkTypeEnum());
    }

    public function testReturnsEmptyWhenNothingMatches(): void
    {
        $composer = $this->buildComposer(['some/unrelated-package' => '^1.0']);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(0, $frameworks);
    }

    public function testReturnsEmptyWhenComposerIsNull(): void
    {
        $frameworks = $this->detector->detect($this->projectPath, null);

        $this->assertCount(0, $frameworks);
    }

    public function testFiltersOutLowCertaintyMatches(): void
    {
        // symfony/http-kernel alone contributes weight 25, below threshold of 40
        $composer = $this->buildComposer(['symfony/http-kernel' => '^7.0']);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(0, $frameworks);
    }

    public function testHigherCertaintyWithMoreSubPackages(): void
    {
        $composerPartial = $this->buildComposer(['symfony/framework-bundle' => '^7.0']);
        $composerFull = $this->buildComposer([
            'symfony/framework-bundle' => '^7.0',
            'symfony/console' => '^7.0',
            'symfony/http-kernel' => '^7.0',
        ]);

        $partial = $this->detector->detect($this->projectPath, $composerPartial)->first();
        $full = $this->detector->detect($this->projectPath, $composerFull)->first();

        $this->assertGreaterThan($partial->getCertainty(), $full->getCertainty());
    }

    public function testUnstableVersionConstraintReducesCertainty(): void
    {
        // Stable: framework-bundle + console = 50 + 40 = 90
        $stableComposer = $this->buildComposer([
            'symfony/framework-bundle' => '^7.0',
            'symfony/console' => '^7.0',
        ]);
        // dev-main: same packages but 0.5 multiplier = 25 + 20 = 45 (still above threshold)
        $devComposer = $this->buildComposer([
            'symfony/framework-bundle' => 'dev-main',
            'symfony/console' => 'dev-main',
        ]);

        $stable = $this->detector->detect($this->projectPath, $stableComposer)->first();
        $dev = $this->detector->detect($this->projectPath, $devComposer)->first();

        $this->assertNotFalse($dev, 'dev-main packages above threshold should still be detected');
        $this->assertGreaterThan($dev->getCertainty(), $stable->getCertainty());
    }

    public function testSingleDevMainPackageBelowThreshold(): void
    {
        // dev-main on primary only = 50 * 0.5 = 25, below threshold of 40
        $composer = $this->buildComposer(['symfony/framework-bundle' => 'dev-main']);

        $frameworks = $this->detector->detect($this->projectPath, $composer);

        $this->assertCount(0, $frameworks);
    }

    public function testFilesystemFingerprintBoostsCertainty(): void
    {
        $composer = $this->buildComposer(['symfony/framework-bundle' => '^7.0']);

        $baseFrameworks = $this->detector->detect($this->projectPath, $composer);
        $baseCertainty = $baseFrameworks->first()->getCertainty();

        mkdir($this->projectPath . '/bin');
        file_put_contents($this->projectPath . '/bin/console', '#!/usr/bin/env php');
        file_put_contents($this->projectPath . '/symfony.lock', '{}');

        $boostedFrameworks = $this->detector->detect($this->projectPath, $composer);
        $boostedCertainty = $boostedFrameworks->first()->getCertainty();

        $this->assertGreaterThan($baseCertainty, $boostedCertainty);
    }

    public function testFilesystemOnlyDetectionWithNoComposer(): void
    {
        mkdir($this->projectPath . '/bin');
        file_put_contents($this->projectPath . '/bin/console', '#!/usr/bin/env php');
        file_put_contents($this->projectPath . '/symfony.lock', '{}');
        file_put_contents($this->projectPath . '/bin/console', '#!/usr/bin/env php');

        $frameworks = $this->detector->detect($this->projectPath, null);

        $this->assertCount(1, $frameworks);
        $this->assertSame(FrameworkTypeEnum::SYMFONY, $frameworks->first()->getFrameworkTypeEnum());
        $this->assertNull($frameworks->first()->getPackageVersion());
    }

    public function testFilesystemOnlyBelowThresholdNotDetected(): void
    {
        // symfony.lock alone = 1 match = certainty 30, below threshold 40
        file_put_contents($this->projectPath . '/symfony.lock', '{}');

        $frameworks = $this->detector->detect($this->projectPath, null);

        $this->assertCount(0, $frameworks);
    }

    /**
     * @param array<string, string> $require
     * @param array<string, string> $requireDev
     */
    private function buildComposer(array $require, array $requireDev = []): Composer
    {
        $composer = new Composer();

        foreach ($require as $name => $version) {
            $composer->addRequire(new Package(
                name: $name,
                type: PackageTypeEnum::REQUIRE,
                packageVersion: new PackageVersion(
                    versionString: $version,
                    version: (float) preg_replace('/[^0-9.]/', '', $version),
                    versionConstraints: $version
                )
            ));
        }

        foreach ($requireDev as $name => $version) {
            $composer->addDevRequire(new Package(
                name: $name,
                type: PackageTypeEnum::DEVELOPMENT,
                packageVersion: new PackageVersion(
                    versionString: $version,
                    version: (float) preg_replace('/[^0-9.]/', '', $version),
                    versionConstraints: $version
                )
            ));
        }

        return $composer;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($path);
    }
}
