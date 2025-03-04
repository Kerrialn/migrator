<?php

namespace KerrialNewham\Migrator\Command;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use KerrialNewham\Migrator\Config\Config;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\DataTransferObject\Route;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'routes', aliases: ['routes'])]
class RoutesExtractorCommand extends Command
{
    /**
     * @param Project $project
     * @param Config $config
     * @param ArrayCollection<int, Route> $routes
     * @param SymfonyStyle|null $io
     */
    public function __construct(
        private readonly Project  $project,
        private readonly Config   $config,
        private readonly ArrayCollection   $routes,
        private null|SymfonyStyle $io = null
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(name: 'dump', shortcut: 'd', mode: InputOption::VALUE_NONE, description: 'output routes.php file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $isDump = $input->getOption(name: 'dump');
        $this->project->setPath(path: $this->config->getPath());

        $this->io->title('Starting extraction');
        $this->extractRouteFiles(path: $this->config->getPath(), exclude: $this->config->getExclude());
        $files = $this->project->getFiles();

        foreach ($files as $file) {
            $this->extractRoutesFromFile(file: $file);
        }

        if ($isDump) {
            $this->dumpRoutesFile();
            $this->io->info("Found {$this->routes->count()} routes");
            $this->io->success("route.php created successfully");
            return Command::SUCCESS;
        }

        $table = $this->io->createTable();
        $table->setHeaders(['route', 'file']);

        foreach ($this->routes as $route) {
            $table->addRow([$route->getRoute(), $route->getPath()]);
        }
        $table->addRow(['------------', '-----------']);
        $table->addRow(['route count', number_format($this->routes->count())]);

        $table->render();
        return Command::SUCCESS;
    }

    private function extractRouteFiles(string $path, array $exclude = []): void
    {
        $finder = new Finder();
        $files = $finder->in($path)->exclude($exclude)->name('routes.php')->files();

        foreach ($files as $file) {
            $this->project->addFile($file);
        }
    }

    private function extractRoutesFromFile($file): void
    {
        $content = $file->getContents();
        preg_match_all('/\$route\[\'(.*?)\'\] = \'(.*?)\'/', (string) $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $this->routes->add(
                new Route(route: $match[1], path: $match[2])
            );
        }
    }

    private function dumpRoutesFile(): void
    {
        $filesystem = new Filesystem();
        $routes = $this->routes->toArray();

        $newRoutes = [];
        foreach ($routes as $route) {
            $newRoutes[$route->getRoute()] = $route->getPath();
        }

        $content = "<?php\n\nreturn " . var_export($newRoutes, true) . ";\n";
        $now = new DateTimeImmutable();
        $filesystem->dumpFile("./public/routes-{$now->format(DateTimeImmutable::RFC3339)}.php", $content);
    }


}
