<?php

namespace KerrialNewham\Migrator\Command;

use Doctrine\Common\Collections\ArrayCollection;
use KerrialNewham\ComposerJsonParser\Exception\ComposerJsonNotFoundException;
use KerrialNewham\ComposerJsonParser\Parser;
use KerrialNewham\Migrator\Config\Config;
use KerrialNewham\Migrator\DataTransferObject\Project;
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
        private readonly Project $project,
        private readonly Config  $config,
        private null|SymfonyStyle $io = null,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->project->setPath(path: $this->config->getPath());
        $this->extractProjectFiles(path: $this->config->getPath());

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
    private function fixDirectoryStructure(): void
    {
        $files = new ArrayCollection($this->project->getFiles()->toArray());
        $dirs = array_unique($files->map(fn(SplFileInfo $splFileInfo) => $splFileInfo->getPath())->toArray());
        $fileSystem = new Filesystem();

        usort($dirs, fn($a, $b) => substr_count($b, DIRECTORY_SEPARATOR) <=> substr_count($a, DIRECTORY_SEPARATOR));

        $renamedDirs = [];

        foreach ($dirs as $dirPath) {
            $originalDirPath = $dirPath;

            foreach ($renamedDirs as $old => $new) {
                if (str_starts_with($originalDirPath, $old)) {
                    $dirPath = str_replace($old, $new, $originalDirPath);
                    break;
                }
            }

            $dirName = basename($dirPath);
            $properCasedName = ucfirst($this->toCamelCase($dirName));

            if ($dirName !== $properCasedName) {
                $newDirPath = dirname($dirPath) . DIRECTORY_SEPARATOR . $properCasedName;

                if (!file_exists($newDirPath)) {
                    $fileSystem->rename($dirPath, $newDirPath, true);
                    $renamedDirs[$originalDirPath] = $newDirPath;
                }
            }
        }
    }

    private function toCamelCase(string $string): string
    {
        $i = array("-", "_");
        $string = preg_replace('/([a-z])([A-Z])/', "\\1 \\2", $string);
        $string = preg_replace('@[^a-zA-Z0-9\-_ ]+@', '', $string);
        $string = str_replace($i, ' ', $string);
        $string = str_replace(' ', '', ucwords(strtolower($string)));
        $string = strtolower(substr($string, 0, 1)) . substr($string, 1);
        return $string;
    }

    private function updateComposerJson(): void
    {
        $composer = $this->project->getComposer();
        $files = $this->project->getFiles();

        // Initialize the class map
        $classMap = [];

        foreach ($files as $file) {
            $filePath = $file->getPath();
            $namespace = $this->getNamespaceFromFilePath($file, $this->project->getPath());
            if ($namespace) {
                $classMap[$namespace] = $this->getNamespacePath($filePath);
            }
        }

        var_dump($classMap);
        exit();
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
        $finder->in($path)->name('*.php')->exclude($exclude)->files();

        foreach ($finder as $file) {
            $this->project->addFile($file);
        }
    }
}
