<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Portability;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class PackagePortabilityTest extends TestCase
{
    public function testProductionCodeDoesNotReferenceProjectInternals(): void
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/../../src')->name('*.php');
        foreach ($finder as $file) {
            $contents = \file_get_contents($file->getRealPath());
            self::assertIsString($contents);
            foreach (self::forbiddenNeedles() as $forbiddenNeedle) {
                self::assertStringNotContainsString($forbiddenNeedle, $contents, $file->getRealPath());
            }
        }
    }
    /**
     * @return list<string>
     */
    private static function forbiddenNeedles(): array
    {
        return ['namespace ' . 'Ap' . 'p' . '\\', 'use ' . 'Ap' . 'p' . '\\', 'Yoga' . 'Loka', 'Yoga ' . 'Loka', 'yoga' . '-loka', 'yoga' . '_loka', 'Tools' . '\\'];
    }
}
