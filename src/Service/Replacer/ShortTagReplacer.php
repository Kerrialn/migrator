<?php

declare(strict_types=1);

namespace KerrialNewham\Migrator\Service\Replacer;

use KerrialNewham\Migrator\Service\Replacer\Contract\ReplacerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ShortTagReplacer implements ReplacerInterface
{

    public function replace(string $dir)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->isFile() && pathinfo($file->getRealPath(), PATHINFO_EXTENSION) === 'php') {
                $content = file_get_contents($file->getRealPath());

                if (preg_match('/^<\?(php|xml)/', $content)) {
                    continue;
                }

                $updatedContent = preg_replace('/<\?(?!php|xml)/', '<?php ', $content);

                if ($updatedContent !== $content) {
                    file_put_contents($file->getRealPath(), $updatedContent);
                }
            }
        }
    }

}
