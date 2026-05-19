<?php

declare(strict_types=1);

namespace Tools\OpenApi\Scanner;

use Symfony\Component\Finder\Finder;
use Tools\OpenApi\Model\SourceFile;

final readonly class FileScanner
{
    /**
     * @param list<string> $sourcePaths
     * @return list<SourceFile>
     */
    public function scan(array $sourcePaths): array
    {
        $finder = new Finder();
        $finder->files()->in($sourcePaths)->name('*.php')->sortByName();

        $sourceFiles = [];

        foreach ($finder as $file) {
            $sourceFiles[] = new SourceFile($file->getRealPath());
        }

        return $sourceFiles;
    }
}
