<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Command;

use KerrialNewham\Migrator\Service\Replacer\IfStatementMissingBracketsReplacer;
use KerrialNewham\Migrator\Service\Replacer\ShortTagReplacer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'replace', aliases: ['fix'])]
class ReplacerCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'The action to perform (e.g., replace:php-short-tags, replace:old-if-syntax)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle(input: $input, output: $output);
        $action = $input->getArgument('action');
        $projectPath = getcwd();  // Assuming project path is current working directory; modify as needed

        var_dump($action);
        exit();

        switch ($action) {
            case 'php-short-tags':
                $io->note("Replacing PHP short tags...");
                (new ShortTagReplacer())->replace($projectPath);
                break;

            case 'old-if-syntax':
                $io->note("Fixing old if statements...");
                (new IfStatementMissingBracketsReplacer())->replace($projectPath);
                break;

            default:
                $io->error("Unknown action: {$action}");
                return Command::FAILURE;
        }

        $io->success("Action '{$action}' completed successfully.");
        return Command::SUCCESS;
    }
}
