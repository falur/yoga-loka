<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\PHPStan;

use GianTiaga\SpiralOpenApi\PHPStan\Rules\OpenApiAttributeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
/**
 * @extends RuleTestCase<OpenApiAttributeRule>
 */
final class OpenApiAttributeRuleTest extends RuleTestCase
{
    public function testAllowsDocumentedIdCharacters(): void
    {
        $file = __DIR__ . '/Fixtures/OpenApiAttributeValid.fixture';
        require_once $file;
        $this->analyse(files: [$file], expectedErrors: []);
    }
    public function testRejectsEmptyInvalidAndDuplicateIds(): void
    {
        $file = __DIR__ . '/Fixtures/OpenApiAttributeInvalid.fixture';
        require_once $file;
        $errors = $this->gatherAnalyserErrors([$file]);
        self::assertSame(['gianTiaga.spiralOpenApi.emptyId', 'gianTiaga.spiralOpenApi.invalidId', 'gianTiaga.spiralOpenApi.duplicateId'], \array_map(callback: static fn($error): ?string => $error->getIdentifier(), array: $errors));
    }
    protected function getRule(): Rule
    {
        return new OpenApiAttributeRule();
    }
}
