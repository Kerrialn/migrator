<?php

namespace KerrialNewham\Migrator\Command;

use Doctrine\DBAL\DriverManager;
use Fhaculty\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use KerrialNewham\Migrator\Config\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'map-scheme', aliases: ['ms'])]
class SchemeMapCommand extends Command
{
    public function __construct(private readonly Config $config)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting mapping');

        $database = $this->config->getDatabase();
        if ($database === null) {
            $io->error('No database configuration found in migrator.php. Add a DatabaseConfig to use map-scheme.');
            return Command::FAILURE;
        }

        $conn = DriverManager::getConnection($database->toConnectionParams());

        $schemaManager = $conn->createSchemaManager();
        $tables = $schemaManager->listTables();
        $graph = new Graph();

        $count = count($tables);

        foreach ($tables as $table) {
            $io->info("Adding table: {$table->getName()}");

            // Build a Graphviz RECORD label
            $label = "{ {$table->getName()} | ";

            $columnLabels = [];
            foreach ($table->getColumns() as $column) {
                $columnType = $column->getType()->getBindingType()->name;
                $columnLabels[] = "{$column->getName()} : {$columnType}";
            }

            // Append column definitions
            $label .= implode(" | ", $columnLabels);
            $label .= " }"; // Close the record structure

            // Create the vertex for the current table
            /** @phpstan-ignore argument.type */
            $node = $graph->createVertex($table->getName());
            $node->setAttribute('graphviz.shape', 'record'); // Use "record" shape
            $node->setAttribute('graphviz.label', $label); // Set correct label format

            // Add foreign keys as edges
            foreach ($table->getForeignKeys() as $fk) {
                $foreignTableName = $fk->getForeignTableName();
                $io->info("Adding foreign key: {$fk->getName()}");

                // Check if the foreign table exists in the graph
                if ($graph->hasVertex($foreignTableName)) {
                    // Create an edge between the current table and the foreign table
                    $node->createEdgeTo($graph->getVertex($foreignTableName));
                } else {
                    // Log an error if the foreign table doesn't exist
                    $io->error("Foreign table '{$foreignTableName}' not found in the graph.");
                }
            }
        }

        // Create the GraphViz object and display the graph
        $viz = new GraphViz();
        $viz->setFormat('svg');
        $viz->display($graph);

        $io->info("found {$count} tables");
        $io->success('Mapping complete');
        return Command::SUCCESS;
    }
}
