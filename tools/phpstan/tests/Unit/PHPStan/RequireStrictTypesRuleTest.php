<?php

declare(strict_types=1);

namespace Tools\PHPStan\Tests\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\Rules\RequireStrictTypesRule;

/**
 * @extends RuleTestCase<RequireStrictTypesRule>
 */
final class RequireStrictTypesRuleTest extends RuleTestCase
{
    public function testAllowsFirstStrictTypesDeclare(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/StrictTypesValid.php'], []);
    }

    public function testRejectsMissingDeclare(): void
    {
        $this->assertMissingStrictTypesError(__DIR__ . '/Fixtures/StrictTypesMissingDeclare.fixture');
    }

    public function testRejectsDisabledStrictTypesDeclare(): void
    {
        $this->assertMissingStrictTypesError(__DIR__ . '/Fixtures/StrictTypesDisabled.fixture');
    }

    public function testRejectsInvalidStrictTypesDeclareValue(): void
    {
        $this->assertMissingStrictTypesError(__DIR__ . '/Fixtures/StrictTypesInvalidValue.fixture');
    }

    public function testRejectsDeclareWithoutFirstStrictTypesDeclare(): void
    {
        $this->assertMissingStrictTypesError(__DIR__ . '/Fixtures/StrictTypesSeveralDeclares.fixture');
    }

    public function testRejectsStrictTypesDeclareAfterNamespace(): void
    {
        $this->assertMissingStrictTypesError(__DIR__ . '/Fixtures/StrictTypesAfterNamespace.fixture');
    }

    protected function getRule(): Rule
    {
        return new RequireStrictTypesRule();
    }

    private function assertMissingStrictTypesError(string $file): void
    {
        $errors = $this->gatherAnalyserErrors([$file]);

        $this->assertSame(
            ['project.missingStrictTypes'],
            \array_map(static fn($error): ?string => $error->getIdentifier(), $errors),
        );

        $this->analyse([$file], [
            ['Every analysed PHP file must start with declare(strict_types=1).', 1],
        ]);
    }
}
