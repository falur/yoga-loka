<?php

declare(strict_types=1);

namespace Tools\PHPStan\Tests\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\Rules\RequireNamedArgumentsRule;

/**
 * @extends RuleTestCase<RequireNamedArgumentsRule>
 */
final class RequireNamedArgumentsRuleTest extends RuleTestCase
{
    public function testAllowsNamedArgumentsAndSinglePositionalArguments(): void
    {
        $file = __DIR__ . '/Fixtures/NamedArgumentsAllowed.php';

        require_once $file;

        $this->analyse([$file], []);
    }

    public function testRejectsCallsWithMultipleUnnamedOrdinaryArguments(): void
    {
        $file = __DIR__ . '/Fixtures/NamedArgumentsForbidden.php';

        require_once $file;

        $errors = $this->gatherAnalyserErrors([$file]);

        self::assertSame([
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
            'project.namedArgumentsRequired',
        ], \array_map(static fn($error): ?string => $error->getIdentifier(), $errors));
    }

    public function testReportsExpectedLines(): void
    {
        $file = __DIR__ . '/Fixtures/NamedArgumentsForbidden.php';

        require_once $file;

        $this->analyse([$file], [
            ['Calls with two or more ordinary arguments must use named arguments.', 7],
            ['Calls with two or more ordinary arguments must use named arguments.', 7],
            ['Calls with two or more ordinary arguments must use named arguments.', 16],
            ['Calls with two or more ordinary arguments must use named arguments.', 16],
            ['Calls with two or more ordinary arguments must use named arguments.', 17],
            ['Calls with two or more ordinary arguments must use named arguments.', 18],
            ['Calls with two or more ordinary arguments must use named arguments.', 19],
            ['Calls with two or more ordinary arguments must use named arguments.', 19],
            ['Calls with two or more ordinary arguments must use named arguments.', 20],
            ['Calls with two or more ordinary arguments must use named arguments.', 20],
            ['Calls with two or more ordinary arguments must use named arguments.', 21],
            ['Calls with two or more ordinary arguments must use named arguments.', 21],
            ['Calls with two or more ordinary arguments must use named arguments.', 25],
            ['Calls with two or more ordinary arguments must use named arguments.', 25],
        ]);
    }

    protected function getRule(): Rule
    {
        return new RequireNamedArgumentsRule(reflectionProvider: self::createReflectionProvider());
    }
}
