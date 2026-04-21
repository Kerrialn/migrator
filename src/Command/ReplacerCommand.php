<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Command;

use KerrialNewham\Migrator\Config\Config;
use KerrialNewham\Migrator\Service\Replacer\IfStatementMissingBracketsReplacer;
use KerrialNewham\Migrator\Service\Replacer\ShortTagReplacer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'replace')]
class ReplacerCommand extends Command
{
    public function __construct(
        private readonly Config  $config
    )
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'The action to perform (e.g., replace:php-short-tags, replace:old-if-syntax)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle(input: $input, output: $output);
        $action = $input->getArgument('action');

        switch ($action) {
            case 'php-short-tags':
                $io->note("Replacing PHP short tags...");
                (new ShortTagReplacer())->replace(dir: $this->config->getPath());
                break;

            case 'old-if-syntax':
                $io->note("Fixing old if statements...");
                $replacer = new IfStatementMissingBracketsReplacer();
                foreach ((new Finder())->in($this->config->getPath())->files()->name('*.php') as $file) {
                    $replacer->replace($file);
                }
                break;

            default:
                $io->error("Unknown action: {$action}");
                return Command::FAILURE;
        }

        $io->success("Action '{$action}' completed successfully.");
        return Command::SUCCESS;
    }
}
