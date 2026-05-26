<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan;

use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\FileTypeMapper;
use GianTiaga\PhpStanStrictRules\Rules\TypeContractRule;
use GianTiaga\PhpStanStrictRules\TypeContracts\PhpDocContractTypeCollector;
use GianTiaga\PhpStanStrictRules\TypeContracts\TypeContractInspector;

/**
 * @extends RuleTestCase<TypeContractRule>
 */
final class TypeContractRuleTest extends RuleTestCase
{
    public function testAllowsSimpleNamedContracts(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/AllowedContracts.php'], []);
    }

    public function testRejectsForbiddenContracts(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/ForbiddenContracts.php']);

        self::assertSame([
            'gianTiaga.phpstanStrictRules.noArrayShapeType',
            'gianTiaga.phpstanStrictRules.noNestedArrayType',
            'gianTiaga.phpstanStrictRules.noArrayShapeType',
            'gianTiaga.phpstanStrictRules.noNestedArrayType',
            'gianTiaga.phpstanStrictRules.noNestedArrayType',
        ], \array_map(static fn($error): ?string => $error->getIdentifier(), $errors));

        $this->analyse([__DIR__ . '/Fixtures/ForbiddenContracts.php'], [
            ['Type contracts must not contain nested arrays.', 7],
            ['Type contracts must not contain array shapes or tuple types.', 7],
            ['Type contracts must not contain array shapes or tuple types.', 30],
            ['Type contracts must not contain nested arrays.', 35],
            ['Type contracts must not contain nested arrays.', 47],
        ]);
    }

    public function testRejectsMissingPhpDocGenerics(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/MissingGenericContracts.php'], [
            ['Type contracts must specify generic types instead of relying on implicit mixed.', 7],
            ['Type contracts must specify generic types instead of relying on implicit mixed.', 13],
            ['Type contracts must specify generic types instead of relying on implicit mixed.', 24],
        ]);
    }

    protected function getRule(): Rule
    {
        return new TypeContractRule(
            new TypeContractInspector(),
            self::getContainer()->getByType(FileTypeMapper::class),
            new PhpDocContractTypeCollector(self::getContainer()->getByType(TypeNodeResolver::class)),
        );
    }
}
