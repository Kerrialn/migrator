<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Replacer;

use KerrialNewham\Migrator\Service\Replacer\Contract\ReplacerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class IfStatementMissingBracketsReplacer implements ReplacerInterface
{
    public function replace(string $dir)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        /**
         * @var SplFileInfo $file
         */
        foreach ($files as $file) {
            if ($file->isFile() && pathinfo($file->getRealPath(), PATHINFO_EXTENSION) === 'php') {
                $content = file_get_contents($file->getRealPath());

                $pattern = '/\bif\s*\(([^)]*)\)\s*([^;{][^;]*?)(?=\s*[\n;])/';
                $updatedContent = preg_replace_callback($pattern, function ($matches) {
                    return 'if (' . $matches[1] . ') {' . PHP_EOL . '    ' . $matches[2] . PHP_EOL . '}';
                }, $content);

                if ($updatedContent !== $content) {
                    file_put_contents($file->getRealPath(), $updatedContent);
                }
            }
        }
    }
}
