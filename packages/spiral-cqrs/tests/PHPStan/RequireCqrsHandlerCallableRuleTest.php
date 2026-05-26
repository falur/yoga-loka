<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use GianTiaga\SpiralCqrs\PHPStan\Rules\RequireCqrsHandlerCallableRule;

/**
 * @extends RuleTestCase<RequireCqrsHandlerCallableRule>
 */
final class RequireCqrsHandlerCallableRuleTest extends RuleTestCase
{
    public function testAllowsFirstClassCallableOnHandle(): void
    {
        $file = __DIR__ . '/Fixtures/CqrsHandlerCallableAllowed.php';

        require_once $file;

        $this->analyse(files: [$file], expectedErrors: []);
    }

    public function testRejectsInvalidHandlerCallableFormsAndTransactionalQueryHandlers(): void
    {
        $file = __DIR__ . '/Fixtures/CqrsHandlerCallableForbidden.fixture';

        require_once $file;

        $errors = $this->gatherAnalyserErrors([$file]);

        self::assertSame(expected: [
            'gianTiaga.spiralCqrs.handlerCallableRequired',
            'gianTiaga.spiralCqrs.handlerCallableRequired',
            'gianTiaga.spiralCqrs.handlerCallableRequired',
            'gianTiaga.spiralCqrs.transactionalQueryHandler',
        ], actual: \array_map(
            callback: static fn($error): ?string => $error->getIdentifier(),
            array: $errors,
        ));
    }

    protected function getRule(): Rule
    {
        return new RequireCqrsHandlerCallableRule();
    }
}
