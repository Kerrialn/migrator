<?php

declare(strict_types=1);

use KerrialNewham\Migrator\Command\AnalyseCommand;
use KerrialNewham\Migrator\Command\ReplacerCommand;
use KerrialNewham\Migrator\DataTransferObject\Project;
use Symfony\Component\Console\Application;

require __DIR__ . '/../../../autoload.php';

$application = new Application();
$application->add(new AnalyseCommand(project: new Project()));
$application->add(new ReplacerCommand());
try {
    $application->run();
} catch (Exception) {
}
