<?php

namespace KerrialNewham\Migrator\Command;

use KerrialNewham\ComposerJsonParser\Exception\ComposerJsonNotFoundException;
use KerrialNewham\ComposerJsonParser\ComposerJson;
use KerrialNewham\Migrator\Config\Config;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\Helper\Strings as StringUtils;

use KerrialNewham\Migrator\Service\MultiClassSplitter\MultiClassSplitter;
use Nette\Utils\Strings;
use PhpParser\PhpVersion;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use PhpParser\ParserFactory;

#[AsCommand(name: 'psr4')]
class Psr4AutoloaderConverterCommand extends Command
{
    public function __construct(
        private readonly Project  $project,
        private readonly Config   $config,
        private null|SymfonyStyle $io = null,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(name: 'action');
        $this->addOption(name: 'dry', mode: InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->project->setPath(path: $this->config->getPath());
        $action = $input->getArgument('action');
        $isDryRun = $input->getOption('dry');

        $this->extractProjectFiles(path: $this->config->getPath(), exclude: $this->config->getExclude());

        try {
            $composer = (new ComposerJson())
                ->withComposerJsonPath(path: $this->config->getPath())
                ->withName()
                ->withRequire()
                ->getComposer();

            $this->project->setComposer(composer: $composer);

        } catch (ComposerJsonNotFoundException) {
            $this->io->info("no composer.json found.");
            $this->project->setComposer(null);
        }

        $this->io->title('Starting PSR-4 Conversion');

        // 1. Extract multiple classes into separate files
        $validActions = ['split-multi-class-files', 'fix-directory-naming', 'capitalise-directory-names', 'sync-file-and-class-names', 'dump-autoload-classmap', 'optimizing-autoload'];

        if (!in_array($action, $validActions, true)) {
            $this->io->error(sprintf('Unknown action "%s". Available actions: %s.', $action ?? '(none)', implode(', ', $validActions)));
            return Command::INVALID;
        }

        match ($action) {
            'split-multi-class-files' => $this->splitMultipleClasses($isDryRun),
            'fix-directory-naming' => $this->fixDirectoryNaming(),
            'capitalise-directory-names' => $this->capitaliseDirectories(),
            'sync-file-and-class-names' => $this->syncFileAndClassNames(),
            'dump-autoload-classmap' => $this->updateComposerJson(),
            'optimizing-autoload' => $this->optimizeNamespaceRoot(),
        };

        $this->io->success('PSR-4 conversion completed successfully!');
        return Command::SUCCESS;
    }


    private function splitMultipleClasses(bool $isDryRun = false): void
    {
        $this->io->section('Splitting multiple classes into individual files');
        $filesystem = new Filesystem();
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString("7.0"));

        $count = 0;
        foreach ($this->project->getFiles() as $file) {
            $multiClassSplitter = new MultiClassSplitter(filesystem: $filesystem, parser: $parser, file: $file);

            if ($isDryRun) {
                $count + $multiClassSplitter->countMultiClassFiles();
            }

            $multiClassSplitter->split();
        }
        $this->io->info("found {$count} multi class files");
    }

    private function syncFileAndClassNames(): void
    {
        $this->io->section('Fixing class names and file names');
        $filesystem = new Filesystem();

        foreach ($this->project->getFiles() as $file) {
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);

            if (preg_match('/\bclass\s+(\w+)/', $content, $matches)) {
                $className = $matches[1];

                if ($className !== $fileName) {
                    $newContent = preg_replace(
                        '/\bclass\s+' . preg_quote($className, '/') . '\b/',
                        'class ' . $fileName,
                        $content,
                        1
                    );

                    $filesystem->dumpFile($filePath, $newContent);
                }
            }
        }
    }


    private function capitaliseDirectories(): void
    {
        $fileSystem = new Filesystem();
        $finder = new Finder();

        // Find all directories in the scanned project
        $directories = $finder->in($this->project->getPath())
            ->exclude($this->config->getExclude())
            ->directories()
            ->depth('>= 1');

        $directories = iterator_to_array($directories);
        usort($directories, fn($a, $b): int => substr_count((string)$b->getPath(), DIRECTORY_SEPARATOR) <=> substr_count((string)$a->getPath(), DIRECTORY_SEPARATOR));

        // Iterate over each directory
        foreach ($directories as $index => $directory) {
            $currentPath = $directory->getRealPath();
            $parentDir = dirname($currentPath);
            $directoryName = basename($currentPath);

            $capitalisedDirectoryName = Strings::capitalize($directoryName);
            $newPath = $parentDir . DIRECTORY_SEPARATOR . $capitalisedDirectoryName;

            if (Strings::compare($directoryName, $capitalisedDirectoryName)) {
                // Use a temporary name if the case-only rename fails
                $tempPath = $parentDir . DIRECTORY_SEPARATOR . uniqid($directoryName . '_tmp_');

                try {
                    $this->io->info("Renaming '{$currentPath}' → '{$tempPath}'");
                    $fileSystem->rename($currentPath, $tempPath);

                    $this->io->info("Renaming '{$tempPath}' → '{$newPath}'");
                    $fileSystem->rename($tempPath, $newPath);
                } catch (\Exception $e) {
                    $this->io->error("Failed to rename '{$currentPath}' to '{$newPath}': " . $e->getMessage());
                }
            }
        }

    }

    private function fixDirectoryNaming(): void
    {
        $this->io->section('Fixing directory names');

        $fileSystem = new Filesystem();
        $finder = new Finder();

        // Find all directories in the scanned project
        $directories = $finder->in($this->project->getPath())
            ->exclude($this->config->getExclude())
            ->directories()
            ->depth('-1');

        // Sort directories by depth (deepest first) so we rename subdirectories first
        $directories = iterator_to_array($directories); // Convert Finder result to array for sorting
        usort($directories, fn($a, $b): int => substr_count((string)$b->getPath(), DIRECTORY_SEPARATOR) <=> substr_count((string)$a->getPath(), DIRECTORY_SEPARATOR));

        $renamedDirs = [];

        foreach ($directories as $dir) {
            $originalDirPath = $dir->getRealPath();

            if (!$originalDirPath) {
                $this->io->warning("Skipping invalid directory: {$dir->getPathname()}");
                continue;
            }

            // Handle already renamed directories
            foreach ($renamedDirs as $old => $new) {
                $this->io->info("Renaming directory: {$old} to {$new}");
                if (str_starts_with($originalDirPath, $old)) {
                    $originalDirPath = str_replace($old, $new, $originalDirPath);
                    break;
                }
            }

            $dirName = basename($originalDirPath);
            $properCasedName = ucfirst(StringUtils::toCamelCase($dirName));

            // Check if the directory name isn't already properly capitalized
            if ($dirName !== $properCasedName) {
                $newDirPath = dirname($originalDirPath) . DIRECTORY_SEPARATOR . $properCasedName;

                $this->io->info("Checking directory renaming: {$originalDirPath} to {$newDirPath}");

                // Check if the new directory path exists
                if (!is_dir($newDirPath)) {
                    $this->io->info("Renaming directory: {$originalDirPath} to {$newDirPath}");
                    try {
                        // Rename the directory
                        $fileSystem->rename($originalDirPath, $newDirPath, true);
                        $renamedDirs[$originalDirPath] = $newDirPath;
                    } catch (\Exception $e) {
                        $this->io->error("Failed to rename {$originalDirPath}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function updateComposerJson(): void
    {
        $this->io->section('Updating PSR4 autoload in composer.json');

        $composer = $this->project->getComposer();
        $files = $this->project->getFiles();

        // Initialize the class map
        $classMap = [];

        // Initialize the IO table
        $table = $this->io->createTable();
        $table->setHeaders(['Namespace', 'Path']);

        foreach ($files as $file) {
            $filePath = $file->getPath();
            $namespace = $this->getNamespaceFromFilePath($file, $this->project->getPath());

            if ($namespace) {
                $relativePath = $this->getRelativePath($this->project->getPath(), $filePath);
                $classMap[$namespace] = $relativePath;
                $table->addRow([$namespace, $relativePath]);
            }
        }

        $table->render();
    }

    private function getRelativePath(string $basePath, string $filePath): string
    {
        $basePath = realpath($basePath);
        $filePath = realpath($filePath);
        return str_replace($basePath . DIRECTORY_SEPARATOR, '', $filePath);
    }

    private function getNamespaceFromFilePath(SplFileInfo $file, string $basePath): ?string
    {
        $realPath = realpath($file->getPathname());
        $basePath = realpath($basePath);

        if (!$realPath || !$basePath || !str_starts_with($realPath, $basePath)) {
            return null;
        }

        $relativePath = substr($realPath, strlen($basePath));
        $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', trim(dirname($relativePath), DIRECTORY_SEPARATOR));

        return $namespace ?: null;
    }

    private function getNamespacePath(string $filePath): string
    {
        $baseDir = 'src';
        return str_replace($baseDir, '', $filePath);
    }

    private function optimizeNamespaceRoot(): void
    {
        $this->io->section('Optimizing namespace root structure in composer.json');
        $composer = $this->project->getComposer();
        // TODO: Implement logic to optimize namespace root structure
    }

    private function extractProjectFiles(string $path, array $exclude = []): void
    {
        $finder = new Finder();
        $files = $finder->in($path)->exclude($exclude)->name('*.php')->files();

        foreach ($files as $file) {
            $this->project->addFile($file);
        }
    }
}
