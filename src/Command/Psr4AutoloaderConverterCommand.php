<?php

namespace KerrialNewham\Migrator\Command;

use KerrialNewham\ComposerJsonParser\Exception\ComposerJsonNotFoundException;
use KerrialNewham\ComposerJsonParser\Parser;
use KerrialNewham\Migrator\Config\Config;
use KerrialNewham\Migrator\DataTransferObject\Project;
use KerrialNewham\Migrator\Helper\Strings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

#[AsCommand(name: 'convert-to-psr4', aliases: ['psr4'])]
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->project->setPath(path: $this->config->getPath());
        $this->extractProjectFiles(path: $this->config->getPath(), exclude: $this->config->getExclude());

        try {
            $composer = (new Parser())
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
        $this->io->section('Step 1: Splitting multiple classes into individual files');
        $this->splitMultipleClasses();

        // 2. Fix class short names or file name / class name mismatch
        $this->io->section('Step 2: Fixing class names and file names');
        $this->fixClassNames();

        // 3. Ensure directory names are capitalized
        $this->io->section('Step 3: Capitalizing directory names');
        $this->fixDirectoryStructure();
        $this->capitaliseDirectories();

        // 4. Load all files as a PSR4 autoload entry in composer.json (no matter how many)
        $this->io->section('Step 4: Add PHP files to PSR4 autoload in composer.json');
        $this->updateComposerJson();

        // 5. Optimize namespace roots in composer.json
        $this->io->section('Step 5: Optimizing namespace root structure');
        $this->optimizeNamespaceRoot(project: $this->project);

        $this->io->success('PSR-4 conversion completed successfully!');
        return Command::SUCCESS;
    }


    private function splitMultipleClasses(): void
    {
        $filesystem = new Filesystem();

        foreach ($this->project->getFiles() as $file) {
            $filePath = $file->getPathname();
            $content = $file->getContents();

            preg_match_all('/\bclass\s+(\w+)/', $content, $matches);
            $classes = $matches[1] ?? [];

            if (count($classes) <= 1) {
                continue;
            }

            foreach ($classes as $className) {
                $pattern = '/(class\s+' . preg_quote($className, '/') . '\s+\{.*?\})/s';
                preg_match($pattern, $content, $classMatch);

                if (!isset($classMatch[1])) {
                    continue;
                }

                $classContent = "<?php\n\n" . $classMatch[1] . "\n";
                $newFilePath = dirname($filePath) . DIRECTORY_SEPARATOR . $className . '.php';

                if (!$filesystem->exists($newFilePath)) {
                    $filesystem->dumpFile($newFilePath, $classContent);
                }
            }

            $filesystem->remove($filePath);
        }
    }

    private function fixClassNames(): void
    {
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
        usort($directories, fn($a, $b): int => substr_count((string) $b->getPath(), DIRECTORY_SEPARATOR) <=> substr_count((string) $a->getPath(), DIRECTORY_SEPARATOR));

        // Iterate over each directory
        foreach ($directories as $directory) {
            $currentPath = $directory->getRealPath();
            $parentDir = dirname($currentPath);
            $directoryName = basename($currentPath);
            $capitalisedDirectoryName = ucfirst($directoryName);

            // Check if the directory name is already capitalised
            if ($directoryName !== $capitalisedDirectoryName) {
                $newPath = $parentDir . DIRECTORY_SEPARATOR . $capitalisedDirectoryName;

                // Check if the target directory already exists
                if ($fileSystem->exists($newPath)) {
                    $this->io->warning("Skipping: Target directory '{$newPath}' already exists.");
                } else {
                    $this->io->info("Capitalising: {$directoryName}");

                    // Rename the directory
                    try {
                        $fileSystem->rename($currentPath, $newPath);
                    } catch (\Exception $e) {
                        $this->io->error("Failed to rename '{$currentPath}' to '{$newPath}': " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function fixDirectoryStructure(): void
    {
        $fileSystem = new Filesystem();
        $finder = new Finder();

        // Find all directories in the scanned project
        $directories = $finder->in($this->project->getPath())
            ->exclude($this->config->getExclude())
            ->directories()
            ->depth('>= 1'); // Ensure it scans subdirectories as well

        // Sort directories by depth (deepest first) so we rename subdirectories first
        $directories = iterator_to_array($directories); // Convert Finder result to array for sorting
        usort($directories, fn($a, $b): int => substr_count((string) $b->getPath(), DIRECTORY_SEPARATOR) <=> substr_count((string) $a->getPath(), DIRECTORY_SEPARATOR));

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
            $properCasedName = ucfirst(Strings::toCamelCase($dirName));

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

    private function optimizeNamespaceRoot(Project $project): void
    {
        $composer = $project->getComposer();
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
