<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan;

use GianTiaga\PhpStanStrictRules\Rules\DisallowSetBasedWriteRule;
use GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan\Fixtures\SetBasedWriteTestMarker;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<DisallowSetBasedWriteRule>
 */
final class DisallowSetBasedWriteRuleTest extends RuleTestCase
{
    public function testAllowsWriteInMarkedClassAndEntityManagerDelete(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/SetBasedWriteAllowed.php'], []);
    }

    public function testRejectsWriteOnDatabaseInterfaceWithoutMarker(): void
    {
        $file = __DIR__ . '/Fixtures/SetBasedWriteForbidden.php';

        $errors = $this->gatherAnalyserErrors([$file]);

        self::assertSame([
            'gianTiaga.phpstanStrictRules.setBasedWriteForbidden',
            'gianTiaga.phpstanStrictRules.setBasedWriteForbidden',
            'gianTiaga.phpstanStrictRules.setBasedWriteForbidden',
        ], \array_map(static fn($error): string|null => $error->getIdentifier(), $errors));

        $this->analyse([$file], [
            [
                'Set-based запись update() на DatabaseInterface разрешена только в классах, реализующих SetBasedWrite. Обычное изменение состояния — через Entity + EntityManager.',
                17,
            ],
            [
                'Set-based запись insert() на DatabaseInterface разрешена только в классах, реализующих SetBasedWrite. Обычное изменение состояния — через Entity + EntityManager.',
                18,
            ],
            [
                'Set-based запись delete() на DatabaseInterface разрешена только в классах, реализующих SetBasedWrite. Обычное изменение состояния — через Entity + EntityManager.',
                19,
            ],
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/set-based-write.neon'];
    }

    protected function getRule(): Rule
    {
        return new DisallowSetBasedWriteRule(SetBasedWriteTestMarker::class);
    }
}
