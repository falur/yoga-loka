<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Portability;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class PackagePortabilityTest extends TestCase
{
    public function testProductionCodeDoesNotReferenceApplicationNamespace(): void
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/../../src')->name('*.php');

        foreach ($finder as $file) {
            self::assertStringNotContainsString('App\\', (string) \file_get_contents($file->getRealPath()));
        }
    }
}
