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
        $this->addOption(name: 'split-multi-class-files', mode: InputOption::VALUE_NONE, description: 'Extract multiple classes into separate files');
        $this->addOption(name: 'fix-directory-naming', mode: InputOption::VALUE_NONE, description: 'Rename directories to PascalCase');
        $this->addOption(name: 'capitalise-directory-names', mode: InputOption::VALUE_NONE, description: 'Capitalise directory names');
        $this->addOption(name: 'sync-file-and-class-names', mode: InputOption::VALUE_NONE, description: 'Rename class names to match their file names');
        $this->addOption(name: 'dump-autoload-classmap', mode: InputOption::VALUE_NONE, description: 'Dump PSR-4 autoload classmap into composer.json');
        $this->addOption(name: 'optimizing-autoload', mode: InputOption::VALUE_NONE, description: 'Optimize namespace root structure in composer.json');
        $this->addOption(name: 'dry', mode: InputOption::VALUE_NONE, description: 'Dry run — preview changes without applying them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->project->setPath(path: $this->config->getPath());
        $isDryRun = (bool) $input->getOption('dry');

        $flags = ['split-multi-class-files', 'fix-directory-naming', 'capitalise-directory-names', 'sync-file-and-class-names', 'dump-autoload-classmap', 'optimizing-autoload'];
        $active = array_filter($flags, fn(string $flag): bool => (bool) $input->getOption($flag));

        if (empty($active)) {
            $this->io->error('No action specified. Use one or more flags: --' . implode(', --', $flags));
            return Command::INVALID;
        }

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

        if ($input->getOption('split-multi-class-files')) {
            $this->splitMultipleClasses($isDryRun);
        }

        if ($input->getOption('fix-directory-naming')) {
            $this->fixDirectoryNaming();
        }

        if ($input->getOption('capitalise-directory-names')) {
            $this->capitaliseDirectories();
        }

        if ($input->getOption('sync-file-and-class-names')) {
            $this->syncFileAndClassNames();
        }

        if ($input->getOption('dump-autoload-classmap')) {
            $this->updateComposerJson();
        }

        if ($input->getOption('optimizing-autoload')) {
            $this->optimizeNamespaceRoot();
        }

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

            try {
                if ($isDryRun) {
                    $count += $multiClassSplitter->countMultiClassFiles();
                    continue;
                }

                $multiClassSplitter->split();
            } catch (\RuntimeException $e) {
                $this->io->warning($e->getMessage());
            }
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

        // Build the set of directories that are part of the PHP file hierarchy
        $basePath = rtrim((string) realpath($this->project->getPath()), DIRECTORY_SEPARATOR);
        $phpFileDirs = [];
        foreach ($this->project->getFiles() as $file) {
            $dir = dirname((string) ($file->getRealPath() ?: $file->getPathname()));
            while (strlen($dir) > strlen($basePath) && str_starts_with($dir, $basePath)) {
                $phpFileDirs[$dir] = true;
                $dir = dirname($dir);
            }
        }

        $finder = new Finder();
        $directories = $finder->in($this->project->getPath())
            ->exclude($this->config->getExclude())
            ->directories();

        $directories = iterator_to_array($directories);
        usort($directories, fn($a, $b): int => substr_count((string)$b->getPath(), DIRECTORY_SEPARATOR) <=> substr_count((string)$a->getPath(), DIRECTORY_SEPARATOR));

        $renamedDirs = [];

        foreach ($directories as $dir) {
            $originalDirPath = $dir->getRealPath();

            if (!$originalDirPath) {
                $this->io->warning("Skipping invalid directory: {$dir->getPathname()}");
                continue;
            }

            // Adjust path if a parent directory was already renamed
            foreach ($renamedDirs as $old => $new) {
                if (str_starts_with($originalDirPath, $old . DIRECTORY_SEPARATOR)) {
                    $originalDirPath = $new . substr($originalDirPath, strlen($old));
                    break;
                }
            }

            if (!isset($phpFileDirs[$originalDirPath])) {
                continue;
            }

            $dirName = basename($originalDirPath);
            $properCasedName = ucfirst(StringUtils::toCamelCase($dirName));

            if ($dirName === $properCasedName) {
                continue;
            }

            $newDirPath = dirname($originalDirPath) . DIRECTORY_SEPARATOR . $properCasedName;

            try {
                if (strcasecmp($dirName, $properCasedName) === 0) {
                    // Case-only rename: use a temp path to work around case-insensitive filesystems
                    $tempPath = dirname($originalDirPath) . DIRECTORY_SEPARATOR . uniqid($dirName . '_tmp_');
                    $fileSystem->rename($originalDirPath, $tempPath);
                    $fileSystem->rename($tempPath, $newDirPath);
                } elseif (!is_dir($newDirPath)) {
                    $fileSystem->rename($originalDirPath, $newDirPath, true);
                } else {
                    $this->io->warning("Cannot rename '{$originalDirPath}': target '{$newDirPath}' already exists");
                    continue;
                }

                $this->io->info("Renamed '{$originalDirPath}' → '{$newDirPath}'");
                $renamedDirs[$originalDirPath] = $newDirPath;

                // Update phpFileDirs to reflect the rename so descendant dirs still match
                $updated = [];
                foreach ($phpFileDirs as $phpDir => $_) {
                    if (str_starts_with($phpDir, $originalDirPath)) {
                        $updated[$newDirPath . substr($phpDir, strlen($originalDirPath))] = true;
                    } else {
                        $updated[$phpDir] = true;
                    }
                }
                $phpFileDirs = $updated;
            } catch (\Exception $e) {
                $this->io->error("Failed to rename '{$originalDirPath}': " . $e->getMessage());
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

    private function optimizeNamespaceRoot(): void
    {
        $this->io->section('Optimizing namespace root structure in composer.json');
        $composer = $this->project->getComposer();
        // TODO: Implement logic to optimize namespace root structure
    }

    /** @param string[] $exclude */
    private function extractProjectFiles(string $path, array $exclude = []): void
    {
        $finder = new Finder();
        $files = $finder->in($path)->exclude($exclude)->name('*.php')->files();

        foreach ($files as $file) {
            $this->project->addFile($file);
        }
    }
}
