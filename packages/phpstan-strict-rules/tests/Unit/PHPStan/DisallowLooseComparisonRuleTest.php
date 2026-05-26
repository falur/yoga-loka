<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use GianTiaga\PhpStanStrictRules\Rules\DisallowLooseComparisonRule;

/**
 * @extends RuleTestCase<DisallowLooseComparisonRule>
 */
final class DisallowLooseComparisonRuleTest extends RuleTestCase
{
    public function testAllowsStrictComparisonsAndOtherBinaryOperators(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/LooseComparisonsAllowed.php'], []);
    }

    public function testRejectsLooseComparisons(): void
    {
        $file = __DIR__ . '/Fixtures/LooseComparisonsForbidden.php';

        $errors = $this->gatherAnalyserErrors([$file]);

        self::assertSame([
            'gianTiaga.phpstanStrictRules.looseEqualForbidden',
            'gianTiaga.phpstanStrictRules.looseNotEqualForbidden',
            'gianTiaga.phpstanStrictRules.looseNotEqualForbidden',
        ], \array_map(static fn($error): ?string => $error->getIdentifier(), $errors));

        $this->analyse([$file], [
            ['Loose comparison with == is forbidden. Use === instead.', 11],
            ['Loose not-equal comparison is forbidden. Use !== instead.', 12],
            ['Loose not-equal comparison is forbidden. Use !== instead.', 13],
        ]);
    }

    protected function getRule(): Rule
    {
        return new DisallowLooseComparisonRule();
    }
}
