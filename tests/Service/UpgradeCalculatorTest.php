<?php


declare(strict_types=1);

namespace Test\Service;

use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\DataTransferObject\Upgrade;
use KerrialNewham\Migrator\Service\Calculator\UpgradeCalculator;
use PHPUnit\Framework\TestCase;

class UpgradeCalculatorTest extends TestCase
{
    public function testCalculateTotalScore(): void
    {
        $project = new Project();
        $upgrade = new Upgrade();
        $project->setUpgrade($upgrade);
        $upgrade->setFrameworkVersionUpgradabilityScore(80);
        $upgrade->setDependenciesUpgradabilityScore(60);
        $upgrade->setPhpVersionUpgradabilityScore(90);
        $upgrade->setCodebaseSizeUpgradabilityScore(70);

        $upgradeCalculator = new UpgradeCalculator();
        $result = $upgradeCalculator->calculateTotalScore(project: $project);
        $expectedScore = round( 63.5 , 2);

        $this->assertEquals($expectedScore, $result);
    }
}
