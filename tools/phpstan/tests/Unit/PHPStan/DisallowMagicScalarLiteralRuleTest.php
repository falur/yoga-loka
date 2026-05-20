<?php

declare(strict_types=1);

namespace Tools\PHPStan\Tests\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\Rules\DisallowMagicScalarLiteralRule;

/**
 * @extends RuleTestCase<DisallowMagicScalarLiteralRule>
 */
final class DisallowMagicScalarLiteralRuleTest extends RuleTestCase
{
    public function testAllowsNamedConstantsEnumsAttributesAndMessages(): void
    {
        $file = __DIR__ . '/Fixtures/MagicScalarLiteralsAllowed.php';

        require_once $file;

        $this->analyse([$file], []);
    }

    public function testRejectsRuntimeScalarLiterals(): void
    {
        $file = __DIR__ . '/Fixtures/MagicScalarLiteralsForbidden.php';
        $errors = $this->gatherAnalyserErrors([$file]);

        self::assertSame([
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
            'project.magicScalarLiteral',
        ], \array_map(static fn($error): ?string => $error->getIdentifier(), $errors));

        $this->analyse([$file], [
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 11],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 12],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 13],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 15],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 16],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 19],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 19],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 21],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 21],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 21],
            ['Magic scalar literal is forbidden in runtime code. Move it to an enum, class constant or value object.', 51],
        ]);
    }

    protected function getRule(): Rule
    {
        return new DisallowMagicScalarLiteralRule(analysedPathFragments: ['/Fixtures/']);
    }
}
