<?php

declare(strict_types=1);

use Doctrine\Common\Collections\ArrayCollection;
use KerrialNewham\Migrator\Command\AnalyseCommand;
use KerrialNewham\Migrator\Command\RoutesExtractorCommand;
use KerrialNewham\Migrator\Command\Psr4AutoloaderConverterCommand;
use KerrialNewham\Migrator\Command\ReplacerCommand;
use KerrialNewham\Migrator\Command\SchemeMapCommand;
use KerrialNewham\Migrator\Config\Config;
use KerrialNewham\Migrator\DataTransferObject\Project;
use Symfony\Component\Console\Application;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    require __DIR__ . '/../../../autoload.php';
}
$file = getcwd() . '/migrator.php';

if (!file_exists($file)) {
    $dist = __DIR__ . '/../migrator.php.dist';
    copy($dist, $file);
    echo "Generated migrator.php in " . getcwd() . PHP_EOL;
}

/** @var Config $config */
$config = require $file;

$application = new Application();
$application->add(new AnalyseCommand(project: new Project(), config: $config));
$application->add(new ReplacerCommand(config: $config));
$application->add(new Psr4AutoloaderConverterCommand(project: new Project(), config: $config));
$application->add(new RoutesExtractorCommand(project: new Project(), config: $config, routes: new ArrayCollection()));
$application->add(new SchemeMapCommand());

try {
    $application->run();
} catch (Exception) {
}
