<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Scanner;

use Symfony\Component\Finder\Finder;
use GianTiaga\SpiralOpenApi\Model\SourceFile;

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
