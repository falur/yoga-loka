<?php

declare(strict_types=1);

namespace Tools\Cqrs\Tests\Portability;

use PHPUnit\Framework\TestCase;

final class PackagePortabilityTest extends TestCase
{
    public function testProductionCodeDoesNotReferenceApplicationNamespace(): void
    {
        $checkedFiles = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                directory: __DIR__ . '/../../src',
                flags: \RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $checkedFiles++;
            $contents = \file_get_contents($file->getPathname());

            self::assertIsString(actual: $contents);
            self::assertStringNotContainsString(
                needle: 'namespace App\\',
                haystack: $contents,
                message: $file->getPathname(),
            );
            self::assertStringNotContainsString(
                needle: 'use App\\',
                haystack: $contents,
                message: $file->getPathname(),
            );
        }

        self::assertGreaterThan(minimum: 0, actual: $checkedFiles);
    }
}
