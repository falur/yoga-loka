<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Tests\Portability;

use PHPUnit\Framework\TestCase;

final class PackagePortabilityTest extends TestCase
{
    public function testProductionCodeDoesNotReferenceProjectInternals(): void
    {
        $checkedFiles = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(directory: __DIR__ . '/../../src', flags: \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $checkedFiles++;
            $contents = \file_get_contents($file->getPathname());
            self::assertIsString($contents);
            foreach (self::forbiddenNeedles() as $forbiddenNeedle) {
                self::assertStringNotContainsString($forbiddenNeedle, $contents, $file->getPathname());
            }
        }
        self::assertGreaterThan(0, $checkedFiles);
    }
    /**
     * @return list<string>
     */
    private static function forbiddenNeedles(): array
    {
        return ['namespace ' . 'Ap' . 'p' . '\\', 'use ' . 'Ap' . 'p' . '\\', 'Yoga' . 'Loka', 'Yoga ' . 'Loka', 'yoga' . '-loka', 'yoga' . '_loka', 'Tools' . '\\'];
    }
}
